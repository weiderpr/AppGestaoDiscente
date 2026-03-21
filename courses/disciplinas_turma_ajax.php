<?php
/**
 * Vértice Acadêmico — AJAX: Carregar modal de professores da disciplina
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if (!isset($_GET['td_id']) || !isset($_GET['disciplina_nome'])) {
    die('<div class="alert alert-danger">Parâmetros inválidos.</div>');
}

$tdId           = (int)$_GET['td_id'];
$disciplinaNome = htmlspecialchars($_GET['disciplina_nome']);

$db     = getDB();
$inst   = getCurrentInstitution();
$instId = $inst['id'];

// Verificar se a relação turma-disciplina pertence à instituição
$stCheck = $db->prepare('
    SELECT 1 FROM turma_disciplinas td
    JOIN turmas t ON t.id = td.turma_id
    JOIN courses c ON c.id = t.course_id
    WHERE td.id = ? AND c.institution_id = ?
');
$stCheck->execute([$tdId, $instId]);
if (!$stCheck->fetch()) {
    die('<div class="alert alert-danger">Disciplina não encontrada.</div>');
}

// ---- AÇÕES ----
$success = '';
$error   = '';

// Adicionar professor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_professor') {
    $professorId = (int)($_POST['professor_id'] ?? 0);
    
    if (!$professorId) {
        $error = 'Selecione um professor.';
    } else {
        try {
            $db->prepare('INSERT INTO turma_disciplina_professores (turma_disciplina_id, professor_id) VALUES (?, ?)')
               ->execute([$tdId, $professorId]);
            $success = 'Professor vinculado com sucesso!';
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $error = 'Este professor já está atribuído a esta disciplina.';
            } else {
                $error = 'Erro ao vincular professor: ' . $e->getMessage();
            }
        }
    }
}

// Remover professor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_professor') {
    $tdpId = (int)($_POST['td_professor_id'] ?? 0);
    if ($tdpId) {
        $db->prepare('DELETE FROM turma_disciplina_professores WHERE id = ?')->execute([$tdpId]);
        $success = 'Professor desvinculado.';
    }
}

// ---- LISTAR PROFESSORES ATUAIS ----
$stCurrent = $db->prepare('
    SELECT tdp.id as tdp_id, u.id, u.name, u.email, u.photo
    FROM turma_disciplina_professores tdp
    JOIN users u ON u.id = tdp.professor_id
    WHERE tdp.turma_disciplina_id = ?
    ORDER BY u.name
');
$stCurrent->execute([$tdId]);
$currentProfs = $stCurrent->fetchAll();

// ---- PROFESSORES DISPONÍVEIS ----
$stAvailable = $db->prepare('
    SELECT u.id, u.name, u.email, u.photo
    FROM users u
    JOIN user_institutions ui ON ui.user_id = u.id
    WHERE ui.institution_id = ? AND u.profile = "Professor" AND u.is_active = 1
      AND u.id NOT IN (SELECT professor_id FROM turma_disciplina_professores WHERE turma_disciplina_id = ?)
    ORDER BY u.name
');
$stAvailable->execute([$instId, $tdId]);
$availableProfs = $stAvailable->fetchAll();
?>

<?php if ($success): ?>
<div class="alert alert-success" style="margin:1rem 1rem 0;">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger" style="margin:1rem 1rem 0;">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div style="padding:1rem 1.5rem;">
    <div style="padding:.625rem .875rem;border-radius:var(--radius-md);background:var(--color-primary-light);color:var(--color-primary);font-size:.875rem;font-weight:500;margin-bottom:1rem;">
        📖 Disciplina: <strong><?= $disciplinaNome ?></strong>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.3fr 0.7fr;gap:1rem;padding:0 1.5rem 1.5rem;">

    <!-- Professores Vinculados -->
    <div class="card" style="margin:0;">
        <div class="card-header">
            <span class="card-title">Professores Vinculados</span>
            <span style="font-size:.8125rem;color:var(--text-muted);"><?= count($currentProfs) ?></span>
        </div>
        <div class="card-body" style="padding:0;max-height:280px;overflow-y:auto;">
            <?php if (empty($currentProfs)): ?>
            <div style="padding:1.5rem;text-align:center;color:var(--text-muted);font-size:.875rem;">
                Nenhum professor vinculado a esta disciplina.
            </div>
            <?php else: ?>
            <?php foreach ($currentProfs as $p): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid var(--border-color);">
                <div style="display:flex;align-items:center;gap:.625rem;min-width:0;">
                    <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;background:var(--bg-surface-2nd);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--text-muted);flex-shrink:0;">
                        <?php if (!empty($p['photo']) && file_exists(__DIR__ . '/../' . $p['photo'])): ?>
                            <img src="/<?= htmlspecialchars($p['photo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <?= mb_substr($p['name'], 0, 1) ?>
                        <?php endif; ?>
                    </div>
                    <div style="min-width:0;">
                        <div style="font-weight:600;font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($p['name']) ?>
                        </div>
                        <div style="font-size:.75rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($p['email']) ?>
                        </div>
                    </div>
                </div>
                <form method="POST" style="margin:0;" onsubmit="return confirm('Remover <?= htmlspecialchars($p['name']) ?> desta disciplina?');">
                    <input type="hidden" name="action" value="remove_professor">
                    <input type="hidden" name="td_professor_id" value="<?= $p['tdp_id'] ?>">
                    <button type="submit" class="btn btn-ghost" style="padding:.3rem .5rem;font-size:.75rem;color:var(--color-danger);">Remover</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Adicionar Professor -->
    <div>
        <div class="card" style="margin:0;">
            <div class="card-header"><span class="card-title">➕ Vincular Professor</span></div>
            <div class="card-body">
                <?php if (empty($availableProfs)): ?>
                <p style="font-size:.8125rem;color:var(--text-muted);text-align:center;">
                    Não há outros professores disponíveis para vincular a esta disciplina.
                </p>
                <?php else: ?>
                <form method="POST" style="display:flex;flex-direction:column;gap:.875rem;">
                    <input type="hidden" name="action" value="add_professor">
                    <div class="form-group" style="margin:0;">
                        <select name="professor_id" class="form-control" required>
                            <option value="">— Selecione —</option>
                            <?php foreach ($availableProfs as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['email']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">💾 Vincular Professor</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-top:1rem;">
            <div class="card-body" style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                <h4 style="margin:0 0 .5rem 0;font-size:.8125rem;font-weight:600;">💡 Informação</h4>
                <p style="margin:0;font-size:.75rem;color:var(--text-muted);line-height:1.5;">
                    São listados apenas usuários com o perfil <strong>Professor</strong> e vínculo ativo com a instituição.
                </p>
            </div>
        </div>
    </div>

</div>
