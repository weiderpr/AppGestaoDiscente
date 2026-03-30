<?php
/**
 * Vértice Acadêmico — Institution Middleware
 * Garante que uma instituição esteja selecionada na sessão para rotas pedagógicas.
 */

namespace App\Middleware;

use Core\Middleware;

class InstitutionMiddleware implements Middleware {
    /**
     * @param array $params Parâmetros da rota
     * @param callable $next O próximo passo na cadeia
     */
    public function handle(array $params, callable $next): void {
        // Reutiliza a lógica de autenticação global para consistência
        require_once __DIR__ . '/../../../includes/auth.php';

        $inst = getCurrentInstitution();

        if (!$inst['id']) {
            // Suporte para requisições AJAX
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false, 
                    'message' => 'Sessão expirada: Selecione uma instituição.'
                ]);
                exit;
            }

            // Redireciona para seleção de instituição com retorno para a URL atual
            $currentUrl = $_SERVER['REQUEST_URI'] ?? '/dashboard.php';
            header('Location: /select_institution.php?redirect=' . urlencode($currentUrl));
            exit;
        }

        $next($params);
    }
}
