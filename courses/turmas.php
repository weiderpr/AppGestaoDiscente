<?php
/**
 * Vértice Acadêmico — Turmas de um Curso
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$user    = getCurrentUser();
$allowed = ['Administrador', 'Coordenador', 'Professor'];
if (!$user || !in_array($user['profile'], $allowed)) {
    header('Location: /dashboard.php');
    exit;
}

$db     = getDB();
$inst   = getCurrentInstitution();
$instId = $inst['id'];

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/courses/index.php'));
    exit;
}

// Curso ao qual pertencem as turmas
$courseId = (int)($_GET['course_id'] ?? 0);
if (!$courseId) { header('Location: /courses/index.php'); exit; }

// Garante que o curso pertence à instituição logada
$stCourse = $db->prepare('SELECT * FROM courses WHERE id=? AND institution_id=? LIMIT 1');
$stCourse->execute([$courseId, $instId]);
$course = $stCourse->fetch();
if (!$course) { header('Location: /courses/index.php'); exit; }

// Segurança: Coordenador só vê os seus cursos
if ($user['profile'] === 'Coordenador') {
    $stCheck = $db->prepare('SELECT 1 FROM course_coordinators WHERE course_id=? AND user_id=? LIMIT 1');
    $stCheck->execute([$courseId, $user['id']]);
    if (!$stCheck->fetch()) {
        header('Location: /courses/index.php');
        exit;
    }
} elseif ($user['profile'] === 'Professor') {
    $stCheck = $db->prepare('
        SELECT 1 
        FROM turmas t
        JOIN turma_disciplinas td ON t.id = td.turma_id
        JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id
        WHERE t.course_id = ? AND tdp.professor_id = ? 
        LIMIT 1
    ');
    $stCheck->execute([$courseId, $user['id']]);
    if (!$stCheck->fetch()) {
        header('Location: /courses/index.php');
        exit;
    }
}

$success = '';
$error   = '';
$action  = $_POST['action'] ?? '';

// Verificação CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify($_POST['csrf_token'] ?? '')) {
    $error = 'Token de segurança expirado. Tente novamente.';
} elseif ($action === 'create') {
    $description     = trim($_POST['description']     ?? '');
    $ano             = (int)($_POST['ano']            ?? date('Y'));
    $nota_maxima     = (float)str_replace(',', '.', $_POST['nota_maxima']     ?? '10');
    $media_aprovacao = (float)str_replace(',', '.', $_POST['media_aprovacao'] ?? '6');

    if (strlen($description) < 2) {
        $error = 'Informe a descrição da turma.';
    } elseif ($nota_maxima <= 0) {
        $error = 'A nota máxima deve ser maior que zero.';
    } elseif ($media_aprovacao < 0 || $media_aprovacao > $nota_maxima) {
        $error = 'A média para aprovação deve estar entre 0 e a nota máxima.';
    } else {
        $st = $db->prepare('SELECT id FROM turmas WHERE description=? AND course_id=? LIMIT 1');
        $st->execute([$description, $courseId]);
        if ($st->fetch()) {
            $error = 'Já existe uma turma com esta descrição neste curso.';
        } else {
            $db->prepare('INSERT INTO turmas (course_id, description, ano, nota_maxima, media_aprovacao) VALUES (?,?,?,?,?)')
               ->execute([$courseId, $description, $ano, $nota_maxima, $media_aprovacao]);
            $success = "Turma «{$description}» cadastrada com sucesso!";
        }
    }
}

// ---- TOGGLE ----
if ($action === 'toggle' && !empty($_POST['turma_id'])) {
    $tid = (int)$_POST['turma_id'];
    $db->prepare('UPDATE turmas SET is_active = !is_active WHERE id=? AND course_id=?')
       ->execute([$tid, $courseId]);
    $success = 'Status da turma atualizado.';
}

// ---- EXCLUIR ----
if ($action === 'delete' && !empty($_POST['turma_id'])) {
    $tid = (int)$_POST['turma_id'];
    $db->prepare('DELETE FROM turmas WHERE id=? AND course_id=?')
       ->execute([$tid, $courseId]);
    $success = 'Turma removida.';
}

// ---- LISTAR TURMAS ----
$search = trim($_GET['search'] ?? '');
$sql = "
    SELECT t.*,
           GROUP_CONCAT(a.nome ORDER BY a.nome ASC SEPARATOR '||') as rep_names,
           GROUP_CONCAT(COALESCE(a.photo, '') ORDER BY a.nome ASC SEPARATOR '||') as rep_photos
    FROM turmas t
    LEFT JOIN turma_representantes tr ON t.id = tr.turma_id
    LEFT JOIN alunos a ON a.id = tr.aluno_id
    WHERE t.course_id = ?
";
$params = [$courseId];

if ($user['profile'] === 'Professor') {
    $sql .= " AND t.id IN (
        SELECT DISTINCT t2.id
        FROM turmas t2
        JOIN turma_disciplinas td ON t2.id = td.turma_id
        JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id
        WHERE tdp.professor_id = ?
    )";
    $params[] = $user['id'];
}
if ($search) {
    $sql .= " AND (t.description LIKE ? OR t.ano LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " GROUP BY t.id ORDER BY t.ano DESC, t.description ASC";
$st = $db->prepare($sql);
$st->execute($params);
$turmas = $st->fetchAll();

$pageTitle = 'Turmas — ' . $course['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.turmas-table-wrap { overflow-x:auto; border-radius:var(--radius-lg); }
.turmas-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.turmas-table th {
    padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:600;
    text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted);
    background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color);
    white-space:nowrap;
}
.turmas-table td { padding:.875rem 1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; }
.turmas-table tr:last-child td { border-bottom:none; }
.turmas-table tr:hover td { background:var(--bg-hover); }
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
    border-radius:var(--radius-xl); width:100%; max-width:500px;
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
.nota-pill {
    display:inline-block; padding:.2rem .625rem;
    border-radius:var(--radius-full); font-size:.8125rem; font-weight:600;
}
/* Avatares dos Representantes */
.rep-stack { display:flex; align-items:center; margin-top:.375rem; }
.rep-avatar {
    width:24px; height:24px; border-radius:50%; border:2px solid var(--bg-surface);
    background:var(--bg-surface-2nd); margin-left:-8px; object-fit:cover;
    display:flex; align-items:center; justify-content:center;
    font-size:.625rem; font-weight:700; color:var(--text-muted);
    position:relative; cursor:help;
}
.rep-avatar:first-child { margin-left:0; }
.rep-avatar img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
</style>

