<?php
/**
 * Demo - Componentes de UX/UI
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Demo - Componentes UX/UI';

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .demo-section {
        margin-bottom: 2rem;
    }
    .demo-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .demo-section h3 {
        margin-bottom: 1rem;
        color: var(--text-primary);
    }
</style>

<div class="page-header">
    <h1>🎨 Demo - Componentes UX/UI</h1>
    <p>Teste os novos componentes implementados</p>
</div>

<div class="demo-section">
    <h3>📢 Toast Notifications</h3>
    <div class="demo-buttons">
        <button class="btn btn-success" onclick="showSuccess('Operação realizada com sucesso!')">Success</button>
        <button class="btn btn-danger" onclick="showError('Erro ao processar dados')">Error</button>
        <button class="btn btn-warning" onclick="showWarning('Atenção: verifique os dados')">Warning</button>
        <button class="btn btn-primary" onclick="showInfo('Informações atualizadas')">Info</button>
    </div>
</div>

<div class="demo-section">
    <h3>⏳ Loading States</h3>
    <div class="demo-buttons">
        <button class="btn btn-primary" onclick="testSpinner()">Spinner</button>
        <button class="btn btn-primary" onclick="testSkeleton()">Skeleton Table</button>
    </div>
    <div id="skeleton-demo" style="margin-top:1rem;"></div>
</div>

<div class="demo-section">
    <h3>📋 Modal</h3>
    <div class="demo-buttons">
        <button class="btn btn-primary" onclick="testModal()">Abrir Modal</button>
        <button class="btn btn-primary" onclick="testConfirm()">Confirm Dialog</button>
        <button class="btn btn-primary" onclick="testAlert()">Alert</button>
    </div>
</div>

<script>
function testSpinner() {
    showLoading('Carregando dados...');
    setTimeout(() => {
        hideLoading();
        showSuccess('Dados carregados!');
    }, 2000);
}

function testSkeleton() {
    const container = document.getElementById('skeleton-demo');
    container.innerHTML = Skeleton.table(4, 3);
    
    setTimeout(() => {
        container.innerHTML = `
            <table class="data-table">
                <thead><tr><th>Coluna 1</th><th>Coluna 2</th><th>Coluna 3</th></tr></thead>
                <tbody>
                    <tr><td>Dado 1</td><td>Dado 2</td><td>Dado 3</td></tr>
                    <tr><td>Dado 4</td><td>Dado 5</td><td>Dado 6</td></tr>
                    <tr><td>Dado 7</td><td>Dado 8</td><td>Dado 9</td></tr>
                    <tr><td>Dado 10</td><td>Dado 11</td><td>Dado 12</td></tr>
                </tbody>
            </table>
        `;
        showSuccess('Tabela carregada!');
    }, 2500);
}

function testModal() {
    showModal({
        title: 'Exemplo de Modal',
        content: `
            <p>Este é um exemplo de modal reutilizável.</p>
            <p>Você pode incluir formulários, textos, ou qualquer conteúdo HTML.</p>
            <div style="margin-top:1rem;padding:1rem;background:var(--bg-secondary);border-radius:8px;">
                <strong>💡 Dica:</strong> Os modais suportam diversos tamanhos (sm, md, lg, xl)
            </div>
        `,
        size: 'md',
        buttons: [
            { text: 'Fechar', class: 'btn-secondary', action: () => {} },
            { text: 'Entendi!', class: 'btn-primary', action: () => showSuccess('Modal confirmado!') }
        ]
    });
}

function testConfirm() {
    confirmModal({
        title: 'Confirmar Exclusão',
        message: 'Tem certeza que deseja excluir este item? Esta ação não pode ser desfeita.',
        confirmText: 'Excluir',
        onConfirm: () => showSuccess('Item excluído!'),
        onCancel: () => showInfo('Operação cancelada')
    });
}

function testAlert() {
    alertModal({
        title: 'Aviso',
        message: 'Operação concluída com sucesso!',
        onClose: () => console.log('Alert fechado')
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
