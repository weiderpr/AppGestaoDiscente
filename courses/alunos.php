<?php
/**
 * Vértice Acadêmico — Alunos de uma Turma
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/AlunoService.php';
require_once __DIR__ . '/../src/App/Services/TurmaService.php';

requireLogin();

$user    = getCurrentUser();
hasDbPermission('students.index'); // Nova verificação RBAC

$isProfessor = $user && $user['profile'] === 'Professor';
$isCoord     = $user && $user['profile'] === 'Coordenador';
$isAdmin     = $user && $user['profile'] === 'Administrador';
$isPedagogo  = $user && in_array($user['profile'], ['Pedagogo', 'Assistente Social', 'Psicólogo']);

// canComment agora checa se tem a permissão específica ou é um dos perfis clássicos (para compatibilidade)
$canComment  = hasDbPermission('students.comments', false) || $isProfessor || $isCoord || $isAdmin || $isPedagogo;

$db     = getDB();
$inst   = getCurrentInstitution();
$instId = $inst['id'];

$alunoService = new \App\Services\AlunoService();
$turmaService = new \App\Services\TurmaService();

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// UI Standard Components
require_once __DIR__ . '/../includes/toast.php';
require_once __DIR__ . '/../includes/loading.php';
require_once __DIR__ . '/../includes/modal.php';
require_once __DIR__ . '/../includes/components/AtendimentoMedals.php';

// ---- AJAX: BUSCAR ALUNOS DE OUTRA TURMA ----
if (isset($_GET['api']) && $_GET['api'] === 'get_students') {
    $tid = $_GET['source_id'] ?? '';
    
    if ($tid === 'unlinked') {
        // Busca alunos que não estão em NENHUMA turma
        $st = getDB()->prepare("
            SELECT a.id, a.nome, a.matricula, a.photo 
            FROM alunos a 
            LEFT JOIN turma_alunos ta ON ta.aluno_id = a.id 
            WHERE ta.aluno_id IS NULL 
            ORDER BY a.nome ASC
        ");
        $st->execute();
    } else {
        $tid      = (int)$tid;
        $targetId = (int)($_GET['target_id'] ?? 0);
        
        // Busca alunos QUE ESTÃO na turma de origem ($tid)
        // E (opcionalmente) que NÃO ESTÃO na turma de destino ($targetId)
        $sql = "
            SELECT a.id, a.nome, a.matricula, a.photo 
            FROM alunos a 
            INNER JOIN turma_alunos ta_src ON ta_src.aluno_id = a.id AND ta_src.turma_id = ?
        ";
        $params = [$tid];

        if ($targetId > 0) {
            $sql .= " LEFT JOIN turma_alunos ta_tgt ON ta_tgt.aluno_id = a.id AND ta_tgt.turma_id = ?";
            $sql .= " WHERE ta_tgt.aluno_id IS NULL";
            $params[] = $targetId;
        }

        $st = getDB()->prepare($sql . " ORDER BY a.nome ASC");
        $st->execute($params);
    }
    header('Content-Type: application/json');
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ---- ABRIR MODAL DE COMENTÁRIOS VIA GET (para conselho de classe) ----
$openCommentModal = false;
$commentAlunoId = 0;
$commentAlunoNome = '';
$commentAlunoPhoto = null;
$commentAlunoPhotoUrl = null;

$turmaId = (int)($_GET['turma_id'] ?? 0);

if (isset($_GET['comment']) && (int)$_GET['comment'] > 0) {
    $commentAlunoId = (int)$_GET['comment'];
    $commentTurmaId = (int)($_GET['turma_id'] ?? 0);
    if ($commentTurmaId > 0) {
        $turmaId = $commentTurmaId;
        $openCommentModal = true;
        
        $stAluno = $db->prepare('SELECT nome, photo FROM alunos WHERE id = ?');
        $stAluno->execute([$commentAlunoId]);
        $alunoInfo = $stAluno->fetch();
        if ($alunoInfo) {
            $commentAlunoNome = $alunoInfo['nome'];
            $commentAlunoPhoto = $alunoInfo['photo'] ?? null;
            $commentAlunoPhotoUrl = ($commentAlunoPhoto && file_exists(__DIR__.'/../'.$commentAlunoPhoto)) ? '/'.$commentAlunoPhoto : null;
        }
    }
}
if (!$turmaId) { header('Location: /courses/index.php'); exit; }

// Busca a turma e o curso para garantir contexto e permissão
$stTurma = $db->prepare('
    SELECT t.*, c.name as course_name, c.institution_id 
    FROM turmas t
    INNER JOIN courses c ON c.id = t.course_id
    WHERE t.id = ? AND c.institution_id = ?
    LIMIT 1
');
$stTurma->execute([$turmaId, $instId]);
$turma = $stTurma->fetch();

if (!$turma) { header('Location: /courses/index.php'); exit; }

$courseId = $turma['course_id'];

// Segurança: Coordenador só vê os seus cursos
if ($user['profile'] === 'Coordenador') {
    $stCheck = $db->prepare('SELECT 1 FROM course_coordinators WHERE course_id=? AND user_id=? LIMIT 1');
    $stCheck->execute([$courseId, $user['id']]);
    if (!$stCheck->fetch()) {
        header('Location: /courses/index.php');
        exit;
    }
}

// Segurança: Professor só vê turmas que leciona
if ($isProfessor) {
    $stCheck = $db->prepare('
        SELECT 1 
        FROM turmas t
        JOIN turma_disciplinas td ON t.id = td.turma_id
        JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id
        WHERE t.id = ? AND tdp.professor_id = ? 
        LIMIT 1
    ');
    $stCheck->execute([$turmaId, $user['id']]);
    if (!$stCheck->fetch()) {
        header('Location: /courses/index.php');
        exit;
    }
}

$success = '';
$error   = '';
$action  = $_POST['action'] ?? '';

// ---- CRIAR / VINCULAR ALUNO ----
if ($action === 'create') {
    $matricula = trim($_POST['matricula'] ?? '');
    $nome      = trim($_POST['nome']      ?? '');
    $telefone  = trim($_POST['telefone']  ?? '');
    $email     = trim($_POST['email']     ?? '');

    if (empty($matricula) || empty($nome)) {
        $error = 'Matrícula e Nome são obrigatórios.';
    } else {
        try {
            $db->beginTransaction();

            $aluno = $alunoService->findByMatricula($matricula);

            if ($aluno) {
                $alunoId = $aluno['id'];
                $alunoService->update($alunoId, [
                    'nome' => $nome,
                    'telefone' => $telefone,
                    'email' => $email
                ]);
            } else {
                $photoPath = null;
                if (!empty($_FILES['photo']['tmp_name'])) {
                    $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                        $destDir  = __DIR__ . '/../assets/uploads/alunos/';
                        $fileName = uniqid('student_', true) . '.' . $ext;
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $destDir . $fileName)) {
                            $photoPath = 'assets/uploads/alunos/' . $fileName;
                        }
                    }
                }
                $resultCreate = $alunoService->create([
                    'matricula' => $matricula,
                    'nome'      => $nome,
                    'telefone'  => $telefone,
                    'email'     => $email,
                    'photo'     => $photoPath
                ]);
                $alunoId = $resultCreate['id'];
            }

            $turmaService->addAluno($turmaId, $alunoId);

            $db->commit();
            $success = "Aluno «{$nome}» vinculado com sucesso!";
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Erro ao processar: ' . $e->getMessage();
        }
    }
}

// ---- ATUALIZAR ALUNO ----
if ($action === 'update' && !empty($_POST['aluno_id'])) {
    $aid       = (int)$_POST['aluno_id'];
    $matricula = trim($_POST['matricula'] ?? '');
    $nome      = trim($_POST['nome']      ?? '');
    $telefone  = trim($_POST['telefone']  ?? '');
    $email     = trim($_POST['email']     ?? '');

    if (empty($matricula) || empty($nome)) {
        $error = 'Matrícula e Nome são obrigatórios.';
    } else {
        $existing = $alunoService->findByMatricula($matricula);
        if ($existing && $existing['id'] != $aid) {
            $error = 'Já existe outro aluno com esta matrícula.';
        } else {
            $photoPath = $_POST['current_photo'] ?? null;
            if (!empty($_FILES['photo']['tmp_name'])) {
                $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $fileName = uniqid('student_', true) . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../assets/uploads/alunos/' . $fileName)) {
                        $photoPath = 'assets/uploads/alunos/' . $fileName;
                    }
                }
            }
            
            $alunoService->update($aid, [
                'matricula' => $matricula,
                'nome' => $nome,
                'telefone' => $telefone,
                'email' => $email,
                'photo' => $photoPath
            ]);
            $success = 'Dados do aluno atualizados com sucesso!';
        }
    }
}
if ($action === 'remove' && !empty($_POST['aluno_id'])) {
    $aid = (int)$_POST['aluno_id'];
    if ($turmaService->removeAluno($turmaId, $aid)) {
        // Também remove se for representante desta turma específica
        $db->prepare('DELETE FROM turma_representantes WHERE turma_id=? AND aluno_id=?')
           ->execute([$turmaId, $aid]);
        $success = 'Aluno removido desta turma.';
    }
}

// ---- IMPORTAR DE OUTRA TURMA ----
if ($action === 'import' && !empty($_POST['source_turma_id'])) {
    $sourceId   = (int)$_POST['source_turma_id'];
    $studentIds = $_POST['student_ids'] ?? [];

    if ($sourceId === $turmaId) {
        $error = 'Selecione uma turma diferente da atual.';
    } elseif (empty($studentIds)) {
        $error = 'Selecione ao menos um aluno para importar.';
    } else {
        foreach ($studentIds as $sid) {
            $turmaService->addAluno($turmaId, (int)$sid);
        }
        $success = count($studentIds) . ' aluno(s) importado(s) com sucesso!';
    }
}

// ---- IMPORTAR VIA ARQUIVO (CSV) ----
if ($action === 'import_file' && !empty($_FILES['import_file']['tmp_name'])) {
    $file = $_FILES['import_file']['tmp_name'];
    $handle = fopen($file, "r");
    $imported = 0;
    $errors = 0;

    // Tenta detectar delimitador (vírgula ou ponto-e-vírgula)
    $header = fgets($handle);
    rewind($handle);
    $delimiter = (str_contains($header, ';')) ? ';' : ',';

    $db->beginTransaction();
    try {
        while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            // Pula cabeçalho se houver "matricula" no texto
            if (str_contains(strtolower($data[0] ?? ''), 'matri')) continue;
            
            $matricula = trim($data[0] ?? '');
            $nome      = trim($data[1] ?? '');

            if (empty($matricula) || empty($nome)) continue;

            // 1. Garante que o aluno existe
            $stEx = $db->prepare('SELECT id FROM alunos WHERE matricula = ? LIMIT 1');
            $stEx->execute([$matricula]);
            $aluno = $stEx->fetch();

            if ($aluno) {
                $alunoId = $aluno['id'];
                $db->prepare('UPDATE alunos SET nome = ? WHERE id = ?')->execute([$nome, $alunoId]);
            } else {
                $db->prepare('INSERT INTO alunos (matricula, nome) VALUES (?,?)')->execute([$matricula, $nome]);
                $alunoId = $db->lastInsertId();
            }

            // 2. Vincula à turma
            $db->prepare('INSERT IGNORE INTO turma_alunos (turma_id, aluno_id) VALUES (?,?)')
               ->execute([$turmaId, $alunoId]);
            
            $imported++;
        }
        $db->commit();
        $success = "Importação concluída: {$imported} alunos processados.";
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erro na importação: " . $e->getMessage();
    }
    fclose($handle);
}

// ---- LISTAR ALUNOS DA TURMA ----
$search = trim($_GET['search'] ?? '');
$sql = "
    SELECT a.*, 
           (SELECT COUNT(*) FROM sancao WHERE aluno_id = a.id AND status != 'Cancelado') as total_sancoes
    FROM alunos a
    INNER JOIN turma_alunos ta ON ta.aluno_id = a.id
    WHERE ta.turma_id = ?
";
$params = [$turmaId];
if ($search) {
    $sql .= " AND (a.nome LIKE ? OR a.matricula LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY a.nome ASC";
$st = $db->prepare($sql);
$st->execute($params);
$alunos = $st->fetchAll();

// ---- OUTRAS TURMAS PARA IMPORTAR ----
$otherTurmas = $db->prepare('
    SELECT t.id, t.description, t.ano, c.name as course_name 
    FROM turmas t
    INNER JOIN courses c ON c.id = t.course_id
    WHERE c.institution_id = ? AND t.id != ?
    ORDER BY c.name, t.ano DESC, t.description ASC
');
$otherTurmas->execute([$instId, $turmaId]);
$otherTurmas = $otherTurmas->fetchAll();

// ---- BUSCAR ATENDIMENTOS ATIVOS PARA AS MEDALHAS ----
$activeAtendimentosMap = [];
if (!empty($alunos)) {
    $alunoIds = array_column($alunos, 'id');
    $placeholders = implode(',', array_fill(0, count($alunoIds), '?'));
    
    $stAtend = $db->prepare("
        SELECT at.aluno_id, u.name, u.profile, u.photo
        FROM gestao_atendimentos at
        JOIN gestao_atendimento_usuarios gau ON at.id = gau.atendimento_id
        JOIN users u ON gau.usuario_id = u.id
        WHERE at.aluno_id IN ($placeholders) 
          AND at.status IN ('Aberto', 'Em Atendimento') 
          AND at.is_archived = 0 
          AND at.deleted_at IS NULL
          AND at.institution_id = ?
    ");
    $stAtend->execute(array_merge($alunoIds, [$instId]));
    
    foreach ($stAtend->fetchAll(PDO::FETCH_ASSOC) as $at) {
        $activeAtendimentosMap[$at['aluno_id']][] = $at;
    }
}

// ---- CONTAGEM DE ALUNOS SEM TURMA ----
$unlinkedCount = $db->query("
    SELECT COUNT(*) 
    FROM alunos a 
    LEFT JOIN turma_alunos ta ON ta.aluno_id = a.id 
    WHERE ta.aluno_id IS NULL
")->fetchColumn();

$pageTitle = 'Alunos — ' . $turma['description'];
$extraJS = [
    '/assets/js/performance_system.js?v=1.6',
    '/assets/js/sancao_popover.js?v=1.0'
];

require_once __DIR__ . '/../includes/header.php'; 
renderModalStyles(); 
?>
<link rel="stylesheet" href="/assets/css/sancao_popover.css?v=1.0">

<style>
.alunos-table-wrap { overflow-x:auto; border-radius:var(--radius-lg); }
.alunos-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.alunos-table th {
    padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:600;
    text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted);
    background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color);
}
.alunos-table td { padding:.875rem 1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; }
.alunos-table tr:last-child td { border-bottom:none; }
.alunos-table tr:hover td { background:var(--bg-hover); }
.action-btn {
    display:inline-flex; align-items:center; justify-content:center;
    width:32px; height:32px; border-radius:var(--radius-md);
    border:1px solid var(--border-color); background:var(--bg-surface);
    color:var(--text-muted); cursor:pointer; font-size:.875rem;
    transition:all var(--transition-fast); text-decoration:none;
    padding:0; line-height:1;
}
.action-btn:hover { background:var(--bg-hover); color:var(--text-primary); }
.action-btn.danger:hover { background:#fef2f2; color:var(--color-danger); border-color:var(--color-danger); }
[data-theme="dark"] .action-btn.danger:hover { background:#450a0a; }
.aluno-photo { width:40px; height:40px; border-radius:50%; object-fit:cover; background:var(--bg-surface-2nd); }
.aluno-initials { width:40px; height:40px; border-radius:50%; background:var(--gradient-brand); color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.875rem; }
.modal-footer { padding:1.25rem 2rem; border-top:1px solid var(--border-color);
    display:flex; gap:.75rem; justify-content:flex-end; }

/* Modal 80% e Abas */
.modal-80 { 
    width:80vw !important; height:80vh !important; max-width:none !important; 
    display:flex !important; flex-direction:column !important;
}
.modal-80 .modal-body { flex:1; overflow-y:auto; padding:1.5rem 2rem; }