<!-- Breadcrumb / Page Header -->
<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.25rem;">
            <a href="/courses/index.php" style="color:var(--color-primary);text-decoration:none;">📚 Cursos</a>
            &nbsp;›&nbsp; <?= htmlspecialchars($course['name']) ?>
        </div>
        <h1 class="page-title">🎓 Turmas</h1>
        <p class="page-subtitle">
            Curso: <strong><?= htmlspecialchars($course['name']) ?></strong>
            <?php if ($course['location']): ?>&nbsp;·&nbsp; 📍 <?= htmlspecialchars($course['location']) ?><?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="/courses/index.php" class="btn btn-secondary">← Voltar</a>
        <?php if ($user['profile'] !== 'Professor'): ?>
        <button class="btn btn-primary" onclick="openModal()">➕ Nova Turma</button>
        <?php endif; ?>
    </div>
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
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            <div class="form-group" style="flex:1;min-width:220px;margin:0;">
                <div class="input-group">
                    <span class="input-icon">🔍</span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Buscar por descrição..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($search): ?>
            <a href="/courses/turmas.php?course_id=<?= $courseId ?>" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabela -->
<div class="card fade-in">
    <div class="card-header">
        <span class="card-title">Lista de Turmas</span>
        <span style="font-size:.875rem;color:var(--text-muted);"><?= count($turmas) ?> turma(s)</span>
    </div>
    <div class="turmas-table-wrap">
        <table class="turmas-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Descrição</th>
                    <th>Ano</th>
                    <th>Nota Máxima</th>
                    <th>Média p/ Aprovação</th>
                    <th>Status</th>
                    <th>Cadastrado em</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($turmas)): ?>
                <tr><td colspan="8" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
                    Nenhuma turma cadastrada neste curso.
                </td></tr>
                <?php endif; ?>
                <?php foreach ($turmas as $t): ?>
                <tr style="<?= !$t['is_active'] ? 'opacity:.55' : '' ?>">
                    <td style="color:var(--text-muted);font-size:.8125rem;"><?= $t['id'] ?></td>
                    <td>
                        <span style="font-weight:600;"><?= htmlspecialchars($t['description']) ?></span>
                        <?php if (!empty($t['rep_names'])): ?>
                            <div class="rep-stack">
                                <?php 
                                $names  = explode('||', $t['rep_names']);
                                $photos = explode('||', $t['rep_photos']);
                                foreach ($names as $idx => $name): 
                                    if ($idx >= 5) { // Limite visual
                                        echo '<div class="rep-avatar" title="E mais ' . (count($names)-$idx) . '...">+' . (count($names)-$idx) . '</div>';
                                        break;
                                    }
                                    $photo = $photos[$idx] ?? '';
                                ?>
                                    <div class="rep-avatar" title="Representante: <?= htmlspecialchars($name) ?>">
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
                    <td><span class="badge-profile badge-Outro"><?= $t['ano'] ?></span></td>
                    <td>
                        <span class="nota-pill" style="background:rgba(79,70,229,.1);color:var(--color-primary);">
                            <?= number_format($t['nota_maxima'], 2, ',', '.') ?>
                        </span>
                    </td>
                    <td>
                        <span class="nota-pill" style="background:rgba(16,185,129,.1);color:var(--color-success);">
                            <?= number_format($t['media_aprovacao'], 2, ',', '.') ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-size:.8125rem;font-weight:600;color:<?= $t['is_active'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                            <?= $t['is_active'] ? '● Ativa' : '○ Inativa' ?>
                        </span>
                    </td>
                    <td style="color:var(--text-muted);white-space:nowrap;font-size:.8125rem;">
                        <?= date('d/m/Y', strtotime($t['created_at'])) ?>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <?php if ($user['profile'] !== 'Professor'): ?>
                            <a href="/courses/disciplinas_turma.php?turma_id=<?= $t['id'] ?>"
                               class="action-btn" title="Gerenciar Disciplinas">📖</a>
                            <a href="/courses/representantes.php?turma_id=<?= $t['id'] ?>"
                               class="action-btn" title="Relacionar Representantes">👥</a>
                            <?php endif; ?>
                            <a href="/courses/alunos.php?turma_id=<?= $t['id'] ?>"
                               class="action-btn" title="Visualizar Alunos">👤</a>
                            <a href="/courses/etapas.php?turma_id=<?= $t['id'] ?>"
                               class="action-btn" title="Lançar Notas/Faltas">📋</a>
                            <?php if ($user['profile'] !== 'Professor'): ?>
                            <a href="/courses/edit_turma.php?id=<?= $t['id'] ?>&course_id=<?= $courseId ?>"
                               class="action-btn" title="Editar">✏️</a>
                            <button type="button" class="action-btn"
                                    title="<?= $t['is_active'] ? 'Desativar' : 'Ativar' ?>"
                                    onclick="toggleTurma(<?= $t['id'] ?>, '<?= htmlspecialchars($t['description']) ?>', <?= $t['is_active'] ? 'true' : 'false' ?>)">
                                <?= $t['is_active'] ? '⏸' : '▶' ?>
                            </button>
                            <button type="button" class="action-btn danger" title="Excluir"
                                    onclick="deleteTurma(<?= $t['id'] ?>, '<?= htmlspecialchars($t['description']) ?>')">🗑</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Nova Turma -->
<div class="modal-backdrop" id="turmaModal" role="dialog" aria-modal="true">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">🎓 Nova Turma</span>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <form method="POST" id="createTurmaForm">
            <input type="hidden" name="action" value="create">
            <?= csrf_field() ?>
            <div class="modal-body">

                <div style="padding:.625rem .875rem;border-radius:var(--radius-md);background:var(--color-primary-light);color:var(--color-primary);font-size:.875rem;font-weight:500;">
                    📚 Curso: <strong><?= htmlspecialchars($course['name']) ?></strong>
                </div>

                <div style="display:grid;grid-template-columns:2fr 1fr;gap:.875rem;">
                    <div class="form-group">
                        <label class="form-label">Descrição da Turma <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🎓</span>
                            <input type="text" name="description" class="form-control"
                                   placeholder="Ex: 1º Ano" required autofocus>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ano <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">📅</span>
                            <input type="number" name="ano" class="form-control"
                                   value="<?= date('Y') ?>" min="2000" max="2100" required>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem;">
                    <div class="form-group">
                        <label class="form-label">Nota Máxima <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🏆</span>
                            <input type="number" name="nota_maxima" class="form-control"
                                   value="10" min="0.01" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Média p/ Aprovação <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">✅</span>
                            <input type="number" name="media_aprovacao" class="form-control"
                                   value="6" min="0" step="0.01" required>
                        </div>
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

<script>
function openModal()  { document.getElementById('turmaModal').classList.add('show'); document.body.style.overflow='hidden'; }
function closeModal() { document.getElementById('turmaModal').classList.remove('show'); document.body.style.overflow=''; }
document.getElementById('turmaModal').addEventListener('click', e => { if(e.target===document.getElementById('turmaModal')) closeModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });

// Toasts para feedback
<?php if ($success || $error): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success): ?>
    showSuccess(<?= json_encode($success) ?>);
    <?php endif; ?>
    <?php if ($error): ?>
    showError(<?= json_encode($error) ?>);
    <?php endif; ?>
});
<?php endif; ?>

// Toggle e Delete com confirmModal
function toggleTurma(id, desc, isActive) {
    const action = isActive ? 'Desativar' : 'Ativar';
    confirmModal({
        title: action + ' Turma',
        message: `Tem certeza que deseja ${action.toLowerCase()} a turma "${desc}"?`,
        confirmText: action,
        confirmClass: isActive ? 'btn-warning' : 'btn-success',
        onConfirm: () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="turma_id" value="${id}">
                <input type="hidden" name="csrf_token" value="${document.querySelector('[name=csrf_token]')?.value || ''}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function deleteTurma(id, desc) {
    confirmModal({
        title: 'Excluir Turma',
        message: `Tem certeza que deseja excluir permanentemente a turma "${desc}"?`,
        confirmText: 'Excluir',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="turma_id" value="${id}">
                <input type="hidden" name="csrf_token" value="${document.querySelector('[name=csrf_token]')?.value || ''}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Submit AJAX do formulário de criar
document.getElementById('createTurmaForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    showLoading('Criando turma...');
    fetch('', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(html => { hideLoading(); window.location.reload(); })
    .catch(err => { hideLoading(); showError('Erro ao criar turma.'); });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
