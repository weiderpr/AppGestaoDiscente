<?php
/**
 * Vértice Acadêmico — Etapas de uma Turma
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user    = getCurrentUser();
$allowed = ['Administrador', 'Coordenador', 'Professor', 'Pedagogo', 'Assistente Social', 'Psicólogo'];
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

$turmaId = (int)($_GET['turma_id'] ?? 0);
if (!$turmaId) { header('Location: /courses/index.php'); exit; }

// Garante que a turma pertence a um curso da instituição logada
$stmt = $db->prepare(
    'SELECT t.*, c.name AS course_name, c.id AS course_id
     FROM turmas t
     INNER JOIN courses c ON c.id = t.course_id
     WHERE t.id=? AND c.institution_id=? LIMIT 1'
);
$stmt->execute([$turmaId, $instId]);
$turma = $stmt->fetch();
if (!$turma) { header('Location: /courses/index.php'); exit; }

// Segurança: Coordenador só vê os seus cursos
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

$success = '';
$error   = '';
$action  = $_POST['action'] ?? '';

// ---- CRIAR ----
if ($action === 'create') {
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
        // Validação da soma das notas das etapas
        $stSum = $db->prepare('SELECT SUM(nota_maxima) as total FROM etapas WHERE turma_id = ?');
        $stSum->execute([$turmaId]);
        $resSum = $stSum->fetch();
        $currentSum = (float)($resSum['total'] ?? 0);

        if (($currentSum + $nota_maxima) > $turma['nota_maxima']) {
            $disponivel = $turma['nota_maxima'] - $currentSum;
            if ($disponivel <= 0) {
                $error = 'A nota máxima da turma (' . number_format($turma['nota_maxima'], 2, ',', '.') . ') já foi atingida pela soma das etapas existentes.';
            } else {
                $error = 'A soma das notas das etapas não pode ultrapassar a nota máxima da turma (' . number_format($turma['nota_maxima'], 2, ',', '.') . '). ' .
                         'Disponível para esta etapa: ' . number_format($disponivel, 2, ',', '.') . '.';
            }
        } else {
            $st = $db->prepare('SELECT id FROM etapas WHERE description=? AND turma_id=? LIMIT 1');
        $st->execute([$description, $turmaId]);
        if ($st->fetch()) {
            $error = 'Já existe uma etapa com esta descrição nesta turma.';
        } else {
            $db->prepare('INSERT INTO etapas (turma_id, description, nota_maxima, media_nota) VALUES (?,?,?,?)')
               ->execute([$turmaId, $description, $nota_maxima, $media_nota]);
            $success = "Etapa «{$description}» cadastrada com sucesso!";
        }
        }
    }
}

// ---- TOGGLE ----
if ($action === 'toggle' && !empty($_POST['etapa_id'])) {
    $eid = (int)$_POST['etapa_id'];
    $db->prepare('UPDATE etapas SET is_active = !is_active WHERE id=? AND turma_id=?')
       ->execute([$eid, $turmaId]);
    $success = 'Status da etapa atualizado.';
}

// ---- EXCLUIR ----
if ($action === 'delete' && !empty($_POST['etapa_id'])) {
    $eid = (int)$_POST['etapa_id'];
    $db->prepare('DELETE FROM etapas WHERE id=? AND turma_id=?')
       ->execute([$eid, $turmaId]);
    $success = 'Etapa removida.';
}

// ---- LISTAR ----
$search = trim($_GET['search'] ?? '');
$sql    = 'SELECT * FROM etapas WHERE turma_id=?';
$params = [$turmaId];
if ($search) {
    $sql    .= ' AND description LIKE ?';
    $params[] = "%$search%";
}
$sql .= ' ORDER BY description ASC';
$st   = $db->prepare($sql);
$st->execute($params);
$etapas = $st->fetchAll();

$pageTitle = 'Etapas — ' . $turma['description'];
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.etapas-table-wrap { overflow-x:auto; border-radius:var(--radius-lg); }
.etapas-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.etapas-table th {
    padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:600;
    text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted);
    background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color);
    white-space:nowrap;
}
.etapas-table td { padding:.875rem 1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; }
.etapas-table tr:last-child td { border-bottom:none; }
.etapas-table tr:hover td { background:var(--bg-hover); }
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
.nota-pill { display:inline-block; padding:.2rem .625rem;
    border-radius:var(--radius-full); font-size:.8125rem; font-weight:600; }
</style>

<!-- Page Header / Breadcrumb -->
<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.25rem;">
            <a href="/courses/index.php" style="color:var(--color-primary);text-decoration:none;">📚 Cursos</a>
            &nbsp;›&nbsp;
            <a href="/courses/turmas.php?course_id=<?= $turma['course_id'] ?>" style="color:var(--color-primary);text-decoration:none;"><?= htmlspecialchars($turma['course_name']) ?></a>
            &nbsp;›&nbsp; <?= htmlspecialchars($turma['description']) ?>
        </div>
        <h1 class="page-title">📋 Etapas</h1>
        <p class="page-subtitle">
            Turma: <strong><?= htmlspecialchars($turma['description']) ?></strong>
            &nbsp;·&nbsp; 📚 <?= htmlspecialchars($turma['course_name']) ?>
        </p>
    </div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="/courses/turmas.php?course_id=<?= $turma['course_id'] ?>" class="btn btn-secondary">← Voltar</a>
        <?php if ($user['profile'] !== 'Professor'): ?>
        <button class="btn btn-primary" onclick="openModal()">➕ Nova Etapa</button>
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
            <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
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
            <a href="/courses/etapas.php?turma_id=<?= $turmaId ?>" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabela -->
<div class="card fade-in">
    <div class="card-header">
        <span class="card-title">Lista de Etapas</span>
        <span style="font-size:.875rem;color:var(--text-muted);"><?= count($etapas) ?> etapa(s)</span>
    </div>
    <div class="etapas-table-wrap">
        <table class="etapas-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Descrição</th>
                    <th>Nota Máxima</th>
                    <th>Média de Nota</th>
                    <th>Status</th>
                    <th>Cadastrada em</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($etapas)): ?>
                <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
                    Nenhuma etapa cadastrada nesta turma.
                </td></tr>
                <?php endif; ?>
                <?php foreach ($etapas as $e): ?>
                <tr style="<?= !$e['is_active'] ? 'opacity:.55' : '' ?>">
                    <td style="color:var(--text-muted);font-size:.8125rem;"><?= $e['id'] ?></td>
                    <td><span style="font-weight:600;"><?= htmlspecialchars($e['description']) ?></span></td>
                    <td>
                        <span class="nota-pill" style="background:rgba(79,70,229,.1);color:var(--color-primary);">
                            <?= number_format($e['nota_maxima'], 2, ',', '.') ?>
                        </span>
                    </td>
                    <td>
                        <span class="nota-pill" style="background:rgba(16,185,129,.1);color:var(--color-success);">
                            <?= number_format($e['media_nota'], 2, ',', '.') ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-size:.8125rem;font-weight:600;color:<?= $e['is_active'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                            <?= $e['is_active'] ? '● Ativa' : '○ Inativa' ?>
                        </span>
                    </td>
                    <td style="color:var(--text-muted);white-space:nowrap;font-size:.8125rem;">
                        <?= date('d/m/Y', strtotime($e['created_at'])) ?>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <a href="/courses/lancar_notas.php?etapa_id=<?= $e['id'] ?>"
                               class="action-btn" title="Lançar Notas e Faltas" style="border-color:var(--color-primary);color:var(--color-primary);">📊</a>
                            <?php if ($user['profile'] !== 'Professor'): ?>
                            <a href="/courses/edit_etapa.php?id=<?= $e['id'] ?>&turma_id=<?= $turmaId ?>"
                               class="action-btn" title="Editar">✏️</a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action"   value="toggle">
                                <input type="hidden" name="etapa_id" value="<?= $e['id'] ?>">
                                <button type="submit" class="action-btn"
                                        title="<?= $e['is_active'] ? 'Desativar' : 'Ativar' ?>"
                                        onclick="return confirm('<?= $e['is_active'] ? 'Desativar' : 'Ativar' ?> «<?= htmlspecialchars($e['description']) ?>»?')">
                                    <?= $e['is_active'] ? '⏸' : '▶' ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action"   value="delete">
                                <input type="hidden" name="etapa_id" value="<?= $e['id'] ?>">
                                <button type="submit" class="action-btn danger" title="Excluir"
                                        onclick="return confirm('Excluir permanentemente «<?= htmlspecialchars($e['description']) ?>»?')">🗑</button>
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

<!-- Modal: Nova Etapa -->
<div class="modal-backdrop" id="etapaModal" role="dialog" aria-modal="true">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">📋 Nova Etapa</span>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">

                <div style="padding:.625rem .875rem;border-radius:var(--radius-md);background:var(--color-primary-light);color:var(--color-primary);font-size:.875rem;font-weight:500;">
                    🎓 Turma: <strong><?= htmlspecialchars($turma['description']) ?></strong>
                </div>

                <div class="form-group">
                    <label class="form-label">Descrição da Etapa <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">📋</span>
                        <input type="text" name="description" class="form-control"
                               placeholder="Ex: 1ª Bimestre" required autofocus>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem;">
                    <div class="form-group">
                        <label class="form-label">Nota Máxima <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🏆</span>
                            <input type="number" name="nota_maxima" class="form-control"
                                   value="10" min="0" max="<?= $turma['nota_maxima'] ?>" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Média de Nota <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">✅</span>
                            <input type="number" name="media_nota" class="form-control"
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
function openModal()  { document.getElementById('etapaModal').classList.add('show'); document.body.style.overflow='hidden'; }
function closeModal() { document.getElementById('etapaModal').classList.remove('show'); document.body.style.overflow=''; }
document.getElementById('etapaModal').addEventListener('click', e => { if(e.target===document.getElementById('etapaModal')) closeModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
