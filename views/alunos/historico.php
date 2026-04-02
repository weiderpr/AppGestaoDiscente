<?php
/**
 * Vértice Acadêmico — Histórico Multidisciplinar do Aluno
 * UI Sincronizada com Versão Mobile (Hierarquia e Design Premium)
 */
require_once __DIR__ . '/../../includes/auth.php';

$user = getCurrentUser();
$pageTitle = 'Histórico Multidisciplinar — ' . htmlspecialchars($aluno['nome']);

if (!isset($isAjax) || !$isAjax) {
    require_once __DIR__ . '/../../includes/header.php';
}

/**
 * Organiza o histórico em árvore para exibir aninhamento (Encaminhamento -> Atendimento -> Comentário)
 */
function buildHistoryTree(array $flatItems): array {
    $itemMap = [];
    $tree = [];

    // Primeiro mapeia todos por unique_id
    foreach ($flatItems as $item) {
        $item['children'] = [];
        $item['is_archived_inherited'] = isset($item['is_archived']) && $item['is_archived'] == 1;
        $itemMap[$item['unique_id']] = $item;
    }

    // Depois vincula aos pais e propagada arquivo para filhos
    foreach ($itemMap as $id => &$item) {
        if ($item['parent_unique_id'] && isset($itemMap[$item['parent_unique_id']])) {
            $parent = $itemMap[$item['parent_unique_id']];
            $itemMap[$item['parent_unique_id']]['children'][] = &$item;
            
            // Herda arquivo do pai
            if (isset($parent['is_archived_inherited']) && $parent['is_archived_inherited']) {
                $item['is_archived_inherited'] = true;
            }
            if (isset($parent['is_archived']) && $parent['is_archived'] == 1) {
                $item['is_archived_inherited'] = true;
            }
        } else {
            $tree[] = &$item;
        }
    }
    return $tree;
}

function safeHtml($html) {
    $allowedTags = '<b><strong><i><em><u><s><p><br><ul><ol><li><a><code><blockquote><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($html, $allowedTags);
    $text = preg_replace('/([a-záàâãéèêíìîíòôõúùûç])([A-ZÁÀÂÃÉÈÊÍÌÎÍÒÔÕÚÙÛ])/', '$1 $2', $text);
    return nl2br($text);
}

$historyTree = buildHistoryTree($history);
?>

