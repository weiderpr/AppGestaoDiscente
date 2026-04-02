/**
 * Vértice Acadêmico — Lógica Compartilhada de Detalhes de Atendimento
 */

// Rastreia a aba atualmente ativa para preservar a posição após recargas do modal
let currentActiveTab = 'info';


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

        const isOwner = typeof currentUserId !== 'undefined' && c.author_id == currentUserId;
        const isAdmin = typeof currentUserProfile !== 'undefined' && ['Administrador', 'Coordenador'].includes(currentUserProfile);
        const canDelete = isOwner || isAdmin;
        const deleteBtn = canDelete ? `<button onclick="deleteComment(${c.id})" title="Excluir comentário" style="background:transparent;border:none;color:#ef4444;cursor:pointer;padding:2px 4px;font-size:0.85rem;line-height:1;opacity:0.6;transition:opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">🗑️</button>` : '';

        h += `
            <div class="comment-item" id="comment-${c.id}">
                <div class="comment-header">
                    <strong>${c.author_name}</strong>
                    <span style="display:flex;align-items:center;gap:0.5rem;">${dateStr} ${privBadge} ${deleteBtn}</span>
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
    const preserveTab = options.preserveTab || false;
    
    const titleEl = document.getElementById('cdMainTitle');
    if (titleEl) titleEl.innerText = at.titulo;
    
    const statusEl = document.getElementById('cdBadgeStatus');
    if (statusEl) statusEl.innerText = at.status;
    
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
    } else {
        if (subtitleEl) subtitleEl.innerText = 'Atendimento Geral';
        if (photoEl) photoEl.style.display = 'none';
        if (avatarEl) {
            avatarEl.style.display = 'flex';
            avatarEl.innerText = '📄';
        }
    }

    const demandContext = document.getElementById('cdDemandaContext');
    const profSec = document.getElementById('cdProfessionalsSection');
    const deleteSec = document.getElementById('cdDeleteSection');

    // Archive / Unarchive Button logic
    const archiveText = document.getElementById('archiveText');
    const archiveIcon = document.getElementById('archiveIcon');
    if (archiveText && typeof currentIsArchived !== 'undefined') {
        archiveText.innerText = currentIsArchived ? 'Desarquivar Card' : 'Arquivar Card';
        archiveIcon.innerText = currentIsArchived ? '♻️' : '📦';
    }

    // Mostrar/Esconder aba de encaminhamento se houver vínculo com encaminhamento
    const btnTabEnc = document.getElementById('btn-tab-encaminhamento');
    if (btnTabEnc) {
        btnTabEnc.style.display = (at.encaminhamento_id) ? 'flex' : 'none';
    }

    // Determina qual aba inicial mostrar:
    const tabToRestore = preserveTab ? currentActiveTab : 'info';
    if (!preserveTab) currentActiveTab = 'info'; 
    
    // Chama a troca de abas diretamente
    switchTab(null, tabToRestore);
    
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
        if (demandContext) demandContext.style.display = 'block';
        const _tabTimeline = document.getElementById('tab-timeline');
        const _tabInfo = document.getElementById('tab-info');
        const _tabAnexos = document.getElementById('tab-anexos');
        const _tabEnc = document.getElementById('tab-encaminhamento');
        if (_tabTimeline) _tabTimeline.style.setProperty('display', 'none', 'important');
        if (_tabInfo) _tabInfo.style.setProperty('display', 'none', 'important');
        if (_tabAnexos) _tabAnexos.style.setProperty('display', 'none', 'important');
        
        // Se for puro encaminhamento, mostramos a aba de encaminhamento se disponível
        const btnTabEnc = document.getElementById('btn-tab-encaminhamento');
        if (btnTabEnc) {
            btnTabEnc.style.display = 'flex';
            switchTab(btnTabEnc, 'encaminhamento');
        }
        if (profSec) profSec.style.display = 'none';
        if (deleteSec) deleteSec.style.display = 'none';

        if (demandContext) {
            document.getElementById('cdCouncilName').innerText = at.conselho_nome || 'Conselho de Classe';
            document.getElementById('cdDemandText').innerText = at.texto || at.encaminhamento_texto || 'Sem descrição adicional.';
            document.getElementById('cdDeadlineValue').innerText = at.data_expectativa ? new Date(at.data_expectativa + 'T00:00:00').toLocaleDateString() : 'Não definido';
        }
    } else {
        if (demandContext) demandContext.style.display = 'none';
        // editorSec and timelineSec visibility is managed by tab switching,
        // not by direct style manipulation
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

        // Render timeline, responsaveis e anexos
        renderTimeline(data.comentarios || [], isRestricted);
        renderResponsaveis(data.responsaveis || [], !isRestricted);
        loadAnexos(at.id);
    }
}

function switchTab(btn, tabName) {
    if (!tabName) return;

    const modal = document.getElementById('modalCardDetails');
    if (!modal) return;

    // 1. Localiza o botão (seja via clique 'this' ou via nome da aba na restauração)
    if (!btn || typeof btn === 'string') {
        const name = (typeof btn === 'string' ? btn : tabName).trim();
        btn = modal.querySelector(`.tab-btn[data-tab="${name}"]`);
        tabName = name;
    }

    if (!btn) return;

    // 2. Atualiza estado e indicador visual (Borda e Cor)
    currentActiveTab = tabName;
    modal.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // 3. Gerenciamento FORÇADO de visibilidade (Inline Style com !important)
    // Esconde todos os containers de aba
    modal.querySelectorAll('.tab-content').forEach(content => {
        content.style.setProperty('display', 'none', 'important');
    });

    // Exibe apenas o alvo
    // Exibe apenas o alvo
    const targetContent = document.getElementById('tab-' + tabName.trim());
    if (targetContent) {
        targetContent.style.setProperty('display', 'flex', 'important');
    } else {
        console.warn('Alvo de aba não encontrado:', 'tab-' + tabName);
    }
}

/**
 * Funções de Gestão de Anexos
 */

async function loadAnexos(atendimentoId) {
    const container = document.getElementById('cdAnexosList');
    if (!container) return;

    container.innerHTML = '<div style="padding:1rem;color:var(--text-muted);font-size:0.875rem;">Carregando anexos...</div>';

    try {
        const res = await fetch(`/api/atendimentos.php?action=fetch_anexos&atendimento_id=${atendimentoId}`);
        const data = await res.json();

        if (data.success) {
            renderAnexos(data.anexos);
        } else {
            container.innerHTML = `<div style="padding:1rem;color:#ef4444;font-size:0.875rem;">Erro ao carregar: ${data.error}</div>`;
        }
    } catch (e) {
        container.innerHTML = '<div style="padding:1rem;color:#ef4444;font-size:0.875rem;">Erro de conexão ao carregar anexos.</div>';
    }
}

function renderAnexos(anexos) {
    const container = document.getElementById('cdAnexosList');
    if (!container) return;

    if (anexos.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 2rem 1rem; color: var(--text-muted); background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                <div style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;">📁</div>
                <p style="font-size: 0.8125rem;">Nenhum anexo encontrado para este atendimento.</p>
            </div>
        `;
        return;
    }

    let h = '';
    anexos.forEach(a => {
        const dateStr = new Date(a.created_at).toLocaleDateString();
        const icon = a.extensao === 'pdf' ? '📄' : '🖼️';
        const sizeStr = a.tamanho ? (a.tamanho / 1024 / 1024).toFixed(2) + ' MB' : '';

        h += `
            <div class="anexo-item">
                <div class="anexo-icon">${icon}</div>
                <div class="anexo-info">
                    <span class="anexo-name" title="${a.descricao || 'Sem descrição'}">${a.descricao || 'Arquivo .' + a.extensao}</span>
                    <div class="anexo-meta">${dateStr} • ${a.extensao.toUpperCase()} ${sizeStr ? ' • ' + sizeStr : ''} • Por ${a.author_name}</div>
                </div>
                <div class="anexo-actions">
                    <button class="btn btn-secondary btn-sm" style="padding:0.25rem 0.5rem;" onclick="viewAnexo('/${a.arquivo}', '${a.descricao || ''}', '${a.extensao}')">👁️</button>
                    <button class="btn btn-secondary btn-sm" style="padding:0.25rem 0.5rem; color:#ef4444;" onclick="deleteAnexo(${a.id})">🗑️</button>
                </div>
            </div>
        `;
    });
    container.innerHTML = h;
}

