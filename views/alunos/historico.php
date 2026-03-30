<?php
/**
 * Vértice Acadêmico — Histórico Multidisciplinar do Aluno
 */
$pageTitle = 'Histórico Multidisciplinar — ' . htmlspecialchars($aluno['nome']);
if (!isset($isAjax) || !$isAjax) {
    require_once __DIR__ . '/../../includes/header.php';
}
?>

<style>
/* Estilos Específicos do Feed */
.history-container {
    max-width: 800px;
    margin: 0 auto;
    padding-bottom: 3rem;
}

/* Header do Aluno */
.student-profile-card {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 2rem;
    background: var(--gradient-brand);
    color: white;
    border-radius: var(--radius-xl);
    margin-bottom: 2rem;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
}

.student-profile-card::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    pointer-events: none;
}

.student-avatar-lg {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.3);
    object-fit: cover;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 800;
}

.student-info h1 {
    font-family: 'Outfit', sans-serif;
    font-size: 1.75rem;
    font-weight: 800;
    margin: 0;
    line-height: 1.2;
}

.student-meta {
    display: flex;
    gap: 1rem;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    opacity: 0.9;
}

.student-meta span {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

/* Timeline Feed */
.history-feed {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.feed-item {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 0.825rem 1rem;
    box-shadow: var(--card-shadow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.feed-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--card-shadow-hover);
}

.feed-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.35rem;
}

.author-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.author-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    background: var(--bg-surface-2nd);
    border: 1px solid var(--border-color);
}

.author-details {
    display: flex;
    flex-direction: column;
}

.author-name {
    font-weight: 700;
    font-size: 0.9375rem;
    color: var(--text-primary);
    line-height: 1.2;
}

