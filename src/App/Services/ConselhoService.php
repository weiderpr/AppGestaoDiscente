<?php
/**
 * Vértice Acadêmico — Serviço de Conselhos de Classe
 */

namespace App\Services;

class ConselhoService extends Service {
    public function findById(int $id): ?array {
        return $this->fetchOne(
            'SELECT cc.*, i.name as institution_name, c.name as course_name, t.description as turma_name
             FROM conselhos_classe cc
             INNER JOIN institutions i ON cc.institution_id = i.id
             INNER JOIN courses c ON cc.course_id = c.id
             INNER JOIN turmas t ON cc.turma_id = t.id
             WHERE cc.id = ? AND cc.deleted_at IS NULL',
            [$id]
        );
    }

    public function getAll(int $institutionId, int $turmaId = null, int $courseId = null): array {
        $sql = 'SELECT cc.*, c.name as course_name, t.description as turma_name
                FROM conselhos_classe cc
                INNER JOIN courses c ON cc.course_id = c.id
                INNER JOIN turmas t ON cc.turma_id = t.id
                WHERE cc.institution_id = ? AND cc.deleted_at IS NULL';
        $params = [$institutionId];

        if ($courseId) {
            $sql .= ' AND cc.course_id = ?';
            $params[] = $courseId;
        }

        if ($turmaId) {
            $sql .= ' AND cc.turma_id = ?';
            $params[] = $turmaId;
        }

        $sql .= ' ORDER BY cc.data_hora DESC';
        return $this->fetchAll($sql, $params);
    }

    public function create(int $institutionId, int $courseId, int $turmaId, array $data): array {
        $this->db->prepare(
            'INSERT INTO conselhos_classe (institution_id, course_id, turma_id, descricao, data_hora, local_reuniao) 
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $institutionId,
            $courseId,
            $turmaId,
            $data['descricao'],
            $data['data_hora'],
            $data['local_reuniao'] ?? null
        ]);

        $conselhoId = $this->lastInsertId();

        if (!empty($data['etapas'])) {
            $stmt = $this->db->prepare('INSERT INTO conselhos_etapas (conselho_id, etapa_id) VALUES (?, ?)');
            foreach ($data['etapas'] as $etapaId) {
                $stmt->execute([$conselhoId, $etapaId]);
            }
        }

        return ['success' => true, 'id' => $conselhoId];
    }

    public function update(int $id, array $data): array {
        $fields = [];
        $params = [];

        if (isset($data['descricao'])) {
            $fields[] = 'descricao = ?';
            $params[] = $data['descricao'];
        }
        if (isset($data['data_hora'])) {
            $fields[] = 'data_hora = ?';
            $params[] = $data['data_hora'];
        }
        if (isset($data['local_reuniao'])) {
            $fields[] = 'local_reuniao = ?';
            $params[] = $data['local_reuniao'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
        }

        if (empty($fields)) {
            return ['error' => 'Nenhum campo para atualizar'];
        }

        $params[] = $id;
        $sql = 'UPDATE conselhos_classe SET ' . implode(', ', $fields) . ' WHERE id = ?';
        
        $this->execute($sql, $params);

        if (isset($data['etapas'])) {
            $this->db->prepare('DELETE FROM conselhos_etapas WHERE conselho_id = ?')->execute([$id]);
            $stmt = $this->db->prepare('INSERT INTO conselhos_etapas (conselho_id, etapa_id) VALUES (?, ?)');
            foreach ($data['etapas'] as $etapaId) {
                $stmt->execute([$id, $etapaId]);
            }
        }

        return ['success' => true];
    }

    public function delete(int $id): bool {
        return $this->execute(
            'UPDATE conselhos_classe SET deleted_at = NOW() WHERE id = ?',
            [$id]
        ) > 0;
    }

    public function getEtapas(int $conselhoId): array {
        return $this->fetchAll(
            'SELECT e.* FROM etapas e
             INNER JOIN conselhos_etapas ce ON e.id = ce.etapa_id
             WHERE ce.conselho_id = ? AND e.deleted_at IS NULL',
            [$conselhoId]
        );
    }

    public function getComentarios(int $conselhoId): array {
        return $this->fetchAll(
            'SELECT cc.*, u.name as usuario_name, u.profile as usuario_profile, a.nome as aluno_nome
             FROM conselhos_comentarios cc
             INNER JOIN users u ON cc.usuario_id = u.id
             LEFT JOIN alunos a ON cc.aluno_id = a.id
             WHERE cc.conselho_id = ?
             ORDER BY cc.created_at ASC',
            [$conselhoId]
        );
    }

    public function addComentario(int $conselhoId, int $usuarioId, int $alunoId = null, string $conteudo): array {
        $this->db->prepare(
            'INSERT INTO conselhos_comentarios (conselho_id, usuario_id, aluno_id, conteudo) 
             VALUES (?, ?, ?, ?)'
        )->execute([$conselhoId, $usuarioId, $alunoId > 0 ? $alunoId : null, $conteudo]);

        return ['success' => true, 'id' => $this->lastInsertId()];
    }

    public function getParticipantes(int $conselhoId): array {
        return $this->fetchAll(
            'SELECT u.id, u.name, u.email, u.profile, cp.presente
             FROM users u
             INNER JOIN conselhos_presentes cp ON u.id = cp.usuario_id
             WHERE cp.conselho_id = ? AND u.deleted_at IS NULL
             ORDER BY u.name',
            [$conselhoId]
        );
    }

    public function setParticipante(int $conselhoId, int $usuarioId, bool $presente = true): array {
        $this->db->prepare(
            'INSERT INTO conselhos_presentes (conselho_id, usuario_id, presente) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE presente = ?'
        )->execute([$conselhoId, $usuarioId, $presente ? 1 : 0, $presente ? 1 : 0]);

        return ['success' => true];
    }

    public function getAlunosDiscussao(int $conselhoId): array {
        return $this->fetchAll(
            'SELECT a.id, a.nome, a.matricula, a.photo,
                    COUNT(DISTINCT cc2.id) as comentarios_count
             FROM alunos a
             INNER JOIN comentarios_professores cc ON a.id = cc.aluno_id
             INNER JOIN turmas t ON cc.turma_id = t.id
             LEFT JOIN conselhos_comentarios cc2 ON cc2.aluno_id = a.id
             WHERE t.id = (SELECT turma_id FROM conselhos_classe WHERE id = ?)
             GROUP BY a.id
             ORDER BY comentarios_count DESC, a.nome',
            [$conselhoId]
        );
    }
}
