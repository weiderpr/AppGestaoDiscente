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
try {
    // Pagination params
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Aluno Filter
    $alunoIdsRaw = isset($_GET['aluno_ids']) && !empty($_GET['aluno_ids']) ? explode(',', $_GET['aluno_ids']) : [];
    $validAlunoIds = [];
    foreach ($alunoIdsRaw as $id) {
        if ((int)$id > 0) $validAlunoIds[] = (int)$id;
    }
    
    $alunoFilter = "";
    if (!empty($validAlunoIds)) {
        $alunoFilter = " AND a.id IN (" . implode(',', $validAlunoIds) . ") ";
    }

    // Aggregator Query using UNION to bring all social activities
    // Fields: event_type, event_id, aluno_id, aluno_nome, aluno_foto, content, responsible_name, responsible_photo, timestamp, badge_text, badge_type
    
    $sql = "
    SELECT * FROM (
        -- 1. Comentários de Professores
        SELECT 
            'comentario_professor' AS event_type,
            cp.id AS event_id,
            a.id AS aluno_id,
            a.nome AS aluno_nome,
            a.photo AS aluno_foto,
            cp.conteudo AS content,
            u.name AS responsible_name,
            u.photo AS responsible_photo,
            cp.created_at AS timestamp,
            'Comentário' as badge_text,
            'info' as badge_type
        FROM comentarios_professores cp
        JOIN alunos a ON cp.aluno_id = a.id
        JOIN users u ON cp.professor_id = u.id
        JOIN turmas t ON cp.turma_id = t.id
        JOIN courses c ON t.course_id = c.id
        WHERE c.institution_id = :inst_id_1
          AND a.deleted_at IS NULL
          $alunoFilter

        UNION ALL

        -- 2. Encaminhamentos do Conselho
        SELECT 
            'conselho' AS event_type,
            ce.id AS event_id,
            a.id AS aluno_id,
            a.nome AS aluno_nome,
            a.photo AS aluno_foto,
            ce.texto AS content,
            u.name AS responsible_name,
            u.photo AS responsible_photo,
            ce.created_at AS timestamp,
            'Conselho' as badge_text,
            'warning' as badge_type
        FROM conselho_encaminhamentos ce
        JOIN alunos a ON ce.aluno_id = a.id
        JOIN users u ON ce.author_id = u.id
        JOIN conselhos_classe cc ON ce.conselho_id = cc.id
        WHERE cc.institution_id = :inst_id_2
          AND a.deleted_at IS NULL
          $alunoFilter

        UNION ALL

        -- 3. Atendimentos
        SELECT 
            'atendimento' AS event_type,
            ga.id AS event_id,
            a.id AS aluno_id,
            a.nome AS aluno_nome,
            a.photo AS aluno_foto,
            CONCAT(ga.titulo, IF(ga.descricao_publica IS NOT NULL AND ga.descricao_publica != '', CONCAT('\n', ga.descricao_publica), '')) AS content,
            u.name AS responsible_name,
            u.photo AS responsible_photo,
            ga.created_at AS timestamp,
            'Atendimento' as badge_text,
            'success' as badge_type
        FROM gestao_atendimentos ga
        JOIN alunos a ON ga.aluno_id = a.id
        JOIN users u ON ga.author_id = u.id
        WHERE ga.institution_id = :inst_id_3
          AND ga.deleted_at IS NULL
          AND a.deleted_at IS NULL
          $alunoFilter

        UNION ALL

        -- 4. Comentários em Atendimentos (Somente Públicos)
        SELECT 
            'comentario_atendimento' AS event_type,
            gac.id AS event_id,
            a.id AS aluno_id,
            a.nome AS aluno_nome,
            a.photo AS aluno_foto,
            CONCAT('No atendimento \"', ga.titulo, '\":\n', gac.texto) AS content,
            u.name AS responsible_name,
            u.photo AS responsible_photo,
            gac.created_at AS timestamp,
            'Comentário Atend.' as badge_text,
            'success' as badge_type
        FROM gestao_atendimento_comentarios gac
        JOIN gestao_atendimentos ga ON gac.atendimento_id = ga.id
        JOIN alunos a ON ga.aluno_id = a.id
        JOIN users u ON gac.usuario_id = u.id
        WHERE ga.institution_id = :inst_id_4 
          AND gac.is_private = 0
          AND ga.deleted_at IS NULL
          AND a.deleted_at IS NULL
          $alunoFilter

        UNION ALL

        -- 5. Sanções Disciplinares
        SELECT 
            'sancao' AS event_type,
            s.id AS event_id,
            a.id AS aluno_id,
            a.nome AS aluno_nome,
            a.photo AS aluno_foto,
            CONCAT(st.titulo, ': ', s.observacoes) AS content,
            u.name AS responsible_name,
            u.photo AS responsible_photo,
            s.created_at AS timestamp,
            'Sanção' as badge_text,
            'danger' as badge_type
        FROM sancao s
        JOIN sancao_tipo st ON s.sancao_tipo_id = st.id
        JOIN alunos a ON s.aluno_id = a.id
        JOIN users u ON s.author_id = u.id
        WHERE s.institution_id = :inst_id_5
          AND a.deleted_at IS NULL
          $alunoFilter
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
