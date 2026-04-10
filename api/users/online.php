<?php
/**
 * Vértice Acadêmico — API: Usuários Online
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/UserService.php';

header('Content-Type: application/json');

// Exige login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$inst = getCurrentInstitution();
if (!$inst['id']) {
    http_response_code(400);
    echo json_encode(['error' => 'Instituição não selecionada']);
    exit;
}

try {
    $userService = new \App\Services\UserService();
    $onlineUsers = $userService->getOnlineUsers((int)$inst['id'], (int)$_SESSION['user_id']);
    $totalOnline = $userService->countOnlineUsers((int)$inst['id']);

    echo json_encode([
        'success' => true,
        'count'   => $totalOnline,
        'users'   => $onlineUsers
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar usuários online: ' . $e->getMessage()
    ]);
}
