/**
 * Vértice Acadêmico — Componente de Modal
 */

class Modal {
    static instances = new Map();
    static zIndex = 9000;
    
    static open(options) {
        const {
            title = '',
            content = '',
            size = 'md', // sm, md, lg, xl, full
            closable = true,
            closeOnOverlay = true,
            closeOnEscape = true,
            buttons = [],
            onClose = null,
            id = 'modal-' + Date.now()
        } = options;
        
        // Remove modal existente com mesmo ID
        if (this.instances.has(id)) {
            this.close(id);
        }
        
        const modal = document.createElement('div');
        modal.id = id;
        modal.className = 'modal-wrapper';
        modal.dataset.zIndex = ++this.zIndex;
        
        let buttonsHtml = '';
        if (buttons.length > 0) {
            buttonsHtml = '<div class="modal-footer">';
            buttons.forEach((btn, index) => {
                const { text, class: btnClass = 'btn-secondary', action, type = 'button' } = btn;
                buttonsHtml += `
                    <button type="${type}" class="btn ${btnClass}" data-modal-action="${index}">
                        ${text}
                    </button>
                `;
            });
            buttonsHtml += '</div>';
        }
        
        modal.innerHTML = `
            <div class="modal-overlay" ${closeOnOverlay ? 'data-modal-close' : ''}>
                <div class="modal-dialog modal-${size}">
                    <div class="modal-content">
                        ${title ? `
                            <div class="modal-header">
                                <h3 class="modal-title">${title}</h3>
                                ${closable ? '<button type="button" class="modal-close" data-modal-close>×</button>' : ''}
                            </div>
                        ` : ''}
                        <div class="modal-body">
                            ${content}
                        </div>
                        ${buttonsHtml}
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Armazena callbacks
        const callbacks = { onClose, buttons: buttons.map(b => b.action) };
        this.instances.set(id, callbacks);
        
        // Anima entrada
        requestAnimationFrame(() => {
            modal.classList.add('modal-show');
        });
        
        // Event listeners
        if (closable) {
            modal.querySelectorAll('[data-modal-close]').forEach(el => {
                el.addEventListener('click', () => this.close(id));
            });
        }
        
        if (closeOnEscape) {
            const escapeHandler = (e) => {
                if (e.key === 'Escape' && this.instances.has(id)) {
                    this.close(id);
                    document.removeEventListener('keydown', escapeHandler);
                }
            };
            document.addEventListener('keydown', escapeHandler);
        }
        
        // Botões
        modal.querySelectorAll('[data-modal-action]').forEach(el => {
            el.addEventListener('click', (e) => {
                const index = parseInt(e.target.dataset.modalAction);
                const callback = callbacks.buttons[index];
                if (callback) callback(e);
            });
        });
        
        // Callback de abertura
        if (options.onOpen) {
            options.onOpen(modal);
        }
        
        return id;
    }
    
    static close(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        
        const callbacks = this.instances.get(id);
        
        modal.classList.remove('modal-show');
        modal.classList.add('modal-hide');
        
        setTimeout(() => {
            modal.remove();
            this.instances.delete(id);
            
            if (callbacks && callbacks.onClose) {
                callbacks.onClose();
            }
        }, 300);
    }
    
    static closeAll() {
        this.instances.forEach((_, id) => this.close(id));
    }
    
    static confirm(options) {
        const {
            title = 'Confirmar',
            message,
            confirmText = 'Confirmar',
            cancelText = 'Cancelar',
            confirmClass = 'btn-primary',
            onConfirm = () => {},
            onCancel = () => {}
        } = options;
        
        return this.open({
            title,
            content: `<p class="modal-confirm-message">${message}</p>`,
            size: 'sm',
            buttons: [
                { text: cancelText, class: 'btn-secondary', action: onCancel },
                { text: confirmText, class: confirmClass, action: onConfirm }
            ]
        });
    }
    
    static alert(options) {
        const {
            title = 'Aviso',
            message,
            buttonText = 'OK',
            onClose = () => {}
        } = options;
        
        return this.open({
            title,
            content: `<p class="modal-alert-message">${message}</p>`,
            size: 'sm',
            buttons: [
                { text: buttonText, class: 'btn-primary', action: onClose }
            ]
        });
    }
    
    static showLoading(options) {
        const {
            title = 'Aguarde',
            message = 'Processando...'
        } = options;
        
        return this.open({
            title,
            content: `
                <div style="text-align: center; padding: 1rem;">
                    <div class="spinner spinner-sm" style="margin: 0 auto;"></div>
                    <p style="margin-top: 1rem;">${message}</p>
                </div>
            `,
            closable: false,
            closeOnOverlay: false,
            closeOnEscape: false,
            size: 'sm',
            buttons: []
        });
    }
}

// Funções globais
function showModal(options) {
    return Modal.open(options);
}

function hideModal(id) {
    return Modal.close(id);
}

function confirmModal(options) {
    return Modal.confirm(options);
}

function alertModal(options) {
    return Modal.alert(options);
}
