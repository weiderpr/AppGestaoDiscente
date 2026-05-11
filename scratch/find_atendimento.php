<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

$sql = "SELECT id, titulo, aluno_id, turma_id, status, is_archived, created_at, deleted_at 
        FROM gestao_atendimentos 
        WHERE (titulo LIKE '%Coordenador%' OR descricao_publica LIKE '%Coordenador%')
        AND created_at LIKE '2026-03-31 17:%'";

$st = $db->query($sql);
$results = $st->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results, JSON_PRETTY_PRINT);
