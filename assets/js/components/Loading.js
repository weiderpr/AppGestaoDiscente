/**
 * Vértice Acadêmico — Componentes de Loading
 */

class Spinner {
    static show(message = 'Carregando...') {
        const overlay = document.createElement('div');
        overlay.id = 'spinner-overlay';
        overlay.className = 'spinner-overlay';
        overlay.innerHTML = `
            <div class="spinner-container">
                <div class="spinner"></div>
                ${message ? `<p class="spinner-text">${message}</p>` : ''}
            </div>
        `;
        document.body.appendChild(overlay);
        return overlay;
    }
    
    static hide() {
        const overlay = document.getElementById('spinner-overlay');
        if (overlay) {
            overlay.remove();
        }
    }
}

class Skeleton {
    static table(rows = 5, cols = 4) {
        let html = '<div class="skeleton-wrapper">';
        
        // Header
        html += '<div class="skeleton-table"><div class="skeleton-row">';
        for (let i = 0; i < cols; i++) {
            html += '<div class="skeleton-cell skeleton-header"></div>';
        }
        html += '</div>';
        
        // Rows
        for (let r = 0; r < rows; r++) {
            html += '<div class="skeleton-row">';
            for (let c = 0; c < cols; c++) {
                const width = Math.floor(Math.random() * 40) + 40;
                html += `<div class="skeleton-cell" style="width: ${width}%"></div>`;
            }
            html += '</div>';
        }
        
        html += '</div></div>';
        return html;
    }
    
    static card(count = 3) {
        let html = '<div class="skeleton-wrapper">';
        html += '<div class="skeleton-cards">';
        
        for (let i = 0; i < count; i++) {
            html += `
                <div class="skeleton-card">
                    <div class="skeleton-card-image"></div>
                    <div class="skeleton-card-title"></div>
                    <div class="skeleton-card-text"></div>
                    <div class="skeleton-card-text" style="width: 60%"></div>
                </div>
            `;
        }
        
        html += '</div></div>';
        return html;
    }
    
    static text(lines = 3) {
        let html = '<div class="skeleton-wrapper"><div class="skeleton-text">';
        for (let i = 0; i < lines; i++) {
            const width = i === lines - 1 ? 60 : 100;
            html += `<div class="skeleton-line" style="width: ${width}%"></div>`;
        }
        html += '</div></div>';
        return html;
    }
}

// Funções globais
function showLoading(message) {
    return Spinner.show(message);
}

function hideLoading() {
    return Spinner.hide();
}

// AJAX helper com loading automático
async function fetchWithLoading(url, options = {}) {
    const originalOnReadyStateChange = options.onReadyStateChange;
    
    showLoading();
    
    try {
        const response = await fetch(url, {
            ...options,
            onReadyStateChange: function() {
                if (this.readyState === 4) {
                    hideLoading();
                }
                if (originalOnReadyStateChange) {
                    originalOnReadyStateChange.call(this);
                }
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        return response;
    } catch (error) {
        hideLoading();
        showError(error.message || 'Erro ao carregar dados');
        throw error;
    }
}

// Função para transformar elementos em skeletons
function showSkeleton(selector) {
    document.querySelectorAll(selector).forEach(el => {
        el.classList.add('skeleton-loading');
    });
}

function hideSkeleton(selector) {
    document.querySelectorAll(selector).forEach(el => {
        el.classList.remove('skeleton-loading');
    });
}
