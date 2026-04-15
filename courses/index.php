<?php
/**
 * Vértice Acadêmico — Cursos (Administrador e Coordenador)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/CourseService.php';

requireLogin();

$user = getCurrentUser();
hasDbPermission('courses.index'); // Verifica se o usuário tem permissão para acessar esta página e redireciona se não tiver.

$db      = getDB();
$inst    = getCurrentInstitution();
$instId  = $inst['id'];

$courseService = new \App\Services\CourseService();

// Se não há instituição selecionada, solicita seleção
if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/courses/index.php'));
    exit;
}

$success = '';
$error   = '';
$action  = $_POST['action'] ?? '';

// Verificação CSRF para ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        die('Token de segurança inválido. Tente novamente.');
    }
}

// ---- CRIAR ----
if ($action === 'create' && hasDbPermission('courses.create', false)) {
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
            $result = $courseService->create($instId, [
                'name' => $name,
                'location' => $location ?: null
            ]);
            
            if (isset($result['success'])) {
                $newId = $result['id'];
                if ($user['profile'] === 'Coordenador') {
                    // Adiciona o criador como coordenador
                    $db->prepare('INSERT INTO course_coordinators (course_id, user_id) VALUES (?,?)')
                       ->execute([$newId, $user['id']]);
                }
                $success = "Curso «{$name}» cadastrado com sucesso!";
            } else {
                $error = $result['error'] ?? 'Erro ao criar curso.';
            }
        }
    }
}

// ---- TOGGLE ----
if ($action === 'toggle' && !empty($_POST['course_id']) && hasDbPermission('courses.update', false)) {
    $cid = (int)$_POST['course_id'];
    $current = $db->prepare('SELECT is_active FROM courses WHERE id=? AND institution_id=?');
    $current->execute([$cid, $instId]);
    $course = $current->fetch();
    
    if ($course) {
        $courseService->update($cid, ['is_active' => !$course['is_active']]);
        $success = 'Status do curso atualizado.';
    }
}

// ---- EXCLUIR ----
if ($action === 'delete' && !empty($_POST['course_id']) && hasDbPermission('courses.delete', false)) {
    $cid = (int)$_POST['course_id'];
    if ($courseService->delete($cid)) {
        $success = 'Curso removido.';
    } else {
        $error = 'Erro ao remover curso ou curso não encontrado.';
    }
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

// Lógica de visibilidade: Administrador ou quem tem permissão 'view_all' vê tudo da instituição.
// Caso contrário, filtra por vínculo de Coordenador ou Professor.
$canViewAll = hasDbPermission('courses.view_all', false);

if (!$canViewAll) {
    if ($user['profile'] === 'Coordenador') {
        $where .= " AND c.id IN (SELECT course_id FROM course_coordinators WHERE user_id = ?)";
        $params[] = $user['id'];
    } elseif ($user['profile'] === 'Professor') {
        $where .= " AND c.id IN (
            SELECT DISTINCT t.course_id 
            FROM turmas t
            JOIN turma_disciplinas td ON t.id = td.turma_id
            JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id
            WHERE tdp.professor_id = ?
        )";
        $params[] = $user['id'];
    } else {
        // Se não for VIP nem Prof/Coord, não vê cursos (mesmo tendo acesso à página)
        $where .= " AND 1=0";
    }
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
/* Modal styles are now handled by core CSS */

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
    <?php if (hasDbPermission('courses.create', false)): ?>
    <button class="btn btn-primary" onclick="openModal()">➕ Novo Curso</button>
    <?php endif; ?>
</div>

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
                            <?php if (hasDbPermission('coordinators.manage', false)): ?>
                            <a href="/courses/coordinators.php?course_id=<?= $c['id'] ?>"
                               class="action-btn" title="Relacionar Coordenadores">👥</a>
                            <?php endif; ?>
                            <a href="/courses/turmas.php?course_id=<?= $c['id'] ?>"
                               class="action-btn" title="Gerenciar Turmas">🎓</a>
                            <?php if (hasDbPermission('courses.update', false)): ?>
                                <a href="/courses/edit.php?id=<?= $c['id'] ?>" class="action-btn" title="Editar">✏️</a>
                                <button type="button" class="action-btn"
                                        title="<?= $c['is_active'] ? 'Desativar' : 'Ativar' ?>"
                                        onclick='toggleCourse(<?= $c['id'] ?>, <?= json_encode($c['name']) ?>, <?= $c['is_active'] ? 'true' : 'false' ?>)'>
                                    <?= $c['is_active'] ? '⏸' : '▶' ?>
                                </button>
                            <?php endif; ?>
                            <?php if (hasDbPermission('courses.delete', false)): ?>
                                <button type="button" class="action-btn danger" title="Excluir"
                                        onclick='deleteCourse(<?= $c['id'] ?>, <?= json_encode($c['name']) ?>)'>🗑</button>
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
<div id="courseModal" class="modal-wrapper" role="dialog" aria-modal="true">
    <div class="modal-overlay" onclick="closeModal()">
        <div class="modal-dialog modal-md" onclick="event.stopPropagation()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <span class="modal-title">📚 Novo Curso</span>
                <button type="button" class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <form method="POST" id="createCourseForm">
                <input type="hidden" name="action" value="create">
                <?= csrf_field() ?>
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
    </div>
</div>



<script>
var courseModal = document.getElementById('courseModal');


function toggleCourse(id, name, isActive) {
    const action = isActive ? 'Desativar' : 'Ativar';
    Modal.confirm({
        title: action + ' Curso',
        message: `Tem certeza que deseja ${action.toLowerCase()} o curso «${name}»?`,
        confirmText: 'Sim, ' + action,
        confirmClass: isActive ? 'btn-danger' : 'btn-primary',
        onConfirm: () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="course_id" value="${id}">
                <input type="hidden" name="csrf_token" value="${(el = document.querySelector('[name=csrf_token]')) ? el.value : ''}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function deleteCourse(id, name) {
    Modal.confirm({
        title: 'Excluir Curso',
        message: `Tem certeza que deseja excluir permanentemente o curso «${name}»? Esta ação não pode ser desfeita.`,
        confirmText: 'Sim, Excluir',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="course_id" value="${id}">
                <input type="hidden" name="csrf_token" value="${(el = document.querySelector('[name=csrf_token]')) ? el.value : ''}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Submit AJAX do formulário de criar
const createCourseForm = document.getElementById('createCourseForm');
if (createCourseForm) createCourseForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    if (typeof Loading !== 'undefined') Loading.show();
    fetch('', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(html => { 
        if (typeof Loading !== 'undefined') Loading.hide();
        window.location.reload(); 
    })
    .catch(err => { 
        if (typeof Loading !== 'undefined') Loading.hide();
        Toast.error('Erro ao criar curso.'); 
    });
});

document.getElementById('courseModal').addEventListener('click', e => { if(e.target===document.getElementById('courseModal')) closeModal(); });
document.addEventListener('keydown', e => { 
    if(e.key==='Escape') { closeModal(); } 
});

// Toasts para feedback
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success): ?> Toast.success(<?= json_encode($success) ?>); <?php endif; ?>
    <?php if ($error): ?> Toast.error(<?= json_encode($error) ?>); <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
