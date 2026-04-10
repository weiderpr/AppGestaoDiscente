/**
 * Vértice Acadêmico — Social Feed Component Logic
 */

class SocialFeed {
    constructor() {
        this.sidebar = document.querySelector('.social-sidebar');
        this.resizer = document.querySelector('.social-resizer');
        this.feedList = document.querySelector('.social-feed-list');
        this.container = document.querySelector('.social-feed-container');
        
        this.isResizing = false;
        this.defaultWidth = 240;
        
        // Paging state
        this.offset = 0;
        this.limit = 20;
        this.hasMore = true;
        this.isLoading = false;
        
        // Filter state
        this.selectedFilters = []; // Array of {id, name, type}
        this.searchTimeout = null;
        
        this.init();
    }
    
    init() {
        this.setupResizer();
        this.setupAutocomplete();
        this.loadSidebarWidth();
        this.loadFeed(); // Initial load
        this.setupInfiniteScroll();
        
        // Listen for window resize to handle layout shifts
        window.addEventListener('resize', () => this.handleResponsiveLayout());
    }

    // --- Student/Turma/Course Filters ---

    setupAutocomplete() {
        const input = document.getElementById('aluno-search-input');
        const results = document.getElementById('aluno-search-results');
        
        if (!input || !results) return;

        input.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            
            clearTimeout(this.searchTimeout);
            if (query.length < 3) {
                results.style.display = 'none';
                return;
            }

            this.searchTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(`/api/students.php?action=search&q=${encodeURIComponent(query)}`);
                    const data = await response.json();
                    this.renderSearchResults(data);
                } catch (error) {
                    console.error('Search error:', error);
                }
            }, 300);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !results.contains(e.target)) {
                results.style.display = 'none';
            }
        });
    }

    renderSearchResults(items) {
        const results = document.getElementById('aluno-search-results');
        if (items.length === 0) {
            results.innerHTML = '<div style="padding: 1rem; font-size: 0.75rem; color: var(--text-muted); text-align: center;">Nenhum resultado encontrado.</div>';
        } else {
            const icons = { 'aluno': '👤', 'turma': '🏫', 'curso': '🎓' };
            
            results.innerHTML = items.map(s => `
                <div class="search-result-item" data-id="${s.id}" data-name="${s.name}" data-type="${s.type}">
                    ${s.type === 'aluno' 
                        ? `<img src="/${s.foto || 'assets/img/avatar-placeholder.png'}" onerror="this.src='/assets/img/avatar-placeholder.png'">`
                        : `<div style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 14px; background: var(--bg-surface-2nd); border-radius: 4px;">${icons[s.type]}</div>`
                    }
                    <div class="search-result-info">
                        <span class="search-result-name">${s.name}</span>
                        <span class="search-result-desc">${s.subtext || ''} ${s.matricula ? '• ' + s.matricula : ''}</span>
                    </div>
                </div>
            `).join('');

            results.querySelectorAll('.search-result-item').forEach(item => {
                item.addEventListener('click', () => {
                    this.addFilter({
                        id: item.dataset.id,
                        name: item.dataset.name,
                        type: item.dataset.type
                    });
                    results.style.display = 'none';
                    document.getElementById('aluno-search-input').value = '';
                });
            });
        }
        results.style.display = 'block';
    }

    addFilter(filter) {
        if (this.selectedFilters.find(f => f.id === filter.id && f.type === filter.type)) return;
        
        this.selectedFilters.push(filter);
        this.renderFilterTags();
        this.loadFeed(false); 
    }

    removeFilter(id, type) {
        this.selectedFilters = this.selectedFilters.filter(f => !(f.id === id && f.type === type));
        this.renderFilterTags();
        this.loadFeed(false);
    }

    clearFilters() {
        this.selectedFilters = [];
        this.renderFilterTags();
        document.getElementById('aluno-search-input').value = '';
        this.loadFeed(false);
    }

    renderFilterTags() {
        const container = document.getElementById('active-filters');
        if (!container) return;

        if (this.selectedFilters.length === 0) {
            container.innerHTML = '';
            return;
        }

        const icons = { 'aluno': '👤', 'turma': '🏫', 'curso': '🎓' };

        container.innerHTML = this.selectedFilters.map(f => `
            <div class="filter-tag">
                <span style="font-size: 10px; margin-right: 2px;">${icons[f.type]}</span>
                <span>${f.name.split(' ')[0]}</span>
                <span class="filter-tag-remove" onclick="window.socialFeed.removeFilter('${f.id}', '${f.type}')">&times;</span>
            </div>
        `).join('');
    }

    // --- Infinite Scroll ---

    setupInfiniteScroll() {
        const sentinel = document.getElementById('social-feed-sentinel');
        if (!sentinel) return;

        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && this.hasMore && !this.isLoading) {
                this.loadFeed(true);
            }
        }, {
            root: this.container,
            rootMargin: '200px', // Load before reaching the very bottom
            threshold: 0.1
        });

        observer.observe(sentinel);
    }

    // --- Sidebar Resizing ---
    
    setupResizer() {
        if (!this.resizer) return;

        this.resizer.addEventListener('mousedown', (e) => {
            this.isResizing = true;
            this.resizer.classList.add('resizing');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', (e) => {
            if (!this.isResizing) return;

            const offset = this.sidebar.getBoundingClientRect().left;
            let width = e.clientX - offset;
            
            // Constraints
            if (width < 150) width = 150;
            if (width > 400) width = 400;

            this.sidebar.style.width = `${width}px`;
            localStorage.setItem('social_sidebar_width', width);
        });

        document.addEventListener('mouseup', () => {
            if (this.isResizing) {
                this.isResizing = false;
                this.resizer.classList.remove('resizing');
                document.body.style.cursor = 'default';
                document.body.style.userSelect = 'auto';
            }
        });
    }

    loadSidebarWidth() {
        const savedWidth = localStorage.getItem('social_sidebar_width');
        if (savedWidth && window.innerWidth > 768) {
            this.sidebar.style.width = `${savedWidth}px`;
        } else if (window.innerWidth > 768) {
            this.sidebar.style.width = `${this.defaultWidth}px`;
        }
    }

    handleResponsiveLayout() {
        if (window.innerWidth <= 768) {
            this.sidebar.style.width = '100%';
        } else {
            this.loadSidebarWidth();
        }
    }

    // --- Feed Logic ---

    async loadFeed(append = false) {
        if (this.isLoading) return;

        if (!append) {
            this.offset = 0;
            this.hasMore = true;
            this.feedList.innerHTML = `
                <div id="initial-loader" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                    <div class="spinner" style="margin-bottom: 1rem; animation: spin 1s linear infinite;">⌛</div>
                    Carregando atividades...
                </div>
            `;
        } else {
            const footerLoader = document.getElementById('social-feed-footer-loading');
            if (footerLoader) footerLoader.style.display = 'block';
        }

        this.isLoading = true;

        const filters = { aluno: [], turma: [], curso: [] };
        this.selectedFilters.forEach(f => {
            if (filters[f.type]) filters[f.type].push(f.id);
        });

        let url = `/social/feed_ajax.php?offset=${this.offset}&limit=${this.limit}`;
        if (filters.aluno.length > 0) url += `&aluno_ids=${filters.aluno.join(',')}`;
        if (filters.turma.length > 0) url += `&turma_ids=${filters.turma.join(',')}`;
        if (filters.curso.length > 0) url += `&course_ids=${filters.curso.join(',')}`;

        try {
            const response = await fetch(url);
            const result = await response.json();

            if (result.status === 'success') {
                this.hasMore = result.has_more;
                this.offset += result.count;
                this.renderFeed(result.data, append);
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('SocialFeed Error:', error);
            this.showError('Não foi possível conectar ao servidor.');
        } finally {
            this.isLoading = false;
            const footerLoader = document.getElementById('social-feed-footer-loading');
            if (footerLoader) footerLoader.style.display = 'none';
            
            const initialLoader = document.getElementById('initial-loader');
            if (initialLoader) initialLoader.remove();
        }
    }

    renderFeed(items, append = false) {
        if (!append && items.length === 0) {
            this.feedList.innerHTML = `
                <div style="padding: 4rem; text-align: center; color: var(--text-muted); background: var(--bg-surface); border-radius: var(--radius-xl); border: 1px dashed var(--border-color);">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">✨</div>
                    <p>Nenhuma atividade registrada recentemente.</p>
                </div>
            `;
            return;
        }

        if (!append) {
            this.feedList.innerHTML = '';
        }

        items.forEach((item, index) => {
            const card = this.createCard(item, append ? index : index);
            this.feedList.appendChild(card);
        });
    }

    createCard(item, index) {
        const card = document.createElement('div');
        card.className = 'social-card';
        card.style.animationDelay = `${index * 0.05}s`;

        const initials = item.aluno_nome.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        const alunoPhotoHtml = item.aluno_foto 
            ? `<img src="/${item.aluno_foto}" class="aluno-avatar" alt="${item.aluno_nome}">`
            : `<div class="aluno-initials">${initials}</div>`;

        const respPhotoHtml = item.responsible_photo
            ? `<img src="/${item.responsible_photo}" class="responsible-avatar" alt="${item.responsible_name}">`
            : `<div class="responsible-avatar" style="background: var(--bg-surface-2nd); display: flex; align-items: center; justify-content: center; font-size: 8px; border: 1px solid var(--border-color);">👤</div>`;

        card.innerHTML = `
            <div class="social-card-header">
                <div class="social-card-user">
                    ${alunoPhotoHtml}
                    <div class="aluno-info">
                        <div class="aluno-title-row">
                            <span class="aluno-name">${item.aluno_nome}</span>
                            <span class="event-badge badge-${item.badge_type}">${item.badge_text}</span>
                        </div>
                        <span class="aluno-meta">${item.turma_desc} (${item.turma_ano}) — ${item.curso_nome}</span>
                    </div>
                </div>
                <div class="card-actions">
                    <button class="btn-icon-only" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem; opacity: 0.5; transition: opacity 0.2s;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                    </button>
                </div>
            </div>
            <div class="social-card-body">
                ${this.formatContent(item.content)}
            </div>
            <div class="social-card-footer">
                <div class="responsible-info">
                    ${respPhotoHtml}
                    <span><strong>${item.responsible_name}</strong></span>
                </div>
                <span class="event-time" title="${this.formatFullDate(item.timestamp)}">
                    ${this.formatRelativeTime(item.timestamp)}
                </span>
            </div>
        `;

        return card;
    }

    formatContent(content) {
        if (!content) return '';
        // Basic escaping and newline to <br> if needed, but CSS white-space: pre-wrap handles it
        return content;
    }

    formatRelativeTime(timestamp) {
        const date = new Date(timestamp.replace(' ', 'T'));
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) return 'Agora mesmo';
        
        const diffInMinutes = Math.floor(diffInSeconds / 60);
        if (diffInMinutes < 60) return `Há ${diffInMinutes} min`;
        
        const diffInHours = Math.floor(diffInMinutes / 60);
        if (diffInHours < 24) return `Há ${diffInHours}h`;
        
        const diffInDays = Math.floor(diffInHours / 24);
        if (diffInDays === 1) return 'Ontem';
        if (diffInDays < 7) return `Há ${diffInDays} dias`;
        
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    }

    formatFullDate(timestamp) {
        const date = new Date(timestamp.replace(' ', 'T'));
        return date.toLocaleString('pt-BR');
    }

    showError(message) {
        this.feedList.innerHTML = `
            <div style="padding: 3rem; text-align: center; color: var(--color-danger); background: var(--bg-surface); border-radius: var(--radius-xl); border: 1px solid var(--color-danger-light);">
                <div style="font-size: 2rem; margin-bottom: 1rem;">⚠️</div>
                <p>${message}</p>
                <button class="btn btn-secondary mt-md" onclick="window.socialFeed.loadFeed()">Tentar Novamente</button>
            </div>
        `;
    }
}

// Global instance initialization
document.addEventListener('DOMContentLoaded', () => {
    window.socialFeed = new SocialFeed();
});
