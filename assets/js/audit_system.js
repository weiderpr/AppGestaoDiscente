/**
 * Vértice Acadêmico — Sistema de Auditoria (Infinite Scroll - Window Based)
 */

class AuditManager {
    constructor(config) {
        this.tbody = document.querySelector(config.tbodySelector);
        this.loadingIndicator = document.querySelector(config.loadingSelector);
        this.filtersForm = document.querySelector(config.filtersFormSelector);
        
        this.offset = 0;
        this.limit = 20;
        this.loading = false;
        this.hasMore = true;
        
        this.filters = this.getFilters();
        this.init();
    }

    init() {
        if (!this.tbody) return;

        // Listener de Scroll na Janela (Social Media Style)
        window.addEventListener('scroll', () => {
            if (this.shouldLoadMore()) {
                this.loadMore();
            }
        }, { passive: true });

        // Listener de Filtros
        if (this.filtersForm) {
            this.filtersForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.applyFilters();
            });
        }

        // Carga inicial (considerando o que o PHP já renderizou)
        this.offset = this.tbody.querySelectorAll('.audit-tr').length;
        if (this.offset === 0) {
            this.loadMore();
        }
    }

    getFilters() {
        if (!this.filtersForm) return {};
        const formData = new FormData(this.filtersForm);
        const params = {};
        formData.forEach((value, key) => {
            if (value !== '') params[key] = value;
        });
        return params;
    }

    applyFilters() {
        this.filters = this.getFilters();
        this.offset = 0;
        this.hasMore = true;
        this.tbody.innerHTML = '';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        this.loadMore();
    }

    shouldLoadMore() {
        if (this.loading || !this.hasMore) return false;
        
        // Distância do fundo da página
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        const scrollBottom = documentHeight - (scrollTop + windowHeight);
        return scrollBottom < 400; // Gatilho a 400px do fundo para ser fluído
    }

    async loadMore() {
        if (this.loading) return;
        
        this.loading = true;
        this.showLoading(true);

        try {
            const queryParams = new URLSearchParams({
                ...this.filters,
                limit: this.limit,
                offset: this.offset
            });

            const response = await fetch(`/api/audit_logs.php?${queryParams.toString()}`);
            const result = await response.json();

            if (result.success) {
                if (result.data.length === 0) {
                    this.hasMore = false;
                    this.showNoMore(this.offset > 0);
                } else {
                    this.renderLogs(result.data);
                    this.offset += result.data.length;
                    
                    if (result.data.length < this.limit) {
                        this.hasMore = false;
                        this.showNoMore(true);
                    }
                }
            } else {
                if (typeof showError === 'function') showError(result.error);
                else alert(result.error);
            }
        } catch (error) {
            console.error('Audit Load Error:', error);
        } finally {
            this.loading = false;
            this.showLoading(false);
        }
    }

    showLoading(show) {
        if (this.loadingIndicator) {
            this.loadingIndicator.style.display = show ? 'block' : 'none';
        }
    }
    
    showNoMore(show) {
        const noMoreEl = document.getElementById('audit-no-more');
        if (noMoreEl) {
            noMoreEl.style.display = show ? 'block' : 'none';
        }
    }

    renderLogs(logs) {
        if (logs.length === 0 && this.offset === 0) {
            this.tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="audit-empty-td">
                        <div class="audit-empty-state">
                            <div class="empty-icon">📭</div>
                            <h3>Nenhum log encontrado</h3>
                            <p>Tente ajustar os filtros de busca.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        const fragment = document.createDocumentFragment();
        logs.forEach(log => {
            const tr = document.createElement('tr');
            tr.className = 'audit-tr fade-in';
            tr.innerHTML = this.getRowTemplate(log);
            fragment.appendChild(tr);
        });
        this.tbody.appendChild(fragment);
    }

    getRowTemplate(log) {
        const badgeClass = {
            'CREATE': 'audit-badge-create',
            'UPDATE': 'audit-badge-update',
            'DELETE': 'audit-badge-delete'
        }[log.action] || 'audit-badge-default';

        const dateObj = new Date(log.created_at);
        const dateStr = dateObj.toLocaleDateString('pt-BR');
        const timeStr = dateObj.toLocaleTimeString('pt-BR');

        return `
            <td class="audit-td-time">
                <span class="time-main">${dateStr}</span>
                <span class="time-sub">${timeStr}</span>
            </td>
            <td class="audit-td-user">
                <div class="audit-user-info">
                    <span class="user-name">${this.escape(log.user_name || 'Sistema')}</span>
                    <span class="inst-name">${this.escape(log.inst_name || 'Global')}</span>
                </div>
            </td>
            <td class="audit-td-action">
                <span class="audit-badge ${badgeClass}">${log.action}</span>
            </td>
            <td class="audit-td-record">
                <span class="table-name">${this.escape(log.table_name)}</span>
                <span class="record-id">ID #${log.record_id}</span>
            </td>
            <td class="audit-td-diff">
                <div class="diff-container">
                    <div class="diff-side old">
                        <label>Anterior</label>
                        <div class="diff-content">${this.formatJson(log.old_values)}</div>
                    </div>
                    <div class="diff-separator">➜</div>
                    <div class="diff-side new">
                        <label>Novo</label>
                        <div class="diff-content">${this.formatJson(log.new_values)}</div>
                    </div>
                </div>
            </td>
            <td class="audit-td-meta">
                <div class="meta-row" title="IP Address">
                    <span class="meta-icon">🌐</span>
                    <span class="meta-text">${log.ip_address || '—'}</span>
                </div>
                <div class="meta-row" title="${this.escape(log.user_agent || '')}">
                    <span class="meta-icon">📱</span>
                    <span class="meta-text truncate">Navegador</span>
                </div>
            </td>
        `;
    }

    formatJson(json) {
        if (!json || json === 'null') return '<span class="text-muted">Sem dados</span>';
        try {
            const data = typeof json === 'string' ? JSON.parse(json) : json;
            if (!data || Object.keys(data).length === 0) return '<span class="text-muted">Sem dados</span>';
            
            let html = '<div class="json-viewer-premium">';
            for (const [key, val] of Object.entries(data)) {
                let valStr = (typeof val === 'object' && val !== null) ? JSON.stringify(val) : String(val);
                if (valStr.length > 50) valStr = valStr.substring(0, 47) + '...';
                
                html += `
                    <div class="json-row">
                        <span class="json-key">${this.escape(key)}:</span>
                        <span class="json-val" title="${this.escape(JSON.stringify(val))}">${this.escape(valStr)}</span>
                    </div>
                `;
            }
            html += '</div>';
            return html;
        } catch (e) {
            return `<span class="text-muted">${this.escape(typeof json === 'string' ? json : JSON.stringify(json))}</span>`;
        }
    }

    escape(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.querySelector('.audit-table tbody');
    if (tbody) {
        window.auditManager = new AuditManager({
            tbodySelector: '.audit-table tbody',
            loadingSelector: '#audit-loader',
            filtersFormSelector: '.audit-form-inner'
        });
    }
});
