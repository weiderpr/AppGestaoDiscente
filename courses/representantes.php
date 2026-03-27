<?php
/**
 * Vértice Acadêmico — Gestão de Representantes da Turma
 */
require_once __DIR__ . '/../includes/auth.php';
hasDbPermission('representantes.manage');

$user    = getCurrentUser();

$db     = getDB();
$inst   = getCurrentInstitution();
$instId = $inst['id'];

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

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

$success = '';
$error   = '';

// ---- ADICIONAR REPRESENTANTE ----
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $alunoId = (int)($_POST['aluno_id'] ?? 0);
    if (!$alunoId) {
        $error = 'Selecione um aluno.';
    } else {
        // Tenta inserir
        try {
            $db->prepare('INSERT INTO turma_representantes (turma_id, aluno_id) VALUES (?,?)')
               ->execute([$turmaId, $alunoId]);
            $success = 'Representante vinculado com sucesso!';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { $error = 'Este aluno já é representante desta turma.'; }
            else { $error = 'Erro ao vincular representante.'; }
        }
    }
}

// ---- REMOVER REPRESENTANTE ----
if (isset($_POST['action']) && $_POST['action'] === 'remove') {
    $alunoId = (int)($_POST['aluno_id'] ?? 0);
    $db->prepare('DELETE FROM turma_representantes WHERE turma_id=? AND aluno_id=?')
       ->execute([$turmaId, $alunoId]);
    $success = 'Vínculo de representante removido.';
}

// ---- LISTAR ATUAIS ----
$stReps = $db->prepare('
    SELECT a.* FROM alunos a 
    JOIN turma_representantes tr ON a.id = tr.aluno_id 
    WHERE tr.turma_id=?
    ORDER BY a.nome ASC
');
$stReps->execute([$turmaId]);
$currentReps = $stReps->fetchAll();

// ---- LISTAR DISPONÍVEIS (Alunos da turma que não são representantes ainda) ----
$stAvailable = $db->prepare('
    SELECT a.* FROM alunos a 
    JOIN turma_alunos ta ON a.id = ta.aluno_id 
    WHERE ta.turma_id=?
    AND a.id NOT IN (SELECT aluno_id FROM turma_representantes WHERE turma_id=?)
    ORDER BY a.nome ASC
');
$stAvailable->execute([$turmaId, $turmaId]);
$availableReps = $stAvailable->fetchAll();

$pageTitle = 'Representantes — ' . $turma['description'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.25rem;">
            <a href="/courses/index.php" style="color:var(--color-primary);text-decoration:none;">📚 Cursos</a>
            &nbsp;›&nbsp; <a href="/courses/turmas.php?course_id=<?= $courseId ?>" style="color:var(--color-primary);text-decoration:none;"><?= htmlspecialchars($turma['course_name']) ?></a>
            &nbsp;›&nbsp; Representantes
        </div>
        <h1 class="page-title">👥 Representantes da Turma</h1>
        <p class="page-subtitle">Turma: <strong><?= htmlspecialchars($turma['description']) ?> (<?= $turma['ano'] ?>)</strong></p>
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

    <!-- Representantes Atuais -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Representantes Vinculados</span>
            <span style="font-size:.875rem;color:var(--text-muted);"><?= count($currentReps) ?> aluno(s)</span>
        </div>
        <div class="card-body" style="padding:0;">
            <div style="display:flex;flex-direction:column;">
                <?php if (empty($currentReps)): ?>
                <div style="padding:2rem;text-align:center;color:var(--text-muted);">
                    Nenhum representante vinculado a esta turma ainda.
                </div>
                <?php endif; ?>
                <?php foreach ($currentReps as $r): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.875rem 1.25rem;border-bottom:1px solid var(--border-color);">
                    <div style="display:flex;align-items:center;gap:.75rem;">
                        <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;background:var(--bg-surface-2nd);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--text-muted);">
                            <?php if ($r['photo']): ?>
                                <img src="/<?= htmlspecialchars($r['photo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <?= mb_substr($r['nome'], 0, 1) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:.9375rem;"><?= htmlspecialchars($r['nome']) ?></div>
                            <div style="font-size:.8125rem;color:var(--text-muted);">Matrícula: <?= htmlspecialchars($r['matricula']) ?></div>
                        </div>
                    </div>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Remover <?= htmlspecialchars($r['nome']) ?> da representação desta turma?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="aluno_id" value="<?= $r['id'] ?>">
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
            <div class="card-header"><span class="card-title">➕ Adicionar Representante</span></div>
            <div class="card-body">
                <?php if (empty($availableReps)): ?>
                    <p style="font-size:.875rem;color:var(--text-muted);text-align:center;">
                        Não há outros alunos disponíveis nesta turma para representação.
                    </p>
                <?php else: ?>
                    <form method="POST" style="display:flex;flex-direction:column;gap:1rem;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Selecionar Aluno</label>
                            <select name="aluno_id" class="form-control" required>
                                <option value="">— Selecione —</option>
                                <?php foreach ($availableReps as $avail): ?>
                                    <option value="<?= $avail['id'] ?>">
                                        <?= htmlspecialchars($avail['nome']) ?> (<?= htmlspecialchars($avail['matricula']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">💾 Vincular Representante</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-top:1.25rem;">
             <div class="card-body" style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                <h4 style="margin:0 0 .5rem 0;font-size:.875rem;">💡 Informação</h4>
                <p style="margin:0;font-size:.8125rem;color:var(--text-muted);line-height:1.5;">
                    Somente alunos matriculados na turma <strong><?= htmlspecialchars($turma['description']) ?></strong> aparecem na lista de seleção.
                </p>
             </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
