/**
 * Vértice Acadêmico — Kanban Atendimentos Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    loadBoard();
    initDragAndDrop();
});

let draggedCard = null;
let currentAtendimentoId = null;
let currentIsArchived = false;
let showArchived = false;

function handleArchiveToggle() {
    showArchived = document.getElementById('toggleShowArchived').checked;
    loadBoard();
}

// --- Board Operations ---

async function loadBoard() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
        const res = await fetch('/api/atendimentos.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: `action=fetch_board&show_archived=${showArchived}&csrf_token=` + encodeURIComponent(csrfToken)
        });
        const data = await res.json();
        
        if (data.success) {
            renderColumn('Demandas', data.board['Demandas'] || []);
            renderColumn('Aberto', data.board['Aberto'] || []);
            renderColumn('Em Atendimento', data.board['Em Atendimento'] || []);
            renderColumn('Finalizado', data.board['Finalizado'] || []);
            
            // Clear all filters on reload
            document.querySelectorAll('.column-filter').forEach(input => input.value = '');
        } else {
            Toast.show('Erro ao carregar quadro: ' + data.error, 'error');
        }
    } catch (e) {
        Toast.show('Erro de conexão ao carregar quadro.', 'error');
    }
}

function renderColumn(status, cards) {
    const colEl = document.getElementById('col-' + status);
    const countEl = document.getElementById('count-' + status);
    
    if (!colEl) return;
    
    colEl.innerHTML = '';
    countEl.innerText = cards.length;

    cards.forEach(card => {
        const badgeClass = card.aluno_id ? 'k-badge-aluno' : (card.turma_id ? 'k-badge-turma' : 'k-badge-encaminhamento');
        const badgeText = card.aluno_id ? 'Aluno' : (card.turma_id ? 'Turma' : 'Geral');
        
        let responsaveisHtml = '';
        if (card.responsaveis && card.responsaveis.length > 0) {
            responsaveisHtml = '<div class="k-card-users">';
            card.responsaveis.slice(0, 3).forEach(r => {
                if (r.photo) {
                    responsaveisHtml += `<img src="/${r.photo}" class="k-card-user" title="${r.name}">`;
                } else {
                    const ini = r.name.substring(0, 1).toUpperCase();
                    responsaveisHtml += `<div class="k-card-user" title="${r.name}">${ini}</div>`;
                }
            });
            if (card.responsaveis.length > 3) {
                responsaveisHtml += `<div class="k-card-user" style="font-size:0.5rem;">+${card.responsaveis.length - 3}</div>`;
            }
            responsaveisHtml += '</div>';
        }

        const dateStr = card.data ? new Date(card.data).toLocaleDateString() : '';

        const cardEl = document.createElement('div');
        cardEl.className = 'k-card' + (card.is_archived ? ' archived' : '');
        if (card.is_archived) {
            cardEl.style.opacity = '0.6';
            cardEl.style.borderStyle = 'dashed';
        }
        cardEl.draggable = true;
        cardEl.dataset.id = card.id;
        cardEl.dataset.is_encaminhamento = card.is_encaminhamento ? 'true' : 'false';
        
        // Clicar no card abre detalhes, mas n pode ser o Demandas direto agor a menos q tenhamos um modal view pra Demandas puro.
        cardEl.onclick = (e) => {
            openCardDetails(card.id);
        };

        cardEl.innerHTML = `
            <div class="k-card-header">
                <span class="k-badge ${badgeClass}">${badgeText}</span>
                ${card.aluno_id ? (card.aluno_photo ? `<img src="/${card.aluno_photo}" class="k-card-student">` : `<div class="k-card-student">${card.aluno_nome.charAt(0)}</div>`) : ''}
            </div>
            <div class="k-card-title">${card.titulo}</div>
            <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.5rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                ${card.aluno_nome || card.turma_nome || ''}
            </div>
            <div class="k-card-footer">
                <span class="k-card-date">${dateStr}</span>
                ${responsaveisHtml}
            </div>
        `;
        
        // Drag Events
        cardEl.addEventListener('dragstart', handleDragStart);
        cardEl.addEventListener('dragend', handleDragEnd);

        colEl.appendChild(cardEl);
    });
}

// --- Drag and Drop ---

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
    // Se quiser transferir ID: e.dataTransfer.setData('text/plain', this.dataset.id);
}

function handleDragEnd() {
    this.classList.remove('dragging');
    draggedCard = null;
}

function handleDragOver(e) {
    e.preventDefault(); // Necessary to allow dropping
    e.dataTransfer.dropEffect = 'move';
    
    // Optional: visual feedback on column
    // const afterElement = getDragAfterElement(this, e.clientY);
}

async function handleDrop(e) {
    e.preventDefault();
    if (!draggedCard) return;

    const targetCol = this.closest('.kanban-column');
    const newStatus = targetCol.dataset.status;
    const cardId = draggedCard.dataset.id;
    const isEnc = draggedCard.dataset.is_encaminhamento === 'true';

    // Rule: Demandas column is source only (for now)
    if (newStatus === 'Demandas') {
        Toast.show('Você não pode mover cards de volta para Demandas.', 'warning');
        return;
    }

    // Move in DOM immediately for snappy feel
    this.appendChild(draggedCard);

    // Call API
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const formData = new URLSearchParams();
        formData.append('action', 'update_status');
        formData.append('card_id', cardId);
        formData.append('new_status', newStatus);
        formData.append('csrf_token', csrfToken);

        const res = await fetch('/api/atendimentos.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData
        });
        const data = await res.json();
        
        if (data.success) {
            Toast.show('Status atualizado com sucesso.', 'success');
            // Recarregar pra garantir a mudança de ID (se veio de demanda) e atualizar contadores
            loadBoard(); 
        } else {
            Toast.show('Erro: ' + data.error, 'error');
            loadBoard(); // revert
        }
    } catch (err) {
        Toast.show('Erro de conexão.', 'error');
        loadBoard();
    }
}

// --- Modals & Autocomplete ---

function toggleVinculoType() {
    const type = document.getElementById('tipoVinculo').value;
    document.getElementById('vinculoAlunoGroup').style.display = type === 'aluno' ? 'block' : 'none';
    document.getElementById('vinculoTurmaGroup').style.display = type === 'turma' ? 'block' : 'none';
}

function openNewAtendimentoModal() {
    document.getElementById('formNewAtendimento').reset();
    clearAlunoSelection();
    clearTurmaSelection();
    toggleVinculoType();
    openModal('modalNewAtendimento');
}

// Search Alunos
let timeoutAluno = null;
function debounceSearchAluno(query) {
    if (timeoutAluno) clearTimeout(timeoutAluno);
    const resEl = document.getElementById('searchAlunoResults');
    if (query.length < 3) {
        resEl.style.display = 'none';
        return;
    }
    timeoutAluno = setTimeout(async () => {
        try {
            const res = await fetch('/api/atendimentos.php?action=search_alunos&q=' + encodeURIComponent(query));
            const data = await res.json();
            if (data.success) {
                if (data.alunos.length === 0) {
                    resEl.innerHTML = '<div style="padding:0.75rem; font-size:0.875rem; color:var(--text-muted);">Nenhum aluno encontrado.</div>';
                } else {
                    let h = '';
                    data.alunos.forEach(a => {
                        const matr = a.matricula ? `(${a.matricula})` : '';
                        h += `<div style="padding:0.6rem 0.75rem; font-size:0.875rem; cursor:pointer; border-bottom:1px solid var(--border-color);" onmouseover="this.style.background='var(--bg-surface-2nd)'" onmouseout="this.style.background='transparent'" onclick="selectAluno(${a.id}, '${a.nome}')">${a.nome} ${matr}</div>`;
                    });
                    resEl.innerHTML = h;
                }
                resEl.style.display = 'block';
            }
        } catch(e) {}
    }, 400);
}

function selectAluno(id, name) {
    if (timeoutAluno) clearTimeout(timeoutAluno);
    const resEl = document.getElementById('searchAlunoResults');
    resEl.innerHTML = '';
    resEl.style.display = 'none';

    document.getElementById('inputAlunoId').value = id;
    document.getElementById('searchAlunoInput').style.display = 'none';
    document.getElementById('selectedAlunoName').innerText = name;
    document.getElementById('selectedAlunoInfo').style.display = 'block';
}

function clearAlunoSelection() {
    document.getElementById('inputAlunoId').value = '';
    document.getElementById('searchAlunoInput').value = '';
    document.getElementById('searchAlunoInput').style.display = 'block';
    document.getElementById('selectedAlunoInfo').style.display = 'none';
}

// Search Turmas
let timeoutTurma = null;
function debounceSearchTurma(query) {
    if (timeoutTurma) clearTimeout(timeoutTurma);
    const resEl = document.getElementById('searchTurmaResults');
    if (query.length < 3) {
        resEl.style.display = 'none';
        return;
    }
    timeoutTurma = setTimeout(async () => {
        try {
            const res = await fetch('/api/atendimentos.php?action=search_turmas&q=' + encodeURIComponent(query));
            const data = await res.json();
            if (data.success) {
                if (data.turmas.length === 0) {
                    resEl.innerHTML = '<div style="padding:0.75rem; font-size:0.875rem; color:var(--text-muted);">Nenhuma turma encontrada.</div>';
                } else {
                    let h = '';
                    data.turmas.forEach(t => {
                        h += `<div style="padding:0.6rem 0.75rem; font-size:0.875rem; cursor:pointer; border-bottom:1px solid var(--border-color);" onmouseover="this.style.background='var(--bg-surface-2nd)'" onmouseout="this.style.background='transparent'" onclick="selectTurma(${t.id}, '${t.nome} - ${t.course_name}')">${t.nome} <small style="color:var(--text-muted);display:block;">${t.course_name}</small></div>`;
                    });
                    resEl.innerHTML = h;
                }
                resEl.style.display = 'block';
            }
        } catch(e) {}
    }, 400);
}

function selectTurma(id, name) {
    if (timeoutTurma) clearTimeout(timeoutTurma);
    const resEl = document.getElementById('searchTurmaResults');
    resEl.innerHTML = '';
    resEl.style.display = 'none';

    document.getElementById('inputTurmaId').value = id;
    document.getElementById('searchTurmaInput').style.display = 'none';
    document.getElementById('selectedTurmaName').innerText = name;
    document.getElementById('selectedTurmaInfo').style.display = 'block';
}

function clearTurmaSelection() {
    document.getElementById('inputTurmaId').value = '';
    document.getElementById('searchTurmaInput').value = '';
    document.getElementById('searchTurmaInput').style.display = 'block';
    document.getElementById('selectedTurmaInfo').style.display = 'none';
}

async function submitNewAtendimento() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const form = document.getElementById('formNewAtendimento');
    const formData = new FormData(form);
    formData.append('action', 'create_atendimento');
    formData.append('csrf_token', csrfToken);

    try {
        const res = await fetch('/api/atendimentos.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            Toast.show('Atendimento criado!', 'success');
            closeModal('modalNewAtendimento');
            loadBoard();
        } else {
            Toast.show(data.error, 'error');
        }
    } catch (e) {
        Toast.show('Erro de conexão', 'error');
    }
}

// --- Card Details (Timeline) ---

async function openCardDetails(id) {
    currentAtendimentoId = id;

    try {
        const res = await fetch('/api/atendimentos.php?action=get_details&id=' + id);
        const data = await res.json();
        
        if (data.success) {
            const at = data.atendimento;
            
            document.getElementById('cdMainTitle').innerText = at.titulo;
            document.getElementById('cdBadgeStatus').innerText = at.status;
            
            const tipoBadgeEl = document.getElementById('cdTipoBadge');
            if (tipoBadgeEl) {
                if (at.aluno_id) {
                    tipoBadgeEl.innerText = 'Aluno';
                    tipoBadgeEl.className = 'k-badge k-badge-aluno';
                } else if (at.turma_id) {
                    tipoBadgeEl.innerText = 'Turma';
                    tipoBadgeEl.className = 'k-badge k-badge-turma';
                } else if (at.encaminhamento_id) {
                    tipoBadgeEl.innerText = 'Encaminhamento';
                    tipoBadgeEl.className = 'k-badge k-badge-encaminhamento';
                } else {
                    tipoBadgeEl.innerText = 'Geral';
                    tipoBadgeEl.className = 'k-badge';
                }
            }
            
            currentIsArchived = !!at.is_archived;
            
            const archiveText = document.getElementById('archiveText');
            const archiveIcon = document.getElementById('archiveIcon');
            if (archiveText) {
                archiveText.innerText = currentIsArchived ? 'Desarquivar Card' : 'Arquivar Card';
                archiveIcon.innerText = currentIsArchived ? '♻️' : '📦';
            }
            
            const photoEl = document.getElementById('cdAlunoPhoto');
            const avatarEl = document.getElementById('cdAlunoAvatar');
            const subtitleEl = document.getElementById('cdAlunoSubtitle');
            
            if (at.aluno_id) {
                subtitleEl.innerText = at.aluno_nome + (at.matricula ? ' (#' + at.matricula + ')' : '') + (at.curso_nome ? ' • ' + at.curso_nome : '') + (at.turma_nome ? ' — ' + at.turma_nome : '');
                
                if (at.aluno_photo) {
                    photoEl.src = '/' + at.aluno_photo;
                    photoEl.style.display = 'block';
                    avatarEl.style.display = 'none';
                } else {
                    photoEl.style.display = 'none';
                    avatarEl.style.display = 'flex';
                    const initials = at.aluno_nome.split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase();
                    avatarEl.innerText = initials;
                }
            } else if (at.turma_id) {
                subtitleEl.innerText = 'Turma: ' + (at.turma_nome || 'Não identificada');
                photoEl.style.display = 'none';
                avatarEl.style.display = 'flex';
                avatarEl.innerText = '👥';
            } else {
                subtitleEl.innerText = 'Atendimento Geral';
                photoEl.style.display = 'none';
                avatarEl.style.display = 'flex';
                avatarEl.innerText = '📄';
            }

            const demandContext = document.getElementById('cdDemandaContext');
            const editorSec = document.getElementById('cdEditorSection');
            const timelineSec = document.getElementById('cdTimelineSection');
            const profSec = document.getElementById('cdProfessionalsSection');
            const deleteSec = document.getElementById('cdDeleteSection');

            if (at.is_encaminhamento_pure) {
                demandContext.style.display = 'block';
                editorSec.style.display = 'none';
                timelineSec.style.display = 'none';
                profSec.style.display = 'none';
                deleteSec.style.display = 'none';

                document.getElementById('cdCouncilName').innerText = at.conselho_nome || 'Conselho de Classe';
                document.getElementById('cdDemandText').innerText = at.texto || 'Sem descrição adicional.';
                document.getElementById('cdDeadlineValue').innerText = at.data_expectativa ? new Date(at.data_expectativa + 'T00:00:00').toLocaleDateString() : 'Não definido';
            } else {
                demandContext.style.display = 'none';
                editorSec.style.display = 'block';
                timelineSec.style.display = 'block';
                profSec.style.display = 'block';
                deleteSec.style.display = 'block';

                // Render using shared logic
                populateAtendimentoModal(data, { isRestricted: false });
            }
            
            document.getElementById('userSearchResults').innerHTML = '';
            document.getElementById('userSearch').value = '';

            openModal('modalCardDetails');
        } else {
            showToast(data.error, 'error');
        }
    } catch (e) {
        showToast('Erro ao carregar detalhes', 'error');
    }
}

async function saveAtendimentoInfo() {
    if (!currentAtendimentoId) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const dp = document.getElementById('cdDescPublica').value;
    const dprof = document.getElementById('cdDescProfissional').value;

    const fd = new URLSearchParams();
    fd.append('action', 'save_info');
    fd.append('atendimento_id', currentAtendimentoId);
    fd.append('descricao_publica', dp);
    fd.append('descricao_profissional', dprof);
    fd.append('csrf_token', csrfToken);

    try {
        const res = await fetch('/api/atendimentos.php', { 
            method: 'POST', 
            headers: { 'X-CSRF-Token': csrfToken },
            body: fd 
        });
        const data = await res.json();
        if (data.success) Toast.show('Informações salvas', 'success');
        else Toast.show('Erro: ' + data.error, 'error');
    } catch (e) {
        Toast.show('Erro de conexão', 'error');
    }
}

async function submitComment() {
    if (!currentAtendimentoId) return;

    const txt = document.getElementById('ncTexto').value.trim();
    if (!txt) {
        Toast.show('Digite um comentário.', 'info');
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const isPriv = document.getElementById('ncPrivate').checked ? 1 : 0;
    
    const fd = new URLSearchParams();
    fd.append('action', 'save_comment');
    fd.append('atendimento_id', currentAtendimentoId);
    fd.append('texto', txt);
    fd.append('is_private', isPriv);
    fd.append('csrf_token', csrfToken);

    try {
        const res = await fetch('/api/atendimentos.php', { 
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken }, 
            body: fd 
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('ncTexto').value = '';
            document.getElementById('ncPrivate').checked = false;
            Toast.show('Comentário publicado', 'success');
            openCardDetails(currentAtendimentoId); // reload timeline
        } else {
            Toast.show('Erro: ' + data.error, 'error');
        }
    } catch(e) {
        Toast.show('Erro de conexão', 'error');
    }
}

// Redundant functions removed, using assets/js/atendimento_shared.js

async function searchUsers(q) {
    const resEl = document.getElementById('userSearchResults');
    if (q.length < 3) {
        resEl.innerHTML = '';
        return;
    }

    try {
        const res = await fetch('/api/atendimentos.php?action=search_users&q=' + encodeURIComponent(q));
        const data = await res.json();
        if (data.success) {
            if (data.users.length === 0) {
                resEl.innerHTML = '<div style="font-size:0.75rem;padding:0.5rem;">Nenhum usuário encontrado.</div>';
                return;
            }
            let h = '';
            data.users.forEach(u => {
                h += `
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem;background:var(--bg-card);border:1px solid var(--border-color);border-radius:6px;cursor:pointer;font-size:0.875rem;" onclick="addResponsible(${u.id})">
                        <span>${u.name} (<small>${u.profile}</small>)</span>
                        <span style="color:var(--color-primary);font-weight:bold;">+</span>
                    </div>
                `;
            });
            resEl.innerHTML = h;
        }
    } catch (e) {
        console.error(e);
    }
}

async function addResponsible(userId) {
    if (!currentAtendimentoId) return;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const fd = new URLSearchParams();
    fd.append('action', 'add_responsible');
    fd.append('atendimento_id', currentAtendimentoId);
    fd.append('usuario_id', userId);
    fd.append('csrf_token', csrfToken);

    try {
        const res = await fetch('/api/atendimentos.php', { 
            method: 'POST', 
            headers: { 'X-CSRF-Token': csrfToken },
            body: fd 
        });
        const data = await res.json();
        if (data.success) {
            Toast.show('Responsável adicionado', 'success');
            openCardDetails(currentAtendimentoId); // recarrega
            loadBoard(); // pra atualizar as fotinhos do card no board
        }
    } catch(e) {}
}

async function removeResponsible(userId) {
    if (!currentAtendimentoId) return;

    Modal.confirm({
        title: 'Remover Responsável',
        message: 'Deseja remover este profissional do acompanhamento?',
        confirmText: 'Remover',
        confirmClass: 'btn-danger',
        onConfirm: async () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const fd = new URLSearchParams();
            fd.append('action', 'remove_responsible');
            fd.append('atendimento_id', currentAtendimentoId);
            fd.append('usuario_id', userId);
            fd.append('csrf_token', csrfToken);

            try {
                const res = await fetch('/api/atendimentos.php', { 
                    method: 'POST', 
                    headers: { 'X-CSRF-Token': csrfToken },
                    body: fd 
                });
                const data = await res.json();
                if (data.success) {
                    Toast.show('Responsável removido', 'success');
                    openCardDetails(currentAtendimentoId); 
                    loadBoard(); 
                }
            } catch(e) {
                console.error(e);
            }
        }
    });
}

async function deleteAtendimento() {
    if (!currentAtendimentoId) return;

    Modal.confirm({
        title: 'Excluir Atendimento',
        message: 'Tem certeza que deseja excluir permanentemente este atendimento? <br><br><small>Se ele veio de uma Demanda, ele retornará para a coluna de Pendentes.</small>',
        confirmText: 'Excluir permanentemente',
        confirmClass: 'btn-danger',
        onConfirm: async () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const fd = new URLSearchParams();
            fd.append('action', 'delete_atendimento');
            fd.append('atendimento_id', currentAtendimentoId);
            fd.append('csrf_token', csrfToken);

            try {
                const res = await fetch('/api/atendimentos.php', { 
                    method: 'POST', 
                    headers: { 'X-CSRF-Token': csrfToken },
                    body: fd 
                });
                const data = await res.json();
                
                if (data.success) {
                    Toast.show('Card excluído com sucesso.', 'success');
                    closeModal('modalCardDetails');
                    loadBoard();
                } else {
                    Toast.show('Erro ao excluir: ' + data.error, 'error');
                }
            } catch (e) {
                Toast.show('Erro de conexão.', 'error');
            }
        }
    });
}

window.archiveAtendimentoToggle = function() {
    console.log('Toggle archive triggered', {currentAtendimentoId, currentIsArchived});
    archiveAtendimento(!currentIsArchived);
};

async function archiveAtendimento(isArchiving = true) {
    if (!currentAtendimentoId) return;

    const actionText = isArchiving ? 'arquivar' : 'desarquivar';
    
    Modal.confirm({
        title: isArchiving ? 'Arquivar Atendimento' : 'Desarquivar Atendimento',
        message: `Deseja realmente ${actionText} este atendimento?`,
        confirmText: isArchiving ? 'Confirmar' : 'Confirmar',
        onConfirm: async () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const fd = new URLSearchParams();
            fd.append('action', 'archive_atendimento');
            fd.append('atendimento_id', currentAtendimentoId);
            fd.append('archive', isArchiving ? 1 : 0);
            fd.append('csrf_token', csrfToken);

            try {
                const res = await fetch('/api/atendimentos.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken },
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    Toast.show(data.message, 'success');
                    closeModal('modalCardDetails');
                    loadBoard();
                } else {
                    Toast.show(data.error, 'error');
                }
            } catch (e) {
                Toast.show('Erro de conexão', 'error');
            }
        }
    });
}

function filterColumn(status, term) {
    const colEl = document.getElementById('col-' + status);
    if (!colEl) return;
    
    const cards = colEl.querySelectorAll('.k-card');
    const query = term.toLowerCase().trim();
    let visibleCount = 0;

    cards.forEach(card => {
        const title = card.querySelector('.k-card-title').innerText.toLowerCase();
        // The subtitle is the div with font-size:0.8rem
        const subtitle = card.querySelector('div[style*="font-size:0.8rem"]').innerText.toLowerCase();
        
        if (title.includes(query) || subtitle.includes(query)) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const countEl = document.getElementById('count-' + status);
    if (countEl) {
        countEl.innerText = visibleCount;
    }
}
