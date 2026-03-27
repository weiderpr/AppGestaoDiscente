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
        INSERT INTO atendimentos (
            institution_id, user_id, aluno_id, turma_id, encaminhamento_id, 
            professional_text, public_text, data_atendimento
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $st->execute([
        $data['institution_id'],
        $data['user_id'],
        $data['aluno_id'] ?: null,
        $data['turma_id'] ?: null,
        $data['encaminhamento_id'] ?: null,
        $data['professional_text'],
        $data['public_text'],
        $data['data_atendimento']
    ]);
    
    return (int)$db->lastInsertId();
}

/**
 * Retorna atendimentos de um aluno
 */
function getAtendimentosByAluno(int $alunoId, int $instId): array {
    $db = getDB();
    $st = $db->prepare("
        SELECT a.*, u.name as user_name, u.profile as user_profile
        FROM atendimentos a
        JOIN users u ON a.user_id = u.id
        WHERE a.aluno_id = ? AND a.institution_id = ? AND a.deleted_at IS NULL
        ORDER BY a.data_atendimento DESC, a.created_at DESC
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
        SELECT a.*, u.name as user_name, u.profile as user_profile
        FROM atendimentos a
        JOIN users u ON a.user_id = u.id
        WHERE a.turma_id = ? AND a.institution_id = ? AND a.deleted_at IS NULL
        ORDER BY a.data_atendimento DESC, a.created_at DESC
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
               ce.texto as encaminhamento_texto
        FROM atendimentos a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN alunos al ON a.aluno_id = al.id
        LEFT JOIN turmas t ON a.turma_id = t.id
        LEFT JOIN conselho_encaminhamentos ce ON a.encaminhamento_id = ce.id
        WHERE a.institution_id = ? AND a.deleted_at IS NULL
    ";
    
    $params = [$instId];
    
    if (!empty($filters['user_id'])) {
        $sql .= " AND a.user_id = ?";
        $params[] = (int)$filters['user_id'];
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (al.nome LIKE ? OR t.description LIKE ? OR a.professional_text LIKE ? OR a.public_text LIKE ?)";
        $search = "%{$filters['search']}%";
        $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search;
    }
    
    $sql .= " ORDER BY a.data_atendimento DESC, a.created_at DESC";
    
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Retorna encaminhamentos pendentes (sem atendimento vinculado)
 */
function getPendingReferrals(int $instId, int $conselhoId = 0): array {
    $db = getDB();
    // Um encaminhamento é pendente se seu ID não está na tabela de atendimentos
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
    
    $sql .= " AND ce.id NOT IN (SELECT encaminhamento_id FROM atendimentos WHERE encaminhamento_id IS NOT NULL AND deleted_at IS NULL)
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
        SELECT a.*, u.name as user_name, u.profile as user_profile
        FROM atendimentos a
        JOIN users u ON a.user_id = u.id
        WHERE a.encaminhamento_id = ? AND a.deleted_at IS NULL
        LIMIT 1
    ");
    $st->execute([$referralId]);
    return $st->fetch() ?: null;
}

/**
 * Atualiza um atendimento existente
 */
function updateAtendimento(int $id, array $data): bool {
    $db = getDB();
    $st = $db->prepare("
        UPDATE atendimentos SET 
            professional_text = ?, 
            public_text = ?, 
            data_atendimento = ?,
            updated_at = NOW()
        WHERE id = ? AND institution_id = ?
    ");
    return $st->execute([
        $data['professional_text'],
        $data['public_text'],
        $data['data_atendimento'],
        $id,
        $data['institution_id']
    ]);
}
