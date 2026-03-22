<?php
/**
 * Vértice Acadêmico — Cursos (Administrador e Coordenador)
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$allowed = ['Administrador', 'Coordenador'];
if (!$user || !in_array($user['profile'], $allowed)) {
    header('Location: /dashboard.php');
    exit;
}

$db      = getDB();
$inst    = getCurrentInstitution();
$instId  = $inst['id'];

// Se não há instituição selecionada, solicita seleção
if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/courses/index.php'));
    exit;
}

$success = '';
$error   = '';
$action  = $_POST['action'] ?? '';

// ---- CRIAR ----
if ($action === 'create' && $user['profile'] === 'Administrador') {
    $name     = trim($_POST['name']     ?? '');
    $location = trim($_POST['location'] ?? '');

    if (strlen($name) < 2) {
        $error = 'Informe o nome do curso.';
    } else {
        $st = $db->prepare('SELECT id FROM courses WHERE name=? AND institution_id=? LIMIT 1');
        $st->execute([$name, $instId]);
        if ($st->fetch()) {
            $error = 'Já existe um curso com este nome nesta instituição.';
        } else {
            $db->prepare('INSERT INTO courses (institution_id, name, location) VALUES (?,?,?)')
               ->execute([$instId, $name, $location ?: null]);
            
            $newId = $db->lastInsertId();
            if ($user['profile'] === 'Coordenador') {
                $db->prepare('INSERT INTO course_coordinators (course_id, user_id) VALUES (?,?)')
                   ->execute([$newId, $user['id']]);
            }

            $success = "Curso «{$name}» cadastrado com sucesso!";
        }
    }
}

// ---- TOGGLE ----
if ($action === 'toggle' && !empty($_POST['course_id']) && $user['profile'] === 'Administrador') {
    $cid = (int)$_POST['course_id'];
    $db->prepare('UPDATE courses SET is_active = !is_active WHERE id=? AND institution_id=?')
       ->execute([$cid, $instId]);
    $success = 'Status do curso atualizado.';
}

// ---- EXCLUIR ----
if ($action === 'delete' && !empty($_POST['course_id']) && $user['profile'] === 'Administrador') {
    $cid = (int)$_POST['course_id'];
    $db->prepare('DELETE FROM courses WHERE id=? AND institution_id=?')
       ->execute([$cid, $instId]);
    $success = 'Curso removido.';
}

// ---- EXCLUIR ----
if ($action === 'delete' && !empty($_POST['course_id']) && $user['profile'] === 'Administrador') {
    $cid = (int)$_POST['course_id'];
    $db->prepare('DELETE FROM courses WHERE id=? AND institution_id=?')
       ->execute([$cid, $instId]);
    $success = 'Curso removido.';
}


// ---- LISTAR ----
$search = trim($_GET['search'] ?? '');
$sql    = "SELECT c.*, 
                  GROUP_CONCAT(u.name ORDER BY u.name ASC SEPARATOR '||') as coord_names,
                  GROUP_CONCAT(COALESCE(u.photo, '') ORDER BY u.name ASC SEPARATOR '||') as coord_photos
           FROM courses c
           LEFT JOIN course_coordinators cc ON c.id = cc.course_id
           LEFT JOIN users u ON u.id = cc.user_id";
$params = [$instId];
$where  = "WHERE c.institution_id=?";

if ($user['profile'] === 'Coordenador') {
    $where .= " AND c.id IN (SELECT course_id FROM course_coordinators WHERE user_id = ?)";
    $params[] = $user['id'];
}

if ($search) {
    $where    .= ' AND (c.name LIKE ? OR c.location LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " $where GROUP BY c.id ORDER BY c.name ASC";
$st  = $db->prepare($sql);
$st->execute($params);
$courses = $st->fetchAll();

$pageTitle = 'Cursos';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.courses-table-wrap { overflow-x:auto; border-radius:var(--radius-lg); }
.courses-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.courses-table th {
    padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:600;
    text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted);
    background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color);
    white-space:nowrap;
}
.courses-table td { padding:.875rem 1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; }
.courses-table tr:last-child td { border-bottom:none; }
.courses-table tr:hover td { background:var(--bg-hover); }
.action-btn {
    display:inline-flex; align-items:center; justify-content:center;
    width:32px; height:32px; border-radius:var(--radius-md);
    border:1px solid var(--border-color); background:var(--bg-surface);
    color:var(--text-muted); cursor:pointer; font-size:.875rem;
    transition:all var(--transition-fast); text-decoration:none;
}
.action-btn:hover { background:var(--bg-hover); color:var(--text-primary); }
.action-btn.danger:hover { background:#fef2f2; color:var(--color-danger); border-color:var(--color-danger); }
[data-theme="dark"] .action-btn.danger:hover { background:#450a0a; }
.modal-backdrop { position:fixed; inset:0; z-index:3000; background:rgba(0,0,0,.5);
    backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center;
    padding:1rem; opacity:0; visibility:hidden; transition:all .25s ease; }
.modal-backdrop.show { opacity:1; visibility:visible; }
.modal { background:var(--bg-surface); border:1px solid var(--border-color);
    border-radius:var(--radius-xl); width:100%; max-width:480px;
    max-height:90vh; overflow-y:auto; box-shadow:0 25px 60px rgba(0,0,0,.3);
    transform:translateY(20px) scale(.97); transition:all .25s ease; }
.modal-backdrop.show .modal { transform:translateY(0) scale(1); }
.modal-header { padding:1.5rem; border-bottom:1px solid var(--border-color);
    display:flex; align-items:center; justify-content:space-between; }
.modal-title { font-size:1.0625rem; font-weight:700; color:var(--text-primary); }
.modal-close { width:32px; height:32px; border-radius:var(--radius-md);
    border:1px solid var(--border-color); background:var(--bg-surface);
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    color:var(--text-muted); font-size:1.125rem; transition:all var(--transition-fast); }
.modal-close:hover { background:var(--bg-hover); }
.modal-body { padding:1.5rem; display:flex; flex-direction:column; gap:1rem; }
.modal-footer { padding:1rem 1.5rem; border-top:1px solid var(--border-color);
    display:flex; gap:.75rem; justify-content:flex-end; }

/* Avatares dos Coordenadores */
.coord-stack { display:flex; align-items:center; margin-top:.375rem; }
.coord-avatar {
    width:24px; height:24px; border-radius:50%; border:2px solid var(--bg-surface);
    background:var(--bg-surface-2nd); margin-left:-8px; object-fit:cover;
    display:flex; align-items:center; justify-content:center;
    font-size:.625rem; font-weight:700; color:var(--text-muted);
    position:relative; cursor:help;
}
.coord-avatar:first-child { margin-left:0; }
.coord-avatar img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
</style>

