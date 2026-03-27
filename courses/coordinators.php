<?php
/**
 * Vértice Acadêmico — Gestão de Coordenadores do Curso
 */
require_once __DIR__ . '/../includes/auth.php';
hasDbPermission('coordinators.manage');

$user    = getCurrentUser();

$db     = getDB();
$inst   = getCurrentInstitution();
$instId = $inst['id'];

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/courses/index.php'));
    exit;
}

$courseId = (int)($_GET['course_id'] ?? 0);
if (!$courseId) { header('Location: /courses/index.php'); exit; }

// Verifica se o curso pertence à instituição
$stc = $db->prepare('SELECT * FROM courses WHERE id=? AND institution_id=? LIMIT 1');
$stc->execute([$courseId, $instId]);
$course = $stc->fetch();
if (!$course) { header('Location: /courses/index.php'); exit; }

$success = '';
$error   = '';

// ---- ADICIONAR COORDENADOR ----
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if (!$uid) {
        $error = 'Selecione um coordenador.';
    } else {
        // Verifica se o usuário é coordenador e tem vínculo com a instituição
        $stu = $db->prepare('
            SELECT u.* FROM users u 
            JOIN user_institutions ui ON u.id = ui.user_id 
            WHERE u.id=? AND ui.institution_id=? AND u.profile="Coordenador" AND u.is_active=1
            LIMIT 1
        ');
        $stu->execute([$uid, $instId]);
        if (!$stu->fetch()) {
            $error = 'Usuário inválido ou sem permissão de coordenação nesta instituição.';
        } else {
            // Tenta inserir
            try {
                $db->prepare('INSERT INTO course_coordinators (course_id, user_id) VALUES (?,?)')
                   ->execute([$courseId, $uid]);
                $success = 'Coordenador vinculado com sucesso!';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { $error = 'Este usuário já coordena este curso.'; }
                else { $error = 'Erro ao vincular coordenador.'; }
            }
        }
    }
}

// ---- REMOVER COORDENADOR ----
if (isset($_POST['action']) && $_POST['action'] === 'remove') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $db->prepare('DELETE FROM course_coordinators WHERE course_id=? AND user_id=?')
       ->execute([$courseId, $uid]);
    $success = 'Vínculo de coordenação removido.';
}

// ---- LISTAR ATUAIS ----
$stCoords = $db->prepare('
    SELECT u.* FROM users u 
    JOIN course_coordinators cc ON u.id = cc.user_id 
    WHERE cc.course_id=?
    ORDER BY u.name ASC
');
$stCoords->execute([$courseId]);
$currentCoords = $stCoords->fetchAll();

// ---- LISTAR DISPONÍVEIS (Coordenadores da instituição que não estão vinculados ainda) ----
$stAvailable = $db->prepare('
    SELECT u.* FROM users u 
    JOIN user_institutions ui ON u.id = ui.user_id 
    WHERE ui.institution_id=? AND u.profile="Coordenador" AND u.is_active=1
    AND u.id NOT IN (SELECT user_id FROM course_coordinators WHERE course_id=?)
    ORDER BY u.name ASC
');
$stAvailable->execute([$instId, $courseId]);
$availableCoords = $stAvailable->fetchAll();

$pageTitle = 'Coordenação — ' . $course['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.25rem;">
            <a href="/courses/index.php" style="color:var(--color-primary);text-decoration:none;">📚 Cursos</a>
            &nbsp;›&nbsp; Coordenação
        </div>
        <h1 class="page-title">👥 Coordenação do Curso</h1>
        <p class="page-subtitle">Curso: <strong><?= htmlspecialchars($course['name']) ?></strong></p>
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

    <!-- Coordenadores Atuais -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Coordenadores Vinculados</span>
            <span style="font-size:.875rem;color:var(--text-muted);"><?= count($currentCoords) ?> usuários</span>
        </div>
        <div class="card-body" style="padding:0;">
            <div style="display:flex;flex-direction:column;">
                <?php if (empty($currentCoords)): ?>
                <div style="padding:2rem;text-align:center;color:var(--text-muted);">
                    Nenhum coordenador vinculado a este curso ainda.
                </div>
                <?php endif; ?>
                <?php foreach ($currentCoords as $c): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.875rem 1.25rem;border-bottom:1px solid var(--border-color);">
                    <div style="display:flex;align-items:center;gap:.75rem;">
                        <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;background:var(--bg-surface-2nd);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--text-muted);">
                            <?php if ($c['photo']): ?>
                                <img src="/<?= htmlspecialchars($c['photo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <?= mb_substr($c['name'], 0, 1) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:.9375rem;"><?= htmlspecialchars($c['name']) ?></div>
                            <div style="font-size:.8125rem;color:var(--text-muted);"><?= htmlspecialchars($c['email']) ?></div>
                        </div>
                    </div>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Remover <?= htmlspecialchars($c['name']) ?> da coordenação deste curso?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn btn-ghost danger" style="padding:.4rem .6rem;font-size:.8125rem;">Remover</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Adicionar Novo -->
    <div>
        <div class="card">
            <div class="card-header"><span class="card-title">➕ Adicionar Coordenador</span></div>
            <div class="card-body">
                <?php if (empty($availableCoords)): ?>
                    <p style="font-size:.875rem;color:var(--text-muted);text-align:center;">
                        Não há outros coordenadores disponíveis nesta instituição para este curso.
                    </p>
                <?php else: ?>
                    <form method="POST" style="display:flex;flex-direction:column;gap:1rem;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Selecionar Usuário</label>
                            <select name="user_id" class="form-control" required>
                                <option value="">— Selecione —</option>
                                <?php foreach ($availableCoords as $avail): ?>
                                    <option value="<?= $avail['id'] ?>">
                                        <?= htmlspecialchars($avail['name']) ?> (<?= htmlspecialchars($avail['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">💾 Vincular Coordenador</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-top:1.25rem;">
             <div class="card-body" style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                <h4 style="margin:0 0 .5rem 0;font-size:.875rem;">💡 Informação</h4>
                <p style="margin:0;font-size:.8125rem;color:var(--text-muted);line-height:1.5;">
                    Somente usuários com o perfil <strong>Coordenador</strong> e que possuem vínculo ativo com a instituição <strong><?= htmlspecialchars($inst['name']) ?></strong> aparecem na lista de seleção.
                </p>
             </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
