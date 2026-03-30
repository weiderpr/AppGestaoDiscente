/**
 * Vértice Acadêmico — Lógica Compartilhada de Detalhes de Atendimento
 */

/**
 * Renderiza a timeline de comentários
 */
function renderTimeline(comentarios, isRestricted = false) {
    const tl = document.getElementById('cdTimeline');
    if (!tl) return;
    
    if (comentarios.length === 0) {
        tl.innerHTML = '<div style="color:var(--text-muted); font-size:0.875rem;">Nenhum registro ainda.</div>';
        return;
    }

    let h = '';
    comentarios.forEach(c => {
        const dateStr = new Date(c.created_at).toLocaleString();
        const isPrivate = c.is_private == 1;
        const privBadge = isPrivate ? '<span class="c-private-badge">🔒 Privado</span>' : '';
        
        // No Conselho (Restricted), comentários privados aparecem borrados
        let blurClass = (isRestricted && isPrivate) ? 'privacy-blur' : '';
        let blurAttr = '';
        let blurOverlay = '';

        if (isRestricted && isPrivate) {
            const isAdmin = typeof currentUserProfile !== 'undefined' && ['Administrador', 'Coordenador'].includes(currentUserProfile);
            const isOwner = typeof currentUserId !== 'undefined' && c.author_id == currentUserId;
            const canReveal = isAdmin || isOwner;
            
            blurAttr = canReveal ? 'onclick="this.classList.add(\'revealed\')"' : 'onclick="if(typeof Toast !== \'undefined\') Toast.show(\'Acesso restrito ao profissional que registrou ou coordenação.\', \'warning\');"';
            blurOverlay = '<div class="privacy-overlay">⚠️ Registro Privado. Clique para revelar.</div>';
        }

        h += `
            <div class="comment-item">
                <div class="comment-header">
                    <strong>${c.author_name}</strong>
                    <span>${dateStr} ${privBadge}</span>
                </div>
                <div class="${blurClass}" ${blurAttr} style="position:relative;">
                    ${blurOverlay}
                    ${c.texto.replace(/\n/g, '<br>')}
                </div>
            </div>
        `;
    });
    tl.innerHTML = h;
}

/**
 * Renderiza a lista de responsáveis
 */
function renderResponsaveis(resp, canEdit = true) {
    const list = document.getElementById('cdResponsaveisList');
    if (!list) return;
    
    if (resp.length === 0) {
        list.innerHTML = '<span style="font-size:0.8rem;color:var(--text-muted);">Nenhum responsável</span>';
        return;
    }
    
    let h = '';
    resp.forEach(r => {
        const imgHtml = r.photo ? `<img src="/${r.photo}" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">` 
                                : `<div style="width:24px;height:24px;border-radius:50%;background:var(--bg-surface);display:flex;align-items:center;justify-content:center;font-size:0.6rem;font-weight:bold;">${r.name.charAt(0)}</div>`;
        
        const deleteBtn = canEdit ? `<button onclick="removeResponsible(${r.id})" style="background:transparent;border:none;color:#ef4444;cursor:pointer;padding:4px;font-size:1rem;" title="Remover">×</button>` : '';

        h += `
            <div style="display:flex;justify-content:space-between;align-items:center;background:var(--bg-card);padding:0.5rem;border-radius:8px;border:1px solid var(--border-color);font-size:0.875rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    ${imgHtml}
                    <div>
                        <div style="font-weight:600;line-height:1.2;">${r.name}</div>
                        <div style="font-size:0.65rem;color:var(--text-muted);">${r.profile}</div>
                    </div>
                </div>
                ${deleteBtn}
            </div>
        `;
    });
    list.innerHTML = h;
}

/**
 * Preenche o modal de detalhes com os dados recebidos
 */
