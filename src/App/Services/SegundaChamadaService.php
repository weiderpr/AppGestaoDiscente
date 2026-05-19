<?php
/**
 * Vértice Acadêmico — Serviço de Segunda Chamada
 */

namespace App\Services;

class SegundaChamadaService extends Service {

    /**
     * Lista todas as solicitações de segunda chamada por instituição com opção de busca e escopo de coordenador
     */
    public function getAll(int $institutionId, string $search = '', ?int $coordinatorUserId = null, ?string $status = null): array {
        $sql = "
            SELECT 
                sc.*,
                a.nome as aluno_nome,
                a.matricula as aluno_matricula,
                a.photo as aluno_photo,
                d.descricao as disciplina_nome
            FROM segunda_chamada sc
            JOIN alunos a ON sc.aluno_id = a.id
            JOIN disciplinas d ON sc.disciplina_codigo = d.codigo
            WHERE sc.institution_id = ?
        ";
        $params = [$institutionId];

        if ($coordinatorUserId !== null) {
            $sql .= " AND a.id IN (
                SELECT DISTINCT ta2.aluno_id
                FROM turma_alunos ta2
                JOIN turmas t2 ON ta2.turma_id = t2.id
                JOIN course_coordinators cc2 ON t2.course_id = cc2.course_id
                WHERE cc2.user_id = ?
            )";
            $params[] = $coordinatorUserId;
        }

        if ($status !== null && $status !== '') {
            $sql .= " AND sc.status = ?";
            $params[] = $status;
        }

