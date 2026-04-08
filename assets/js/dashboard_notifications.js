/**
 * Vértice Acadêmico — Lógica para Notificações no Dashboard (Modo Fallback)
 */
const DashboardNotifications = {
    init() {
        // Encontrar todos os botões "Lido" no painel do dashboard
        const buttons = document.querySelectorAll('.dash-notif-btn-read');
        buttons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = btn.getAttribute('data-id');
                this.markAsRead(id, btn);
            });
        });
    },

    async markAsRead(id, btn) {
        const card = btn.closest('.dash-notif-card');
        card.style.opacity = '0.5';
        card.style.pointerEvents = 'none';

        try {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('id', id);
            if (window.csrfToken) formData.append('csrf_token', window.csrfToken);

            const response = await fetch('/api/notifications_ajax.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                // Efeito de saída
                card.style.transform = 'translateX(20px)';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    this.updateCount();
                    this.checkEmpty();
                }, 300);
            } else {
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
                console.error('Erro ao marcar como lida:', result.message);
            }
        } catch (error) {
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
            console.error('Erro na requisição:', error);
        }
    },

    updateCount() {
        const countBadge = document.querySelector('.dash-notif-count');
        if (countBadge) {
            const current = parseInt(countBadge.textContent);
            if (current > 0) {
                countBadge.textContent = current - 1;
                if (current - 1 === 0) countBadge.remove();
            }
        }
    },

    checkEmpty() {
        const list = document.querySelector('.dash-notif-list');
        if (list && list.children.length === 0) {
            list.innerHTML = `
                <div class="dash-notif-empty">
                    <span>✨ Todas as notificações foram lidas!</span>
                </div>
            `;
        }
    }
};

// Inicializar
document.addEventListener('DOMContentLoaded', () => DashboardNotifications.init());
