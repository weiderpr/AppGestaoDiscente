<?php
/**
 * Vértice Acadêmico — Auth Middleware
 */

namespace App\Middleware;

use Core\Middleware;

class AuthMiddleware implements Middleware {
    public function handle(array $params, callable $next): void {
        require_once __DIR__ . '/../../../includes/auth.php';
        
        if (!isLoggedIn()) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
                exit;
            }
            
            header('Location: /login.php');
            exit;
        }
        
        $next($params);
    }
}
