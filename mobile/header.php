<?php
/**
 * Vértice Acadêmico — Mobile Header with Drawer
 */
require_once __DIR__ . '/../includes/auth.php';
$curInst = getCurrentInstitution();
$user = getCurrentUser();

$userName   = htmlspecialchars($user['name'] ?? '');
$userEmail  = htmlspecialchars($user['email'] ?? '');
$profile    = htmlspecialchars($user['profile'] ?? '');

// Iniciais para avatar
$initials = '';
foreach (explode(' ', trim($userName)) as $part) {
    if (!empty($part)) $initials .= strtoupper(substr($part, 0, 1));
    if (strlen($initials) >= 2) break;
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="<?= $user['theme'] ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="<?= \CsrfToken::generate() ?>">
    <title><?= $pageTitle ?? 'Início' ?> — Vértice Mobile</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="/assets/css/variables.css">
    
    <style>
        :root {
            --safe-area-top: env(safe-area-inset-top, 0px);
            --safe-area-bottom: env(safe-area-inset-bottom, 0px);
            --header-height: calc(64px + var(--safe-area-top));
            --drawer-width: 85%;
            --color-primary: #4f46e5;
            --color-primary-light: #eef2ff;
        }

        [data-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-surface: #1e293b;
            --border-color: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
        }

        body {
            background-color: var(--bg-body, #f1f5f9);
            color: var(--text-primary, #0f172a);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding-top: var(--header-height);
            padding-bottom: var(--safe-area-bottom);
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
        }

        /* Header Mobile */
        .mobile-header {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: var(--header-height);
            background: rgba(var(--bg-card-rgb, 255, 255, 255), 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color, #e2e8f0);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--safe-area-top) 1.25rem 0;
            z-index: 2000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        .brand-mobile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .brand-logo {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 0.875rem;
            box-shadow: 0 4px 8px rgba(79,70,229,0.3);
        }

        .brand-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.125rem;
            color: var(--text-primary, #0f172a);
            letter-spacing: -0.02em;
        }

        .hamburger-btn {
            width: 44px;
            height: 44px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            z-index: 2001;
        }

        .hamburger-btn span {
            display: block;
            width: 24px;
            height: 2.5px;
            background: var(--text-primary, #0f172a);
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .hamburger-btn.active span:nth-child(1) { transform: translateY(7.5px) rotate(45deg); }
        .hamburger-btn.active span:nth-child(2) { opacity: 0; }
        .hamburger-btn.active span:nth-child(3) { transform: translateY(-7.5px) rotate(-45deg); }

        /* Drawer (Menu Lateral) */
        .drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(4px);
            z-index: 3000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .drawer-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .drawer {
            position: fixed;
            top: 0; right: -100%;
            width: var(--drawer-width);
            height: 100vh;
            background: var(--bg-surface, #ffffff);
            box-shadow: -10px 0 40px rgba(0,0,0,0.15);
            z-index: 3001;
            transition: right 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            padding-top: var(--safe-area-top);
        }

        .drawer.active { right: 0; }

        .drawer-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .drawer-user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .drawer-avatar {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .drawer-user-details {
            overflow: hidden;
        }

        .drawer-user-name {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 1.0625rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .drawer-user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .drawer-nav {
            padding: 1.5rem 1rem;
            flex: 1;
            overflow-y: auto;
        }

        .drawer-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-radius: 16px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
        }

        .drawer-link:active, .drawer-link.active {
            background: var(--color-primary-light);
            color: var(--color-primary);
        }

        .drawer-link-icon {
            font-size: 1.25rem;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-surface-2nd);
            border-radius: 10px;
        }

        .drawer-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .logout-btn-mobile {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: 16px;
            background: #fef2f2;
            color: #ef4444;
            font-weight: 700;
            text-decoration: none;
            width: 100%;
        }

        /* Content spacing */
        .m-content {
            padding: 1.5rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .m-section-title {
            font-size: 1.25rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            margin-bottom: 1.25rem;
            color: var(--text-primary);
        }

        /* Buttons & Touch targets */
        .m-btn {
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            padding: 0 1.5rem;
            transition: transform 0.1s;
        }

        .m-btn:active { transform: scale(0.97); }
    </style>
</head>
<body>

<header class="mobile-header">
    <a href="/mobile/index.php" class="brand-mobile">
        <div class="brand-logo">VA</div>
        <span class="brand-name">Vértice</span>
    </a>
    
    <button class="hamburger-btn" id="menuToggle" aria-label="Abrir menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
</header>

<div class="drawer-overlay" id="drawerOverlay"></div>

<div class="drawer" id="mobileDrawer">
    <div class="drawer-header">
        <div class="drawer-user-info">
            <?php if (!empty($user['photo']) && file_exists(__DIR__ . '/../' . $user['photo'])): ?>
                <img src="/<?= htmlspecialchars($user['photo']) ?>" class="drawer-avatar" style="object-fit:cover;">
            <?php else: ?>
                <div class="drawer-avatar"><?= $initials ?></div>
            <?php endif; ?>
            <div class="drawer-user-details">
                <div class="drawer-user-name"><?= $userName ?></div>
                <div class="drawer-user-role"><?= $profile ?></div>
            </div>
        </div>
    </div>

    <nav class="drawer-nav">
        <a href="/mobile/index.php" class="drawer-link <?= $currentPage === 'home' ? 'active' : '' ?>">
            <span class="drawer-link-icon">🏠</span>
            <span>Início</span>
        </a>
        <a href="/courses/conselhos.php" class="drawer-link <?= $currentPage === 'conselhos' ? 'active' : '' ?>">
            <span class="drawer-link-icon">⚖️</span>
            <span>Conselhos</span>
        </a>
        <a href="/mobile/courses.php" class="drawer-link <?= $currentPage === 'cursos' ? 'active' : '' ?>">
            <span class="drawer-link-icon">📚</span>
            <span>Cursos Ativos</span>
        </a>
        <a href="/profile.php" class="drawer-link <?= $currentPage === 'perfil' ? 'active' : '' ?>">
            <span class="drawer-link-icon">👤</span>
            <span>Meu Perfil</span>
        </a>
        <a href="/settings.php" class="drawer-link">
            <span class="drawer-link-icon">⚙️</span>
            <span>Configurações</span>
        </a>
    </nav>

    <div class="drawer-footer">
        <a href="/logout.php" class="logout-btn-mobile">
            <span>🚪</span> Sair do Sistema
        </a>
    </div>
</div>

<script>
    const menuToggle = document.getElementById('menuToggle');
    const mobileDrawer = document.getElementById('mobileDrawer');
    const drawerOverlay = document.getElementById('drawerOverlay');

    function toggleMenu() {
        menuToggle.classList.toggle('active');
        mobileDrawer.classList.toggle('active');
        drawerOverlay.classList.toggle('active');
        document.body.style.overflow = mobileDrawer.classList.contains('active') ? 'hidden' : '';
    }

    menuToggle.onclick = toggleMenu;
    drawerOverlay.onclick = toggleMenu;
</script>
