<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/AlunoService.php';

requireLogin();

$user = getCurrentUser();
header('Content-Type: application/json');

if (!$user || !hasDbPermission('students.comments', false)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado: Seu perfil não tem permissão para realizar comentários.']);
    exit;
}

$db = getDB();
$alunoService = new \App\Services\AlunoService();
$professorId = $user['id'];

// GET: Buscar comentários
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $alunoId = (int)($_GET['aluno_id'] ?? 0);
    $turmaId = (int)($_GET['turma_id'] ?? 0);
    
    if (!$alunoId || !$turmaId) {
        echo json_encode(['error' => 'Parâmetros inválidos (Aluno ou Turma não informados)']);
        exit;
    }
    
    try {
        // Controle de Acesso por Perfil
        $isProfessor = ($user['profile'] === 'Professor');
        $isCoord = ($user['profile'] === 'Coordenador');
        $isAdmin = ($user['profile'] === 'Administrador');
        $isPedagogo = in_array($user['profile'], ['Pedagogo', 'Assistente Social', 'Psicólogo']);

        if ($isProfessor) {
            $stCheck = $db->prepare('
                SELECT 1 FROM turma_disciplinas td
                JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id
                WHERE td.turma_id = ? AND tdp.professor_id = ? LIMIT 1
            ');
            $stCheck->execute([$turmaId, $professorId]);
            if (!$stCheck->fetch()) {
                echo json_encode(['error' => 'Acesso negado: Você não leciona nesta turma']);
                exit;
            }
        } elseif ($isCoord) {
            $stCheckCoord = $db->prepare('
                SELECT 1 FROM course_coordinators cc
                JOIN courses c ON c.id = cc.course_id
                JOIN turmas t ON t.course_id = c.id
                WHERE t.id = ? AND cc.user_id = ? LIMIT 1
            ');
            $stCheckCoord->execute([$turmaId, $professorId]);
            if (!$stCheckCoord->fetch()) {
                echo json_encode(['error' => 'Acesso negado: Você não coordena o curso desta turma']);
                exit;
            }
        }
        
        $st = $db->prepare('
            SELECT cp.id, cp.conteudo, cp.created_at, cp.updated_at,
                   u.name as professor_nome, u.photo as professor_photo
            FROM comentarios_professores cp
            JOIN users u ON u.id = cp.professor_id
            WHERE cp.aluno_id = ? AND cp.turma_id = ? AND cp.professor_id != ?
            ORDER BY cp.created_at DESC
        ');
        $st->execute([$alunoId, $turmaId, $professorId]);
        $outrosComentarios = $st->fetchAll(PDO::FETCH_ASSOC);
        
        // Busca comentários do professor logado (histórico)
        $stMeu = $db->prepare('
            SELECT cp.id, cp.conteudo, cp.created_at, cp.updated_at,
                   u.name as professor_nome, u.photo as professor_photo
            FROM comentarios_professores cp
            JOIN users u ON u.id = cp.professor_id
            WHERE cp.aluno_id = ? AND cp.turma_id = ? AND cp.professor_id = ?
            ORDER BY cp.created_at DESC
        ');
        $stMeu->execute([$alunoId, $turmaId, $professorId]);
        $meusComentarios = $stMeu->fetchAll(PDO::FETCH_ASSOC);
        
        // Busca TODOS os comentários do aluno (para nuvem de palavras) sem filtro de turma
        $stAll = $db->prepare('
            SELECT cp.conteudo, cp.created_at
            FROM comentarios_professores cp
            WHERE cp.aluno_id = ?
            ORDER BY cp.created_at DESC
        ');
        $stAll->execute([$alunoId]);
        $todosComentarios = $stAll->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'meus_comentarios' => $meusComentarios,
            'outros_comentarios' => $outrosComentarios,
            'todos_comentarios' => $todosComentarios
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
    }
    exit;
}

// POST: Salvar ou atualizar comentário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_comment') {
        $alunoId = (int)($_POST['aluno_id'] ?? 0);
        $turmaId = (int)($_POST['turma_id'] ?? 0);
        $conteudo = trim($_POST['conteudo'] ?? '');
        
        if (!$alunoId || !$turmaId || empty($conteudo)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Dados inválidos']);
            exit;
        }
        
        // Controle de Acesso por Perfil
        $isProfessor = ($user['profile'] === 'Professor');
        $isCoord = ($user['profile'] === 'Coordenador');

        if ($isProfessor) {
            $stCheck = $db->prepare('
                SELECT 1 FROM turma_disciplinas td
                JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id
                WHERE td.turma_id = ? AND tdp.professor_id = ? LIMIT 1
            ');
            $stCheck->execute([$turmaId, $professorId]);
            if (!$stCheck->fetch()) {
                echo json_encode(['error' => 'Acesso negado: Você não leciona nesta turma']);
                exit;
            }
        } else if ($isCoord) {
            $stCheckCoord = $db->prepare('
                SELECT 1 FROM course_coordinators cc
                JOIN courses c ON c.id = cc.course_id
                JOIN turmas t ON t.course_id = c.id
                WHERE t.id = ? AND cc.user_id = ? LIMIT 1
            ');
            $stCheckCoord->execute([$turmaId, $professorId]);
            if (!$stCheckCoord->fetch()) {
                echo json_encode(['error' => 'Acesso negado: Você não coordena o curso desta turma']);
                exit;
            }
        }
        
        try {
            $commentIdResult = $alunoService->addComentario(
                $alunoId,
                $turmaId,
                $professorId,
                $conteudo
            );
            echo json_encode(['success' => true, 'id' => $commentIdResult['id'] ?? null]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Erro ao salvar: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'delete_comment') {
        $commentId = (int)($_POST['comment_id'] ?? 0);
        
        if (!$commentId) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'ID inválido']);
            exit;
        }
        
        try {
            $success = $alunoService->deleteComentario($commentId, $professorId, $user['profile'] === 'Administrador');
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Erro ao excluir: ' . $e->getMessage()]);
        }
        exit;
    }
}

header('Content-Type: application/json');
echo json_encode(['error' => 'Método não permitido']);
