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
            <div class="social-filter-group">
                <div class="input-group input-group-sm">
                    <span class="input-icon">🔍</span>
                    <input type="text" 
                           id="aluno-search-input" 
                           class="form-control" 
                           placeholder="Pesquisar aluno..."
                           autocomplete="off">
                </div>
                
                <!-- Autocomplete Results Container -->
                <div id="aluno-search-results" class="search-results-dropdown" style="display: none;"></div>
            </div>

            <!-- Active Filters Container -->
            <div id="active-filters" class="active-filters-container"></div>
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
