<!-- 
    Vértice Acadêmico — Modal de Detalhes de Atendimento (Compartilhado)
-->
<div class="modal-backdrop" id="modalCardDetails" role="dialog">
    <div class="modal" style="max-width: 800px; width: 95vw; height: 80vh; overflow: hidden; display: flex; flex-direction: column;">
        <!-- Fixed Header with Photo and Name -->
        <div id="modalFixedHeader" style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); background: var(--bg-surface); display: flex; align-items: center; gap: 1rem;">
            <img id="cdAlunoPhoto" src="" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; display: none; border: 2px solid var(--border-color);">
            <div id="cdAlunoAvatar" class="m-aluno-avatar-text" style="width: 48px; height: 48px; font-size: 1.25rem; display: none; border-radius: 50%;"></div>
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span id="cdBadgeStatus" class="kanban-badge" style="font-size: 0.6rem;"></span>
                </div>
                <div id="cdAlunoSubtitle" style="font-size: 0.9375rem; color: var(--text-primary); font-weight: 600; margin-top: 0.25rem;"></div>
            </div>
            <button class="modal-close" onclick="closeModal('modalCardDetails')" style="position: static;">×</button>
        </div>

        <div class="modal-body" style="padding: 0; flex: 1; overflow: hidden;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0; height: 100%; overflow: hidden;">
                <!-- Main Area (Left) with Tabs -->
                <div style="border-right: 1px solid var(--border-color); display: flex; flex-direction: column; overflow: hidden;">
                    <!-- Tabs Navigation -->
                    <div style="display: flex; border-bottom: 1px solid var(--border-color); background: var(--bg-surface-2nd); padding: 0 1rem; flex-shrink: 0;">
                        <button class="tab-btn active" data-tab="info" onclick="switchTab(this, 'info')">
                            <span>📋</span> Informações
                        </button>
                        <button class="tab-btn" data-tab="timeline" onclick="switchTab(this, 'timeline')">
                            <span>💬</span> Timeline
                        </button>
                        <button class="tab-btn" data-tab="anexos" onclick="switchTab(this, 'anexos')">
                            <span>📎</span> Anexos
                        </button>
                    </div>

                    <!-- Tab Content -->
                    <div style="flex: 1; overflow: hidden; display: flex; flex-direction: column; padding: 1.5rem; min-height: 0;">
                        
                        <!-- Tab: Informações -->
                        <div id="tab-info" class="tab-content" style="display: block;">
                            
                            <h2 id="cdMainTitle" style="margin: 0 0 1.5rem 0; line-height: 1.2; font-size: 1.25rem; font-weight: 700;">Título</h2>

                            <div id="cdDemandaContext" style="background: var(--bg-surface-2nd); padding: 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-bottom: 1.5rem; display: none; position: relative; overflow: hidden;">
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

                                <div style="text-align: right; margin-bottom: 1rem; border-top: 1px solid var(--border-color-light); padding-top: 0.75rem; display:none;" id="cdSaveInfoBtnContainer">
                                    <button class="btn btn-primary" onclick="saveAtendimentoInfo()">
                                        <span style="margin-right:0.5rem;">💾</span> Salvar Descrições
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Timeline -->
                        <div id="tab-timeline" class="tab-content" style="display: none;">
                            <div id="cdTimelineSection" style="display: flex; flex-direction: column; height: 100%;">
                                <div style="background: var(--bg-surface-2nd); padding: 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-bottom: 1rem; flex-shrink: 0;">
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

                                <div class="timeline-container" id="cdTimeline" style="flex: 1; overflow-y: auto;">
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Anexos -->
                        <div id="tab-anexos" class="tab-content" style="display: none;">
                            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                                <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;">📎</div>
                                <p style="font-size: 0.9375rem;">Funcionalidade de anexos em breve</p>
                                <p style="font-size: 0.8125rem; margin-top: 0.5rem;">Você poderá adicionar arquivos e documentos a este atendimento.</p>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Sidebar (Right) -->
                <div style="padding: 1.5rem; background: var(--bg-surface-2nd); overflow-y: auto; display:flex; flex-direction:column; gap:1.5rem;">
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

/* Tab Navigation Styles */
.tab-btn {
    background: none;
    border: none;
    padding: 0.875rem 1rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}
.tab-btn:hover {
    color: var(--text-secondary);
    background: var(--bg-surface);
}
.tab-btn.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
    background: var(--bg-surface);
}
.tab-content {
}
.tab-show { display: block; height: 100%; overflow-y: auto; }
.tab-hide { display: none; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>


