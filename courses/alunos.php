<?php
/**
 * Vértice Acadêmico — Alunos de uma Turma
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user    = getCurrentUser();
$allowed = ['Administrador', 'Coordenador', 'Professor'];
$isProfessor = $user && $user['profile'] === 'Professor';
$isCoord     = $user && $user['profile'] === 'Coordenador';
$isAdmin     = $user && $user['profile'] === 'Administrador';
$canComment  = $isProfessor || $isCoord || $isAdmin;
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
        $tid = (int)$tid;
        $st = getDB()->prepare("
            SELECT a.id, a.nome, a.matricula, a.photo 
            FROM alunos a 
            INNER JOIN turma_alunos ta ON ta.aluno_id = a.id 
            WHERE ta.turma_id = ? 
            ORDER BY a.nome ASC
        ");
        $st->execute([$tid]);
    }
    header('Content-Type: application/json');
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Turma à qual pertencem os alunos
$turmaId = (int)($_GET['turma_id'] ?? 0);
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
.modal-backdrop { position:fixed; inset:0; z-index:3000; background:rgba(0,0,0,.5); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; padding:1rem; opacity:0; visibility:hidden; transition:all .25s ease; }
.modal-backdrop.show { opacity:1; visibility:visible; }
.modal { background:var(--bg-surface); border:1px solid var(--border-color);
    border-radius:var(--radius-xl); width:100%; max-width:520px;
    max-height:90vh; overflow-y:auto; box-shadow:0 25px 60px rgba(0,0,0,.3);
    transform:translateY(20px) scale(.97); transition:all .25s ease; }
.modal-backdrop.show .modal { transform:translateY(0) scale(1); }
.modal-header { padding:1.5rem 2rem; border-bottom:1px solid var(--border-color);
    display:flex; align-items:center; justify-content:space-between; }
.modal-title { font-size:1.0625rem; font-weight:700; color:var(--text-primary); }
.modal-close { width:32px; height:32px; border-radius:var(--radius-md);
    border:1px solid var(--border-color); background:var(--bg-surface);
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    color:var(--text-muted); font-size:1.125rem; transition:all var(--transition-fast); }
.modal-close:hover { background:var(--bg-hover); color:var(--text-primary); }
.modal-body { padding:2rem; display:flex; flex-direction:column; gap:1.125rem; }
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
    <?php if (!$isProfessor): ?>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="/courses/turmas.php?course_id=<?= $courseId ?>" class="btn btn-secondary">← Voltar</a>
        <button class="btn btn-secondary" onclick="openModal('importFileModal')">📊 Importar Excel/CSV</button>
        <button class="btn btn-secondary" onclick="openModal('importModal')">📥 Importar de Turma</button>
        <button class="btn btn-primary" onclick="openModal('alunoModal')">➕ Novo Aluno</button>
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
                    <?php if ($isAdmin || $isCoord): ?>
                    <th>Contato</th>
                    <?php endif; ?>
                    <th style="text-align:center; width:<?= ($isAdmin || $isCoord) ? '160px' : '80px' ?>;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alunos)): ?>
                <tr><td colspan="<?= ($isAdmin || $isCoord) ? '5' : '4' ?>" style="text-align:center;padding:3rem;color:var(--text-muted);">Nenhum aluno vinculado a esta turma.</td></tr>
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
                        <?php if ($isAdmin || $isCoord): ?>
                        <div style="font-size:.75rem;color:var(--text-muted);font-weight:400;"><?= htmlspecialchars($a['email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin || $isCoord): ?>
                    <td style="font-size:.8125rem;"><?= htmlspecialchars($a['telefone'] ?: '—') ?></td>
                    <?php endif; ?>
                    <td style="text-align:center;">
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <button type="button" class="action-btn" title="Adicionar Comentário" onclick='openCommentModal(<?= json_encode(["id" => $a["id"], "nome" => $a["nome"], "photo" => $a["photo"], "photo_url" => ($a["photo"] && file_exists(__DIR__."/../".$a["photo"]) ? "/".$a["photo"] : null)]) ?>)'>💬</button>
                            
                            <?php if ($isAdmin || $isCoord): ?>
                            <button type="button" class="action-btn" title="Editar"
                                    onclick='openEditModal(<?= json_encode($a) ?>)'>✏️</button>
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
<div class="modal-backdrop" id="alunoModal" role="dialog">
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
<div class="modal-backdrop" id="editAlunoModal" role="dialog">
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
<div class="modal-backdrop" id="importModal" role="dialog">
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
<div class="modal-backdrop" id="importFileModal" role="dialog">
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
        const resp = await fetch(`?api=get_students&source_id=${sourceId}`);
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

let currentAlunoId = null;

function openCommentModal(aluno) {
    currentAlunoId = aluno.id;
    currentCommentAlunoId = aluno.id;
    
    document.getElementById('comment_aluno_id').value = aluno.id;
    document.getElementById('comment_aluno_name').textContent = aluno.nome;
    document.getElementById('comment_text').innerHTML = '';
    document.getElementById('comment_history_meu').innerHTML = '<div style="padding:1rem;text-align:center;"><span style="font-size:.875rem;color:var(--text-muted);">Carregando...</span></div>';
    document.getElementById('comment_history_outros').innerHTML = '';
    
    document.querySelectorAll('.comment-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === 'comments');
    });
    document.querySelectorAll('.comment-tab-content').forEach(content => {
        content.style.display = 'none';
    });
    document.getElementById('tab-comments').style.display = 'block';
    
    const photoDiv = document.getElementById('comment_aluno_photo');
    if (aluno.photo && aluno.photo_url) {
        photoDiv.innerHTML = `<img src="${aluno.photo_url}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">`;
    } else {
        const initial = aluno.nome.charAt(0).toUpperCase();
        photoDiv.textContent = initial;
    }
    
    loadComments(aluno.id);
    openModal('commentModal');
}

async function loadComments(alunoId) {
    currentCommentAlunoId = alunoId;
    
    try {
        const resp = await fetch(`/api/comments.php?aluno_id=${alunoId}&turma_id=<?= $turmaId ?>`);
        const data = await resp.json();
        
        if (data.error) {
            const preview = document.getElementById('comment_preview');
            if (preview) preview.innerHTML = `<span style="font-size:.75rem;color:var(--color-danger);">${data.error}</span>`;
            else alert(data.error);
            return;
        }
        
        // Renderiza Meus Comentários Agrupados
        let htmlMeu = '';
        if (data.meus_comentarios && data.meus_comentarios.length > 0) {
            const c0 = data.meus_comentarios[0];
            const initial = (c0.professor_nome || 'P').charAt(0);
            const photoHtml = c0.professor_photo 
                ? `<img src="/${c0.professor_photo}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">`
                : `<div style="width:28px;height:28px;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.75rem;text-transform:uppercase;">${initial}</div>`;

            htmlMeu += `
                <div style="margin-bottom:1.5rem;padding:1rem;background:var(--bg-surface-2nd);border-radius:var(--radius-md);border-left:3px solid var(--color-primary);">
                    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.75rem;">
                        ${photoHtml}
                        <div style="font-size:.875rem;font-weight:700;color:var(--text-primary);">Eu</div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.75rem;">
                        ${data.meus_comentarios.map(c => `
                            <div style="background:var(--bg-surface);padding:.75rem;border-radius:var(--radius-sm);border:1px solid var(--border-color);">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.375rem;">
                                    <span style="font-size:.6875rem;color:var(--text-muted);">${formatDate(c.created_at)}</span>
                                    <button type="button" class="action-btn danger" style="width:24px;height:24px;font-size:.75rem;" onclick="deleteComment(${c.id})" title="Excluir">🗑</button>
                                </div>
                                <div style="font-size:.875rem;line-height:1.5;color:var(--text-primary);">${c.conteudo}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        } else {
            htmlMeu += `<span style="font-size:.75rem;color:var(--text-muted);display:block;margin-bottom:1rem;">Você ainda não comentou sobre este aluno.</span>`;
        }
        document.getElementById('comment_history_meu').innerHTML = htmlMeu;
        
        // Renderiza Comentários de Outros Agrupados por Professor
        let htmlOutros = '';
        if (data.outros_comentarios && data.outros_comentarios.length > 0) {
            // Agrupar por professor_nome
            const groups = {};
            data.outros_comentarios.forEach(c => {
                if (!groups[c.professor_nome]) {
                    groups[c.professor_nome] = {
                        name: c.professor_nome,
                        photo: c.professor_photo,
                        list: []
                    };
                }
                groups[c.professor_nome].list.push(c);
            });

            Object.values(groups).forEach(g => {
                const initial = (g.name || 'P').charAt(0);
                const photoHtml = g.photo 
                    ? `<img src="/${g.photo}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">`
                    : `<div style="width:28px;height:28px;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.75rem;text-transform:uppercase;">${initial}</div>`;
                
                htmlOutros += `
                    <div style="margin-bottom:1.5rem;padding:1rem;background:var(--bg-surface-2nd);border-radius:var(--radius-md);">
                        <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.75rem;">
                            ${photoHtml}
                            <div style="font-size:.875rem;font-weight:700;color:var(--text-primary);">${escapeHtml(g.name)}</div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:.625rem;">
                            ${g.list.map(c => `
                                <div style="background:var(--bg-surface);padding:.75rem;border-radius:var(--radius-sm);border:1px solid var(--border-color);">
                                    <div style="font-size:.6875rem;color:var(--text-muted);margin-bottom:.25rem;">${formatDate(c.created_at)}</div>
                                    <div style="font-size:.875rem;line-height:1.5;color:var(--text-secondary);">${c.conteudo}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            });
        } else {
            htmlOutros += `<span style="font-size:.75rem;color:var(--text-muted);">Nenhum comentário de outros professores.</span>`;
        }
        
        document.getElementById('comment_history_outros').innerHTML = htmlOutros;
    } catch (e) {
        const preview = document.getElementById('comment_preview');
        const msg = `Houve um erro ao carregar os comentários: ${e.message}`;
        if (preview) preview.innerHTML = `<span style="font-size:.75rem;color:var(--color-danger);">${msg}</span>`;
        console.error('Erro ao carregar comentários:', e);
    }
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function saveComment(event) {
    event.preventDefault();
    const btn = event.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    
    // Pega o HTML formatado da div contenteditable
    const conteudo = document.getElementById('comment_text').innerHTML.trim();
    if (!conteudo || conteudo === '<br>') {
        alert('Por favor, digite um comentário.');
        return;
    }
    
    btn.innerHTML = '⏳ Salvando...';
    btn.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('action', 'save_comment');
        formData.append('aluno_id', document.getElementById('comment_aluno_id').value);
        formData.append('turma_id', <?= $turmaId ?>);
        formData.append('conteudo', conteudo);
        
        const resp = await fetch('/api/comments.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await resp.json();
        if (data.error) throw new Error(data.error);
        
        showToast('Comentário publicado com sucesso!', 'success');
        
        // Limpa o campo e recarrega os comentários
        document.getElementById('comment_text').innerHTML = '';
        loadComments(document.getElementById('comment_aluno_id').value);
        
    } catch (e) {
        showToast(e.message || 'Erro ao salvar comentário', 'error');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function deleteComment(id) {
    if (!confirm('Deseja realmente excluir este comentário?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_comment');
        formData.append('comment_id', id);
        
        const resp = await fetch('/api/comments.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await resp.json();
        if (data.error) throw new Error(data.error);
        
        loadComments(document.getElementById('comment_aluno_id').value);
        
    } catch (e) {
        alert(e.message || 'Erro ao excluir comentário');
    }
}

// Comandos de Rich Text Simplificados
function formatText(command) {
    document.execCommand(command, false, null);
    document.getElementById('comment_text').focus();
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}


function showToast(message, type) {
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;bottom:2rem;right:2rem;padding:1rem 1.5rem;border-radius:var(--radius-lg);font-size:.875rem;font-weight:500;z-index:9999;animation:slideIn .3s ease;background:${type === 'success' ? 'var(--color-success)' : 'var(--color-danger)'};color:white;box-shadow:0 4px 12px rgba(0,0,0,.15);`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
}

let currentCommentAlunoId = null;

function switchCommentTab(tabName) {
    const alunoId = currentCommentAlunoId || document.getElementById('comment_aluno_id')?.value;
    if (!alunoId) return;
    
    document.querySelectorAll('.comment-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
    });
    document.querySelectorAll('.comment-tab-content').forEach(content => {
        content.style.display = 'none';
    });
    document.getElementById('tab-' + tabName).style.display = 'block';
    
    if (tabName === 'wordcloud' && typeof generateWordCloud === 'function') {
        generateWordCloud(alunoId);
    }
    if (tabName === 'summary' && typeof generateSummary === 'function') {
        generateSummary(alunoId);
    }
    if (tabName === 'trend' && typeof generateTrend === 'function') {
        generateTrend(alunoId);
    }
}

async function generateWordCloud(alunoId) {
    const loading = document.getElementById('wordcloud_loading');
    const canvas = document.getElementById('wordcloud_canvas');
    const empty = document.getElementById('wordcloud_empty');
    const info = document.getElementById('wordcloud_info');
    
    loading.style.display = 'block';
    canvas.style.display = 'none';
    empty.style.display = 'none';
    info.style.display = 'none';
    
    try {
        const resp = await fetch(`/api/comments.php?aluno_id=${alunoId}&turma_id=<?= $turmaId ?>`);
        const data = await resp.json();
        
        if (!data.todos_comentarios || data.todos_comentarios.length === 0) {
            loading.style.display = 'none';
            empty.style.display = 'block';
            return;
        }
        
const stopWords = new Set(['0','1','2','3','4','5','6','7','8','9','a','e','i','o','v','x','à','é','af','ah','ao','as','aí','da','de','do','eh','em','eu','há','ii','iv','ix','já','me','na','no','né','oh','ok','os','ou','pq','se','só','tb','te','tu','tá','um','vc','vi','xi','xv','às',' cá',' lá',' né','agr','ali','aos','até','bem','com','das','dos','ela','ele','era','foi','for','fui','hei','hão','iii','lhe','mas','meu','msm','nas','nem','nos','num','não','por','pra','pro','que','sem','ser','seu','sou','sua','são','tbm','tem','ter','teu','tlg','tua','tém','têm','uma','vai','vcs','vcê','vii','vos','vou','vão','xii','xiv','como','dela','dele','elas','eles','eram','essa','esse','esta','este','está','fora','haja','isso','isto','lhes','logo','mais','meus','numa','para','pela','pelo','pode','pois','qual','quem','seja','será','seus','suas','terá','teus','teve','tive','tuas','viii','você','xiii','ainda','aluna','aluno','assim','delas','deles','entre','então','essas','esses','estas','estes','estou','estão','fomos','foram','forem','fosse','hajam','houve','mesmo','minha','muito','nossa','nosso','pelas','pelos','poder','porém','sejam','serei','seria','serão','somos','temos','tenha','tenho','terei','teria','terão','tinha','tiver','vamos','visto','vocês','alunas','alunos','aquela','aquele','aquilo','depois','estava','esteja','esteve','estive','formos','fossem','houver','minhas','nossas','nossos','porque','quando','seriam','também','tenham','teriam','tinham','tivera','éramos','aquelas','aqueles','contudo','estamos','estavam','estejam','estiver','fôramos','hajamos','havemos','houvera','houverá','sejamos','seremos','teremos','tivemos','tiveram','tiverem','tivesse','todavia','estivera','fôssemos','houvemos','houveram','houverei','houverem','houveria','houverão','houvesse','seríamos','tenhamos','teríamos','tivermos','tivessem','tínhamos','estejamos','estivemos','estiveram','estiverem','estivesse','estávamos','houveriam','houvermos','houvessem','tivéramos','estivermos','estivessem','houveremos','houvéramos','tivéssemos','estivéramos','houveríamos','houvéssemos','estivéssemos']);
        
        const wordCounts = {};
        let totalWords = 0;
        
        data.todos_comentarios.forEach(comment => {
            const text = comment.conteudo.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ');
            const words = text.toLowerCase().match(/\b[a-záàâãéèêíìîóòôõúùûç]+/g) || [];
            
            words.forEach(word => {
                if (word.length > 2 && !stopWords.has(word)) {
                    wordCounts[word] = (wordCounts[word] || 0) + 1;
                    totalWords++;
                }
            });
        });
        
        const wordList = Object.entries(wordCounts)
            .map(([word, count]) => [word, count])
            .sort((a, b) => b[1] - a[1])
            .slice(0, 50);
        
        if (wordList.length === 0) {
            loading.style.display = 'none';
            empty.style.display = 'block';
            return;
        }
        
        document.getElementById('wordcloud_word_count').textContent = totalWords;
        document.getElementById('wordcloud_comment_count').textContent = data.todos_comentarios.length;
        
        if (typeof WordCloud === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/wordcloud@1.2.2/src/wordcloud2.min.js';
            script.onload = () => drawWordCloud(wordList, canvas);
            document.head.appendChild(script);
        } else {
            drawWordCloud(wordList, canvas);
        }
        
        loading.style.display = 'none';
        canvas.style.display = 'block';
        info.style.display = 'block';
        
    } catch (e) {
        console.error('Erro ao gerar nuvem de palavras:', e);
        loading.style.display = 'none';
        empty.style.display = 'block';
    }
}

function drawWordCloud(wordList, canvas) {
    const maxCount = Math.max(...wordList.map(w => w[1]));
    const minCount = Math.min(...wordList.map(w => w[1]));
    const countRange = maxCount - minCount || 1;
    
    const getColor = () => {
        const colors = ['#4f46e5','#7c3aed','#06b6d4','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
        return colors[Math.floor(Math.random() * colors.length)];
    };
    
    const options = {
        list: wordList.map(([word, count]) => {
            const weight = 12 + ((count - minCount) / countRange) * 40;
            return [word, Math.round(weight)];
        }),
        gridSize: 8,
        weightFactor: 1,
        fontFamily: 'Inter, sans-serif',
        color: getColor,
        rotateRatio: 0.3,
        rotationSteps: 2,
        backgroundColor: 'transparent',
        drawOutOfBound: false,
        shrinkToFit: true,
        wait: 10,
        abortThreshold: 100
    };
    
    WordCloud(canvas, options);
}

async function generateSummary(alunoId) {
    const loading = document.getElementById('summary_loading');
    const content = document.getElementById('summary_content');
    const empty = document.getElementById('summary_empty');
    
    loading.style.display = 'block';
    content.style.display = 'none';
    empty.style.display = 'none';
    
    try {
        const resp = await fetch(`/api/comments.php?aluno_id=${alunoId}&turma_id=<?= $turmaId ?>`);
        const data = await resp.json();
        
        if (!data.todos_comentarios || data.todos_comentarios.length === 0) {
            loading.style.display = 'none';
            empty.style.display = 'block';
            return;
        }
        
        const criticalPositivePhrases = ['parabéns','parabens','parabéns!','parabens!','muito bem','muito bem!','excelente trabalho','trabalho excelente','superou expectativas','acima da média','acima da media','nota máxima','nota maxima','nota 10','nota dez','melhor da turma','destaque da turma','referência','referencia','modelo','exemplo'];
        const strongPositiveWords = ['ótimo','otimo','excelente','maravilhoso','fantástico','fantastico','incrível','incrivel','perfeito','perfeita','extraordinário','extraordinaria','excepcional','brilhante','genial','notável','notavel','impressionante','extraordinário','destacado','destaque','esforçado','esforçada','dedicado','dedicada','exemplar','admirá vel','admiravel'];
        const moderatePositiveWords = ['bom','boa','bom demais','muito bom','muito boa','legal','legal demais','gostei','gostei muito','positivamente','surpreendeu','surpreendente','satisfatório','satisfatoria','gratificante','animador','encorajador','motivado','motivada','comprometido','comprometida','responsável','responsavel','disciplinado','disciplinada','atento','atenta','participativo','participativa','engajado','engajada','proativo','proativa','iniciativa','criativo','criativa','inteligente','espert','espirituoso','rápido','rapida','aprende','aprendeu','progresso','evoluiu','melhor','melhorou','cresceu','adiantado','adiantada'];
        const mildPositiveWords = ['aprovado','aprovada','aprovação','aprovacao','nota','notas','progresso','participa','participou','participação','ativo','ativa','interessado','interessada','curioso','criativo','engajado','engajada','interessante','interessada','bem','bem comporta','boa postura','boa attitude','responde','respondeu','entrega','entregou'];
        
        const criticalNegativePhrases = ['não','não consegue','não pode','não sabe','não entende','não faz','não executa','não executa atividades','não participam','reprovou','reprovada','reprovado','desistente','desistiu','abandonou','abandono','comportamento ruim','comportamento péssimo','comportamento terrível','comportamento muito ruim','problema','problemas','crítico','crítica','grave','sério','séria','muito ruim','péssimo comportamento','indisciplina grave','reincidente','zero esforço','sem esforço'];
        const strongNegativeWords = ['fraco','fraca','fracos','fracas','péssimo','péssima','terrível','horrível','pior','zero','nenhum','nenhuma','reprovado','reprovada','desistente','abandono','problemático','problemática','incompatível','incapaz','inadequado','inadequada','inaceitável','insuportável','indisciplinado','indisciplinada','impossível','faltas','faltou','faltaram','dificuldade','difícil','reprovação','abaixo','insuficiente','insuficientes','preocupa','preocupante','desatento','desatenta','bagunça','bagunceiro','bagunceira','atrasado','atrasada','demora','lento','lenta','esquece','esquecido','esquecida','negligente','desorganizado','desorganizada','nunca','jamais','ruim'];
        const moderateNegativeWords = ['precisa melhorar','necessita','deveria','seria melhor','poderia','difícil','complicado','complexo','confuso','desmotivado','desmotivada','desinteressado','desinteressada','passivo','passiva','apático','apática','inconstante','irregular','instável','hesitante','desconfiado','desconfiada','reticente','reservado','reservada','agitado','agitada','nervoso','nervosa','ansioso','ansiosa','preocupado','preocupada','cansado','cansada','desanimado','desanimada','triste','abatido','abatida','desconfortável','constrangido','constrangida'];
        const mildNegativeWords = ['atenção','cuidado','monitorar','acompanhar','observar','verificar','checar','avaliar','questionável','incerto','incerta','variável','algumas vezes','às vezes','ocasionalmente','raramente','pouco','pouca','quase','quase nunca','falta'];
        
        const categoryKeywords = {
            '📚 Estudo': ['estuda','estudo','estudou','aula','aulas','prova','provas','teste','testes','exame','exercício','exercícios','tarefa','tarefas','lição','lições','matéria','matérias','conteúdo','conteúdos','aprender','aprendeu','aprenderam','leitura','leu','ler'],
            '👥 Comportamento': ['comportamento','comporta','comportou','conduta','educado','educada','educados','educadas','respeito','respeto','respeita','respeitou','atitude','atitudes','postura','civilizado','civilizada','educação','cidadão','cidadã'],
            '🎯 Desempenho': ['desempenho','nota','notas','pontuação','resultado','resultados','média','aprovado','aprovada','reprovado','reprovada','conceito','conceitos','rendimento','performance','produtividade','conquistas','conquista','progresso','evoluiu','evolução'],
            '🤝 Participação': ['participa','participou','participação','ativo','ativa','engajado','engajada','interage','interagiu','interação','colabora','colaborou','colaboração','contribui','contribuiu','contribuição','opinião','opiniões','ideia','ideias','questiona','questionou','pergunta','perguntas'],
            '⚡ Entrega': ['entrega','entregou','entregam','atraso','atrasado','atrasada','pontual','pontualidade',' prazo','prazos','devolveu','devolver','submeteu','submeter','completou','completar','finalizou','finalizar']
        };
        
        const stats = {
            total: data.todos_comentarios.length,
            positive: 0,
            negative: 0,
            neutral: 0,
            categories: {},
            topPositive: [],
            topNegative: [],
            recentComments: []
        };
        
        data.todos_comentarios.forEach(comment => {
            const text = comment.conteudo.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').toLowerCase();
            const words = text.match(/\b[a-záàâãéèêíìîóòôõúùûç]+\b/g) || [];
            const wordSet = new Set(words);
            
            let sentimentScore = 0;
            
            criticalPositivePhrases.forEach(phrase => { if (text.includes(phrase)) sentimentScore += 5; });
            strongPositiveWords.forEach(w => { if (wordSet.has(w)) sentimentScore += 3; });
            moderatePositiveWords.forEach(w => { if (wordSet.has(w)) sentimentScore += 2; });
            mildPositiveWords.forEach(w => { if (wordSet.has(w)) sentimentScore += 1; });
            
            criticalNegativePhrases.forEach(phrase => { if (text.includes(phrase)) sentimentScore -= 5; });
            strongNegativeWords.forEach(w => { if (wordSet.has(w)) sentimentScore -= 3; });
            moderateNegativeWords.forEach(w => { if (text.includes(w)) sentimentScore -= 2; });
            mildNegativeWords.forEach(w => { if (text.includes(w)) sentimentScore -= 1; });
            
            let sentiment = 'neutral';
            if (sentimentScore >= 1) sentiment = 'positive';
            else if (sentimentScore <= -1) sentiment = 'negative';
            
            if (sentiment === 'positive') stats.positive++;
            else if (sentiment === 'negative') stats.negative++;
            else stats.neutral++;
            
            Object.entries(categoryKeywords).forEach(([cat, keywords]) => {
                keywords.forEach(kw => {
                    if (wordSet.has(kw)) {
                        stats.categories[cat] = (stats.categories[cat] || 0) + 1;
                    }
                });
            });
            
            const date = new Date(comment.created_at);
            stats.recentComments.push({
                text: comment.conteudo.replace(/<[^>]*>/g, ' ').substring(0, 150) + (comment.conteudo.length > 150 ? '...' : ''),
                date: date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }),
                sentiment: sentiment
            });
        });
        
        Object.entries(categoryKeywords).forEach(([cat, keywords]) => {
            let catPositive = 0, catNegative = 0;
            const allPositiveWords = [...criticalPositivePhrases, ...strongPositiveWords, ...moderatePositiveWords, ...mildPositiveWords];
            const allNegativeWords = [...criticalNegativePhrases, ...strongNegativeWords, ...moderateNegativeWords, ...mildNegativeWords];
            data.todos_comentarios.forEach(comment => {
                const text = comment.conteudo.replace(/<[^>]*>/g, ' ').toLowerCase();
                keywords.forEach(kw => {
                    if (text.includes(kw)) {
                        if (allPositiveWords.some(pw => text.includes(pw))) catPositive++;
                        if (allNegativeWords.some(nw => text.includes(nw))) catNegative++;
                    }
                });
            });
            if (stats.categories[cat]) {
                stats.topPositive.push({ cat, count: catPositive });
                stats.topNegative.push({ cat, count: catNegative });
            }
        });
        
        const sentimentPercent = {
            positive: Math.round((stats.positive / stats.total) * 100),
            negative: Math.round((stats.negative / stats.total) * 100),
            neutral: Math.round((stats.neutral / stats.total) * 100)
        };
        
        const sortedCategories = Object.entries(stats.categories)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 5);
        
        let html = '';
        
        html += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem;margin-bottom:1.25rem;">';
        html += '<div style="background:var(--bg-surface-2nd);padding:1rem;border-radius:var(--radius-md);text-align:center;">';
        html += '<div style="font-size:1.5rem;font-weight:700;color:var(--color-success);">✓ ' + stats.positive + '</div>';
        html += '<div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Positivos</div>';
        html += '</div>';
        html += '<div style="background:var(--bg-surface-2nd);padding:1rem;border-radius:var(--radius-md);text-align:center;">';
        html += '<div style="font-size:1.5rem;font-weight:700;color:var(--color-warning);">○ ' + stats.neutral + '</div>';
        html += '<div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Neutros</div>';
        html += '</div>';
        html += '<div style="background:var(--bg-surface-2nd);padding:1rem;border-radius:var(--radius-md);text-align:center;">';
        html += '<div style="font-size:1.5rem;font-weight:700;color:var(--color-danger);">✗ ' + stats.negative + '</div>';
        html += '<div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Negativos</div>';
        html += '</div>';
        html += '</div>';
        
        html += '<div style="margin-bottom:1.25rem;">';
        html += '<div style="font-size:0.8125rem;font-weight:700;color:var(--text-primary);margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:0.05em;">📈 Visão Geral dos Comentários</div>';
        html += '<div style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:0.75rem;display:flex;gap:0.5rem;height:12px;">';
        if (sentimentPercent.positive > 0) html += '<div style="background:var(--color-success);border-radius:var(--radius-sm);width:' + sentimentPercent.positive + '%;" title="Positivos: ' + sentimentPercent.positive + '%"></div>';
        if (sentimentPercent.neutral > 0) html += '<div style="background:var(--color-warning);border-radius:var(--radius-sm);width:' + sentimentPercent.neutral + '%;" title="Neutros: ' + sentimentPercent.neutral + '%"></div>';
        if (sentimentPercent.negative > 0) html += '<div style="background:var(--color-danger);border-radius:var(--radius-sm);width:' + sentimentPercent.negative + '%;" title="Negativos: ' + sentimentPercent.negative + '%"></div>';
        html += '</div>';
        html += '<div style="display:flex;gap:1rem;margin-top:0.5rem;font-size:0.75rem;color:var(--text-muted);">';
        html += '<span style="display:flex;align-items:center;gap:0.25rem;"><span style="width:8px;height:8px;border-radius:50%;background:var(--color-success);"></span> Positivos ' + sentimentPercent.positive + '%</span>';
        html += '<span style="display:flex;align-items:center;gap:0.25rem;"><span style="width:8px;height:8px;border-radius:50%;background:var(--color-warning);"></span> Neutros ' + sentimentPercent.neutral + '%</span>';
        html += '<span style="display:flex;align-items:center;gap:0.25rem;"><span style="width:8px;height:8px;border-radius:50%;background:var(--color-danger);"></span> Negativos ' + sentimentPercent.negative + '%</span>';
        html += '</div>';
        html += '</div>';
        
        if (sortedCategories.length > 0) {
            html += '<div style="margin-bottom:1.25rem;">';
            html += '<div style="font-size:0.8125rem;font-weight:700;color:var(--text-primary);margin-bottom:0.75rem;text-transform:uppercase;letter-spacing:0.05em;">🏷️ Temas Mais Mencionados</div>';
            html += '<div style="display:flex;flex-wrap:wrap;gap:0.5rem;">';
            sortedCategories.forEach(([cat, count]) => {
                const size = Math.max(0.75, Math.min(1, 0.75 + (count / stats.total) * 0.5));
                html += '<span style="background:var(--bg-surface-2nd);padding:0.375rem 0.75rem;border-radius:var(--radius-lg);font-size:' + size + 'rem;font-weight:600;color:var(--color-primary);border:1px solid var(--border-color);">' + cat + ' <span style="opacity:0.6;">(' + count + ')</span></span>';
            });
            html += '</div>';
            html += '</div>';
        }
        
        const overall = sentimentPercent.positive > sentimentPercent.negative ? 'positivo' : (sentimentPercent.negative > sentimentPercent.positive ? 'negativo' : 'neutro');
        const overallColor = overall === 'positivo' ? 'var(--color-success)' : (overall === 'negativo' ? 'var(--color-danger)' : 'var(--color-warning)');
        html += '<div style="background:var(--bg-surface-2nd);padding:1rem;border-radius:var(--radius-md);margin-bottom:1rem;">';
        html += '<div style="font-size:0.8125rem;font-weight:700;color:var(--text-primary);margin-bottom:0.5rem;">📋 Síntese Geral</div>';
        html += '<div style="font-size:0.875rem;color:var(--text-secondary);line-height:1.6;">';
        html += 'Este aluno possui <strong style="color:' + overallColor + ';">' + stats.total + ' comentário(s)</strong> registrados. ';
        if (overall === 'positivo') {
            html += 'A maioria dos comentários destaca aspectos <strong style="color:var(--color-success);">positivos</strong> sobre o desempenho e comportamento do aluno. ';
            if (sortedCategories.length > 0) {
                html += 'Os temas mais recorrentes são: <strong>' + sortedCategories.slice(0, 3).map(c => c[0]).join(', ') + '</strong>.';
            }
        } else if (overall === 'negativo') {
            html += 'Há的关注 sobre áreas que necessitam de <strong style="color:var(--color-danger);">melhoria</strong>. ';
            if (sortedCategories.length > 0) {
                html += 'Os temas que requerem atenção são: <strong>' + sortedCategories.slice(0, 3).map(c => c[0]).join(', ') + '</strong>.';
            }
        } else {
            html += 'Os comentários são predominantemente <strong>descritivos</strong>, sem tendências claras de aspecto positivo ou negativo.';
        }
        html += '</div>';
        html += '</div>';
        
        html += '<div>';
        html += '<div style="font-size:0.8125rem;font-weight:700;color:var(--text-primary);margin-bottom:0.75rem;text-transform:uppercase;letter-spacing:0.05em;">💬 Comentários Recentes</div>';
        html += '<div style="display:flex;flex-direction:column;gap:0.5rem;max-height:180px;overflow-y:auto;">';
        stats.recentComments.slice(0, 5).forEach(c => {
            const sentColor = c.sentiment === 'positive' ? 'var(--color-success)' : (c.sentiment === 'negative' ? 'var(--color-danger)' : 'var(--color-warning)');
            const sentIcon = c.sentiment === 'positive' ? '✓' : (c.sentiment === 'negative' ? '✗' : '○');
            html += '<div style="background:var(--bg-surface);padding:0.75rem;border-radius:var(--radius-sm);border:1px solid var(--border-color);">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.25rem;">';
            html += '<span style="font-size:0.6875rem;color:var(--text-muted);">' + c.date + '</span>';
            html += '<span style="font-size:0.6875rem;color:' + sentColor + ';font-weight:600;">' + sentIcon + '</span>';
            html += '</div>';
            html += '<div style="font-size:0.8125rem;color:var(--text-secondary);line-height:1.4;">' + c.text + '</div>';
            html += '</div>';
        });
        html += '</div>';
        html += '</div>';
        
        content.innerHTML = html;
        loading.style.display = 'none';
        content.style.display = 'block';
        
    } catch (e) {
        console.error('Erro ao gerar resumo:', e);
        loading.style.display = 'none';
        empty.style.display = 'block';
    }
}

async function generateTrend(alunoId) {
    const loading = document.getElementById('trend_loading');
    const content = document.getElementById('trend_content');
    const empty = document.getElementById('trend_empty');
    
    loading.style.display = 'block';
    content.style.display = 'none';
    empty.style.display = 'none';
    
    try {
        const resp = await fetch(`/api/comments.php?aluno_id=${alunoId}&turma_id=<?= $turmaId ?>`);
        const data = await resp.json();
        
        if (!data.todos_comentarios || data.todos_comentarios.length < 2) {
            loading.style.display = 'none';
            empty.style.display = 'block';
            return;
        }
        
        const criticalPositivePhrases = ['parabéns','parabens','muito bem','excelente trabalho','superou expectativas','acima da média','nota máxima','nota 10','melhor da turma'];
        const strongPositiveWords = ['ótimo','otimo','excelente','maravilhoso','fantástico','fantastico','incrível','perfeito','perfeita','brilhante','excepcional','destacado','esforçado','esforçada','dedicado','dedicada','exemplar'];
        const moderatePositiveWords = ['bom','boa','legal','gostei','positivamente','surpreendeu','satisfatório','motivado','motivada','comprometido','responsável','disciplinado','participativo','engajado','proativo','criativo','inteligente','aprende','progresso','melhor','melhorou'];
        const mildPositiveWords = ['aprovado','aprovada','nota','notas','participa','ativo','ativa','interessado','interessada','engajado','bem','responde','entrega','entregou'];
        
        const criticalNegativePhrases = ['não','não consegue','não pode','não sabe','não entende','não faz','não executa','não participam','reprovou','reprovada','reprovado','desistente','desistiu','abandonou','abandono','comportamento ruim','comportamento péssimo','comportamento terrível','problema','problemas','grave','sério','séria','muito ruim','péssimo comportamento','indisciplina grave','reincidente','zero esforço','sem esforço'];
        const strongNegativeWords = ['fraco','fraca','péssimo','péssima','terrível','horrível','pior','reprovado','reprovada','problemático','problemática','indisciplinado','indisciplinada','faltas','faltou','faltaram','dificuldade','difícil','insuficiente','insuficientes','preocupa','preocupante','desatento','desatenta','bagunça','bagunceiro','bagunceira','atrasado','atrasada','lento','lenta','esquece','esquecido','negligente','desorganizado','desorganizada','nunca','jamais','ruim','péssima','incompatível','incapaz','inadequado','inadequada','impossível'];
        const moderateNegativeWords = ['precisa melhorar','necessita','deveria','seria melhor','poderia','difícil','complicado','complexo','confuso','desmotivado','desmotivada','desinteressado','desinteressada','passivo','passiva','apático','apática','irregular','instável','agitado','agitada','nervoso','nervosa','ansioso','ansiosa','preocupado','preocupada','cansado','cansada','desanimado','desanimada','triste','reservado','reservada','quieto','quieta'];
        const mildNegativeWords = ['atenção','cuidado','monitorar','acompanhar','observar','falta','verificar','avaliar','questionável','pouco','pouca','raramente'];
        
        const commentsWithScores = data.todos_comentarios
            .map(c => {
                const text = c.conteudo.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').toLowerCase();
                const words = text.match(/\b[a-záàâãéèêíìîóòôõúùûç]+\b/g) || [];
                const wordSet = new Set(words);
                
                let score = 0;
                criticalPositivePhrases.forEach(p => { if (text.includes(p)) score += 5; });
                strongPositiveWords.forEach(w => { if (wordSet.has(w)) score += 3; });
                moderatePositiveWords.forEach(w => { if (wordSet.has(w)) score += 2; });
                mildPositiveWords.forEach(w => { if (wordSet.has(w)) score += 1; });
                criticalNegativePhrases.forEach(p => { if (text.includes(p)) score -= 5; });
                strongNegativeWords.forEach(w => { if (wordSet.has(w)) score -= 3; });
                moderateNegativeWords.forEach(w => { if (text.includes(w)) score -= 2; });
                mildNegativeWords.forEach(w => { if (text.includes(w)) score -= 1; });
                
                return {
                    date: new Date(c.created_at),
                    score: score,
                    content: c.conteudo.replace(/<[^>]*>/g, ' ').substring(0, 100),
                    label: score >= 1 ? 'positive' : (score <= -1 ? 'negative' : 'neutral')
                };
            })
            .sort((a, b) => a.date - b.date);
        
        const firstHalf = commentsWithScores.slice(0, Math.ceil(commentsWithScores.length / 2));
        const secondHalf = commentsWithScores.slice(Math.ceil(commentsWithScores.length / 2));
        
        const avgFirst = firstHalf.reduce((sum, c) => sum + c.score, 0) / firstHalf.length;
        const avgSecond = secondHalf.reduce((sum, c) => sum + c.score, 0) / secondHalf.length;
        const avgOverall = commentsWithScores.reduce((sum, c) => sum + c.score, 0) / commentsWithScores.length;
        
        const diff = avgSecond - avgFirst;
        let trend = 'stable';
        let trendLabel = 'Estável';
        let trendColor = 'var(--color-warning)';
        let trendIcon = '→';
        
        if (diff >= 1) {
            trend = 'improving';
            trendLabel = 'Em Melhora';
            trendColor = 'var(--color-success)';
            trendIcon = '↗';
        } else if (diff <= -1) {
            trend = 'declining';
            trendLabel = 'Em Piora';
            trendColor = 'var(--color-danger)';
            trendIcon = '↘';
        }
        
        const firstDate = commentsWithScores[0].date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        const lastDate = commentsWithScores[commentsWithScores.length - 1].date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        
        let html = '';
        
        html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">';
        html += '<div style="background:var(--bg-surface-2nd);padding:1.25rem;border-radius:var(--radius-md);text-align:center;">';
        html += '<div style="font-size:2.5rem;margin-bottom:0.25rem;">' + trendIcon + '</div>';
        html += '<div style="font-size:1.125rem;font-weight:700;color:' + trendColor + ';">' + trendLabel + '</div>';
        html += '<div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">Tendência Geral</div>';
        html += '</div>';
        
        const posCount = commentsWithScores.filter(c => c.label === 'positive').length;
        const negCount = commentsWithScores.filter(c => c.label === 'negative').length;
        const neuCount = commentsWithScores.filter(c => c.label === 'neutral').length;
        
        html += '<div style="background:var(--bg-surface-2nd);padding:1.25rem;border-radius:var(--radius-md);">';
        html += '<div style="font-size:0.8125rem;font-weight:700;color:var(--text-primary);margin-bottom:0.75rem;">📊 Distribuição</div>';
        html += '<div style="display:flex;flex-direction:column;gap:0.375rem;">';
        html += '<div style="display:flex;align-items:center;gap:0.5rem;"><span style="width:8px;height:8px;border-radius:50%;background:var(--color-success);"></span><span style="font-size:0.8125rem;color:var(--text-secondary);flex:1;">Positivos</span><span style="font-size:0.8125rem;font-weight:600;color:var(--color-success);">' + posCount + '</span></div>';
        html += '<div style="display:flex;align-items:center;gap:0.5rem;"><span style="width:8px;height:8px;border-radius:50%;background:var(--color-warning);"></span><span style="font-size:0.8125rem;color:var(--text-secondary);flex:1;">Neutros</span><span style="font-size:0.8125rem;font-weight:600;color:var(--color-warning);">' + neuCount + '</span></div>';
        html += '<div style="display:flex;align-items:center;gap:0.5rem;"><span style="width:8px;height:8px;border-radius:50%;background:var(--color-danger);"></span><span style="font-size:0.8125rem;color:var(--text-secondary);flex:1;">Negativos</span><span style="font-size:0.8125rem;font-weight:600;color:var(--color-danger);">' + negCount + '</span></div>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        
        html += '<div style="background:var(--bg-surface-2nd);padding:1rem;border-radius:var(--radius-md);margin-bottom:1.25rem;">';
        html += '<div style="font-size:0.8125rem;font-weight:700;color:var(--text-primary);margin-bottom:1rem;">📈 Evolução dos Comentários</div>';
        html += '<div style="display:flex;align-items:flex-end;gap:4px;height:100px;padding:0 0.5rem;">';
        commentsWithScores.forEach((c, i) => {
            const height = Math.max(10, 50 + c.score * 8);
            const color = c.label === 'positive' ? 'var(--color-success)' : (c.label === 'negative' ? 'var(--color-danger)' : 'var(--color-warning)');
            const firstInGroup = (i === 0 || i === Math.floor(commentsWithScores.length / 2));
            const label = firstInGroup ? (i === 0 ? firstDate : lastDate) : '';
            html += '<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:0.25rem;">';
            html += '<div style="width:100%;height:' + height + 'px;background:' + color + ';border-radius:3px 3px 0 0;opacity:0.8;"></div>';
            if (label) html += '<span style="font-size:0.625rem;color:var(--text-muted);">' + label + '</span>';
            html += '</div>';
        });
        html += '</div>';
        html += '<div style="display:flex;justify-content:space-between;margin-top:0.5rem;padding:0 0.5rem;">';
        html += '<div style="text-align:center;"><div style="font-size:0.6875rem;color:var(--text-muted);">Início</div><div style="font-size:0.75rem;font-weight:600;color:var(--text-primary);">' + avgFirst.toFixed(1) + '</div></div>';
        html += '<div style="text-align:center;"><div style="font-size:0.6875rem;color:var(--text-muted);">Média Geral</div><div style="font-size:0.75rem;font-weight:600;color:var(--text-primary);">' + avgOverall.toFixed(1) + '</div></div>';
        html += '<div style="text-align:center;"><div style="font-size:0.6875rem;color:var(--text-muted);">Atual</div><div style="font-size:0.75rem;font-weight:600;color:var(--text-primary);">' + avgSecond.toFixed(1) + '</div></div>';
        html += '</div>';
        html += '</div>';
        
        html += '<div style="background:var(--bg-surface-2nd);padding:1rem;border-radius:var(--radius-md);margin-bottom:1rem;">';
        html += '<div style="font-size:0.8125rem;font-weight:700;color:var(--text-primary);margin-bottom:0.75rem;">📋 Análise Comparativa</div>';
        html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">';
        html += '<div style="text-align:center;padding:0.75rem;background:var(--bg-surface);border-radius:var(--radius-sm);">';
        html += '<div style="font-size:0.6875rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">1ª Metade</div>';
        html += '<div style="font-size:1.25rem;font-weight:700;color:var(--text-primary);">' + avgFirst.toFixed(1) + '</div>';
        html += '<div style="font-size:0.6875rem;color:var(--text-muted);">' + firstHalf.length + ' comentário(s)</div>';
        html += '</div>';
        html += '<div style="text-align:center;padding:0.75rem;background:var(--bg-surface);border-radius:var(--radius-sm);">';
        html += '<div style="font-size:0.6875rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">2ª Metade</div>';
        html += '<div style="font-size:1.25rem;font-weight:700;color:var(--text-primary);">' + avgSecond.toFixed(1) + '</div>';
        html += '<div style="font-size:0.6875rem;color:var(--text-muted);">' + secondHalf.length + ' comentário(s)</div>';
        html += '</div>';
        html += '</div>';
        html += '<div style="margin-top:0.75rem;text-align:center;font-size:0.8125rem;color:var(--text-secondary);">';
        const variation = Math.abs(diff).toFixed(1);
        const variationPercent = avgFirst !== 0 ? Math.round((diff / Math.abs(avgFirst)) * 100) : 0;
        if (trend === 'improving') {
            html += '<span style="color:var(--color-success);font-weight:600;">+' + variation + ' (' + (variationPercent > 0 ? '+' : '') + variationPercent + '%)</span> em relação ao início';
        } else if (trend === 'declining') {
            html += '<span style="color:var(--color-danger);font-weight:600;">' + variation + ' (' + variationPercent + '%)</span> em relação ao início';
        } else {
            html += '<span style="color:var(--color-warning);font-weight:600;">Variação de ' + variation + '</span> - desempenho consistente';
        }
        html += '</div>';
        html += '</div>';
        
        if (commentsWithScores.length <= 10) {
            html += '<div style="background:var(--bg-surface-2nd);padding:1rem;border-radius:var(--radius-md);">';
            html += '<div style="font-size:0.8125rem;font-weight:700;color:var(--text-primary);margin-bottom:0.75rem;">💬 Comentários por Data</div>';
            html += '<div style="display:flex;flex-direction:column;gap:0.5rem;max-height:150px;overflow-y:auto;">';
            commentsWithScores.forEach(c => {
                const sentColor = c.label === 'positive' ? 'var(--color-success)' : (c.label === 'negative' ? 'var(--color-danger)' : 'var(--color-warning)');
                const sentIcon = c.label === 'positive' ? '✓' : (c.label === 'negative' ? '✗' : '○');
                const dateStr = c.date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                html += '<div style="display:flex;align-items:flex-start;gap:0.5rem;padding:0.5rem;background:var(--bg-surface);border-radius:var(--radius-sm);">';
                html += '<span style="color:' + sentColor + ';font-weight:700;flex-shrink:0;">' + sentIcon + '</span>';
                html += '<div style="flex:1;min-width:0;">';
                html += '<div style="font-size:0.6875rem;color:var(--text-muted);margin-bottom:0.125rem;">' + dateStr + ' · score: ' + c.score + '</div>';
                html += '<div style="font-size:0.75rem;color:var(--text-secondary);line-height:1.3;">' + c.content + '...</div>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
            html += '</div>';
        }
        
        content.innerHTML = html;
        loading.style.display = 'none';
        content.style.display = 'block';
        
    } catch (e) {
        console.error('Erro ao gerar tendência:', e);
        loading.style.display = 'none';
        empty.style.display = 'block';
    }
}
</script>

<style>
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.comment-tabs-card { overflow:hidden; }
.comment-tabs-header { padding:0.75rem 1.25rem; background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color); display:flex; align-items:center; gap:0.5rem; }
.comment-tabs-label { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted); }
.comment-tabs-container { display:flex; overflow-x:auto; background:var(--bg-surface); scrollbar-width:none; -ms-overflow-style:none; border-top:1px solid var(--border-color); border-bottom:1px solid var(--border-color); }
.comment-tabs-container::-webkit-scrollbar { display:none; }
.comment-tab-btn {
    display:flex; align-items:center; gap:0.625rem; padding:1rem 1.5rem;
    color:var(--text-secondary); font-weight:600; text-decoration:none;
    border-bottom:3px solid transparent; transition:all var(--transition-fast);
    white-space:nowrap; font-size:0.875rem; border:none; background:none;
    cursor:pointer;
}
.comment-tab-btn:hover { background:var(--bg-hover); color:var(--color-primary); }
.comment-tab-btn.active { color:var(--color-primary); border-bottom-color:var(--color-primary); background:var(--color-primary-light); }
.comment-tab-icon { font-size:1.1rem; opacity:0.7; }
.comment-tab-btn.active .comment-tab-icon { opacity:1; }
.comment-tab-content { height:420px; overflow-y:auto; display:flex; flex-direction:column; }
.comment-tab-content > div { flex:1; }
#wordcloud_container canvas { border-radius:var(--radius-md); background:var(--bg-surface-2nd); }
#wordcloud_canvas { max-width:100%; height:auto; }
</style>

<?php if ($canComment): ?>
<div class="modal-backdrop" id="commentModal" role="dialog">
    <div class="modal" style="max-width:720px;">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div id="comment_aluno_photo" style="width:40px;height:40px;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1rem;"></div>
                <div>
                    <div id="comment_aluno_name" style="font-size:1rem;font-weight:700;color:var(--text-primary);"></div>
                    <div style="font-size:.75rem;color:var(--text-muted);">Análise e Comentários</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('commentModal')">✕</button>
        </div>
        
        <div class="comment-tabs-card">
            <div class="comment-tabs-container">
                <button class="comment-tab-btn active" data-tab="comments" onclick="switchCommentTab('comments')">
                    <span class="comment-tab-icon">💬</span>
                    <span>Comentários</span>
                </button>
                <button class="comment-tab-btn" data-tab="wordcloud" onclick="switchCommentTab('wordcloud')">
                    <span class="comment-tab-icon">☁️</span>
                    <span>Nuvem de Palavras</span>
                </button>
                <button class="comment-tab-btn" data-tab="summary" onclick="switchCommentTab('summary')">
                    <span class="comment-tab-icon">📊</span>
                    <span>Resumo</span>
                </button>
                <button class="comment-tab-btn" data-tab="trend" onclick="switchCommentTab('trend')">
                    <span class="comment-tab-icon">📈</span>
                    <span>Tendência</span>
                </button>
            </div>
        </div>
        
        <div id="commentTabContent" class="modal-body" style="padding:1.25rem 1.5rem;display:flex;flex-direction:column;">
            <div id="tab-comments" class="comment-tab-content" style="height:420px;">
                <div style="flex:1;overflow-y:auto;">
                    <form id="commentForm" onsubmit="saveComment(event); return false;">
                        <input type="hidden" name="action" value="save_comment">
                        <input type="hidden" name="aluno_id" id="comment_aluno_id">
                        
                        <div class="form-group" style="margin-bottom:1rem;">
                            <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
                                <span>Novo Comentário</span>
                                <div style="display:flex;gap:.25rem;background:var(--bg-surface-2nd);padding:.25rem;border-radius:var(--radius-sm);border:1px solid var(--border-color);">
                                    <button type="button" class="action-btn" style="width:28px;height:28px;" onclick="formatText('bold')" title="Negrito"><b>B</b></button>
                                    <button type="button" class="action-btn" style="width:28px;height:28px;" onclick="formatText('italic')" title="Itálico"><i>I</i></button>
                                    <button type="button" class="action-btn" style="width:28px;height:28px;" onclick="formatText('insertUnorderedList')" title="Lista">📋</button>
                                </div>
                            </label>
                            <div id="comment_text" class="form-control" contenteditable="true" style="min-height:80px;max-height:120px;overflow-y:auto;background:var(--bg-surface);padding:.75rem;" placeholder="Digite seu comentário sobre este aluno..."></div>
                            <div id="comment_preview"></div>
                            <div style="text-align:right;margin-top:.5rem;">
                                <button type="submit" class="btn btn-primary btn-sm">💾 Publicar</button>
                            </div>
                        </div>
                    </form>

                    <div id="comment_history_meu"></div>
                    <div id="comment_history_outros" style="margin-top:0.5rem;"></div>
                </div>
            </div>
            
            <div id="tab-wordcloud" class="comment-tab-content" style="display:none;height:420px;">
                <div id="wordcloud_container" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <div id="wordcloud_loading" style="text-align:center;color:var(--text-muted);">
                        <div style="font-size:2rem;margin-bottom:0.5rem;">☁️</div>
                        <div style="font-size:.875rem;">Carregando nuvem de palavras...</div>
                    </div>
                    <canvas id="wordcloud_canvas" width="660" height="280" style="display:none;"></canvas>
                    <div id="wordcloud_empty" style="display:none;text-align:center;color:var(--text-muted);">
                        <div style="font-size:3rem;margin-bottom:0.75rem;">📝</div>
                        <div style="font-size:.9375rem;font-weight:600;margin-bottom:0.25rem;">Nenhum comentário registrado</div>
                        <div style="font-size:.8125rem;">Adicione comentários para gerar a nuvem de palavras.</div>
                    </div>
                </div>
                <div id="wordcloud_info" style="padding:0.5rem;background:var(--bg-surface-2nd);border-radius:var(--radius-sm);font-size:.75rem;color:var(--text-muted);text-align:center;display:none;flex-shrink:0;">
                    <span id="wordcloud_word_count">0</span> palavras analisadas de <span id="wordcloud_comment_count">0</span> comentário(s)
                </div>
            </div>
            
            <div id="tab-summary" class="comment-tab-content" style="display:none;height:420px;">
                <div id="summary_container" style="flex:1;overflow-y:auto;">
                    <div id="summary_loading" style="text-align:center;color:var(--text-muted);padding:2rem;">
                        <div style="font-size:2rem;margin-bottom:0.5rem;">📊</div>
                        <div style="font-size:.875rem;">Gerando resumo dos comentários...</div>
                    </div>
                    <div id="summary_content" style="display:none;"></div>
                    <div id="summary_empty" style="display:none;text-align:center;color:var(--text-muted);padding:2rem;">
                        <div style="font-size:3rem;margin-bottom:0.75rem;">📝</div>
                        <div style="font-size:.9375rem;font-weight:600;margin-bottom:0.25rem;">Nenhum comentário para analisar</div>
                        <div style="font-size:.8125rem;">Adicione comentários para gerar o resumo.</div>
                    </div>
                </div>
            </div>
            
            <div id="tab-trend" class="comment-tab-content" style="display:none;height:420px;">
                <div id="trend_container" style="flex:1;overflow-y:auto;">
                    <div id="trend_loading" style="text-align:center;color:var(--text-muted);padding:2rem;">
                        <div style="font-size:2rem;margin-bottom:0.5rem;">📈</div>
                        <div style="font-size:.875rem;">Analisando tendência...</div>
                    </div>
                    <div id="trend_content" style="display:none;"></div>
                    <div id="trend_empty" style="display:none;text-align:center;color:var(--text-muted);padding:2rem;">
                        <div style="font-size:3rem;margin-bottom:0.75rem;">📝</div>
                        <div style="font-size:.9375rem;font-weight:600;margin-bottom:0.25rem;">Comentários insuficientes</div>
                        <div style="font-size:.8125rem;">São necessários pelo menos 2 comentários para analisar a tendência.</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer" style="padding:1rem 1.5rem;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('commentModal')" style="width:100%;">Fechar Janela</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