<style>
    /* Design Sincronizado — Estilos Mobile adaptados para Desktop Modal */
    .m-content-container {
        padding: 1rem;
        max-width: 800px;
        margin: 0 auto;
        animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes slideInHierarchy {
        from { opacity: 0; transform: translateX(-10px) scale(0.95); }
        to { opacity: 1; transform: translateX(0) scale(1); }
    }

    /* Student Hero Card */
    .m-history-hero {
        background: var(--gradient-brand);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: var(--radius-xl);
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .m-history-hero::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
        pointer-events: none;
    }

    .m-history-avatar {
        width: 80px;
        height: 80px;
        border-radius: 20px;
        object-fit: cover;
        border: 3px solid rgba(255,255,255,0.3);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .m-history-avatar-placeholder {
        width: 80px;
        height: 80px;
        border-radius: 20px;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 1.75rem;
        border: 3px solid rgba(255,255,255,0.3);
    }

    .m-history-student-info h1 {
        font-family: 'Outfit', sans-serif;
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0;
        line-height: 1.2;
    }

    .m-history-student-meta {
        font-size: 0.8125rem;
        opacity: 0.9;
        margin-top: 0.375rem;
        font-weight: 600;
        display: flex;
        gap: 1rem;
    }

    /* Post Comment Area */
    .m-new-comment-container {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        padding: 1.25rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-sm);
    }

    .m-comment-input {
        width: 100%;
        min-height: 80px;
        border: 1px solid var(--border-color);
        background: var(--bg-surface-2nd);
        border-radius: var(--radius-md);
        padding: 1rem;
        font-family: inherit;
        font-size: 0.9375rem;
        color: var(--text-primary);
        resize: none;
        outline: none;
        margin-bottom: 1rem;
        transition: border-color 0.2s;
    }

    .m-comment-input:focus {
        border-color: var(--color-primary);
        background: var(--bg-surface);
    }

    /* History Tree & Connectors */
    .m-history-tree {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .m-history-branch {
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .m-history-children {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        margin-left: 1.5rem;
        padding-left: 1.25rem;
        position: relative;
        margin-top: 0.5rem;
    }

    .m-history-children::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(to bottom, #6366f1 0%, #a5b4fc 50%, #c4b5fd 100%);
    }

    /* Cards */
    .m-history-item {
        position: relative;
        border-radius: 12px;
        padding: 1.25rem;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm);
    }

    .m-history-tree > .m-history-branch > .m-history-item {
        background: linear-gradient(135deg, var(--bg-surface-2nd), var(--bg-surface));
        border-left: 4px solid #6366f1;
    }

    .m-history-children .m-history-item {
        background: var(--bg-surface);
        border-left: 4px solid #94a3b8;
    }

    .m-history-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .m-history-author {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .m-author-img { width: 36px; height: 36px; border-radius: 10px; object-fit: cover; }
    .m-author-placeholder { 
        width: 36px; height: 36px; border-radius: 10px; 
        background: var(--bg-surface-2nd); display: flex; 
        align-items: center; justify-content: center; 
        font-weight: 700; color: var(--text-muted);
    }

    .m-author-name { font-size: 0.9375rem; font-weight: 700; color: var(--text-primary); }
    .m-author-role { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }

    .m-category-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.6875rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .m-badge-aula { background: #dbeafe; color: #1e40af; }
    .m-badge-conselho { background: #f3e8ff; color: #6b21a8; }
    .m-badge-geral { background: #fef3c7; color: #92400e; }
    .m-badge-atendimento { background: #dcfce7; color: #14532d; }

    .m-history-body {
        font-size: 0.9375rem;
        color: var(--text-secondary);
        line-height: 1.6;
        padding-left: 0.125rem;
    }

    .m-history-footer {
        margin-top: 1rem;
        padding-top: 0.75rem;
        border-top: 1px dashed var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    /* Archived States */
    .m-history-item[data-is-archived="1"] {
        opacity: 0.5;
        filter: grayscale(40%);
        background: #f1f5f9 !important;
    }

    .m-btn-delete-small {
        background: transparent; border: none; color: #ef4444; 
        font-size: 0.75rem; font-weight: 700; cursor: pointer;
        padding: 4px 8px; border-radius: 4px;
    }
    .m-btn-delete-small:hover { background: rgba(239, 68, 68, 0.1); }

    .mention { background: #e0e7ff; color: #3730a3; padding: 0.125rem 0.375rem; border-radius: 4px; font-weight: 600; font-size: 0.9em; }

    @media (max-width: 640px) {
        .m-history-hero { flex-direction: column; text-align: center; border-radius: 0; margin: -1rem -1rem 1rem -1rem; }
        .m-history-student-meta { justify-content: center; }
    }
</style>

<div class="m-content-container">
    
    <!-- Student Hero Card -->
    <div class="m-history-hero">
        <?php if (!empty($aluno['photo'])): ?>
            <img src="/<?= htmlspecialchars($aluno['photo']) ?>" class="m-history-avatar" alt="">
        <?php else: 
            $initials = mb_substr($aluno['nome'], 0, 1);
        ?>
            <div class="m-history-avatar-placeholder"><?= $initials ?></div>
        <?php endif; ?>
        
        <div class="m-history-student-info">
            <h1><?= htmlspecialchars($aluno['nome']) ?></h1>
            <div class="m-history-student-meta">
                <span>🆔 #<?= htmlspecialchars($aluno['matricula']) ?></span>
                <?php if ($turma): ?>
                    <span>🎓 <?= htmlspecialchars($turma['description']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- New Comment Box -->
    <?php if (hasDbPermission('students.comments', false) && $turma): ?>
    <div class="m-new-comment-container fade-in">
        <form id="historyCommentForm">
            <?= csrf_field() ?>
            <input type="hidden" name="aluno_id" value="<?= $aluno['id'] ?>">
            <input type="hidden" name="turma_id" value="<?= $turma['id'] ?>">
            <input type="hidden" name="action" value="save_comment">
            
            <textarea name="conteudo" class="m-comment-input" placeholder="O que aconteceu hoje com este aluno?"></textarea>
            
            <div style="display:flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" style="border-radius:var(--radius-full); padding:0.625rem 1.5rem;">
                    🚀 Publicar Registro
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- History Tree -->
    <div class="m-timeline-container">
        <?php if (empty($historyTree)): ?>
            <div style="text-align:center; padding:4rem 2rem; color:var(--text-muted); background:var(--bg-card); border-radius:var(--radius-xl); border:1px dashed var(--border-color);">
                <div style="font-size:3rem; margin-bottom:1rem;">📭</div>
                <p>Nenhum registro encontrado no histórico deste aluno.</p>
            </div>
        <?php else: ?>
            <div class="m-history-tree">
            <?php 
            function renderTimelineItem($item, $level = 0) {
                global $user;
                $badgeClass = 'm-badge-geral';
                $icon = '📢';
                $badgeText = $item['categoria'];
                
                if ($item['categoria'] === 'Aula') { $badgeClass = 'm-badge-aula'; $icon = '📝'; }
                if ($item['categoria'] === 'Conselho') { $badgeClass = 'm-badge-conselho'; $icon = '🤝'; }
                if ($item['categoria'] === 'Atendimento') { 
                    $atendStatus = $item['atendimento_status'] ?? 'Aberto';
                    $badgeClass = 'm-badge-status-' . strtolower(str_replace(' ', '-', $atendStatus));
                    $icon = '📋';
                    $badgeText = 'Atendimento: ' . $atendStatus;
                }
                
                $isAdmin = ($user['profile'] === 'Administrador');
                $isAuthor = ($item['autor_id'] == $user['id']);
                $canDelete = ($item['categoria'] === 'Aula' && ($isAdmin || $isAuthor));
                
                $levelClass = $level > 0 ? 'm-level-' . $level : '';
                $isArchived = (isset($item['is_archived']) && $item['is_archived'] == 1) || 
                              (isset($item['is_archived_inherited']) && $item['is_archived_inherited']) ? '1' : '0';
                ?>
                <div class="m-history-branch">
                    <div class="m-history-item <?= $levelClass ?>" data-history-id="<?= $item['id'] ?>" data-categoria="<?= $item['categoria'] ?>" data-is-archived="<?= $isArchived ?>">
                        <div class="m-history-header">
                            <div class="m-history-author">
                                <?php if (!empty($item['autor_foto'])): ?>
                                    <img src="/<?= htmlspecialchars($item['autor_foto']) ?>" class="m-author-img" alt="">
                                <?php else: ?>
                                    <div class="m-author-placeholder"><?= mb_substr($item['autor_nome'] ?? '?', 0, 1) ?></div>
                                <?php endif; ?>
                                <div class="m-author-details">
                                    <span class="m-author-name"><?= htmlspecialchars($item['autor_nome'] ?? 'Sistema') ?></span>
                                    <span class="m-author-role"><?= htmlspecialchars($item['autor_perfil'] ?? 'Automático') ?></span>
                                </div>
                            </div>
                            <span class="m-category-badge <?= $badgeClass ?>">
                                <?= $icon ?> <?= htmlspecialchars($badgeText) ?>
                            </span>
                        </div>

                        <div class="m-history-body"><?= safeHtml(trim($item['texto'] ?? '')) ?></div>

                        <div class="m-history-footer">
                            <span>📅 <?= date('d/m/Y \à\s H:i', strtotime($item['data_registro'])) ?></span>
                            <?php if ($canDelete): ?>
                                <button class="m-btn-delete-small" onclick="historyDeleteComment(<?= $item['id'] ?>)">
                                    🗑️ Excluir
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($item['children'])): ?>
                        <div class="m-history-children">
                            <?php foreach ($item['children'] as $child): ?>
                                <?php renderTimelineItem($child, $level + 1); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php 
            }

            foreach ($historyTree as $item) {
                renderTimelineItem($item);
            }
            ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const initHistory = () => {
        // Post behavior
        const form = document.getElementById('historyCommentForm');
        if (form && !form.dataset.init) {
            form.dataset.init = 'true';
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = form.querySelector('button[type="submit"]');
                const txt = form.querySelector('textarea');
                if(!txt.value.trim()) return;

                if (typeof showLoading === 'function') showLoading('Publicando...');
                btn.disabled = true;

                try {
                    const res = await fetch('/api/comments.php', {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();
                    if(data.success) {
                        if (typeof Toast !== 'undefined') Toast.show('Registro publicado!', 'success');
                        // Refresh modal content if available
                        if (window.openHistoryModal && typeof currentHistoryAlunoId !== 'undefined') {
                            openHistoryModal(currentHistoryAlunoId, true); 
                        } else {
                            location.reload();
                        }
                    } else {
                        if (typeof Toast !== 'undefined') Toast.show(data.error || 'Erro ao publicar', 'danger');
                    }
                } catch(err) {
                    if (typeof Toast !== 'undefined') Toast.show('Erro de conexão', 'danger');
                } finally {
                    if (typeof hideLoading === 'function') hideLoading();
                    btn.disabled = false;
                }
            });
        }

        // Mention highlighting
        document.querySelectorAll('.m-history-body').forEach(el => {
            el.innerHTML = el.innerHTML.replace(/(@[a-zA-ZÀ-ÿ0-9_]+)/g, '<span class="mention">$1</span>');
        });
    };

    window.historyDeleteComment = async (id) => {
        if(!confirm('Deseja excluir permanentemente este registro?')) return;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const fd = new FormData();
        fd.append('action', 'delete_comment');
        fd.append('comment_id', id);
        fd.append('csrf_token', csrfToken);

        try {
            const res = await fetch('/api/comments.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if(data.success) {
                if (typeof Toast !== 'undefined') Toast.show('Registro removido.', 'success');
                const item = document.querySelector(`[data-history-id="${id}"]`);
                if (item) {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-10px)';
                    setTimeout(() => {
                        if (window.openHistoryModal && typeof currentHistoryAlunoId !== 'undefined') {
                            openHistoryModal(currentHistoryAlunoId, true);
                        } else {
                            location.reload();
                        }
                    }, 300);
                }
            } else {
                if (typeof Toast !== 'undefined') Toast.show(data.error || 'Erro ao excluir', 'danger');
            }
        } catch(err) {
            if (typeof Toast !== 'undefined') Toast.show('Erro ao conectar', 'danger');
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHistory);
    } else {
        initHistory();
    }
})();
</script>

<?php 
if (!isset($isAjax) || !$isAjax) {
    require_once __DIR__ . '/../../includes/footer.php'; 
}
?>
