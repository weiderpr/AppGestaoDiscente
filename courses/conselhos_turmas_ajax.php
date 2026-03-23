<?php
/**
 * AJAX - Carregar turmas por curso
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$db = getDB();
$inst = getCurrentInstitution();
$courseId = (int)($_GET['course_id'] ?? 0);
$user = getCurrentUser();

if (!$inst['id'] || !$courseId) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT t.id, t.description 
        FROM turmas t 
        WHERE t.course_id = ? AND t.is_active = 1 
        ORDER BY t.description";

$st = $db->prepare($sql);
$st->execute([$courseId]);
$turmas = $st->fetchAll();

echo json_encode($turmas);
