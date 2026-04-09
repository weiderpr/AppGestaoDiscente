<?php
/**
 * Vértice Acadêmico — Students API (General Search)
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'search';
$db = getDB();
$inst = getCurrentInstitution();
$instId = (int)$inst['id'];

if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 3) {
        echo json_encode([]);
        exit;
    }

    try {
        // Search students by name or enrollment number
        $st = $db->prepare("
            SELECT a.id, a.nome, a.matricula, a.photo as foto,
                   t.description as turma_desc
            FROM alunos a
            JOIN turma_alunos ta ON ta.aluno_id = a.id
            JOIN turmas t ON ta.turma_id = t.id
            JOIN courses c ON t.course_id = c.id
            WHERE c.institution_id = ?
              AND a.deleted_at IS NULL
              AND (a.nome LIKE ? OR a.matricula LIKE ?)
            LIMIT 15
        ");
        $term = "%$q%";
        $st->execute([$instId, $term, $term]);
        
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro interno ao buscar alunos.']);
    }
    exit;
}

echo json_encode(['error' => 'Ação inválida.']);
