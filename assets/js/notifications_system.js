/**
 * Vértice Acadêmico — Sistema de Notificações (Frontend Logic)
 */

const NotificationSystem = {
    stackId: 'notifSystemStack',
    fetchInterval: 30000, // 30 segundos

    init() {
        // Inicializa o listener do menu SEMPRE, para permitir re-ativar
        this.initToggleListener();

        // Se desativado nas preferências do usuário, não carrega a estrutura agora
        if (window.AppConfig && window.AppConfig.exibirNotificacoes === 0) {
            return;
        }

        this.renderContainer();
        this.fetchNotifications();
        
        // Polling (Opcional)
        // setInterval(() => this.fetchNotifications(), this.fetchInterval);
    },

    initToggleListener() {
        const toggle = document.getElementById('notif-visibility-toggle');
        if (!toggle) return;

        toggle.addEventListener('change', async (e) => {
            const isVisible = e.target.checked;
            
            // Salva no Banco de Dados via API
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_visibility');
                formData.append('status', isVisible ? 1 : 0);
                if (window.csrfToken) formData.append('csrf_token', window.csrfToken);

                const response = await fetch('/api/notifications_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    if (isVisible) {
                        // Re-inicializa
                        window.AppConfig.exibirNotificacoes = 1;
                        this.renderContainer();
                        this.fetchNotifications();
                    } else {
                        // Remove tudo imediatamente
                        window.AppConfig.exibirNotificacoes = 0;
                        const stack = document.getElementById(this.stackId);
                        if (stack) stack.remove();
                    }
                }
            } catch (error) {
                console.error('Erro ao alternar visibilidade:', error);
            }
        });
    },

    renderContainer() {
        if (!document.getElementById(this.stackId)) {
            const stack = document.createElement('div');
            stack.id = this.stackId;
            stack.className = 'notif-system-stack';
            document.body.appendChild(stack);
        }
    },

    async fetchNotifications() {
        try {
            const response = await fetch('/api/notifications_ajax.php?action=fetch');
            const result = await response.json();

            if (result.success) {
                const stack = document.getElementById(this.stackId);
                // Limpar para não duplicar no re-fetch
                stack.innerHTML = ''; 
                result.data.forEach(notif => this.display(notif));
            }
        } catch (error) {
            console.error('Erro ao carregar notificações:', error);
        }
    },

    display(notif) {
        const stack = document.getElementById(this.stackId);
        if (!stack) return;

        if (document.querySelector(`.notif-card[data-id="${notif.id}"]`)) return;

        // Verificar permissão para exibir o link
        const isAdmin = window.AppPermissions && window.AppPermissions['IS_ADMIN'] === 1;
        const hasSpecificPerm = window.AppPermissions && window.AppPermissions[notif.required_permission] == 1;
        const canSeeLink = !notif.required_permission || isAdmin || hasSpecificPerm;

        const card = document.createElement('div');
        card.className = `notif-card type-${notif.tipo}`;
        card.setAttribute('data-id', notif.id);

        if (notif.aluno_id || notif.turma_id) {
            card.classList.add('aluno-turma');
        } else {
            card.classList.add('sistema-global');
        }

        const date = new Date(notif.created_at).toLocaleString();

        card.innerHTML = `
            <div class="notif-header">
                <span class="notif-title">${notif.titulo}</span>
                <button class="notif-btn-read" onclick="NotificationSystem.markAsRead(${notif.id}, this)">Lido</button>
            </div>
            <div class="notif-body">${notif.mensagem}</div>
            <div class="notif-footer">
                <span>${date}</span>
                ${(notif.link_acao && canSeeLink) ? `<a href="${notif.link_acao}" class="notif-link">Ver detalhes</a>` : ''}
            </div>
        `;

        stack.appendChild(card);
    },

    async markAsRead(id, btn) {
        const card = btn.closest('.notif-card');
        card.classList.add('removing');

        try {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('id', id);
            
            // Segurança: Token CSRF para requisições POST
            if (window.csrfToken) {
                formData.append('csrf_token', window.csrfToken);
            }

            const response = await fetch('/api/notifications_ajax.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                setTimeout(() => card.remove(), 300);
            } else {
                card.classList.remove('removing');
                // Se for erro de CSRF, pode ser necessário recarregar a página
                if (result.message && result.message.includes('CSRF')) {
                    console.error('Erro de Segurança (CSRF). Recarregando...');
                    // window.location.reload();
                }
                console.error('Erro ao marcar como lida:', result.message);
            }
        } catch (error) {
            card.classList.remove('removing');
            console.error('Erro na requisição mark_read:', error);
        }
    },

    /**
     * Função Global pushNotification
     */
    async push(data) {
        try {
            const formData = new FormData();
            formData.append('action', 'push');
            formData.append('titulo', data.titulo);
            formData.append('mensagem', data.mensagem);
            formData.append('tipo', data.tipo || 'Info');
            formData.append('aluno_id', data.aluno_id || '');
            formData.append('turma_id', data.turma_id || '');
            formData.append('link_acao', data.link_acao || '');
            formData.append('required_permission', data.required_permission || '');

            // Segurança: Token CSRF para requisições POST
            if (window.csrfToken) {
                formData.append('csrf_token', window.csrfToken);
            }

            const response = await fetch('/api/notifications_ajax.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                // Se o push foi com sucesso e o usuário tem permissão de ver, ele aparecerá no próximo fetch ou podemos exibir direto
                if (result.id) {
                    data.id = result.id;
                    data.created_at = new Date().toISOString();
                    this.display(data);
                }
            }
        } catch (error) {
            console.error('Erro ao enviar notificação:', error);
        }
    }
};

// Expondo globalmente
window.pushNotification = (data) => NotificationSystem.push(data);

// Inicializar no carregamento
document.addEventListener('DOMContentLoaded', () => NotificationSystem.init());
