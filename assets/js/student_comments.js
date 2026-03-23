/**
 * Shared JS: Student Comment Logic
 * Depends on WordCloud2 (external) and standard system UI functions (openModal, closeModal)
 */

let currentCommentAlunoId = null;
let currentCommentTurmaId = null;


/**
 * Generic Modal Helpers (if not defined by host page)
 */
if (typeof window.openModal !== 'function') {
    window.openModal = function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    };
}
if (typeof window.closeModal !== 'function') {
    window.closeModal = function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('show');
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
 */
function openCommentModal(aluno, turmaId) {
    currentCommentAlunoId = aluno.id;
    currentCommentTurmaId = turmaId;
    
    // UI Elements
    const nameEl = document.getElementById('comment_aluno_name');
    const photoEl = document.getElementById('comment_aluno_photo');
    const idInput = document.getElementById('comment_aluno_id');
    const textDiv = document.getElementById('comment_text');
    const historyMeu = document.getElementById('comment_history_meu');
    const historyOutros = document.getElementById('comment_history_outros');
    
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
    
    loadComments(aluno.id, turmaId);
    if (typeof openModal === 'function') openModal('commentModal');
    else document.getElementById('commentModal').classList.add('show');
}

/**
 * Fetch and render comments from API
 */
async function loadComments(alunoId, turmaId) {
    try {
        const resp = await fetch(`/api/comments.php?aluno_id=${alunoId}&turma_id=${turmaId}`);
        const data = await resp.json();
        
        if (data.error) {
            console.error(data.error);
            return;
        }
        
        renderMyComments(data.meus_comentarios);
        renderOtherComments(data.outros_comentarios);
        
        // Refresh active analysis tab inside modal if not "comments"
        const activeTab = document.querySelector('.comment-tab-btn.active');
        if (activeTab && activeTab.dataset.tab !== 'comments') {
            switchCommentTab(activeTab.dataset.tab);
        }

        // Refresh trend container on the main page if it exists
        const trendContainer = document.getElementById(`trend-${alunoId}`);
        if (trendContainer && typeof VASentiment !== 'undefined') {
            VASentiment.renderTrend(trendContainer, alunoId, turmaId);
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
                                <button type="button" class="action-btn danger" style="width:24px;height:24px;font-size:.75rem;" onclick="deleteComment(${c.id})" title="Excluir">🗑</button>
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
        alert('Por favor, digite um comentário.');
        return;
    }
    
    btn.innerHTML = '⏳ Salvando...';
    btn.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('action', 'save_comment');
        formData.append('aluno_id', currentCommentAlunoId);
        formData.append('turma_id', currentCommentTurmaId);
        formData.append('conteudo', conteudo);
        
        const resp = await fetch('/api/comments.php', { method: 'POST', body: formData });
        const data = await resp.json();
        
        if (data.error) throw new Error(data.error);
        
        if (typeof showToast === 'function') showToast('Comentário publicado!', 'success');
        else alert('Comentário publicado com sucesso!');
        
        document.getElementById('comment_text').innerHTML = '';
        loadComments(currentCommentAlunoId, currentCommentTurmaId);
        
    } catch (e) {
        alert(e.message || 'Erro ao salvar comentário');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function deleteComment(id) {
    if (!confirm('Deseja realmente excluir este comentário?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_comment');
        formData.append('comment_id', id);
        
        const resp = await fetch('/api/comments.php', { method: 'POST', body: formData });
        const data = await resp.json();
        if (data.error) throw new Error(data.error);
        
        loadComments(currentCommentAlunoId, currentCommentTurmaId);
    } catch (e) {
        alert(e.message || 'Erro ao excluir');
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
    
    if (tabName === 'wordcloud') generateWordCloud(currentCommentAlunoId, currentCommentTurmaId);
    if (tabName === 'summary') generateSummary(currentCommentAlunoId, currentCommentTurmaId);
    if (tabName === 'trend') generateTrend(currentCommentAlunoId, currentCommentTurmaId);
}

// ---- Analysis Generators (simplified call to API) ----

async function fetchAllComments(alunoId, turmaId) {
    const resp = await fetch(`/api/comments.php?aluno_id=${alunoId}&turma_id=${turmaId}`);
    return await resp.json();
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
            const words = text.toLowerCase().match(/\b[a-záàâãéèêíìîóòôõúùûç]+/g) || [];
            words.forEach(word => {
                if (word.length > 2 && !stopWords.has(word)) {
                    wordCounts[word] = (wordCounts[word] || 0) + 1;
                    totalWords++;
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
            
            // Word counts
            const words = rawText.toLowerCase().match(/\b[a-záàâãéèêíìîóòôõúùûç]{3,}\b/g) || [];
            words.forEach(word => {
                if (!stopWords.has(word)) {
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
