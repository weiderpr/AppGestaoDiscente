<?php
/**
 * Vértice Acadêmico — Role Middleware
 */

namespace App\Middleware;

use Core\Middleware;
use App\Services\PermissionService;

class RoleMiddleware implements Middleware {
    private string $resource = '';
    private PermissionService $permissionService;

    public function __construct() {
        $this->permissionService = new PermissionService();
    }

    public function setResource(string $resource): void {
        $this->resource = $resource;
    }

    public function handle(array $params, callable $next): void {
        require_once __DIR__ . '/../../../includes/auth.php';
        $user = getCurrentUser();
        
        if (!$user) {
            $this->deny();
        }

        // Se o recurso não estiver definido, permite por padrão (ou nega, dependendo da política)
        if (!$this->resource) {
            $next($params);
            return;
        }

        // Admin sempre tem acesso a tudo (hardcoded como fallback de segurança)
        if ($user['profile'] === 'Administrador') {
            $next($params);
            return;
        }

        // Verifica no banco de dados
        if (!$this->permissionService->canAccess($user['profile'], $this->resource)) {
            $this->deny();
        }
        
        $next($params);
    }

    private function deny(): void {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Acesso negado: Perfil insuficiente.']);
            exit;
        }
        
        header('Location: /dashboard.php?error=access_denied');
        exit;
    }
}
