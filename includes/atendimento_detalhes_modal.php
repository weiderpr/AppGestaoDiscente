<!-- 
    Vértice Acadêmico — Modal de Detalhes de Atendimento (Compartilhado)
-->
<div class="modal-backdrop" id="modalCardDetails" role="dialog">
    <div class="modal" style="max-width: 800px; width: 95vw;">
        <div class="modal-header">
            <h3 id="cdTitle">Detalhes do Atendimento</h3>
            <button class="modal-close" onclick="closeModal('modalCardDetails')">×</button>
        </div>
        <div class="modal-body" style="padding: 0;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0; min-height: 400px;">
                <!-- Main Area (Left) -->
                <div style="padding: 2rem; border-right: 1px solid var(--border-color); overflow-y: auto; max-height: 80vh;">
                    
                    <!-- Student Header -->
                    <div style="display: flex; align-items: flex-start; gap: 1.5rem; margin-bottom: 2rem;">
                        <img id="cdAlunoPhoto" src="" style="width: 84px; height: 84px; border-radius: var(--radius-lg); object-fit: cover; display: none; border: 2px solid var(--border-color); box-shadow: var(--shadow-sm);">
                        <div id="cdAlunoAvatar" class="m-aluno-avatar-text" style="width: 84px; height: 84px; font-size: 2rem; display: none; border-radius: var(--radius-lg);"></div>
                        
                        <div style="flex: 1;">
                            <div id="cdBadgeStatus" class="kanban-badge" style="margin-bottom: 0.5rem; display: inline-block;">Status</div>
                            <h2 id="cdMainTitle" style="margin: 0.25rem 0 0.5rem 0; line-height: 1.2; font-size: 1.5rem;">Título</h2>
                            <div id="cdAlunoSubtitle" style="font-size: 0.9375rem; color: var(--text-muted); font-weight: 600;"></div>
                        </div>
                    </div>

                    <!-- Contexto da Demanda (Original Council Info) -->
                    <div id="cdDemandaContext" style="background: var(--bg-surface-2nd); padding: 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-bottom: 2rem; display: none; position: relative; overflow: hidden;">
                        <div style="position: absolute; right: -10px; top: -10px; font-size: 4rem; opacity: 0.05; transform: rotate(15deg);">📋</div>
                        <h4 style="font-size: 0.7rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.375rem;">
                            <span>📌</span> Origem: Conselho de Classe
                        </h4>
                        <div style="font-size: 0.9rem; line-height: 1.5; color: var(--text-primary);">
                             <div id="cdCouncilName" style="font-weight: 700; margin-bottom: 0.25rem;"></div>
                             <div id="cdDemandText" style="font-style: italic; color: var(--text-muted); border-left: 3px solid var(--border-color); padding-left: 1rem; margin: 0.75rem 0;"></div>
                             <div id="cdDeadline" style="font-size: 0.8125rem; font-weight: 700; color: #ef4444; display: flex; align-items: center; gap: 0.375rem;">
                                 <span>⏰</span> Prazo esperado: <span id="cdDeadlineValue"></span>
                             </div>
                        </div>
                    </div>

                    <!-- Editor / Descrições -->
                    <div id="cdEditorSection">
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label">Informação Pública (Visível para o aluno em relatórios)</label>
                            <textarea class="form-control" id="cdDescPublica" rows="3" placeholder="Descreva o que será visível para o aluno..."></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom: 0.75rem;">
                            <label class="form-label">Anotação Profissional (Privado ao conselho/equipe)</label>
                            <div id="cdDescProfissionalWrapper" style="position:relative;">
                                <textarea class="form-control" id="cdDescProfissional" rows="3" placeholder="Anotações internas para a equipe..."></textarea>
                            </div>
                        </div>

                        <div style="text-align: right; margin-bottom: 2rem; border-top: 1px solid var(--border-color-light); padding-top: 0.75rem; display:none;" id="cdSaveInfoBtnContainer">
                            <button class="btn btn-primary" onclick="saveAtendimentoInfo()">
                                <span style="margin-right:0.5rem;">💾</span> Salvar Descrições
                            </button>
                        </div>
                    </div>

                    <!-- Timeline de Comentários -->
                    <div id="cdTimelineSection">
                        <h4 style="display:flex; align-items:center; gap:0.375rem; margin-bottom:0.75rem; font-size:0.75rem; text-transform:uppercase; font-weight:800; letter-spacing:0.05em; color: var(--text-muted);">
                            <span>💬</span> Timeline de Ações e Comentários
                        </h4>
                        <div style="background: var(--bg-surface-2nd); padding: 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                            <form id="formNewComment" style="display:flex; gap:0.75rem; flex-direction:column;">
                                <textarea class="form-control" id="ncTexto" rows="2" placeholder="Escreva uma atualização ou observação..."></textarea>
                                <div style="display:flex; justify-content: space-between; align-items: center;">
                                    <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.8125rem; color:var(--text-muted); cursor:pointer;">
                                        <input type="checkbox" id="ncPrivate" style="width:14px; height:14px;"> 
                                        <span style="display:flex; align-items:center; gap:0.25rem;">🔒 Registro Privado</span>
                                    </label>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="submitComment()">
                                        Postar Registro
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="timeline-container" id="cdTimeline">
                            <!-- Comentários via JS -->
                        </div>
                    </div>
                </div>

                <!-- Sidebar (Right) -->
                <div style="padding: 1.5rem; background: var(--bg-surface-2nd); overflow-y: auto; max-height: 80vh; display:flex; flex-direction:column; gap:1.5rem;">
                    <div id="cdProfessionalsSection">
                        <h4 style="margin-bottom: 1rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em; color: var(--text-muted); display:flex; align-items:center; gap:0.375rem;">
                            <span>👥</span> Equipe Responsável
                        </h4>
                        <div id="cdResponsaveisList" style="display:flex; flex-direction: column; gap: 0.5rem;">
                            <!-- JS -->
                        </div>

                        <div style="border-top: 1px dashed var(--border-color); padding-top: 1.25rem; margin-top: 1.25rem;">
                            <div style="position:relative;">
                                <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:0.8rem; opacity:0.5;">🔍</span>
                                <input type="text" id="userSearch" class="form-control form-control-sm" placeholder="Adicionar profissional..." oninput="searchUsers(this.value)" style="padding-left:30px;">
                            </div>
                            <div id="userSearchResults" style="margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.375rem;"></div>
                        </div>
                    </div>

                    <!-- Botão de Excluir / Arquivar (Final da Sidebar) -->
                    <div id="cdDeleteSection" style="margin-top: auto; padding-top: 1.5rem; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 0.75rem;">
                        <button id="btnArchiveAtendimento" class="btn btn-outline-secondary btn-sm" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem;" onclick="archiveAtendimentoToggle()">
                            <span id="archiveIcon">📦</span> <span id="archiveText">Arquivar Card</span>
                        </button>
                        <div style="height: 1px; background: var(--border-color-light); margin: 0.25rem 0;"></div>
                        <button class="btn btn-outline-danger btn-sm" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem;" onclick="deleteAtendimento()">
                            <span>🗑️</span> Excluir Card
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos para o Modal de Atendimento */
.comment-item {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-bottom: 1rem;
    font-size: 0.875rem;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.comment-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}
