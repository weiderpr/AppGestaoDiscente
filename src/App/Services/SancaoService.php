<?php
/**
 * Vértice Acadêmico — Serviço de Sanções
 */

namespace App\Services;

use Exception;
use PDO;

class SancaoService extends Service {

    /**
     * Lista sanções conforme filtros
     */
    public function list(int $institutionId, array $filters = []): array {
        $sql = "
            SELECT s.id, s.data_sancao, s.status, s.author_id, a.nome as aluno_nome, a.matricula, a.photo as aluno_foto, a.id as aluno_id,
                   t.description as turma_desc, st.titulo as tipo_titulo
            FROM sancao s
            JOIN alunos a ON s.aluno_id = a.id
            JOIN turmas t ON s.turma_id = t.id
            JOIN sancao_tipo st ON s.sancao_tipo_id = st.id
            WHERE s.institution_id = ?
        ";
        $params = [$institutionId];

        if (!empty($filters['aluno'])) {
            $sql .= " AND (a.nome LIKE ? OR a.matricula LIKE ?)";
            $params[] = "%{$filters['aluno']}%";
            $params[] = "%{$filters['aluno']}%";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND s.status = ?";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY s.data_sancao DESC, s.id DESC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Busca uma sanção específica
     */
    public function get(int $id, int $institutionId): ?array {
        $sql = "
            SELECT s.*, 
                   a.nome as aluno_nome, a.matricula, a.photo as aluno_foto,
                   t.description as turma_desc,
                   c.name as curso_nome,
                   st.titulo as tipo_titulo,
                   u.name as author_name
            FROM sancao s
            JOIN alunos a ON s.aluno_id = a.id
            JOIN turmas t ON s.turma_id = t.id
            JOIN courses c ON t.course_id = c.id
            JOIN sancao_tipo st ON s.sancao_tipo_id = st.id
            JOIN users u ON s.author_id = u.id
            WHERE s.id = ? AND s.institution_id = ?
        ";
        $sancao = $this->fetchOne($sql, [$id, $institutionId]);

        if ($sancao) {
            $stAcoes = $this->db->prepare("SELECT sancao_acao_id FROM sancao_acoes_rel WHERE sancao_id = ?");
            $stAcoes->execute([$id]);
            $sancao['acoes_rel'] = $stAcoes->fetchAll(PDO::FETCH_COLUMN);
        }

        return $sancao;
    }

    /**
     * Salva ou atualiza uma sanção
     */
    public function save(array $data, int $institutionId, int $authorId): int {
        $id = (int)($data['id'] ?? 0);
        $alunoId = (int)($data['aluno_id'] ?? 0);
        $status = $data['status'] ?? 'Em aberto';
        $acoes = $data['acoes'] ?? [];

        $this->beginTransaction();

        try {
            if ($id > 0) {
                // UPDATE
                $old = $this->get($id, $institutionId);
                if (!$old) throw new Exception("Sanção não encontrada.");

                $sql = "UPDATE sancao SET sancao_tipo_id=?, data_sancao=?, observacoes=?, status=?, updated_at=NOW()";
                $params = [$data['sancao_tipo_id'], $data['data_sancao'], $data['observacoes'], $status];

                if ($status === 'Concluído' && $old['status'] !== 'Concluído') {
                    $sql .= ", data_conclusao=CURRENT_DATE";
                }

                $sql .= " WHERE id=? AND institution_id=?";
                $params[] = $id;
                $params[] = $institutionId;

                $this->execute($sql, $params);
                
                // Relacionamentos (Ações)
                $this->db->prepare("DELETE FROM sancao_acoes_rel WHERE sancao_id=?")->execute([$id]);

                $this->audit('UPDATE', 'sancao', $id, $old, $data);
            } else {
                // INSERT
                // Buscar turma atual do aluno
                $stT = $this->db->prepare("
                    SELECT ta.turma_id 
                    FROM turma_alunos ta 
                    JOIN turmas t ON ta.turma_id=t.id 
                    JOIN courses c ON t.course_id=c.id 
                    WHERE ta.aluno_id=? AND c.institution_id=? 
                    ORDER BY t.ano DESC LIMIT 1
                ");
                $stT->execute([$alunoId, $institutionId]);
                $turmaId = $stT->fetchColumn();

                if (!$turmaId) throw new Exception("Aluno sem turma vinculada nesta instituição.");

                $sql = "INSERT INTO sancao (institution_id, author_id, aluno_id, turma_id, sancao_tipo_id, data_sancao, observacoes, status, created_at, updated_at";
                $params = [$institutionId, $authorId, $alunoId, $turmaId, $data['sancao_tipo_id'], $data['data_sancao'], $data['observacoes'], $status];

                if ($status === 'Concluído') {
                    $sql .= ", data_conclusao";
                }
                $sql .= ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()";
                if ($status === 'Concluído') {
                    $sql .= ", CURRENT_DATE";
                }
                $sql .= ")";

                $this->execute($sql, $params);
                $id = $this->lastInsertId();

                $this->audit('CREATE', 'sancao', $id, null, $data);
            }

            // Inserir ações relacionadas
            if (!empty($acoes) && is_array($acoes)) {
                $stInsAcoes = $this->db->prepare("INSERT INTO sancao_acoes_rel (sancao_id, sancao_acao_id) VALUES (?, ?)");
                foreach ($acoes as $acaoId) {
                    $stInsAcoes->execute([$id, (int)$acaoId]);
                }
            }

            $this->commit();
            return $id;

        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Exclui uma sanção
     */
    public function delete(int $id, int $institutionId, int $userId): bool {
        $old = $this->get($id, $institutionId);
        if (!$old) return false;

        // Regra de negócio: apenas o autor pode excluir
        if ((int)$old['author_id'] !== $userId) {
            throw new Exception("Você não tem permissão para excluir esta sanção pois não foi o criador do registro.");
        }

        $this->beginTransaction();
        try {
            // Remove anexos (arquivos físicos e registros)
            $anexos = $this->getAnexos($id);
            foreach ($anexos as $anexo) {
                $this->deleteAnexo((int)$anexo['id'], $id);
            }

            $this->db->prepare("DELETE FROM sancao_acoes_rel WHERE sancao_id=?")->execute([$id]);
            $this->execute("DELETE FROM sancao WHERE id=? AND institution_id=?", [$id, $institutionId]);

            $this->audit('DELETE', 'sancao', $id, $old, null);
            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Finaliza uma sanção
     */
    public function finish(int $id, int $institutionId): bool {
        $old = $this->get($id, $institutionId);
        if (!$old) return false;

        $updated = $this->execute(
            "UPDATE sancao SET status = 'Concluído', data_conclusao = CURRENT_DATE, updated_at = NOW() WHERE id = ? AND institution_id = ?",
            [$id, $institutionId]
        ) > 0;

        if ($updated) {
            $this->audit('UPDATE', 'sancao', $id, ['status' => $old['status']], ['status' => 'Concluído']);
        }

        return $updated;
    }

    /**
     * Histórico de sanções de um aluno
     */
    public function getHistory(int $alunoId, int $institutionId, int $excludeId = 0): array {
        $sql = "
            SELECT s.id, s.data_sancao, s.status, s.observacoes, st.titulo as tipo_titulo
            FROM sancao s
            JOIN sancao_tipo st ON s.sancao_tipo_id = st.id
            WHERE s.aluno_id = ? AND s.institution_id = ?
        ";
        $params = [$alunoId, $institutionId];
        if ($excludeId > 0) {
            $sql .= " AND s.id != ?";
            $params[] = $excludeId;
        }
        $sql .= " ORDER BY s.data_sancao DESC, s.id DESC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Dependências para o formulário (tipos e ações)
     */
    public function getDependencies(int $institutionId): array {
        $tipos = $this->fetchAll("SELECT id, titulo, descricao FROM sancao_tipo WHERE institution_id = ? AND is_active = 1 ORDER BY titulo", [$institutionId]);
        $acoes = $this->fetchAll("SELECT id, descricao FROM sancao_acao WHERE institution_id = ? AND is_active = 1 ORDER BY id", [$institutionId]);
        return ['tipos' => $tipos, 'acoes' => $acoes];
    }

    /**
     * Anexos de uma sanção
     */
    public function getAnexos(int $sancaoId): array {
        return $this->fetchAll("
            SELECT sa.*, u.name as author_name 
            FROM sancao_anexos sa
            JOIN users u ON sa.usuario_id = u.id
            WHERE sa.sancao_id = ?
            ORDER BY sa.created_at DESC
        ", [$sancaoId]);
    }

    /**
     * Adiciona um anexo
     */
    public function addAnexo(int $sancaoId, int $userId, array $fileData): bool {
        $sql = "INSERT INTO sancao_anexos (sancao_id, usuario_id, arquivo, descricao, extensao, tamanho, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $inserted = $this->execute($sql, [
            $sancaoId,
            $userId,
            $fileData['arquivo'],
            $fileData['descricao'],
            $fileData['extensao'],
            $fileData['tamanho']
        ]) > 0;

        if ($inserted) {
            $this->audit('CREATE', 'sancao_anexos', $this->lastInsertId(), null, $fileData);
        }
        return $inserted;
    }

    /**
     * Remove um anexo
     */
    public function deleteAnexo(int $anexoId, int $sancaoId): bool {
        $old = $this->fetchOne("SELECT * FROM sancao_anexos WHERE id = ? AND sancao_id = ?", [$anexoId, $sancaoId]);
        if (!$old) return false;

        $path = __DIR__ . '/../../../' . $old['arquivo'];
        if (file_exists($path)) @unlink($path);

        $deleted = $this->execute("DELETE FROM sancao_anexos WHERE id = ?", [$anexoId]) > 0;

        if ($deleted) {
            $this->audit('DELETE', 'sancao_anexos', $anexoId, $old, null);
        }
        return $deleted;
    }
}
