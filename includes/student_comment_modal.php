<?php
/**
 * Shared Component: Student Comment Modal with Analysis Tabs
 */
if (!isset($canComment)) {
    $user = getCurrentUser();
    $allowed = ['Administrador', 'Coordenador', 'Professor', 'Pedagogo', 'Assistente Social', 'Psicólogo'];
    $canComment = $user && in_array($user['profile'], $allowed);
}

if ($canComment): 
?>

<style>
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
/* Base Modal Styles */
.modal-backdrop { 
    position:fixed; inset:0; z-index:8000; background:rgba(0,0,0,.5); 
    display:flex; align-items:center; justify-content:center; 
    opacity:0; visibility:hidden; transition:all .25s ease;
}
.modal-backdrop.show { opacity:1; visibility:visible; display:flex !important; }
.modal { 
    background:var(--bg-surface); border-radius:var(--radius-lg); 
    box-shadow:var(--shadow-xl); width:95%; max-width:600px; 
    max-height:90vh; display:flex; flex-direction:column; 
    transform:translateY(20px) scale(0.95); transition:all .25s ease; 
}
.modal-backdrop.show .modal { transform:translateY(0) scale(1); }
.modal-header { padding:1.25rem 1.5rem; border-bottom:1px solid var(--border-color); display:flex; align-items:center; justify-content:space-between; }
.modal-close { background:none; border:none; font-size:1.25rem; color:var(--text-muted); cursor:pointer; padding:.25rem; border-radius:var(--radius-sm); transition:all .2s; }
.modal-close:hover { background:var(--bg-hover); color:var(--text-primary); }
.modal-body { padding:1.5rem; overflow-y:auto; flex:1; }
.modal-footer { padding:1.25rem 1.5rem; border-top:1px solid var(--border-color); display:flex; justify-content:flex-end; gap:.75rem; }

.comment-tabs-card { overflow:hidden; }
.comment-tabs-header { padding:0.75rem 1.25rem; background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color); display:flex; align-items:center; gap:0.5rem; }
.comment-tabs-label { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted); }
.comment-tabs-container { display:flex; overflow-x:auto; background:var(--bg-surface); border-top:1px solid var(--border-color); border-bottom:1px solid var(--border-color); }
.comment-tab-btn {
    display:flex; align-items:center; gap:0.625rem; padding:1rem 1.5rem;
    color:var(--text-secondary); font-weight:600; text-decoration:none;
    border-bottom:3px solid transparent; transition:all var(--transition-fast);
    white-space:nowrap; font-size:0.875rem; border:none; background:none;
    cursor:pointer;
}
.comment-tab-btn:hover { background:var(--bg-hover); color:var(--color-primary); }
.comment-tab-btn.active { color:var(--color-primary); border-bottom-color:var(--color-primary); background:var(--color-primary-light); }
.comment-tab-icon { font-size:1.1rem; opacity:0.7; }
.comment-tab-btn.active .comment-tab-icon { opacity:1; }
.comment-tab-content { height:420px; overflow-y:auto; display:flex; flex-direction:column; }
.comment-tab-content > div { flex:1; }
#wordcloud_container canvas { border-radius:var(--radius-md); background:var(--bg-surface-2nd); }
#wordcloud_canvas { max-width:100%; height:auto; }
</style>

