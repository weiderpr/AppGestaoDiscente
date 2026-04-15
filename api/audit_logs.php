<?php
/**
 * Vértice Acadêmico — API: Logs de Auditoria
 * Endpoint JSON para carregamento dinâmico e scroll infinito.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/AuditService.php';

// Verificação de permissão
if (!hasDbPermission('audit.view_logs', false)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.']);
    exit;
}

use App\Services\AuditService;

try {
    $auditService = new AuditService();
    
    // Parâmetros de Filtro
    $filters = [
        'date_from'  => $_GET['date_from'] ?? null,
        'date_to'    => $_GET['date_to'] ?? null,
        'user_id'    => !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null,
        'table_name' => !empty($_GET['table_name']) ? $_GET['table_name'] : null,
        'inst_id'    => isset($_GET['inst_id']) ? ($_GET['inst_id'] === '' ? 0 : (int)$_GET['inst_id']) : null
    ];
    
    // Paginação
    $limit  = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $logs = $auditService->getLogs($filters, $limit, $offset);
    
    echo json_encode([
        'success' => true,
        'data'    => $logs,
        'count'   => count($logs),
        'offset'  => $offset,
        'limit'   => $limit
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
