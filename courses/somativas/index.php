<?php
/**
 * Vértice Acadêmico — Avaliações Somativas (CRUD)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/SomativaService.php';

requireLogin();
$user   = getCurrentUser();
hasDbPermission('somativas.index');

$inst   = getCurrentInstitution();
$instId = $inst['id'] ?? 0;

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/courses/somativas/index.php'));
    exit;
}

$service   = new \App\Services\SomativaService();
$search    = trim($_GET['search'] ?? '');
$somativas = $service->getAll($instId, $search);

$statusColors = [
    'Rascunho'     => 'color:var(--text-muted)',
    'Configurando' => 'color:#92400e',
    'Publicado'    => 'color:var(--color-success)',
    'Encerrado'    => 'color:var(--text-muted)',
];

$pageTitle = 'Avaliações Somativas';
$extraCSS  = ['/assets/css/somativas.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.som-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.25rem;
}
.som-card {
    display: flex;
    flex-direction: column;
    gap: .875rem;
}
.som-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .75rem;
}
.som-card-nome {
    font-size: .9375rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 .25rem;
}
.som-card-periodo {
    font-size: .8125rem;
    color: var(--text-muted);
}
.som-status-badge {
    font-size: .72rem;
    font-weight: 700;
    padding: .2rem .625rem;
    border-radius: 999px;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: .04em;
    background: var(--bg-surface-2nd);
    flex-shrink: 0;
}
.som-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    font-size: .8rem;
    color: var(--text-secondary);
}
.som-meta-item {
    display: flex;
    align-items: center;
    gap: .3rem;
    background: var(--bg-surface-2nd);
    border: 1px solid var(--border-color);
    border-radius: 999px;
    padding: .15rem .5rem;
}
.som-desc {
    font-size: .8125rem;
    color: var(--text-muted);
    line-height: 1.5;
    padding: .625rem .875rem;
    background: var(--bg-surface-2nd);
    border-radius: var(--radius-md);
}
.som-actions {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    padding-top: .75rem;
    border-top: 1px solid var(--border-color);
    margin-top: auto;
}
.som-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-muted);
}
.som-empty-icon { font-size: 2.5rem; margin-bottom: .75rem; }
.som-empty-title { font-size: 1rem; font-weight: 600; margin-bottom: .375rem; color: var(--text-secondary); }
.som-empty-desc  { font-size: .875rem; margin-bottom: 1.25rem; }
</style>

<!-- Cabeçalho -->
<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 class="page-title">📋 Avaliações Somativas</h1>
        <p class="page-subtitle">Gestão de períodos de avaliação somativa institucional</p>
    </div>
    <?php if (hasDbPermission('somativas.create', false)): ?>
    <a href="edit.php" class="btn btn-primary">+ Nova Somativa</a>
    <?php endif; ?>
</div>

<!-- Filtro -->
<div class="card fade-in" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.5rem;">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:220px;margin:0;">
                <div class="input-group">
                    <span class="input-icon">🔍</span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Buscar por nome..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($search): ?>
            <a href="index.php" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Conteúdo -->
<?php if (empty($somativas)): ?>
<div class="card fade-in">
    <div class="card-body">
        <div class="som-empty">
            <div class="som-empty-icon">📋</div>
            <div class="som-empty-title">
                <?= $search
                    ? 'Nenhuma somativa encontrada para "' . htmlspecialchars($search) . '"'
                    : 'Nenhuma avaliação somativa cadastrada' ?>
            </div>
            <?php if (!$search && hasDbPermission('somativas.create', false)): ?>
            <div class="som-empty-desc">Crie a primeira avaliação somativa para começar</div>
            <a href="edit.php" class="btn btn-primary">+ Nova Somativa</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>
<div class="som-grid fade-in">
    <?php foreach ($somativas as $s):
        $inicio = date('d/m/Y', strtotime($s['data_inicio']));
        $fim    = date('d/m/Y', strtotime($s['data_fim']));
        $podeGrade = $s['total_turmas'] > 0 && $s['total_slots'] > 0;
        $stColor   = $statusColors[$s['status']] ?? 'color:var(--text-muted)';
    ?>
    <div class="card">
        <div class="card-body som-card">
            <div class="som-card-top">
                <div>
                    <h3 class="som-card-nome"><?= htmlspecialchars($s['nome']) ?></h3>
                    <div class="som-card-periodo">📅 <?= $inicio ?> — <?= $fim ?></div>
                </div>
                <span class="som-status-badge" style="<?= $stColor ?>">
                    <?= htmlspecialchars($s['status']) ?>
                </span>
            </div>

            <div class="som-meta">
                <span class="som-meta-item">🏫 <?= (int)$s['total_turmas'] ?> turma<?= $s['total_turmas'] != 1 ? 's' : '' ?></span>
                <span class="som-meta-item">⏰ <?= (int)$s['total_slots'] ?> slot<?= $s['total_slots'] != 1 ? 's' : '' ?></span>
                <span class="som-meta-item">📊 Máx <?= (int)$s['max_provas_por_dia'] ?> prova<?= $s['max_provas_por_dia'] != 1 ? 's' : '' ?>/dia</span>
                <span class="som-meta-item">👤 <?= htmlspecialchars($s['criador_nome']) ?></span>
            </div>

            <?php if (!empty($s['descricao'])): ?>
            <div class="som-desc"><?= htmlspecialchars($s['descricao']) ?></div>
            <?php endif; ?>

            <div class="som-actions">
                <?php if ($podeGrade): ?>
                <a href="grade.php?id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">📅 Configurar Grade</a>
                <?php else: ?>
                <button type="button" class="btn btn-secondary btn-sm" disabled
                        title="Configure turmas e slots de horário para habilitar">
                    📅 Grade (incompleto)
                </button>
                <?php endif; ?>

                <?php if (hasDbPermission('somativas.update', false)): ?>
                <a href="edit.php?id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">✏️ Editar</a>
                <?php endif; ?>

                <?php if (hasDbPermission('somativas.delete', false)): ?>
                <button type="button" class="btn btn-ghost btn-sm"
                        style="color:var(--color-danger)"
                        onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['nome'])) ?>')">
                    🗑️ Excluir
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function confirmDelete(id, nome) {
    Modal.confirm({
        title:        'Excluir Somativa',
        message:      `Tem certeza que deseja excluir permanentemente a somativa <strong>${nome}</strong>?<br><small style="color:var(--color-danger)">Esta ação removerá também todas as configurações e a grade de horários.</small>`,
        confirmText:  'Sim, Excluir',
        confirmClass: 'btn-danger',
        onConfirm: async () => {
            const fd = new FormData();
            fd.append('action',     'delete');
            fd.append('csrf_token', window.csrfToken);
            fd.append('id',         id);
            try {
                const res  = await fetch('/api/somativas/index.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    Toast.success('Somativa removida com sucesso.');
                    setTimeout(() => location.reload(), 900);
                } else {
                    Toast.error(data.message || 'Erro ao remover.');
                }
            } catch (e) {
                Toast.error('Erro de comunicação com o servidor.');
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
