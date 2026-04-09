<?php
/**
 * Componente Modal de Atendimento Profissional (Estrutura Padrão Vértice)
 */
?>

<!-- Quill Rich Text Editor -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<style>
    .atend-xl { max-width: 850px !important; max-height: 90vh; }
    .quill-editor { height: 180px; background: var(--bg-surface); }
    .ql-toolbar { border-radius: var(--radius-md) var(--radius-md) 0 0; background: var(--bg-surface-2nd); border-color: var(--border-color) !important; }
    .ql-container { border-color: var(--border-color) !important; font-family: 'Inter', sans-serif; font-size: 0.875rem; border-radius: 0 0 var(--radius-md) var(--radius-md); }
    .atend-section-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
    
    /* Ajuste para o formulário dentro do modal-content */
    #atendimentoForm { display: flex; flex-direction: column; flex: 1; overflow: hidden; }
    
    /* Modal de atendimento específico */
    #atendimentoModal .modal-dialog.atend-xl {
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    #atendimentoModal .modal-content {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
    }
    #atendimentoModal .modal-body {
        flex: 1;
        overflow-y: auto;
    }
</style>

<div class="modal-wrapper" id="atendimentoModal" role="dialog" aria-modal="true">
    <div class="modal-overlay" onclick="closeAtendimentoModal()">
        <div class="modal-dialog modal-lg atend-xl" onclick="event.stopPropagation()">
            <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.75rem;">
                    <!-- Foto ou Ícone (Padrão do student_comment_modal.php) -->
                    <div id="atend_header_photo" style="width:40px;height:40px;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1rem;overflow:hidden;">
                        <span id="atend_header_icon">📝</span>
                        <img id="atend_header_img" src="" style="display:none; width:100%; height:100%; object-fit:cover;">
                    </div>
                    
                    <div>
                        <div id="atend_header_name" style="font-size:1rem;font-weight:700;color:var(--text-primary);">Novo Atendimento</div>
                        <div style="font-size:.75rem;color:var(--text-muted);">Atendimentos</div>
                    </div>
                </div>
                <button class="modal-close" onclick="closeAtendimentoModal()">✕</button>
            </div>
            
            <form id="atendimentoForm" novalidate>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="aluno_id" id="atend_aluno_id">
                <input type="hidden" name="turma_id" id="atend_turma_id">
                <input type="hidden" name="encaminhamento_id" id="atend_encaminhamento_id">
                <input type="hidden" name="atend_id" id="atend_id">
                
                <div class="modal-body" style="padding: 1.5rem 2rem; overflow-y: auto; flex: 1;">
                    
                    <!-- Contexto do Encaminhamento (quando houver) -->
                    <div id="atend_referral_context" style="display:none; margin-bottom:1.5rem; padding:1rem; background:rgba(var(--color-primary-rgb), 0.05); border-left:4px solid var(--color-primary); border-radius:var(--radius-md);">
                        <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; color:var(--color-primary); margin-bottom:0.25rem;">📌 Contexto do Encaminhamento</div>
                        <div id="atend_referral_text" style="font-size:0.875rem; color:var(--text-secondary); line-height:1.4;"></div>
                    </div>

                    <div class="form-row" style="margin-bottom: 1rem;">
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">Data do Atendimento</label>
                            <div class="input-group">
                                <span class="input-icon">📅</span>
                                <input type="date" name="data_atendimento" id="atend_data" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Atendimento Profissional (Privado) -->
                    <div class="form-group" style="margin-top: 0.5rem;">
                        <label class="atend-section-title">
                            <span>🔒 Atendimento Profissional (Privado)</span>
                        </label>
                        <div id="editor_professional" class="quill-editor"></div>
                        <input type="hidden" name="professional_text" id="professional_text_input">
                    </div>

                    <!-- Informação Pública -->
                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label class="atend-section-title">
                            <span>📢 Informação Pública / Encaminhamento</span>
                        </label>
                        <div id="editor_public" class="quill-editor"></div>
                        <input type="hidden" name="public_text" id="public_text_input">
                    </div>

                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAtendimentoModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveAtendimento">
                        <span class="btn-text">💾 Salvar Atendimento</span>
                        <span class="spinner" id="atendSpinner" style="display:none;"></span>
                    </button>
                </div>
            </form>
        </div>
        </div>
    </div>
</div>

