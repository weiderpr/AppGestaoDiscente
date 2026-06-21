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
.somativas-table-wrap { overflow-x:auto; border-radius:var(--radius-lg); }
.somativas-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.somativas-table th {
    padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:600;
    text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted);
    background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color);
    white-space:nowrap;
}
.somativas-table td { padding:.875rem 1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; }
.somativas-table tr:last-child td { border-bottom:none; }
.somativas-table tr:hover td { background:var(--bg-hover); }

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
<div class="card fade-in">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <span class="card-title">Períodos de Avaliação</span>
        <span style="font-size:.875rem;color:var(--text-muted);"><?= count($somativas) ?> registro(s)</span>
    </div>
    <div class="somativas-table-wrap">
        <table class="somativas-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Avaliação Somativa</th>
                    <th>Período</th>
                    <th>Turmas</th>
                    <th>Slots</th>
                    <th>Provas/Dia</th>
                    <th>Criador</th>
                    <th>Status</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($somativas as $s):
                    $inicio = date('d/m/Y', strtotime($s['data_inicio']));
                    $fim    = date('d/m/Y', strtotime($s['data_fim']));
                    $podeGrade = $s['total_turmas'] > 0 && $s['total_slots'] > 0;
                    $stColor   = $statusColors[$s['status']] ?? 'color:var(--text-muted)';
                ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:.8125rem;"><?= (int)$s['id'] ?></td>
                    <td>
                        <div style="font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($s['nome']) ?></div>
                        <?php if (!empty($s['descricao'])): ?>
                            <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars($s['descricao']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--text-secondary);white-space:nowrap;">
                        📅 <?= $inicio ?> — <?= $fim ?>
                    </td>
                    <td style="color:var(--text-secondary);">
                        🏫 <?= (int)$s['total_turmas'] ?>
                    </td>
                    <td style="color:var(--text-secondary);">
                        ⏰ <?= (int)$s['total_slots'] ?>
                    </td>
                    <td style="color:var(--text-secondary);">
                        📊 <?= (int)$s['max_provas_por_dia'] ?>
                    </td>
                    <td style="color:var(--text-secondary);">
                        👤 <?= htmlspecialchars($s['criador_nome']) ?>
                    </td>
                    <td>
                        <span style="font-size:.8125rem;font-weight:600;<?= $stColor ?>;">
                            ● <?= htmlspecialchars($s['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <?php if ($podeGrade): ?>
                            <a href="grade.php?id=<?= $s['id'] ?>" class="action-btn" title="Configurar Grade">📅</a>
                            <?php else: ?>
                            <button type="button" class="action-btn" disabled style="opacity:0.4;cursor:not-allowed;"
                                    title="Configure turmas e slots de horário para habilitar">📅</button>
                            <?php endif; ?>

                            <?php if (hasDbPermission('somativas.update', false)): ?>
                            <a href="edit.php?id=<?= $s['id'] ?>" class="action-btn" title="Editar">✏️</a>
                            <?php endif; ?>

                            <?php if (hasDbPermission('somativas.delete', false)): ?>
                            <button type="button" class="action-btn danger" title="Excluir"
                                    onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['nome'])) ?>')">🗑️</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
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
