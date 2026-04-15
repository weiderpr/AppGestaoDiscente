<?php
/**
 * Vértice Acadêmico — Log de Auditoria (UI Premium & Interativa)
 */
hasDbPermission('audit.view_logs');

require_once __DIR__ . '/../../src/App/Services/AuditService.php';

use App\Services\AuditService;

$auditService = new AuditService();
$currentInstitutionId = getCurrentInstitution()['id'];

// Filtros - Padrão: HOJE
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$filterUser  = (int)($_GET['user_id'] ?? 0);
$filterTable = trim($_GET['table_name'] ?? '');

// Se inst_id não estiver na URL, o padrão é a instituição atual.
$filterInst = isset($_GET['inst_id']) ? ($_GET['inst_id'] === '' ? 0 : (int)$_GET['inst_id']) : $currentInstitutionId;

// Contagem de filtros ativos (para o badge)
$activeFiltersCount = 0;
if ($_GET['date_from'] ?? false) $activeFiltersCount++;
if ($_GET['date_to'] ?? false)   $activeFiltersCount++;
if ($filterUser)  $activeFiltersCount++;
if ($filterTable) $activeFiltersCount++;
if ($filterInst != $currentInstitutionId) $activeFiltersCount++;

// Buscar Usuários para o filtro
$users = $db->query("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name")->fetchAll();

// Buscar Tabelas únicas via Service
$tables = $auditService->getUniqueTables();

// Buscar Instituições
$institutions = $db->query("SELECT id, name FROM institutions WHERE is_active = 1 ORDER BY name")->fetchAll();

// Carga Inicial (Primeiros 20) via Service
$initialFilters = [
    'date_from'  => $dateFrom,
    'date_to'    => $dateTo,
    'user_id'    => $filterUser,
    'table_name' => $filterTable,
    'inst_id'    => $filterInst
];
$logs = $auditService->getLogs($initialFilters, 20, 0);

