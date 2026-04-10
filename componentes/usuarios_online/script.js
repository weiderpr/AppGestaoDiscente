/**
 * Vértice Acadêmico — Lógica do Componente de Usuários Online
 */

(function() {
    const COMP_ID = 'comp-online-users';
    const API_URL = '/api/users/online.php';
    const POLLING_INTERVAL = 60000; // 60s

    const elements = {
        container: document.getElementById('comp-online-users'),
        list: document.getElementById('comp-online-list'),
        total: document.getElementById('comp-online-total'),
        empty: document.getElementById('comp-online-empty'),
        template: document.getElementById('tpl-online-user')
    };

    if (!elements.container) return;

    /**
     * Busca dados da API e renderiza
     */
    async function updateOnlineUsers() {
        try {
            const response = await fetch(API_URL, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!response.ok) throw new Error('Falha na API');
            
            const data = await response.json();
            
            if (data.success) {
                renderList(data.users);
                elements.total.textContent = `${data.count} ativos`;
            }
        } catch (error) {
            console.error('Erro ao atualizar usuários online:', error);
        }
    }

    /**
     * Renderiza a lista de usuários no DOM
     */
    function renderList(users) {
        // Limpa lista atual
        elements.list.innerHTML = '';
        
        if (users.length === 0) {
            elements.empty.style.display = 'block';
            return;
        }

        elements.empty.style.display = 'none';

        users.forEach(user => {
            const clone = elements.template.content.cloneNode(true);
            const card = clone.querySelector('.online-user-card');
            const img = clone.querySelector('.online-user-avatar');
            const nameEl = clone.querySelector('.online-user-name');
            const roleEl = clone.querySelector('.online-user-role');

            // Foto ou placeholder
            img.src = user.photo ? `/${user.photo}` : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.name) + '&background=f1f5f9&color=64748b&bold=true';
            img.alt = user.name;
            
            nameEl.textContent = user.name;
            roleEl.textContent = user.profile;

            elements.list.appendChild(clone);
        });
    }

    // Inicializa
    updateOnlineUsers();

    // Configura Polling
    setInterval(updateOnlineUsers, POLLING_INTERVAL);

})();
