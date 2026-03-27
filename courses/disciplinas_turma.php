<?php
/**
 * Vértice Acadêmico — Disciplinas da Turma
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
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
    header('Location: /select_institution.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Turma
$turmaId = (int)($_GET['turma_id'] ?? 0);
if (!$turmaId) { header('Location: /courses/index.php'); exit; }

$stTurma = $db->prepare('
    SELECT t.*, c.name as course_name, c.id as course_id 
    FROM turmas t
    JOIN courses c ON c.id = t.course_id
    WHERE t.id = ? AND c.institution_id = ?
');
$stTurma->execute([$turmaId, $instId]);
$turma = $stTurma->fetch();
if (!$turma) { header('Location: /courses/index.php'); exit; }

$success = '';
$error   = '';

// CSRF check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify($_POST['csrf_token'] ?? '')) {
    $error = 'Token de segurança expirado. Tente novamente.';
} else {

// ---- ADICIONAR DISCIPLINA ----
if (($_POST['action'] ?? '') === 'add_disciplina') {
    $disciplinaCodigo = trim($_POST['disciplina_codigo'] ?? '');
    
    if (!$disciplinaCodigo) {
        $error = 'Selecione uma disciplina.';
    } else {
        try {
            $st = $db->prepare('INSERT INTO turma_disciplinas (turma_id, disciplina_codigo) VALUES (?, ?)');
            $st->execute([$turmaId, $disciplinaCodigo]);
            $success = 'Disciplina adicionada à turma!';
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $error = 'Esta disciplina já está relacionada a esta turma.';
            } else {
                $error = 'Erro ao adicionar disciplina: ' . $e->getMessage();
            }
        }
    }
}

// ---- REMOVER DISCIPLINA ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_disciplina') {
    $tdId = (int)($_POST['turma_disciplina_id'] ?? 0);
    if ($tdId) {
        $db->prepare('DELETE FROM turma_disciplinas WHERE id = ? AND turma_id = ?')
           ->execute([$tdId, $turmaId]);
        $success = 'Disciplina removida da turma!';
    }
}

// ---- ADICIONAR PROFESSOR À DISCIPLINA ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_professor') {
    $tdId        = (int)($_POST['turma_disciplina_id'] ?? 0);
    $professorId = (int)($_POST['professor_id'] ?? 0);
    
    if (!$tdId || !$professorId) {
        $error = 'Selecione um professor.';
    } else {
        try {
            $st = $db->prepare('INSERT INTO turma_disciplina_professores (turma_disciplina_id, professor_id) VALUES (?, ?)');
            $st->execute([$tdId, $professorId]);
            $success = 'Professor vinculado à disciplina!';
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $error = 'Este professor já está atribuído a esta disciplina.';
            } else {
                $error = 'Erro ao vincular professor: ' . $e->getMessage();
            }
        }
    }
}

// ---- REMOVER PROFESSOR ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_professor') {
    $tdpId = (int)($_POST['td_professor_id'] ?? 0);
    if ($tdpId) {
        $db->prepare('DELETE FROM turma_disciplina_professores WHERE id = ?')
           ->execute([$tdpId]);
        $success = 'Professor desvinculado da disciplina.';
    }
}
}

// ---- LISTAR DISCIPLINAS DA TURMA ----
$sqlDisciplinas = "
    SELECT td.id as td_id, td.created_at as td_created_at,
           d.codigo as disciplina_codigo, d.descricao, d.observacoes,
           dc.nome as categoria_nome
    FROM turma_disciplinas td
    JOIN disciplinas d ON d.codigo = td.disciplina_codigo
    JOIN disciplina_categorias dc ON dc.id = d.categoria_id
    WHERE td.turma_id = ?
";

if ($user['profile'] === 'Professor') {
    $sqlDisciplinas .= " AND td.id IN (SELECT turma_disciplina_id FROM turma_disciplina_professores WHERE professor_id = ?)";
}

$sqlDisciplinas .= " ORDER BY dc.nome, d.descricao";
$stDisciplinas = $db->prepare($sqlDisciplinas);

$paramsDisciplinas = [$turmaId];
if ($user['profile'] === 'Professor') {
    $paramsDisciplinas[] = $user['id'];
}
$stDisciplinas->execute($paramsDisciplinas);
$turmaDisciplinas = $stDisciplinas->fetchAll();

// Carregar professores de cada relação
$disciplinasComProfessores = [];
foreach ($turmaDisciplinas as $td) {
    $stProfs = $db->prepare('
        SELECT tdp.id as tdp_id, u.id, u.name, u.email, u.photo
        FROM turma_disciplina_professores tdp
        JOIN users u ON u.id = tdp.professor_id
        WHERE tdp.turma_disciplina_id = ?
        ORDER BY u.name
    ');
    $stProfs->execute([$td['td_id']]);
    $td['professores'] = $stProfs->fetchAll();
    $disciplinasComProfessores[] = $td;
}

// ---- LISTAS PARA SELECTS ----
// Disciplinas disponíveis (não relacionadas)
$stDisponiveis = $db->prepare('
    SELECT d.codigo, d.descricao, dc.nome as categoria_nome
    FROM disciplinas d
    JOIN disciplina_categorias dc ON dc.id = d.categoria_id
    WHERE d.institution_id = ?
      AND d.codigo NOT IN (SELECT disciplina_codigo FROM turma_disciplinas WHERE turma_id = ?)
    ORDER BY dc.nome, d.descricao
');
$stDisponiveis->execute([$instId, $turmaId]);
$disciplinasDisponiveis = $stDisponiveis->fetchAll();

// Professores disponíveis (profile='Professor' OU is_teacher=1)
$stProfessores = $db->prepare('
    SELECT u.id, u.name, u.email, u.photo
    FROM users u
    JOIN user_institutions ui ON ui.user_id = u.id
    WHERE ui.institution_id = ? AND (u.profile = "Professor" OR u.is_teacher = 1) AND u.is_active = 1
    ORDER BY u.name
');
$stProfessores->execute([$instId]);
$professores = $stProfessores->fetchAll();

$pageTitle = 'Disciplinas da Turma';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.disciplinas-table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
.disciplinas-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.disciplinas-table th {
    padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted);
    background: var(--bg-surface-2nd); border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.disciplinas-table td { padding: .875rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.disciplinas-table tr:last-child td { border-bottom: none; }
.disciplinas-table tr:hover td { background: var(--bg-hover); }
.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: var(--radius-md);
    border: 1px solid var(--border-color); background: var(--bg-surface);
    color: var(--text-muted); cursor: pointer; font-size: .875rem;
    transition: all var(--transition-fast); text-decoration: none;
}
.action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
.action-btn.danger:hover { background: #fef2f2; color: var(--color-danger); border-color: var(--color-danger); }
[data-theme="dark"] .action-btn.danger:hover { background: #450a0a; }
.modal-backdrop { position: fixed; inset: 0; z-index: 3000; background: rgba(0,0,0,.5);
    backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center;
    padding: 1rem; opacity: 0; visibility: hidden; transition: all .25s ease; }
.modal-backdrop.show { opacity: 1; visibility: visible; }
.modal { background: var(--bg-surface); border: 1px solid var(--border-color);
    border-radius: var(--radius-xl); width: 100%; max-width: 720px;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,.3);
    transform: translateY(20px) scale(.97); transition: all .25s ease; }
.modal-backdrop.show .modal { transform: translateY(0) scale(1); }
.modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: space-between; }
.modal-title { font-size: 1.0625rem; font-weight: 700; color: var(--text-primary); }
.modal-close { width: 32px; height: 32px; border-radius: var(--radius-md);
    border: 1px solid var(--border-color); background: var(--bg-surface);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    color: var(--text-muted); font-size: 1.125rem; transition: all var(--transition-fast); }
.modal-close:hover { background: var(--bg-hover); color: var(--text-primary); }
.modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
.modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-color);
    display: flex; gap: .75rem; justify-content: flex-end; }
.prof-avatars { display: flex; align-items: center; gap: .25rem; }
.prof-avatar {
    width: 28px; height: 28px; border-radius: 50%; border: 2px solid var(--bg-surface);
    background: var(--gradient-brand); margin-left: -6px; object-fit: cover;
    display: flex; align-items: center; justify-content: center;
    font-size: .6875rem; font-weight: 700; color: white;
}
.prof-avatar:first-child { margin-left: 0; }
.prof-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
.prof-more { background: var(--bg-surface-2nd); color: var(--text-muted); font-size: .625rem; }
</style>

<!-- Page Header -->
<div class="page-header fade-in">
    <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.25rem;">
        <a href="/courses/index.php" style="color:var(--color-primary);text-decoration:none;">📚 Cursos</a>
        &nbsp;›&nbsp;
        <a href="/courses/turmas.php?course_id=<?= $turma['course_id'] ?>" style="color:var(--color-primary);text-decoration:none;">
            <?= htmlspecialchars($turma['course_name']) ?>
        </a>
        &nbsp;›&nbsp; Disciplinas
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 class="page-title">📖 Disciplinas da Turma</h1>
            <p class="page-subtitle">
                <strong><?= htmlspecialchars($turma['description']) ?></strong> 
                · <?= $turma['ano'] ?>
            </p>
        </div>
        <div style="display:flex;gap:.75rem;">
            <a href="/courses/turmas.php?course_id=<?= $turma['course_id'] ?>" class="btn btn-secondary">← Voltar</a>
            <?php if ($user['profile'] !== 'Professor'): ?>
            <button class="btn btn-primary" onclick="openAddDisciplinaModal()">➕ Adicionar Disciplina</button>
            <?php endif; ?>
        </div>
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

<!-- Tabela de Disciplinas -->
<div class="card fade-in">
    <div class="card-header">
        <span class="card-title">Disciplinas Vinculadas</span>
        <span style="font-size:.875rem;color:var(--text-muted);"><?= count($disciplinasComProfessores) ?> disciplina(s)</span>
    </div>
    <?php if (empty($disciplinasComProfessores)): ?>
    <div class="card-body" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
        <div style="font-size:2.5rem;margin-bottom:1rem;">📖</div>
        <div style="font-size:1rem;font-weight:600;color:var(--text-primary);margin-bottom:.5rem;">
            Nenhuma disciplina vinculada a esta turma
        </div>
        <div style="max-width:400px;margin:0 auto 1rem;">
            Clique em "Adicionar Disciplina" para começar a relacionar disciplinas a esta turma.
        </div>
        <?php if (!empty($disciplinasDisponiveis)): ?>
        <button class="btn btn-primary" onclick="openAddDisciplinaModal()">➕ Adicionar Disciplina</button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="disciplinas-table-wrap">
        <table class="disciplinas-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Disciplina</th>
                    <th>Categoria</th>
                    <th>Professores</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($disciplinasComProfessores as $td): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:.8125rem;">
                        <span class="badge-profile badge-Outro" style="font-family:monospace;"><?= htmlspecialchars($td['disciplina_codigo']) ?></span>
                    </td>
                    <td>
                        <span style="font-weight:600;"><?= htmlspecialchars($td['descricao']) ?></span>
                        <?php if (!empty($td['observacoes'])): ?>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem;max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($td['observacoes']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-profile" style="background:var(--color-primary-light);color:var(--color-primary);">
                            <?= htmlspecialchars($td['categoria_nome']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($td['professores'])): ?>
                        <div class="prof-avatars" title="<?= htmlspecialchars(implode(', ', array_column($td['professores'], 'name'))) ?>">
                            <?php 
                            $maxShow = 4;
                            foreach (array_slice($td['professores'], 0, $maxShow) as $idx => $prof): 
                            ?>
                                <div class="prof-avatar">
                                    <?php if (!empty($prof['photo'])): ?>
                                        <img src="/<?= htmlspecialchars($prof['photo']) ?>" alt="<?= htmlspecialchars($prof['name']) ?>">
                                    <?php else: ?>
                                        <?= mb_substr($prof['name'], 0, 1) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($td['professores']) > $maxShow): ?>
                                <div class="prof-avatar prof-more" title="E mais <?= count($td['professores']) - $maxShow ?> professor(es)">
                                    +<?= count($td['professores']) - $maxShow ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <span style="font-size:.8125rem;color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <?php if ($user['profile'] !== 'Professor'): ?>
                            <button class="action-btn" 
                                    onclick='openProfessorModal(<?= $td['td_id'] ?>, <?= json_encode($td['descricao']) ?>)'
                                    title="Gerenciar Professores">
                                👨‍🏫
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remover esta disciplina da turma?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove_disciplina">
                                <input type="hidden" name="turma_disciplina_id" value="<?= $td['td_id'] ?>">
                                <button type="submit" class="action-btn danger" title="Remover Disciplina">🗑</button>
                            </form>
                            <?php else: ?>
                            <span style="font-size:.8125rem;color:var(--text-muted);">Somente leitura</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Adicionar Disciplina -->
<div class="modal-backdrop" id="addDisciplinaModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">➕ Adicionar Disciplina</span>
            <button class="modal-close" onclick="closeAddDisciplinaModal()">✕</button>
        </div>
        <?php if (empty($disciplinasDisponiveis)): ?>
        <div class="modal-body">
            <div class="alert alert-info" style="margin:0;">
                📖 Não há disciplinas disponíveis para adicionar. 
                Cadastre novas disciplinas em 
                <a href="/subjects/index.php">Disciplinas</a>.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAddDisciplinaModal()">Fechar</button>
        </div>
        <?php else: ?>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_disciplina">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Selecione a Disciplina <span class="required">*</span></label>
                    <select name="disciplina_codigo" class="form-control" required autofocus>
                        <option value="">Selecione...</option>
                        <?php 
                        $currentCat = '';
                        foreach ($disciplinasDisponiveis as $d): 
                            if ($d['categoria_nome'] !== $currentCat):
                                if ($currentCat !== '') echo '</optgroup>';
                                echo '<optgroup label="📂 ' . htmlspecialchars($d['categoria_nome']) . '">';
                                $currentCat = $d['categoria_nome'];
                            endif;
                        ?>
                        <option value="<?= $d['codigo'] ?>"><?= htmlspecialchars($d['descricao']) ?></option>
                        <?php endforeach; ?>
                        <?php if ($currentCat) echo '</optgroup>'; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddDisciplinaModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">💾 Adicionar</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Gerenciar Professores (mesmo padrão do coordinators.php) -->
<div class="modal-backdrop" id="professorModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">👨‍🏫 Professores da Disciplina</span>
            <button class="modal-close" onclick="closeProfessorModal()">✕</button>
        </div>
        <div id="professorModalContent">
            <!-- Conteúdo carregado via AJAX -->
        </div>
    </div>
</div>

<script>
function openAddDisciplinaModal() {
    document.getElementById('addDisciplinaModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeAddDisciplinaModal() {
    document.getElementById('addDisciplinaModal').classList.remove('show');
    document.body.style.overflow = '';
}

function openProfessorModal(tdId, disciplinaNome) {
    document.getElementById('professorModalContent').innerHTML = '<div style="padding:2rem;text-align:center;color:var(--text-muted);">Carregando...</div>';
    document.getElementById('professorModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Carregar conteúdo via AJAX
    const csrfEl = document.querySelector('[name=csrf_token]');
    const csrfToken = csrfEl ? csrfEl.value : '';
    fetch('disciplinas_turma_ajax.php?td_id=' + tdId + '&disciplina_nome=' + encodeURIComponent(disciplinaNome) + '&csrf_token=' + encodeURIComponent(csrfToken))
        .then(r => r.text())
        .then(html => {
            document.getElementById('professorModalContent').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('professorModalContent').innerHTML = '<div style="padding:2rem;text-align:center;color:var(--color-danger);">Erro ao carregar dados.</div>';
        });
}
function closeProfessorModal() {
    document.getElementById('professorModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('addDisciplinaModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddDisciplinaModal();
});
document.getElementById('professorModal').addEventListener('click', function(e) {
    if (e.target === this) closeProfessorModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddDisciplinaModal();
        closeProfessorModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
