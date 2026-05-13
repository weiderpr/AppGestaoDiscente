<?php
/**
 * Vértice Acadêmico — AJAX Handler para Permissões Individuais
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/PermissionService.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
$perms = $_POST['perms'] ?? []; // format: [resource] = 1

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Usuário não informado.']);
    exit;
}

try {
    $permissionService = new \App\Services\PermissionService();
    
    // Primeiro, precisamos saber quais recursos estão sendo gerenciados.
    // Para simplificar, vamos resetar as permissões individuais deste usuário para os recursos enviados ou cadastrados.
    // Uma abordagem melhor é deletar todas as permissões individuais deste usuário e reinserir as marcadas como 1.
    
    $db = getDB();
    $inst = getCurrentInstitution();
    $instId = $inst['id'];

    if (!$instId) throw new Exception('Instituição não identificada.');

    $db->beginTransaction();

    // Remove todas as permissões individuais atuais deste usuário na instituição
    $stmt = $db->prepare("DELETE FROM user_individual_permissions WHERE user_id = ? AND institution_id = ?");
    $stmt->execute([$userId, $instId]);

    // Insere as novas permissões marcadas como 1
    foreach ($perms as $resource => $val) {
        if ($val == 1) {
            $permissionService->updateUserPermission($userId, $resource, true);
        }
    }

    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
