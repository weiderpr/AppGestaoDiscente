<?php
/**
 * AJAX - Comentários do aluno no conselho de classe
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$db = getDB();
$user = getCurrentUser();
$conselhoId = (int)($_REQUEST['conselho_id'] ?? 0);
$alunoId = (int)($_REQUEST['aluno_id'] ?? 0);

if (!$conselhoId || !$alunoId) {
    echo json_encode(['error' => 'Parâmetros inválidos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comentario = trim($_POST['comentario'] ?? '');
    
    if (empty($comentario)) {
        echo json_encode(['error' => 'Comentário não pode ser vazio']);
        exit;
    }
    
    $st = $db->prepare('INSERT INTO conselhos_comentarios (conselho_id, user_id, comentario) VALUES (?, ?, ?)');
    $st->execute([$conselhoId, $user['id'], $comentario]);
    
    echo json_encode(['success' => true]);
    exit;
}

// GET - Listar comentários
$st = $db->prepare("
    SELECT cc.*, u.name as user_name
    FROM conselhos_comentarios cc
    JOIN users u ON cc.user_id = u.id
    WHERE cc.conselho_id = ?
    ORDER BY cc.created_at DESC
");
$st->execute([$conselhoId]);
$comentarios = $st->fetchAll();

foreach ($comentarios as &$c) {
    $c['created_at'] = date('d/m/Y H:i', strtotime($c['created_at']));
}

echo json_encode(['comentarios' => $comentarios]);
