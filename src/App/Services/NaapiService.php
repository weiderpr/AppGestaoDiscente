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

        $newId = (int)$this->db->lastInsertId();
        $this->audit('CREATE', 'alunos_naapi', $newId, null, $data);
        
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
        
        $old = $this->fetchOne("SELECT * FROM alunos_naapi WHERE id = ?", [$id]);

        $this->execute($sql, [
            $data['data_inclusao'],
            $data['neurodivergencia'],
            $data['campo_texto'],
            $data['observacoes_publicas'],
            $id
        ]);
        
        $this->audit('UPDATE', 'alunos_naapi', $id, $old, $data);

        return true;
    }

    /**
     * Remove um aluno do NAAPI
     */
    public function delete(int $id): bool {
        $old = $this->fetchOne("SELECT * FROM alunos_naapi WHERE id = ?", [$id]);
        $sql = "DELETE FROM alunos_naapi WHERE id = ?";
        $deleted = $this->execute($sql, [$id]) > 0;
        
        if ($deleted && $old) {
            $this->audit('DELETE', 'alunos_naapi', $id, $old, null);
        }
        
        return $deleted;
    }

    /**
     * Busca anexos de um aluno no NAAPI
     */
    public function getAnexos(int $alunoId): array {
        $sql = "
            SELECT a.*, u.name as author_name 
            FROM alunos_naapi_anexos a
            JOIN users u ON a.usuario_id = u.id
            WHERE a.aluno_id = ?
            ORDER BY a.created_at DESC
        ";
        return $this->fetchAll($sql, [$alunoId]);
    }

    /**
     * Adiciona um anexo ao aluno no NAAPI
     */
    public function addAnexo(array $data): int {
        $sql = "INSERT INTO alunos_naapi_anexos (aluno_id, usuario_id, nome_arquivo, caminho_arquivo, tipo_arquivo, tamanho_bytes) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $this->execute($sql, [
            $data['aluno_id'],
            $data['usuario_id'],
            $data['nome_arquivo'],
            $data['caminho_arquivo'],
            $data['tipo_arquivo'],
            $data['tamanho_bytes']
        ]);
        $id = (int)$this->db->lastInsertId();
        $this->audit('CREATE', 'alunos_naapi_anexos', $id, null, $data);
        return $id;
    }

    /**
     * Remove um anexo do NAAPI
     */
    public function deleteAnexo(int $id): bool {
        $old = $this->fetchOne("SELECT * FROM alunos_naapi_anexos WHERE id = ?", [$id]);
        $deleted = $this->execute("DELETE FROM alunos_naapi_anexos WHERE id = ?", [$id]) > 0;
        if ($deleted && $old) {
            $this->audit('DELETE', 'alunos_naapi_anexos', $id, $old, null);
        }
        return $deleted;
    }

    /**
     * Busca relatos/ocorrências de um aluno no NAAPI
     */
    public function getOcorrencias(int $alunoId, int $institutionId, int $currentUserId, string $profile): array {
        $sql = "
            SELECT o.*, u.name as usuario_nome, u.photo as usuario_foto
            FROM naapi_ocorrencias o
            JOIN users u ON o.usuario_id = u.id
            WHERE o.aluno_id = ? AND o.institution_id = ?
              AND (o.is_privado = 0 OR o.usuario_id = ? OR ? = 'Administrador')
            ORDER BY o.data_ocorrencia DESC, o.created_at DESC
        ";
        return $this->fetchAll($sql, [$alunoId, $institutionId, $currentUserId, $profile]);
    }

    /**
     * Salva ou atualiza um relato/ocorrência
     */
    public function saveOcorrencia(array $data): int {
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            $old = $this->fetchOne("SELECT * FROM naapi_ocorrencias WHERE id = ?", [$id]);
            
            $sql = "UPDATE naapi_ocorrencias SET texto = ?, is_privado = ?, data_ocorrencia = ? WHERE id = ?";
            $this->execute($sql, [
                $data['texto'],
                (int)$data['is_privado'],
                $data['data_ocorrencia'],
                $id
            ]);
            $this->audit('UPDATE', 'naapi_ocorrencias', $id, $old, $data);
            return $id;
        } else {
            $sql = "INSERT INTO naapi_ocorrencias (aluno_id, institution_id, usuario_id, texto, is_privado, data_ocorrencia) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $this->execute($sql, [
                $data['aluno_id'],
                $data['institution_id'],
                $data['usuario_id'],
                $data['texto'],
                (int)$data['is_privado'],
                $data['data_ocorrencia']
            ]);
            $id = (int)$this->db->lastInsertId();
            $this->audit('CREATE', 'naapi_ocorrencias', $id, null, $data);
            return $id;
        }
    }

    /**
     * Remove um relato/ocorrência
     */
    public function deleteOcorrencia(int $id): bool {
        $old = $this->fetchOne("SELECT * FROM naapi_ocorrencias WHERE id = ?", [$id]);
        $deleted = $this->execute("DELETE FROM naapi_ocorrencias WHERE id = ?", [$id]) > 0;
        if ($deleted && $old) {
            $this->audit('DELETE', 'naapi_ocorrencias', $id, $old, null);
        }
        return $deleted;
    }

    /**
     * Busca alunos que ainda não estão no NAAPI para a instituição
     */
    public function searchAlunosNotInNaapi(int $institutionId, string $query): array {
        $term = "%$query%";
        $sql = "
            SELECT DISTINCT a.id, a.nome, a.matricula, a.photo
            FROM alunos a
            JOIN turma_alunos ta ON ta.aluno_id = a.id
            JOIN turmas t ON t.id = ta.turma_id
            JOIN courses c ON c.id = t.course_id
            WHERE c.institution_id = ?
              AND a.deleted_at IS NULL
              AND (a.nome LIKE ? OR a.matricula LIKE ?)
              AND a.id NOT IN (
                  SELECT aluno_id FROM alunos_naapi WHERE institution_id = ?
              )
            ORDER BY a.nome ASC
            LIMIT 15
        ";
        return $this->fetchAll($sql, [$institutionId, $term, $term, $institutionId]);
    }

    /**
     * Verifica se o aluno já está no NAAPI
     */
    public function exists(int $alunoId): bool {
        return (bool)$this->getByAlunoId($alunoId);
    }
}
