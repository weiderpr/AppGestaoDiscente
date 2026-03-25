<?php
/**
 * Componente Loading Reutilizável
 * 
 * Uso:
 * include __DIR__ . '/loading.php';
 */

/**
 * Renderiza os estilos CSS do Loading
 */
function renderLoadingStyles() {
    ?>
    <link rel="stylesheet" href="/assets/css/components/loading.css">
    <?php
}

/**
 * Renderiza os scripts JS do Loading
 */
function renderLoadingScripts() {
    ?>
    <script src="/assets/js/components/Loading.js"></script>
    <?php
}

/**
 * Renderiza o HTML do componente de loading (overlay)
 */
function renderLoadingOverlay() {
    ?>
    <div id="loading-overlay" class="loading-overlay" style="display:none;">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p id="loading-message">Carregando...</p>
        </div>
    </div>
    <?php
}
