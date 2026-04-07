<?php
/**
 * Vértice Acadêmico — Edição de Etapa
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$user    = getCurrentUser();
hasDbPermission('courses.manage');

$db     = getDB();
$inst   = getCurrentInstitution();
$instId = $inst['id'];

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/courses/index.php'));
    exit;
}

$id      = (int)($_GET['id']       ?? 0);
$turmaId = (int)($_GET['turma_id'] ?? 0);
if (!$id || !$turmaId) { header('Location: /courses/index.php'); exit; }

// Garante que a turma pertence a um curso da instituição logada
$stm = $db->prepare(
    'SELECT t.*, c.name AS course_name, c.id AS course_id
     FROM turmas t
     INNER JOIN courses c ON c.id = t.course_id
     WHERE t.id=? AND c.institution_id=? LIMIT 1'
);
$stm->execute([$turmaId, $instId]);
$turma = $stm->fetch();
if (!$turma) { header('Location: /courses/index.php'); exit; }

// Segurança: Coordenador só edita os seus cursos
if ($user['profile'] === 'Coordenador') {
    $stCheck = $db->prepare('SELECT 1 FROM course_coordinators WHERE course_id=? AND user_id=? LIMIT 1');
    $stCheck->execute([$turma['course_id'], $user['id']]);
    if (!$stCheck->fetch()) {
        header('Location: /courses/index.php');
        exit;
    }
} elseif (in_array($user['profile'], ['Pedagogo', 'Assistente Social', 'Psicólogo'])) {
    // Estes perfis veem todas as turmas
} elseif ($user['profile'] === 'Professor') {
    $stCheck = $db->prepare('
        SELECT 1 FROM turma_disciplinas td
        JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id
        WHERE td.turma_id = ? AND tdp.professor_id = ? LIMIT 1
    ');
    $stCheck->execute([$turmaId, $user['id']]);
    if (!$stCheck->fetch()) {
        header('Location: /courses/index.php');
        exit;
    }
}

// Busca a etapa
$stmt = $db->prepare('SELECT * FROM etapas WHERE id=? AND turma_id=? LIMIT 1');
$stmt->execute([$id, $turmaId]);
$etapa = $stmt->fetch();
if (!$etapa) { header('Location: /courses/etapas.php?turma_id=' . $turmaId); exit; }

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança expirado. Tente novamente.';
    } else {
        $description = trim($_POST['description'] ?? '');
    $nota_maxima = (float)str_replace(',', '.', $_POST['nota_maxima'] ?? '10');
    $media_nota  = (float)str_replace(',', '.', $_POST['media_nota']  ?? '6');

    if (strlen($description) < 2) {
        $error = 'Informe a descrição da etapa.';
    } elseif ($nota_maxima < 0) {
        $error = 'A nota máxima não pode ser negativa.';
    } elseif ($nota_maxima > $turma['nota_maxima']) {
        $error = 'A nota máxima da etapa não pode ser maior que a nota máxima da turma (' . number_format($turma['nota_maxima'], 2, ',', '.') . ').';
    } elseif ($media_nota < 0 || $media_nota > $nota_maxima) {
        $error = 'A média de nota deve estar entre 0 e a nota máxima.';
    } else {
        // Validação da soma das notas das etapas (exceto esta etapa que está sendo editada)
        $stSum = $db->prepare('SELECT SUM(nota_maxima) as total FROM etapas WHERE turma_id = ? AND id != ?');
        $stSum->execute([$turmaId, $id]);
        $resSum = $stSum->fetch();
        $currentSumOthers = (float)($resSum['total'] ?? 0);

        if (($currentSumOthers + $nota_maxima) > $turma['nota_maxima']) {
            $disponivel = $turma['nota_maxima'] - $currentSumOthers;
            if ($disponivel <= 0) {
                $error = 'A nota máxima da turma (' . number_format($turma['nota_maxima'], 2, ',', '.') . ') já foi atingida pela soma das outras etapas.';
            } else {
                $error = 'A soma das notas das etapas não pode ultrapassar a nota máxima da turma (' . number_format($turma['nota_maxima'], 2, ',', '.') . '). ' .
                         'Disponível para esta etapa: ' . number_format($disponivel, 2, ',', '.') . '.';
            }
        } else {
            // Verifica duplicidade (exceto o próprio)
            $st = $db->prepare('SELECT id FROM etapas WHERE description=? AND turma_id=? AND id!=? LIMIT 1');
            $st->execute([$description, $turmaId, $id]);
        if ($st->fetch()) {
            $error = 'Já existe outra etapa com esta descrição nesta turma.';
        } else {
            $db->prepare('UPDATE etapas SET description=?, nota_maxima=?, media_nota=? WHERE id=? AND turma_id=?')
               ->execute([$description, $nota_maxima, $media_nota, $id, $turmaId]);
            $success = 'Etapa atualizada com sucesso!';
            $stmt = $db->prepare('SELECT * FROM etapas WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $etapa = $stmt->fetch();
        }
        }
        }
    }
}

$pageTitle = 'Editar Etapa';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.25rem;">
            <a href="/courses/index.php" style="color:var(--color-primary);text-decoration:none;">📚 Cursos</a>
            &nbsp;›&nbsp;
            <a href="/courses/turmas.php?course_id=<?= $turma['course_id'] ?>" style="color:var(--color-primary);text-decoration:none;"><?= htmlspecialchars($turma['course_name']) ?></a>
            &nbsp;›&nbsp;
            <a href="/courses/etapas.php?turma_id=<?= $turmaId ?>" style="color:var(--color-primary);text-decoration:none;"><?= htmlspecialchars($turma['description']) ?></a>
            &nbsp;›&nbsp; Editar Etapa
        </div>
        <h1 class="page-title">✏️ Editar Etapa</h1>
        <p class="page-subtitle">Editando: <strong><?= htmlspecialchars($etapa['description']) ?></strong></p>
    </div>
    <a href="/courses/etapas.php?turma_id=<?= $turmaId ?>" class="btn btn-secondary">← Voltar</a>
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
            <span class="card-title">📝 Dados da Etapa</span>
            <span style="font-size:.8125rem;font-weight:600;color:<?= $etapa['is_active'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                <?= $etapa['is_active'] ? '● Ativa' : '○ Inativa' ?>
            </span>
        </div>
        <div class="card-body">
            <form method="POST" class="auth-form" style="gap:1.125rem;">
                <?= csrf_field() ?>

                <!-- Turma (leitura) -->
                <div class="form-group">
                    <label class="form-label">Turma</label>
                    <div class="input-group">
                        <span class="input-icon">🎓</span>
                        <input type="text" class="form-control"
                               value="<?= htmlspecialchars($turma['description']) ?> — <?= htmlspecialchars($turma['course_name']) ?>"
                               disabled style="opacity:.7;cursor:not-allowed;">
                    </div>
                </div>

                <!-- Descrição -->
                <div class="form-group">
                    <label for="description" class="form-label">Descrição <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">📋</span>
                        <input type="text" id="description" name="description" class="form-control"
                               value="<?= htmlspecialchars($etapa['description']) ?>" required>
                    </div>
                </div>

                <!-- Notas -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem;">
                    <div class="form-group">
                        <label for="nota_maxima" class="form-label">Nota Máxima <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🏆</span>
                            <input type="number" id="nota_maxima" name="nota_maxima" class="form-control"
                                   value="<?= number_format($etapa['nota_maxima'], 2, '.', '') ?>"
                                   min="0" max="<?= $turma['nota_maxima'] ?>" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="media_nota" class="form-label">Média de Nota <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">✅</span>
                            <input type="number" id="media_nota" name="media_nota" class="form-control"
                                   value="<?= number_format($etapa['media_nota'], 2, '.', '') ?>"
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

    <!-- Info -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">
        <div class="card">
            <div class="card-header"><span class="card-title">ℹ️ Informações</span></div>
            <div class="card-body" style="padding:1rem 1.5rem;">
                <?php $rows = [
                    ['🔢', 'ID',             $etapa['id']],
                    ['📅', 'Cadastrada em',  date('d/m/Y H:i', strtotime($etapa['created_at']))],
                    ['🔄', 'Atualizada em',  date('d/m/Y H:i', strtotime($etapa['updated_at']))],
                    ['🏆', 'Nota Máxima',    number_format($etapa['nota_maxima'], 2, ',', '.')],
                    ['✅', 'Média de Nota',  number_format($etapa['media_nota'], 2, ',', '.')],
                ]; ?>
                <?php foreach ($rows as [$icon, $label, $val]): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--border-color);font-size:.875rem;">
                    <span style="color:var(--text-muted);"><?= $icon ?> <?= $label ?></span>
                    <span style="font-weight:500;color:var(--text-primary);"><?= htmlspecialchars((string)$val) ?></span>
                </div>
                <?php endforeach; ?>

                <form method="POST" action="/courses/etapas.php?turma_id=<?= $turmaId ?>" style="margin-top:1rem;">
                    <input type="hidden" name="action"   value="toggle">
                    <input type="hidden" name="etapa_id" value="<?= $etapa['id'] ?>">
                    <button type="submit"
                            class="btn btn-full <?= $etapa['is_active'] ? 'btn-secondary' : 'btn-primary' ?>"
                            onclick="return confirm('<?= $etapa['is_active'] ? 'Desativar' : 'Ativar' ?> esta etapa?')"
                            style="margin-top:.25rem;">
                        <?= $etapa['is_active'] ? '⏸ Desativar Etapa' : '▶ Ativar Etapa' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
