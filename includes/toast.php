<?php
/**
 * Componente Toast Reutilizável
 * 
 * Uso:
 * include __DIR__ . '/toast.php';
 * 
 * Funções disponíveis:
 * - showToast($message, $type, $duration) - exibe toast via PHP (renderiza JS)
 */

/**
 * Renderiza os estilos CSS do Toast
 */
function renderToastStyles() {
    ?>
    <link rel="stylesheet" href="/assets/css/components/toast.css">
    <?php
}

/**
 * Renderiza os scripts JS do Toast
 */
function renderToastScripts() {
    ?>
    <script src="/assets/js/components/Toast.js"></script>
    <script>
    // Função para exibir toast via PHP
    function phpShowToast(message, type, duration) {
        if (typeof Toast !== 'undefined') {
            Toast.show(message, type, duration);
        } else {
            alert(message);
        }
    }
    </script>
    <?php
}

/**
 * Exibe toast após redirect (via session/cookie)
 */
function renderToastMessages() {
    if (!isset($_SESSION['toast_messages'])) {
        return;
    }
    
    $messages = $_SESSION['toast_messages'];
    unset($_SESSION['toast_messages']);
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php foreach ($messages as $msg): ?>
        phpShowToast(<?= json_encode($msg['message']) ?>, <?= json_encode($msg['type']) ?>, <?= $msg['duration'] ?? 4000 ?>);
        <?php endforeach; ?>
    });
    </script>
    <?php
}

/**
 * Adiciona mensagem toast para exibir após redirect
 */
function addToastMessage($message, $type = 'info', $duration = 4000) {
    if (!isset($_SESSION['toast_messages'])) {
        $_SESSION['toast_messages'] = [];
    }
    $_SESSION['toast_messages'][] = [
        'message' => $message,
        'type' => $type,
        'duration' => $duration
    ];
}
