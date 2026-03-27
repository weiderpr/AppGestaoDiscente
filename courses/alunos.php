<?php
/**
 * Vértice Acadêmico — Alunos de uma Turma
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user    = getCurrentUser();
$allowed = ['Administrador', 'Coordenador', 'Professor', 'Pedagogo', 'Assistente Social', 'Psicólogo'];
$isProfessor = $user && $user['profile'] === 'Professor';
$isCoord     = $user && $user['profile'] === 'Coordenador';
$isAdmin     = $user && $user['profile'] === 'Administrador';
$isPedagogo  = $user && in_array($user['profile'], ['Pedagogo', 'Assistente Social', 'Psicólogo']);
$canComment  = $isProfessor || $isCoord || $isAdmin || $isPedagogo;
if (!$user || !in_array($user['profile'], $allowed)) {
    header('Location: /dashboard.php');
    exit;
}

$db     = getDB();
$inst   = getCurrentInstitution();
$instId = $inst['id'];

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

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

            // 1. Verifica se aluno já existe (pela matrícula)
            $stExist = $db->prepare('SELECT id, photo FROM alunos WHERE matricula = ? LIMIT 1');
            $stExist->execute([$matricula]);
            $aluno = $stExist->fetch();

            if ($aluno) {
                $alunoId = $aluno['id'];
                $photoPath = $aluno['photo'];
                // Atualiza dados se necessário (opcional, vamos atualizar nome/tel/email)
                $db->prepare('UPDATE alunos SET nome=?, telefone=?, email=? WHERE id=?')
                   ->execute([$nome, $telefone, $email, $alunoId]);
            } else {
                // Novo aluno
                $photoPath = null;
                if (!empty($_FILES['photo']['tmp_name'])) {
                    $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','webp'];
                    if (in_array($ext, $allowed)) {
                        $destDir  = __DIR__ . '/../assets/uploads/alunos/';
                        $fileName = uniqid('student_', true) . '.' . $ext;
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $destDir . $fileName)) {
                            $photoPath = 'assets/uploads/alunos/' . $fileName;
                        }
                    }
                }
                $stIns = $db->prepare('INSERT INTO alunos (matricula, nome, telefone, email, photo) VALUES (?,?,?,?,?)');
                $stIns->execute([$matricula, $nome, $telefone, $email, $photoPath]);
                $alunoId = $db->lastInsertId();
            }

            // 2. Vincula à turma (se não estiver vinculado)
            $db->prepare('INSERT IGNORE INTO turma_alunos (turma_id, aluno_id) VALUES (?,?)')
               ->execute([$turmaId, $alunoId]);

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
        // Verifica matrícula única (exceto este aluno)
        $stM = $db->prepare('SELECT id FROM alunos WHERE matricula=? AND id!=? LIMIT 1');
        $stM->execute([$matricula, $aid]);
        if ($stM->fetch()) {
            $error = 'Já existe outro aluno com esta matrícula.';
        } else {
            // Upload de nova foto
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
            
            $db->prepare('UPDATE alunos SET matricula=?, nome=?, telefone=?, email=?, photo=? WHERE id=?')
               ->execute([$matricula, $nome, $telefone, $email, $photoPath, $aid]);
            $success = 'Dados do aluno atualizados com sucesso!';
        }
    }
}
if ($action === 'remove' && !empty($_POST['aluno_id'])) {
    $aid = (int)$_POST['aluno_id'];
    $db->prepare('DELETE FROM turma_alunos WHERE turma_id=? AND aluno_id=?')
       ->execute([$turmaId, $aid]);
    // Também remove se for representante desta turma específica
    $db->prepare('DELETE FROM turma_representantes WHERE turma_id=? AND aluno_id=?')
       ->execute([$turmaId, $aid]);
    $success = 'Aluno removido desta turma.';
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
        $stImp = $db->prepare('INSERT IGNORE INTO turma_alunos (turma_id, aluno_id) VALUES (?,?)');
        foreach ($studentIds as $sid) {
            $stImp->execute([$turmaId, (int)$sid]);
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
    SELECT a.* 
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

// ---- CONTAGEM DE ALUNOS SEM TURMA ----
$unlinkedCount = $db->query("
    SELECT COUNT(*) 
    FROM alunos a 
    LEFT JOIN turma_alunos ta ON ta.aluno_id = a.id 
    WHERE ta.aluno_id IS NULL
")->fetchColumn();

$pageTitle = 'Alunos — ' . $turma['description'];
$extraJS = [
    '/assets/js/sentiment_system.js?v=1.2',
    '/assets/js/performance_system.js?v=1.6'
];
require_once __DIR__ . '/../includes/header.php';
?>

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
    <?php if (!in_array($user['profile'], ['Professor', 'Pedagogo', 'Assistente Social', 'Psicólogo'])): ?>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="/courses/turmas.php?course_id=<?= $courseId ?>" class="btn btn-secondary">← Voltar</a>
        <button class="btn btn-secondary" onclick="openModal('importFileModal')">📊 Importar Excel/CSV</button>
        <button class="btn btn-secondary" onclick="openModal('importModal')">📥 Importar de Turma</button>
        <button class="btn btn-primary" onclick="openModal('alunoModal')">➕ Novo Aluno</button>
    </div>
    <?php elseif (in_array($user['profile'], ['Pedagogo', 'Assistente Social', 'Psicólogo'])): ?>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="/courses/turmas.php?course_id=<?= $courseId ?>" class="btn btn-secondary">← Voltar para Turmas</a>
    </div>
    <?php else: ?>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="/courses/turmas.php?course_id=<?= $courseId ?>" class="btn btn-secondary">← Voltar para Turmas</a>
    </div>
    <?php endif; ?>
</div>

<?php if ($success): ?>
<div class="alert alert-success fade-in" style="margin-bottom:1.5rem;">✅ <?= $success ?> <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;">✕</button></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger fade-in" style="margin-bottom:1.5rem;">⚠️ <?= $error ?> <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;">✕</button></div>
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
                    <?php if ($isAdmin || $isCoord || $isPedagogo): ?>
                    <th style="width:200px;">Tendência (Análise Quantitativa)</th>
                    <?php endif; ?>
                    <th style="width:200px;">Tendência (Análise Qualitativa)</th>
                    <th style="text-align:center; width:<?= ($isAdmin || $isCoord || $isPedagogo) ? '160px' : '80px' ?>;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alunos)): ?>
                <tr><td colspan="<?= ($isAdmin || $isCoord || $isPedagogo) ? '5' : '4' ?>" style="text-align:center;padding:3rem;color:var(--text-muted);">Nenhum aluno vinculado a esta turma.</td></tr>
                <?php endif; ?>
                <?php foreach ($alunos as $a): ?>
                <tr>
                    <td>
                        <?php if ($a['photo'] && file_exists(__DIR__ . '/../' . $a['photo'])): ?>
                            <img src="/<?= htmlspecialchars($a['photo']) ?>" class="aluno-photo">
                        <?php else: ?>
                            <div class="aluno-initials"><?= strtoupper(substr($a['nome'],0,1)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:600;color:var(--color-primary);"><?= htmlspecialchars($a['matricula']) ?></td>
                    <td style="font-weight:600;">
                        <div><?= htmlspecialchars($a['nome']) ?></div>
                        <?php if ($isAdmin || $isCoord || $isPedagogo): ?>
                        <div style="font-size:.75rem;color:var(--text-muted);font-weight:400;"><?= htmlspecialchars($a['email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin || $isCoord || $isPedagogo): ?>
                    <td>
                        <div id="perf-trend-<?= $a['id'] ?>" class="performance-trend-container" data-aluno-id="<?= $a['id'] ?>" data-turma-id="<?= $turmaId ?>"></div>
                    </td>
                    <?php endif; ?>
                    <td>
                        <div id="trend-<?= $a['id'] ?>" class="sentiment-trend-container" data-aluno-id="<?= $a['id'] ?>" data-turma-id="<?= $turmaId ?>"></div>
                    </td>
                    <td style="text-align:center;">
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <button type="button" class="action-btn" title="Atendimento Profissional" onclick="openAtendimentoModal({aluno_id: <?= $a['id'] ?>, target_name: '<?= addslashes($a['nome']) ?>', aluno_photo: '<?= $a['photo'] ?>', turma_id: <?= $turmaId ?>})">📝</button>
                            <button type="button" class="action-btn" title="Adicionar Comentário" onclick="openCommentModal(<?= htmlspecialchars(json_encode(['id' => $a['id'], 'nome' => $a['nome'], 'photo' => $a['photo'], 'photo_url' => ($a['photo'] && file_exists(__DIR__.'/../'.$a['photo']) ? '/'.$a['photo'] : null)]), ENT_QUOTES) ?>, <?= $turmaId ?>)">💬</button>
                            
                            <?php if ($isAdmin || $isCoord): ?>
                            <button type="button" class="action-btn" title="Editar"
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)">✏️</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Desvincular aluno da turma?')">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="aluno_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="action-btn danger" title="Remover da Turma">✕</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Novo Aluno -->
<?php $hideModals = in_array($user['profile'], ['Pedagogo', 'Assistente Social', 'Psicólogo']); ?>
<div class="modal-backdrop" id="alunoModal" role="dialog" <?= $hideModals ? 'style="display:none;"' : '' ?>>
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">👤 Novo Aluno</span>
            <button class="modal-close" onclick="closeModal('alunoModal')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Matrícula (11 dígitos) <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">🆔</span>
                        <input type="text" name="matricula" class="form-control" maxlength="11" placeholder="Ex: 20240001" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nome Completo <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">👤</span>
                        <input type="text" name="nome" class="form-control" placeholder="Nome do aluno" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">E-mail</label>
                        <div class="input-group">
                            <span class="input-icon">✉️</span>
                            <input type="email" name="email" class="form-control" placeholder="aluno@email.com">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telefone</label>
                        <div class="input-group">
                            <span class="input-icon">📱</span>
                            <input type="tel" name="telefone" class="form-control" placeholder="(00) 00000-0000">
                        </div>
                    </div>
                </div>
                <div class="form-group">
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
</div>

<!-- Modal: Editar Aluno -->
<div class="modal-backdrop" id="editAlunoModal" role="dialog" <?= $hideModals ? 'style="display:none;"' : '' ?>>
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">✏️ Editar Dados do Aluno</span>
            <button class="modal-close" onclick="closeModal('editAlunoModal')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="aluno_id" id="edit_aluno_id">
            <input type="hidden" name="current_photo" id="edit_current_photo">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Matrícula <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">🆔</span>
                        <input type="text" name="matricula" id="edit_matricula" class="form-control" maxlength="11" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nome Completo <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">👤</span>
                        <input type="text" name="nome" id="edit_nome" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">E-mail</label>
                        <div class="input-group">
                            <span class="input-icon">✉️</span>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telefone</label>
                        <div class="input-group">
                            <span class="input-icon">📱</span>
                            <input type="tel" name="telefone" id="edit_telefone" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-group">
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
</div>

<!-- Modal: Importar -->
<div class="modal-backdrop" id="importModal" role="dialog" <?= $hideModals ? 'style="display:none;"' : '' ?>>
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">📥 Importar Alunos</span>
            <button class="modal-close" onclick="closeModal('importModal')">✕</button>
        </div>
        <form method="POST">
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
<div class="modal-backdrop" id="importFileModal" role="dialog" <?= $hideModals ? 'style="display:none;"' : '' ?>>
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">📊 Importar Alunos via Arquivo</span>
            <button class="modal-close" onclick="closeModal('importFileModal')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
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
function openModal(id)  { document.getElementById(id).classList.add('show'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('show'); document.body.style.overflow=''; }
window.onclick = function(event) { if (event.target.classList.contains('modal-backdrop')) { event.target.classList.remove('show'); document.body.style.overflow=''; } }

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
                ? `<img src="/${a.photo}" style="width:32px; height:32px; border-radius:50%; object-fit:cover; flex-shrink:0;">`
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
<script src="/assets/js/student_comments.js?v=1.1"></script>
<?php require_once __DIR__ . '/../includes/student_comment_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/atendimento_modal.php'; ?>


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

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Inicializa tendências qualitativas
    document.querySelectorAll('.sentiment-trend-container').forEach(container => {
        VASentiment.renderTrend(container, container.dataset.alunoId, container.dataset.turmaId);
    });

    // Inicializa tendências quantitativas (notas)
    document.querySelectorAll('.performance-trend-container').forEach(container => {
        VAPerformance.renderTrend(container, container.dataset.alunoId, container.dataset.turmaId);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
