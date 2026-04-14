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
             WHERE cc.id = ?',
            [$id]
        );
    }

    public function getAll(int $institutionId, int $turmaId = null, int $courseId = null): array {
        $sql = 'SELECT cc.*, c.name as course_name, t.description as turma_name
                FROM conselhos_classe cc
                INNER JOIN courses c ON cc.course_id = c.id
                INNER JOIN turmas t ON cc.turma_id = t.id
                WHERE cc.institution_id = ?';
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
            'INSERT INTO conselhos_classe (institution_id, course_id, turma_id, descricao, data_hora, local_reuniao, avaliacao_id) 
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $institutionId,
            $courseId,
            $turmaId,
            $data['descricao'],
            $data['data_hora'],
            $data['local_reuniao'] ?? null,
            (isset($data['avaliacao_id']) && $data['avaliacao_id'] > 0) ? $data['avaliacao_id'] : null
        ]);

        $conselhoId = $this->lastInsertId();

        if (!empty($data['etapas'])) {
            $stmt = $this->db->prepare('INSERT INTO conselhos_etapas (conselho_id, etapa_id) VALUES (?, ?)');
            foreach ($data['etapas'] as $etapaId) {
                $stmt->execute([$conselhoId, $etapaId]);
            }
        }

        $this->audit('CREATE', 'conselhos_classe', $conselhoId, null, array_merge(['institution_id' => $institutionId], $data));

        return ['success' => true, 'id' => $conselhoId];
    }

    public function update(int $id, array $data): array {
        $fields = [];
        $params = [];

        if (isset($data['course_id'])) {
            $fields[] = 'course_id = ?';
            $params[] = (int)$data['course_id'];
        }
        if (isset($data['turma_id'])) {
            $fields[] = 'turma_id = ?';
            $params[] = (int)$data['turma_id'];
        }
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
        if (isset($data['avaliacao_id'])) {
            $fields[] = 'avaliacao_id = ?';
            $params[] = $data['avaliacao_id'] > 0 ? $data['avaliacao_id'] : null;
        }

        if (empty($fields)) {
            return ['error' => 'Nenhum campo para atualizar'];
        }

        $old = $this->fetchOne('SELECT * FROM conselhos_classe WHERE id = ?', [$id]);
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

        $this->audit('UPDATE', 'conselhos_classe', $id, $old, $data);

        return ['success' => true];
    }

    public function toggleStatus(int $id): bool {
        $old = $this->fetchOne('SELECT id, is_active FROM conselhos_classe WHERE id = ?', [$id]);
        if (!$old) return false;

        $newStatus = $old['is_active'] ? 0 : 1;
        $updated = $this->execute(
            'UPDATE conselhos_classe SET is_active = ? WHERE id = ?',
            [$newStatus, $id]
        ) > 0;

        if ($updated) {
            $this->audit('UPDATE', 'conselhos_classe', $id, $old, ['is_active' => $newStatus]);
        }
        return $updated;
    }

    public function delete(int $id): bool {
        $old = $this->fetchOne('SELECT * FROM conselhos_classe WHERE id = ?', [$id]);
        $deleted = $this->execute(
            'DELETE FROM conselhos_classe WHERE id = ?',
            [$id]
        ) > 0;
        if ($deleted && $old) {
            $this->audit('DELETE', 'conselhos_classe', $id, $old, ['deleted' => true]);
        }
        return $deleted;
    }

    public function getEtapas(int $conselhoId): array {
        return $this->fetchAll(
            'SELECT e.* FROM etapas e
             INNER JOIN conselhos_etapas ce ON e.id = ce.etapa_id
             WHERE ce.conselho_id = ?',
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

        $newId = $this->lastInsertId();
        $this->audit('CREATE', 'conselhos_comentarios', $newId, null, [
            'conselho_id' => $conselhoId,
            'usuario_id' => $usuarioId,
            'aluno_id' => $alunoId,
            'conteudo' => $conteudo
        ]);

        return ['success' => true, 'id' => $newId];
    }

    public function getParticipantes(int $conselhoId): array {
        return $this->fetchAll(
            'SELECT u.id, u.name, u.email, u.profile
             FROM users u
             INNER JOIN conselhos_presentes cp ON u.id = cp.user_id
             WHERE cp.conselho_id = ?
             ORDER BY u.name',
            [$conselhoId]
        );
    }

    public function setParticipante(int $conselhoId, int $usuarioId): array {
        $old = $this->fetchOne('SELECT * FROM conselhos_presentes WHERE conselho_id = ? AND user_id = ?', [$conselhoId, $usuarioId]);
        
        if (!$old) {
            $this->db->prepare(
                'INSERT INTO conselhos_presentes (conselho_id, user_id) 
                 VALUES (?, ?)'
            )->execute([$conselhoId, $usuarioId]);
            
            $this->audit('CREATE', 'conselhos_presentes', $conselhoId, null, ['user_id' => $usuarioId]);
        }

        return ['success' => true];
    }

    public function removeParticipante(int $conselhoId, int $usuarioId): bool {
        $old = $this->fetchOne('SELECT * FROM conselhos_presentes WHERE conselho_id = ? AND user_id = ?', [$conselhoId, $usuarioId]);
        if (!$old) return false;

        $deleted = $this->execute(
            'DELETE FROM conselhos_presentes WHERE conselho_id = ? AND user_id = ?',
            [$conselhoId, $usuarioId]
        ) > 0;

        if ($deleted) {
            $this->audit('DELETE', 'conselhos_presentes', $conselhoId, $old, ['user_id' => $usuarioId]);
        }
        return $deleted;
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

    // --- REGISTROS DO CONSELHO (POST-ITS) ---

    public function addRegistro(int $conselhoId, int $userId, ?int $alunoId, string $texto): array {
        $this->db->prepare(
            "INSERT INTO conselho_registros (conselho_id, aluno_id, user_id, texto) VALUES (?, ?, ?, ?)"
        )->execute([$conselhoId, $alunoId > 0 ? $alunoId : null, $userId, $texto]);

        $newId = $this->lastInsertId();
        $this->audit('CREATE', 'conselho_registros', $newId, null, [
            'conselho_id' => $conselhoId,
            'aluno_id' => $alunoId,
            'user_id' => $userId,
            'texto' => $texto
        ]);

        return ['success' => true, 'id' => $newId];
    }

    public function deleteRegistro(int $id, int $requestUserId, string $requestUserProfile): bool {
        $old = $this->fetchOne(
            "SELECT cr.*, cc.is_active FROM conselho_registros cr 
             JOIN conselhos_classe cc ON cr.conselho_id = cc.id 
             WHERE cr.id = ?", 
            [$id]
        );
        if (!$old) throw new \Exception('Registro não encontrado.');
        if ($old['is_active'] == 0) throw new \Exception('Não é possível excluir registros de um conselho finalizado.');

        // Permissão: Autor ou Admin/Coord
        if ($old['user_id'] != $requestUserId && !in_array($requestUserProfile, ['Administrador', 'Coordenador'])) {
            throw new \Exception('Sem permissão para excluir este registro.');
        }

        $deleted = $this->execute("DELETE FROM conselho_registros WHERE id = ?", [$id]) > 0;
        if ($deleted) {
            $this->audit('DELETE', 'conselho_registros', $id, $old, ['deleted' => true]);
        }
        return $deleted;
    }

    // --- ENCAMINHAMENTOS (REFERRALS) ---

    public function addEncaminhamento(int $conselhoId, int $authorId, ?int $alunoId, array $data): array {
        $this->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO conselho_encaminhamentos (conselho_id, aluno_id, author_id, setor_tipo, texto, data_expectativa)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $conselhoId, 
                $alunoId > 0 ? $alunoId : null, 
                $authorId, 
                $data['setor_tipo'], 
                $data['texto'], 
                $data['data_expectativa'] ?: null
            ]);
            $encaminhamentoId = $this->lastInsertId();

            if (!empty($data['usuarios_id']) && is_array($data['usuarios_id'])) {
                $stmtUser = $this->db->prepare("INSERT INTO conselho_encaminhamento_usuarios (encaminhamento_id, user_id) VALUES (?, ?)");
                foreach ($data['usuarios_id'] as $uId) {
                    $uId = (int)$uId;
                    if ($uId > 0) $stmtUser->execute([$encaminhamentoId, $uId]);
                }
            }

            $this->audit('CREATE', 'conselho_encaminhamentos', $encaminhamentoId, null, array_merge(['conselho_id' => $conselhoId, 'aluno_id' => $alunoId, 'author_id' => $authorId], $data));
            $this->commit();
            return ['success' => true, 'id' => $encaminhamentoId];
        } catch (\Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function deleteEncaminhamento(int $id, int $requestUserId, string $requestUserProfile): bool {
        $old = $this->fetchOne(
            "SELECT ce.*, cc.is_active FROM conselho_encaminhamentos ce 
             JOIN conselhos_classe cc ON ce.conselho_id = cc.id 
             WHERE ce.id = ?", 
            [$id]
        );
        if (!$old) throw new \Exception('Encaminhamento não encontrado.');
        if ($old['is_active'] == 0) throw new \Exception('Não é possível excluir encaminhamentos de um conselho finalizado.');

        // Permissão: Autor ou Admin/Coord
        if ($old['author_id'] != $requestUserId && !in_array($requestUserProfile, ['Administrador', 'Coordenador'])) {
            throw new \Exception('Você não tem permissão para excluir este encaminhamento.');
        }

        $this->beginTransaction();
        try {
            $this->execute("DELETE FROM conselho_encaminhamento_usuarios WHERE encaminhamento_id = ?", [$id]);
            $deleted = $this->execute("DELETE FROM conselho_encaminhamentos WHERE id = ?", [$id]) > 0;
            if ($deleted) {
                $this->audit('DELETE', 'conselho_encaminhamentos', $id, $old, ['deleted' => true]);
            }
            $this->commit();
            return $deleted;
        } catch (\Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }
}
