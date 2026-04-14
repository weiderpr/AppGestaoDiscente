<?php
/**
 * Vértice Acadêmico — Social Feed Aggregator API
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
hasDbPermission('social.feed_view');

header('Content-Type: application/json');

$db = getDB();
$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = (int)$inst['id'];

if (!$instId) {
    echo json_encode(['status' => 'error', 'message' => 'Nenhuma instituição selecionada.']);
    exit;
}

// Handler para Criação de Comentário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_comment') {
    try {
        require_once __DIR__ . '/../src/App/Services/Service.php';
        require_once __DIR__ . '/../src/App/Services/AlunoService.php';
        
        $alunoId = (int)($_POST['aluno_id'] ?? 0);
        $turmaId = (int)($_POST['turma_id'] ?? 0);
        $conteudo = trim($_POST['conteudo'] ?? '');
        
        if (!$alunoId || !$conteudo) {
            throw new Exception('Aluno e conteúdo são obrigatórios.');
        }

        // Se turmaId não veio, tenta buscar a mais recente do aluno
        if (!$turmaId) {
            $st = $db->prepare("SELECT turma_id FROM turma_alunos ta JOIN turmas t ON ta.turma_id = t.id WHERE aluno_id = ? ORDER BY t.ano DESC LIMIT 1");
            $st->execute([$alunoId]);
            $turmaId = (int)$st->fetchColumn();
        }

        if (!$turmaId) {
            throw new Exception('O aluno selecionado não possui uma turma ativa vinculada.');
        }

        $service = new \App\Services\AlunoService();
        $res = $service->addComentario($alunoId, $turmaId, $user['id'], $conteudo);

        echo json_encode(['status' => 'success', 'id' => $res['id']]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Handler para Exclusão de Comentário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_comment') {
    try {
        require_once __DIR__ . '/../src/App/Services/Service.php';
        require_once __DIR__ . '/../src/App/Services/AlunoService.php';
        
        $commentId = (int)($_POST['id'] ?? 0);
        if (!$commentId) throw new Exception('ID do comentário inválido.');

        $db = getDB();
        $stmt = $db->prepare("SELECT professor_id FROM comentarios_professores WHERE id = ?");
        $stmt->execute([$commentId]);
        $ownerId = (int)$stmt->fetchColumn();

        if ($ownerId !== (int)$user['id'] && $user['profile'] !== 'Administrador') {
            throw new Exception('Você não tem permissão para excluir este comentário.');
        }

        $service = new \App\Services\AlunoService();
        if ($service->deleteComentario($commentId)) {
            echo json_encode(['status' => 'success']);
        } else {
            throw new Exception('Não foi possível excluir o comentário ou ele já foi removido.');
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
    try {
    // Pagination params
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Advanced Filters
    $validAlunoIds  = [];
    $validTurmaIds  = [];
    $validCourseIds = [];

    if (!empty($_GET['aluno_ids']))  foreach (explode(',', $_GET['aluno_ids'])  as $id) if ((int)$id > 0) $validAlunoIds[]  = (int)$id;
    if (!empty($_GET['turma_ids']))  foreach (explode(',', $_GET['turma_ids'])  as $id) if ((int)$id > 0) $validTurmaIds[]  = (int)$id;
    if (!empty($_GET['course_ids'])) foreach (explode(',', $_GET['course_ids']) as $id) if ((int)$id > 0) $validCourseIds[] = (int)$id;

    $filterSql = "";
    $hasFilters = !empty($validAlunoIds) || !empty($validTurmaIds) || !empty($validCourseIds);
    
    if ($hasFilters) {
        $clauses = [];
        if (!empty($validAlunoIds))  $clauses[] = "a.id IN (" . implode(',', $validAlunoIds) . ")";
        if (!empty($validTurmaIds))  $clauses[] = "t.id IN (" . implode(',', $validTurmaIds) . ")";
        if (!empty($validCourseIds)) $clauses[] = "c.id IN (" . implode(',', $validCourseIds) . ")";
        $filterSql = " AND (" . implode(' OR ', $clauses) . ") ";
    }

    // Aggregator Query using UNION to bring all social activities
    
    $sql = "
    SELECT * FROM (
        -- 1. Comentários de Professores
        SELECT 
            'comentario_professor' AS event_type,
            cp.id AS event_id,
            a.id AS aluno_id,
            a.nome AS aluno_nome,
            a.photo AS aluno_foto,
            t.id AS turma_id,
            cp.conteudo AS content,
            u.name AS responsible_name,
            u.photo AS responsible_photo,
            u.id AS responsible_id,
            cp.created_at AS timestamp,
            'Comentário' as badge_text,
            'info' as badge_type,
            t.description AS turma_desc,
            t.ano AS turma_ano,
            c.name AS curso_nome
        FROM comentarios_professores cp
        JOIN alunos a ON cp.aluno_id = a.id
        JOIN users u ON cp.professor_id = u.id
        JOIN turmas t ON cp.turma_id = t.id
        JOIN courses c ON t.course_id = c.id
        WHERE c.institution_id = :inst_id_1
          AND a.deleted_at IS NULL
          $filterSql

        UNION ALL

        -- 2. Encaminhamentos do Conselho
        SELECT 
            'conselho' AS event_type,
            ce.id AS event_id,
            a.id AS aluno_id,
            a.nome AS aluno_nome,
            a.photo AS aluno_foto,
            t.id AS turma_id,
            ce.texto AS content,
            u.name AS responsible_name,
            u.photo AS responsible_photo,
            u.id AS responsible_id,
            ce.created_at AS timestamp,
            'Conselho' as badge_text,
            'warning' as badge_type,
            t.description AS turma_desc,
            t.ano AS turma_ano,
            c.name AS curso_nome
        FROM conselho_encaminhamentos ce
        JOIN alunos a ON ce.aluno_id = a.id
        JOIN users u ON ce.author_id = u.id
        JOIN conselhos_classe cc ON ce.conselho_id = cc.id
        JOIN turmas t ON cc.turma_id = t.id
        JOIN courses c ON t.course_id = c.id
        WHERE cc.institution_id = :inst_id_2
          AND a.deleted_at IS NULL
          $filterSql

        UNION ALL

        -- 3. Atendimentos
        SELECT 
            'atendimento' AS event_type,
            ga.id AS event_id,
            a.id AS aluno_id,
            a.nome AS aluno_nome,
            a.photo AS aluno_foto,
            t.id AS turma_id,
            CONCAT(ga.titulo, IF(ga.descricao_publica IS NOT NULL AND ga.descricao_publica != '', CONCAT('\n', ga.descricao_publica), '')) AS content,
            u.name AS responsible_name,
            u.photo AS responsible_photo,
            u.id AS responsible_id,
            ga.created_at AS timestamp,
            'Atendimento' as badge_text,
            'success' as badge_type,
            t.description AS turma_desc,
            t.ano AS turma_ano,
            c.name AS curso_nome
        FROM gestao_atendimentos ga
        JOIN alunos a ON ga.aluno_id = a.id
        JOIN users u ON ga.author_id = u.id
        -- Subquery approach to get the current turma for the student accurately
        LEFT JOIN (
            SELECT ta.aluno_id, ta.turma_id FROM turma_alunos ta 
            JOIN turmas t2 ON ta.turma_id = t2.id 
            ORDER BY t2.ano DESC 
        ) AS current_ta ON a.id = current_ta.aluno_id
        LEFT JOIN turmas t ON current_ta.turma_id = t.id
        LEFT JOIN courses c ON t.course_id = c.id
        WHERE ga.institution_id = :inst_id_3
          AND ga.deleted_at IS NULL
          AND a.deleted_at IS NULL
          $filterSql

        UNION ALL

        -- 4. Comentários em Atendimentos (Somente Públicos)
        SELECT 
            'comentario_atendimento' AS event_type,
            gac.id AS event_id,
            a.id AS aluno_id,
            a.nome AS aluno_nome,
            a.photo AS aluno_foto,
            t.id AS turma_id,
            CONCAT('No atendimento \"', ga.titulo, '\":\n', gac.texto) AS content,
            u.name AS responsible_name,
            u.photo AS responsible_photo,
            u.id AS responsible_id,
            gac.created_at AS timestamp,
            'Comentário Atend.' as badge_text,
            'success' as badge_type,
            t.description AS turma_desc,
            t.ano AS turma_ano,
            c.name AS curso_nome
        FROM gestao_atendimento_comentarios gac
        JOIN gestao_atendimentos ga ON gac.atendimento_id = ga.id
        JOIN alunos a ON ga.aluno_id = a.id
        JOIN users u ON gac.usuario_id = u.id
        LEFT JOIN (
            SELECT ta.aluno_id, ta.turma_id FROM turma_alunos ta 
            JOIN turmas t2 ON ta.turma_id = t2.id 
            ORDER BY t2.ano DESC 
        ) AS current_ta ON a.id = current_ta.aluno_id
        LEFT JOIN turmas t ON current_ta.turma_id = t.id
        LEFT JOIN courses c ON t.course_id = c.id
        WHERE ga.institution_id = :inst_id_4 
          AND gac.is_private = 0
          AND ga.deleted_at IS NULL
          AND a.deleted_at IS NULL
          $filterSql

        UNION ALL

        -- 5. Sanções Disciplinares
        SELECT 
            'sancao' AS event_type,
            s.id AS event_id,
            a.id AS aluno_id,
            a.nome AS aluno_nome,
            a.photo AS aluno_foto,
            t.id AS turma_id,
            CONCAT(st.titulo, ': ', s.observacoes) AS content,
            u.name AS responsible_name,
            u.photo AS responsible_photo,
            u.id AS responsible_id,
            s.created_at AS timestamp,
            'Sanção' as badge_text,
            'danger' as badge_type,
            t.description AS turma_desc,
            t.ano AS turma_ano,
            c.name AS curso_nome
        FROM sancao s
        JOIN sancao_tipo st ON s.sancao_tipo_id = st.id
        JOIN alunos a ON s.aluno_id = a.id
        JOIN users u ON s.author_id = u.id
        JOIN turmas t ON s.turma_id = t.id
        JOIN courses c ON t.course_id = c.id
        WHERE s.institution_id = :inst_id_5
          AND a.deleted_at IS NULL
          $filterSql
    ) AS feed
    ORDER BY timestamp DESC
    LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':inst_id_1', $instId, PDO::PARAM_INT);
    $stmt->bindValue(':inst_id_2', $instId, PDO::PARAM_INT);
    $stmt->bindValue(':inst_id_3', $instId, PDO::PARAM_INT);
    $stmt->bindValue(':inst_id_4', $instId, PDO::PARAM_INT);
    $stmt->bindValue(':inst_id_5', $instId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'count' => count($data),
        'has_more' => count($data) === $limit,
        'data' => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao carregar o feed: ' . $e->getMessage()
    ]);
}
