<?php
/**
 * Vértice Acadêmico — Serviço de Turmas
 */

namespace App\Services;

use PDOException;

class TurmaService extends Service {
    public function findById(int $id): ?array {
        return $this->fetchOne(
            'SELECT * FROM turmas WHERE id = ? AND deleted_at IS NULL',
            [$id]
        );
    }

    public function getAll(int $courseId, string $search = ''): array {
        $sql = 'SELECT * FROM turmas 
                WHERE course_id = ? AND is_active = 1 AND deleted_at IS NULL';
        $params = [$courseId];

        if ($search) {
            $sql .= ' AND description LIKE ?';
            $params[] = "%{$search}%";
        }

        $sql .= ' ORDER BY ano DESC, description';
        return $this->fetchAll($sql, $params);
    }

    public function create(int $courseId, array $data): array {
        $this->db->prepare(
            'INSERT INTO turmas (course_id, description, ano, nota_maxima, media_aprovacao) 
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $courseId,
            trim($data['description']),
            (int) $data['ano'],
            (float) ($data['nota_maxima'] ?? 10.00),
            (float) ($data['media_aprovacao'] ?? 6.00)
        ]);

        return ['success' => true, 'id' => $this->lastInsertId()];
    }

    public function update(int $id, array $data): array {
        $fields = [];
        $params = [];

        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $params[] = trim($data['description']);
        }
        if (isset($data['ano'])) {
            $fields[] = 'ano = ?';
            $params[] = (int) $data['ano'];
        }
        if (isset($data['nota_maxima'])) {
            $fields[] = 'nota_maxima = ?';
            $params[] = (float) $data['nota_maxima'];
        }
        if (isset($data['media_aprovacao'])) {
            $fields[] = 'media_aprovacao = ?';
            $params[] = (float) $data['media_aprovacao'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
        }

        if (empty($fields)) {
            return ['error' => 'Nenhum campo para atualizar'];
        }

        $params[] = $id;
        $sql = 'UPDATE turmas SET ' . implode(', ', $fields) . ' WHERE id = ?';
        
        $this->execute($sql, $params);
        return ['success' => true];
    }

    public function delete(int $id): bool {
        return $this->execute(
            'UPDATE turmas SET deleted_at = NOW() WHERE id = ?',
            [$id]
        ) > 0;
    }

    public function getAlunos(int $turmaId): array {
        return $this->fetchAll(
            'SELECT a.* FROM alunos a
             INNER JOIN turma_alunos ta ON a.id = ta.aluno_id
             WHERE ta.turma_id = ? AND a.deleted_at IS NULL
             ORDER BY a.nome',
            [$turmaId]
        );
    }

    public function getAlunoCount(int $turmaId): int {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM turma_alunos ta
             INNER JOIN alunos a ON a.id = ta.aluno_id
             WHERE ta.turma_id = ? AND a.deleted_at IS NULL'
        );
        $stmt->execute([$turmaId]);
        return (int) $stmt->fetchColumn();
    }

    public function addAluno(int $turmaId, int $alunoId): array {
        try {
            $this->db->prepare(
                'INSERT IGNORE INTO turma_alunos (turma_id, aluno_id) VALUES (?, ?)'
            )->execute([$turmaId, $alunoId]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['error' => 'Erro ao adicionar aluno à turma'];
        }
    }

    public function removeAluno(int $turmaId, int $alunoId): bool {
        return $this->execute(
            'DELETE FROM turma_alunos WHERE turma_id = ? AND aluno_id = ?',
            [$turmaId, $alunoId]
        ) > 0;
    }

    public function getEtapas(int $turmaId): array {
        return $this->fetchAll(
            'SELECT * FROM etapas 
             WHERE turma_id = ? AND is_active = 1 AND deleted_at IS NULL
             ORDER BY id',
            [$turmaId]
        );
    }

    public function getDisciplinas(int $turmaId): array {
        return $this->fetchAll(
            'SELECT d.*, td.id as turma_disciplina_id
             FROM disciplinas d
             INNER JOIN turma_disciplinas td ON d.codigo = td.disciplina_codigo
             WHERE td.turma_id = ? AND d.deleted_at IS NULL
             ORDER BY d.descricao',
            [$turmaId]
        );
    }
}