<script>
let quillProfessional, quillPublic;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializa Quill Editors
    quillProfessional = new Quill('#editor_professional', {
        theme: 'snow',
        placeholder: 'Descreva os detalhes profissionais do atendimento...',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['clean']
            ]
        }
    });

    quillPublic = new Quill('#editor_public', {
        theme: 'snow',
        placeholder: 'Descreva a informação pública ou encaminhamento...',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['clean']
            ]
        }
    });

    // Form submission
    const form = document.getElementById('atendimentoForm');
    form.onsubmit = async function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('btnSaveAtendimento');
        const spinner = document.getElementById('atendSpinner');
        const btnText = btn.querySelector('.btn-text');
        
        // Sincroniza Quill -> Hidden Inputs
        document.getElementById('professional_text_input').value = quillProfessional.root.innerHTML;
        document.getElementById('public_text_input').value = quillPublic.root.innerHTML;
        
        // Validação mínima
        const profText = quillProfessional.getText().trim();
        const pubText = quillPublic.getText().trim();
        if (!profText && !pubText) {
            if (typeof Toast !== 'undefined') {
                Toast.show('Por favor, preencha o conteúdo do atendimento.', 'warning');
            } else {
                alert('Por favor, preencha o conteúdo do atendimento.');
            }
            return;
        }

        // Bloqueia interface
        btn.disabled = true;
        if (typeof showLoading === 'function') {
            showLoading('Salvando atendimento...');
        } else {
            spinner.style.display = 'inline-block';
            btnText.style.opacity = '0.5';
        }

        try {
            const formData = new FormData();
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            formData.append('csrf_token', csrfToken);
            formData.append('action', form.querySelector('input[name="action"]').value);
            formData.append('aluno_id', document.getElementById('atend_aluno_id').value);
            formData.append('turma_id', document.getElementById('atend_turma_id').value);
            formData.append('encaminhamento_id', document.getElementById('atend_encaminhamento_id').value);
            formData.append('atend_id', document.getElementById('atend_id').value);
            formData.append('data_atendimento', document.getElementById('atend_data').value);
            formData.append('professional_text', document.getElementById('professional_text_input').value);
            formData.append('public_text', document.getElementById('public_text_input').value);

            const response = await fetch('/api/atendimentos.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();

            if (result.success) {
                if (typeof Toast !== 'undefined') {
                    Toast.show(result.message, 'success');
                }
                closeAtendimentoModal();
                form.reset();
                quillProfessional.setContents([]);
                quillPublic.setContents([]);
                
                window.dispatchEvent(new CustomEvent('atendimentoSaved', { detail: result }));
            } else {
                if (typeof Toast !== 'undefined') {
                    Toast.show(result.error || 'Erro ao salvar atendimento.', 'danger');
                } else {
                    alert(result.error || 'Erro ao salvar atendimento.');
                }
            }
        } catch (error) {
            console.error('Error saving atendimento:', error);
            if (typeof Toast !== 'undefined') {
                Toast.show('Ocorreu um erro inesperado.', 'danger');
            }
        } finally {
            btn.disabled = false;
            if (typeof hideLoading === 'function') {
                hideLoading();
            } else {
                spinner.style.display = 'none';
                btnText.style.opacity = '1';
            }
        }
    };
});

function closeAtendimentoModal() {
    const modal = document.getElementById('atendimentoModal');
    if (modal) {
        modal.classList.remove('modal-show');
        document.body.style.overflow = '';
    }
}

/**
 * Função global para abrir o modal de atendimento configurado
 */
function openAtendimentoModal(data) {
    const modal = document.getElementById('atendimentoModal');
    if (!modal) return;

    document.getElementById('atend_aluno_id').value = data.aluno_id || '';
    document.getElementById('atend_turma_id').value = data.turma_id || '';
    document.getElementById('atend_encaminhamento_id').value = data.encaminhamento_id || '';
    document.getElementById('atend_id').value = data.id || '';
    
    const form = document.getElementById('atendimentoForm');
    const actionInput = form.querySelector('input[name="action"]');
    const btnText = document.querySelector('#btnSaveAtendimento .btn-text');

    if (data.id) {
        actionInput.value = 'update';
        btnText.innerText = '💾 Atualizar Atendimento';
    } else {
        actionInput.value = 'save';
        btnText.innerText = '💾 Salvar Atendimento';
    }

    document.getElementById('atend_header_name').innerText = data.target_name || (data.id ? 'Editar Atendimento' : 'Novo Atendimento');
    document.getElementById('atend_data').value = data.data_atendimento || new Date().toISOString().split('T')[0];
    
    // Foto do Aluno no Cabeçalho
    const photoImg = document.getElementById('atend_header_img');
    const photoIcon = document.getElementById('atend_header_icon');
    
    if (data.aluno_photo) {
        photoImg.src = '/' + data.aluno_photo;
        photoImg.style.display = 'block';
        photoIcon.style.display = 'none';
    } else {
        photoImg.style.display = 'none';
        photoIcon.style.display = 'block';
        // Se for aluno, mostra a inicial. Se não, o ícone de atendimento.
        if (data.aluno_id && data.target_name) {
            photoIcon.innerText = data.target_name.charAt(0).toUpperCase();
        } else {
            photoIcon.innerText = '📝';
        }
    }
    
    // Contexto de encaminhamento
    const contextArea = document.getElementById('atend_referral_context');
    const contextText = document.getElementById('atend_referral_text');
    if (data.referral_text) {
        contextText.innerText = data.referral_text;
        contextArea.style.display = 'block';
    } else {
        contextArea.style.display = 'none';
    }
    
    if (quillProfessional) quillProfessional.root.innerHTML = data.professional_text || '';
    if (quillPublic) quillPublic.root.innerHTML = data.public_text || '';
    
    modal.classList.add('modal-show');
    document.body.style.overflow = 'hidden';
}

document.getElementById('atendimentoModal').addEventListener('click', function(e) { 
    if(e.target === this) closeAtendimentoModal(); 
});
</script>
