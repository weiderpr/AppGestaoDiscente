<?php
/**
 * Vértice Acadêmico — Funções de Atendimento
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Salva um novo atendimento
 */
function saveAtendimento(array $data): int {
    $db = getDB();
    
    $st = $db->prepare("
        INSERT INTO gestao_atendimentos (
            institution_id, author_id, aluno_id, turma_id, encaminhamento_id, 
            descricao_profissional, descricao_publica, status, titulo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $st->execute([
        $data['institution_id'],
        $data['user_id'],
        $data['aluno_id'] ?: null,
        $data['turma_id'] ?: null,
        $data['encaminhamento_id'] ?: null,
        $data['professional_text'],
        $data['public_text'],
        $data['status'] ?? 'Aberto',
        $data['titulo'] ?? 'Atendimento Profissional'
    ]);
    
    $atendimentoId = (int)$db->lastInsertId();
    
    // Adiciona o autor como responsável automaticamente para aparecer no Kanban
    $stResp = $db->prepare("INSERT IGNORE INTO gestao_atendimento_usuarios (atendimento_id, usuario_id) VALUES (?, ?)");
    $stResp->execute([$atendimentoId, $data['user_id']]);
    
    // Atualiza o encaminhamento caso exista
    if (!empty($data['encaminhamento_id'])) {
        $db->prepare("UPDATE conselho_encaminhamentos SET status = 'Em Andamento' WHERE id = ?")
           ->execute([$data['encaminhamento_id']]);
    }
    
    return $atendimentoId;
}

/**
 * Retorna atendimentos de um aluno
 */
function getAtendimentosByAluno(int $alunoId, int $instId): array {
    $db = getDB();
    $st = $db->prepare("
        SELECT a.*, u.name as user_name, u.profile as user_profile,
               a.descricao_profissional as professional_text, a.descricao_publica as public_text,
               a.created_at as data_atendimento
        FROM gestao_atendimentos a
        JOIN users u ON a.author_id = u.id
        WHERE a.aluno_id = ? AND a.institution_id = ? AND a.deleted_at IS NULL
        ORDER BY a.created_at DESC
    ");
    $st->execute([$alunoId, $instId]);
    return $st->fetchAll();
}

/**
 * Retorna atendimentos de uma turma
 */
function getAtendimentosByTurma(int $turmaId, int $instId): array {
    $db = getDB();
    $st = $db->prepare("
        SELECT a.*, u.name as user_name, u.profile as user_profile,
               a.descricao_profissional as professional_text, a.descricao_publica as public_text,
               a.created_at as data_atendimento
        FROM gestao_atendimentos a
        JOIN users u ON a.author_id = u.id
        WHERE a.turma_id = ? AND a.institution_id = ? AND a.deleted_at IS NULL
        ORDER BY a.created_at DESC
    ");
    $st->execute([$turmaId, $instId]);
    return $st->fetchAll();
}
/**
 * Retorna todos os atendimentos de uma instituição (com filtros opcionais)
 */
function getAllAtendimentos(int $instId, array $filters = []): array {
    $db = getDB();
    $sql = "
        SELECT a.*, u.name as user_name, u.profile as user_profile,
               al.nome as aluno_nome, al.photo as aluno_photo, 
               t.description as turma_nome,
               ce.texto as encaminhamento_texto,
               a.descricao_profissional as professional_text, a.descricao_publica as public_text,
               a.created_at as data_atendimento
        FROM gestao_atendimentos a
        JOIN users u ON a.author_id = u.id
        LEFT JOIN alunos al ON a.aluno_id = al.id
        LEFT JOIN turmas t ON a.turma_id = t.id
        LEFT JOIN conselho_encaminhamentos ce ON a.encaminhamento_id = ce.id
        WHERE a.institution_id = ? AND a.deleted_at IS NULL
    ";
    
    $params = [$instId];
    
    if (!empty($filters['user_id'])) {
        $sql .= " AND a.author_id = ?";
        $params[] = (int)$filters['user_id'];
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (al.nome LIKE ? OR t.description LIKE ? OR a.descricao_profissional LIKE ? OR a.descricao_publica LIKE ? OR a.titulo LIKE ?)";
        $search = "%{$filters['search']}%";
        $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search;
    }
    
    $sql .= " ORDER BY a.created_at DESC";
    
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Retorna encaminhamentos pendentes (sem atendimento vinculado)
 */
function getPendingReferrals(int $instId, int $conselhoId = 0): array {
    $db = getDB();
    $sql = "
        SELECT ce.*, al.nome as aluno_nome, al.photo as aluno_photo, 
               t.description as turma_nome, cc.descricao as conselho_nome
        FROM conselho_encaminhamentos ce
        JOIN conselhos_classe cc ON ce.conselho_id = cc.id
        LEFT JOIN alunos al ON ce.aluno_id = al.id
        LEFT JOIN turmas t ON cc.turma_id = t.id
        WHERE cc.institution_id = ? 
    ";
    
    $params = [$instId];
    
    if ($conselhoId > 0) {
        $sql .= " AND ce.conselho_id = ?";
        $params[] = $conselhoId;
    }
    
    $sql .= " AND ce.id NOT IN (SELECT encaminhamento_id FROM gestao_atendimentos WHERE encaminhamento_id IS NOT NULL AND deleted_at IS NULL)
        ORDER BY ce.created_at DESC
    ";
    
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Retorna um atendimento vinculado a um encaminhamento
 */
function getAtendimentoByReferral(int $referralId): ?array {
    $db = getDB();
    $st = $db->prepare("
        SELECT a.*, u.name as user_name, u.profile as user_profile,
               al.nome as aluno_nome, al.matricula, al.photo as aluno_photo, 
               t.description as turma_nome,
               co.name as curso_nome,
               ce.texto as encaminhamento_texto,
               ce.data_expectativa as data_expectativa,
               cc.descricao as conselho_nome,
               a.descricao_profissional as professional_text, a.descricao_publica as public_text,
               a.created_at as data_atendimento, a.author_id as user_id
        FROM gestao_atendimentos a
        JOIN users u ON a.author_id = u.id
        LEFT JOIN alunos al ON a.aluno_id = al.id
        LEFT JOIN turmas t ON a.turma_id = t.id
        LEFT JOIN courses co ON t.course_id = co.id
        LEFT JOIN conselho_encaminhamentos ce ON a.encaminhamento_id = ce.id
        LEFT JOIN conselhos_classe cc ON ce.conselho_id = cc.id
        WHERE a.encaminhamento_id = ? AND a.deleted_at IS NULL
        LIMIT 1
    ");
    $st->execute([$referralId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Atualiza um atendimento existente
 */
function updateAtendimento(int $id, array $data): bool {
    $db = getDB();
    $st = $db->prepare("
        UPDATE gestao_atendimentos SET 
            descricao_profissional = ?, 
            descricao_publica = ?, 
            updated_at = NOW()
        WHERE id = ? AND institution_id = ?
    ");
    return $st->execute([
        $data['professional_text'],
        $data['public_text'],
        $id,
        $data['institution_id']
    ]);
}
