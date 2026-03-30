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

    // Hide registration form if council is completed
    const form = modal.querySelector('form');
    if (form) {
        if (typeof conselhoConcluido !== 'undefined' && conselhoConcluido) {
            form.style.display = 'none';
        } else {
            form.style.display = 'block';
        }
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
        if (typeof Toast !== 'undefined') {
            Toast.show('Por favor, descreva o encaminhamento.', 'warning');
        } else {
            alert('Por favor, descreva o encaminhamento.');
        }
        return;
    }
    if (!setor) {
        if (typeof Toast !== 'undefined') {
            Toast.show('Por favor, selecione o setor de destino.', 'warning');
        } else {
            alert('Por favor, selecione o setor de destino.');
        }
        return;
    }

    if (typeof showLoading === 'function') showLoading('Salvando encaminhamento...');
    btn.disabled = true;

    try {
        const formData = new FormData();
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        formData.append('csrf_token', csrfToken);
        formData.append('aluno_id', document.getElementById('referral_aluno_id').value);
        formData.append('conselho_id', document.getElementById('referral_conselho_id').value);
        formData.append('setor_tipo', document.getElementById('referral_setor').value);
        formData.append('data_expectativa', document.getElementById('referral_data').value);
        formData.append('texto', texto);
        
        // Handle multiple users if they are checked
        const selectedUsers = Array.from(document.querySelectorAll('input[name="usuarios_id[]"]:checked')).map(cb => cb.value);
        selectedUsers.forEach(userId => formData.append('usuarios_id[]', userId));

        const resp = await fetch('/courses/referrals_ajax.php?action=save', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (data.success) {
            if (typeof Toast !== 'undefined') {
                Toast.show('Encaminhamento registrado!', 'success');
            }
            
            document.getElementById('referral_text').innerHTML = '';
            document.getElementById('referral_data').value = '';
            loadReferrals(currentReferralAlunoId, currentReferralConselhoId);
        } else {
            throw new Error(data.message || data.error || 'Erro ao salvar encaminhamento');
        }
    } catch (e) {
        console.error('Save Referral Error:', e);
        if (typeof Toast !== 'undefined') {
            Toast.show(e.message || 'Erro ao salvar encaminhamento', 'danger');
        } else {
            alert('Erro ao salvar: ' + e.message);
        }
    } finally {
        if (typeof hideLoading === 'function') hideLoading();
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
                    'Concluído': 'var(--color-success)',
                    'Atendido': '#8b5cf6',
                    'Aberto': 'var(--color-primary)',
                    'Em Atendimento': '#0ea5e9',
                    'Finalizado': 'var(--color-success)'
                };

                const displayStatus = (item.kanban_status && item.kanban_status !== 'Demandas') ? item.kanban_status : item.status;
                const isReferralActive = item.conselho_is_active == 1;

                const isGlobalCouncilFinished = (window.conselhoIsConcluido === true) || (typeof conselhoConcluido !== 'undefined' && conselhoConcluido === true);
                const isItemFinished = item.conselho_is_active == 0;
                
                const deleteBtn = (isReferralActive && !isGlobalCouncilFinished && !isItemFinished)
                    ? `<button type="button" onclick="deleteReferral(${item.id}, ${alunoId})" style="position:absolute; top:0.75rem; right:0.75rem; background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1rem; transition:color 0.2s;" onmouseover="this.style.color='var(--color-danger)'" onmouseout="this.style.color='var(--text-muted)'" title="Excluir encaminhamento">🗑</button>`
                    : (item.atendimento_id ? `<button type="button" onclick="viewAtendimentoByReferral(${item.id})" style="position:absolute; top:0.75rem; right:0.75rem; background:none; border:none; color:var(--text-primary); cursor:pointer; font-size:1.1rem;" title="Ver Atendimento">👁️</button>` : '');

                const bgStyle = item.atendimento_id ? 'background:#f0fdf4; border:1px solid #dcfce7;' : 'background:var(--bg-surface); border:1px solid var(--border-color);';
                
                html += `
                    <div style="${bgStyle} border-radius:var(--radius-md); padding:1rem; position:relative; margin-bottom:1rem;">
                        ${deleteBtn}
                        
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem; padding-right:2rem;">
                            <div>
                                <div style="font-size:0.625rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:2px;">Destino: ${item.setor_tipo}</div>
                                <div style="font-size:0.75rem; font-weight:700; color:var(--text-primary);">${item.target_users || 'Todo o setor'}</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:0.625rem; color:var(--text-muted);">${date}</div>
                                <span style="font-size:0.625rem; font-weight:700; color:${statusColors[displayStatus] || 'gray'}; text-transform:uppercase;">● ${displayStatus}</span>
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
async function loadCouncilReferrals(conselhoId, isConcluidoOverride = null) {
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
                    'Concluído': '#10b981',
                    'Atendido': '#8b5cf6',
                    'Aberto': '#3b82f6',
                    'Em Atendimento': '#0ea5e9',
                    'Finalizado': '#10b981'
                };
                
                const displayStatus = (item.kanban_status && item.kanban_status !== 'Demandas') ? item.kanban_status : item.status;

                const isReferralActive = item.conselho_is_active == 1;

                const isItemFinished = item.conselho_is_active == 0;
                const isConcluidoGlobal = (window.conselhoIsConcluido === true);
                const isConcluidoParam = (isConcluidoOverride === true);
                
                const isConcluido = isConcluidoParam || isConcluidoGlobal || isItemFinished;

                let actionContent;
                if (isConcluido) {
                    actionContent = item.atendimento_id 
                        ? `<button type="button" class="btn btn-ghost btn-sm" onclick="viewAtendimentoByReferral(${item.id})" style="color:var(--color-primary); padding:4px 8px; min-width:unset; font-size:1.1rem;" title="Ver Atendimento">👁️</button>` 
                        : '—';
                } else {
                    actionContent = isReferralActive 
                        ? `<button type="button" class="btn btn-ghost btn-sm" onclick="deleteReferral(${item.id}, null, true)" style="color:var(--color-danger); padding:4px 8px; min-width:unset; font-size:1rem;" title="Remover encaminhamento">🗑</button>`
                        : (item.atendimento_id ? `<button type="button" class="btn btn-ghost btn-sm" onclick="viewAtendimentoByReferral(${item.id})" style="color:var(--color-primary); padding:4px 8px; min-width:unset; font-size:1.1rem;" title="Ver Atendimento">👁️</button>` : '—');
                }

                const rowBg = item.atendimento_id ? 'background-color:#f0fdf4;' : '';

                html += `
                    <tr style="${rowBg}">
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
                            <span style="display:inline-block; padding:.25rem .75rem; border-radius:12px; font-size:.6875rem; font-weight:700; text-transform:uppercase; background:${statusColors[displayStatus] + '22'}; color:${statusColors[displayStatus]}; border:1px solid ${statusColors[displayStatus] + '44'};">
                                ${displayStatus}
                            </span>
                        </td>
                        <td style="padding:1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; text-align:center;">
                            <div style="font-size:0.8125rem; font-weight:600; color:var(--text-secondary);">${expDate}</div>
                        </td>
                        <td style="padding:1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; text-align:center;">
                            ${actionContent}
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
    if (window.conselhoIsConcluido === true || (typeof conselhoConcluido !== 'undefined' && conselhoConcluido === true)) {
        if (typeof Toast !== 'undefined') {
            Toast.show('Este conselho já foi finalizado. Não é permitido excluir encaminhamentos.', 'warning');
        } else {
            alert('Este conselho já foi finalizado. Não é permitido excluir encaminhamentos.');
        }
        return;
    }
    
    const performDelete = async () => {
        if (typeof showLoading === 'function') showLoading('Removendo...');
        try {
            const resp = await fetch(`/courses/referrals_ajax.php?action=delete&id=${referralId}`);
            const data = await resp.json();

            if (data.success) {
                if (typeof Toast !== 'undefined') {
                    Toast.show('Encaminhamento excluído!', 'success');
                }
                
                // Reload appropriate view
                if (isCouncilView && typeof conselhoId !== 'undefined') {
                    loadCouncilReferrals(conselhoId, window.conselhoIsConcluido);
                } else {
                    loadReferrals(alunoId, currentReferralConselhoId);
                }
            } else {
                throw new Error(data.message || 'Erro ao excluir');
            }
        } catch (e) {
            console.error(e);
            if (typeof Toast !== 'undefined') {
                Toast.show(e.message || 'Erro ao excluir', 'danger');
            } else {
                alert('Erro: ' + e.message);
            }
        } finally {
            if (typeof hideLoading === 'function') hideLoading();
        }
    };

    if (typeof Modal !== 'undefined' && typeof Modal.confirm === 'function') {
        Modal.confirm({
            title: 'Excluir Encaminhamento',
            message: 'Deseja realmente excluir este encaminhamento?',
            confirmText: 'Excluir',
            confirmClass: 'btn-danger',
            onConfirm: performDelete
        });
    } else {
        if (confirm('Deseja realmente excluir este encaminhamento?')) {
            performDelete();
        }
    }
}

/**
 * Abre modal com detalhes do atendimento vinculado a um encaminhamento
 */
async function viewAtendimentoByReferral(referralId) {
    if (typeof showLoading === 'function') showLoading('Buscando atendimento...');
    
    try {
        const resp = await fetch(`/courses/referrals_ajax.php?action=get_atendimento&id=${referralId}`);
        const data = await resp.json();
        
        if (data.success) {
            // Usa a lógica compartilhada para preencher o modal
            populateAtendimentoModal(data, { isRestricted: true });
            
            // Define o ID global para que ações (comentários) funcionem
            window.currentAtendimentoId = data.atendimento.id;
            
            // Abre o modal
            openModal('modalCardDetails');
        } else {
            if (typeof Toast !== 'undefined') {
                Toast.show(data.message || 'Erro ao carregar detalhes', 'danger');
            } else {
                alert('Erro: ' + data.message);
            }
        }
    } catch (e) {
        console.error('Erro ao buscar atendimento:', e);
        if (typeof Toast !== 'undefined') {
            Toast.show('Erro ao carregar detalhes do atendimento.', 'danger');
        } else {
            alert('Erro ao carregar detalhes do atendimento.');
        }
    } finally {
        if (typeof hideLoading === 'function') hideLoading();
    }
}
