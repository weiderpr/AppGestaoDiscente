<?php
/**
 * Vértice Acadêmico — Serviço de Atendimentos
 */

namespace App\Services;

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
        
        $this->audit('CREATE', 'gestao_atendimentos', $atendimentoId, null, $data);
        
        return $atendimentoId;
    }

    /**
     * Adiciona um responsável (profissional) ao atendimento
     */
    public function addResponsible(int $atendimentoId, int $usuarioId): bool {
        $st = $this->db->prepare("INSERT IGNORE INTO gestao_atendimento_usuarios (atendimento_id, usuario_id) VALUES (?, ?)");
        $added = $st->execute([$atendimentoId, $usuarioId]);
        if ($added) {
            $this->audit('CREATE', 'gestao_atendimento_usuarios', $atendimentoId, null, ['usuario_id' => $usuarioId]);
        }
        return $added;
    }

    /**
     * Remove um responsável do atendimento
     */
    public function removeResponsible(int $atendimentoId, int $usuarioId): bool {
        $old = $this->fetchOne("SELECT * FROM gestao_atendimento_usuarios WHERE atendimento_id = ? AND usuario_id = ?", [$atendimentoId, $usuarioId]);
        $deleted = $this->execute("DELETE FROM gestao_atendimento_usuarios WHERE atendimento_id = ? AND usuario_id = ?", [$atendimentoId, $usuarioId]) > 0;
        if ($deleted && $old) {
            $this->audit('DELETE', 'gestao_atendimento_usuarios', $atendimentoId, $old, null);
        }
        return $deleted;
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
        $old = $this->fetchOne("SELECT * FROM gestao_atendimentos WHERE id = ? AND institution_id = ?", [$id, $instId]);
        if (!$old) return false;

        $st = $this->db->prepare("
            UPDATE gestao_atendimentos SET 
                descricao_profissional = ?, 
                descricao_publica = ?, 
                updated_at = NOW()
            WHERE id = ? AND institution_id = ?
        ");
        
        $updated = $st->execute([
            $data['professional_text'] ?? $old['descricao_profissional'],
            $data['public_text'] ?? $old['descricao_publica'],
            $id,
            $instId
        ]);

        if ($updated) {
            $this->audit('UPDATE', 'gestao_atendimentos', $id, $old, $data);
        }
        return $updated;
    }

    /**
     * Atualiza o status de um atendimento
     */
    public function updateStatus(int $id, int $instId, string $status): bool {
        $old = $this->fetchOne("SELECT status FROM gestao_atendimentos WHERE id = ? AND institution_id = ?", [$id, $instId]);
        if (!$old) return false;

        $st = $this->db->prepare("UPDATE gestao_atendimentos SET status = ? WHERE id = ? AND institution_id = ?");
        $updated = $st->execute([$status, $id, $instId]);

        if ($updated) {
            // Handle completion of linked encaminhamento
            if ($status === 'Finalizado') {
                $this->execute("
                    UPDATE conselho_encaminhamentos 
                    SET status = 'Concluído' 
                    WHERE id = (SELECT encaminhamento_id FROM gestao_atendimentos WHERE id = ?)
                ", [$id]);
            }
            $this->audit('UPDATE', 'gestao_atendimentos', $id, $old, ['status' => $status]);
        }
        return $updated;
    }

    /**
     * Arquiva ou desarquiva um atendimento
     */
    public function archive(int $id, bool $archive = true): bool {
        $old = $this->fetchOne("SELECT is_archived FROM gestao_atendimentos WHERE id = ?", [$id]);
        $st = $this->db->prepare("UPDATE gestao_atendimentos SET is_archived = ? WHERE id = ?");
        $updated = $st->execute([$archive ? 1 : 0, $id]);
        if ($updated) {
            $this->audit('UPDATE', 'gestao_atendimentos', $id, $old, ['is_archived' => $archive]);
        }
        return $updated;
    }

    /**
     * Adiciona um comentário ao atendimento
     */
    public function addComment(array $data): int {
        $st = $this->db->prepare("
            INSERT INTO gestao_atendimento_comentarios (atendimento_id, usuario_id, texto, is_private)
            VALUES (?, ?, ?, ?)
        ");
        $st->execute([
            $data['atendimento_id'],
            $data['usuario_id'],
            $data['texto'],
            (int)$data['is_private']
        ]);
        $id = (int)$this->db->lastInsertId();
        $this->audit('CREATE', 'gestao_atendimento_comentarios', $id, null, $data);
        return $id;
    }

    /**
     * Remove um comentário
     */
    public function deleteComment(int $id): bool {
        $old = $this->fetchOne("SELECT * FROM gestao_atendimento_comentarios WHERE id = ?", [$id]);
        $deleted = $this->execute("DELETE FROM gestao_atendimento_comentarios WHERE id = ?", [$id]) > 0;
        if ($deleted && $old) {
            $this->audit('DELETE', 'gestao_atendimento_comentarios', $id, $old, null);
        }
        return $deleted;
    }

    /**
     * Adiciona um anexo ao atendimento
     */
    public function addAnexo(array $data): int {
        $st = $this->db->prepare("
            INSERT INTO gestao_atendimentos_anexos (atendimento_id, usuario_id, arquivo, descricao, extensao, tamanho)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $st->execute([
            $data['atendimento_id'],
            $data['usuario_id'],
            $data['arquivo'],
            $data['descricao'],
            $data['extensao'],
            $data['tamanho']
        ]);
        $id = (int)$this->db->lastInsertId();
        $this->audit('CREATE', 'gestao_atendimentos_anexos', $id, null, $data);
        return $id;
    }

    /**
     * Remove um anexo
     */
    public function deleteAnexo(int $id): bool {
        $old = $this->fetchOne("SELECT * FROM gestao_atendimentos_anexos WHERE id = ?", [$id]);
        $deleted = $this->execute("DELETE FROM gestao_atendimentos_anexos WHERE id = ?", [$id]) > 0;
        if ($deleted && $old) {
            $this->audit('DELETE', 'gestao_atendimentos_anexos', $id, $old, null);
        }
        return $deleted;
    }

    /**
     * Retorna os anexos de um atendimento
     */
    public function getAnexos(int $atendimentoId): array {
        return $this->fetchAll("
            SELECT a.*, u.name as author_name
            FROM gestao_atendimentos_anexos a
            JOIN users u ON a.usuario_id = u.id
            WHERE a.atendimento_id = ?
            ORDER BY a.created_at DESC
        ", [$atendimentoId]);
    }

    /**
     * Exclui (soft-delete) um atendimento
     */
    public function deleteAtendimento(int $id, int $instId): bool {
        $old = $this->fetchOne("SELECT * FROM gestao_atendimentos WHERE id = ? AND institution_id = ?", [$id, $instId]);
        if (!$old) return false;

        // Se houver um encaminhamento vinculado, reverte o status dele para 'Pendente'
        if ($old['encaminhamento_id']) {
            $this->execute("UPDATE conselho_encaminhamentos SET status = 'Pendente' WHERE id = ?", [$old['encaminhamento_id']]);
        }

        $deleted = $this->execute("UPDATE gestao_atendimentos SET deleted_at = NOW() WHERE id = ? AND institution_id = ?", [$id, $instId]) > 0;
        if ($deleted) {
            $this->audit('DELETE', 'gestao_atendimentos', $id, $old, ['deleted_at' => date('Y-m-d H:i:s')]);
        }
        return $deleted;
    }
}
