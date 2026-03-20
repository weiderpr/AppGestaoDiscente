<?php
/**
 * Vértice Acadêmico — Header / Navbar (reutilizável)
 * Requer: $user (array com dados do usuário logado)
 */

if (!isset($user)) $user = getCurrentUser();
$theme      = $user['theme'] ?? 'light';
$userName   = htmlspecialchars($user['name'] ?? '');
$userEmail  = htmlspecialchars($user['email'] ?? '');
$profile    = htmlspecialchars($user['profile'] ?? '');
$userId     = (int)($user['id'] ?? 0);

// Instituição atual na sessão
$curInst      = getCurrentInstitution();
$instCount    = $userId ? countUserInstitutions($userId) : 0;

// Iniciais para avatar placeholder
$initials = '';
foreach (explode(' ', trim($userName)) as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
    if (strlen($initials) >= 2) break;
}

// Página ativa
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $theme ?>" data-server-theme="<?= $theme ?>" data-user-id="<?= $userId ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Vértice Acadêmico — Sistema de Gestão de Indicadores Discentes">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>Vértice Acadêmico</title>

    <!-- Favicon inline SVG -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <?php if (isset($extraCSS)): foreach ($extraCSS as $css): ?>
    <link rel="stylesheet" href="<?= $css ?>">
    <?php endforeach; endif; ?>
</head>
<body>
<div class="app-wrapper">

<!-- ======== NAVBAR ======== -->
<header class="navbar" role="banner">
    <div class="navbar-inner">

        <!-- Marca -->
        <a href="/dashboard.php" class="navbar-brand" aria-label="Vértice Acadêmico — Início">
            <div class="brand-icon" aria-hidden="true">VA</div>
            <div>
                <span class="brand-name">Vértice Acadêmico</span>
                <span class="brand-sub">Gestão Discente</span>
            </div>
        </a>

        <!-- Menu de Navegação -->
        <nav class="navbar-menu" aria-label="Menu principal">
            <a href="/dashboard.php"
               class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>"
               aria-current="<?= $currentPage === 'dashboard' ? 'page' : 'false' ?>">
                📊 Dashboard
            </a>
            <?php if ($user && $user['profile'] === 'Administrador'): ?>
            <a href="/admin/users.php"
               class="nav-link <?= $currentPage === 'users' ? 'active' : '' ?>">
                👥 Usuários
            </a>
            <a href="/admin/institutions.php"
               class="nav-link <?= $currentPage === 'institutions' ? 'active' : '' ?>">
                🏫 Instituições
            </a>
            <?php endif; ?>
            <?php if ($user && in_array($user['profile'], ['Administrador', 'Coordenador']) && !empty($curInst['id'])): ?>
            <a href="/courses/index.php"
               class="nav-link <?= $currentPage === 'index' && strpos($_SERVER['PHP_SELF'], '/courses/') !== false ? 'active' : '' ?>">
                📚 Cursos
            </a>
            <a href="/subjects/index.php"
               class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/subjects/') !== false ? 'active' : '' ?>">
                📖 Disciplinas
            </a>
            <?php endif; ?>
            <!-- Novos itens de menu serão adicionados aqui conforme o sistema cresce -->
        </nav>

        <!-- Ações (direita) -->
        <div class="navbar-actions">

            <!-- Toggle de Tema -->
            <button class="theme-toggle"
                    data-action="toggleTheme"
                    data-theme-icon
                    title="Alternar tema"
                    aria-label="Alternar tema claro/escuro">
                🌙
            </button>

            <!-- Menu do Usuário -->
            <div class="user-menu" id="userMenu" role="navigation" aria-label="Menu do usuário">
                <button class="user-avatar-btn"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="userDropdown"
                        id="userMenuBtn">
                    <?php if (!empty($user['photo']) && file_exists(__DIR__ . '/../' . $user['photo'])): ?>
                        <img src="/<?= htmlspecialchars($user['photo']) ?>"
                             alt="Foto de <?= $userName ?>"
                             class="user-avatar">
                    <?php else: ?>
                        <div class="user-avatar-placeholder" aria-hidden="true"><?= $initials ?></div>
                    <?php endif; ?>

                    <div class="user-info">
                        <span class="user-name-nav"><?= $userName ?></span>
                        <span class="user-profile-nav"><?= $profile ?></span>
                    </div>
                    <span class="user-chevron" aria-hidden="true">▾</span>
                </button>

                <!-- Dropdown -->
                <div class="dropdown-menu" id="userDropdown" role="menu" aria-labelledby="userMenuBtn">
                    <div class="dropdown-header">
                        <div class="dropdown-user-name"><?= $userName ?></div>
                        <div class="dropdown-user-email"><?= $userEmail ?></div>
                    </div>

                    <a href="/profile.php" class="dropdown-item" role="menuitem">
                        👤 Meu Perfil
                    </a>

                    <!-- Instituição Atual -->
                    <?php if ($instCount > 0): ?>
                    <?php if ($instCount > 1): ?>
                    <a href="/select_institution.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="dropdown-item" role="menuitem"
                       title="Trocar de instituição" style="display:flex;align-items:center;gap:.625rem;">
                        <div style="width:24px;height:24px;border-radius:4px;overflow:hidden;background:var(--bg-surface-2nd);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <?php if (!empty($curInst['photo']) && file_exists(__DIR__ . '/../' . $curInst['photo'])): ?>
                                <img src="/<?= htmlspecialchars($curInst['photo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <span style="font-size:.875rem;">🏫</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;flex-direction:column;min-width:0;max-width:160px;">
                            <span style="font-size:.8125rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($curInst['name'] ?? '') ?>"><?= $curInst['name'] ? htmlspecialchars($curInst['name']) : 'Selecionar Instituição' ?></span>
                            <span style="font-size:.65rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.02em;">Trocar instituição ↗</span>
                        </div>
                    </a>
                    <?php else: ?>
                    <div class="dropdown-item" style="cursor:default;opacity:.8;display:flex;align-items:center;gap:.625rem;">
                        <div style="width:24px;height:24px;border-radius:4px;overflow:hidden;background:var(--bg-surface-2nd);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <?php if (!empty($curInst['photo']) && file_exists(__DIR__ . '/../' . $curInst['photo'])): ?>
                                <img src="/<?= htmlspecialchars($curInst['photo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <span style="font-size:.875rem;">🏫</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;flex-direction:column;min-width:0;max-width:160px;">
                            <span style="font-size:.8125rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($curInst['name'] ?? '') ?>"><?= $curInst['name'] ? htmlspecialchars($curInst['name']) : 'Carregando...' ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($user && $user['profile'] === 'Administrador'): ?>
                    <a href="/settings.php" class="dropdown-item" role="menuitem">
                        ⚙️ Configurações
                    </a>
                    <?php endif; ?>

                    <div class="dropdown-divider"></div>

                    <a href="/logout.php" class="dropdown-item danger" role="menuitem">
                        🚪 Sair
                    </a>
                </div>
            </div><!-- /user-menu -->

        </div><!-- /navbar-actions -->
    </div><!-- /navbar-inner -->
</header>
<!-- ======== /NAVBAR ======== -->

<!-- Conteúdo da página -->
<main class="main-content" id="main-content" role="main">
