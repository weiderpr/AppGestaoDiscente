<div id="comp-online-users" class="comp-online-users">
    <div class="comp-online-header">
        <h3 class="comp-online-title">
            <span class="comp-online-icon">👥</span>
            Usuários Online
        </h3>
        <span id="comp-online-total" class="comp-online-badge">0 ativos</span>
    </div>
    
    <div id="comp-online-list" class="comp-online-list">
        <!-- Listagem via JS -->
        <div class="comp-online-loading">Carregando...</div>
    </div>

    <template id="tpl-online-user">
        <div class="online-user-card">
            <div class="online-user-avatar-wrapper">
                <img src="" alt="" class="online-user-avatar">
                <div class="online-user-dot"></div>
            </div>
            <div class="online-user-info">
                <div class="online-user-name"></div>
                <div class="online-user-role"></div>
            </div>
        </div>
    </template>

    <div id="comp-online-empty" class="comp-online-empty" style="display: none;">
        <span>Nenhum outro usuário online.</span>
    </div>
</div>

<link rel="stylesheet" href="/componentes/usuarios_online/style.css">
<script src="/componentes/usuarios_online/script.js" defer></script>
