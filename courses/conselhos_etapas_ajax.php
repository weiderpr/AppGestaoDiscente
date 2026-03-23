<?php
/**
 * AJAX - Carregar etapas por turma
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$db = getDB();
$turmaId = (int)($_GET['turma_id'] ?? 0);

if (!$turmaId) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, description 
        FROM etapas 
        WHERE turma_id = ? AND is_active = 1 
        ORDER BY id";

$st = $db->prepare($sql);
$st->execute([$turmaId]);
$etapas = $st->fetchAll();

echo json_encode($etapas);
