/**
 * Shared JS: Student Comment Logic
 * Depends on WordCloud2 (external) and standard system UI functions (openModal, closeModal)
 */

let currentCommentAlunoId = null;
let currentCommentTurmaId = null;
let currentCommentAlunoList = null;


/**
 * Generic Modal Helpers (if not defined by host page)
 */
if (typeof window.openModal !== 'function') {
    window.openModal = function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('show');
            el.style.display = 'flex'; // Ensure display:flex
            document.body.style.overflow = 'hidden';
        }
    };
}
if (typeof window.closeModal !== 'function') {
    window.closeModal = function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('show');
            el.style.display = 'none'; // Revert to hidden
            document.body.style.overflow = '';
        }
    };
    // Close on backdrop click
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-backdrop')) {
            event.target.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
}

/**
 * Open the comment modal for a specific student
 * @param {Object} aluno - { id, nome, photo, photo_url }
 * @param {Number} turmaId - Context turma ID
 * @param {Array} alunoList - Optional list of students for navigation
 */
function openCommentModal(aluno, turmaId, alunoList = null) {
    currentCommentAlunoId = aluno.id;
    currentCommentTurmaId = turmaId;
    currentCommentAlunoList = alunoList;
    
    // UI Elements
    const nameEl = document.getElementById('comment_aluno_name');
    const photoEl = document.getElementById('comment_aluno_photo');
    const idInput = document.getElementById('comment_aluno_id');
    const textDiv = document.getElementById('comment_text');
    const historyMeu = document.getElementById('comment_history_meu');
    const historyOutros = document.getElementById('comment_history_outros');
    const navEl = document.getElementById('comment_modal_nav');
    
    if (idInput) idInput.value = aluno.id;
    if (nameEl) nameEl.textContent = aluno.nome;
    if (textDiv) textDiv.innerHTML = '';
    
    if (historyMeu) historyMeu.innerHTML = '<div style="padding:1rem;text-align:center;"><span style="font-size:.875rem;color:var(--text-muted);">Carregando...</span></div>';
    if (historyOutros) historyOutros.innerHTML = '';
    
    // Reset Tabs
    document.querySelectorAll('.comment-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === 'comments');
    });
    document.querySelectorAll('.comment-tab-content').forEach(content => {
        content.style.display = 'none';
    });
    const tabComments = document.getElementById('tab-comments');
    if (tabComments) tabComments.style.display = 'block';
    
    // Photo
    if (photoEl) {
        if (aluno.photo && (aluno.photo_url || aluno.photo)) {
            const src = aluno.photo_url || '/' + aluno.photo;
            photoEl.innerHTML = `<img src="${src}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">`;
        } else {
            const initial = (aluno.nome || '?').charAt(0).toUpperCase();
            photoEl.innerHTML = `<span>${initial}</span>`;
            photoEl.style.background = 'var(--gradient-brand)';
        }
    }
    
    // Navigation controls
    if (navEl) {
        if (currentCommentAlunoList && currentCommentAlunoList.length > 1) {
            navEl.style.display = 'flex';
            const currentIndex = currentCommentAlunoList.findIndex(a => a.id === aluno.id);
            const prevBtn = document.getElementById('btn_comment_prev');
            const nextBtn = document.getElementById('btn_comment_next');
            if (prevBtn) prevBtn.disabled = (currentIndex <= 0);
            if (nextBtn) nextBtn.disabled = (currentIndex === -1 || currentIndex >= currentCommentAlunoList.length - 1);
        } else {
            navEl.style.display = 'none';
        }
    }
    
    loadComments(aluno.id, turmaId);
    if (typeof openModal === 'function') openModal('commentModal');
    else document.getElementById('commentModal').classList.add('show');
}

/**
 * Navigate to next/previous student inside comment modal
 * @param {Number} direction - -1 for previous, 1 for next
 */
function navigateCommentStudent(direction) {
    if (!currentCommentAlunoList || currentCommentAlunoList.length <= 1) return;
    
    const currentIndex = currentCommentAlunoList.findIndex(a => a.id === currentCommentAlunoId);
    if (currentIndex === -1) return;
    
    const newIndex = currentIndex + direction;
    if (newIndex < 0 || newIndex >= currentCommentAlunoList.length) return;
    
    // Check for unsaved comment
    const textDiv = document.getElementById('comment_text');
    if (textDiv) {
        const conteudo = textDiv.innerHTML.trim();
        if (conteudo && conteudo !== '<br>') {
            if (!confirm('Você digitou um comentário que ainda não foi publicado. Deseja mudar de aluno e descartar esse texto?')) {
                return;
            }
        }
    }
    
    const nextAluno = currentCommentAlunoList[newIndex];
    openCommentModal(nextAluno, currentCommentTurmaId, currentCommentAlunoList);
}

/**
 * Fetch and render comments from API
 */
async function loadComments(alunoId, turmaId) {
    console.log('[loadComments] Requesting comments for aluno:', alunoId, 'turma:', turmaId);
    try {
        const resp = await fetch(`/api/comments.php?aluno_id=${alunoId}&turma_id=${turmaId}`);
        console.log('[loadComments] Response status:', resp.status);
        
        const text = await resp.text();
        console.log('[loadComments] Raw response:', text.substring(0, 500));
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('[loadComments] JSON parse error:', e);
            return;
        }
        
        if (data.error) {
            console.error('[loadComments] API error:', data.error);
            return;
        }
        
        console.log('[loadComments] Data:', data);
        
        renderMyComments(data.meus_comentarios);
        renderOtherComments(data.outros_comentarios);
        
        // Refresh active analysis tab inside modal if not "comments"
        const activeTab = document.querySelector('.comment-tab-btn.active');
        if (activeTab && activeTab.dataset.tab !== 'comments') {
            switchCommentTab(activeTab.dataset.tab);
        }

        // Refresh trend container on the main page if it exists (mini mode)
        const trendContainer = document.getElementById(`trend-${alunoId}`);
        if (trendContainer && typeof VASentiment !== 'undefined') {
            VASentiment.renderTrend(trendContainer, alunoId, turmaId, true);
        }
        
    } catch (e) {
        console.error('Erro ao carregar comentários:', e);
    }
}

