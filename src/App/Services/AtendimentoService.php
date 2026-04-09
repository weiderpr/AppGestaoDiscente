<?php
/**
 * Vértice Acadêmico — Serviço de Atendimentos
 */

namespace App\Services;

use PDO;
use Exception;

class AtendimentoService extends Service {
    
    /**
     * Salva um novo atendimento
     */
    public function save(array $data): int {
        $st = $this->db->prepare("
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
            $data['professional_text'] ?? '',
            $data['public_text'] ?? '',
            $data['status'] ?? 'Aberto',
            $data['titulo'] ?? 'Atendimento Profissional'
        ]);
        
        $atendimentoId = (int)$this->db->lastInsertId();
        
        // Adiciona o autor como responsável automaticamente
        $this->addResponsible($atendimentoId, $data['user_id']);
        
        // Atualiza o encaminhamento caso exista
        if (!empty($data['encaminhamento_id'])) {
            $this->db->prepare("UPDATE conselho_encaminhamentos SET status = 'Em Andamento' WHERE id = ?")
               ->execute([$data['encaminhamento_id']]);
        }
        
        return $atendimentoId;
    }

    /**
     * Adiciona um responsável (profissional) ao atendimento
     */
    public function addResponsible(int $atendimentoId, int $usuarioId): bool {
        $st = $this->db->prepare("INSERT IGNORE INTO gestao_atendimento_usuarios (atendimento_id, usuario_id) VALUES (?, ?)");
        return $st->execute([$atendimentoId, $usuarioId]);
    }

    /**
     * Retorna atendimentos de um aluno
     */
    public function getByAluno(int $alunoId, int $instId): array {
        return $this->fetchAll("
            SELECT a.*, u.name as user_name, u.profile as user_profile,
                   a.descricao_profissional as professional_text, a.descricao_publica as public_text,
                   a.created_at as data_atendimento
            FROM gestao_atendimentos a
            JOIN users u ON a.author_id = u.id
            WHERE a.aluno_id = ? AND a.institution_id = ? AND a.deleted_at IS NULL
            ORDER BY a.created_at DESC
        ", [$alunoId, $instId]);
    }

    /**
     * Retorna atendimentos de uma turma
     */
    public function getByTurma(int $turmaId, int $instId): array {
        return $this->fetchAll("
            SELECT a.*, u.name as user_name, u.profile as user_profile,
                   a.descricao_profissional as professional_text, a.descricao_publica as public_text,
                   a.created_at as data_atendimento
            FROM gestao_atendimentos a
            JOIN users u ON a.author_id = u.id
            WHERE a.turma_id = ? AND a.institution_id = ? AND a.deleted_at IS NULL
            ORDER BY a.created_at DESC
        ", [$turmaId, $instId]);
    }

    /**
     * Retorna todos os atendimentos de uma instituição (com filtros)
     */
    public function getAll(int $instId, array $filters = []): array {
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
            array_push($params, $search, $search, $search, $search, $search);
        }
        
        $sql .= " ORDER BY a.created_at DESC";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Atualiza um atendimento existente
     */
    public function update(int $id, int $instId, array $data): bool {
        $st = $this->db->prepare("
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
            $instId
        ]);
    }

    /**
     * Atualiza o status de um atendimento
     */
    public function updateStatus(int $id, int $instId, string $status): bool {
        $st = $this->db->prepare("UPDATE gestao_atendimentos SET status = ? WHERE id = ? AND institution_id = ?");
        return $st->execute([$status, $id, $instId]);
    }
}
