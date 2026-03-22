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
                    <?php if ($isProfessor): ?>
                    <th style="text-align:center;">Ações</th>
                    <?php else: ?>
                    <th>Contato</th>
                    <th style="text-align:center;">Ações</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alunos)): ?>
                <tr><td colspan="<?= $isProfessor ? '4' : '5' ?>" style="text-align:center;padding:3rem;color:var(--text-muted);">Nenhum aluno vinculado a esta turma.</td></tr>
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
                    <?php if ($canComment): ?>
                    <td style="font-weight:600;"><?= htmlspecialchars($a['nome']) ?></td>
                    <td style="text-align:center;">
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <button type="button" class="action-btn" title="Adicionar Comentário" onclick='openCommentModal(<?= json_encode(["id" => $a["id"], "nome" => $a["nome"], "photo" => $a["photo"], "photo_url" => ($a["photo"] && file_exists(__DIR__."/../".$a["photo"]) ? "/".$a["photo"] : null)]) ?>)'>💬</button>
                        </div>
                    </td>
                    <?php else: ?>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($a['nome']) ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($a['email']) ?></div>
                    </td>
                    <td style="font-size:.8125rem;"><?= htmlspecialchars($a['telefone'] ?: '—') ?></td>
                    <td style="text-align:center;">
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <button type="button" class="action-btn" title="Editar"
                                    onclick='openEditModal(<?= json_encode($a) ?>)'>✏️</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Desvincular aluno da turma?')">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="aluno_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="action-btn danger" title="Remover da Turma">✕</button>
                            </form>
                        </div>
                    </td>
                    <?php endif; ?>
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
    document.getElementById('comment_aluno_id').value = aluno.id;
    document.getElementById('comment_aluno_name').textContent = aluno.nome;
    document.getElementById('comment_text').innerHTML = ''; // Limpa o contenteditable
    document.getElementById('comment_history_meu').innerHTML = '<div style="padding:1rem;text-align:center;"><span style="font-size:.875rem;color:var(--text-muted);">Carregando...</span></div>';
    document.getElementById('comment_history_outros').innerHTML = '';
    
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
</script>

<style>
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>

<?php if ($canComment): ?>
<div class="modal-backdrop" id="commentModal" role="dialog">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div id="comment_aluno_photo" style="width:40px;height:40px;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1rem;"></div>
                <div>
                    <div id="comment_aluno_name" style="font-size:1rem;font-weight:700;color:var(--text-primary);"></div>
                    <div style="font-size:.75rem;color:var(--text-muted);">💬 Comentários</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('commentModal')">✕</button>
        </div>
        <form id="commentForm" onsubmit="saveComment(event); return false;">
            <input type="hidden" name="action" value="save_comment">
            <input type="hidden" name="aluno_id" id="comment_aluno_id">
            
            <div class="modal-body" style="padding:1.25rem 1.5rem;">
                
                <!-- Rich Text Editor -->
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
                        <span>Novo Comentário</span>
                        <div style="display:flex;gap:.25rem;background:var(--bg-surface-2nd);padding:.25rem;border-radius:var(--radius-sm);border:1px solid var(--border-color);">
                            <button type="button" class="action-btn" style="width:28px;height:28px;" onclick="formatText('bold')" title="Negrito"><b>B</b></button>
                            <button type="button" class="action-btn" style="width:28px;height:28px;" onclick="formatText('italic')" title="Itálico"><i>I</i></button>
                            <button type="button" class="action-btn" style="width:28px;height:28px;" onclick="formatText('insertUnorderedList')" title="Lista">📋</button>
                        </div>
                    </label>
                    <div id="comment_text" class="form-control" contenteditable="true" style="min-height:100px;max-height:200px;overflow-y:auto;background:var(--bg-surface);padding:.75rem;" placeholder="Digite seu comentário sobre este aluno..."></div>
                    <div id="comment_preview"></div>
                    <div style="text-align:right;margin-top:.5rem;">
                        <button type="submit" class="btn btn-primary btn-sm">💾 Publicar Comentário</button>
                    </div>
                </div>

                <!-- Histórico de Comentários -->
                <div id="comment_history_meu"></div>
                <div id="comment_history_outros" style="margin-top:0.5rem;"></div>
            </div>
            
            <div class="modal-footer" style="padding:1rem 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('commentModal')" style="width:100%;">Fechar Janela</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
