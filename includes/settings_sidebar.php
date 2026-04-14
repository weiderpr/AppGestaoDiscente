<?php
/**
 * Vértice Acadêmico — Settings Sidebar (partial include)
 * Inclua dentro de settings.php APÓS o page-header.
 * Requer: $activeSection (string) — ex: 'backup', 'avaliacoes'
 *         $activeSub    (string) — ex: 'backup', 'restore', 'logs'
 */

$activeSection = $activeSection ?? 'backup';
$activeSub     = $activeSub     ?? 'backup';

// Itens principais com submenu opcional
$navItems = [
    [
        'section'  => 'backup',
        'icon'     => '💾',
        'label'    => 'Backup',
        'desc'     => 'Backup e restauração do banco de dados',
        'children' => [
            ['sub' => 'backup',  'icon' => '📤', 'label' => 'Gerar Backup', 'url' => '/settings.php?section=backup&sub=backup'],
            ['sub' => 'restore', 'icon' => '📥', 'label' => 'Restaurar',    'url' => '/settings.php?section=backup&sub=restore'],
            ['sub' => 'logs',    'icon' => '📜', 'label' => 'Log de Restaurações', 'url' => '/settings.php?section=backup&sub=logs'],
        ],
    ],
    [
        'section'  => 'avaliacoes',
        'icon'     => '📊',
        'label'    => 'Avaliações',
        'desc'     => 'Gerenciamento de avaliações e perguntas',
        'children' => [
            ['sub' => 'tipos', 'icon' => '📂', 'label' => 'Tipos',      'url' => '/settings.php?section=avaliacoes&sub=tipos'],
            ['sub' => 'lista', 'icon' => '📋', 'label' => 'Avaliações', 'url' => '/settings.php?section=avaliacoes&sub=lista'],
        ],
    ],
    [
        'section'  => 'permissoes',
        'icon'     => '🔐',
        'label'    => 'Permissões',
        'desc'     => 'Gerenciamento de acesso por perfil',
        'url'      => '/settings.php?section=permissoes',
    ],
    [
        'section'  => 'audit_logs',
        'icon'     => '🔍',
        'label'    => 'Auditoria',
        'desc'     => 'Logs de alterações globais do sistema',
        'url'      => '/settings.php?section=audit_logs',
    ],
];

// Filtrar itens por perfil (dinâmico via RBAC)
$navItems = array_filter($navItems, function($item) {
    return hasDbPermission('settings.' . $item['section'], false);
});
?>
<aside class="settings-sidebar" id="settingsSidebar" aria-expanded="true" role="navigation" aria-label="Menu de configurações">

    <!-- Header: título + toggle -->
    <div class="sidebar-header">
        <span class="sidebar-title">Configurações</span>
        <button class="sidebar-toggle-btn"
                id="sidebarToggleBtn"
                type="button"
                aria-label="Alternar menu lateral"
                title="Expandir/recolher menu">◀</button>
    </div>

    <!-- Nav Items -->
    <nav class="sidebar-nav" aria-label="Seções de configurações">
        <?php foreach ($navItems as $item):
            $isParentActive = ($activeSection === $item['section']);
            $hasChildren    = !empty($item['children']);
        ?>

        <!-- Item pai -->
        <?php if ($hasChildren): ?>
        <!-- Pai com submenu: não é link, apenas toggle do submenu -->
        <button type="button"
                class="sidebar-nav-item <?= $isParentActive ? 'active' : '' ?>"
                data-submenu="sub-<?= $item['section'] ?>"
                title="<?= htmlspecialchars($item['desc']) ?>"
                aria-expanded="<?= $isParentActive ? 'true' : 'false' ?>">
            <span class="sidebar-nav-icon" aria-hidden="true"><?= $item['icon'] ?></span>
            <span class="sidebar-label"><?= htmlspecialchars($item['label']) ?></span>
        </button>

        <!-- Submenu -->
        <div class="sidebar-submenu <?= $isParentActive ? 'open' : '' ?>" id="sub-<?= $item['section'] ?>">
            <?php foreach ($item['children'] as $child):
                $isSubActive = ($isParentActive && $activeSub === $child['sub']);
            ?>
            <a href="<?= $child['url'] ?>"
               class="sidebar-sub-item <?= $isSubActive ? 'active' : '' ?>"
               aria-current="<?= $isSubActive ? 'page' : 'false' ?>">
                <span aria-hidden="true"><?= $child['icon'] ?></span>
                <span class="sidebar-label"><?= htmlspecialchars($child['label']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <!-- Item simples, sem submenu -->
        <a href="/settings.php?section=<?= $item['section'] ?>"
           class="sidebar-nav-item <?= $isParentActive ? 'active' : '' ?>"
           title="<?= htmlspecialchars($item['desc']) ?>"
           aria-current="<?= $isParentActive ? 'page' : 'false' ?>">
            <span class="sidebar-nav-icon" aria-hidden="true"><?= $item['icon'] ?></span>
            <span class="sidebar-label"><?= htmlspecialchars($item['label']) ?></span>
        </a>
        <?php endif; ?>

        <?php endforeach; ?>
    </nav>

</aside>
