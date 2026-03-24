/**
 * Vértice Acadêmico — Sistema de Registros do Conselho (Post-its)
 */

let currentRecordConselhoId = null;
let currentRecordAlunoId = null;

/**
 * Abre o modal de registros do conselho
 * @param {Number} conselhoId 
 * @param {Object|null} aluno - { id, nome, photo } ou null para geral
 */
function openCouncilRecordModal(conselhoId, aluno = null) {
    currentRecordConselhoId = conselhoId;
    currentRecordAlunoId = aluno ? aluno.id : null;

    const modal = document.getElementById('registroModal');
    if (!modal) return;

    // Reset Form
    document.getElementById('registro_conselho_id').value = conselhoId;
    document.getElementById('registro_aluno_id').value = aluno ? aluno.id : '';
    document.getElementById('registro_text').innerHTML = '';
    
    // UI Context
    const banner = document.getElementById('registro_aluno_banner');
    const subtitle = document.getElementById('registro_context_subtitle');
    
    if (aluno) {
        banner.style.display = 'flex';
        document.getElementById('registro_aluno_name').textContent = aluno.nome;
        subtitle.textContent = 'Discussão específica sobre o aluno';
        
        const photoEl = document.getElementById('registro_aluno_photo');
        if (aluno.photo) {
            photoEl.innerHTML = `<img src="/${aluno.photo}" style="width:100%; height:100%; object-fit:cover;">`;
        } else {
            photoEl.innerHTML = `<span>${aluno.nome.charAt(0).toUpperCase()}</span>`;
        }
    } else {
        banner.style.display = 'none';
        subtitle.textContent = 'Discussões e anotações gerais da sessão';
    }

    // Load History
    loadCouncilRecords(conselhoId, currentRecordAlunoId);

    // Show Modal
    if (typeof openModal === 'function') {
        openModal('registroModal');
    } else {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Salva um novo registro do conselho
 */
async function saveCouncilRecord(event) {
    event.preventDefault();
    const btn = event.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    
    const texto = document.getElementById('registro_text').innerHTML.trim();
    if (!texto || texto === '<br>') {
        alert('Por favor, digite o registro da discussão.');
        return;
    }

    btn.innerHTML = '⏳ Salvando...';
    btn.disabled = true;

    try {
        const formData = new FormData(event.target);
        formData.append('texto', texto);
        
        const resp = await fetch('/courses/conselho_registros_ajax.php?action=save', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();

        if (data.success) {
            if (typeof showToast === 'function') showToast('Registro salvo com sucesso!', 'success');
            else alert('Registro salvo!');
            
            document.getElementById('registro_text').innerHTML = '';
            loadCouncilRecords(currentRecordConselhoId, currentRecordAlunoId);
        } else {
            throw new Error(data.message || 'Erro ao salvar o registro');
        }
    } catch (e) {
        alert(e.message);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

/**
 * Carrega histórico de registros
 */
async function loadCouncilRecords(conselhoId, alunoId = null) {
    const list = document.getElementById('registro_history_list');
    const badge = document.getElementById('registro_count_badge');
    
    let url = `/courses/conselho_registros_ajax.php?action=list&conselho_id=${conselhoId}`;
    if (alunoId !== null) url += `&aluno_id=${alunoId}`;
    else url += '&general_only=1'; // If opening general, only show general

    try {
        const resp = await fetch(url);
        const data = await resp.json();

        if (data.success) {
            badge.textContent = data.list.length;
            if (data.list.length === 0) {
                list.innerHTML = `
                    <div style="text-align:center; padding:1.5rem; color:var(--text-muted); font-size:0.875rem; background:var(--bg-surface-2nd); border:1px dashed var(--border-color); border-radius:var(--radius-md);">
                        Ainda não há registros ${alunoId ? 'específicos deste aluno' : 'gerais deste conselho'}.
                    </div>
                `;
                return;
            }

            let html = '';
            data.list.forEach(item => {
                const date = new Date(item.created_at).toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'});
                
                html += `
                    <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:1.25rem; position:relative; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                            <div style="font-size:0.75rem; font-weight:700; color:var(--color-primary); display:flex; align-items:center; gap:0.5rem;">
                                <span style="font-size:1rem;">🧑‍🏫</span> ${item.author_name} (${item.author_profile})
                            </div>
                            <div style="font-size:0.6875rem; color:var(--text-muted); font-weight:500;">${date}</div>
                        </div>
                        <div style="font-size:0.9375rem; line-height:1.6; color:var(--text-primary);">${item.texto}</div>
                        
                        <button type="button" class="action-btn danger" onclick="deleteRecord(${item.id})" style="position:absolute; top:1.25rem; right:-0.5rem; width:28px; height:28px; font-size:0.75rem; opacity:0; transition:all 0.2s;" title="Excluir">🗑</button>
                    </div>
                `;
            });
            list.innerHTML = html;
            
            // Add hover effect for delete button
            const cards = list.querySelectorAll('div[style*="background:var(--bg-surface)"]');
            cards.forEach(card => {
                card.onmouseenter = () => card.querySelector('.action-btn.danger').style.opacity = '1';
                card.onmouseleave = () => card.querySelector('.action-btn.danger').style.opacity = '0';
            });

        }
    } catch (e) {
        list.innerHTML = '<div style="text-align:center; color:var(--color-danger);">Erro ao carregar histórico.</div>';
    }
}

/**
 * Exclui um registro
 */
async function deleteRecord(id) {
    if (!confirm('Deseja realmente excluir este registro?')) return;

    try {
        const formData = new FormData();
        formData.append('id', id);
        
        const resp = await fetch('/courses/conselho_registros_ajax.php?action=delete', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message);
        
        loadCouncilRecords(currentRecordConselhoId, currentRecordAlunoId);
    } catch (e) {
        alert(e.message);
    }
}

/**
 * Rich Text Formatter
 */
function formatRecordText(command) {
    document.execCommand(command, false, null);
    document.getElementById('registro_text').focus();
}