.author-role {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

/* Badges de Categoria */
.category-badge {
    padding: 0.25rem 0.75rem;
    border-radius: var(--radius-full);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge-aula { background: #dbeafe; color: #1e40af; }
.badge-conselho { background: #f3e8ff; color: #6b21a8; }
.badge-geral { background: #fef3c7; color: #92400e; }
.badge-atendimento { background: #dcfce7; color: #14532d; }

.feed-content {
    font-size: 0.9375rem;
    color: var(--text-secondary);
    line-height: 1.5;
    white-space: pre-wrap;
    padding: 0.25rem 0;
    text-indent: 0;
}

.feed-footer {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.75rem;
}

.empty-state {
    text-align: center;
    padding: 5rem 2rem;
    background: var(--card-bg);
    border-radius: var(--radius-xl);
    border: 2px dashed var(--border-color);
}

.empty-icon {
    font-size: 3.5rem;
    margin-bottom: 1rem;
    display: block;
}

/* Responsividade Mobile */
@media (max-width: 640px) {
    .main-content { padding: 1rem; }
    
    .student-profile-card {
        flex-direction: column;
        text-align: center;
        padding: 1.5rem;
        gap: 1rem;
        border-radius: 0;
        margin-left: -1rem;
        margin-right: -1rem;
    }
    
    .student-meta {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .feed-item {
        border-radius: 0;
        margin-left: -1rem;
        margin-right: -1rem;
    }
}

/* Caixa de Comentário (Estilo Rede Social) */
.post-comment-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 1rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
}

.comment-box {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.comment-input-wrapper textarea {
    width: 100%;
    min-height: 60px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 0.75rem;
    font-family: inherit;
    font-size: 1rem;
    color: var(--text-primary);
    background: var(--bg-surface-2nd);
    resize: none;
    transition: border-color 0.2s;
}

.comment-input-wrapper textarea:focus {
    outline: none;
    border-color: var(--color-primary);
}

.comment-actions {
    display: flex;
    justify-content: flex-end;
}

.btn-post {
    background: var(--color-primary);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius-full);
    font-weight: 700;
    font-size: 0.9375rem;
    cursor: pointer;
    transition: filter 0.2s, transform 0.1s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-post:hover {
    filter: brightness(1.1);
}

.btn-post:active {
    transform: scale(0.98);
}

.btn-post:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
</style>

<div class="history-container fade-in">
    <!-- Header Aluno -->
    <div class="student-profile-card">
        <?php if (!empty($aluno['photo'])): ?>
            <img src="/<?= htmlspecialchars($aluno['photo']) ?>" alt="<?= htmlspecialchars($aluno['nome']) ?>" class="student-avatar-lg">
        <?php else: ?>
            <div class="student-avatar-lg">
                <?= mb_substr($aluno['nome'], 0, 1) ?>
            </div>
        <?php endif; ?>
        
        <div class="student-info">
            <h1><?= htmlspecialchars($aluno['nome']) ?></h1>
            <div class="student-meta">
                <span>🆔 <strong>Matrícula:</strong> <?= htmlspecialchars($aluno['matricula']) ?></span>
                <?php if ($turma): ?>
                    <span>🎓 <strong>Turma:</strong> <?= htmlspecialchars($turma['description']) ?> (<?= $turma['ano'] ?>)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Nova área de Postagem (Estilo Rede Social) -->
    <?php if ($turma): ?>
    <div class="post-comment-container fade-in">
        <form id="historyCommentForm" class="comment-box" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_comment">
            <input type="hidden" name="aluno_id" value="<?= $aluno['id'] ?>">
            <input type="hidden" name="turma_id" value="<?= $turma['id'] ?>">
            
            <div class="comment-input-wrapper">
                <textarea 
                    name="conteudo" 
                    placeholder="Escreva uma observação pedagógica para <?= explode(' ', $aluno['nome'])[0] ?>..."
                ></textarea>
            </div>
            
            <div class="comment-actions">
                <button type="submit" class="btn-post">
                    <span>🚀</span> Publicar Registro
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Feed de Histórico -->
    <div class="history-feed">
        
        <?php if (empty($history)): ?>
            <div class="empty-state">
                <span class="empty-icon">📭</span>
                <p style="font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">Nenhum registro encontrado</p>
                <p style="color: var(--text-muted);">Este aluno ainda não possui observações, encaminhamentos ou atendimentos registrados.</p>
            </div>
        <?php else: ?>
            <?php foreach ($history as $item): 
                $badgeClass = 'badge-' . strtolower(str_replace(' (Geral)', '', $item['categoria']));
                if ($item['categoria'] === 'Geral') $badgeClass = 'badge-geral';
                if ($item['categoria'] === 'Aula') $badgeClass = 'badge-aula';
                if ($item['categoria'] === 'Conselho') $badgeClass = 'badge-conselho';
                if ($item['categoria'] === 'Atendimento') $badgeClass = 'badge-atendimento';
            ?>
                <div class="feed-item fade-in">
                    <div class="feed-header">
                        <div class="author-info">
                            <?php if (!empty($item['autor_foto'])): ?>
                                <img src="/<?= htmlspecialchars($item['autor_foto']) ?>" class="author-avatar" alt="<?= htmlspecialchars($item['autor_nome']) ?>">
                            <?php else: ?>
                                <div class="author-avatar" style="display:flex;align-items:center;justify-content:center;background:var(--bg-surface-2nd);font-weight:700;color:var(--text-muted);font-size:0.75rem;">
                                    <?= mb_substr($item['autor_nome'], 0, 1) ?>
                                </div>
                            <?php endif; ?>
                            <div class="author-details">
                                <span class="author-name"><?= htmlspecialchars($item['autor_nome']) ?></span>
                                <span class="author-role"><?= htmlspecialchars($item['autor_perfil']) ?></span>
                            </div>
                        </div>
                        <span class="category-badge <?= $badgeClass ?>">
                            <?= htmlspecialchars($item['categoria']) ?>
                        </span>
                    </div>
                    
                    <div class="feed-content"><?= nl2br(htmlspecialchars(trim(html_entity_decode(strip_tags($item['texto']), ENT_QUOTES | ENT_HTML5, 'UTF-8')))) ?></div>
                    
                    <div class="feed-footer">
                        📅 <span>Registrado em: <?= date('d/m/Y \à\s H:i', strtotime($item['data_registro'])) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const initForm = () => {
        const form = document.getElementById('historyCommentForm');
        if (!form || form.dataset.init === 'true') return;
        
        form.dataset.init = 'true';
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const txt = form.querySelector('textarea');
            
            if(!txt.value.trim()) return;

            if (typeof showLoading === 'function') showLoading('Publicando...');
            btn.disabled = true;
            const originalBtnContent = btn.innerHTML;
            btn.innerHTML = '<span>⏳</span> Publicando...';

            const formData = new FormData(form);
            
            try {
                const response = await fetch('/api/comments.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const result = await response.json();
                
                if(result.success) {
                    if (typeof Toast !== 'undefined') Toast.show('Registro publicado!', 'success');
                    
                    // If in modal, we might want to refresh the modal content
                    if (window.openHistoryModal && typeof currentHistoryAlunoId !== 'undefined') {
                        openHistoryModal(currentHistoryAlunoId, true); // true = silent/refresh
                    } else {
                        location.reload(); 
                    }
                } else {
                    if (typeof Toast !== 'undefined') Toast.show(result.error || 'Erro ao publicar', 'danger');
                    else alert(result.error || 'Erro ao publicar comentário');
                }
            } catch (error) {
                console.error(error);
                if (typeof Toast !== 'undefined') Toast.show('Erro de conexão', 'danger');
            } finally {
                if (typeof hideLoading === 'function') hideLoading();
                btn.disabled = false;
                btn.innerHTML = originalBtnContent;
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initForm);
    } else {
        initForm();
    }
})();
</script>

<?php 
if (!isset($isAjax) || !$isAjax) {
    require_once __DIR__ . '/../../includes/footer.php'; 
}
?>
