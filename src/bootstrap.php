<?php
/**
 * Vértice Acadêmico — Bootstrap
 * Inicializa o autoloader e configurações básicas
 */

require_once __DIR__ . '/Core/Autoloader.php';
require_once __DIR__ . '/../includes/auth.php';

date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