function renderMyComments(comments) {
    const container = document.getElementById('comment_history_meu');
    if (!container) return;

    let html = '';
    if (comments && comments.length > 0) {
        const c0 = comments[0];
        const initial = (c0.professor_nome || 'P').charAt(0);
        const photoHtml = c0.professor_photo 
            ? `<img src="/${c0.professor_photo}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">`
            : `<div style="width:28px;height:28px;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.75rem;text-transform:uppercase;">${initial}</div>`;

        html += `
            <div style="margin-bottom:1.5rem;padding:1rem;background:var(--bg-surface-2nd);border-radius:var(--radius-md);border-left:3px solid var(--color-primary);">
                <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.75rem;">
                    ${photoHtml}
                    <div style="font-size:.875rem;font-weight:700;color:var(--text-primary);">Eu</div>
                </div>
                <div style="display:flex;flex-direction:column;gap:.75rem;">
                    ${comments.map(c => `
                        <div style="background:var(--bg-surface);padding:.75rem;border-radius:var(--radius-sm);border:1px solid var(--border-color);">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.375rem;">
                                <span style="font-size:.6875rem;color:var(--text-muted);">${formatCommentDate(c.created_at)}</span>
                                <button type="button" class="action-btn danger" style="width:24px;height:24px;font-size:.75rem;" onclick="deleteStudentComment(${c.id})" title="Excluir">🗑</button>
                            </div>
                            <div style="font-size:.875rem;line-height:1.5;color:var(--text-primary);">${c.conteudo}</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    } else {
        html += `<span style="font-size:.75rem;color:var(--text-muted);display:block;margin-bottom:1rem;">Você ainda não comentou sobre este aluno.</span>`;
    }
    container.innerHTML = html;
}

function renderOtherComments(comments) {
    const container = document.getElementById('comment_history_outros');
    if (!container) return;

    let html = '';
    if (comments && comments.length > 0) {
        const groups = {};
        comments.forEach(c => {
            if (!groups[c.professor_nome]) {
                groups[c.professor_nome] = { name: c.professor_nome, photo: c.professor_photo, list: [] };
            }
            groups[c.professor_nome].list.push(c);
        });

        Object.values(groups).forEach(g => {
            const initial = (g.name || 'P').charAt(0);
            const photoHtml = g.photo 
                ? `<img src="/${g.photo}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">`
                : `<div style="width:28px;height:28px;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.75rem;text-transform:uppercase;">${initial}</div>`;
            
            html += `
                <div style="margin-bottom:1.5rem;padding:1rem;background:var(--bg-surface-2nd);border-radius:var(--radius-md);">
                    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.75rem;">
                        ${photoHtml}
                        <div style="font-size:.875rem;font-weight:700;color:var(--text-primary);">${escapeHtml(g.name)}</div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.625rem;">
                        ${g.list.map(c => `
                            <div style="background:var(--bg-surface);padding:.75rem;border-radius:var(--radius-sm);border:1px solid var(--border-color);">
                                <div style="font-size:.6875rem;color:var(--text-muted);margin-bottom:.25rem;">${formatCommentDate(c.created_at)}</div>
                                <div style="font-size:.875rem;line-height:1.5;color:var(--text-secondary);">${c.conteudo}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        });
    } else {
        html += `<span style="font-size:.75rem;color:var(--text-muted);">Nenhum comentário de outros professores.</span>`;
    }
    container.innerHTML = html;
}

function formatCommentDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function saveComment(event) {
    event.preventDefault();
    const btn = event.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    
    const conteudo = document.getElementById('comment_text').innerHTML.trim();
    if (!conteudo || conteudo === '<br>') {
        if (typeof Toast !== 'undefined') {
            Toast.show('Por favor, digite um comentário.', 'warning');
        } else {
            alert('Por favor, digite um comentário.');
        }
        return;
    }
    
    if (typeof showLoading === 'function') showLoading('Salvando comentário...');
    btn.disabled = true;
    
    try {
        const formData = new FormData();
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'save_comment');
        formData.append('aluno_id', currentCommentAlunoId);
        formData.append('turma_id', currentCommentTurmaId);
        formData.append('conteudo', conteudo);
        
        const resp = await fetch('/api/comments.php', { 
            method: 'POST', 
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();
        
        if (data.error) throw new Error(data.error);
        if (data.message) throw new Error(data.message);
        
        if (typeof Toast !== 'undefined') {
            Toast.show('Comentário publicado com sucesso!', 'success');
        }
        
        document.getElementById('comment_text').innerHTML = '';
        loadComments(currentCommentAlunoId, currentCommentTurmaId);
        
    } catch (e) {
        console.error(e);
        if (typeof Toast !== 'undefined') {
            Toast.show(e.message || 'Erro ao salvar comentário', 'danger');
        } else {
            alert(e.message || 'Erro ao salvar comentário');
        }
    } finally {
        if (typeof hideLoading === 'function') hideLoading();
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function deleteStudentComment(id) {
    const performDelete = async () => {
        if (typeof showLoading === 'function') showLoading('Excluindo...');
        try {
            const formData = new FormData();
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'delete_comment');
            formData.append('comment_id', id);
            
            const resp = await fetch('/api/comments.php', { 
                method: 'POST', 
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            if (data.error) throw new Error(data.error);
            
            if (typeof Toast !== 'undefined') {
                Toast.show('Comentário excluído.', 'success');
            }
            loadComments(currentCommentAlunoId, currentCommentTurmaId);
        } catch (e) {
            console.error(e);
            if (typeof Toast !== 'undefined') {
                Toast.show(e.message || 'Erro ao excluir', 'danger');
            } else {
                alert(e.message || 'Erro ao excluir');
            }
        } finally {
            if (typeof hideLoading === 'function') hideLoading();
        }
    };

    if (typeof Modal !== 'undefined' && typeof Modal.confirm === 'function') {
        Modal.confirm({
            title: 'Excluir Comentário',
            message: 'Deseja realmente excluir este comentário?',
            confirmText: 'Excluir',
            confirmClass: 'btn-danger',
            onConfirm: performDelete
        });
    } else {
        if (confirm('Deseja realmente excluir este comentário?')) {
            performDelete();
        }
    }
}

function switchCommentTab(tabName) {
    if (!currentCommentAlunoId || !currentCommentTurmaId) return;
    
    document.querySelectorAll('.comment-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
    });
    document.querySelectorAll('.comment-tab-content').forEach(content => {
        content.style.display = 'none';
    });
    const target = document.getElementById('tab-' + tabName);
    if (target) target.style.display = 'block';
    
    if (tabName === 'wordcloud')  generateWordCloud(currentCommentAlunoId, currentCommentTurmaId);
    if (tabName === 'summary')    generateSummary(currentCommentAlunoId, currentCommentTurmaId);
    if (tabName === 'trend')      generateTrend(currentCommentAlunoId, currentCommentTurmaId);
    if (tabName === 'ai_report')  loadAIReport(currentCommentAlunoId, currentCommentTurmaId);
}

// ---- Analysis Generators (simplified call to API) ----

async function fetchAllComments(alunoId, turmaId) {
    const url = `/api/comments.php?aluno_id=${alunoId}&turma_id=${turmaId}`;
    console.log('[fetchAllComments] Requesting:', url);
    
    const resp = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    
    if (!resp.ok) {
        const errText = await resp.text().catch(() => '');
        console.error('[fetchAllComments] HTTP Error:', resp.status, errText);
        throw new Error(data.error || `Erro HTTP ${resp.status}`);
    }
    
    const text = await resp.text();
    console.log('[fetchAllComments] Raw response:', text.substring(0, 500));
    
    let data;
    try {
        data = JSON.parse(text);
    } catch (e) {
        console.error('[fetchAllComments] JSON parse error:', e);
        throw new Error('Resposta inválida do servidor');
    }
    
    console.log('[fetchAllComments] Parsed data:', data);
    return data;
}

/** Rich Text Helpers */
function formatText(command) {
    document.execCommand(command, false, null);
    document.getElementById('comment_text').focus();
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Note: generateWordCloud, generateSummary, and generateTrend logic 
// are expected to be present or will be added to this file below.
// I'll include them to make it fully functional.

// =============================================================================
// Relatório IA — Análise Pedagógica via /api/ai_analysis.php
// =============================================================================

/**
 * Carrega a análise de IA para o aluno (usa cache quando disponível).
 */
async function loadAIReport(alunoId, turmaId) {
    const loading = document.getElementById('ai_report_loading');
    const content = document.getElementById('ai_report_content');
    const empty   = document.getElementById('ai_report_empty');
    const error   = document.getElementById('ai_report_error');

    try {
        if (!loading || !content || !empty || !error) return;

        loading.style.display = 'block';
        content.style.display = 'none';
        empty.style.display   = 'none';
        error.style.display   = 'none';

        const resp = await fetch(
            `/api/ai_analysis.php?aluno_id=${alunoId}&turma_id=${turmaId}`,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );

        if (!resp.ok) {
            throw new Error(`Erro HTTP ${resp.status}`);
        }

        const data = await resp.json();

        if (data.error) {
            throw new Error(data.error);
        }

        if (data.status === 'sem_dados') {
            loading.style.display = 'none';
            empty.style.display   = 'block';
            return;
        }

        renderAIReport(data.analysis);
        loading.style.display = 'none';
        content.style.display = 'block';

    } catch (e) {
        console.error('[loadAIReport]', e);
        const loadingEl = document.getElementById('ai_report_loading');
        const errorEl   = document.getElementById('ai_report_error');
        const msgEl     = document.getElementById('ai_report_error_msg');
        if (loadingEl) loadingEl.style.display = 'none';
        if (errorEl)   errorEl.style.display   = 'block';
        if (msgEl)     msgEl.textContent        = e.message || 'Erro desconhecido ao carregar análise.';
    }
}

/**
 * Força recálculo via IA (POST com CSRF).
 */
async function refreshAIReport() {
    const content = document.getElementById('ai_report_content');
    const loading = document.getElementById('ai_report_loading');
    const error   = document.getElementById('ai_report_error');

    if (content) content.style.display = 'none';
    if (error)   error.style.display   = 'none';
    if (loading) loading.style.display = 'block';

    try {
        const formData  = new FormData();
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        formData.append('csrf_token', csrfToken);
        formData.append('aluno_id', currentCommentAlunoId);
        formData.append('turma_id', currentCommentTurmaId);

        const resp = await fetch('/api/ai_analysis.php', {
            method:  'POST',
            body:    formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!resp.ok) {
            throw new Error(`Erro HTTP ${resp.status}`);
        }

        const data = await resp.json();
        if (data.error) throw new Error(data.error);

        if (data.status === 'sem_dados') {
            if (loading) loading.style.display = 'none';
            const empty = document.getElementById('ai_report_empty');
            if (empty) empty.style.display = 'block';
            return;
        }

        renderAIReport(data.analysis);
        if (loading) loading.style.display = 'none';
        if (content) content.style.display = 'block';

        if (typeof Toast !== 'undefined') {
            Toast.show('Análise atualizada com sucesso!', 'success');
        }

    } catch (e) {
        console.error('[refreshAIReport]', e);
        if (loading) loading.style.display = 'none';
        if (typeof Toast !== 'undefined') {
            Toast.show(e.message || 'Erro ao atualizar análise', 'danger');
        } else {
            if (error) {
                error.style.display = 'block';
                const msgEl = document.getElementById('ai_report_error_msg');
                if (msgEl) msgEl.textContent = e.message;
            }
        }
    }
}

/**
 * Renderiza o HTML da análise dentro do container #ai_report_content.
 */
function renderAIReport(analysis) {
    const content = document.getElementById('ai_report_content');
    if (!content) return;

    const tendColor = {
        melhorando: 'var(--color-success)',
        piorando:   'var(--color-danger)',
        'estável':  'var(--color-warning)',
        sem_dados:  'var(--text-muted)',
    };
    const tendEmoji = {
        melhorando: '📈', piorando: '📉', 'estável': '➡️', sem_dados: '❓',
    };
    const atencaoColor = {
        normal:    'var(--color-success)',
        'atenção': 'var(--color-warning)',
        urgente:   'var(--color-danger)',
    };
    const atencaoEmoji = { normal: '✅', 'atenção': '⚠️', urgente: '🚨' };

    const tComp = analysis.tendencia_comportamental || 'sem_dados';
    const tAcad = analysis.tendencia_academica      || 'sem_dados';
    const nivel = analysis.nivel_atencao            || 'normal';

    let html = '';

    // Cartões de indicadores (3 colunas)
    html += `
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:1.5rem;">
            <div style="background:var(--bg-surface-2nd);padding:.875rem;border-radius:var(--radius-md);text-align:center;border-top:3px solid ${tendColor[tComp] || 'var(--border-color)'};">
                <div style="font-size:1.5rem;">${tendEmoji[tComp] || '❓'}</div>
                <div style="font-size:.6875rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.04em;margin-top:.25rem;">Comportamento</div>
                <div style="font-size:.8125rem;font-weight:700;color:${tendColor[tComp] || 'var(--text-muted)'};">${tComp}</div>
            </div>
            <div style="background:var(--bg-surface-2nd);padding:.875rem;border-radius:var(--radius-md);text-align:center;border-top:3px solid ${tendColor[tAcad] || 'var(--border-color)'};">
                <div style="font-size:1.5rem;">${tendEmoji[tAcad] || '❓'}</div>
                <div style="font-size:.6875rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.04em;margin-top:.25rem;">Desempenho</div>
                <div style="font-size:.8125rem;font-weight:700;color:${tendColor[tAcad] || 'var(--text-muted)'};">${tAcad}</div>
            </div>
            <div style="background:var(--bg-surface-2nd);padding:.875rem;border-radius:var(--radius-md);text-align:center;border-top:3px solid ${atencaoColor[nivel] || 'var(--border-color)'};">
                <div style="font-size:1.5rem;">${atencaoEmoji[nivel] || '❓'}</div>
                <div style="font-size:.6875rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.04em;margin-top:.25rem;">Atenção</div>
                <div style="font-size:.8125rem;font-weight:700;color:${atencaoColor[nivel] || 'var(--text-muted)'};">${nivel}</div>
            </div>
        </div>
    `;

    // Resumo
    if (analysis.resumo) {
        html += `
            <div style="background:var(--color-primary-light);border-left:4px solid var(--color-primary);padding:1rem;border-radius:var(--radius-md);margin-bottom:1.25rem;">
                <div style="font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-primary);margin-bottom:.5rem;">Resumo Pedagógico</div>
                <div style="font-size:.875rem;line-height:1.6;color:var(--text-primary);">${escapeHtml(analysis.resumo)}</div>
            </div>
        `;
    }

    // Dificuldades e pontos fortes
    const difs  = Array.isArray(analysis.areas_dificuldade) ? analysis.areas_dificuldade : [];
    const pontos = Array.isArray(analysis.pontos_fortes)    ? analysis.pontos_fortes     : [];
    if (difs.length > 0 || pontos.length > 0) {
        html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">`;
        if (difs.length > 0) {
            html += `
                <div>
                    <div style="font-size:.6875rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:.625rem;">Dificuldades</div>
                    <div style="display:flex;flex-wrap:wrap;gap:.375rem;">
                        ${difs.map(a => `<span style="background:rgba(239,68,68,.1);color:var(--color-danger);padding:.2rem .625rem;border-radius:var(--radius-full);font-size:.75rem;font-weight:600;">${escapeHtml(a)}</span>`).join('')}
                    </div>
                </div>
            `;
        }
        if (pontos.length > 0) {
            html += `
                <div>
                    <div style="font-size:.6875rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:.625rem;">Pontos Fortes</div>
                    <div style="display:flex;flex-wrap:wrap;gap:.375rem;">
                        ${pontos.map(p => `<span style="background:rgba(16,185,129,.1);color:var(--color-success);padding:.2rem .625rem;border-radius:var(--radius-full);font-size:.75rem;font-weight:600;">${escapeHtml(p)}</span>`).join('')}
                    </div>
                </div>
            `;
        }
        html += `</div>`;
    }

    // Síntese dos comentários
    if (analysis.comentarios_resumo) {
        html += `
            <div style="background:var(--bg-surface-2nd);padding:.875rem;border-radius:var(--radius-md);margin-bottom:1.25rem;border:1px solid var(--border-color);">
                <div style="font-size:.6875rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:.5rem;">Síntese dos Comentários</div>
                <div style="font-size:.875rem;line-height:1.6;color:var(--text-secondary);">${escapeHtml(analysis.comentarios_resumo)}</div>
            </div>
        `;
    }

    // Evolução descritiva
    if (analysis.evolucao_descritiva && analysis.evolucao_descritiva !== 'sem_dados') {
        html += `
            <div style="background:var(--bg-surface-2nd);padding:.875rem;border-radius:var(--radius-md);margin-bottom:1.25rem;border:1px solid var(--border-color);">
                <div style="font-size:.6875rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:.5rem;">Evolução Acadêmica</div>
                <div style="font-size:.875rem;line-height:1.6;color:var(--text-secondary);">${escapeHtml(analysis.evolucao_descritiva)}</div>
            </div>
        `;
    }

    // Recomendações
    const recs = Array.isArray(analysis.recomendacoes) ? analysis.recomendacoes : [];
    if (recs.length > 0) {
        html += `
            <div style="margin-bottom:1.25rem;">
                <div style="font-size:.6875rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:.625rem;">Recomendações</div>
                <ul style="margin:0;padding-left:1.375rem;display:flex;flex-direction:column;gap:.5rem;">
                    ${recs.map(r => `<li style="font-size:.875rem;color:var(--text-secondary);line-height:1.5;">${escapeHtml(r)}</li>`).join('')}
                </ul>
            </div>
        `;
    }

    // Rodapé com metadados — visível apenas para Administrador
    if (window.VA_IS_ADMIN === true) {
        const geradoEm = analysis._cache?.generated_at
            ? new Date(analysis._cache.generated_at).toLocaleString('pt-BR')
            : (analysis.gerado_em ? new Date(analysis.gerado_em).toLocaleString('pt-BR') : '');
        const provider = escapeHtml(analysis.provider || '');

        html += `
            <div style="border-top:1px solid var(--border-color);padding-top:.75rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
                <div style="font-size:.6875rem;color:var(--text-muted);">
                    🤖 Gerado por IA${provider ? ' (' + provider + ')' : ''}${geradoEm ? ' — ' + geradoEm : ''}
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="refreshAIReport()">
                    🔄 Atualizar análise
                </button>
            </div>
        `;
    }

    content.innerHTML = html;
}

// =============================================================================
// Objeto público: vaAIAnalysis (para uso pelo componente standalone)
// =============================================================================

const vaAIAnalysis = {
    /**
     * Carrega a análise dentro de um widget standalone (renderStudentAIAnalysis).
     * @param {string} widgetId — ID do elemento .va-ai-analysis-widget
     */
    async load(widgetId) {
        const widget  = document.getElementById(widgetId);
        if (!widget) return;

        const alunoId = widget.dataset.alunoId;
        const turmaId = widget.dataset.turmaId;

        const states = {
            loading: widget.querySelector('.va-ai-loading'),
            content: widget.querySelector('.va-ai-content'),
            empty:   widget.querySelector('.va-ai-empty'),
            error:   widget.querySelector('.va-ai-error'),
        };

        Object.values(states).forEach(el => { if (el) el.style.display = 'none'; });
        if (states.loading) states.loading.style.display = 'block';

        try {
            const resp = await fetch(
                `/api/ai_analysis.php?aluno_id=${alunoId}&turma_id=${turmaId}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const data = await resp.json();

            if (data.error) throw new Error(data.error);

            if (data.status === 'sem_dados') {
                if (states.loading) states.loading.style.display = 'none';
                if (states.empty)   states.empty.style.display   = 'block';
                return;
            }

            if (states.content) {
                states.content.innerHTML = vaAIAnalysis.renderHtml(data.analysis);
                states.loading.style.display = 'none';
                states.content.style.display = 'block';
            }
        } catch (e) {
            console.error('[vaAIAnalysis.load]', e);
            if (states.loading) states.loading.style.display = 'none';
            if (states.error) {
                states.error.style.display = 'block';
                const msgEl = states.error.querySelector('.va-ai-error-msg');
                if (msgEl) msgEl.textContent = e.message || 'Erro ao carregar análise.';
            }
        }
    },

    /** Gera o HTML de análise para uso no componente standalone. */
    renderHtml(analysis) {
        // Reutiliza a função do modal mas retorna string ao invés de injetar no DOM
        const tmp = document.createElement('div');
        tmp.id = '__va_ai_tmp_content';
        document.body.appendChild(tmp);

        const prev = document.getElementById('ai_report_content');
        tmp.id = 'ai_report_content';
        if (prev) prev.id = '__va_ai_bkp';

        renderAIReport(analysis);
        const result = tmp.innerHTML;

        tmp.remove();
        if (prev) prev.id = 'ai_report_content';

        return result;
    },
};

// =============================================================================
// Continuação: Word Cloud, Summary, Trend
// =============================================================================

async function generateWordCloud(alunoId, turmaId) {
    const loading = document.getElementById('wordcloud_loading');
    const canvas = document.getElementById('wordcloud_canvas');
    const empty = document.getElementById('wordcloud_empty');
    const info = document.getElementById('wordcloud_info');
    
    loading.style.display = 'block';
    canvas.style.display = 'none';
    empty.style.display = 'none';
    info.style.display = 'none';
    
    try {
        const data = await fetchAllComments(alunoId, turmaId);
        if (!data.todos_comentarios || data.todos_comentarios.length === 0) {
            loading.style.display = 'none';
            empty.style.display = 'block';
            return;
        }
        
        const stopWords = new Set(['0','1','2','3','4','5','6','7','8','9','a','e','i','o','v','x','à','é','af','ah','ao','as','aí','da','de','do','eh','em','eu','há','ii','iv','ix','já','me','na','no','né','oh','ok','os','ou','pq','se','só','tb','te','tu','tá','um','vc','vi','xi','xv','às',' cá',' lá',' né','agr','ali','aos','até','bem','com','das','dos','ela','ele','era','foi','for','fui','hei','hão','iii','lhe','mas','meu','msm','nas','nem','nos','num','não','por','pra','pro','que','sem','ser','seu','sou','sua','são','tbm','tem','ter','teu','tlg','tua','tém','têm','uma','vai','vcs','vcê','vii','vos','vou','vão','xii','xiv','como','dela','dele','elas','eles','eram','essa','esse','esta','este','está','fora','haja','isso','isto','lhes','logo','mais','meus','numa','para','pela','pelo','pode','pois','qual','quem','seja','será','seus','suas','terá','teus','teve','tive','tuas','viii','você','xiii','ainda','aluna','aluno','assim','delas','deles','entre','então','essas','esses','estas','estes','estou','estão','fomos','foram','forem','fosse','hajam','houve','mesmo','minha','muito','nossa','nosso','pelas','pelos','poder','porém','sejam','serei','seria','serão','somos','temos','tenha','tenho','terei','teria','terão','tinha','tiver','vamos','visto','vocês','alunas','alunos','aquela','aquele','aquilo','depois','estava','esteja','esteve','estive','formos','fossem','houver','minhas','nossas','nossos','porque','quando','seriam','também','tenham','teriam','tinham','tivera','éramos','aquelas','aqueles','contudo','estamos','estavam','estejam','estiver','fôramos','hajamos','havemos','houvera','houverá','sejamos','seremos','teremos','tivemos','tiveram','tiverem','tivesse','todavia','estivera','fôssemos','houvemos','houveram','houverei','houverem','houveria','houverão','houvesse','seríamos','tenhamos','teríamos','tivermos','tivessem','tínhamos','estejamos','estivemos','estiveram','estiverem','estivesse','estávamos','houveriam','houvermos','houvessem','tivéramos','estivermos','estivessem','houveremos','houvéramos','tivéssemos','estivéramos','houveríamos','houvéssemos','estivéssemos']);
        const wordCounts = {};
        let totalWords = 0;
        
        data.todos_comentarios.forEach(comment => {
            const text = comment.conteudo.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ');
            // Divide o texto em blocos por qualquer tipo de espaço ou pontuação comum
            const tokens = text.toLowerCase().split(/[\s\n\r,.;:!?()\[\]"']+/);
            
            tokens.forEach(token => {
                // Remove qualquer resquício de pontuação e garante que não comece com @
                const word = token.trim();
                if (word.length > 2 && !word.startsWith('@') && !stopWords.has(word)) {
                    // Verificação extra para garantir que não haja arroba no meio ou fim (casos raros)
                    if (word.indexOf('@') === -1) {
                        wordCounts[word] = (wordCounts[word] || 0) + 1;
                        totalWords++;
                    }
                }
            });
        });
        
        const wordList = Object.entries(wordCounts).sort((a, b) => b[1] - a[1]).slice(0, 50);
        if (wordList.length === 0) {
            loading.style.display = 'none';
            empty.style.display = 'block';
            return;
        }
        
        document.getElementById('wordcloud_word_count').textContent = totalWords;
        document.getElementById('wordcloud_comment_count').textContent = data.todos_comentarios.length;
        
        if (typeof WordCloud === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/wordcloud@1.2.2/src/wordcloud2.min.js';
            script.onload = () => drawWordCloud(wordList, canvas);
            document.head.appendChild(script);
        } else {
            drawWordCloud(wordList, canvas);
        }
        
        loading.style.display = 'none';
        canvas.style.display = 'block';
        info.style.display = 'block';
    } catch (e) {
        loading.style.display = 'none';
        empty.style.display = 'block';
    }
}

function drawWordCloud(wordList, canvas) {
    const maxCount = Math.max(...wordList.map(w => w[1]));
    const minCount = Math.min(...wordList.map(w => w[1]));
    const countRange = maxCount - minCount || 1;
    const getColor = () => {
        const colors = ['#4f46e5','#7c3aed','#06b6d4','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
        return colors[Math.floor(Math.random() * colors.length)];
    };
    WordCloud(canvas, {
        list: wordList.map(([word, count]) => [word, Math.round(12 + ((count - minCount) / countRange) * 40)]),
        gridSize: 8, weightFactor: 1, fontFamily: 'Inter, sans-serif', color: getColor, rotateRatio: 0.3, rotationSteps: 2, backgroundColor: 'transparent', drawOutOfBound: false, shrinkToFit: true
    });
}

async function generateSummary(alunoId, turmaId) {
    const loading = document.getElementById('summary_loading');
    const content = document.getElementById('summary_content');
    const empty = document.getElementById('summary_empty');
    loading.style.display = 'block'; content.style.display = 'none'; empty.style.display = 'none';
    
    try {
        const data = await fetchAllComments(alunoId, turmaId);
        if (!data.todos_comentarios || data.todos_comentarios.length === 0) {
            loading.style.display = 'none'; empty.style.display = 'block'; return;
        }

        const wordCounts = {};
        const stats = { total: data.todos_comentarios.length, positive: 0, negative: 0, neutral: 0, items: [] };
        const stopWords = new Set(['a', 'o', 'e', 'do', 'da', 'no', 'na', 'de', 'em', 'um', 'uma', 'como', 'para', 'com', 'ao', 'que', 'dos', 'das', 'seu', 'sua', 'está', 'aluno', 'aluna', 'comentário', 'foi', 'por', 'é', 'não']);

        data.todos_comentarios.forEach(comment => {
            const score = VASentiment.analyzeText(comment.conteudo);
            const sentiment = score >= 1 ? 'positive' : (score <= -1 ? 'negative' : 'neutral');
            stats[sentiment]++;
            
            const rawText = comment.conteudo.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ');
            const tokens = rawText.toLowerCase().split(/[\s\n\r,.;:!?()\[\]"']+/);
            
            // Word counts ignorando menções
            tokens.forEach(token => {
                const word = token.trim();
                if (word.length >= 3 && !word.startsWith('@') && word.indexOf('@') === -1 && !stopWords.has(word)) {
                    wordCounts[word] = (wordCounts[word] || 0) + 1;
                }
            });
            
            // Store item
            stats.items.push({
                text: rawText.length > 150 ? rawText.substring(0, 147) + '...' : rawText,
                fullContent: comment.conteudo,
                date: new Date(comment.created_at).toLocaleDateString('pt-BR'),
                sentiment: sentiment,
                score: score
            });
        });

        const topWords = Object.entries(wordCounts).sort((a,b) => b[1] - a[1]).slice(0, 10);
        const sentimentLabels = { positive: 'Positivo', negative: 'Negativo', neutral: 'Neutro' };
        const sentimentPercent = { 
            positive: Math.round((stats.positive / stats.total) * 100), 
            neutral: Math.round((stats.neutral / stats.total) * 100),
            negative: Math.round((stats.negative / stats.total) * 100) 
        };

        let html = `
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem;margin-bottom:1.5rem;">
                <div style="background:var(--bg-surface-2nd);padding:1rem;border-radius:var(--radius-md);text-align:center;border-top:4px solid var(--color-success);">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--color-success);">${stats.positive}</div>
                    <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Positivos</div>
                </div>
                <div style="background:var(--bg-surface-2nd);padding:1rem;border-radius:var(--radius-md);text-align:center;border-top:4px solid var(--color-warning);">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--color-warning);">${stats.neutral}</div>
                    <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Neutros</div>
                </div>
                <div style="background:var(--bg-surface-2nd);padding:1rem;border-radius:var(--radius-md);text-align:center;border-top:4px solid var(--color-danger);">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--color-danger);">${stats.negative}</div>
                    <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Negativos</div>
                </div>
            </div>

            <div style="margin-bottom:1.5rem;">
                <div style="display:flex;justify-content:space-between;margin-bottom:.5rem;font-size:.75rem;font-weight:600;color:var(--text-muted);">
                   <span>PERFIL DE SENTIMENTO (${stats.total} comentários)</span>
                </div>
                <div style="display:flex;height:12px;border-radius:10px;overflow:hidden;background:var(--bg-surface-3rd);">
                    <div style="background:var(--color-success);width:${sentimentPercent.positive}%" title="Positivos: ${sentimentPercent.positive}%"></div>
                    <div style="background:var(--color-warning);width:${sentimentPercent.neutral}%" title="Neutros: ${sentimentPercent.neutral}%"></div>
                    <div style="background:var(--color-danger);width:${sentimentPercent.negative}%" title="Negativos: ${sentimentPercent.negative}%"></div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;">
                <div>
                   <h4 style="font-size:0.875rem;margin-bottom:1rem;color:var(--text-primary);display:flex;align-items:center;gap:.5rem;">
                        <span style="font-size:1.1rem;">🏷️</span> Palavras mais frequentes
                   </h4>
                   <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
                       ${topWords.length ? topWords.map(([word, count]) => `
                           <div style="background:var(--bg-surface-2nd);border:1px solid var(--border-color);padding:.375rem .75rem;border-radius:var(--radius-full);font-size:.8125rem;display:flex;align-items:center;gap:.5rem;">
                               <span style="color:var(--text-primary);padding-right:.375rem;border-right:1px solid var(--border-color);">${word}</span>
                               <span style="color:var(--color-primary);font-weight:700;font-size:.75rem;">${count}</span>
                           </div>
                       `).join('') : '<span style="font-size:.8125rem;color:var(--text-muted);">Nenhuma palavra relevante encontrada.</span>'}
                   </div>
                </div>
                <div style="background:var(--bg-surface-2nd);padding:1.25rem;border-radius:var(--radius-lg);border:1px dashed var(--border-color);display:flex;flex-direction:column;justify-content:center;align-items:center;">
                    <span style="font-size:2rem;margin-bottom:.5rem;">🎯</span>
                    <span style="font-size:.875rem;font-weight:600;color:var(--text-primary);">Total Analisado</span>
                    <span style="font-size:.75rem;color:var(--text-muted);">${stats.total} comentários históricos</span>
                </div>
            </div>

            <div>
                <h4 style="font-size:0.875rem;margin-bottom:1rem;color:var(--text-primary);display:flex;align-items:center;gap:.5rem;">
                    <span style="font-size:1.1rem;">📜</span> Lista de Comentários Classificados
                </h4>
                <div style="display:flex;flex-direction:column;gap:1px;background:var(--border-color);border:1px solid var(--border-color);border-radius:var(--radius-md);overflow:hidden;">
                    ${stats.items.map(item => {
                        const colors = {
                            positive: { bg: 'var(--badge-prof-bg)', text: 'var(--badge-prof-text)', border: 'var(--border-color)', emoji: '✓' },
                            neutral: { bg: 'var(--badge-outro-bg)', text: 'var(--badge-outro-text)', border: 'var(--border-color)', emoji: '○' },
                            negative: { bg: 'var(--badge-naapi-bg)', text: 'var(--badge-naapi-text)', border: 'var(--border-color)', emoji: '✗' }
                        };
                        const theme = colors[item.sentiment];
                        const dateBadge = `<span style="font-size:.6875rem;color:var(--text-muted);font-weight:500;">${item.date}</span>`;
                        const sentimentBadge = `<span style="font-size:.625rem;font-weight:700;text-transform:uppercase;padding:.125rem .5rem;border-radius:10px;background:${theme.bg};color:${theme.text};border:1px solid ${theme.border};display:inline-flex;align-items:center;gap:3px;">${theme.emoji} ${sentimentLabels[item.sentiment]}</span>`;
                        
                        return `
                            <div style="background:var(--bg-surface);padding:1rem;display:flex;gap:1rem;align-items:flex-start;">
                                <div style="flex:1;">
                                    <div style="display:flex;justify-content:space-between;margin-bottom:.5rem;align-items:center;">
                                        ${sentimentBadge}
                                        ${dateBadge}
                                    </div>
                                    <div style="font-size:.875rem;color:var(--text-secondary);line-height:1.5;">${item.text}</div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        loading.style.display = 'none';
        content.style.display = 'block';
    } catch (e) {
        console.error('Error generating summary:', e);
        loading.style.display = 'none';
        empty.style.display = 'block';
    }
}

async function generateTrend(alunoId, turmaId) {
    const loading = document.getElementById('trend_loading');
    const content = document.getElementById('trend_content');
    const empty = document.getElementById('trend_empty');
    loading.style.display = 'block'; content.style.display = 'none'; empty.style.display = 'none';
    
    try {
        const data = await fetchAllComments(alunoId, turmaId);
        if (!data.todos_comentarios || data.todos_comentarios.length < 2) {
            loading.style.display = 'none'; empty.style.display = 'block'; return;
        }

        const analysis = VASentiment.getHistoryAnalysis(data.todos_comentarios);
        const { history, status } = analysis;
        const maxScore = Math.max(...history.map(c => Math.abs(c.score)), 5);
        
        let html = `
            <div style="background:var(--bg-surface-2nd);padding:1.5rem;border-radius:var(--radius-lg);margin-bottom:1.5rem;display:flex;align-items:center;gap:1.5rem;border:1px solid var(--border-color);">
                <div style="font-size:3rem;">${status.emoji}</div>
                <div>
                   <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.05em;">Tendência de Desempenho</div>
                   <div style="font-size:1.5rem;font-weight:800;color:${status.color};margin-bottom:.25rem;">${status.label}</div>
                   <div style="font-size:.875rem;color:var(--text-secondary);">${status.desc}</div>
                </div>
            </div>

            <div style="margin-bottom:1.5rem;">
                <h4 style="font-size:0.875rem;margin-bottom:1rem;color:var(--text-primary);display:flex;align-items:center;gap:.5rem;">
                     🗓️ Evolução no Tempo
                </h4>
                <div style="background:var(--bg-surface-2nd);padding:1.5rem;border-radius:var(--radius-md);height:200px;display:flex;align-items:flex-end;gap:8px;border:1px solid var(--border-color);position:relative;">
                    <div style="position:absolute;left:10px;top:10px;bottom:10px;display:flex;flex-direction:column;justify-content:space-between;font-size:10px;color:var(--text-muted);pointer-events:none;">
                        <span>Positivo</span>
                        <span>Neutro</span>
                        <span>Negativo</span>
                    </div>

                    ${history.map(c => {
                        const hFactor = (c.score / maxScore) * 50;
                        const height = Math.abs(hFactor) + 2; 
                        const isPos = c.score >= 0;
                        const color = c.score >= 1 ? 'var(--color-success)' : (c.score <= -1 ? 'var(--color-danger)' : 'var(--color-warning)');
                        
                        return `
                            <div style="flex:1;height:100%;position:relative;display:flex;flex-direction:column;justify-content:center;">
                                <div style="position:absolute;bottom:50%;left:0;right:0;height:${isPos ? height : 0}%;background:${color};border-radius:4px 4px 0 0;opacity:0.8;"></div>
                                <div style="position:absolute;top:50%;left:0;right:0;height:${!isPos ? height : 0}%;background:${color};border-radius:0 0 4px 4px;opacity:0.8;"></div>
                            </div>
                        `;
                    }).join('')}
                    <div style="position:absolute;left:0;right:0;top:50%;height:1px;background:var(--border-color);z-index:0;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0 .5rem;margin-top:.5rem;font-size:.625rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;">
                    <span>${history[0].date.toLocaleDateString()}</span>
                    <span>Progresso dos Comentários</span>
                    <span>${history[history.length-1].date.toLocaleDateString()}</span>
                </div>
            </div>

            <div style="background:var(--bg-surface-2nd);padding:1rem;border-radius:var(--radius-md);font-size:.8125rem;color:var(--text-muted);border:1px dashed var(--border-color);">
                <strong>Nota:</strong> Esta análise pedagógica modularizada garante consistência em todo o sistema.
            </div>
        `;
        
        content.innerHTML = html; loading.style.display = 'none'; content.style.display = 'block';
    } catch (e) {
        console.error('Error in trend analysis:', e);
        loading.style.display = 'none'; empty.style.display = 'block';
    }
}