/* Estilos para o Modal de Alunos (80%) */
#alunoModal .modal, #editAlunoModal .modal { width: 80vw; height: 80vh; max-width: none; display: flex; flex-direction: column; overflow: hidden; }
#alunoModal .modal-body, #editAlunoModal .modal-body { flex: 1; overflow-y: auto; padding: 1.5rem; }
#alunoModal .modal-footer, #editAlunoModal .modal-footer { flex-shrink: 0; }

/* Abas do Modal */
.tabs-nav { display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 0; background: var(--bg-surface-2nd); padding: 0 1rem; flex-shrink: 0; }
.tab-btn { background: none; border: none; padding: 0.75rem 1.25rem; font-size: 0.875rem; font-weight: 600; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem; }
.tab-btn:hover { color: var(--color-primary); background: var(--bg-surface); }
.tab-btn.active { color: var(--color-primary); border-bottom-color: var(--color-primary); background: var(--bg-surface); }
.tab-btn[disabled] { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }
.tab-content { display: none; flex: 1; flex-direction: column; overflow: hidden; height: 100%; }
.tab-content.active { display: flex !important; animation: fadeIn 0.3s ease; }

/* Sub-abas NAAPI */
.naapi-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0; }
.naapi-tab-btn { background: none; border: none; padding: 0.5rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; }
.naapi-tab-btn:hover { color: var(--text-secondary); }
.naapi-tab-btn.active { color: var(--color-primary); border-bottom-color: var(--color-primary); }

