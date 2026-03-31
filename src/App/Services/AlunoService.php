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
             WHERE ta.aluno_id = ? AND t.is_active = 1
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

    public function getMultidisciplinaryHistory(int $alunoId, ?int $turmaId = null): array {
        $db = getDB();
        
        $queries = [];
        $params = [];
        
        // Queries 1-5 and 7 all use $alunoId
        $queries[] = "(-- 1. Comentários de Aula
            SELECT cp.id, CONCAT('cprof_', cp.id) as unique_id, NULL as parent_unique_id,
                'Aula' COLLATE utf8mb4_unicode_ci as categoria, cp.conteudo COLLATE utf8mb4_unicode_ci as texto, cp.created_at as data_registro, 
                u.id as autor_id, u.name COLLATE utf8mb4_unicode_ci as autor_nome, u.photo COLLATE utf8mb4_unicode_ci as autor_foto, u.profile COLLATE utf8mb4_unicode_ci as autor_perfil,
                NULL as atendimento_status, 0 as is_turma
            FROM comentarios_professores cp
            JOIN users u ON cp.professor_id = u.id
            WHERE cp.aluno_id = ? AND cp.conteudo != '')";
        $params[] = $alunoId;
        
        $queries[] = "(-- 2. Encaminhamentos
            SELECT ce.id, CONCAT('enc_', ce.id) as unique_id, NULL as parent_unique_id,
                'Conselho' COLLATE utf8mb4_unicode_ci as categoria, ce.texto COLLATE utf8mb4_unicode_ci as texto, ce.created_at as data_registro,
                u.id as autor_id, u.name COLLATE utf8mb4_unicode_ci as autor_nome, u.photo COLLATE utf8mb4_unicode_ci as autor_foto, u.profile COLLATE utf8mb4_unicode_ci as autor_perfil,
                NULL as atendimento_status, 0 as is_turma
            FROM conselho_encaminhamentos ce
            JOIN users u ON ce.author_id = u.id
            WHERE ce.aluno_id = ? AND ce.texto != '')";
        $params[] = $alunoId;
        
        $queries[] = "(-- 3. Registros Gerais
            SELECT cr.id, CONCAT('creg_', cr.id) as unique_id, NULL as parent_unique_id,
                'Conselho' COLLATE utf8mb4_unicode_ci as categoria, cr.texto COLLATE utf8mb4_unicode_ci as texto, cr.created_at as data_registro,
                u.id as autor_id, u.name COLLATE utf8mb4_unicode_ci as autor_nome, u.photo COLLATE utf8mb4_unicode_ci as autor_foto, u.profile COLLATE utf8mb4_unicode_ci as autor_perfil,
                NULL as atendimento_status, 0 as is_turma
            FROM conselho_registros cr
            JOIN users u ON cr.user_id = u.id
            WHERE cr.aluno_id = ? AND cr.texto != '')";
        $params[] = $alunoId;
        
        $queries[] = "(-- 4. Registros de Turma
            SELECT cr.id, CONCAT('creg_', cr.id) as unique_id, NULL as parent_unique_id,
                'Geral' COLLATE utf8mb4_unicode_ci as categoria, cr.texto COLLATE utf8mb4_unicode_ci as texto, cr.created_at as data_registro,
                u.id as autor_id, u.name COLLATE utf8mb4_unicode_ci as autor_nome, u.photo COLLATE utf8mb4_unicode_ci as autor_foto, u.profile COLLATE utf8mb4_unicode_ci as autor_perfil,
                NULL as atendimento_status, 0 as is_turma
            FROM conselho_registros cr
            JOIN conselhos_classe cc ON cr.conselho_id = cc.id
            JOIN users u ON cr.user_id = u.id
            WHERE cr.aluno_id IS NULL AND cc.turma_id IN (SELECT turma_id FROM turma_alunos WHERE aluno_id = ?) AND cr.texto != '')";
        $params[] = $alunoId;
        
        $queries[] = "(-- 5. Comentários Gerais
            SELECT ccm.id, CONCAT('ccom_', ccm.id) as unique_id, NULL as parent_unique_id,
                'Geral' COLLATE utf8mb4_unicode_ci as categoria, ccm.comentario COLLATE utf8mb4_unicode_ci as texto, ccm.created_at as data_registro,
                u.id as autor_id, u.name COLLATE utf8mb4_unicode_ci as autor_nome, u.photo COLLATE utf8mb4_unicode_ci as autor_foto, u.profile COLLATE utf8mb4_unicode_ci as autor_perfil,
                NULL as atendimento_status, 0 as is_turma
            FROM conselhos_comentarios ccm
            JOIN conselhos_classe cc ON ccm.conselho_id = cc.id
            JOIN users u ON ccm.user_id = u.id
            WHERE cc.turma_id IN (SELECT turma_id FROM turma_alunos WHERE aluno_id = ?) AND ccm.comentario != '')";
        $params[] = $alunoId;
        
        $queries[] = "(-- 6. Atendimentos do aluno
            SELECT ga.id, CONCAT('gatend_', ga.id) as unique_id,
                CASE WHEN ga.encaminhamento_id IS NOT NULL THEN CONCAT('enc_', ga.encaminhamento_id) ELSE NULL END as parent_unique_id,
                'Atendimento' COLLATE utf8mb4_unicode_ci as categoria, COALESCE(ga.descricao_publica, ga.titulo) COLLATE utf8mb4_unicode_ci as texto, ga.created_at as data_registro,
                u.id as autor_id, u.name COLLATE utf8mb4_unicode_ci as autor_nome, u.photo COLLATE utf8mb4_unicode_ci as autor_foto, u.profile COLLATE utf8mb4_unicode_ci as autor_perfil,
                ga.status COLLATE utf8mb4_unicode_ci as atendimento_status, 0 as is_turma
            FROM gestao_atendimentos ga
            JOIN users u ON ga.author_id = u.id
            WHERE ga.aluno_id = ? AND ga.deleted_at IS NULL AND (ga.descricao_publica IS NOT NULL OR ga.titulo IS NOT NULL))";
        $params[] = $alunoId;
        
        if ($turmaId) {
            $queries[] = "(-- 6b. Atendimentos da turma
                SELECT ga.id, CONCAT('gatend_turma_', ga.id) as unique_id, NULL as parent_unique_id,
                    'Atendimento' COLLATE utf8mb4_unicode_ci as categoria, COALESCE(ga.descricao_publica, ga.titulo) COLLATE utf8mb4_unicode_ci as texto, ga.created_at as data_registro,
                    u.id as autor_id, u.name COLLATE utf8mb4_unicode_ci as autor_nome, u.photo COLLATE utf8mb4_unicode_ci as autor_foto, u.profile COLLATE utf8mb4_unicode_ci as autor_perfil,
                    ga.status COLLATE utf8mb4_unicode_ci as atendimento_status, 1 as is_turma
                FROM gestao_atendimentos ga
                JOIN users u ON ga.author_id = u.id
                WHERE ga.turma_id = ? AND ga.aluno_id IS NULL AND ga.deleted_at IS NULL AND ga.encaminhamento_id IS NULL AND (ga.descricao_publica IS NOT NULL OR ga.titulo IS NOT NULL))";
            $params[] = $turmaId;
        }
        
        $queries[] = "(-- 7. Comentários de Atendimento
            SELECT gac.id, CONCAT('gcomm_', gac.id) as unique_id, CONCAT('gatend_', gac.atendimento_id) as parent_unique_id,
                'Atendimento' COLLATE utf8mb4_unicode_ci as categoria, gac.texto COLLATE utf8mb4_unicode_ci as texto, gac.created_at as data_registro,
                u.id as autor_id, u.name COLLATE utf8mb4_unicode_ci as autor_nome, u.photo COLLATE utf8mb4_unicode_ci as autor_foto, u.profile COLLATE utf8mb4_unicode_ci as autor_perfil,
                NULL as atendimento_status, 0 as is_turma
            FROM gestao_atendimento_comentarios gac
            JOIN gestao_atendimentos ga ON gac.atendimento_id = ga.id
            JOIN users u ON gac.usuario_id = u.id
            WHERE ga.aluno_id = ? AND gac.is_private = 0 AND ga.deleted_at IS NULL AND gac.texto != '')";
        $params[] = $alunoId;
        
        $sql = implode("\nUNION ALL\n", $queries) . "\nORDER BY data_registro DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