function openAddAnexoModal() {
    openModal('modalAddAnexo');
    document.getElementById('formAddAnexo').reset();
    document.getElementById('uploadProgressContainer').style.display = 'none';
    document.getElementById('uploadProgressBar').style.width = '0%';
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

async function submitAnexo() {
    const fileInput = document.getElementById('anexoFile');
    const descInput = document.getElementById('anexoDescricao');
    
    if (!fileInput.files || fileInput.files.length === 0) {
        Toast.show('Por favor, selecione um arquivo.', 'warning');
        return;
    }

    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('action', 'upload_anexo');
    formData.append('atendimento_id', currentAtendimentoId);
    formData.append('descricao', descInput.value);
    formData.append('arquivo', file);

    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    
    progressContainer.style.display = 'block';

    try {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/atendimentos.php', true);
        xhr.setRequestHeader('X-CSRF-TOKEN', getCsrfToken());

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                progressBar.style.width = percentComplete + '%';
            }
        };

        xhr.onload = function() {
            if (xhr.status === 200) {
                const res = JSON.parse(xhr.responseText);
                if (res.success) {
                    Toast.show('Anexo enviado com sucesso!', 'success');
                    closeModal('modalAddAnexo');
                    loadAnexos(currentAtendimentoId);
                } else {
                    Toast.show('Erro: ' + res.error, 'error');
                }
            } else {
                Toast.show('Erro no servidor ao enviar arquivo.', 'error');
            }
            progressContainer.style.display = 'none';
        };

        xhr.onerror = function() {
            Toast.show('Erro de rede ao enviar arquivo.', 'error');
            progressContainer.style.display = 'none';
        };

        xhr.send(formData);
    } catch (e) {
        Toast.show('Erro inesperado: ' + e.message, 'error');
        progressContainer.style.display = 'none';
    }
}

