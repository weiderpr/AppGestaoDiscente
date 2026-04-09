<?php
/**
 * Vértice Acadêmico — Social Feed Component Shell
 */
?>

<!-- Component Assets -->
<link rel="stylesheet" href="/social/feed.css?v=1.0">
<script src="/social/feed.js?v=1.0" defer></script>

<div class="social-feed-layout">
    <!-- Sidebar Esquerda (Resizable) -->
    <aside class="social-sidebar">
        <div class="social-sidebar-content">
            <span class="sidebar-title">Navegação</span>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem;">
                <div style="padding: 0.75rem 1rem; background: var(--color-primary-light); color: var(--color-primary); border-radius: var(--radius-md); font-weight: 700; cursor: pointer;">
                    🏠 Feed Geral
                </div>
                <!-- Reservado para filtros futuros -->
                <div style="padding: 0.75rem 1rem; color: var(--text-muted); cursor: not-allowed; font-size: 0.875rem;">
                    🎯 Meus Alunos (Em breve)
                </div>
                <div style="padding: 0.75rem 1rem; color: var(--text-muted); cursor: not-allowed; font-size: 0.875rem;">
                    📅 Filtro por Data (Em breve)
                </div>
            </div>
        </div>
        <div class="social-resizer"></div>
    </aside>

    <!-- Feed Central area -->
    <main class="social-feed-container">
        <!-- Header com filtros/ações -->
        <header class="social-feed-header">
            <div class="social-feed-title">
                <span>📱 Feed de Acompanhamento</span>
            </div>
            
            <div class="social-feed-actions">
                <button class="btn btn-secondary btn-sm" onclick="window.socialFeed.loadFeed()">
                    🔄 Atualizar
                </button>
            </div>
        </header>

        <!-- Listagem de Cards -->
        <div class="social-feed-list">
            <!-- Renderizado via JS -->
        </div>

        <!-- Sentinel for Infinite Scroll -->
        <div id="social-feed-sentinel" style="height: 20px; width: 100%;"></div>

        <!-- Footer Loading State -->
        <div id="social-feed-footer-loading" style="display: none; padding: 2rem; text-align: center; color: var(--text-muted);">
            <div class="spinner" style="display: inline-block; margin-right: 0.5rem; animation: spin 1s linear infinite;">⌛</div>
            Buscando atividades anteriores...
        </div>
    </main>
</div>