/* Estilos de Anexos */
.anexo-item { display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1rem; background: var(--bg-surface-2nd); border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 0.5rem; transition: all 0.2s ease; }
.anexo-item:hover { border-color: var(--color-primary); background: var(--bg-surface); }
.anexo-icon { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: var(--bg-surface); border-radius: var(--radius-sm); font-size: 1.25rem; }
.anexo-info { flex: 1; min-width: 0; }
.anexo-name { font-weight: 600; font-size: 0.875rem; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary); }
.anexo-meta { font-size: 0.75rem; color: var(--text-muted); }
.anexo-actions { display: flex; gap: 0.25rem; }
</style>

<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.25rem;">
            <a href="/courses/index.php" style="color:var(--color-primary);">📚 Cursos</a>
            &nbsp;›&nbsp; <?= htmlspecialchars($turma['course_name']) ?>
            &nbsp;›&nbsp; <a href="/courses/turmas.php?course_id=<?= $courseId ?>" style="color:var(--color-primary);"><?= htmlspecialchars($turma['description']) ?></a>
        </div>
        <h1 class="page-title">👤 Alunos da Turma</h1>
        <p class="page-subtitle">Turma: <strong><?= htmlspecialchars($turma['description']) ?> (<?= $turma['ano'] ?>)</strong></p>
    </div>
    <?php if (hasDbPermission('students.manage', false)): ?>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="/courses/turmas.php?course_id=<?= $courseId ?>" class="btn btn-secondary">← Voltar</a>
        <button class="btn btn-secondary" onclick="openModal('importFileModal')">📊 Importar Excel/CSV</button>
        <button class="btn btn-secondary" onclick="openModal('importModal')">📥 Importar de Turma</button>
        <button class="btn btn-primary" onclick="openModal('alunoModal')">➕ Novo Aluno</button>
    </div>
    <?php else: ?>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="/courses/turmas.php?course_id=<?= $courseId ?>" class="btn btn-secondary">← Voltar</a>
    </div>
    <?php endif; ?>
</div>

<?php if ($success): ?>
    <script>document.addEventListener('DOMContentLoaded', () => Toast.show(<?= json_encode($success) ?>, 'success'));</script>
<?php endif; ?>

<?php if ($error): ?>
    <script>document.addEventListener('DOMContentLoaded', () => Toast.show(<?= json_encode($error) ?>, 'danger'));</script>
<?php endif; ?>

<!-- Filtro -->
<div class="card fade-in" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.5rem;">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
            <div class="form-group" style="flex:1;min-width:220px;margin:0;">
                <div class="input-group">
                    <span class="input-icon">🔍</span>
                    <input type="text" name="search" class="form-control" placeholder="Buscar por nome ou matrícula..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($search): ?>
            <a href="/courses/alunos.php?turma_id=<?= $turmaId ?>" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabela -->
