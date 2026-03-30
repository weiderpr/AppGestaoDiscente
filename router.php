<?php
/**
 * Vértice Acadêmico — Front Controller / Router
 */

require_once __DIR__ . '/src/bootstrap.php';

// Carrega as rotas e obtém a instância do Router
$router = require_once __DIR__ . '/src/routes.php';

// Captura o método e URI da requisição
$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'];

// Trata subdiretórios: se o script estiver em /subdiretorio/router.php,
// remove "/subdiretorio" do início da URI para que o Router combine apenas o path relativo.
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// Despacha para o handler correspondente
$router->dispatch($method, $uri);