.c-private-badge {
    background: #fee2e2;
    color: #ef4444;
    padding: 1px 6px;
    border-radius: 4px;
    font-weight: 700;
}
.kanban-badge {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    background: var(--bg-surface-2nd);
    padding: 2px 8px;
    border-radius: 12px;
}
.m-aluno-avatar-text {
    background: #e0f2fe;
    color: #0369a1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
}

/* Privacy Blur Logic (CSS-based) */
.privacy-blur {
    position: relative;
    overflow: hidden;
    cursor: pointer;
    min-height: 60px;
}
.privacy-blur > *:not(.privacy-overlay) {
    filter: blur(10px);
    transition: filter 0.3s ease;
    pointer-events: none;
    user-select: none;
}
.privacy-blur.revealed > *:not(.privacy-overlay) {
    filter: blur(0);
    pointer-events: auto;
    user-select: auto;
}
.privacy-overlay {
    position: absolute;
    inset: 0;
    background: rgba(var(--bg-surface-2nd-rgb), 0.8);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-align: center;
    padding: 1rem;
    z-index: 10;
    transition: opacity 0.3s ease;
    border: 1px dashed var(--border-color);
    border-radius: var(--radius-md);
}
.privacy-blur.revealed .privacy-overlay {
    opacity: 0;
    pointer-events: none;
}
</style>