<!-- Page Header -->
<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 class="page-title">📚 Cursos</h1>
        <p class="page-subtitle">
            Instituição: <strong><?= htmlspecialchars($inst['name']) ?></strong>
        </p>
    </div>
    <?php if ($user['profile'] === 'Administrador'): ?>
    <button class="btn btn-primary" onclick="openModal()">➕ Novo Curso</button>
    <?php endif; ?>
</div>

<?php if ($success): ?>
<div class="alert alert-success fade-in" style="margin-bottom:1.5rem;">
    ✅ <?= htmlspecialchars($success) ?>
    <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:1.1rem;">✕</button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger fade-in" style="margin-bottom:1.5rem;">
    ⚠️ <?= htmlspecialchars($error) ?>
    <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:1.1rem;">✕</button>
</div>
<?php endif; ?>

<!-- Filtro -->
<div class="card fade-in" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.5rem;">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:220px;margin:0;">
                <div class="input-group">
                    <span class="input-icon">🔍</span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Buscar por nome ou local..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($search): ?>
            <a href="/courses/index.php" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabela -->
<div class="card fade-in">
    <div class="card-header">
        <span class="card-title">Lista de Cursos</span>
        <span style="font-size:.875rem;color:var(--text-muted);"><?= count($courses) ?> curso(s)</span>
    </div>
    <div class="courses-table-wrap">
        <table class="courses-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome do Curso</th>
                    <th>Local</th>
                    <th>Status</th>
                    <th>Cadastrado em</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                <tr><td colspan="6" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
                    Nenhum curso cadastrado nesta instituição.
                </td></tr>
                <?php endif; ?>
                <?php foreach ($courses as $c): ?>
                <tr style="<?= !$c['is_active'] ? 'opacity:.55' : '' ?>">
                    <td style="color:var(--text-muted);font-size:.8125rem;"><?= $c['id'] ?></td>
                    <td>
                        <span style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></span>
                        <?php if (!empty($c['coord_names'])): ?>
                            <div class="coord-stack">
                                <?php 
                                $names  = explode('||', $c['coord_names']);
                                $photos = explode('||', $c['coord_photos']);
                                foreach ($names as $idx => $name): 
                                    if ($idx >= 5) { // Limite visual
                                        echo '<div class="coord-avatar" title="E mais ' . (count($names)-$idx) . '...">+' . (count($names)-$idx) . '</div>';
                                        break;
                                    }
                                    $photo = $photos[$idx] ?? '';
                                ?>
                                    <div class="coord-avatar" title="Coordenador: <?= htmlspecialchars($name) ?>">
                                        <?php if ($photo): ?>
                                            <img src="/<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($name) ?>">
                                        <?php else: ?>
                                            <?= mb_substr($name, 0, 1) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--text-secondary);">
                        <?= $c['location'] ? '📍 ' . htmlspecialchars($c['location']) : '—' ?>
                    </td>
                    <td>
                        <span style="font-size:.8125rem;font-weight:600;color:<?= $c['is_active'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                            <?= $c['is_active'] ? '● Ativo' : '○ Inativo' ?>
                        </span>
                    </td>
                    <td style="color:var(--text-muted);white-space:nowrap;font-size:.8125rem;">
                        <?= date('d/m/Y', strtotime($c['created_at'])) ?>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <?php if ($user['profile'] === 'Administrador'): ?>
                            <a href="/courses/coordinators.php?course_id=<?= $c['id'] ?>"
                               class="action-btn" title="Relacionar Coordenadores">👥</a>
                            <?php endif; ?>
                            <a href="/courses/turmas.php?course_id=<?= $c['id'] ?>"
                               class="action-btn" title="Gerenciar Turmas">🎓</a>
                            <button type="button" class="action-btn" title="Importar Notas (CSV)"
                                    onclick='openImportGradesModal(<?= $c['id'] ?>, <?= json_encode($c['name']) ?>)'>
                                📊
                            </button>
                            <?php if ($user['profile'] === 'Administrador'): ?>
                                <a href="/courses/edit.php?id=<?= $c['id'] ?>" class="action-btn" title="Editar">✏️</a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action"    value="toggle">
                                    <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="action-btn"
                                            title="<?= $c['is_active'] ? 'Desativar' : 'Ativar' ?>"
                                            onclick="return confirm('<?= $c['is_active'] ? 'Desativar' : 'Ativar' ?> «<?= htmlspecialchars($c['name']) ?>»?')">
                                        <?= $c['is_active'] ? '⏸' : '▶' ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action"    value="delete">
                                    <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="action-btn danger" title="Excluir"
                                            onclick="return confirm('Excluir permanentemente «<?= htmlspecialchars($c['name']) ?>»?')">🗑</button>
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

