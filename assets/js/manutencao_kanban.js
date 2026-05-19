/**
 * Vértice Acadêmico — Kanban Manutenções Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    loadBoard();
    initDragAndDrop();
});

let draggedCard = null;
let currentMaintenanceId = null;

document.addEventListener('input', (e) => {
    if (e.target.classList.contains('money-mask')) {
        applyMoneyMask(e.target);
    }
});

function applyMoneyMask(input) {
    let value = input.value.replace(/\D/g, '');
    value = (value / 100).toFixed(2) + '';
    value = value.replace(".", ",");
    value = value.replace(/(\d)(\d{3})(\d{3}),/g, "$1.$2.$3,");
    value = value.replace(/(\d)(\d{3}),/g, "$1.$2,");
    input.value = value;
}

async function loadBoard() {
    showLoading();
    try {
        const res = await fetch('../api/manutencao/manutencoes_ajax.php?action=list_kanban');
        const data = await res.json();
        hideLoading();
        
        if (data.success) {
            renderColumn('Demandas', data.data['Demandas'] || []);
            renderColumn('Em Aberto', data.data['Em Aberto'] || []);
            renderColumn('Em Execução', data.data['Em Execução'] || []);
            renderColumn('Finalizado', data.data['Finalizado'] || []);
            
            document.querySelectorAll('.column-filter').forEach(input => input.value = '');
        } else {
            Toast.error('Erro ao carregar quadro: ' + data.message);
        }
    } catch (e) {
        hideLoading();
        Toast.error('Erro de conexão ao carregar quadro.');
    }
}

function renderColumn(status, cards) {
    const colEl = document.getElementById('col-' + status);
    const countEl = document.getElementById('count-' + status);
    
    if (!colEl) return;
    
    colEl.innerHTML = '';
    countEl.innerText = cards.length;

    cards.forEach(card => {
        const dateStr = card.data_manutencao ? new Date(card.data_manutencao).toLocaleDateString() : '';
        
        let problemasHtml = '';
        if (card.problemas && card.problemas.length > 0) {
            problemasHtml = '<div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:0.5rem;">';
            card.problemas.forEach(p => {
                problemasHtml += `<span style="font-size:0.65rem; background:var(--bg-surface-2nd); padding:2px 6px; border-radius:4px; border:1px solid var(--border-color);">${p.descricao}</span>`;
            });
            if (card.outros_detalhes) {
                problemasHtml += `<span style="font-size:0.65rem; background:var(--color-primary-light); color:var(--color-primary); padding:2px 6px; border-radius:4px; border:1px solid var(--color-primary-light); font-weight:bold;">+ Outros</span>`;
            }
            problemasHtml += '</div>';
        } else if (card.outros_detalhes) {
            problemasHtml = '<div style="margin-top:0.5rem;"><span style="font-size:0.65rem; background:var(--color-primary-light); color:var(--color-primary); padding:2px 6px; border-radius:4px; border:1px solid var(--color-primary-light); font-weight:bold;">Outros</span></div>';
        }

        const cardEl = document.createElement('div');
        cardEl.className = 'k-card';
        cardEl.draggable = CAN_MOVE_MANUTENCAO;
        cardEl.dataset.id = card.id;
        
        cardEl.onclick = (e) => {
            openMaintenanceDetails(card.id);
        };

        let outrosHtml = '';
        if (card.outros_detalhes) {
            outrosHtml = `<div style="font-size:0.75rem; font-style:italic; color:var(--text-muted); margin-top:0.25rem; border-left:2px solid var(--color-primary); padding-left:0.5rem;">${card.outros_detalhes}</div>`;
        }

        const isCreator = window.currentUserId && parseInt(card.usuario_id) === parseInt(window.currentUserId);
        const canEdit = typeof CAN_UPDATE_MANUTENCAO !== 'undefined' ? CAN_UPDATE_MANUTENCAO && isCreator : isCreator;

        cardEl.innerHTML = `
            <div class="k-card-header">
                <span class="k-badge k-badge-turma">${card.predio_campus}</span>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <span style="font-size:0.7rem; color:var(--text-muted);">#${card.id}</span>
                    ${canEdit && card.status !== 'Finalizado' ? `
                        <button type="button" class="card-edit-btn" onclick="event.stopPropagation(); editMaintenanceCard(${card.id})" title="Editar" style="background:none; border:none; cursor:pointer; font-size:0.85rem; padding:0; line-height:1; filter: grayscale(1); transition: filter 0.2s;" onmouseover="this.style.filter='none'" onmouseout="this.style.filter='grayscale(1)'">✏️</button>
                    ` : ''}
                </div>
            </div>
            <div class="k-card-title">${card.descricao}</div>
            <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.3rem;">
                📍 ${card.ambiente_nome}
            </div>
            ${problemasHtml}
            ${outrosHtml}
            <div class="k-card-footer">
                <span class="k-card-date">📅 ${dateStr}</span>
            </div>
        `;
        
        cardEl.addEventListener('dragstart', handleDragStart);
        cardEl.addEventListener('dragend', handleDragEnd);

        colEl.appendChild(cardEl);
    });
}

function initDragAndDrop() {
    const columns = document.querySelectorAll('.kanban-cards');
    columns.forEach(col => {
        col.addEventListener('dragover', handleDragOver);
        col.addEventListener('drop', handleDrop);
    });
}

function handleDragStart(e) {
    draggedCard = this;
    setTimeout(() => this.classList.add('dragging'), 0);
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragEnd() {
    this.classList.remove('dragging');
    draggedCard = null;
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

async function handleDrop(e) {
    e.preventDefault();
    if (!CAN_MOVE_MANUTENCAO) {
        Toast.error('Você não tem permissão para movimentar cards.');
        return;
    }
    if (!draggedCard) return;

    const targetCol = this.closest('.kanban-column');
    const newStatus = targetCol.dataset.status;
    const cardId = draggedCard.dataset.id;

    const oldCol = draggedCard.parentElement;
    this.appendChild(draggedCard);

    try {
        const formData = new FormData();
        formData.append('id', cardId);
        formData.append('status', newStatus);
        formData.append('csrf_token', window.csrfToken);

        const res = await fetch('../api/manutencao/manutencoes_ajax.php?action=update_status', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        
        if (data.success) {
            Toast.success('Status atualizado.');
            loadBoard(); 
        } else {
            Toast.error('Erro: ' + data.message);
            loadBoard(); 
        }
    } catch (err) {
        Toast.error('Erro de conexão.');
        loadBoard();
    }
}

function filterColumn(status, term) {
    const colEl = document.getElementById('col-' + status);
    if (!colEl) return;
    
    const cards = colEl.querySelectorAll('.k-card');
    const query = term.toLowerCase().trim();
    let visibleCount = 0;

    cards.forEach(card => {
        const content = card.innerText.toLowerCase();
        if (content.includes(query)) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const countEl = document.getElementById('count-' + status);
    if (countEl) countEl.innerText = visibleCount;
}

function openModal(id) {
    console.log('Opening modal:', id);
    const m = document.getElementById(id);
    if (m) {
        m.classList.remove('modal-hide');
        m.classList.add('modal-show');
        document.body.style.overflow = 'hidden';
    } else {
        console.error('Modal not found:', id);
    }
}

function closeModal(id) {
    console.log('Closing modal:', id);
    const m = document.getElementById(id);
    if (m) {
        m.classList.remove('modal-show');
        m.classList.add('modal-hide');
        document.body.style.overflow = '';
    }
}

function openNewManutencaoModal() {
    console.log('openNewManutencaoModal triggered');
    const form = document.getElementById('formNewManutencao');
    if (form) form.reset();
    
    const idInput = document.getElementById('newManutencaoId');
    if (idInput) idInput.value = '';

    const modalTitle = document.querySelector('#modalNewManutencao .modal-title');
    if (modalTitle) modalTitle.innerText = 'Nova Solicitação de Manutenção';
    
    const checklist = document.getElementById('problemasChecklist');
    if (checklist) {
        checklist.innerHTML = '<p style="font-size:0.8rem;color:var(--text-muted);text-align:center;padding:1rem;">Selecione um ambiente primeiro.</p>';
    }

    const othersGroup = document.getElementById('groupOutrosDetalhes');
    if (othersGroup) othersGroup.style.display = 'none';
    
    openModal('modalNewManutencao');
}

async function loadAmbienteProblemas(ambienteId) {
    console.log('loadAmbienteProblemas triggered with ID:', ambienteId);
    if (!ambienteId) {
        document.getElementById('problemasChecklist').innerHTML = '<p style="font-size:0.8rem;color:var(--text-muted);text-align:center;padding:1rem;">Selecione um ambiente primeiro.</p>';
        return;
    }

    const container = document.getElementById('problemasChecklist');
    if (!container) {
        console.error('Container problemasChecklist not found');
        return;
    }
    
    container.innerHTML = '<div style="text-align:center;padding:1rem;"><div class="spinner spinner-sm" style="margin:0 auto;"></div></div>';

    try {
        const url = `../api/manutencao/manutencoes_ajax.php?action=get_ambiente_problemas&ambiente_id=${ambienteId}`;
        console.log('Fetching problems from:', url);
        
        const res = await fetch(url);
        const data = await res.json();
        console.log('Problems data received:', data);
        
        if (data.success) {
            let html = '<div class="problemas-grid">';
            
            // Render standard problems
            if (data.problemas && data.problemas.length > 0) {
                data.problemas.forEach(p => {
                    html += `
                        <label class="problema-item">
                            <input type="checkbox" name="problemas[]" value="${p.id}">
                            <span>${p.descricao}</span>
                        </label>
                    `;
                });
            } else {
                console.log('No standard problems found for this environment.');
            }

            // Always add "Outros" at the end
            html += `
                <label class="problema-item" style="border-color: var(--color-primary-light);">
                    <input type="checkbox" id="checkOutros" onchange="toggleOutrosField(this.checked)">
                    <span style="font-weight:bold;">Outros</span>
                </label>
            `;
            
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `<p style="color:red; font-size:0.8rem; text-align:center; padding:1rem;">${data.message || 'Erro ao carregar problemas.'}</p>`;
        }
    } catch (e) {
        console.error('Fetch error in loadAmbienteProblemas:', e);
        container.innerHTML = '<p style="color:red; font-size:0.8rem; text-align:center; padding:1rem;">Erro de conexão ao carregar problemas.</p>';
    }
}

function toggleOutrosField(visible) {
    const group = document.getElementById('groupOutrosDetalhes');
    if (group) {
        group.style.display = visible ? 'block' : 'none';
        if (visible) group.querySelector('textarea').focus();
    }
}

document.getElementById('formNewManutencao').onsubmit = async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('csrf_token', window.csrfToken);

    const id = formData.get('id');
    const action = id ? 'update' : 'create';

    showLoading();
    try {
        const res = await fetch(`../api/manutencao/manutencoes_ajax.php?action=${action}`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        hideLoading();

        if (data.success) {
            Toast.success(id ? 'Manutenção atualizada!' : 'Manutenção criada!');
            closeModal('modalNewManutencao');
            loadBoard();
        } else {
            Toast.error(data.message || 'Erro ao salvar manutenção.');
        }
    } catch (e) {
        hideLoading();
        Toast.error('Erro na requisição.');
    }
};

async function editMaintenanceCard(id) {
    if (!id) return;
    
    showLoading();
    try {
        const res = await fetch(`../api/manutencao/manutencoes_ajax.php?action=get_details&id=${id}`);
        const result = await res.json();
        hideLoading();
        
        if (result.success) {
            const data = result.data;
            
            const form = document.getElementById('formNewManutencao');
            if (form) form.reset();
            
            document.getElementById('newManutencaoId').value = data.id;
            
            const modalTitle = document.querySelector('#modalNewManutencao .modal-title');
            if (modalTitle) modalTitle.innerText = 'Editar Solicitação de Manutenção';
            
            const selectAmbiente = form.querySelector('select[name="ambiente_id"]');
            if (selectAmbiente) {
                selectAmbiente.value = data.ambiente_id;
            }
            
            const textareaDescricao = form.querySelector('textarea[name="descricao"]');
            if (textareaDescricao) {
                textareaDescricao.value = data.descricao;
            }
            
            const inputData = form.querySelector('input[name="data_manutencao"]');
            if (inputData && data.data_manutencao) {
                const dateObj = new Date(data.data_manutencao);
                const tzOffset = dateObj.getTimezoneOffset() * 60000;
                const localISOTime = (new Date(dateObj - tzOffset)).toISOString().slice(0, 16);
                inputData.value = localISOTime;
            }
            
            const container = document.getElementById('problemasChecklist');
            if (container) {
                container.innerHTML = '<div style="text-align:center;padding:1rem;"><div class="spinner spinner-sm" style="margin:0 auto;"></div></div>';
            }
            
            const problemsRes = await fetch(`../api/manutencao/manutencoes_ajax.php?action=get_ambiente_problemas&ambiente_id=${data.ambiente_id}`);
            const problemsData = await problemsRes.json();
            
            if (problemsData.success) {
                let html = '<div class="problemas-grid">';
                const checkedIds = (data.problemas || []).map(p => parseInt(p.id));
                
                if (problemsData.problemas && problemsData.problemas.length > 0) {
                    problemsData.problemas.forEach(p => {
                        const isChecked = checkedIds.includes(parseInt(p.id)) ? 'checked' : '';
                        html += `
                            <label class="problema-item">
                                <input type="checkbox" name="problemas[]" value="${p.id}" ${isChecked}>
                                <span>${p.descricao}</span>
                            </label>
                        `;
                    });
                }
                
                const isOutrosChecked = !!data.outros_detalhes;
                html += `
                    <label class="problema-item" style="border-color: var(--color-primary-light);">
                        <input type="checkbox" id="checkOutros" ${isOutrosChecked ? 'checked' : ''} onchange="toggleOutrosField(this.checked)">
                        <span style="font-weight:bold;">Outros</span>
                    </label>
                `;
                
                html += '</div>';
                container.innerHTML = html;
                
                const othersGroup = document.getElementById('groupOutrosDetalhes');
                const othersTextarea = othersGroup ? othersGroup.querySelector('textarea') : null;
                if (othersGroup && othersTextarea) {
                    othersGroup.style.display = isOutrosChecked ? 'block' : 'none';
                    othersTextarea.value = data.outros_detalhes || '';
                }
            } else {
                container.innerHTML = '<p class="text-muted" style="text-align:center;">Erro ao carregar checklist.</p>';
            }
            
            openModal('modalNewManutencao');
        } else {
            Toast.error(result.message || 'Erro ao carregar manutenção.');
        }
    } catch (e) {
        hideLoading();
        Toast.error('Erro ao buscar manutenção.');
        console.error(e);
    }
}

async function openMaintenanceDetails(id) {
    if (!id) return;
    currentMaintenanceId = id;
    
    showLoading();
    try {
        const res = await fetch(`../api/manutencao/manutencoes_ajax.php?action=get_details&id=${id}`);
        const result = await res.json();
        hideLoading();
        
        if (result.success) {
            const data = result.data;
            
            // Preencher dados fixos no modal
            document.getElementById('detailId').innerText = `#${data.id}`;
            document.getElementById('detailAmbiente').innerText = data.ambiente_nome;
            document.getElementById('detailLocal').innerText = data.predio_campus;
            document.getElementById('detailData').innerText = data.data_manutencao ? new Date(data.data_manutencao).toLocaleString() : 'Não informada';
            document.getElementById('detailDescricao').innerText = data.descricao;
            
            // Status Badge
            const statusBadge = document.getElementById('detailStatusBadge');
            statusBadge.innerText = data.status.toUpperCase();
            statusBadge.className = 'k-badge'; // Reset classes
            
            if (data.status === 'Demandas') statusBadge.classList.add('k-badge-turma');
            else if (data.status === 'Em Aberto') {
                statusBadge.style.background = '#3b82f6';
                statusBadge.style.color = 'white';
            }
            else if (data.status === 'Em Execução') {
                statusBadge.style.background = '#f59e0b';
                statusBadge.style.color = 'white';
            }
            else if (data.status === 'Finalizado') {
                statusBadge.style.background = '#10b981';
                statusBadge.style.color = 'white';
            }

            // Problemas Identificados
            const problemasContainer = document.getElementById('detailProblemas');
            problemasContainer.innerHTML = '';
            
            if (data.problemas && data.problemas.length > 0) {
                data.problemas.forEach(p => {
                    const span = document.createElement('span');
                    span.className = 'problema-item-detail';
                    span.style.background = 'var(--bg-surface-2nd)';
                    span.style.padding = '0.5rem';
                    span.style.borderRadius = 'var(--radius-md)';
                    span.style.fontSize = '0.8125rem';
                    span.style.border = '1px solid var(--border-color)';
                    span.innerText = `✅ ${p.descricao}`;
                    problemasContainer.appendChild(span);
                });
            } else if (!data.outros_detalhes) {
                problemasContainer.innerHTML = '<p class="text-muted" style="font-size:0.8125rem; grid-column: 1/-1;">Nenhum problema específico selecionado.</p>';
            }

            if (data.outros_detalhes) {
                const span = document.createElement('span');
                span.className = 'problema-item-detail';
                span.style.background = 'var(--color-primary-light)';
                span.style.color = 'var(--color-primary)';
                span.style.padding = '0.5rem';
                span.style.borderRadius = 'var(--radius-md)';
                span.style.fontSize = '0.8125rem';
                span.style.border = '1px solid var(--color-primary-light)';
                span.style.fontWeight = '700';
                span.innerText = `➕ ${data.outros_detalhes}`;
                problemasContainer.appendChild(span);
            }

            // Foto de Evidência
            const photoContainer = document.getElementById('detailPhotoContainer');
            const photoImg = document.getElementById('detailPhotoImg');
            if (data.foto) {
                photoImg.src = '/' + data.foto;
                photoContainer.style.display = 'block';
            } else {
                photoContainer.style.display = 'none';
                photoImg.src = '';
            }

            // Resetar abas para a primeira
            const firstTabBtn = document.querySelector('#modalMaintenanceDetails .tab-btn');
            switchMaintenanceTab('detalhes', firstTabBtn);
            
            openModal('modalMaintenanceDetails');
        } else {
            Toast.error(result.message || 'Erro ao carregar detalhes.');
        }
    } catch (e) {
        hideLoading();
        Toast.error('Erro de conexão ao buscar detalhes.');
        console.error(e);
    }
}

async function deleteMaintenance() {
    if (!currentMaintenanceId) return;

    if (!confirm('Tem certeza que deseja excluir esta manutenção permanentemente? Esta ação não pode ser desfeita.')) {
        return;
    }

    showLoading();
    try {
        const formData = new FormData();
        formData.append('id', currentMaintenanceId);
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

        const res = await fetch('../api/manutencao/manutencoes_ajax.php?action=delete', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await res.json();
        hideLoading();

        if (result.success) {
            Toast.success('Manutenção excluída com sucesso!');
            closeModal('modalMaintenanceDetails');
            loadBoard(); // Recarrega o quadro
        } else {
            Toast.error(result.message || 'Erro ao excluir manutenção.');
        }
    } catch (e) {
        hideLoading();
        Toast.error('Erro de conexão ao excluir manutenção.');
    }
}

function switchMaintenanceTab(tabName, btn) {
    const modal = document.getElementById('modalMaintenanceDetails');
    // Esconder todos os conteúdos de aba
    modal.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    // Remover classe ativa de todos os botões
    modal.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    
    // Mostrar aba selecionada
    const targetTab = document.getElementById(`tab-${tabName}`);
    if (targetTab) targetTab.classList.add('active');
    
    // Ativar botão selecionado
    if (btn) btn.classList.add('active');

    // Se for a aba de comentários, carrega o feed
    if (tabName === 'comentarios') {
        loadComments(currentMaintenanceId);
    }

    // Se for a aba de materiais, carrega a lista
    if (tabName === 'materiais') {
        loadMaterials(currentMaintenanceId);
    }
}

async function loadMaterials(id) {
    const list = document.getElementById('materialsList');
    const totalEl = document.getElementById('matTotalValue');
    if (!list) return;

    try {
        const res = await fetch(`../api/manutencao/manutencoes_ajax.php?action=list_materials&id=${id}`);
        const data = await res.json();

        if (data.success) {
            if (data.materials && data.materials.length > 0) {
                let html = '';
                let total = 0;
                data.materials.forEach(m => {
                    const valor = parseFloat(m.valor);
                    total += valor;
                    const valorFormatado = valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                    
                    html += `
                        <div class="material-item">
                            <div class="material-info">
                                <span class="material-label">Material</span>
                                <strong>${m.descricao}</strong>
                            </div>
                            <div class="material-info">
                                <span class="material-label">Local de Compra</span>
                                <span>${m.local_compra || '---'}</span>
                            </div>
                            <div class="material-info">
                                <span class="material-label">Valor</span>
                                <span class="material-value-text">${valorFormatado}</span>
                            </div>
                            ${CAN_MATERIAL_MANUTENCAO ? `
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteMaterial(${m.id})" title="Remover">
                                🗑️
                            </button>
                            ` : ''}
                        </div>
                    `;
                });
                list.innerHTML = html;
                totalEl.innerText = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            } else {
                list.innerHTML = '<p class="text-muted" style="text-align: center; padding: 2rem;">Nenhum material registrado.</p>';
                totalEl.innerText = 'R$ 0,00';
            }
        }
    } catch (e) {
        list.innerHTML = '<p style="color:red; text-align:center;">Erro ao carregar materiais.</p>';
    }
}

async function submitMaterial() {
    const descEl = document.getElementById('matDescricao');
    const localEl = document.getElementById('matLocal');
    const valorEl = document.getElementById('matValor');

    const descricao = descEl.value.trim();
    if (!descricao) {
        Toast.warning('A descrição do material é obrigatória.');
        return;
    }

    showLoading();
    try {
        const formData = new FormData();
        formData.append('id', currentMaintenanceId);
        formData.append('descricao', descricao);
        formData.append('local_compra', localEl.value.trim());
        formData.append('valor', valorEl.value);
        formData.append('csrf_token', window.csrfToken);

        const res = await fetch('../api/manutencao/manutencoes_ajax.php?action=add_material', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        hideLoading();

        if (data.success) {
            Toast.success('Material registrado!');
            descEl.value = '';
            localEl.value = '';
            valorEl.value = '';
            loadMaterials(currentMaintenanceId);
        } else {
            Toast.error(data.message || 'Erro ao registrar material.');
        }
    } catch (e) {
        hideLoading();
        Toast.error('Erro de conexão.');
    }
}

async function deleteMaterial(materialId) {
    if (!confirm('Tem certeza que deseja remover este material?')) return;

    showLoading();
    try {
        const formData = new FormData();
        formData.append('id', materialId);
        formData.append('csrf_token', window.csrfToken);

        const res = await fetch('../api/manutencao/manutencoes_ajax.php?action=delete_material', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        hideLoading();

        if (data.success) {
            Toast.success('Material removido.');
            loadMaterials(currentMaintenanceId);
        } else {
            Toast.error(data.message || 'Erro ao remover material.');
        }
    } catch (e) {
        hideLoading();
        Toast.error('Erro de conexão.');
    }
}

async function loadComments(id) {
    const feed = document.getElementById('commentsFeed');
    if (!feed) return;

    try {
        const res = await fetch(`../api/manutencao/manutencoes_ajax.php?action=list_comments&id=${id}`);
        const data = await res.json();

        if (data.success) {
            if (data.comments && data.comments.length > 0) {
                let html = '';
                data.comments.forEach(c => {
                    const date = new Date(c.created_at).toLocaleString();
                    html += `
                        <div class="comment-item">
                            <div class="comment-meta">
                                <span class="comment-user">👤 ${c.user_name}</span>
                                <span class="comment-date">${date}</span>
                            </div>
                            <div class="comment-text">${c.comentario}</div>
                        </div>
                    `;
                });
                feed.innerHTML = html;
            } else {
                feed.innerHTML = '<p class="text-muted" style="text-align: center; padding: 2rem;">Nenhum comentário registrado ainda.</p>';
            }
        } else {
            feed.innerHTML = `<p style="color:red; text-align:center;">${data.message}</p>`;
        }
    } catch (e) {
        feed.innerHTML = '<p style="color:red; text-align:center;">Erro ao carregar comentários.</p>';
    }
}

async function submitComment() {
    const textEl = document.getElementById('newCommentText');
    const comment = textEl.value.trim();
    if (!comment) {
        Toast.warning('Escreva um comentário primeiro.');
        return;
    }

    showLoading();
    try {
        const formData = new FormData();
        formData.append('id', currentMaintenanceId);
        formData.append('comentario', comment);
        formData.append('csrf_token', window.csrfToken);

        const res = await fetch('../api/manutencao/manutencoes_ajax.php?action=add_comment', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        hideLoading();

        if (data.success) {
            Toast.success('Comentário enviado!');
            textEl.value = '';
            loadComments(currentMaintenanceId);
        } else {
            Toast.error(data.message || 'Erro ao enviar comentário.');
        }
    } catch (e) {
        hideLoading();
        Toast.error('Erro de conexão.');
    }
}

function openPhotoPreview() {
    const thumb = document.getElementById('detailPhotoImg');
    const full = document.getElementById('photoPreviewFull');
    const downloadBtn = document.getElementById('btnDownloadPhoto');
    if (thumb && thumb.src) {
        full.src = thumb.src;
        if (downloadBtn) downloadBtn.href = thumb.src;
        openModal('modalPhotoPreview');
    }
}