<div class="modal-backdrop" id="commentModal" role="dialog" style="display:none;">
    <div class="modal" style="max-width:720px;">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div id="comment_aluno_photo" style="width:40px;height:40px;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1rem;"></div>
                <div>
                    <div id="comment_aluno_name" style="font-size:1rem;font-weight:700;color:var(--text-primary);"></div>
                    <div style="font-size:.75rem;color:var(--text-muted);">Análise e Comentários</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('commentModal')">✕</button>
        </div>
        
        <div class="comment-tabs-card">
            <div class="comment-tabs-container">
                <button class="comment-tab-btn active" data-tab="comments" onclick="switchCommentTab('comments')">
                    <span class="comment-tab-icon">💬</span>
                    <span>Comentários</span>
                </button>
                <button class="comment-tab-btn" data-tab="wordcloud" onclick="switchCommentTab('wordcloud')">
                    <span class="comment-tab-icon">☁️</span>
                    <span>Nuvem de Palavras</span>
                </button>
                <button class="comment-tab-btn" data-tab="summary" onclick="switchCommentTab('summary')">
                    <span class="comment-tab-icon">📊</span>
                    <span>Resumo v1.1</span>
                </button>
                <button class="comment-tab-btn" data-tab="trend" onclick="switchCommentTab('trend')">
                    <span class="comment-tab-icon">📈</span>
                    <span>Tendência</span>
                </button>
            </div>
        </div>
        
        <div id="commentTabContent" class="modal-body" style="padding:1.25rem 1.5rem;display:flex;flex-direction:column;">
            <!-- Tab: Comments -->
            <div id="tab-comments" class="comment-tab-content">
                <div style="flex:1;overflow-y:auto;">
                    <form id="commentForm" onsubmit="saveComment(event); return false;" novalidate>
                        <input type="hidden" name="aluno_id" id="comment_aluno_id">
                        
                        <div class="form-group" style="margin-bottom:1rem;">
                            <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
                                <span>Novo Comentário</span>
                                <div style="display:flex;gap:.25rem;background:var(--bg-surface-2nd);padding:.25rem;border-radius:var(--radius-sm);border:1px solid var(--border-color);">
                                    <button type="button" class="action-btn" style="width:28px;height:28px;" onclick="formatText('bold')" title="Negrito"><b>B</b></button>
                                    <button type="button" class="action-btn" style="width:28px;height:28px;" onclick="formatText('italic')" title="Itálico"><i>I</i></button>
                                    <button type="button" class="action-btn" style="width:28px;height:28px;" onclick="formatText('insertUnorderedList')" title="Lista">📋</button>
                                </div>
                            </label>
                            <div id="comment_text" class="form-control" contenteditable="true" style="min-height:80px;max-height:120px;overflow-y:auto;background:var(--bg-surface);padding:.75rem;" placeholder="Digite seu comentário sobre este aluno..."></div>
                            <div id="comment_preview"></div>
                            <div style="text-align:right;margin-top:.5rem;">
                                <button type="submit" class="btn btn-primary btn-sm">💾 Publicar</button>
                            </div>
                        </div>
                    </form>

                    <div id="comment_history_meu"></div>
                    <div id="comment_history_outros" style="margin-top:0.5rem;"></div>
                </div>
            </div>
            
            <!-- Tab: Word Cloud -->
            <div id="tab-wordcloud" class="comment-tab-content" style="display:none;">
                <div id="wordcloud_container" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <div id="wordcloud_loading" style="text-align:center;color:var(--text-muted);">
                        <div style="font-size:2rem;margin-bottom:0.5rem;">☁️</div>
                        <div style="font-size:.875rem;">Carregando nuvem de palavras...</div>
                    </div>
                    <canvas id="wordcloud_canvas" width="660" height="280" style="display:none;"></canvas>
                    <div id="wordcloud_empty" style="display:none;text-align:center;color:var(--text-muted);">
                        <div style="font-size:3rem;margin-bottom:0.75rem;">📝</div>
                        <div style="font-size:.9375rem;font-weight:600;margin-bottom:0.25rem;">Nenhum comentário registrado</div>
                        <div style="font-size:.8125rem;">Adicione comentários para gerar a nuvem de palavras.</div>
                    </div>
                </div>
                <div id="wordcloud_info" style="padding:0.5rem;background:var(--bg-surface-2nd);border-radius:var(--radius-sm);font-size:.75rem;color:var(--text-muted);text-align:center;display:none;flex-shrink:0;">
                    <span id="wordcloud_word_count">0</span> palavras analisadas de <span id="wordcloud_comment_count">0</span> comentário(s)
                </div>
            </div>
            
            <!-- Tab: Summary -->
            <div id="tab-summary" class="comment-tab-content" style="display:none;">
                <div id="summary_container" style="flex:1;overflow-y:auto;">
                    <div id="summary_loading" style="text-align:center;color:var(--text-muted);padding:2rem;">
                        <div style="font-size:2rem;margin-bottom:0.5rem;">📊</div>
                        <div style="font-size:.875rem;">Gerando resumo dos comentários...</div>
                    </div>
                    <div id="summary_content" style="display:none;"></div>
                    <div id="summary_empty" style="display:none;text-align:center;color:var(--text-muted);padding:2rem;">
                        <div style="font-size:3rem;margin-bottom:0.75rem;">📝</div>
                        <div style="font-size:.9375rem;font-weight:600;margin-bottom:0.25rem;">Nenhum comentário para analisar</div>
                        <div style="font-size:.8125rem;">Adicione comentários para gerar o resumo.</div>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Trend -->
            <div id="tab-trend" class="comment-tab-content" style="display:none;">
                <div id="trend_container" style="flex:1;overflow-y:auto;">
                    <div id="trend_loading" style="text-align:center;color:var(--text-muted);padding:2rem;">
                        <div style="font-size:2rem;margin-bottom:0.5rem;">📈</div>
                        <div style="font-size:.875rem;">Analisando tendência...</div>
                    </div>
                    <div id="trend_content" style="display:none;"></div>
                    <div id="trend_empty" style="display:none;text-align:center;color:var(--text-muted);padding:2rem;">
                        <div style="font-size:3rem;margin-bottom:0.75rem;">📝</div>
                        <div style="font-size:.9375rem;font-weight:600;margin-bottom:0.25rem;">Comentários insuficientes</div>
                        <div style="font-size:.8125rem;">São necessários pelo menos 2 comentários para analisar a tendência.</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer" style="padding:1rem 1.5rem;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('commentModal')" style="width:100%;">Fechar Janela</button>
        </div>
    </div>
</div>
<?php endif; ?>