function viewAnexo(url, descricao, extensao) {
    const modal = document.getElementById('modalViewAnexo');
    const container = document.getElementById('anexoPreviewContainer');
    const title = document.getElementById('viewAnexoTitle');
    const downloadBtn = document.getElementById('downloadAnexoBtn');

    if (!modal || !container) return;

    title.innerText = descricao || 'Visualizar Anexo';
    downloadBtn.href = url;
    
    container.innerHTML = '';
    
    if (extensao === 'pdf') {
        container.innerHTML = `<iframe src="${url}#toolbar=0" style="width:100%; height:100%; border:none;"></iframe>`;
    } else {
        container.innerHTML = `<img src="${url}" style="max-width:100%; max-height:100%; object-fit:contain; box-shadow:0 4px 12px rgba(0,0,0,0.1);">`;
    }

    openModal('modalViewAnexo');
}

async function deleteAnexo(anexoId) {
    if (!confirm('Deseja realmente excluir este anexo?')) return;

    try {
        const formData = new FormData();
        formData.append('action', 'delete_anexo');
        formData.append('anexo_id', anexoId);

        const res = await fetch('/api/atendimentos.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            Toast.show('Anexo removido.', 'success');
            loadAnexos(currentAtendimentoId);
        } else {
            Toast.show('Erro: ' + data.error, 'error');
        }
    } catch (e) {
        Toast.show('Erro de conexão ao excluir.', 'error');
    }
}


async function deleteComment(comentarioId) {
    if (!confirm('Deseja realmente excluir este comentário?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_comment');
    formData.append('comentario_id', comentarioId);

    try {
        const res = await fetch('/api/atendimentos.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            const el = document.getElementById('comment-' + comentarioId);
            if (el) el.remove();
            Toast.show('Comentário excluído.', 'success');
        } else {
            Toast.show('Erro: ' + data.error, 'error');
        }
    } catch (e) {
        Toast.show('Erro de conexão ao excluir comentário.', 'error');
    }
}
