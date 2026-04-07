<?php
/**
 * Vértice Acadêmico — Gestão de Permissões (UI Premium)
 */

$profiles = [
    'Coordenador', 'Diretor', 'Professor', 'Pedagogo', 
    'Assistente Social', 'Naapi', 'Psicólogo', 'Outro'
];

// Mapeamento de Categorias para Organização Visual
$categoryMap = [
    'users'          => 'Sistema',
    'institutions'   => 'Sistema',
    'settings'       => 'Sistema',
    'coordinators'   => 'Sistema',
    'courses'        => 'Acadêmico',
    'subjects'       => 'Acadêmico',
    'students'       => 'Alunos & Notas',
    'grades'         => 'Alunos & Notas',
    'representantes' => 'Alunos & Notas',
    'atendimentos'   => 'Pedagógico',
    'conselhos'      => 'Pedagógico',
    'survey'         => 'Pedagógico'
];

$categoryIcons = [
    'Sistema'        => '⚙️',
    'Acadêmico'      => '📚',
    'Alunos & Notas' => '🎓',
    'Pedagógico'     => '🩺'
];

// Buscar todos os recursos
$stResources = $db->query("SELECT DISTINCT resource FROM profile_permissions ORDER BY resource");
$allResources = $stResources->fetchAll(PDO::FETCH_COLUMN);

// Organizar recursos por categoria
$groupedResources = [];
foreach ($allResources as $res) {
    $parts = explode('.', $res);
    $prefix = $parts[0] ?? 'outro';
    $cat = $categoryMap[$prefix] ?? 'Outros';
    $groupedResources[$cat][] = $res;
}

$currentInstitutionId = getCurrentInstitution()['id'] ?? 0;

// Buscar permissões atuais
$stPerms = $db->prepare("SELECT profile, resource, can_access FROM profile_permissions WHERE instituicao_id = ?");
$stPerms->execute([$currentInstitutionId]);
$currentPerms = [];
while ($row = $stPerms->fetch()) {
    $currentPerms[$row['profile']][$row['resource']] = (bool)$row['can_access'];
}

function getFriendlyName($res) {
    $parts = explode('.', $res);
    $obj = $parts[0] ?? '';
    $act = $parts[1] ?? '';
    
    $names = [
        'users' => 'Usuários',
        'courses' => 'Cursos/Turmas',
        'institutions' => 'Instituições',
        'subjects' => 'Disciplinas',
        'conselhos' => 'Conselhos',
        'atendimentos' => 'Atendimentos',
        'students' => 'Alunos',
        'grades' => 'Notas/Frequência',
        'coordinators' => 'Coordenação',
        'representantes' => 'Representantes',
        'settings' => 'Configurações',
        'survey' => 'Pesquisas'
    ];
    
    $actions = [
        'index' => 'Geral',
        'show' => 'Ver',
        'create' => 'Novo',
        'update' => 'Edit',
        'delete' => 'Excluir',
        'manage' => 'Gerir',
        'backup' => 'Backup',
        'avaliacoes' => 'Avaliações',
        'permissoes' => 'Acesso',
        'schedule' => 'Grade Horária',
        'activities' => 'Atividades Extras',
        'config' => 'Configurações de Grupo',
        'view_all' => 'Ver Tudo (Instituição)',
        'comments' => 'Comentários',
        'view_finished' => 'Ver Finalizados'
    ];
    
    $base = $names[$obj] ?? ucfirst($obj);
    $action = $actions[$act] ?? $act;
    
    if (isset($parts[2]) && isset($actions[$parts[2]])) {
        $action = $actions[$parts[2]];
    }
    
    return "<strong>$base</strong> ($action)";
}
?>

