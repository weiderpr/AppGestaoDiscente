<?php
/**
 * Vértice Acadêmico — API: Atualizar tema do usuário (AJAX)
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Não autenticado']);
    http_response_code(401);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$theme = $input['theme'] ?? '';

if (!in_array($theme, ['light', 'dark'])) {
    echo json_encode(['error' => 'Tema inválido']);
    http_response_code(400);
    exit;
}

$db   = getDB();
$stmt = $db->prepare('UPDATE users SET theme = ? WHERE id = ?');
$stmt->execute([$theme, $_SESSION['user_id']]);
$_SESSION['user_theme'] = $theme;

echo json_encode(['success' => true, 'theme' => $theme]);
