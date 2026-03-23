<?php
/**
 * AJAX - Carregar usuários por perfil para conselho
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$db = getDB();
$conselhoId = (int)($_GET['conselho_id'] ?? 0);
$turmaId = (int)($_GET['turma_id'] ?? 0);
$profile = trim($_GET['profile'] ?? '');

if (!$conselhoId || !$turmaId || !$profile) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, name, profile 
        FROM users 
        WHERE is_active = 1 AND profile = ?
        AND id NOT IN (
            SELECT professor_id FROM turma_disciplina_professores tdp 
            JOIN turma_disciplinas td ON tdp.turma_disciplina_id = td.id 
            WHERE td.turma_id = ?
        )
        ORDER BY name";

$st = $db->prepare($sql);
$st->execute([$profile, $turmaId]);
$usuarios = $st->fetchAll();

echo json_encode($usuarios);
