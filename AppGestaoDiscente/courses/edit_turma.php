<?php
/**
 * Vértice Acadêmico — Edição de Turma
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user    = getCurrentUser();
$allowed = ['Administrador', 'Coordenador'];
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

$id       = (int)($_GET['id']        ?? 0);
$courseId = (int)($_GET['course_id'] ?? 0);
if (!$id || !$courseId) { header('Location: /courses/index.php'); exit; }

// Garante que o curso pertence à instituição logada
$stc = $db->prepare('SELECT * FROM courses WHERE id=? AND institution_id=? LIMIT 1');
$stc->execute([$courseId, $instId]);
$course = $stc->fetch();
if (!$course) { header('Location: /courses/index.php'); exit; }

// Busca a turma garantindo que pertence ao curso
$stmt = $db->prepare('SELECT * FROM turmas WHERE id=? AND course_id=? LIMIT 1');
$stmt->execute([$id, $courseId]);
$turma = $stmt->fetch();
if (!$turma) { header('Location: /courses/turmas.php?course_id=' . $courseId); exit; }

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description     = trim($_POST['description']     ?? '');
    $nota_maxima     = (float)str_replace(',', '.', $_POST['nota_maxima']     ?? '10');
    $media_aprovacao = (float)str_replace(',', '.', $_POST['media_aprovacao'] ?? '6');

    if (strlen($description) < 2) {
        $error = 'Informe a descrição da turma.';
    } elseif ($nota_maxima <= 0) {
        $error = 'A nota máxima deve ser maior que zero.';
    } elseif ($media_aprovacao < 0 || $media_aprovacao > $nota_maxima) {
        $error = 'A média para aprovação deve estar entre 0 e a nota máxima.';
    } else {
        $st = $db->prepare('SELECT id FROM turmas WHERE description=? AND course_id=? AND id!=? LIMIT 1');
        $st->execute([$description, $courseId, $id]);
        if ($st->fetch()) {
            $error = 'Já existe outra turma com esta descrição neste curso.';
        } else {
            $db->prepare('UPDATE turmas SET description=?, nota_maxima=?, media_aprovacao=? WHERE id=? AND course_id=?')
               ->execute([$description, $nota_maxima, $media_aprovacao, $id, $courseId]);
            $success = 'Turma atualizada com sucesso!';
            $stmt = $db->prepare('SELECT * FROM turmas WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $turma = $stmt->fetch();
        }
    }
}

$pageTitle = 'Editar Turma';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.25rem;">
            <a href="/courses/index.php" style="color:var(--color-primary);text-decoration:none;">📚 Cursos</a>
            &nbsp;›&nbsp;
            <a href="/courses/turmas.php?course_id=<?= $courseId ?>" style="color:var(--color-primary);text-decoration:none;"><?= htmlspecialchars($course['name']) ?></a>
            &nbsp;›&nbsp; Editar Turma
        </div>
        <h1 class="page-title">✏️ Editar Turma</h1>
        <p class="page-subtitle">Editando: <strong><?= htmlspecialchars($turma['description']) ?></strong></p>
    </div>
    <a href="/courses/turmas.php?course_id=<?= $courseId ?>" class="btn btn-secondary">← Voltar</a>
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
            <span class="card-title">📝 Dados da Turma</span>
            <span style="font-size:.8125rem;font-weight:600;color:<?= $turma['is_active'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                <?= $turma['is_active'] ? '● Ativa' : '○ Inativa' ?>
            </span>
        </div>
        <div class="card-body">
            <form method="POST" class="auth-form" style="gap:1.125rem;">

                <!-- Curso (leitura) -->
                <div class="form-group">
                    <label class="form-label">Curso</label>
                    <div class="input-group">
                        <span class="input-icon">📚</span>
                        <input type="text" class="form-control"
                               value="<?= htmlspecialchars($course['name']) ?>"
                               disabled style="opacity:.7;cursor:not-allowed;">
                    </div>
                </div>

                <!-- Descrição -->
                <div class="form-group">
                    <label for="description" class="form-label">Descrição <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">🎓</span>
                        <input type="text" id="description" name="description" class="form-control"
                               value="<?= htmlspecialchars($turma['description']) ?>" required>
                    </div>
                </div>

                <!-- Notas -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem;">
                    <div class="form-group">
                        <label for="nota_maxima" class="form-label">Nota Máxima <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🏆</span>
                            <input type="number" id="nota_maxima" name="nota_maxima" class="form-control"
                                   value="<?= number_format($turma['nota_maxima'], 2, '.', '') ?>"
                                   min="0.01" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="media_aprovacao" class="form-label">Média p/ Aprovação <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">✅</span>
                            <input type="number" id="media_aprovacao" name="media_aprovacao" class="form-control"
                                   value="<?= number_format($turma['media_aprovacao'], 2, '.', '') ?>"
                                   min="0" step="0.01" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem;">
                    💾 Salvar Alterações
                </button>
            </form>
        </div>
    </div>

    <!-- Info + Toggle -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">
        <div class="card">
            <div class="card-header"><span class="card-title">ℹ️ Informações</span></div>
            <div class="card-body" style="padding:1rem 1.5rem;">
                <?php $rows = [
                    ['🔢', 'ID',            $turma['id']],
                    ['📅', 'Cadastrada em', date('d/m/Y H:i', strtotime($turma['created_at']))],
                    ['🔄', 'Atualizada em', date('d/m/Y H:i', strtotime($turma['updated_at']))],
                    ['🏆', 'Nota Máxima',   number_format($turma['nota_maxima'], 2, ',', '.')],
                    ['✅', 'Média Aprovação', number_format($turma['media_aprovacao'], 2, ',', '.')],
                ]; ?>
                <?php foreach ($rows as [$icon, $label, $val]): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--border-color);font-size:.875rem;">
                    <span style="color:var(--text-muted);"><?= $icon ?> <?= $label ?></span>
                    <span style="font-weight:500;color:var(--text-primary);"><?= htmlspecialchars((string)$val) ?></span>
                </div>
                <?php endforeach; ?>

                <form method="POST" action="/courses/turmas.php?course_id=<?= $courseId ?>" style="margin-top:1rem;">
                    <input type="hidden" name="action"   value="toggle">
                    <input type="hidden" name="turma_id" value="<?= $turma['id'] ?>">
                    <button type="submit"
                            class="btn btn-full <?= $turma['is_active'] ? 'btn-secondary' : 'btn-primary' ?>"
                            onclick="return confirm('<?= $turma['is_active'] ? 'Desativar' : 'Ativar' ?> esta turma?')"
                            style="margin-top:.25rem;">
                        <?= $turma['is_active'] ? '⏸ Desativar Turma' : '▶ Ativar Turma' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
