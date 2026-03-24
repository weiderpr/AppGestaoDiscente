/**
 * Vértice Acadêmico — Sistema de Encaminhamentos
 */

let currentReferralAlunoId = null;
let currentReferralConselhoId = null;

/**
 * Abre o modal de encaminhamento para um aluno específico
 */
function openReferralModal(alunoId, alunoNome, conselhoId) {
    currentReferralAlunoId = alunoId || 0;
    currentReferralConselhoId = conselhoId;

    const modal = document.getElementById('referralModal');
    if (!modal) return;

    // Reset Form
    const alunoInput = document.getElementById('referral_aluno_id');
    const conselhoInput = document.getElementById('referral_conselho_id');
    const nameDisplay = document.getElementById('referral_aluno_name');
    
    if (alunoInput) alunoInput.value = currentReferralAlunoId;
    if (conselhoInput) conselhoInput.value = conselhoId;
    if (nameDisplay) nameDisplay.textContent = alunoNome || 'Encaminhamento para a Turma';
    
    document.getElementById('referral_text').innerHTML = '';
    document.getElementById('referral_data').value = '';
    document.getElementById('referral_setor').value = '';
    document.getElementById('referral_usuarios_container').innerHTML = '<div style="font-size:0.75rem; color:var(--text-muted); text-align:center; padding-top:1.25rem;">Selecione o setor acima...</div>';

    // Load History
    loadReferrals(currentReferralAlunoId, currentReferralConselhoId);

    // Show Modal
    if (typeof openModal === 'function') {
        openModal('referralModal');
    } else {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Carrega usuários do setor selecionado
 */
async function loadSectorUsers(sector) {
    const container = document.getElementById('referral_usuarios_container');
    if (!sector) {
        container.innerHTML = '<div style="font-size:0.75rem; color:var(--text-muted); text-align:center; padding-top:1.25rem;">Selecione o setor acima...</div>';
        return;
    }

    container.innerHTML = '<div style="text-align:center; padding-top:1.25rem; font-size:0.75rem; color:var(--text-muted);">⏳ Carregando...</div>';

    try {
        const resp = await fetch(`/courses/referrals_ajax.php?action=get_users&setor=${sector}`);
        const data = await resp.json();

        if (data.success) {
            if (data.users.length > 0) {
                let html = '<div style="display:flex; flex-direction:column; gap:0.5rem;">';
                data.users.forEach(u => {
                    html += `
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.875rem; color:var(--text-secondary); padding:0.25rem; border-radius:var(--radius-sm); transition:all 0.2s;">
                            <input type="checkbox" name="usuarios_id[]" value="${u.id}" style="width:16px; height:16px;">
                            <span>${u.name}</span>
                        </label>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div style="text-align:center; padding-top:1.25rem; font-size:0.75rem; color:var(--text-muted);">Nenhum usuário encontrado neste setor.</div>';
            }
        }
    } catch (e) {
        console.error('Erro ao carregar usuários:', e);
        container.innerHTML = '<div style="text-align:center; padding-top:1.25rem; font-size:0.75rem; color:var(--color-danger);">Erro ao carregar profissionais.</div>';
    }
}

/**
 * Salva um novo encaminhamento
 */
async function saveReferral(event) {
    event.preventDefault();
    const btn = event.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    
    const texto = document.getElementById('referral_text').innerHTML.trim();
    const setor = document.getElementById('referral_setor').value;
    
    if (!texto || texto === '<br>') {
        alert('Por favor, descreva o encaminhamento.');
        return;
    }
    if (!setor) {
        alert('Por favor, selecione o setor de destino.');
        return;
    }

    btn.innerHTML = '⏳ Salvando...';
    btn.disabled = true;

    try {
        const formData = new FormData(event.target);
        formData.append('texto', texto);
        
        const resp = await fetch('/courses/referrals_ajax.php?action=save', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();

        if (data.success) {
            if (typeof showToast === 'function') showToast('Encaminhamento registrado!', 'success');
            else alert('Encaminhamento registrado com sucesso!');
            
            // Reset and reload
            document.getElementById('referral_text').innerHTML = '';
            document.getElementById('referral_data').value = '';
            loadReferrals(currentReferralAlunoId, currentReferralConselhoId);
        } else {
            throw new Error(data.message || 'Erro desconhecido');
        }
    } catch (e) {
        alert('Erro ao salvar: ' + e.message);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

/**
 * Carrega o histórico de encaminhamentos
 */
async function loadReferrals(alunoId, conselhoId = null) {
    const list = document.getElementById('referral_list');
    const badge = document.getElementById('referral_count_badge');
    
    try {
        const url = alunoId > 0 
            ? `/courses/referrals_ajax.php?action=list&aluno_id=${alunoId}`
            : `/courses/referrals_ajax.php?action=list&conselho_id=${conselhoId}&aluno_id=0`;
            
        const resp = await fetch(url);
        const data = await resp.json();

        if (data.success) {
            badge.textContent = data.list.length;
            if (data.list.length === 0) {
                list.innerHTML = '<div style="text-align:center; padding:1.5rem; color:var(--text-muted); font-size:0.875rem;">Nenhum encaminhamento registrado para este aluno.</div>';
                return;
            }

            let html = '';
            data.list.forEach(item => {
                const date = new Date(item.created_at).toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'});
                const expDate = item.data_expectativa ? new Date(item.data_expectativa).toLocaleDateString('pt-BR') : 'Prazo indefinido';
                
                const statusColors = {
                    'Pendente': 'var(--color-warning)',
                    'Em Andamento': 'var(--color-primary)',
                    'Concluído': 'var(--color-success)'
                };

                html += `
                    <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:1rem; position:relative; margin-bottom:1rem;">
                        <button type="button" onclick="deleteReferral(${item.id}, ${alunoId})" style="position:absolute; top:0.75rem; right:0.75rem; background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1rem; transition:color 0.2s;" onmouseover="this.style.color='var(--color-danger)'" onmouseout="this.style.color='var(--text-muted)'" title="Excluir encaminhamento">🗑</button>
                        
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem; padding-right:2rem;">
                            <div>
                                <div style="font-size:0.625rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:2px;">Destino: ${item.setor_tipo}</div>
                                <div style="font-size:0.75rem; font-weight:700; color:var(--text-primary);">${item.target_users || 'Todo o setor'}</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:0.625rem; color:var(--text-muted);">${date}</div>
                                <span style="font-size:0.625rem; font-weight:700; color:${statusColors[item.status] || 'gray'}; text-transform:uppercase;">● ${item.status}</span>
                            </div>
                        </div>
                        
                        <div style="font-size:0.875rem; line-height:1.5; color:var(--text-secondary); margin-bottom:0.75rem; border-left:3px solid var(--border-color); padding-left:0.75rem;">
                            ${item.texto}
                        </div>
                        
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem;">
                            <div style="color:var(--text-muted);">Por: <b>${item.author_name}</b> (${item.author_profile})</div>
                            <div style="background:var(--bg-surface-2nd); padding:2px 8px; border-radius:10px; font-weight:600; color:var(--text-secondary);">Expectativa: ${expDate}</div>
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
        }
    } catch (e) {
        console.error('Erro ao listar encaminhamentos:', e);
        list.innerHTML = '<div style="text-align:center; padding:1rem; color:var(--color-danger);">Erro ao carregar histórico.</div>';
    }
}

/**
 * Carrega todos os encaminhamentos de um conselho específico (Visão Geral)
 */
async function loadCouncilReferrals(conselhoId) {
    const container = document.getElementById('council_referrals_list');
    if (!container) return;

    container.innerHTML = '<div style="text-align:center; padding:3rem; color:var(--text-muted);">⏳ Carregando todos os encaminhamentos...</div>';

    try {
        const resp = await fetch(`/courses/referrals_ajax.php?action=list_by_council&conselho_id=${conselhoId}`);
        const data = await resp.json();

        if (data.success) {
            if (data.list.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center; padding:3rem; color:var(--text-muted); background:var(--bg-surface); border-radius:var(--radius-lg); border:1px dashed var(--border-color);">
                        <p style="font-size:3rem; margin-bottom:1rem;">📌</p>
                        <p style="font-weight:600;">Nenhum encaminhamento registrado neste conselho.</p>
                        <p style="font-size:0.875rem;">Os encaminhamentos registrados para os alunos aparecerão aqui.</p>
                    </div>
                `;
                return;
            }

            let html = `
                <div class="table-responsive">
                    <table class="table" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:var(--bg-surface-2nd);">
                                <th style="padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border-color);">Aluno</th>
                                <th style="padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border-color);">Setor / Destino</th>
                                <th style="padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border-color);">Encaminhamento</th>
                                <th style="padding:.75rem 1rem; text-align:center; font-size:.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border-color);">Status</th>
                                <th style="padding:.75rem 1rem; text-align:center; font-size:.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border-color);">Expectativa</th>
                                <th style="padding:.75rem 1rem; text-align:center; font-size:.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border-color);">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            data.list.forEach(item => {
                const date = new Date(item.created_at).toLocaleDateString('pt-BR');
                const expDate = item.data_expectativa ? new Date(item.data_expectativa).toLocaleDateString('pt-BR') : '—';
                
                const statusColors = {
                    'Pendente': '#f59e0b',
                    'Em Andamento': '#3b82f6',
                    'Concluído': '#10b981'
                };

                html += `
                    <tr>
                        <td style="padding:1rem; border-bottom:1px solid var(--border-color); vertical-align:middle;">
                            <div style="font-weight:700; color:${item.aluno_name ? 'var(--text-primary)' : 'var(--color-primary)'};">${item.aluno_name || '👥 Turma (Geral)'}</div>
                            <div style="font-size:0.7rem; color:var(--text-muted);">Registrado em ${date}</div>
                        </td>
                        <td style="padding:1rem; border-bottom:1px solid var(--border-color); vertical-align:middle;">
                            <div style="font-size:0.625rem; font-weight:700; text-transform:uppercase; color:var(--text-muted);">${item.setor_tipo}</div>
                            <div style="font-size:0.8125rem; font-weight:600; color:var(--text-secondary);">${item.target_users || 'Geral'}</div>
                        </td>
                        <td style="padding:1rem; border-bottom:1px solid var(--border-color); vertical-align:middle;">
                            <div style="font-size:0.875rem; line-height:1.4; color:var(--text-secondary); max-width:400px;">${item.texto}</div>
                            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.4rem;">Por: <b>${item.author_name}</b></div>
                        </td>
                        <td style="padding:1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; text-align:center;">
                            <span style="display:inline-block; padding:.25rem .75rem; border-radius:12px; font-size:.6875rem; font-weight:700; text-transform:uppercase; background:${statusColors[item.status] + '22'}; color:${statusColors[item.status]}; border:1px solid ${statusColors[item.status] + '44'};">
                                ${item.status}
                            </span>
                        </td>
                        <td style="padding:1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; text-align:center;">
                            <div style="font-size:0.8125rem; font-weight:600; color:var(--text-secondary);">${expDate}</div>
                        </td>
                        <td style="padding:1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; text-align:center;">
                            <button type="button" class="btn btn-ghost btn-sm" onclick="deleteReferral(${item.id}, null, true)" style="color:var(--color-danger); padding:4px 8px; min-width:unset; font-size:1rem;" title="Remover encaminhamento">
                                🗑
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;
            container.innerHTML = html;
        }
    } catch (e) {
        console.error('Erro ao carregar encaminhamentos do conselho:', e);
        container.innerHTML = '<div style="text-align:center; padding:3rem; color:var(--color-danger); font-weight:600;">⚠️ Erro ao carregar os dados.</div>';
    }
}

/**
 * Exclui um encaminhamento
 */
async function deleteReferral(referralId, alunoId = null, isCouncilView = false) {
    if (!confirm('Deseja realmente excluir este encaminhamento?')) return;

    try {
        const resp = await fetch(`/courses/referrals_ajax.php?action=delete&id=${referralId}`);
        const data = await resp.json();

        if (data.success) {
            if (typeof showToast === 'function') showToast('Encaminhamento excluído!', 'success');
            else alert('Encaminhamento excluído com sucesso!');
            
            // Reload appropriate view
            if (isCouncilView && typeof conselhoId !== 'undefined') {
                loadCouncilReferrals(conselhoId);
            } else {
                loadReferrals(alunoId, currentReferralConselhoId);
            }
        } else {
            throw new Error(data.message || 'Erro ao excluir');
        }
    } catch (e) {
        alert('Erro: ' + e.message);
    }
}

/**
 * Rich Text Helpers
 */
function formatReferralText(command) {
    document.execCommand(command, false, null);
    document.getElementById('referral_text').focus();
}
