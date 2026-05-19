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
    
    <!-- PWA Settings -->
    <link rel="manifest" href="/mobile/manifest.json">
    <meta name="theme-color" content="#4f46e5">
    
    <!-- iOS support -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Vértice">
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="/assets/css/variables.css">
    
    <style>
        *, ::before, ::after {
            box-sizing: border-box;
        }

        :root {
            --safe-area-top: env(safe-area-inset-top, 0px);
            --safe-area-bottom: env(safe-area-inset-bottom, 0px);
            --header-height: calc(64px + var(--safe-area-top));
            --drawer-width: 85%;
            --color-primary: #4f46e5;
            --color-primary-light: #eef2ff;
            --bg-body: #ebf0f7; /* Slightly darker for better contrast with white cards */
            --bg-card: #ffffff;
            --border-color: #d1d9e6; /* More distinct border */
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            --radius-xl: 24px;
            --gradient-brand: linear-gradient(135deg, #4f46e5, #7c3aed);
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
            background-color: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding-top: var(--header-height);
            padding-bottom: var(--safe-area-bottom);
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
            -webkit-font-smoothing: antialiased;
        }

        /* Reusable Mobile Components */
        .m-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 0.75rem 1rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 0.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .m-card:active {
            transform: scale(0.98);
            box-shadow: var(--shadow-sm);
        }

        .m-search-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 0.625rem 1.125rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.875rem;
            box-shadow: var(--shadow-sm);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .m-search-box:focus-within {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .m-search-input {
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 1.0625rem;
            font-weight: 500;
            width: 100%;
            outline: none;
            font-family: inherit;
        }

        .m-search-box span {
            font-size: 1.25rem;
            opacity: 0.7;
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
            align-items: flex-end;
            justify-content: space-between;
            padding: 0 1.25rem 10px;
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

        /* PWA Install Banner styling */
        .pwa-install-banner {
            position: fixed;
            bottom: calc(1rem + var(--safe-area-bottom));
            left: 1rem;
            right: 1rem;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 1.25rem;
            box-shadow: var(--shadow-lg);
            z-index: 4000;
            display: none;
            flex-direction: column;
            gap: 1rem;
            animation: slideUpPWA 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            max-width: 500px;
            margin: 0 auto;
        }

        .pwa-install-banner.show {
            display: flex;
        }

        @keyframes slideUpPWA {
            from { transform: translateY(100px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .pwa-banner-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .pwa-banner-logo {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: var(--shadow-sm);
        }

        .pwa-banner-title-group {
            flex: 1;
        }

        .pwa-banner-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-primary);
            margin: 0;
            font-family: 'Outfit', sans-serif;
        }

        .pwa-banner-desc {
            font-size: 0.8125rem;
            color: var(--text-muted);
            margin: 2px 0 0 0;
            line-height: 1.4;
        }

        .pwa-banner-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 0.25rem;
        }

        .pwa-banner-actions button {
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 700;
            padding: 0.625rem 1.25rem;
            border: none;
            cursor: pointer;
            transition: transform 0.1s;
            outline: none;
            font-family: inherit;
        }

        .pwa-banner-actions button:active {
            transform: scale(0.97);
        }

        .pwa-btn-install {
            background: var(--gradient-brand);
            color: white;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
        }

        .pwa-btn-dismiss {
            background: transparent;
            color: var(--text-muted);
        }

        .ios-instructions-text {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin: 0;
        }

        .ios-icon-highlight {
            background: var(--color-primary-light);
            color: var(--color-primary);
            padding: 2px 6px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        [data-theme="dark"] .ios-icon-highlight {
            background: rgba(79, 70, 229, 0.2);
            color: #818cf8;
        }
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
        <a href="/mobile/courses.php" class="drawer-link <?= $currentPage === 'cursos' ? 'active' : '' ?>">
            <span class="drawer-link-icon">📚</span>
            <span>Cursos Ativos</span>
        </a>
        <?php if ($profile === 'Professor' || ($user['is_teacher'] ?? 0) == 1): ?>
        <a href="/mobile/segunda_chamada.php" class="drawer-link <?= $currentPage === 'segunda_chamada' ? 'active' : '' ?>">
            <span class="drawer-link-icon">📝</span>
            <span>Segunda Chamada</span>
        </a>
        <?php endif; ?>
        <a href="/profile.php" class="drawer-link <?= $currentPage === 'perfil' ? 'active' : '' ?>">
            <span class="drawer-link-icon">👤</span>
            <span>Meu Perfil</span>
        </a>
    </nav>

    <div class="drawer-footer">
        <a href="/logout.php" class="logout-btn-mobile">
            <span>🚪</span> Sair do Sistema
        </a>
    </div>
</div>

<!-- Floating PWA Install Banner -->
<div id="pwaInstallBanner" class="pwa-install-banner">
    <div class="pwa-banner-header">
        <img src="/assets/images/icon-192.png" alt="Logo Vértice Acadêmico" class="pwa-banner-logo">
        <div class="pwa-banner-title-group">
            <h4 class="pwa-banner-title">Instalar Vértice Acadêmico</h4>
            <div id="pwaAndroidInstructions">
                <p class="pwa-banner-desc">Instale o aplicativo na sua tela de início para acesso rápido, melhor performance e suporte offline.</p>
            </div>
            <div id="pwaIOSInstructions" style="display: none;">
                <p class="ios-instructions-text">
                    Toque no ícone de compartilhamento <span class="ios-icon-highlight"> Compartilhar </span> e selecione <span class="ios-icon-highlight"> Adicionar à Tela de Início </span>.
                </p>
            </div>
        </div>
    </div>
    <div class="pwa-banner-actions">
        <button id="pwaDismissBtn" class="pwa-btn-dismiss">Agora não</button>
        <button id="pwaInstallBtn" class="pwa-btn-install">Instalar</button>
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

    // --- PWA Registration & Auto Install Logic ---
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/mobile/sw.js')
                .then(reg => console.log('PWA: Service Worker registrado com escopo:', reg.scope))
                .catch(err => console.error('PWA: Falha ao registrar Service Worker:', err));
        });
    }

    let deferredPrompt;
    const installBanner = document.getElementById('pwaInstallBanner');
    const installBtn = document.getElementById('pwaInstallBtn');
    const dismissBtn = document.getElementById('pwaDismissBtn');
    const androidInst = document.getElementById('pwaAndroidInstructions');
    const iosInst = document.getElementById('pwaIOSInstructions');

    window.addEventListener('beforeinstallprompt', (e) => {
        // Evita que o prompt padrão apareça imediatamente
        e.preventDefault();
        // Salva o evento para ser disparado posteriormente
        deferredPrompt = e;

        // Verifica se o usuário já dispensou nas últimas 24 horas
        const lastDismiss = localStorage.getItem('pwa_dismissed_time');
        const now = Date.now();
        if (lastDismiss && (now - parseInt(lastDismiss) < 24 * 60 * 60 * 1000)) {
            return;
        }

        // Mostra o banner customizado para Android/Chrome
        showInstallBanner('android');
    });

    function showInstallBanner(platform) {
        if (!installBanner) return;

        if (platform === 'ios') {
            if (androidInst) androidInst.style.display = 'none';
            if (iosInst) iosInst.style.display = 'block';
            if (installBtn) installBtn.style.display = 'none'; // iOS has no prompt programmatically
        } else {
            if (androidInst) androidInst.style.display = 'block';
            if (iosInst) iosInst.style.display = 'none';
            if (installBtn) installBtn.style.display = 'inline-block';
        }

        installBanner.classList.add('show');
    }

    if (installBtn) {
        installBtn.onclick = () => {
            if (!deferredPrompt) return;
            // Oculta o banner
            installBanner.classList.remove('show');
            // Dispara o prompt de instalação nativo
            deferredPrompt.prompt();
            // Analisa a escolha do usuário
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('PWA: Usuário aceitou a instalação.');
                } else {
                    console.log('PWA: Usuário recusou a instalação.');
                }
                deferredPrompt = null;
            });
        };
    }

    if (dismissBtn) {
        dismissBtn.onclick = () => {
            installBanner.classList.remove('show');
            // Salva no localStorage para não perturbar constantemente
            localStorage.setItem('pwa_dismissed_time', Date.now().toString());
        };
    }

    // --- iOS Detection ---
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;

    if (isIOS && !isStandalone) {
        const lastDismiss = localStorage.getItem('pwa_dismissed_time');
        const now = Date.now();
        if (!lastDismiss || (now - parseInt(lastDismiss) > 24 * 60 * 60 * 1000)) {
            // Pequeno delay para melhorar a experiência do usuário
            setTimeout(() => {
                showInstallBanner('ios');
            }, 2000);
        }
    }
</script>
