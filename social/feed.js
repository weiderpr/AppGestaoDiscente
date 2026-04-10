/**
 * Vértice Acadêmico — Social Feed Component Logic
 * Manages resizing, filtering, infinite scroll, and inline post creation.
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
        this.setupInlineCreator();
        
        // Listen for window resize to handle layout shifts
        window.addEventListener('resize', () => this.handleResponsiveLayout());
    }

    // --- Inline Post Creator ---

    setupInlineCreator() {
        const wrapper = document.querySelector('.social-post-creator-wrapper');
        const textarea = document.getElementById('inline-post-textarea');
        const actions = document.getElementById('inline-post-actions');

        if (!wrapper || !textarea || !actions) return;

        textarea.addEventListener('focus', () => {
            wrapper.classList.add('is-active');
            actions.style.display = 'flex';
        });

        // Mentions setup for inline textarea
        this.setupMentions(textarea);
    }

    setupMentions(textarea) {
        const results = document.getElementById('mention-results');
        if (!textarea || !results) return;

        let mentionActive = false;
        let mentionStart = -1;

        textarea.addEventListener('input', (e) => {
            const val = textarea.value;
            const pos = textarea.selectionStart;
            const charBefore = val.charAt(pos - 1);

            if (charBefore === '@') {
                mentionActive = true;
                mentionStart = pos;
            }

            if (mentionActive) {
                const query = val.substring(mentionStart, pos).trim();
                
                if (query.includes(' ') || pos < mentionStart) {
                    mentionActive = false;
                    results.style.display = 'none';
                    return;
                }

                if (query.length >= 2) {
                    this.searchMentionUsers(query);
                } else {
                    results.style.display = 'none';
                }
            }

            // Simple auto-resize (optional)
            if (textarea.scrollHeight > 40 && textarea.scrollHeight < 200) {
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            }
        });

        textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                results.style.display = 'none';
                mentionActive = false;
                if (!textarea.value.trim()) this.resetPostArea();
            }
        });
    }

    async searchMentionUsers(query) {
        const results = document.getElementById('mention-results');
        try {
            const response = await fetch(`/api/students.php?action=search&q=${encodeURIComponent(query)}`);
            const data = await response.json();
            const students = data.filter(item => item.type === 'aluno');
            this.renderMentionResults(students);
        } catch (error) {
            console.error('Mention search error:', error);
        }
    }

    renderMentionResults(items) {
        const results = document.getElementById('mention-results');
        if (items.length === 0) {
            results.style.display = 'none';
            return;
        }

        results.innerHTML = items.map(s => `
            <div class="search-result-item" data-id="${s.id}" data-name="${s.name}" data-turma="${s.turma_id}">
                <img src="/${s.foto || 'assets/img/avatar-placeholder.png'}" onerror="this.src='/assets/img/avatar-placeholder.png'">
                <div class="search-result-info">
                    <span class="search-result-name">${s.name}</span>
                    <span class="search-result-desc">${s.subtext || ''}</span>
                </div>
            </div>
        `).join('');

        results.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', () => {
                this.selectMention({
                    id: item.dataset.id,
                    name: item.dataset.name,
                    turmaId: item.dataset.turma
                });
                results.style.display = 'none';
            });
        });

        results.style.display = 'block';
    }

    selectMention(student) {
        const textarea = document.getElementById('inline-post-textarea');
        const val = textarea.value;
        const pos = textarea.selectionStart;
        
        const lastAt = val.lastIndexOf('@', pos);
        if (lastAt !== -1) {
            const newVal = val.substring(0, lastAt) + student.name + ' ' + val.substring(pos);
            textarea.value = newVal;
        }

        document.getElementById('mention-aluno-id').value = student.id;
        document.getElementById('mention-turma-id').value = student.turmaId;

        const container = document.getElementById('selected-mention-container');
        const tagName = document.getElementById('mention-tag-name');
        if (container && tagName) {
            tagName.textContent = student.name;
            container.style.display = 'block';
        }

        textarea.focus();
    }

    clearMention() {
        document.getElementById('mention-aluno-id').value = '';
        document.getElementById('mention-turma-id').value = '';
        const container = document.getElementById('selected-mention-container');
        if (container) container.style.display = 'none';
        
        const textarea = document.getElementById('inline-post-textarea');
        if (textarea) textarea.focus();
    }

    resetPostArea() {
        const wrapper = document.querySelector('.social-post-creator-wrapper');
        const textarea = document.getElementById('inline-post-textarea');
        const actions = document.getElementById('inline-post-actions');
        
        if (wrapper && textarea && actions) {
            textarea.value = '';
            textarea.style.height = '40px';
            wrapper.classList.remove('is-active');
            actions.style.display = 'none';
            this.clearMention();
        }
    }

    async submitPost() {
        const textarea = document.getElementById('inline-post-textarea');
        if (!textarea) return;

        const content = textarea.value.trim();
        const alunoId = document.getElementById('mention-aluno-id').value;
        const turmaId = document.getElementById('mention-turma-id').value;
        const btn = document.getElementById('btn-publish-post');

        if (!alunoId) {
            if (typeof showInfo === 'function') showInfo('Mencione um aluno com @ para publicar.');
            else alert('Mencione um aluno com @.');
            return;
        }

        if (!content) return;

        try {
            btn.disabled = true;
            btn.textContent = '...';

            const formData = new FormData();
            formData.append('action', 'create_comment');
            formData.append('aluno_id', alunoId);
            formData.append('turma_id', turmaId);
            formData.append('conteudo', content);

            // Fetch CSRF Token (from meta or window global)
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken;
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            const response = await fetch('/social/feed_ajax.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.status === 'success') {
                if (typeof showSuccess === 'function') showSuccess('Postado!');
                this.resetPostArea();
                this.loadFeed(false); // Reload feed from start
            } else {
                if (typeof showError === 'function') showError(result.message);
                else alert(result.message);
            }
        } catch (error) {
            console.error('Submit Post Error:', error);
            if (typeof showError === 'function') showError('Erro de conexão com o servidor.');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Publicar';
            }
        }
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

        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !results.contains(e.target)) {
                results.style.display = 'none';
            }
        });
    }

    renderSearchResults(items) {
        const results = document.getElementById('aluno-search-results');
        if (items.length === 0) {
            results.innerHTML = '<div style="padding: 0.5rem 0.75rem; font-size: 0.75rem; color: var(--text-muted); text-align: center;">Nenhum resultado encontrado.</div>';
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
            rootMargin: '200px', 
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
        if (this.sidebar) {
            if (window.innerWidth <= 768) {
                this.sidebar.style.width = '100%';
            } else {
                this.loadSidebarWidth();
            }
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

        const currentUserId = parseInt(document.documentElement.dataset.userId || 0);
        const isOwner = parseInt(item.responsible_id) === currentUserId;

        card.innerHTML = `
            <div class="social-card-header">
                <div class="social-card-user">
                    ${alunoPhotoHtml}
                    <div class="aluno-info">
                        <div class="aluno-title-row">
                            <span class="aluno-name" title="${item.aluno_nome}">${item.aluno_nome}</span>
                            <span class="event-badge badge-${item.badge_type}">${item.badge_text}</span>
                        </div>
                        <span class="aluno-meta">${item.turma_desc} (${item.turma_ano}) — ${item.curso_nome}</span>
                    </div>
                </div>
                <div class="social-card-options">
                    <button class="btn-card-option" onclick="window.socialFeed.toggleCardMenu(this, ${item.event_id})">⋮</button>
                    <div class="card-option-menu" id="card-menu-${item.event_id}">
                        <button onclick="window.socialFeed.viewStudent(${item.aluno_id})">👤 Ver Perfil</button>
                        ${isOwner && item.event_type === 'comentario_professor' ? `
                            <button class="delete-option" onclick="window.socialFeed.deleteComment(${item.event_id})">🗑️ Excluir</button>
                        ` : ''}
                    </div>
                </div>
            </div>
            <div class="social-card-body" style="white-space: pre-wrap;">${this.formatContent(item.content)}</div>
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

    toggleCardMenu(btn, id) {
        const menu = document.getElementById(`card-menu-${id}`);
        if (!menu) return;

        const isVisible = menu.classList.contains('show');
        document.querySelectorAll('.card-option-menu').forEach(m => m.classList.remove('show'));
        
        if (!isVisible) {
            menu.classList.add('show');
        }

        const closeMenu = (e) => {
            if (!btn.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('show');
                document.removeEventListener('click', closeMenu);
            }
        };
        document.addEventListener('click', closeMenu);
    }

    async deleteComment(id) {
        if (!confirm('Deseja realmente excluir este comentário?')) return;

        try {
            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('id', id);
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken;
            if (csrfToken) formData.append('csrf_token', csrfToken);

            const response = await fetch('/social/feed_ajax.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();
            if (result.status === 'success') {
                if (typeof showSuccess === 'function') showSuccess('Comentário excluído.');
                this.loadFeed(false);
            } else {
                throw new Exception(result.message);
            }
        } catch (error) {
            console.error('Delete Error:', error);
            if (typeof showError === 'function') showError(error.message || 'Erro ao excluir comentário.');
        }
    }

    formatContent(content) {
        if (!content) return '';
        return content.trim();
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

// Initialization logic to prevent race conditions with defer/async scripts
const initSocialFeed = () => {
    if (!window.socialFeed) {
        window.socialFeed = new SocialFeed();
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSocialFeed);
} else {
    initSocialFeed();
}
