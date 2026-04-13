<?php
/**
 * Vértice Acadêmico — Serviço NAAPI
 */

namespace App\Services;

class NaapiService extends Service {

    /**
     * Lista alunos no NAAPI por instituição
     */
    public function getAll(int $institutionId, string $search = ''): array {
        $sql = "
            SELECT 
                na.*,
                a.nome as aluno_nome,
                a.matricula as aluno_matricula,
                a.photo as aluno_photo,
                t.description as turma_nome,
                c.name as curso_nome
            FROM alunos_naapi na
            JOIN alunos a ON a.id = na.aluno_id
            LEFT JOIN turma_alunos ta ON ta.aluno_id = a.id
            LEFT JOIN turmas t ON t.id = ta.turma_id
            LEFT JOIN courses c ON c.id = t.course_id
            WHERE na.institution_id = ?
        ";
        $params = [$institutionId];

        if (!empty($search)) {
            $sql .= " AND (a.nome LIKE ? OR a.matricula LIKE ? OR na.neurodivergencia LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY a.nome ASC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Busca dados de um registro NAAPI por ID
     */
    public function getById(int $id): ?array {
        $sql = "
            SELECT na.*, a.nome as aluno_nome, a.matricula as aluno_matricula
            FROM alunos_naapi na
            JOIN alunos a ON a.id = na.aluno_id
            WHERE na.id = ?
        ";
        return $this->fetchOne($sql, [$id]);
    }

    /**
     * Busca dados de um registro NAAPI pelo ID do aluno
     */
    public function getByAlunoId(int $alunoId): ?array {
        $sql = "SELECT * FROM alunos_naapi WHERE aluno_id = ?";
        return $this->fetchOne($sql, [$alunoId]);
    }

    /**
     * Adiciona um aluno ao NAAPI
     */
    public function add(array $data): bool {
        // Verificar se aluno já está no NAAPI
        if ($this->getByAlunoId((int)$data['aluno_id'])) {
            throw new \Exception("Este aluno já está cadastrado no NAAPI.");
        }

        $sql = "
            INSERT INTO alunos_naapi 
            (aluno_id, institution_id, data_inclusao, neurodivergencia, campo_texto, observacoes_publicas)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        
        $this->execute($sql, [
            (int)$data['aluno_id'],
            (int)$data['institution_id'],
            $data['data_inclusao'] ?? date('Y-m-d'),
            $data['neurodivergencia'] ?? null,
            $data['campo_texto'] ?? null,
            $data['observacoes_publicas'] ?? null
        ]);
        
        return true;
    }

    /**
     * Atualiza dados de um aluno no NAAPI
     */
    public function update(int $id, array $data): bool {
        $sql = "
            UPDATE alunos_naapi 
            SET 
                data_inclusao = ?,
                neurodivergencia = ?,
                campo_texto = ?,
                observacoes_publicas = ?
            WHERE id = ?
        ";
        
        $this->execute($sql, [
            $data['data_inclusao'],
            $data['neurodivergencia'],
            $data['campo_texto'],
            $data['observacoes_publicas'],
            $id
        ]);
        
        return true;
    }

    /**
     * Remove um aluno do NAAPI
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM alunos_naapi WHERE id = ?";
        return $this->execute($sql, [$id]) > 0;
    }

    /**
     * Busca alunos que NÃO estão no NAAPI em uma instituição (para autocomplete)
     */
    public function searchAlunosNotInNaapi(int $institutionId, string $query): array {
        $sql = "
            SELECT a.id, a.nome, a.matricula, a.photo
            FROM alunos a
            JOIN turma_alunos ta ON a.id = ta.aluno_id
            JOIN turmas t ON ta.turma_id = t.id
            JOIN courses c ON t.course_id = c.id
            WHERE c.institution_id = ? 
              AND a.deleted_at IS NULL
              AND NOT EXISTS (SELECT 1 FROM alunos_naapi na WHERE na.aluno_id = a.id)
              AND (a.nome LIKE ? OR a.matricula LIKE ?)
            LIMIT 10
        ";
        $term = "%$query%";
        return $this->fetchAll($sql, [$institutionId, $term, $term]);
    }
}