<div class="card settings-card premium-rbac-card">
    <div class="settings-card-header">
        <div class="settings-card-icon">🛡️</div>
        <div>
            <div class="settings-card-title">Matriz de Controle de Acesso (RBAC)</div>
            <div class="settings-card-desc">Gerencie granularmente o que cada perfil pode acessar no sistema.</div>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">

        <form method="POST" id="rbacForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_permissions">
            
            <?php foreach ($allResources as $res): ?>
                <input type="hidden" name="resources[]" value="<?= htmlspecialchars($res) ?>">
            <?php endforeach; ?>

            <div class="rbac-tabs-nav">
                <?php $first = true; foreach (array_keys($groupedResources) as $catName): ?>
                    <button type="button" class="rbac-tab-btn <?= $first ? 'active' : '' ?>" 
                            onclick="switchRbaTab(this, 'cat-<?= md5($catName) ?>')">
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
                            <?php foreach ($profiles as $prof): ?>
                                <th class="profile-header">
                                    <div class="prof-label"><?= $prof ?></div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $first = true; foreach ($groupedResources as $catName => $resources): ?>
                            <tr class="rbac-tab-content rbac-category-group cat-<?= md5($catName) ?>" style="<?= $first ? '' : 'display:none;' ?>">
                                <td colspan="<?= count($profiles) + 1 ?>" class="cat-header-td">
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
                                <?php foreach ($profiles as $prof): ?>
                                <td class="toggle-cell">
                                    <label class="rbac-switch">
                                        <input type="checkbox" name="perms[<?= $prof ?>][<?= $res ?>]" value="1" 
                                               <?= isset($currentPerms[$prof][$res]) && $currentPerms[$prof][$res] ? 'checked' : '' ?>>
                                        <span class="rbac-slider"></span>
                                    </label>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php $first = false; endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="rbac-footer">
                <div class="footer-info">Dica: Use as abas acima para navegar entre os módulos do sistema.</div>
                <button type="submit" class="btn btn-primary btn-lg">
                    <span>💾 Salvar Todas as Permissões</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function switchRbaTab(btn, catClass) {
    // Teatamento dos botões
    document.querySelectorAll('.rbac-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Tratamento do conteúdo
    document.querySelectorAll('.rbac-tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.' + catClass).forEach(el => {
        // Table rows need 'table-row' display
        el.style.display = 'table-row';
    });
}
</script>

<style>
.premium-rbac-card { border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }

/* Tabs Navigation */
.rbac-tabs-nav { 
    display: flex; 
    gap: 0.5rem; 
    padding: 1rem 1.5rem; 
    background: var(--bg-surface-2nd); 
    border-bottom: 1px solid var(--border-color);
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

.rbac-container { 
    width: 100%; 
    overflow-x: auto; 
    max-height: 600px; 
    overflow-y: auto;
    position: relative;
}

.rbac-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.rbac-table th { 
    background: var(--bg-surface); 
    padding: 0.75rem; 
    position: sticky; 
    top: 0; 
    z-index: 10;
    border-bottom: 2px solid var(--border-color);
}

.sticky-col { position: sticky; left: 0; background: var(--bg-surface); z-index: 5; }
.rbac-table th.first-col { z-index: 11; border-right: 1px solid var(--border-color); font-size: 0.75rem; }
.rbac-table td.first-col { border-right: 1px solid var(--border-color); background: var(--bg-surface); max-width: 200px; }

.profile-header { min-width: 85px; text-align: center; border-right: 1px solid var(--border-color-light); }
.prof-label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; color: var(--text-secondary); }

.cat-header-td { padding: 0; background: var(--bg-surface-2nd) !important; }
.category-divider { display: flex; align-items: center; gap: 0.75rem; padding: 1rem; }
.cat-title { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.cat-line { flex: 1; height: 1px; background: var(--border-color); }

.resource-row:hover td { background: var(--bg-hover) !important; }
.resource-info { padding: 0.5rem 1rem !important; }
.res-friendly { font-size: 0.8rem; color: var(--text-primary); margin-bottom: 2px; line-height: 1.2; }
.res-technical { font-size: 0.6rem; color: var(--text-muted); font-family: 'JetBrains Mono', monospace; opacity: 0.6; }

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
    padding: 1.25rem 1.5rem; 
    background: var(--bg-surface-2nd); 
    border-top: 1px solid var(--border-color); 
    display: flex; justify-content: space-between; align-items: center; 
}
.footer-info { font-size: 0.75rem; color: var(--text-muted); }

[data-theme="dark"] .rbac-slider { background-color: #334155; }
[data-theme="dark"] .cat-header-td { background: #1e293b !important; }
</style>
