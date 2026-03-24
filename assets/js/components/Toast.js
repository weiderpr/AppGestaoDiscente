/**
 * Vértice Acadêmico — Sistema de Notificações Toast
 */

class Toast {
    static show(message, type = 'info', duration = 4000) {
        const container = Toast.getContainer();
        const toast = document.createElement('div');
        
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${Toast.getIcon(type)}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()" aria-label="Fechar">×</button>
        `;
        
        container.appendChild(toast);
        
        // Animação de entrada
        requestAnimationFrame(() => {
            toast.classList.add('toast-show');
        });
        
        // Auto remover
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.remove('toast-show');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        return toast;
    }
    
    static success(message, duration) {
        return this.show(message, 'success', duration);
    }
    
    static error(message, duration) {
        return this.show(message, 'error', duration);
    }
    
    static warning(message, duration) {
        return this.show(message, 'warning', duration);
    }
    
    static info(message, duration) {
        return this.show(message, 'info', duration);
    }
    
    static getIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }
    
    static getContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }
}

// Funções globais para compatibilidade
function showToast(message, type, duration) {
    return Toast.show(message, type, duration);
}

function showSuccess(message, duration) {
    return Toast.success(message, duration);
}

function showError(message, duration) {
    return Toast.error(message, duration);
}

function showWarning(message, duration) {
    return Toast.warning(message, duration);
}

function showInfo(message, duration) {
    return Toast.info(message, duration);
}
