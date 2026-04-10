<?php
/**
 * Vértice Acadêmico — Entity Search API (Students, Turmas, Courses)
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
        $results = [];
        $term = "%$q%";

        // 1. Search Students
        $stAlunos = $db->prepare("
            SELECT a.id, a.nome as name, a.matricula, a.photo as foto,
                   t.description as subtext, t.id as turma_id, 'aluno' as type
            FROM alunos a
            JOIN turma_alunos ta ON ta.aluno_id = a.id
            JOIN turmas t ON ta.turma_id = t.id
            JOIN courses c ON t.course_id = c.id
            WHERE c.institution_id = ?
              AND a.deleted_at IS NULL
              AND (a.nome LIKE ? OR a.matricula LIKE ?)
            LIMIT 10
        ");
        $stAlunos->execute([$instId, $term, $term]);
        $results = array_merge($results, $stAlunos->fetchAll(PDO::FETCH_ASSOC));

        // 2. Search Turmas
        $stTurmas = $db->prepare("
            SELECT t.id, t.description as name, '' as matricula, '' as foto,
                   c.name as subtext, 'turma' as type
            FROM turmas t
            JOIN courses c ON t.course_id = c.id
            WHERE c.institution_id = ?
              AND t.description LIKE ?
            LIMIT 5
        ");
        $stTurmas->execute([$instId, $term]);
        $results = array_merge($results, $stTurmas->fetchAll(PDO::FETCH_ASSOC));

        // 3. Search Courses
        $stCourses = $db->prepare("
            SELECT c.id, c.name as name, '' as matricula, '' as foto,
                   'Curso' as subtext, 'curso' as type
            FROM courses c
            WHERE c.institution_id = ?
              AND c.name LIKE ?
            LIMIT 5
        ");
        $stCourses->execute([$instId, $term]);
        $results = array_merge($results, $stCourses->fetchAll(PDO::FETCH_ASSOC));
        
        echo json_encode($results);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro interno ao buscar entidades.']);
    }
    exit;
}

echo json_encode(['error' => 'Ação inválida.']);