        if (!empty($search)) {
            $sql .= " AND (a.nome LIKE ? OR a.matricula LIKE ? OR d.descricao LIKE ? OR sc.justificativa LIKE ?)";
            $term = "%$search%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY sc.created_at DESC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Busca uma solicitação pelo ID
     */
    public function getById(int $id): ?array {
        $sql = "
            SELECT 
                sc.*, 
                a.nome as aluno_nome, 
                a.matricula as aluno_matricula,
                d.descricao as disciplina_nome,
                ta.turma_id
            FROM segunda_chamada sc
            JOIN alunos a ON sc.aluno_id = a.id
            JOIN disciplinas d ON sc.disciplina_codigo = d.codigo
            LEFT JOIN turma_alunos ta ON ta.aluno_id = a.id
            WHERE sc.id = ?
            ORDER BY ta.created_at DESC
            LIMIT 1
        ";
        return $this->fetchOne($sql, [$id]);
    }

    /**
     * Adiciona uma nova solicitação de segunda chamada
     */
    public function add(array $data): int {
        $sql = "
            INSERT INTO segunda_chamada 
            (aluno_id, telefone_aluno, email_aluno, nome_responsavel, telefone_responsavel, 
             disciplina_codigo, atividade_nome, justificativa, anexo_caminho, anexo_nome, anexo_tipo, anexo_tamanho, 
             data_atividade_perdida, institution_id, usuario_id, status, observacoes_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $this->execute($sql, [
            (int)$data['aluno_id'],
            trim($data['telefone_aluno']),
            trim($data['email_aluno']),
            !empty($data['nome_responsavel']) ? trim($data['nome_responsavel']) : null,
            !empty($data['telefone_responsavel']) ? trim($data['telefone_responsavel']) : null,
            $data['disciplina_codigo'],
            trim($data['atividade_nome'] ?? ''),
            trim($data['justificativa']),
            $data['anexo_caminho'] ?? null,
            $data['anexo_nome'] ?? null,
            $data['anexo_tipo'] ?? null,
            $data['anexo_tamanho'] ?? null,
            $data['data_atividade_perdida'],
            (int)$data['institution_id'],
            (int)$data['usuario_id'],
            $data['status'] ?? 'Pendente',
            $data['observacoes_status'] ?? null
        ]);

        $newId = (int)$this->db->lastInsertId();
        $this->audit('CREATE', 'segunda_chamada', $newId, null, $data);
        
        return $newId;
    }

    /**
     * Atualiza uma solicitação existente
     */
    public function update(int $id, array $data): bool {
        $old = $this->fetchOne("SELECT * FROM segunda_chamada WHERE id = ?", [$id]);
        if (!$old) {
            throw new \Exception("Solicitação não encontrada.");
        }

        $sql = "
            UPDATE segunda_chamada 
            SET 
                telefone_aluno = ?,
                email_aluno = ?,
                nome_responsavel = ?,
                telefone_responsavel = ?,
                disciplina_codigo = ?,
                atividade_nome = ?,
                justificativa = ?,
                data_atividade_perdida = ?,
                status = ?,
                observacoes_status = ?
        ";
        $params = [
            trim($data['telefone_aluno']),
            trim($data['email_aluno']),
            !empty($data['nome_responsavel']) ? trim($data['nome_responsavel']) : null,
            !empty($data['telefone_responsavel']) ? trim($data['telefone_responsavel']) : null,
            $data['disciplina_codigo'],
            trim($data['atividade_nome'] ?? ''),
            trim($data['justificativa']),
            $data['data_atividade_perdida'],
            $data['status'] ?? $old['status'],
            $data['observacoes_status'] ?? $old['observacoes_status']
        ];

        // Se um novo anexo foi fornecido, atualiza os campos de anexo
        if (array_key_exists('anexo_caminho', $data)) {
            $sql .= ", anexo_caminho = ?, anexo_nome = ?, anexo_tipo = ?, anexo_tamanho = ?";
            $params[] = $data['anexo_caminho'];
            $params[] = $data['anexo_nome'];
            $params[] = $data['anexo_tipo'];
            $params[] = $data['anexo_tamanho'];
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $updated = $this->execute($sql, $params) > 0;
        
        $this->audit('UPDATE', 'segunda_chamada', $id, $old, $data);

        return $updated;
    }

    /**
     * Remove uma solicitação
     */
    public function delete(int $id): bool {
        $old = $this->fetchOne("SELECT * FROM segunda_chamada WHERE id = ?", [$id]);
        if (!$old) return false;

        // Tentar excluir o arquivo de anexo físico do servidor se existir
        if (!empty($old['anexo_caminho'])) {
            $file = __DIR__ . '/../../' . $old['anexo_caminho'];
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        $deleted = $this->execute("DELETE FROM segunda_chamada WHERE id = ?", [$id]) > 0;
        
        if ($deleted) {
            $this->audit('DELETE', 'segunda_chamada', $id, $old, null);
        }
        
        return $deleted;
    }

    /**
     * Busca alunos ativos na instituição para autocomplete com escopo opcional de coordenador
     */
    public function searchAlunos(int $institutionId, string $query, ?int $coordinatorUserId = null): array {
        $term = "%$query%";
        $sql = "
            SELECT DISTINCT 
                a.id, 
                a.nome, 
                a.matricula, 
                a.photo, 
                a.email, 
                a.telefone,
                t.id as turma_id,
                t.description as serie,
                c.name as curso
            FROM alunos a
            JOIN turma_alunos ta ON ta.aluno_id = a.id
            JOIN turmas t ON t.id = ta.turma_id
            JOIN courses c ON c.id = t.course_id
            WHERE c.institution_id = ?
              AND a.deleted_at IS NULL
              AND (a.nome LIKE ? OR a.matricula LIKE ?)
        ";
        $params = [$institutionId, $term, $term];

        if ($coordinatorUserId !== null) {
            $sql .= " AND c.id IN (SELECT course_id FROM course_coordinators WHERE user_id = ?)";
            $params[] = $coordinatorUserId;
        }

        $sql .= " ORDER BY a.nome ASC LIMIT 15";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Busca todas as disciplinas ativas da instituição
     */
    public function getDisciplinas(int $institutionId): array {
        $sql = "
            SELECT codigo, descricao 
            FROM disciplinas 
            WHERE institution_id = ? 
            ORDER BY descricao ASC
        ";
        return $this->fetchAll($sql, [$institutionId]);
    }

    /**
     * Busca disciplinas vinculadas à turma do aluno, excluindo as que ele tenha dispensa ativa
     */
    public function getStudentDisciplines(int $alunoId, int $turmaId): array {
        $sql = "
            SELECT DISTINCT 
                d.codigo, 
                d.descricao 
            FROM turma_disciplinas td
            JOIN disciplinas d ON td.disciplina_codigo = d.codigo
            WHERE td.turma_id = ?
              AND td.disciplina_codigo NOT IN (
                  SELECT ad.disciplina_codigo 
                  FROM alunos_dispensa ad 
                  WHERE ad.aluno_id = ? 
                    AND ad.turma_id = ? 
                    AND ad.is_active = 1
              )
            ORDER BY d.descricao ASC
        ";
        return $this->fetchAll($sql, [$turmaId, $alunoId, $turmaId]);
    }

    /**
     * Resolve a turma_id ativa mais recente de um aluno
     */
    public function getStudentTurma(int $alunoId): ?int {
        $sql = "SELECT turma_id FROM turma_alunos WHERE aluno_id = ? ORDER BY id DESC LIMIT 1";
        $row = $this->fetchOne($sql, [$alunoId]);
        return $row ? (int)$row['turma_id'] : null;
    }

    /**
     * Busca metadados resumidos do aluno (nome, matricula, serie, curso, turma_id)
     */
    public function getStudentMeta(int $alunoId): ?array {
        $sql = "
            SELECT a.nome, a.matricula, t.description as serie, c.name as curso, ta.turma_id
            FROM alunos a
            JOIN turma_alunos ta ON ta.aluno_id = a.id
            JOIN turmas t ON t.id = ta.turma_id
            JOIN courses c ON c.id = t.course_id
            WHERE a.id = ?
            ORDER BY ta.created_at DESC
            LIMIT 1
        ";
        return $this->fetchOne($sql, [$alunoId]);
    }

    /**
     * Busca o nome de uma disciplina pelo código
     */
    public function getDisciplinaName(string $codigo): string {
        $sql = "SELECT descricao FROM disciplinas WHERE codigo = ?";
        $row = $this->fetchOne($sql, [$codigo]);
        return $row ? $row['descricao'] : $codigo;
    }

    /**
     * Busca os e-mails e nomes dos professores vinculados a uma disciplina em uma turma
     */
    public function getProfessoresByTurmaDisciplina(int $turmaId, string $disciplinaCodigo): array {
        $sql = "
            SELECT DISTINCT u.email, u.name
            FROM turma_disciplina_professores tdp
            JOIN turma_disciplinas td ON tdp.turma_disciplina_id = td.id
            JOIN users u ON tdp.professor_id = u.id
            WHERE td.turma_id = ? AND td.disciplina_codigo = ? AND u.email IS NOT NULL AND u.email != ''
        ";
        return $this->fetchAll($sql, [$turmaId, $disciplinaCodigo]);
    }

    /**
     * Busca o coordenador do curso associado a uma turma
     */
    public function getCoordenadorByTurma(int $turmaId): ?array {
        $sql = "
            SELECT u.email, u.name
            FROM turmas t
            JOIN course_coordinators cc ON t.course_id = cc.course_id
            JOIN users u ON cc.user_id = u.id
            WHERE t.id = ?
            LIMIT 1
        ";
        return $this->fetchOne($sql, [$turmaId]);
    }

    /**
     * Verifica se um usuário coordena o curso de um aluno específico
     */
    public function isCoordinatorOfStudent(int $userId, int $alunoId): bool {
        $sql = "
            SELECT 1 
            FROM turma_alunos ta
            JOIN turmas t ON ta.turma_id = t.id
            JOIN course_coordinators cc ON t.course_id = cc.course_id
            WHERE cc.user_id = ? AND ta.aluno_id = ?
            LIMIT 1
        ";
        return (bool)$this->fetchOne($sql, [$userId, $alunoId]);
    }

    /**
     * Atualiza o encaminhamento e o status da solicitação
     */
    public function updateStatusAndReferral(int $id, string $encaminhamento, string $justificativa): bool {
        $old = $this->fetchOne("SELECT * FROM segunda_chamada WHERE id = ?", [$id]);
        if (!$old) {
            throw new \Exception("Solicitação não encontrada.");
        }

        $status = 'Pendente';
        if ($encaminhamento === 'Deferido Ad Referendum' || $encaminhamento === 'Deferido pelo Colegiado') {
            $status = 'Deferido';
        } elseif ($encaminhamento === 'Indeferido') {
            $status = 'Indeferido';
        }

        // Formatamos as observações de status para armazenar o tipo de encaminhamento de forma legível
        $observacoes = trim($justificativa);
        $observacoesCompleta = $encaminhamento . (!empty($observacoes) ? " — " . $observacoes : "");

        $sql = "UPDATE segunda_chamada SET status = ?, observacoes_status = ? WHERE id = ?";
        $updated = $this->execute($sql, [$status, $observacoesCompleta, $id]) > 0;

        $this->audit('UPDATE_STATUS', 'segunda_chamada', $id, $old, [
            'status' => $status,
            'encaminhamento' => $encaminhamento,
            'justificativa' => $justificativa,
            'observacoes_status' => $observacoesCompleta
        ]);

        return $updated;
    }

    /**
     * Reabre uma solicitação pendente limpando o encaminhamento anterior
     */
    public function reopen(int $id): bool {
        $old = $this->fetchOne("SELECT * FROM segunda_chamada WHERE id = ?", [$id]);
        if (!$old) {
            throw new \Exception("Solicitação não encontrada.");
        }

        $sql = "UPDATE segunda_chamada SET status = ?, observacoes_status = ? WHERE id = ?";
        $updated = $this->execute($sql, ['Pendente', null, $id]) > 0;

        $this->audit('REOPEN_STATUS', 'segunda_chamada', $id, $old, [
            'status' => 'Pendente',
            'observacoes_status' => null
        ]);

        return $updated;
    }
}
