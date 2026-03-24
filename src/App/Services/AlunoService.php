<?php
/**
 * Vértice Acadêmico — Serviço de Alunos
 */

namespace App\Services;

class AlunoService extends Service {
    public function findById(int $id): ?array {
        return $this->fetchOne(
            'SELECT * FROM alunos WHERE id = ? AND deleted_at IS NULL',
            [$id]
        );
    }

    public function findByMatricula(string $matricula): ?array {
        return $this->fetchOne(
            'SELECT * FROM alunos WHERE matricula = ? AND deleted_at IS NULL LIMIT 1',
            [$matricula]
        );
    }

    public function getAll(int $institutionId = null, string $search = '', int $limit = 50, int $offset = 0): array {
        $sql = 'SELECT * FROM alunos WHERE deleted_at IS NULL';
        $params = [];

        if ($search) {
            $sql .= ' AND (nome LIKE ? OR matricula LIKE ?)';
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $sql .= ' ORDER BY nome LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        return $this->fetchAll($sql, $params);
    }

    public function create(array $data): array {
        if ($this->findByMatricula($data['matricula'])) {
            return ['error' => 'Matrícula já cadastrada'];
        }

        $this->db->prepare(
            'INSERT INTO alunos (matricula, nome, telefone, email, photo) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $data['matricula'],
            trim($data['nome']),
            $data['telefone'] ?? null,
            $data['email'] ?? null,
            $data['photo'] ?? null
        ]);

        return ['success' => true, 'id' => $this->lastInsertId()];
    }

    public function update(int $id, array $data): array {
        $fields = [];
        $params = [];

        if (isset($data['nome'])) {
            $fields[] = 'nome = ?';
            $params[] = trim($data['nome']);
        }
        if (isset($data['telefone'])) {
            $fields[] = 'telefone = ?';
            $params[] = $data['telefone'];
        }
        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $params[] = $data['email'];
        }
        if (isset($data['photo'])) {
            $fields[] = 'photo = ?';
            $params[] = $data['photo'];
        }

        if (empty($fields)) {
            return ['error' => 'Nenhum campo para atualizar'];
        }

        $params[] = $id;
        $sql = 'UPDATE alunos SET ' . implode(', ', $fields) . ' WHERE id = ?';
        
        $this->execute($sql, $params);
        return ['success' => true];
    }

    public function delete(int $id): bool {
        return $this->execute(
            'UPDATE alunos SET deleted_at = NOW() WHERE id = ?',
            [$id]
        ) > 0;
    }

    public function count(int $institutionId = null): int {
        $stmt = $this->db->query('SELECT COUNT(*) FROM alunos WHERE deleted_at IS NULL');
        return (int) $stmt->fetchColumn();
    }

    public function getTurmas(int $alunoId): array {
        return $this->fetchAll(
            'SELECT t.*, c.name as course_name
             FROM turmas t
             INNER JOIN turma_alunos ta ON t.id = ta.turma_id
             INNER JOIN courses c ON t.course_id = c.id
             WHERE ta.aluno_id = ? AND t.deleted_at IS NULL
             ORDER BY t.ano DESC, t.description',
            [$alunoId]
        );
    }

    public function getComentarios(int $alunoId, int $turmaId = null): array {
        $sql = 'SELECT cp.*, u.name as professor_name, u.profile as professor_profile,
                       t.description as turma_name
                FROM comentarios_professores cp
                INNER JOIN users u ON cp.professor_id = u.id
                LEFT JOIN turmas t ON cp.turma_id = t.id
                WHERE cp.aluno_id = ?';
        $params = [$alunoId];

        if ($turmaId) {
            $sql .= ' AND cp.turma_id = ?';
            $params[] = $turmaId;
        }

        $sql .= ' ORDER BY cp.created_at DESC';
        return $this->fetchAll($sql, $params);
    }

    public function addComentario(int $alunoId, int $turmaId, int $professorId, string $conteudo): array {
        $this->db->prepare(
            'INSERT INTO comentarios_professores (aluno_id, turma_id, professor_id, conteudo) 
             VALUES (?, ?, ?, ?)'
        )->execute([$alunoId, $turmaId, $professorId, $conteudo]);

        return ['success' => true, 'id' => $this->lastInsertId()];
    }

    public function getNotas(int $alunoId, int $turmaId = null): array {
        $sql = 'SELECT en.*, e.description as etapa_name, d.descricao as disciplina_nome
                FROM etapa_notas en
                INNER JOIN etapas e ON en.etapa_id = e.id
                INNER JOIN disciplinas d ON en.disciplina_codigo = d.codigo
                WHERE en.aluno_id = ?';
        $params = [$alunoId];

        if ($turmaId) {
            $sql .= ' AND e.turma_id = ?';
            $params[] = $turmaId;
        }

        $sql .= ' ORDER BY e.id, d.descricao';
        return $this->fetchAll($sql, $params);
    }

    public function getAlunosSemTurma(): array {
        return $this->fetchAll(
            'SELECT a.* FROM alunos a 
             LEFT JOIN turma_alunos ta ON ta.aluno_id = a.id 
             WHERE ta.aluno_id IS NULL AND a.deleted_at IS NULL
             ORDER BY a.nome ASC'
        );
    }
}