function populateAtendimentoModal(data, options = {}) {
    const at = data.atendimento;
    const isRestricted = options.isRestricted || false;
    
    const titleEl = document.getElementById('cdMainTitle');
    if (titleEl) titleEl.innerText = at.titulo;
    
    const statusEl = document.getElementById('cdBadgeStatus');
    if (statusEl) statusEl.innerText = at.status;
    
    const photoEl = document.getElementById('cdAlunoPhoto');
    const avatarEl = document.getElementById('cdAlunoAvatar');
    const subtitleEl = document.getElementById('cdAlunoSubtitle');
    
    if (at.aluno_id) {
        if (subtitleEl) subtitleEl.innerText = at.aluno_nome + (at.matricula ? ' (#' + at.matricula + ')' : '') + (at.curso_nome ? ' • ' + at.curso_nome : '') + (at.turma_nome ? ' — ' + at.turma_nome : '');
        
        if (photoEl && avatarEl) {
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
        }
    } else if (at.turma_id) {
        if (subtitleEl) subtitleEl.innerText = 'Turma: ' + (at.turma_nome || 'Não identificada');
        if (photoEl) photoEl.style.display = 'none';
        if (avatarEl) {
            avatarEl.style.display = 'flex';
            avatarEl.innerText = '👥';
        }
    }

    const demandContext = document.getElementById('cdDemandaContext');
    const editorSec = document.getElementById('cdEditorSection');
    const timelineSec = document.getElementById('cdTimelineSection');
    const profSec = document.getElementById('cdProfessionalsSection');
    const deleteSec = document.getElementById('cdDeleteSection');

    // Contexto de Demanda sempre visível se houver
    if (at.encaminhamento_id || at.is_encaminhamento_pure) {
        if (demandContext) {
            demandContext.style.display = 'block';
            document.getElementById('cdCouncilName').innerText = at.conselho_nome || 'Encaminhamento Original';
            document.getElementById('cdDemandText').innerText = at.texto || at.encaminhamento_texto || 'Sem descrição adicional.';
            document.getElementById('cdDeadlineValue').innerText = at.data_expectativa ? new Date(at.data_expectativa + 'T00:00:00').toLocaleDateString() : 'Não definido';
        }
    } else {
        if (demandContext) demandContext.style.display = 'none';
    }

    if (at.is_encaminhamento_pure) {
        if (editorSec) editorSec.style.display = 'none';
        if (timelineSec) timelineSec.style.display = 'none';
        if (profSec) profSec.style.display = 'none';
        if (deleteSec) deleteSec.style.display = 'none';
    } else {
        if (editorSec) editorSec.style.display = 'block';
        if (timelineSec) timelineSec.style.display = 'block';
        if (profSec) profSec.style.display = 'block';
        if (deleteSec) deleteSec.style.display = isRestricted ? 'none' : 'block';

        // Descrições
        const descPublica = document.getElementById('cdDescPublica');
        const descProfissional = document.getElementById('cdDescProfissional');
        const profWrapper = document.getElementById('cdDescProfissionalWrapper');
        
        if (descPublica) descPublica.value = at.descricao_publica || '';
        
        if (descProfissional) {
            descProfissional.value = at.descricao_profissional || '';
            
            if (isRestricted) {
                // Modo restrito (Conselho): Borrar anotação profissional
                descProfissional.readOnly = true;
                if (profWrapper) {
                    profWrapper.className = 'privacy-blur';
                    
                    const isAdmin = typeof currentUserProfile !== 'undefined' && ['Administrador', 'Coordenador'].includes(currentUserProfile);
                    const isOwner = typeof currentUserId !== 'undefined' && at.author_id == currentUserId;
                    const canReveal = isAdmin || isOwner;

                    profWrapper.onclick = canReveal ? () => profWrapper.classList.add('revealed') : () => {
                        if (typeof Toast !== 'undefined') Toast.show('Acesso restrito ao profissional responsável ou coordenação.', 'warning');
                    };
                    
                    // Adicionar overlay se não houver
                    if (!profWrapper.querySelector('.privacy-overlay')) {
                        const overlay = document.createElement('div');
                        overlay.className = 'privacy-overlay';
                        overlay.innerText = '⚠️ Conteúdo restrito. Clique para visualizar.';
                        profWrapper.appendChild(overlay);
                    }
                }
            } else {
                descProfissional.readOnly = false;
                if (profWrapper) {
                    profWrapper.className = '';
                    profWrapper.onclick = null;
                    const overlay = profWrapper.querySelector('.privacy-overlay');
                    if (overlay) overlay.remove();
                }
            }
        }
        
        // Se estiver num contexto restritito, esconder o botão de salvar do editor
        const saveContainer = document.getElementById('cdSaveInfoBtnContainer');
        if (saveContainer) saveContainer.style.display = isRestricted ? 'none' : 'block';

        // Esconder busca de profissionais se restrito
        const userSearchArea = document.querySelector('#cdProfessionalsSection div[style*="border-top"]');
        if (userSearchArea) userSearchArea.style.display = isRestricted ? 'none' : 'block';

        // Render timeline e responsaveis
        renderTimeline(data.comentarios || [], isRestricted);
        renderResponsaveis(data.responsaveis || [], !isRestricted);
    }
}
