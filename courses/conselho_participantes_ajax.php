<?php
/**
 * AJAX - Buscar usuários para lista de presença do Conselho
 * Traz usuários vinculados à instituição atual (pelo input text).
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$db = getDB();
$inst = getCurrentInstitution();
$instId = $inst['id'];

$search = trim($_GET['search'] ?? '');

if (mb_strlen($search) < 2 || !$instId) {
    echo json_encode(['status' => 'error', 'message' => 'Termo de busca muito curto ou erro de inst']);
    exit;
}

try {
    // Busca os usuários ativos que fazem parte da instituição
    $sql = "
        SELECT DISTINCT u.id, u.name, u.profile, u.photo
        FROM users u
        JOIN user_institutions ui ON u.id = ui.user_id
        WHERE ui.institution_id = ? 
          AND u.is_active = 1
          AND (u.name LIKE ? OR u.email LIKE ?)
        ORDER BY u.name
        LIMIT 15
    ";
    
    $term = "%{$search}%";
    $st = $db->prepare($sql);
    $st->execute([$instId, $term, $term]);
    $usuarios = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $usuarios
    ]);
} catch (Exception $e) {
    error_log("Erro em busca conselho participantes: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erro interno do servidor.']);
}
