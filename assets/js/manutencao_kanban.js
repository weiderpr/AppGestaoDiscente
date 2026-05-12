/**
 * Vértice Acadêmico — Kanban Manutenções Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    loadBoard();
    initDragAndDrop();
});

let draggedCard = null;

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
        cardEl.draggable = true;
        cardEl.dataset.id = card.id;
        
        cardEl.onclick = (e) => {
            openMaintenanceDetails(card.id);
        };

        let outrosHtml = '';
        if (card.outros_detalhes) {
            outrosHtml = `<div style="font-size:0.75rem; font-style:italic; color:var(--text-muted); margin-top:0.25rem; border-left:2px solid var(--color-primary); padding-left:0.5rem;">${card.outros_detalhes}</div>`;
        }

        cardEl.innerHTML = `
            <div class="k-card-header">
                <span class="k-badge k-badge-turma">${card.predio_campus}</span>
                <span style="font-size:0.7rem; color:var(--text-muted);">#${card.id}</span>
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

    showLoading();
    try {
        const res = await fetch('../api/manutencao/manutencoes_ajax.php?action=create', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        hideLoading();

        if (data.success) {
            Toast.success('Manutenção criada!');
            closeModal('modalNewManutencao');
            loadBoard();
        } else {
            Toast.error(data.message || 'Erro ao criar manutenção.');
        }
    } catch (e) {
        hideLoading();
        Toast.error('Erro na requisição.');
    }
};

async function openMaintenanceDetails(id) {
    if (!id) return;
    
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
}
