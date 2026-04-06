<script>
/**
 * Abre o modal de Grade Horária e Configurações de Grupo do Aluno
 * 
 * @param {number} alunoId 
 * @param {string} alunoNome 
 * @param {string} alunoPhoto 
 * @param {string} activeTab 'grade' | 'atividades' | 'analise' | 'confs'
 */
async function openScheduleModal(alunoId, alunoNome, alunoPhoto = '', activeTab = 'grade') {
    if (typeof Loading !== 'undefined') Loading.show('Carregando grade...');
    
    try {
        // Caminho absoluto para garantir funcionamento em qualquer página de /courses/
        const url = `/courses/aulas/student_grid.php?aluno_id=${alunoId}`;
        
        const resp = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        if (!resp.ok) throw new Error(`HTTP ${resp.status}: ${resp.statusText}`);
        const html = await resp.text();

        const photoSrc = (alunoPhoto && !alunoPhoto.startsWith('/') && !alunoPhoto.startsWith('http')) ? '/' + alunoPhoto : alunoPhoto;

        const customTitle = `
            <div style="display:flex; align-items:center; gap:0.75rem;">
                ${photoSrc ? `<img src="${photoSrc}" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border:2px solid var(--color-primary);">` : '🎓'}
                <div style="display:flex; flex-direction:column; line-height:1.2;">
                    <span>Grade Horária</span>
                    <strong style="font-size:0.875rem; color:var(--text-secondary);">${alunoNome}</strong>
                </div>
            </div>
        `;

        if (typeof Modal !== 'undefined') {
            // Verifica se já existe um modal de grade aberto para apenas atualizar conteúdo (transição suave)
            const existingModal = document.getElementById('schedule_modal');
            const isAlreadyOpen = existingModal && !existingModal.classList.contains('modal-hide') && existingModal.style.display !== 'none';

            if (isAlreadyOpen) {
                const body = existingModal.querySelector('.modal-body');
                const title = existingModal.querySelector('.modal-title');
                if (body) body.innerHTML = html;
                if (title) title.innerHTML = customTitle;
            } else {
                Modal.open({
                    id: 'schedule_modal',
                    title: customTitle,
                    content: html,
                    size: 'xl'
                });
            }

            // Execução manual de scripts carregados via AJAX
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const scripts = tempDiv.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                document.body.appendChild(newScript);
                newScript.parentNode.removeChild(newScript);
            });

            // Ativar a aba correta após o carregamento (Grade, Atividades, Análise ou Configurações)
            setTimeout(() => {
                const btn = document.querySelector(`.tab-btn[data-tab="${activeTab}"]`);
                if (btn) btn.click();
            }, 50);

        } else {
            console.error('Sistema de Modal (Modal.js) não encontrado.');
            if (typeof Toast !== 'undefined') Toast.error('Erro de interface: Sistema de modal não carregado.');
        }
    } catch (err) {
        console.error('Erro ao carregar a grade horária:', err);
        if (typeof Toast !== 'undefined') Toast.error('Erro ao carregar a grade horária.');
    } finally {
        if (typeof Loading !== 'undefined') Loading.hide();
    }
}

/**
 * Fecha o modal de grade horária (caso necessário chamar via código)
 */
function closeScheduleModal() {
    if (typeof Modal !== 'undefined') {
        Modal.close('schedule_modal');
    } else {
        const modal = document.getElementById('schedule_modal');
        if (modal) modal.style.display = 'none';
    }
}
</script>