<div class="card fade-in">
    <div class="card-header">
        <span class="card-title">Alunos Matriculados</span>
        <span style="font-size:.875rem;color:var(--text-muted);"><?= count($alunos) ?> aluno(s)</span>
    </div>
    <div class="alunos-table-wrap">
        <table class="alunos-table">
            <thead>
                <tr>
                    <th style="width:70px;">Foto</th>
                    <th>Matrícula</th>
                    <th>Nome Completo</th>
                    <?php if ($isAdmin || $isCoord || $isPedagogo || $isProfessor): ?>
                    <th style="width:110px;text-align:center;">Tendências</th>
                    <?php endif; ?>
                    <th style="text-align:center; width:<?= ($isAdmin || $isCoord || $isPedagogo) ? '160px' : '80px' ?>;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alunos)): ?>
                <tr><td colspan="<?= ($isAdmin || $isCoord || $isPedagogo || $isProfessor) ? '5' : '4' ?>" style="text-align:center;padding:3rem;color:var(--text-muted);">Nenhum aluno vinculado a esta turma.</td></tr>
                <?php endif; ?>
                <?php foreach ($alunos as $a): ?>
                <tr>
                    <td>
                        <?php if ($a['photo'] && file_exists(__DIR__ . '/../' . $a['photo'])): ?>
                            <img src="/<?= htmlspecialchars($a['photo']) ?>" class="aluno-photo" data-preview-image="/<?= htmlspecialchars($a['photo']) ?>" style="cursor:zoom-in;">
                        <?php else: ?>
                            <div class="aluno-initials"><?= strtoupper(substr($a['nome'],0,1)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:600;color:var(--color-primary);"><?= htmlspecialchars($a['matricula']) ?></td>
                    <td style="font-weight:600;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span><?= htmlspecialchars($a['nome']) ?></span>
                            <?= renderAtendimentoMedals($a['id'], $activeAtendimentosMap[$a['id']] ?? []) ?>
                            <?php if (($a['total_sancoes'] ?? 0) > 0): ?>
                                <span class="sancao-popover-trigger" data-aluno-id="<?= $a['id'] ?>" style="display:inline-flex; align-items:center; gap:0.25rem; font-size:0.7rem; font-weight:700; color:#ef4444; background:#fef2f2; border:1px solid #fca5a5; padding:1px 6px; border-radius:12px; cursor: help;" title="Passe o mouse para ver detalhes">
                                    ⚠️ <?= $a['total_sancoes'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($isAdmin || $isCoord || $isPedagogo): ?>
                        <div style="font-size:.75rem;color:var(--text-muted);font-weight:400;"><?= htmlspecialchars($a['email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin || $isCoord || $isPedagogo || $isProfessor): ?>
                    <td style="text-align:center;">
                        <div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;">
                            <?php if ($isAdmin || $isCoord || $isPedagogo): ?>
                            <div id="perf-trend-<?= $a['id'] ?>" 
                                 class="performance-trend-container" 
                                 data-aluno-id="<?= $a['id'] ?>" 
                                 data-turma-id="<?= $turmaId ?>"
                                 title="Tendência de Notas"
                                 style="display:inline-flex;align-items:center;"></div>
                            <span style="color:var(--border-color);font-size:0.7rem;">|</span>
                            <?php endif; ?>
                            <div id="trend-<?= $a['id'] ?>" 
                                 class="sentiment-trend-container" 
                                 data-aluno-id="<?= $a['id'] ?>" 
                                 data-turma-id="<?= $turmaId ?>"
                                 title="Tendência de Comentários"
                                 style="display:inline-flex;align-items:center;"></div>
                        </div>
                    </td>
                    <?php endif; ?>
                    <td style="text-align:center;">
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <button type="button" class="action-btn" title="Atendimento Profissional" onclick="openAtendimentoModal({aluno_id: <?= $a['id'] ?>, target_name: '<?= addslashes($a['nome']) ?>', aluno_photo: '<?= $a['photo'] ?>', turma_id: <?= $turmaId ?>})">📝</button>
                            <button type="button" class="action-btn" title="Adicionar Comentário" onclick="openCommentModal(<?= htmlspecialchars(json_encode(['id' => $a['id'], 'nome' => $a['nome'], 'photo' => $a['photo'], 'photo_url' => ($a['photo'] && file_exists(__DIR__.'/../'.$a['photo']) ? '/'.$a['photo'] : null)]), ENT_QUOTES) ?>, <?= $turmaId ?>)">💬</button>
                            <button type="button" class="action-btn" title="Histórico Multidisciplinar" onclick="openHistoryModal(<?= $a['id'] ?>)">🕒</button>
                            <button type="button" class="action-btn" title="Grade Horária / Horários" onclick="openScheduleModal(<?= $a['id'] ?>, '<?= addslashes($a['nome']) ?>', '<?= $a['photo'] ?>')">🗓️</button>
                            
                            <?php if (hasDbPermission('students.manage', false)): ?>
                            <button type="button" class="action-btn" title="Editar"
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)">✏️</button>
                            <span class="actions-group">
                            <form method="POST" id="unlinkForm_<?= $a['id'] ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="aluno_id" value="<?= $a['id'] ?>">
                                <button type="button" class="action-btn danger" title="Remover da Turma" onclick="confirmUnlink(<?= $a['id'] ?>)">✕</button>
                            </form>
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Histórico Multidisciplinar -->
<div class="modal-backdrop" id="historyModal" role="dialog" style="display:none;">
    <div class="modal" style="width:90vw;max-width:1000px;height:90vh;display:flex;flex-direction:column;">
        <div class="modal-header">
            <span class="modal-title">🕒 Histórico Multidisciplinar</span>
            <button class="modal-close" onclick="closeHistoryModal()">✕</button>
        </div>
        <div id="historyModalContent" style="flex:1;overflow-y:auto;background:var(--bg-surface-2nd);">
            <!-- Conteúdo AJAX -->
        </div>
    </div>
</div>

<!-- Modal: Grade Horária / Schedule Grid -->
<div class="modal-backdrop" id="scheduleModal" role="dialog" style="display:none;">
    <div class="modal" style="width:85vw;max-width:1100px;height:85vh;display:flex;flex-direction:column;overflow:hidden;">
        <div class="modal-header">
            <span class="modal-title" id="scheduleModalTitle">🗓️ Grade Horária Semanal</span>
            <button class="modal-close" onclick="closeScheduleModal()">✕</button>
        </div>
        <div id="scheduleModalContent" style="flex:1;overflow:hidden;background:var(--bg-surface-2nd);display:flex;flex-direction:column;">
            <!-- Conteúdo AJAX -->
        </div>
    </div>
</div>

<!-- Modal: Novo Aluno -->
<?php $hideModals = in_array($user['profile'], ['Pedagogo', 'Assistente Social', 'Psicólogo']); ?>
<div class="modal-backdrop" id="alunoModal" role="dialog" style="display:none;">
    <div class="modal modal-80">
        <div class="modal-header">
            <span class="modal-title">👤 Novo Aluno</span>
            <button class="modal-close" onclick="closeModal('alunoModal')">✕</button>
        </div>
        
        <div class="tabs-nav">
            <button class="tab-btn active" data-tab="new-dados" onclick="switchModalTab('alunoModal', 'new-dados')">Dados Cadastrais</button>
            <button class="tab-btn" data-tab="new-naapi" onclick="switchModalTab('alunoModal', 'new-naapi')">NAAPI</button>
            <button class="tab-btn" disabled title="Em breve">Documentos</button>
        </div>

        <div id="new-dados" class="tab-content active">
            <form method="POST" enctype="multipart/form-data" style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-body" style="flex:1; overflow-y:auto;">
                    <div class="form-group">
                        <label class="form-label">Matrícula (11 dígitos) <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🆔</span>
                            <input type="text" name="matricula" class="form-control" maxlength="11" placeholder="Ex: 20240001" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:1rem;">
                        <label class="form-label">Nome Completo <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">👤</span>
                            <input type="text" name="nome" class="form-control" placeholder="Nome do aluno" required>
                        </div>
                    </div>
                    <div class="form-row" style="display:flex; gap:1rem; margin-top:1rem;">
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">E-mail</label>
                            <div class="input-group">
                                <span class="input-icon">✉️</span>
                                <input type="email" name="email" class="form-control" placeholder="aluno@email.com">
                            </div>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">Telefone</label>
                            <div class="input-group">
                                <span class="input-icon">📱</span>
                                <input type="tel" name="telefone" class="form-control" placeholder="(00) 00000-0000">
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:1rem;">
                        <label class="form-label">Foto de Perfil</label>
                        <div class="input-group">
                            <span class="input-icon">📸</span>
                            <input type="file" name="photo" class="form-control" accept="image/*" style="padding-left:2.75rem;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('alunoModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">💾 Salvar Aluno</button>
                </div>
            </form>
        </div>

        <div id="new-naapi" class="tab-content">
             <div class="modal-body" style="flex:1; overflow-y:auto; display:flex; align-items:center; justify-content:center;">
                <div style="padding:3rem 1rem; text-align:center; color:var(--text-muted);">
                    <div style="font-size:3rem; margin-bottom:1rem;">ℹ️</div>
                    <p>Para gerenciar os dados do NAAPI, primeiro salve o cadastro básico do aluno.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('alunoModal')">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Editar Aluno -->
<div class="modal-backdrop" id="editAlunoModal" role="dialog" style="display:none;">
    <div class="modal modal-80">
        <div class="modal-header">
            <span class="modal-title">✏️ Editar Dados do Aluno</span>
            <button class="modal-close" onclick="closeModal('editAlunoModal')">✕</button>
        </div>

        <div class="tabs-nav">
            <button class="tab-btn active" data-tab="edit-dados" onclick="switchModalTab('editAlunoModal', 'edit-dados')">Dados Cadastrais</button>
            <?php if (hasDbPermission('naapi.index', false)): ?>
                <button class="tab-btn" data-tab="edit-naapi" onclick="switchModalTab('editAlunoModal', 'edit-naapi')">NAAPI</button>
            <?php endif; ?>
            <button class="tab-btn" disabled title="Em breve">Histórico Acadêmico</button>
        </div>

        <div id="edit-dados" class="tab-content active">
            <form method="POST" enctype="multipart/form-data" style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="aluno_id" id="edit_aluno_id">
                <input type="hidden" name="current_photo" id="edit_current_photo">
                
                <div class="modal-body" style="flex:1; overflow-y:auto;">
                    <div class="form-group">
                        <label class="form-label">Matrícula <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🆔</span>
                            <input type="text" name="matricula" id="edit_matricula" class="form-control" maxlength="11" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:1rem;">
                        <label class="form-label">Nome Completo <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">👤</span>
                            <input type="text" name="nome" id="edit_nome" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row" style="display:flex; gap:1rem; margin-top:1rem;">
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">E-mail</label>
                            <div class="input-group">
                                <span class="input-icon">✉️</span>
                                <input type="email" name="email" id="edit_email" class="form-control">
                            </div>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">Telefone</label>
                            <div class="input-group">
                                <span class="input-icon">📱</span>
                                <input type="tel" name="telefone" id="edit_telefone" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:1rem;">
                        <label class="form-label">Alterar Foto</label>
                        <div class="input-group">
                            <span class="input-icon">📸</span>
                            <input type="file" name="photo" class="form-control" accept="image/*" style="padding-left:2.75rem;">
                        </div>
                        <div id="edit_photo_preview_container" style="margin-top:0.5rem; display:none; align-items:center; gap:0.75rem;">
                            <img id="edit_photo_preview" src="" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                            <span style="font-size:0.75rem; color:var(--text-muted);">Foto atual</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editAlunoModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">💾 Salvar Alterações</button>
                </div>
            </form>
        </div>

        <div id="edit-naapi" class="tab-content">
            <div id="naapi-content-container" style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
                <!-- Conteúdo carregado via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editAlunoModal')">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Importar -->
<div class="modal-backdrop" id="importModal" role="dialog" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">📥 Importar Alunos</span>
            <button class="modal-close" onclick="closeModal('importModal')">✕</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Turma de Origem</label>
                    <div class="input-group">
                        <span class="input-icon">🏫</span>
                        <select id="source_turma_id" name="source_turma_id" class="form-control" required onchange="loadStudentsForImport(this.value)">
                            <option value="">Selecione a origem...</option>
                            <option value="unlinked" style="font-weight:700; color:var(--color-primary);">📁 Alunos sem turma vinculada (<?= $unlinkedCount ?>)</option>
                            <optgroup label="Turmas Existentes">
                                <?php foreach ($otherTurmas as $ot): ?>
                                <option value="<?= $ot['id'] ?>">
                                    [<?= $ot['ano'] ?>] <?= htmlspecialchars($ot['course_name']) ?> — <?= htmlspecialchars($ot['description']) ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div id="import_students_container" style="display:none; margin-top:1rem;">
                    <label class="form-label">Selecione os Alunos</label>
                    <div style="max-height:200px; overflow-y:auto; border:1px solid var(--border-color); border-radius:var(--radius-md); background:var(--bg-surface-2nd); padding:0.5rem;">
                        <div id="import_students_list" style="display:flex; flex-direction:column; gap:0.5rem;">
                            <!-- Preenchido via JS -->
                        </div>
                    </div>
                    <div style="margin-top:0.5rem; font-size:0.75rem; color:var(--text-muted); display:flex; gap:1rem;">
                        <a href="javascript:void(0)" onclick="toggleSelection(true)">Marcar todos</a>
                        <a href="javascript:void(0)" onclick="toggleSelection(false)">Desmarcar todos</a>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('importModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">📥 Importar Agora</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Importar Arquivo -->
<div class="modal-backdrop" id="importFileModal" role="dialog" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">📊 Importar Alunos via Arquivo</span>
            <button class="modal-close" onclick="closeModal('importFileModal')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_file">
            <div class="modal-body">
                <div style="padding:1rem; border-radius:var(--radius-md); background:var(--bg-surface-2nd); border:1px dashed var(--border-color); margin-bottom:0.5rem;">
                    <p style="font-size:0.875rem; font-weight:600; margin-bottom:0.5rem; color:var(--text-primary);">📝 Instruções do Arquivo:</p>
                    <ul style="font-size:0.8125rem; color:var(--text-muted); padding-left:1.25rem;">
                        <li>O arquivo deve ser um **CSV** (salvo como Excel CSV).</li>
                        <li>Deve conter 2 colunas: **Matrícula** e **Nome**.</li>
                        <li>A primeira linha (cabeçalho) será ignorada.</li>
                        <li>Exemplo: `2024001;João Silva`</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label class="form-label">Selecione o arquivo (.csv)</label>
                    <div class="input-group">
                        <span class="input-icon">📄</span>
                        <input type="file" name="import_file" class="form-control" accept=".csv" required style="padding-left:2.75rem;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('importFileModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">🚀 Iniciar Importação</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id)  { 
    const modal = document.getElementById(id);
    if (!modal) return;

    modal.style.display = 'flex'; 
    setTimeout(() => {
        modal.classList.add('show'); 
    }, 10);
    
    document.body.style.overflow='hidden'; 

    // Garante que a primeira aba esteja ativa ao abrir (apenas se houver abas)
    const firstTab = modal.querySelector('.tabs-nav .tab-btn:not([disabled])');
    if (firstTab) {
        switchModalTab(id, firstTab.dataset.tab);
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;

    modal.classList.remove('show');
    document.body.style.overflow = '';
    
    setTimeout(() => {
        if (!modal.classList.contains('show')) {
            modal.style.display = 'none';
        }
    }, 250);
}

function switchModalTab(modalId, tabId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    // Deactivate all
    modal.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    modal.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Activate target
    const targetBtn = modal.querySelector(`.tab-btn[data-tab="${tabId}"]`);
    const targetContent = modal.querySelector(`#${tabId}`);
    
    if (targetBtn) targetBtn.classList.add('active');
    if (targetContent) targetContent.classList.add('active');

    // Lógica específica para abas
    if (tabId === 'edit-naapi') {
        const alunoId = document.getElementById('edit_aluno_id').value;
        loadNaapiData(alunoId);
    }
}

async function loadNaapiData(alunoId) {
    const container = document.getElementById('naapi-content-container');
    container.innerHTML = `<div style="padding:3rem; text-align:center; color:var(--text-muted);">
        <div style="width:40px; height:40px; border:3px solid var(--border-color); border-top-color:var(--color-primary); border-radius:50%; animation:spin 1s linear infinite; margin:0 auto 1rem;"></div>
        Carregando informações do NAAPI...
    </div>`;

    try {
        const resp = await fetch(`/api/aluno_naapi.php?aluno_id=${alunoId}`);
        const data = await resp.json();

        if (data.error) throw new Error(data.error);

        if (data.id) {
            renderNaapiForm(data);
        } else {
            renderNaapiOnboarding(alunoId);
        }
    } catch (e) {
        container.innerHTML = `<div class="alert alert-danger" style="margin:2rem;">Erro ao carregar dados: ${e.message}</div>`;
    }
}

function renderNaapiOnboarding(alunoId) {
    const container = document.getElementById('naapi-content-container');
    container.innerHTML = `
        <div style="padding:4rem 2rem; text-align:center;">
            <div style="font-size:4rem; margin-bottom:1.5rem;">🧠</div>
            <h3 style="margin-bottom:1rem; color:var(--text-primary);">Vincular Aluno ao NAAPI</h3>
            <p style="color:var(--text-muted); max-width:400px; margin:0 auto 2rem;">
                Este aluno ainda não possui registro no Núcleo de Atendimento às Pessoas com Necessidades Específicas.
            </p>
            <button type="button" class="btn btn-primary" onclick="renderNaapiForm({aluno_id: ${alunoId}, data_inclusao: '${new Date().toISOString().split('T')[0]}'})">
                ➕ Incluir Aluno no NAAPI
            </button>
        </div>
    `;
}

function renderNaapiForm(data) {
    const container = document.getElementById('naapi-content-container');
    container.innerHTML = `
        <div style="animation:fadeIn 0.3s ease; height: 100%; display: flex; flex-direction: column;">
            <div style="background:var(--bg-surface-2nd); padding:0.5rem 1.5rem; border-radius:var(--radius-md) var(--radius-md) 0 0; border-bottom:1px solid var(--border-color); flex-shrink:0;">
                <div class="naapi-tabs">
                    <button type="button" class="naapi-tab-btn active" data-naapi-tab="ficha" onclick="switchNaapiSubTab('ficha')">📋 Ficha de Acompanhamento</button>
                    <button type="button" class="naapi-tab-btn" data-naapi-tab="anexos" onclick="switchNaapiSubTab('anexos')">📎 Documentos e Anexos</button>
                </div>
            </div>
            <div style="padding:1.5rem; flex:1; overflow-y:auto;">
                <!-- Sub-aba: Ficha -->
                <div id="naapi-sub-ficha" class="naapi-sub-content" style="display:block;">
                    <div id="naapiFormContainer">
                        <input type="hidden" name="aluno_id" id="naapi_aluno_id" value="${data.aluno_id}">
                        <div class="form-row" style="display:flex; gap:1.5rem; margin-bottom:1.5rem;">
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Data de Inclusão <span class="required">*</span></label>
                                <div class="input-group">
                                    <span class="input-icon">📅</span>
                                    <input type="date" name="data_inclusao" id="naapi_data_inclusao" class="form-control" value="${data.data_inclusao || ''}" required>
                                </div>
                            </div>
                            <div class="form-group" style="flex:2;">
                                <label class="form-label">Neurodivergência / Condição</label>
                                <div class="input-group">
                                    <span class="input-icon">🧩</span>
                                    <input type="text" name="neurodivergencia" id="naapi_neurodivergencia" class="form-control" value="${data.neurodivergencia || ''}" placeholder="Ex: TEA, TDAH, Dislexia...">
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:1.5rem;">
                            <label class="form-label">Campo Texto / Detalhes</label>
                            <textarea name="campo_texto" id="naapi_campo_texto" class="form-control" style="min-height:100px; padding:0.75rem;" placeholder="Descrição detalhada do atendimento...">${data.campo_texto || ''}</textarea>
                        </div>

                        <div class="form-group" style="margin-bottom:1.5rem;">
                            <label class="form-label">Observações Públicas</label>
                            <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem;">⚠️ Estas observações podem ser visíveis por outros perfis autorizados.</div>
                            <textarea name="observacoes_publicas" id="naapi_observacoes_publicas" class="form-control" style="min-height:80px; padding:0.75rem;" placeholder="Notas que podem ser compartilhadas...">${data.observacoes_publicas || ''}</textarea>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                            ${data.id ? `<button type="button" class="btn btn-secondary" style="color:var(--color-danger); border-color:rgba(239,68,68,0.2); background:rgba(239,68,68,0.05);" onclick="deleteNaapiRecord(${data.aluno_id})">🗑️ Excluir Registro</button>` : ''}
                            <button type="button" class="btn btn-primary" onclick="saveNaapiData()">💾 Salvar Dados NAAPI</button>
                        </div>
                    </div>
                </div>

                <!-- Sub-aba: Anexos -->
                <div id="naapi-sub-anexos" class="naapi-sub-content" style="display:none;">
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h4 style="margin:0; font-size:0.9rem; font-weight:700;">Gestão de Documentos</h4>
                        <button class="btn btn-secondary btn-sm" onclick="openAddNaapiAnexoModal(${data.aluno_id})">
                            📎 Adicionar Anexo
                        </button>
                    </div>
                    <div id="naapiAnexosList">
                        <!-- Carregado via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Se estivermos na aba de anexos, carregar
    if (currentNaapiSubTab === 'anexos') {
        switchNaapiSubTab('anexos');
    }
}

let currentNaapiSubTab = 'ficha';

function switchNaapiSubTab(tabId) {
    currentNaapiSubTab = tabId;
    document.querySelectorAll('.naapi-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.naapiTab === tabId);
    });
    document.querySelectorAll('.naapi-sub-content').forEach(content => {
        content.style.display = content.id === `naapi-sub-${tabId}` ? 'block' : 'none';
    });

    if (tabId === 'anexos') {
        const alunoId = document.getElementById('naapi_aluno_id')?.value || document.getElementById('edit_aluno_id')?.value;
        if (alunoId) {
            loadNaapiAnexos(alunoId);
        }
    }
}

async function loadNaapiAnexos(alunoId) {
    const container = document.getElementById('naapiAnexosList');
    container.innerHTML = '<div style="padding:2rem; text-align:center; color:var(--text-muted);">Carregando anexos...</div>';

    try {
        const resp = await fetch(`/api/aluno_naapi.php?action=fetch_anexos&aluno_id=${alunoId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (data.success) {
            renderNaapiAnexos(data.anexos);
        } else {
            throw new Error(data.error);
        }
    } catch (e) {
        container.innerHTML = `<div class="alert alert-danger">Erro ao carregar anexos: ${e.message}</div>`;
    }
}

function renderNaapiAnexos(anexos) {
    const container = document.getElementById('naapiAnexosList');
    if (anexos.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted); background: var(--bg-surface-2nd); border-radius: var(--radius-md); border: 2px dashed var(--border-color);">
                <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3;">📁</div>
                <p>Nenhum documento anexado ainda.</p>
            </div>
        `;
        return;
    }

    let h = '';
    anexos.forEach(a => {
        const dateStr = new Date(a.created_at).toLocaleDateString();
        const icon = a.extensao === 'pdf' ? '📄' : '🖼️';
        const sizeStr = a.tamanho ? (a.tamanho / 1024 / 1024).toFixed(2) + ' MB' : '';

        h += `
            <div class="anexo-item">
                <div class="anexo-icon">${icon}</div>
                <div class="anexo-info">
                    <span class="anexo-name" title="${a.descricao || 'Sem descrição'}">${a.descricao || 'Arquivo .' + a.extensao}</span>
                    <div class="anexo-meta">${dateStr} • ${a.extensao.toUpperCase()} ${sizeStr ? ' • ' + sizeStr : ''} • Por ${a.author_name}</div>
                </div>
                <div class="anexo-actions">
                    <button class="btn btn-secondary btn-sm" onclick="viewAnexo('/${a.arquivo}', '${a.descricao || ''}', '${a.extensao}')">👁️</button>
                    <button class="btn btn-secondary btn-sm" style="color:#ef4444;" onclick="deleteNaapiAnexo(${a.id})">🗑️</button>
                </div>
            </div>
        `;
    });
    container.innerHTML = h;
}

function openAddNaapiAnexoModal(alunoId) {
    const targetInput = document.getElementById('naapiAnexoAlunoId');
    if (!targetInput) {
        console.error('Campo naapiAnexoAlunoId não encontrado');
        return;
    }
    
    // Se o ID não veio via parâmetro, tenta pegar do campo oculto do NAAPI
    if (!alunoId) {
        alunoId = document.getElementById('naapi_aluno_id')?.value;
    }

    if (!alunoId) {
        Toast.error('ID do aluno não identificado.');
        return;
    }

    targetInput.value = alunoId;
    document.getElementById('naapiAnexoDescricao').value = '';
    const fileInput = document.getElementById('naapiAnexoFile');
    if (fileInput) fileInput.value = '';
    
    openModal('modalAddNaapiAnexo');
}

async function submitNaapiAnexo() {
    const fileInput = document.getElementById('naapiAnexoFile');
    const alunoId = document.getElementById('naapiAnexoAlunoId').value;
    const descricao = document.getElementById('naapiAnexoDescricao').value;

    if (!fileInput.files[0]) {
        Toast.error('Selecione um arquivo.');
        return;
    }

    const formData = new FormData();
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    formData.append('action', 'upload_anexo');
    formData.append('csrf_token', csrfToken);
    formData.append('aluno_id', alunoId);
    formData.append('descricao', descricao);
    formData.append('arquivo', fileInput.files[0]);

    const btn = document.querySelector('#modalAddNaapiAnexo .btn-primary');
    btn.disabled = true;
    btn.innerHTML = '⌛ Enviando...';

    try {
        const resp = await fetch('/api/aluno_naapi.php', { 
            method: 'POST', 
            headers: { 
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData 
        });
        const res = await resp.json();

        if (res.success) {
            Toast.success('Anexo enviado com sucesso!');
            closeModal('modalAddNaapiAnexo');
            loadNaapiAnexos(alunoId);
        } else {
            throw new Error(res.error);
        }
    } catch (e) {
        Toast.error('Erro no upload: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Fazer Upload';
    }
}

async function deleteNaapiAnexo(anexoId) {
    if (!confirm('Deseja realmente excluir este anexo?')) return;

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    const formData = new FormData();
    formData.append('action', 'delete_anexo');
    formData.append('csrf_token', csrfToken);
    formData.append('anexo_id', anexoId);

    try {
        const resp = await fetch('/api/aluno_naapi.php', { 
            method: 'POST', 
            headers: { 
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData 
        });
        const res = await resp.json();

        if (res.success) {
            Toast.success('Anexo removido.');
            const alunoId = document.getElementById('edit_aluno_id').value;
            loadNaapiAnexos(alunoId);
        } else {
            throw new Error(res.error);
        }
    } catch (e) {
        Toast.error('Erro ao excluir: ' + e.message);
    }
}

function viewAnexo(url, descricao, extensao) {
    const container = document.getElementById('anexoPreviewContainer');
    document.getElementById('viewAnexoTitle').innerText = descricao || 'Visualizar Anexo';
    document.getElementById('downloadAnexoBtn').href = url;
    
    container.innerHTML = '';
    if (extensao === 'pdf') {
        container.innerHTML = `<iframe src="${url}#toolbar=0" style="width:100%; height:100%; border:none;"></iframe>`;
    } else {
        container.innerHTML = `<img src="${url}" style="max-width:100%; max-height:100%; object-fit:contain;">`;
    }
    openModal('modalViewAnexo');
}

async function deleteNaapiRecord(alunoId) {
    if (!confirm('ATENÇÃO: Deseja realmente excluir permanentemente o registro deste aluno no NAAPI? Todos os anexos vinculados também serão removidos.')) return;

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    const formData = new FormData();
    formData.append('action', 'delete_naapi');
    formData.append('csrf_token', csrfToken);
    formData.append('aluno_id', alunoId);

    try {
        const resp = await fetch('/api/aluno_naapi.php', {
            method: 'POST',
            headers: { 
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        });
        const res = await resp.json();

        if (res.success) {
            showSuccess('Registro NAAPI excluído com sucesso.');
            renderNaapiOnboarding(alunoId);
        } else {
            showError('Erro ao excluir registro: ' + (res.error || 'Erro desconhecido'));
        }
    } catch (e) {
        showError('Erro na comunicação com o servidor.');
    }
}

async function saveNaapiData() {
    const btn = document.querySelector('#naapiFormContainer .btn-primary');
    const originalText = btn.innerHTML;
    
    // Coleta manual dos dados para evitar conflito de formulários aninhados
    const alunoId = document.getElementById('naapi_aluno_id').value;
    const dataInclusao = document.getElementById('naapi_data_inclusao').value;
    const neurodivergencia = document.getElementById('naapi_neurodivergencia').value;
    const campoTexto = document.getElementById('naapi_campo_texto').value;
    const observacoesPublicas = document.getElementById('naapi_observacoes_publicas').value;

    if (!dataInclusao) {
        Toast.error('A data de inclusão é obrigatória.');
        return;
    }

    // Busca o token CSRF de algum formulário da página
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    btn.disabled = true;
    btn.innerHTML = '⌛ Salvando...';

    try {
        const formData = new FormData();
        formData.append('action', 'save');
        formData.append('csrf_token', csrfToken); // Envia CSRF via POST
        formData.append('aluno_id', alunoId);
        formData.append('data_inclusao', dataInclusao);
        formData.append('neurodivergencia', neurodivergencia);
        formData.append('campo_texto', campoTexto);
        formData.append('observacoes_publicas', observacoesPublicas);

        const resp = await fetch('/api/aluno_naapi.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest', // Identifica como AJAX para o auth.php
                'X-CSRF-TOKEN': csrfToken           // Envia CSRF via Header também
            },
            body: formData
        });

        // Tenta ler como texto primeiro para dar debug se não for JSON
        const text = await resp.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch(e) {
            console.error('Resposta não-JSON:', text);
            throw new Error('O servidor retornou uma resposta inválida.');
        }

        if (result.success) {
            Toast.success('Dados do NAAPI salvos com sucesso!');
        } else {
            throw new Error(result.message || result.error || 'Erro desconhecido');
        }
    } catch (e) {
        Toast.error('Erro ao salvar: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function loadStudentsForImport(sourceId) {
    const list = document.getElementById('import_students_list');
    const container = document.getElementById('import_students_container');
    
    if (!sourceId) {
        container.style.display = 'none';
        return;
    }

    list.innerHTML = '<div style="padding:1rem; text-align:center; color:var(--text-muted);">Carregando alunos...</div>';
    container.style.display = 'block';

    try {
        const resp = await fetch(`?api=get_students&source_id=${sourceId}&target_id=<?= $turmaId ?>`);
        const data = await resp.json();

        if (data.length === 0) {
            list.innerHTML = '<div style="padding:1rem; text-align:center; color:var(--text-muted);">Nenhum aluno nesta turma.</div>';
            return;
        }

        list.innerHTML = data.map(a => {
            const initials = a.nome.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
            const photoHtml = a.photo 
                ? `<img src="/${a.photo}" style="width:32px; height:32px; border-radius:50%; object-fit:cover; flex-shrink:0; cursor:zoom-in;" data-preview-image="/${a.photo}">`
                : `<div style="width:32px; height:32px; border-radius:50%; background:var(--gradient-brand); color:white; display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700; flex-shrink:0;">${initials}</div>`;
            
            return `
                <label style="display:flex; align-items:center; gap:0.75rem; cursor:pointer; padding:0.4rem 0.5rem; border-radius:var(--radius-sm); transition:background 0.2s;">
                    <input type="checkbox" name="student_ids[]" value="${a.id}" checked style="width:16px; height:16px; flex-shrink:0;">
                    ${photoHtml}
                    <div style="font-size:0.875rem; line-height:1.2; overflow:hidden;">
                        <div style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${a.nome}</div>
                        <div style="font-size:0.75rem; color:var(--text-muted);">Matrícula: ${a.matricula}</div>
                    </div>
                </label>
            `;
        }).join('');

        // Hover effect for labels
        list.querySelectorAll('label').forEach(lbl => {
            lbl.onmouseover = () => lbl.style.background = 'var(--bg-hover)';
            lbl.onmouseout = () => lbl.style.background = 'transparent';
        });

    } catch (e) {
        list.innerHTML = '<div style="padding:1rem; text-align:center; color:var(--color-danger);">Erro ao carregar alunos.</div>';
    }
}

function toggleSelection(check) {
    document.querySelectorAll('#import_students_list input[type="checkbox"]').forEach(i => i.checked = check);
}

function openEditModal(aluno) {
    document.getElementById('edit_aluno_id').value = aluno.id;
    document.getElementById('edit_matricula').value = aluno.matricula;
    document.getElementById('edit_nome').value = aluno.nome;
    document.getElementById('edit_email').value = aluno.email || '';
    document.getElementById('edit_telefone').value = aluno.telefone || '';
    document.getElementById('edit_current_photo').value = aluno.photo || '';
    
    const preview = document.getElementById('edit_photo_preview');
    const container = document.getElementById('edit_photo_preview_container');
    if (aluno.photo) {
        preview.src = '/' + aluno.photo;
        container.style.display = 'flex';
    } else {
        container.style.display = 'none';
    }
    
    openModal('editAlunoModal');
}


</script>
<?php require_once __DIR__ . '/../includes/student_comment_modal.php'; ?>
<script src="/assets/js/student_comments.js?v=2.3"></script>
<?php require_once __DIR__ . '/../includes/atendimento_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/student_schedule_modal.php'; ?>

<script>
async function openHistoryModal(alunoId) {
    if (typeof Loading !== 'undefined') Loading.show('Carregando histórico...');
    
    try {
        const resp = await fetch(`/aluno/historico/${alunoId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const html = await resp.text();
        
        document.getElementById('historyModalContent').innerHTML = html;
        document.getElementById('historyModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    } catch (err) {
        console.error('Erro ao carregar histórico:', err);
        if (typeof Toast !== 'undefined') Toast.error('Erro ao carregar histórico multidisciplinar.');
    } finally {
        if (typeof Loading !== 'undefined') Loading.hide();
    }
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.remove('show');
    document.body.style.overflow = '';
}
</script>


<?php if ($openCommentModal && $commentAlunoId): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const aluno = {
        id: <?= $commentAlunoId ?>,
        nome: '<?= addslashes($commentAlunoNome) ?>',
        photo: <?= $commentAlunoPhoto ? '\'' . addslashes($commentAlunoPhoto) . '\'' : 'null' ?>,
        photo_url: <?= $commentAlunoPhotoUrl ? '\'' . addslashes($commentAlunoPhotoUrl) . '\'' : 'null' ?>
    };
    openCommentModal(aluno, <?= $turmaId ?>);
});
</script>
<?php endif; ?>

<script src="/assets/js/sentiment_system.js"></script>
<script>
function confirmUnlink(alunoId) {
    Modal.confirm({
        title: 'Desvincular Aluno',
        message: 'Deseja realmente desvincular este aluno da turma?',
        confirmText: 'Desvincular',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            showLoading('Processando...');
            document.getElementById('unlinkForm_' + alunoId).submit();
        }
    });
}

function initTrends() {
    const checkAndRender = () => {
        if (typeof VASentiment !== 'undefined') {
            document.querySelectorAll('.sentiment-trend-container').forEach(container => {
                if (!container.innerHTML.trim()) {
                    VASentiment.renderTrend(container, container.dataset.alunoId, container.dataset.turmaId, true);
                }
            });
            if (typeof VAPerformance !== 'undefined') {
                document.querySelectorAll('.performance-trend-container').forEach(container => {
                    if (!container.innerHTML.trim()) {
                        VAPerformance.renderTrend(container, container.dataset.alunoId, container.dataset.turmaId, true);
                    }
                });
            }
        } else {
            setTimeout(checkAndRender, 100);
        }
    };
    checkAndRender();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTrends);
} else {
    initTrends();
}
</script>


<!-- Modal: Adicionar Anexo NAAPI -->
<div id="modalAddNaapiAnexo" class="modal-backdrop" style="z-index: 9000 !important;">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h3>📎 Adicionar Novo Anexo</h3>
            <button class="modal-close" onclick="closeModal('modalAddNaapiAnexo')">×</button>
        </div>
        <div class="modal-body">
            <form id="formAddNaapiAnexo" onsubmit="event.preventDefault(); submitNaapiAnexo();">
                <input type="hidden" id="naapiAnexoAlunoId">
                <div class="form-group">
                    <label>Selecione o Arquivo (PDF ou Imagem)</label>
                    <input type="file" id="naapiAnexoFile" class="form-control" accept=".pdf,image/*" required>
                </div>
                <div class="form-group">
                    <label>Descrição (Opcional)</label>
                    <input type="text" id="naapiAnexoDescricao" class="form-control" placeholder="Ex: Relatório médico, Foto da ocorrência...">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalAddNaapiAnexo')">Cancelar</button>
            <button class="btn btn-primary" onclick="submitNaapiAnexo()">Fazer Upload</button>
        </div>
    </div>
</div>

<!-- Modal: Visualizar Anexo -->
<div id="modalViewAnexo" class="modal-backdrop" style="z-index: 9100 !important;">
    <div class="modal" style="max-width: 90vw; height: 90vh; display: flex; flex-direction: column; overflow: hidden;">
        <div class="modal-header">
            <h3 id="viewAnexoTitle">Visualizar Anexo</h3>
            <div style="display:flex; gap:0.75rem; align-items:center;">
                <a id="downloadAnexoBtn" href="#" download class="btn btn-secondary btn-sm">⬇️ Download</a>
                <button class="modal-close" onclick="closeModal('modalViewAnexo')">×</button>
            </div>
        </div>
        <div class="modal-body" style="flex: 1; padding: 0; overflow: hidden; background: #eee; display: flex; align-items: center; justify-content: center;">
            <div id="anexoPreviewContainer" style="width: 100%; height: 100%; overflow: auto; display: flex; align-items: center; justify-content: center;">
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