<!-- Modal: Novo Curso -->
<div class="modal-backdrop" id="courseModal" role="dialog" aria-modal="true">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">📚 Novo Curso</span>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">

                <div style="padding:.625rem .875rem;border-radius:var(--radius-md);background:var(--color-primary-light);color:var(--color-primary);font-size:.875rem;font-weight:500;">
                    🏫 Será cadastrado em: <strong><?= htmlspecialchars($inst['name']) ?></strong>
                </div>

                <div class="form-group">
                    <label class="form-label">Nome do Curso <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">📚</span>
                        <input type="text" name="name" class="form-control"
                               placeholder="Ex: Técnico em Informática" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Local</label>
                    <div class="input-group">
                        <span class="input-icon">📍</span>
                        <input type="text" name="location" class="form-control"
                               placeholder="Ex: Bloco A — Sala 101">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">💾 Cadastrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Importar Notas -->
<div class="modal-backdrop" id="importGradesModal" role="dialog" aria-modal="true">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">📊 Importar Notas via Arquivo</span>
            <button class="modal-close" onclick="closeImportGradesModal()">✕</button>
        </div>
        <form action="/courses/import_notas.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="course_id" id="import_grades_course_id">
            <div class="modal-body">
                
                <div style="padding:.625rem .875rem;border-radius:var(--radius-md);background:var(--color-primary-light);color:var(--color-primary);font-size:.875rem;font-weight:500;margin-bottom:.5rem;">
                    🏫 Curso: <strong id="import_grades_course_name">...</strong>
                </div>

                <div style="padding:1rem; border-radius:var(--radius-md); background:var(--bg-surface-2nd); border:1px dashed var(--border-color); margin-bottom:0.5rem;">
                    <p style="font-size:0.875rem; font-weight:600; margin-bottom:0.5rem; color:var(--text-primary);">📝 Instruções do Arquivo:</p>
                    <ul style="font-size:0.8125rem; color:var(--text-muted); padding-left:1.25rem;">
                        <li>O arquivo deve ser um <strong>CSV</strong> (separador , ou ;).</li>
                        <li>Colunas: <strong>Etapa, Matrícula, Disciplina, Nota, Faltas</strong>.</li>
                        <li>A primeira linha (cabeçalho) será ignorada.</li>
                        <li>Exemplo: <br><code style="background:var(--bg-surface);padding:.2rem;border-radius:4px;display:inline-block;margin-top:.25rem;">1º Bimestre;2024001;MAT-101;8.5;2</code></li>
                    </ul>
                </div>

                <div class="form-group">
                    <label class="form-label">Selecione o arquivo (.csv) <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">📄</span>
                        <input type="file" name="import_file" class="form-control" accept=".csv" required style="padding-left:2.75rem;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeImportGradesModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">🚀 Abrir Processador</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal()  { document.getElementById('courseModal').classList.add('show'); document.body.style.overflow='hidden'; }
function closeModal() { document.getElementById('courseModal').classList.remove('show'); document.body.style.overflow=''; }

function openImportGradesModal(cid, cname) {
    document.getElementById('import_grades_course_id').value = cid;
    document.getElementById('import_grades_course_name').innerText = cname;
    document.getElementById('importGradesModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeImportGradesModal() {
    document.getElementById('importGradesModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('courseModal').addEventListener('click', e => { if(e.target===document.getElementById('courseModal')) closeModal(); });
document.getElementById('importGradesModal').addEventListener('click', function(e) { if(e.target===this) closeImportGradesModal(); });
document.addEventListener('keydown', e => { 
    if(e.key==='Escape') { closeModal(); closeImportGradesModal(); } 
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