function formatJson($json, $isNew = false) {
    if (!$json) return '<span class="text-muted">Sem dados</span>';
    $data = json_decode($json, true);
    if (!$data) return '<span class="text-muted">' . htmlspecialchars($json) . '</span>';
    
    $html = '<div class="json-viewer-premium">';
    foreach ($data as $key => $val) {
        $valStr = is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : (string)$val;
        if (strlen($valStr) > 50) $valStr = substr($valStr, 0, 47) . '...';
        
        $html .= '<div class="json-row">';
        $html .= '<span class="json-key">' . htmlspecialchars($key) . ':</span>';
        $html .= '<span class="json-val" title="' . htmlspecialchars(is_array($val) ? json_encode($val) : $val) . '">' . htmlspecialchars($valStr) . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
?>

<div class="card settings-card premium-audit-card fade-in">
    <div class="settings-card-header">
        <div class="settings-card-icon">🔍</div>
        <div style="flex:1;">
            <div class="settings-card-title">Auditoria Global</div>
            <div class="settings-card-desc">Histórico completo de alterações e acessos no sistema.</div>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-secondary btn-sm toggle-filter-btn" onclick="toggleAuditFilter()">
                <span class="btn-icon">⚡</span>
                <span class="btn-text">Exibir Filtros</span>
                <?php if ($activeFiltersCount > 0): ?>
                    <span class="active-filter-badge"><?= $activeFiltersCount ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <!-- Filtros Colapsáveis -->
        <div id="auditFilters" class="audit-filters-collapsible">
            <form method="GET" class="audit-form-inner">
                <input type="hidden" name="section" value="audit_logs">
                <div class="filters-grid">
                    <div class="filter-col">
                        <label class="form-label-sm">De</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateFrom ?>">
                    </div>
                    <div class="filter-col">
                        <label class="form-label-sm">Até</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateTo ?>">
                    </div>
                    <div class="filter-col">
                        <label class="form-label-sm">Usuário</label>
                        <select name="user_id" class="form-control form-control-sm">
                            <option value="">Todos</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $filterUser == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-col">
                        <label class="form-label-sm">Tabela</label>
                        <select name="table_name" class="form-control form-control-sm">
                            <option value="">Todas</option>
                            <?php foreach ($tables as $t): ?>
                                <option value="<?= $t ?>" <?= $filterTable == $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-col">
                        <label class="form-label-sm">Unidade (Plant)</label>
                        <select name="inst_id" class="form-control form-control-sm">
                            <option value="" <?= $filterInst === 0 ? 'selected' : '' ?>>Todas (Global + Unidades)</option>
                            <?php foreach ($institutions as $inst): ?>
                                <option value="<?= $inst['id'] ?>" <?= $filterInst == $inst['id'] ? 'selected' : '' ?>><?= htmlspecialchars($inst['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-col btn-col">
                        <button type="submit" class="btn btn-primary btn-sm btn-full">
                            Filtrar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="audit-table-wrapper">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th style="width:120px;">Timestamp</th>
                        <th style="width:180px;">Usuário/Unidade</th>
                        <th style="width:100px;">Ação</th>
                        <th style="width:150px;">Registro</th>
                        <th>Alterações</th>
                        <th style="width:140px;">Metadados</th>
                    </tr>
                </thead>
                <tbody id="audit-tbody">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="audit-empty-td">
                                <div class="audit-empty-state">
                                    <div class="empty-icon">📭</div>
                                    <h3>Nenhum log encontrado</h3>
                                    <p>Não há registros de auditoria para os filtros selecionados.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            $badgeClass = [
                                'CREATE' => 'audit-badge-create',
                                'UPDATE' => 'audit-badge-update',
                                'DELETE' => 'audit-badge-delete'
                            ][$log['action']] ?? 'audit-badge-default';
                        ?>
                        <tr class="audit-tr">
                            <td class="audit-td-time">
                                <span class="time-main"><?= date('d/m/Y', strtotime($log['created_at'])) ?></span>
                                <span class="time-sub"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                            </td>
                            <td class="audit-td-user">
                                <div class="audit-user-info">
                                    <span class="user-name"><?= htmlspecialchars($log['user_name'] ?? 'Sistema') ?></span>
                                    <span class="inst-name"><?= htmlspecialchars($log['inst_name'] ?? 'Global') ?></span>
                                </div>
                            </td>
                            <td class="audit-td-action">
                                <span class="audit-badge <?= $badgeClass ?>"><?= $log['action'] ?></span>
                            </td>
                            <td class="audit-td-record">
                                <span class="table-name"><?= htmlspecialchars($log['table_name']) ?></span>
                                <span class="record-id">ID #<?= $log['record_id'] ?></span>
                            </td>
                            <td class="audit-td-diff">
                                <div class="diff-container">
                                    <div class="diff-side old">
                                        <label>Anterior</label>
                                        <div class="diff-content"><?= formatJson($log['old_values']) ?></div>
                                    </div>
                                    <div class="diff-separator">➜</div>
                                    <div class="diff-side new">
                                        <label>Novo</label>
                                        <div class="diff-content"><?= formatJson($log['new_values']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="audit-td-meta">
                                <div class="meta-row" title="IP Address">
                                    <span class="meta-icon">🌐</span>
                                    <span class="meta-text"><?= $log['ip_address'] ?></span>
                                </div>
                                <div class="meta-row" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                    <span class="meta-icon">📱</span>
                                    <span class="meta-text truncate">Navegador</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Carregamento (Infinite Scroll) -->
            <div id="audit-loader" style="display: none; padding: 2rem; text-align: center; background: var(--bg-surface-2nd); border-top: 1px solid var(--border-color);">
                <div style="display: inline-flex; align-items: center; gap: 0.75rem; color: var(--text-muted); font-size: 0.8125rem;">
                    <div style="width: 18px; height: 18px; border: 2px solid var(--border-color); border-top-color: var(--color-primary); border-radius: 50%; animation: audit-spin 0.8s linear infinite;"></div>
                    <span>Carregando mais registros...</span>
                </div>
            </div>
            
            <div id="audit-no-more" style="display: none; padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.75rem; background: var(--bg-surface-2nd); border-top: 1px solid var(--border-color);">
                ✨ Fim dos registros encontrados
            </div>
        </div>

        <style>
        @keyframes audit-spin { to { transform: rotate(360deg); } }
        </style>
    </div>
</div>

<style>
/* Reset interno para manter o premium */
.premium-audit-card { border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; }

/* Filtros */
.audit-filters-collapsible {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out, padding 0.3s ease-out;
    background: var(--bg-surface-2nd);
    border-bottom: 1px solid var(--border-color);
}
.audit-filters-collapsible.open {
    max-height: 200px; /* Suficiente para os filtros */
    padding: 1.25rem;
}
.audit-form-inner { padding: 0.25rem; }
.filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; align-items: flex-end; }
.filter-col { display: flex; flex-direction: column; gap: 0.375rem; }
.form-label-sm { font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }
.btn-col { min-width: 100px; }

/* Badge de Filtro */
.active-filter-badge {
    background: var(--color-primary);
    color: white;
    font-size: 0.65rem;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 6px;
    font-weight: 800;
}

/* Tabela */
.audit-table-wrapper { overflow-x: auto; width: 100%; }
.audit-table { width: 100%; border-collapse: collapse; min-width: 900px; font-size: 0.7rem; }
.audit-table th {
    background: var(--bg-surface-2nd);
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.575rem;
    letter-spacing: 0.1em;
    padding: 0.875rem 1.25rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
.audit-tr { border-bottom: 1px solid var(--border-color); transition: background 0.15s; }
.audit-tr:hover { background: var(--bg-hover); }
.audit-tr td { padding: 1rem 1.25rem; vertical-align: top; }

/* Celulas Especificas */
.audit-td-time { display: flex; flex-direction: column; }
.time-main { font-weight: 700; color: var(--text-primary); }
.time-sub { color: var(--text-muted); font-size: 0.6rem; }

.user-name { display: block; font-weight: 600; color: var(--text-primary); line-height: 1.3; }
.inst-name { font-size: 0.6rem; color: var(--color-primary); background: var(--color-primary-light); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; }

.audit-badge { padding: 3px 8px; border-radius: 6px; font-size: 0.575rem; font-weight: 800; border: 1px solid transparent; }
.audit-badge-create { background: #ecfdf5; color: #10b981; border-color: #a7f3d0; }
.audit-badge-update { background: #eff6ff; color: #3b82f6; border-color: #bfdbfe; }
.audit-badge-delete { background: #fef2f2; color: #ef4444; border-color: #fecaca; }
.audit-badge-default { background: #f9fafb; color: #6b7280; border-color: #e5e7eb; }

.table-name { display: block; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; font-size: 0.6rem; }
.record-id { color: var(--text-muted); font-size: 0.6rem; }

/* Diffs */
.diff-container { display: flex; gap: 0.75rem; align-items: stretch; background: var(--bg-surface-2nd); border-radius: var(--radius-md); padding: 0.5rem; border: 1px solid var(--border-color-light, #f1f5f9); }
.diff-side { flex: 1; min-width: 140px; overflow: hidden; }
.diff-side label { display: block; font-size: 0.55rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; border-bottom: 1px solid var(--border-color); padding-bottom: 2px; opacity: 0.7; }
.diff-content { font-size: 0.75rem; color: var(--text-secondary); line-height: 1.4; overflow: hidden; }
.diff-separator { align-self: center; color: var(--text-muted); font-weight: 900; opacity: 0.3; font-size: 1.2rem; }

.json-viewer-premium { display: flex; flex-direction: column; gap: 2px; font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 0.7rem; }
.json-row { display: flex; gap: 4px; white-space: nowrap; }
.json-key { color: var(--color-primary); font-weight: 600; opacity: 0.9; }
.json-val { color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; max-width: 100%; }

/* Metadados */
.audit-td-meta { display: flex; flex-direction: column; gap: 0.5rem; }
.meta-row { display: flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); font-size: 0.75rem; white-space: nowrap; }
.meta-text.truncate { max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Empty State */
.audit-empty-td { padding: 5rem !important; }
.audit-empty-state { text-align: center; color: var(--text-muted); }
.empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.audit-empty-state h3 { color: var(--text-primary); margin-bottom: 0.5rem; }
</style>

<script>
function toggleAuditFilter() {
    const filterSection = document.getElementById('auditFilters');
    const btnText = document.querySelector('.toggle-filter-btn .btn-text');
    const btnIcon = document.querySelector('.toggle-filter-btn .btn-icon');
    
    const isOpen = filterSection.classList.toggle('open');
    
    if (isOpen) {
        btnText.textContent = 'Ocultar Filtros';
        btnIcon.textContent = '✖';
    } else {
        btnText.textContent = 'Exibir Filtros';
        btnIcon.textContent = '⚡';
    }
}

// Iniciar aberto se houver filtros ativos
document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const hasActiveFilters = (params.has('user_id') && params.get('user_id') !== '') || 
                             (params.has('table_name') && params.get('table_name') !== '') ||
                             (params.has('inst_id') && params.get('inst_id') !== String(<?= $currentInstitutionId ?>));
    
    // Se o usuário clicou explicitamente em Filtrar, ou se há filtros ativos além das datas
    if (hasActiveFilters) {
        toggleAuditFilter();
    }
});
</script>

<script src="/assets/js/audit_system.js"></script>
