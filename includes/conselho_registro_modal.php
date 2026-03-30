<!-- Modal de Registros do Conselho -->
<div id="registroModal" class="modal-backdrop" role="dialog">
    <div class="modal" style="max-width: 650px;">
        <div class="modal-header">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <div style="width:40px; height:40px; border-radius:12px; background:rgba(79, 70, 229, 0.1); color:var(--color-primary); display:flex; align-items:center; justify-content:center; font-size:1.25rem;">📝</div>
                <div>
                    <h3 style="margin:0; font-size:1rem; font-weight:700; color:var(--text-primary);">Registros do Conselho</h3>
                    <div id="registro_context_subtitle" style="font-size:0.75rem; color:var(--text-muted); font-weight:500;">Anotações e discussões da sessão</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('registroModal')">✕</button>
        </div>

        <div class="modal-body" style="padding: 1.5rem;">
            
            <!-- Student Banner (Visible only if aluno_id is present) -->
            <div id="registro_aluno_banner" style="display:none; align-items:center; gap:1rem; padding:1rem; background:var(--bg-surface-2nd); border-radius:var(--radius-md); margin-bottom:1.5rem; border:1px solid var(--border-color);">
                <div id="registro_aluno_photo" style="width:40px; height:40px; border-radius:50%; background:var(--gradient-brand); display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:1rem; overflow:hidden;"></div>
                <div>
                    <div id="registro_aluno_name" style="font-weight:700; color:var(--text-primary); font-size:0.9375rem;">Nome do Aluno</div>
                    <div style="font-size:0.75rem; color:var(--text-muted);">Registro vinculado a este estudante</div>
                </div>
            </div>

            <!-- New Record Form -->
            <form id="registroForm" onsubmit="saveCouncilRecord(event)" novalidate style="margin-bottom:2rem;">
                <input type="hidden" name="conselho_id" id="registro_conselho_id">
                <input type="hidden" name="aluno_id" id="registro_aluno_id">

                <div class="form-group">
                    <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                        <span>O que foi discutido?</span>
                        <div style="display:flex; gap:0.25rem; background:var(--bg-surface-2nd); padding:0.25rem; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                            <button type="button" class="action-btn" style="width:28px; height:28px;" onclick="formatRecordText('bold')" title="Negrito"><b>B</b></button>
                            <button type="button" class="action-btn" style="width:28px; height:28px;" onclick="formatRecordText('italic')" title="Itálico"><i>I</i></button>
                            <button type="button" class="action-btn" style="width:28px; height:28px;" onclick="formatRecordText('insertUnorderedList')" title="Lista">•</button>
                        </div>
                    </label>
                    <div id="registro_text" contenteditable="true" class="form-control" style="min-height:100px; height:auto; padding:1rem; line-height:1.5; outline:none; background:var(--bg-surface); border-style:dashed;" placeholder="Digite aqui o registro da discussão..."></div>
                </div>

                <div style="display:flex; justify-content:flex-end; margin-top:1rem;">
                    <button type="submit" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:0.5rem;">
                        <span>💾 Salvar Registro</span>
                    </button>
                </div>
            </form>

            <!-- History Section -->
            <div style="border-top: 1px solid var(--border-color); padding-top:1.5rem;">
                <div style="margin-bottom:1rem;">
                    <h4 style="margin:0; font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:0.5rem;">
                        <span>Histórico de Registros</span>
                        <span id="registro_count_badge" style="background:var(--color-primary); color:white; font-size:0.625rem; padding:2px 6px; border-radius:10px;">0</span>
                    </h4>
                </div>
                
                <div id="registro_history_list" style="display:flex; flex-direction:column; gap:1rem;">
                    <!-- Registros renderizados aqui -->
                    <div style="text-align:center; padding:2rem; color:var(--text-muted); font-size:0.875rem;">Carregando histórico...</div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" style="width:100%;" onclick="closeModal('registroModal')">Fechar Janela</button>
        </div>
    </div>
</div>

<style>
#registro_text:empty:before {
    content: attr(placeholder);
    color: var(--text-muted);
    font-size: 0.875rem;
}
</style>
