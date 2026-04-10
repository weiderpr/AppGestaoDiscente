<?php
/**
 * Vértice Acadêmico — Social Feed Component Shell
 */

require_once __DIR__ . '/../includes/auth.php';
$u = getCurrentUser();
$userName = $u['name'] ?? 'Usuário';
$userFirstName = explode(' ', $userName)[0];
$userPhoto = !empty($u['photo']) ? '/' . $u['photo'] : '/assets/img/avatar-placeholder.png';
?>


<!-- Component Assets -->
<link rel="stylesheet" href="/social/feed.css?v=1.2">
<script src="/social/feed.js?v=1.4" defer></script>
<script src="/assets/js/sentiment_system.js" defer></script>
<script src="/assets/js/student_comments.js?v=2.4" defer></script>

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
        <!-- Área de Criação de Post (Inline) -->
        <div class="social-post-creator-wrapper">
            <header class="social-post-creator" id="inline-post-creator">
                <div class="user-avatar-wrapper">
                    <img src="<?= $userPhoto ?>" 
                         class="user-avatar-round" 
                         alt="<?= $userName ?>"
                         onerror="this.src='/assets/img/avatar-placeholder.png'">
                </div>
                
                <div class="post-input-container">
                    <textarea id="inline-post-textarea" 
                              class="post-input-inline" 
                              placeholder="No que você está pensando, <?= $userFirstName ?>?"
                              autocomplete="off"
                              spellcheck="false"></textarea>
                    
                    <!-- Mention Dropdown (Inline) -->
                    <div id="mention-results" class="search-results-dropdown" style="display: none; width: 100%;"></div>
                </div>
            </header>

            <!-- Inline Actions & Tags (Hidden until focus/content) -->
            <div class="post-creator-actions" id="inline-post-actions">
                <div id="selected-mention-container" class="inline-mention-tag" style="display: none;">
                    <span class="filter-tag">
                        <span id="mention-tag-icon">👤</span>
                        <span id="mention-tag-name"></span>
                        <span class="filter-tag-remove" onclick="window.socialFeed.clearMention()">&times;</span>
                    </span>
                </div>

                <div class="post-creator-buttons">
                    <button type="button" class="btn btn-primary btn-sm" id="btn-publish-post" onclick="window.socialFeed.submitPost()">
                        Publicar
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="window.socialFeed.resetPostArea()">
                        Cancelar
                    </button>
                </div>
            </div>

            <!-- Hidden Meta Inputs -->
            <input type="hidden" id="mention-aluno-id" value="">
            <input type="hidden" id="mention-turma-id" value="">
        </div>

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

<!-- Shared Student Modals -->
<?php require_once __DIR__ . '/../includes/student_comment_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/student_schedule_modal.php'; ?>
