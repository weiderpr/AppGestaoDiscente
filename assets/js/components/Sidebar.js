/**
 * Vértice Acadêmico — Settings Sidebar Component
 * Controla:
 *  1. Toggle expandido/recolhido da sidebar (persistido em localStorage)
 *  2. Toggle de submenus ao clicar no item pai
 */
(function () {
    'use strict';

    const STORAGE_KEY = 'settingsSidebarCollapsed';
    const SIDEBAR_ID  = 'settingsSidebar';
    const TOGGLE_ID   = 'sidebarToggleBtn';

    const ICON_EXPANDED  = '◀';
    const ICON_COLLAPSED = '▶';

    function getSidebar()   { return document.getElementById(SIDEBAR_ID); }
    function getToggleBtn() { return document.getElementById(TOGGLE_ID); }

    /** Aplica estado collapsed/expanded e atualiza ícone */
    function applyState(sidebar, collapsed) {
        const btn = getToggleBtn();
        if (collapsed) {
            sidebar.classList.add('collapsed');
            if (btn) btn.textContent = ICON_COLLAPSED;
            sidebar.setAttribute('aria-expanded', 'false');
        } else {
            sidebar.classList.remove('collapsed');
            if (btn) btn.textContent = ICON_EXPANDED;
            sidebar.setAttribute('aria-expanded', 'true');
        }
    }

    /** Persiste e aplica novo estado da sidebar */
    function toggleSidebar() {
        const sidebar = getSidebar();
        if (!sidebar) return;
        const newState = !sidebar.classList.contains('collapsed');
        localStorage.setItem(STORAGE_KEY, newState ? '1' : '0');
        applyState(sidebar, newState);
    }

    /**
     * Inicializa os botões de submenu.
     * Cada botão com [data-submenu] abre/fecha o submenu correspondente.
     * O submenu ativo (com .open já definido pelo PHP) permanece aberto.
     */
    function initSubmenus() {
        const triggers = document.querySelectorAll('[data-submenu]');
        triggers.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const targetId = btn.getAttribute('data-submenu');
                const submenu  = document.getElementById(targetId);
                if (!submenu) return;

                const isOpen = submenu.classList.contains('open');
                // Fechar todos os outros submenus abertos
                document.querySelectorAll('.sidebar-submenu.open').forEach(function (el) {
                    if (el !== submenu) {
                        el.classList.remove('open');
                        const parentBtn = document.querySelector('[data-submenu="' + el.id + '"]');
                        if (parentBtn) parentBtn.setAttribute('aria-expanded', 'false');
                    }
                });

                // Abrir ou fechar o clicado
                submenu.classList.toggle('open', !isOpen);
                btn.setAttribute('aria-expanded', String(!isOpen));
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = getSidebar();
        if (!sidebar) return;

        // 1. Restaurar estado persistido sem flash
        const savedCollapsed = localStorage.getItem(STORAGE_KEY) === '1';
        applyState(sidebar, savedCollapsed);

        // 2. Toggle button
        const btn = getToggleBtn();
        if (btn) btn.addEventListener('click', toggleSidebar);

        // 3. Submenus
        initSubmenus();
    });

})();
