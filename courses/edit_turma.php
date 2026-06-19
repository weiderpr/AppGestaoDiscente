<?php
/**
 * Vértice Acadêmico — Edição de Turma
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/TurmaService.php';

requireLogin();

$user    = getCurrentUser();
hasDbPermission('courses.update');

$db     = getDB();
$inst   = getCurrentInstitution();
$instId = $inst['id'];

$turmaService = new \App\Services\TurmaService();

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
$turma = $turmaService->findById($id);
if (!$turma || $turma['course_id'] != $courseId) { header('Location: /courses/turmas.php?course_id=' . $courseId); exit; }

// Carrega ambiente atual da turma
$ambienteAtual = null;
if (!empty($turma['ambiente_id'])) {
    $stAmb = $db->prepare('SELECT id, descricao, predio_campus FROM manutencao_ambientes WHERE id = ?');
    $stAmb->execute([$turma['ambiente_id']]);
    $ambienteAtual = $stAmb->fetch(PDO::FETCH_ASSOC) ?: null;
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança expirado. Tente novamente.';
    } else {
        $description     = trim($_POST['description']     ?? '');
        $ano             = (int)($_POST['ano']            ?? date('Y'));
        $nota_maxima     = (float)str_replace(',', '.', $_POST['nota_maxima']     ?? '10');
        $media_aprovacao = (float)str_replace(',', '.', $_POST['media_aprovacao'] ?? '6');
        $ambiente_id     = !empty($_POST['ambiente_id'])  ? (int)$_POST['ambiente_id'] : null;

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
                $turmaService->update($id, [
                    'description'     => $description,
                    'ano'             => $ano,
                    'nota_maxima'     => $nota_maxima,
                    'media_aprovacao' => $media_aprovacao,
                    'ambiente_id'     => $ambiente_id,
                ]);
                $success = 'Turma atualizada com sucesso!';
                $turma = $turmaService->findById($id);

                // Recarrega ambiente após salvar
                $ambienteAtual = null;
                if (!empty($turma['ambiente_id'])) {
                    $stAmb2 = $db->prepare('SELECT id, descricao, predio_campus FROM manutencao_ambientes WHERE id = ?');
                    $stAmb2->execute([$turma['ambiente_id']]);
                    $ambienteAtual = $stAmb2->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }
        }
    }
}

$pageTitle = 'Editar Turma';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.amb-wrap { position:relative; }
.amb-drop {
    position:absolute; top:calc(100% + 4px); left:0; right:0;
    background:var(--bg-surface); border:1px solid var(--border-color);
    border-radius:var(--radius-md); box-shadow:0 8px 24px rgba(0,0,0,.12);
    z-index:400; max-height:220px; overflow-y:auto;
}
.amb-item { padding:.625rem .875rem; cursor:pointer; transition:background var(--transition-fast); }
.amb-item:hover { background:var(--bg-hover); }
.amb-item-label { font-size:.875rem; font-weight:500; }
.amb-item-sub   { font-size:.75rem; color:var(--text-muted); }
.amb-selected {
    display:flex; align-items:center; gap:.625rem;
    background:rgba(79,70,229,.07); border:1px solid rgba(79,70,229,.25);
    border-radius:var(--radius-md); padding:.625rem .875rem;
}
.amb-selected-info { flex:1; }
.amb-selected-name { font-size:.875rem; font-weight:600; }
.amb-selected-sub  { font-size:.75rem; color:var(--text-muted); }
</style>

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
                <?= csrf_field() ?>
                
                <div class="form-group">
                    <label class="form-label">Curso</label>
                    <div class="input-group">
                        <span class="input-icon">📚</span>
                        <input type="text" class="form-control"
                               value="<?= htmlspecialchars($course['name']) ?>"
                               disabled style="opacity:.7;cursor:not-allowed;">
                    </div>
                </div>

                <!-- Descrição e Ano -->
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:.875rem;">
                    <div class="form-group">
                        <label for="description" class="form-label">Descrição <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🎓</span>
                            <input type="text" id="description" name="description" class="form-control"
                                   value="<?= htmlspecialchars($turma['description']) ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ano" class="form-label">Ano <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">📅</span>
                            <input type="number" id="ano" name="ano" class="form-control"
                                   value="<?= $turma['ano'] ?>" min="2000" max="2100" required>
                        </div>
                    </div>
                </div>

                <!-- Ambiente / Sala -->
                <div class="form-group">
                    <label class="form-label">Sala / Ambiente</label>
                    <input type="hidden" id="ambiente_id" name="ambiente_id"
                           value="<?= htmlspecialchars((string)($turma['ambiente_id'] ?? '')) ?>">

                    <?php if ($ambienteAtual): ?>
                    <div class="amb-selected" id="amb-selected">
                        <span style="font-size:1.25rem;">🏫</span>
                        <div class="amb-selected-info">
                            <div class="amb-selected-name" id="amb-nome"><?= htmlspecialchars($ambienteAtual['descricao']) ?></div>
                            <?php if (!empty($ambienteAtual['predio_campus'])): ?>
                            <div class="amb-selected-sub" id="amb-predio"><?= htmlspecialchars($ambienteAtual['predio_campus']) ?></div>
                            <?php endif; ?>
                        </div>
                        <button type="button" onclick="limparAmbiente()"
                                class="btn btn-ghost btn-sm" style="color:var(--color-danger);flex-shrink:0;">
                            ✕ Remover
                        </button>
                    </div>
                    <div class="amb-wrap" id="amb-wrap" hidden>
                    <?php else: ?>
                    <div class="amb-selected" id="amb-selected" hidden>
                        <span style="font-size:1.25rem;">🏫</span>
                        <div class="amb-selected-info">
                            <div class="amb-selected-name" id="amb-nome"></div>
                            <div class="amb-selected-sub" id="amb-predio"></div>
                        </div>
                        <button type="button" onclick="limparAmbiente()"
                                class="btn btn-ghost btn-sm" style="color:var(--color-danger);flex-shrink:0;">
                            ✕ Remover
                        </button>
                    </div>
                    <div class="amb-wrap" id="amb-wrap">
                    <?php endif; ?>
                        <input type="text" id="amb-busca" class="form-control"
                               autocomplete="off"
                               placeholder="Buscar sala ou ambiente...">
                        <div class="amb-drop" id="amb-drop" hidden></div>
                    </div>
                    <span class="form-hint" style="font-size:.75rem;color:var(--text-muted);margin-top:.25rem;display:block;">
                        Sala principal onde as provas desta turma serão aplicadas.
                    </span>
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
                    ['📅', 'Ano',           $turma['ano']],
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

                <form method="POST" action="/courses/turmas.php?course_id=<?= $turma['course_id'] ?>" style="margin-top:1rem;">
                    <?= csrf_field() ?>
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

<script>
/* ── Smart Search: Ambiente ──────────────────────────────── */
(function () {
    const input  = document.getElementById('amb-busca');
    const drop   = document.getElementById('amb-drop');
    const hidden = document.getElementById('ambiente_id');
    const wrap   = document.getElementById('amb-wrap');
    const sel    = document.getElementById('amb-selected');
    const nome   = document.getElementById('amb-nome');
    const predio = document.getElementById('amb-predio');

    if (!input) return;

    let tmr = null;

    input.addEventListener('input', () => {
        clearTimeout(tmr);
        const q = input.value.trim();
        if (!q) { drop.hidden = true; return; }
        tmr = setTimeout(() => buscar(q), 220);
    });

    input.addEventListener('focus', () => {
        const q = input.value.trim();
        if (q) buscar(q);
    });

    document.addEventListener('click', e => {
        if (!wrap.contains(e.target)) drop.hidden = true;
    });

    async function buscar(q) {
        try {
            const r = await fetch(`/api/somativas/search.php?type=ambiente&q=${encodeURIComponent(q)}`);
            const d = await r.json();
            renderDrop(d.results || []);
        } catch { drop.hidden = true; }
    }

    function renderDrop(results) {
        if (!results.length) { drop.hidden = true; return; }
        drop.innerHTML = results.map(r =>
            `<div class="amb-item" data-id="${r.id}" data-label="${esc(r.label)}" data-sub="${esc(r.sub || '')}">
                <div class="amb-item-label">${esc(r.label)}</div>
                ${r.sub ? `<div class="amb-item-sub">${esc(r.sub)}</div>` : ''}
             </div>`
        ).join('');
        drop.querySelectorAll('.amb-item').forEach(el => {
            el.addEventListener('click', () => selecionar(el.dataset.id, el.dataset.label, el.dataset.sub));
        });
        drop.hidden = false;
    }

    function selecionar(id, label, sub) {
        hidden.value = id;
        nome.textContent = label;
        predio.textContent = sub || '';
        predio.hidden = !sub;
        drop.hidden = true;
        input.value = '';
        wrap.hidden = true;
        sel.hidden  = false;
    }

    window.limparAmbiente = function () {
        hidden.value = '';
        nome.textContent = '';
        predio.textContent = '';
        sel.hidden  = true;
        wrap.hidden = false;
        input.value = '';
        input.focus();
    };

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
