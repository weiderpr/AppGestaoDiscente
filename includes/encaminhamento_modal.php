<?php
/**
 * Component: Referral (Encaminhamento) Modal
 * Used to redirect students to specific sectors or users from the Class Council
 */
if (!isset($canRefer)) {
    $user = getCurrentUser();
    $allowed = ['Administrador', 'Coordenador', 'Professor', 'Pedagogo', 'Diretor', 'Psicólogo'];
    $canRefer = $user && in_array($user['profile'], $allowed);
}

if ($canRefer): 
?>

<div class="modal-backdrop" id="referralModal" role="dialog">
    <div class="modal" style="max-width:760px;">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div id="referral_aluno_photo" style="width:40px;height:40px;border-radius:50%;background:var(--bg-surface-2nd);display:flex;align-items:center;justify-content:center;overflow:hidden;">
                    <span style="font-weight:700;color:var(--text-muted);">?</span>
                </div>
                <div>
                    <div id="referral_aluno_name" style="font-size:1rem;font-weight:700;color:var(--text-primary);">Carregando...</div>
                    <div style="font-size:.75rem;color:var(--text-muted);">Encaminhamentos e Providências</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('referralModal')">✕</button>
        </div>
        
        <div class="modal-body" style="padding:1.5rem; display:flex; flex-direction:column; gap:1.5rem;">
            
            <!-- Referral Form -->
            <form id="referralForm" onsubmit="saveReferral(event); return false;" novalidate style="background:var(--bg-surface-2nd); padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                <input type="hidden" name="aluno_id" id="referral_aluno_id">
                <input type="hidden" name="conselho_id" id="referral_conselho_id">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <!-- Target Sector -->
                    <div class="form-group">
                        <label class="form-label">Setor Destino *</label>
                        <select name="setor_tipo" id="referral_setor" class="form-control" onchange="loadSectorUsers(this.value)">
                            <option value="">Selecione o setor...</option>
                            <option value="Administrador">Administração</option>
                            <option value="Coordenador">Coordenação</option>
                            <option value="Diretor">Diretoria</option>
                            <option value="Pedagogo">Setor Pedagógico</option>
                            <option value="Assistente Social">Assistência Social</option>
                            <option value="Naapi">NAAPI</option>
                            <option value="Psicólogo">Psicologia</option>
                            <option value="Professor">Docentes</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                    
                    <!-- Target Users -->
                    <div class="form-group" style="display:flex; flex-direction:column;">
                        <label class="form-label">Usuários Específicos (opcional)</label>
                        <div id="referral_usuarios_container" style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-sm); height:90px; overflow-y:auto; padding:0.5rem;">
                            <div style="font-size:0.75rem; color:var(--text-muted); text-align:center; padding-top:1.25rem;">Selecione o setor acima...</div>
                        </div>
                        <small style="font-size:0.7rem; color:var(--text-muted); margin-top:0.25rem;">Se nada for marcado, o encaminhamento vai para todo o setor.</small>
                    </div>
                </div>

                <!-- Rich Text Editor -->
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Descrição do Encaminhamento *</span>
                        <div style="display:flex;gap:.25rem;background:var(--bg-surface);padding:.25rem;border-radius:var(--radius-sm);border:1px solid var(--border-color);">
                            <button type="button" class="action-btn" style="width:28px;height:28px;" onclick="formatReferralText('bold')" title="Negrito"><b>B</b></button>
                            <button type="button" class="action-btn" style="width:28px;height:28px;" onclick="formatReferralText('italic')" title="Itálico"><i>I</i></button>
                            <button type="button" class="action-btn" style="width:28px;height:28px;" onclick="formatReferralText('insertUnorderedList')" title="Lista">📋</button>
                        </div>
                    </label>
                    <div id="referral_text" class="form-control" contenteditable="true" style="min-height:100px; max-height:200px; overflow-y:auto; background:var(--bg-surface); padding:.75rem;" placeholder="Descreva o motivo e a solicitação do encaminhamento..."></div>
                </div>

                <div style="display:flex; align-items:flex-end; gap:1rem;">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">Expectativa de Solução</label>
                        <input type="date" name="data_expectativa" id="referral_data" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary" style="height:38px;">📌 Registrar Encaminhamento</button>
                </div>
            </form>

            <!-- History Section -->
            <div id="referral_history_section">
                <h4 style="font-size:0.875rem; font-weight:700; color:var(--text-secondary); margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
                    <span>📜 Histórico de Encaminhamentos</span>
                    <span id="referral_count_badge" style="background:var(--bg-surface-2nd); padding:2px 8px; border-radius:10px; font-size:0.7rem;">0</span>
                </h4>
                <div id="referral_list" style="display:flex; flex-direction:column; gap:1rem;">
                    <!-- Dynamic List -->
                    <div style="text-align:center; padding:2rem; color:var(--text-muted);">
                        Carregando histórico...
                    </div>
                </div>
            </div>

        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('referralModal')" style="width:100%;">Fechar Janela</button>
        </div>
    </div>
</div>

<script src="/assets/js/referrals_system.js?v=2.5"></script>

<?php endif; ?>
