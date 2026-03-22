<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$allowedProfiles = ['Professor', 'Coordenador', 'Administrador'];
if (!$user || !in_array($user['profile'], $allowedProfiles)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acesso negado: Perfil sem permissão.']);
    exit;
}

$db = getDB();
$professorId = $user['id'];

// GET: Buscar comentários
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $alunoId = (int)($_GET['aluno_id'] ?? 0);
    $turmaId = (int)($_GET['turma_id'] ?? 0);
    
    if (!$alunoId || !$turmaId) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Parâmetros inválidos']);
        exit;
    }
    
    try {
        // Controle de Acesso por Perfil
        $isProfessor = ($user['profile'] === 'Professor');
        $isCoord = ($user['profile'] === 'Coordenador');
        $isAdmin = ($user['profile'] === 'Administrador');

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
        $isAdmin = ($user['profile'] === 'Administrador');

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
            // Insere novo comentário (sempre adiciona ao histórico)
            $stIns = $db->prepare('INSERT INTO comentarios_professores (professor_id, aluno_id, turma_id, conteudo) VALUES (?, ?, ?, ?)');
            $stIns->execute([$professorId, $alunoId, $turmaId, $conteudo]);
            $commentId = $db->lastInsertId();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'id' => $commentId]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
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
        
        // Verifica permissão para excluir
        if ($user['profile'] === 'Administrador') {
            // Admins podem excluir qualquer comentário
            $stDel = $db->prepare('DELETE FROM comentarios_professores WHERE id = ?');
            $stDel->execute([$commentId]);
        } else {
            // Outros perfis só excluem os próprios comentários
            $stDel = $db->prepare('DELETE FROM comentarios_professores WHERE id = ? AND professor_id = ?');
            $stDel->execute([$commentId, $professorId]);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $stDel->rowCount() > 0]);
        exit;
    }
}

header('Content-Type: application/json');
echo json_encode(['error' => 'Método não permitido']);
