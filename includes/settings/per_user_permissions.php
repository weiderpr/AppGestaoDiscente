<?php
/**
 * Vértice Acadêmico — Gestão de Permissões Individuais (Perfil Outro)
 */

$currentInstitutionId = getCurrentInstitution()['id'] ?? 0;

// Buscar usuários do perfil "Outro" vinculados a esta instituição
$stUsers = $db->prepare("
    SELECT u.id, u.name, u.email 
    FROM users u
    INNER JOIN user_institutions ui ON ui.user_id = u.id
    WHERE u.profile = 'Outro' AND ui.institution_id = ? AND u.is_active = 1
    ORDER BY u.name
");
$stUsers->execute([$currentInstitutionId]);
$outroUsers = $stUsers->fetchAll(PDO::FETCH_ASSOC);

$selectedUserId = (int)($_GET['user_id'] ?? 0);
$userPermissions = [];

if ($selectedUserId) {
    require_once __DIR__ . '/../../src/App/Services/Service.php';
    require_once __DIR__ . '/../../src/App/Services/PermissionService.php';
    $permissionService = new \App\Services\PermissionService();
    $rawPerms = $permissionService->getPermissionsByUser($selectedUserId);
    foreach ($rawPerms as $p) {
        $userPermissions[$p['resource']] = (bool)$p['can_access'];
    }
}

// Reutilizar a lógica de friendly names e categorias
if (!function_exists('getFriendlyName')) {
    function getFriendlyName($res) {
        $parts = explode('.', $res);
        $obj = $parts[0] ?? '';
        $act = $parts[1] ?? '';
        $names = [
            'users' => 'Usuários', 'courses' => 'Cursos/Turmas', 'institutions' => 'Instituições', 
            'subjects' => 'Disciplinas', 'conselhos' => 'Conselhos', 'atendimentos' => 'Atendimentos', 
            'students' => 'Alunos', 'grades' => 'Notas/Frequência', 'coordinators' => 'Coordenação', 
            'representantes' => 'Representantes', 'settings' => 'Configurações', 'survey' => 'Pesquisas', 
            'social' => 'Feed Social', 'naapi' => 'NAAPI', 'dashboard' => 'Dashboard', 'manutencao' => 'Manutenção'
        ];
        $actions = [
            'index' => 'Geral', 'show' => 'Ver', 'create' => 'Novo', 'update' => 'Alteração', 'delete' => 'Excluir', 
            'manage' => 'Gerir', 'backup' => 'Backup', 'avaliacoes' => 'Avaliações', 'permissoes' => 'Acesso', 
            'schedule' => 'Grade Horária', 'activities' => 'Atividades Extras', 'dispensas' => 'Dispensas', 
            'config' => 'Configurações', 'view_all' => 'Ver Tudo', 'comments' => 'Comentários', 
            'view_finished' => 'Ver Finalizados', 'feed_view' => 'Visualizar Feed', 'online_users' => 'Usuários Online', 
            'view_logs' => 'Ver Logs', 'ambientes' => 'Ambientes'
        ];
        $base = $names[$obj] ?? ucfirst($obj);
        $action = $actions[$act] ?? $act;
        return "<strong>$base</strong> ($action)";
    }
}

$categoryMap = [
    'users' => 'Sistema', 'institutions' => 'Sistema', 'settings' => 'Sistema', 'coordinators' => 'Sistema', 
    'courses' => 'Acadêmico', 'subjects' => 'Acadêmico', 'students' => 'Alunos & Notas', 'grades' => 'Alunos & Notas', 
    'representantes' => 'Alunos & Notas', 'atendimentos' => 'Pedagógico', 'conselhos' => 'Pedagógico', 
    'survey' => 'Pedagógico', 'social' => 'Acadêmico', 'naapi' => 'Pedagógico', 'dashboard' => 'Sistema', 
    'audit' => 'Sistema', 'manutencao' => 'Manutenção'
];
$categoryIcons = ['Sistema' => '⚙️', 'Acadêmico' => '📚', 'Alunos & Notas' => '🎓', 'Pedagógico' => '🩺', 'Manutenção' => '🛠️'];

require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/PermissionService.php';
$pService = new \App\Services\PermissionService();
$allResourcesData = $pService->getUniqueResources();
$allResources = array_column($allResourcesData, 'resource');

$groupedResources = [];
foreach ($allResources as $res) {
    $parts = explode('.', $res);
    $prefix = $parts[0] ?? 'outro';
    $cat = $categoryMap[$prefix] ?? 'Outros';
    $groupedResources[$cat][] = $res;
}
?>

<div class="card settings-card premium-rbac-card">
    <div class="settings-card-header">
        <div class="settings-card-icon">👤</div>
        <div>
            <div class="settings-card-title">Permissões Individuais (Usuários Especiais)</div>
            <div class="settings-card-desc">Defina acessos específicos para usuários do perfil "Outro".</div>
        </div>
    </div>
    
    <div class="card-body" style="padding: 1.5rem; background: var(--bg-surface-2nd); border-bottom: 1px solid var(--border-color);">
        <form method="GET" action="settings.php" id="userSelectForm">
            <input type="hidden" name="section" value="permissoes">
            <input type="hidden" name="sub" value="usuario">
            <div class="form-group mb-0" style="max-width: 500px;">
                <label class="form-label">Selecione o Usuário (Perfil "Outro")</label>
                <div class="input-group">
                    <span class="input-icon">👤</span>
                    <select name="user_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Selecione um usuário para gerenciar --</option>
                        <?php foreach ($outroUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $selectedUserId === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <?php if ($selectedUserId): ?>
    <div class="card-body" style="padding: 0;">
        <form id="individualPermsForm">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">

            <div class="rbac-tabs-nav">
                <?php $first = true; foreach (array_keys($groupedResources) as $catName): ?>
                    <button type="button" class="rbac-tab-btn <?= $first ? 'active' : '' ?>" 
                            onclick="switchIndividualTab(this, 'cat-<?= md5($catName) ?>')">
                        <span class="tab-icon"><?= $categoryIcons[$catName] ?? '📁' ?></span>
                        <span class="tab-label"><?= $catName ?></span>
                    </button>
                <?php $first = false; endforeach; ?>
            </div>

            <div class="rbac-container">
                <table class="rbac-table">
                    <thead>
                        <tr>
                            <th class="sticky-col first-col">Funcionalidade</th>
                            <th class="profile-header" style="min-width: 150px; text-align: center;">
                                <div class="prof-label">Acesso Permitido?</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $first = true; foreach ($groupedResources as $catName => $resources): ?>
                            <tr class="rbac-tab-content rbac-category-group cat-<?= md5($catName) ?>" style="<?= $first ? '' : 'display:none;' ?>">
                                <td colspan="2" class="cat-header-td">
                                    <div class="category-divider">
                                        <span class="cat-title">Recursos de <?= $catName ?></span>
                                        <div class="cat-line"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php foreach ($resources as $res): ?>
                            <tr class="resource-row rbac-tab-content cat-<?= md5($catName) ?>" style="<?= $first ? '' : 'display:none;' ?>">
                                <td class="sticky-col first-col resource-info">
                                    <div class="res-friendly"><?= getFriendlyName($res) ?></div>
                                    <code class="res-technical"><?= htmlspecialchars($res) ?></code>
                                </td>
                                <td class="toggle-cell">
                                    <label class="rbac-switch">
                                        <input type="checkbox" name="perms[<?= $res ?>]" value="1" 
                                               <?= isset($userPermissions[$res]) && $userPermissions[$res] ? 'checked' : '' ?>>
                                        <span class="rbac-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php $first = false; endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="rbac-footer">
                <div class="footer-info">As alterações aplicadas aqui substituem as permissões padrão do perfil "Outro".</div>
                <button type="button" class="btn btn-primary btn-lg" onclick="saveIndividualPermissions()">
                    <span>💾 Salvar Permissões do Usuário</span>
                </button>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="card-body" style="padding: 4rem; text-align: center; color: var(--text-muted);">
        <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;">🔐</div>
        <p>Selecione um usuário acima para gerenciar suas permissões individuais.</p>
    </div>
    <?php endif; ?>
</div>

<script>
function switchIndividualTab(btn, catClass) {
    document.querySelectorAll('.rbac-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.rbac-tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.' + catClass).forEach(el => { el.style.display = 'table-row'; });
}

async function saveIndividualPermissions() {
    const form = document.getElementById('individualPermsForm');
    const formData = new FormData(form);
    
    showLoading();
    try {
        const response = await fetch('/api/settings/user_permissions_ajax.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        hideLoading();
        
        if (result.success) {
            showSuccess('Permissões individuais atualizadas com sucesso!');
        } else {
            showError(result.message || 'Erro ao salvar permissões.');
        }
    } catch (e) {
        hideLoading();
        showError('Erro de conexão com o servidor.');
    }
}
</script>

<style>
.premium-rbac-card { border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }

/* Tabs Navigation */
.rbac-tabs-nav { 
    display: flex; gap: 0.5rem; padding: 1rem 1.5rem; 
    background: var(--bg-surface-2nd); border-bottom: 1px solid var(--border-color);
    overflow-x: auto;
}
.rbac-tab-btn {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1rem; border: 1px solid var(--border-color);
    background: var(--bg-surface); border-radius: 0.5rem;
    cursor: pointer; transition: all 0.2s; white-space: nowrap;
}
.rbac-tab-btn:hover { background: var(--bg-hover); border-color: var(--color-primary-light); }
.rbac-tab-btn.active { 
    background: var(--color-primary); color: white; border-color: var(--color-primary); 
    box-shadow: 0 4px 12px rgba(var(--color-primary-rgb), 0.2);
}
.tab-icon { font-size: 1rem; }
.tab-label { font-size: 0.8125rem; font-weight: 600; }

.rbac-container { width: 100%; overflow-x: auto; max-height: 600px; overflow-y: auto; position: relative; }
.rbac-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.rbac-table th { 
    background: var(--bg-surface); padding: 0.75rem; 
    position: sticky; top: 0; z-index: 10;
    border-bottom: 2px solid var(--border-color);
}

.sticky-col { position: sticky; left: 0; background: var(--bg-surface); z-index: 5; }
.rbac-table th.first-col { z-index: 11; border-right: 1px solid var(--border-color); font-size: 0.75rem; }
.rbac-table td.first-col { border-right: 1px solid var(--border-color); background: var(--bg-surface); max-width: 300px; }

.profile-header { text-align: center; border-right: 1px solid var(--border-color-light); }
.prof-label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; color: var(--text-secondary); }

.cat-header-td { padding: 0; background: var(--bg-surface-2nd) !important; }
.category-divider { display: flex; align-items: center; gap: 0.75rem; padding: 1rem; }
.cat-title { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.cat-line { flex: 1; height: 1px; background: var(--border-color); }

.resource-row:hover td { background: var(--bg-hover) !important; }
.resource-info { padding: 0.5rem 1rem !important; }
.res-friendly { font-size: 0.85rem; color: var(--text-primary); margin-bottom: 2px; line-height: 1.2; }
.res-technical { font-size: 0.65rem; color: var(--text-muted); font-family: 'JetBrains Mono', monospace; opacity: 0.6; }

.toggle-cell { text-align: center; border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color-light); }

/* Mini Switch */
.rbac-switch { position: relative; display: inline-block; width: 34px; height: 18px; }
.rbac-switch input { opacity: 0; width: 0; height: 0; }
.rbac-slider { 
    position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
    background-color: #e2e8f0; transition: .2s; border-radius: 18px;
}
.rbac-slider:before {
    position: absolute; content: ""; height: 12px; width: 12px; left: 3px; bottom: 3px;
    background-color: white; transition: .2s; border-radius: 50%;
}
input:checked + .rbac-slider { background-color: var(--color-primary); }
input:checked + .rbac-slider:before { transform: translateX(16px); }

.rbac-footer { 
    padding: 1.25rem 1.5rem; background: var(--bg-surface-2nd); border-top: 1px solid var(--border-color); 
    display: flex; justify-content: space-between; align-items: center; 
}
.footer-info { font-size: 0.75rem; color: var(--text-muted); }

[data-theme="dark"] .rbac-slider { background-color: #334155; }
[data-theme="dark"] .cat-header-td { background: #1e293b !important; }
</style>
