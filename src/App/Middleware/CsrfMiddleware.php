<?php
/**
 * Vértice Acadêmico — CSRF Middleware
 */

namespace App\Middleware;

use Core\Middleware;

class CsrfMiddleware implements Middleware {
    /**
     * @param array $params Parâmetros da rota
     * @param callable $next O próximo passo na cadeia
     */
    public function handle(array $params, callable $next): void {
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Métodos seguros (GET, HEAD, OPTIONS) não precisam de CSRF
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            $next($params);
            return;
        }

        require_once __DIR__ . '/../../includes/csrf.php';

        // Busca o token no corpo (form) ou no header (AJAX)
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!csrf_verify($token)) {
            $this->forbidden();
            return;
        }

        $next($params);
    }

    private function forbidden(): void {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => 'Erro de segurança: Token CSRF inválido ou expirado.'
            ]);
            exit;
        }

        http_response_code(403);
        echo '<h1>403 Forbidden</h1>';
        echo '<p>Erro de segurança: Validação CSRF falhou. Por favor, volte e recarregue a página.</p>';
        exit;
    }
}
