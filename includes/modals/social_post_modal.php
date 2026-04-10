<!-- Modal de Criação de Post Social -->
<div id="modalSocialPost" class="modal-backdrop">
    <div class="modal" style="max-width: 520px;">
        <div class="modal-header">
            <h3 class="modal-title">Criar publicação</h3>
            <button class="modal-close" onclick="closeModal('modalSocialPost')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- User Info -->
            <div class="post-modal-user" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <img src="<?= $userPhoto ?>" class="user-avatar-round" style="width: 40px; height: 40px;" alt="<?= $userName ?>">
                <div style="display: flex; flex-direction: column;">
                    <span style="font-weight: 700; color: var(--text-primary); font-size: 0.9375rem;"><?= $userName ?></span>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Público</span>
                </div>
            </div>

            <!-- Textarea Area -->
            <div class="post-textarea-wrapper" style="position: relative;">
                <textarea id="post-content-textarea" 
                          class="form-control" 
                          placeholder="No que você está pensando, <?= $userFirstName ?>?"
                          style="min-height: 150px; border: none; resize: none; padding: 0; font-size: 1.125rem; box-shadow: none; background: transparent;"></textarea>
                
                <!-- Mention Dropdown -->
                <div id="mention-results" class="search-results-dropdown" style="display: none; width: 100%; position: absolute; bottom: 0; left: 0; transform: translateY(100%); z-index: 100;"></div>
            </div>

            <!-- Selected Mention Tag (Optional visibility) -->
            <div id="selected-mention-container" style="margin-top: 1rem; display: none;">
                <span class="filter-tag" id="mention-tag" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                    <span id="mention-tag-icon">👤</span>
                    <span id="mention-tag-name"></span>
                    <span class="filter-tag-remove" onclick="window.socialFeed.clearMention()">&times;</span>
                </span>
            </div>

            <!-- Hidden Inputs -->
            <input type="hidden" id="mention-aluno-id" value="">
            <input type="hidden" id="mention-turma-id" value="">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary w-100" id="btn-publish-post" onclick="window.socialFeed.submitPost()">Publicar</button>
        </div>
    </div>
</div>

<style>
.post-textarea-wrapper textarea:focus {
    outline: none;
}
.post-modal-user .user-avatar-round {
    border: 1px solid var(--border-color);
}
</style>
