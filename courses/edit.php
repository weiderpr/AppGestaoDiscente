<?php
/**
 * Vértice Acadêmico — Edição de Curso
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$user    = getCurrentUser();
hasDbPermission('courses.update');

$db     = getDB();
$inst   = getCurrentInstitution();
$instId = $inst['id'];

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/courses/index.php'));
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /courses/index.php'); exit; }

// Busca o curso — garante que pertence à instituição logada
$stmt = $db->prepare('SELECT * FROM courses WHERE id=? AND institution_id=? LIMIT 1');
$stmt->execute([$id, $instId]);
$course = $stmt->fetch();
if (!$course) { header('Location: /courses/index.php'); exit; }

// Segurança: Coordenador só edita os seus cursos
if ($user['profile'] === 'Coordenador') {
    $stCheck = $db->prepare('SELECT 1 FROM course_coordinators WHERE course_id=? AND user_id=? LIMIT 1');
    $stCheck->execute([$id, $user['id']]);
    if (!$stCheck->fetch()) {
        header('Location: /courses/index.php');
        exit;
    }
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança expirado. Tente novamente.';
    } else {
        $name     = trim($_POST['name']     ?? '');
        $location = trim($_POST['location'] ?? '');

        if (strlen($name) < 2) {
            $error = 'Informe o nome do curso.';
        } else {
            // Verifica duplicidade (exceto o próprio)
            $st = $db->prepare('SELECT id FROM courses WHERE name=? AND institution_id=? AND id!=? LIMIT 1');
            $st->execute([$name, $instId, $id]);
            if ($st->fetch()) {
                $error = 'Já existe outro curso com este nome nesta instituição.';
            } else {
                $db->prepare('UPDATE courses SET name=?, location=? WHERE id=? AND institution_id=?')
                   ->execute([$name, $location ?: null, $id, $instId]);
                $success = 'Curso atualizado com sucesso!';
                $stmt = $db->prepare('SELECT * FROM courses WHERE id=? LIMIT 1');
                $stmt->execute([$id]);
                $course = $stmt->fetch();
            }
        }
    }
}

$pageTitle = 'Editar Curso';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 class="page-title">✏️ Editar Curso</h1>
        <p class="page-subtitle">
            Editando: <strong><?= htmlspecialchars($course['name']) ?></strong>
            &nbsp;·&nbsp; 🏫 <?= htmlspecialchars($inst['name']) ?>
        </p>
    </div>
    <a href="/courses/index.php" class="btn btn-secondary">← Voltar</a>
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

<div class="dashboard-grid fade-in">

    <!-- Formulário -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📝 Dados do Curso</span>
            <span style="font-size:.8125rem;font-weight:600;color:<?= $course['is_active'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                <?= $course['is_active'] ? '● Ativo' : '○ Inativo' ?>
            </span>
        </div>
        <div class="card-body">
            <form method="POST" class="auth-form" style="gap:1.125rem;">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="name" class="form-label">Nome do Curso <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">📚</span>
                        <input type="text" id="name" name="name" class="form-control"
                               value="<?= htmlspecialchars($course['name']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="location" class="form-label">Local</label>
                    <div class="input-group">
                        <span class="input-icon">📍</span>
                        <input type="text" id="location" name="location" class="form-control"
                               value="<?= htmlspecialchars($course['location'] ?? '') ?>"
                               placeholder="Ex: Bloco A — Sala 101">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Instituição</label>
                    <div class="input-group">
                        <span class="input-icon">🏫</span>
                        <input type="text" class="form-control"
                               value="<?= htmlspecialchars($inst['name']) ?>"
                               disabled style="opacity:.7;cursor:not-allowed;">
                    </div>
                    <small style="color:var(--text-muted);font-size:.75rem;">O curso pertence à instituição logada.</small>
                </div>

                <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem;">
                    💾 Salvar Alterações
                </button>
            </form>
        </div>
    </div>

    <!-- Info -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">
        <div class="card">
            <div class="card-header"><span class="card-title">ℹ️ Informações</span></div>
            <div class="card-body" style="padding:1rem 1.5rem;">
                <?php $rows = [
                    ['🔢', 'ID', $course['id']],
                    ['📅', 'Cadastrado em', date('d/m/Y H:i', strtotime($course['created_at']))],
                    ['🔄', 'Atualizado em',  date('d/m/Y H:i', strtotime($course['updated_at']))],
                ]; ?>
                <?php foreach ($rows as [$icon, $label, $val]): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--border-color);font-size:.875rem;">
                    <span style="color:var(--text-muted);"><?= $icon ?> <?= $label ?></span>
                    <span style="font-weight:500;color:var(--text-primary);"><?= htmlspecialchars((string)$val) ?></span>
                </div>
                <?php endforeach; ?>

                <!-- Toggle ativo/inativo -->
                <form method="POST" action="/courses/index.php" style="margin-top:1rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"    value="toggle">
                    <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                    <button type="submit"
                            class="btn btn-full <?= $course['is_active'] ? 'btn-secondary' : 'btn-primary' ?>"
                            onclick="return confirm('<?= $course['is_active'] ? 'Desativar' : 'Ativar' ?> este curso?')"
                            style="margin-top:.25rem;">
                        <?= $course['is_active'] ? '⏸ Desativar Curso' : '▶ Ativar Curso' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
