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

$params = [$courseId];
$where = "t.course_id = ? AND t.is_active = 1";

if ($user['profile'] === 'Professor') {
    $where .= " AND t.id IN (
        SELECT DISTINCT td.turma_id 
        FROM turma_disciplinas td 
        JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id 
        WHERE tdp.professor_id = ?
    )";
    $params[] = $user['id'];
}

$sql = "SELECT t.id, t.description 
        FROM turmas t 
        WHERE $where
        ORDER BY t.description";

$st = $db->prepare($sql);
$st->execute($params);
$turmas = $st->fetchAll();

echo json_encode($turmas);
