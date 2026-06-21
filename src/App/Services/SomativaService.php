<?php
/**
 * Vértice Acadêmico — Serviço de Avaliações Somativas
 */

namespace App\Services;

class SomativaService extends Service {

    // ──────────────────────────────────────────────────────────
    // CRUD Principal
    // ──────────────────────────────────────────────────────────

    public function getAll(int $instId, string $search = ''): array {
        $sql = "SELECT s.*,
                       u.name AS criador_nome,
                       (SELECT COUNT(*) FROM somativa_turmas st WHERE st.somativa_id = s.id) AS total_turmas,
                       (SELECT COUNT(*) FROM somativa_slots_config sc WHERE sc.somativa_id = s.id) AS total_slots
                FROM somativas s
                JOIN users u ON u.id = s.created_by
                WHERE s.institution_id = ?";
        $params = [$instId];

        if ($search) {
            $sql .= ' AND s.nome LIKE ?';
            $params[] = "%{$search}%";
        }

        $sql .= ' ORDER BY s.created_at DESC';
        return $this->fetchAll($sql, $params);
    }

    public function findById(int $id, int $instId): ?array {
        $som = $this->fetchOne(
            'SELECT s.*, ma.descricao AS naapi_ambiente_desc
             FROM somativas s
             LEFT JOIN manutencao_ambientes ma ON ma.id = s.naapi_ambiente_id
             WHERE s.id = ? AND s.institution_id = ?',
            [$id, $instId]
        );
        if (!$som) return null;

        $som['slots']  = $this->getSlotsConfig($id);
        $som['turmas'] = $this->getTurmasComDisciplinas($id);
        return $som;
    }

    public function create(int $instId, int $userId, array $data): array {
        $this->db->prepare(
            'INSERT INTO somativas (institution_id, nome, descricao, data_inicio, data_fim, max_provas_por_dia, status, created_by, evitar_conflito_professor)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $instId,
            trim($data['nome']),
            trim($data['descricao'] ?? '') ?: null,
            $data['data_inicio'],
            $data['data_fim'],
            (int)($data['max_provas_por_dia'] ?? 2),
            'Rascunho',
            $userId,
            (int)($data['evitar_conflito_professor'] ?? 0),
        ]);

        $newId = $this->lastInsertId();
        $this->audit('CREATE', 'somativas', $newId, null, $data);
        return ['success' => true, 'id' => $newId];
    }

    public function update(int $id, array $data): array {
        $allowed = ['nome', 'descricao', 'data_inicio', 'data_fim', 'max_provas_por_dia', 'status', 'segunda_chamada_data', 'naapi_ambiente_id', 'naapi_tempo_extra_min', 'evitar_conflito_professor'];
        $fields  = [];
        $params  = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[]  = $data[$field];
            }
        }

        if (empty($fields)) return ['error' => 'Nenhum campo para atualizar'];

        $old = $this->fetchOne('SELECT * FROM somativas WHERE id = ?', [$id]);
        $params[] = $id;
        $this->execute('UPDATE somativas SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        $this->audit('UPDATE', 'somativas', $id, $old, $data);

        return ['success' => true];
    }

    public function delete(int $id): bool {
        $old = $this->fetchOne('SELECT * FROM somativas WHERE id = ?', [$id]);
        if (!$old) return false;
        $this->execute('DELETE FROM somativas WHERE id = ?', [$id]);
        $this->audit('DELETE', 'somativas', $id, $old, null);
        return true;
    }

    // ──────────────────────────────────────────────────────────
    // Slots de Horário
    // ──────────────────────────────────────────────────────────

    public function getSlotsConfig(int $somativaId): array {
        return $this->fetchAll(
            'SELECT * FROM somativa_slots_config WHERE somativa_id = ? ORDER BY ordem',
            [$somativaId]
        );
    }

    public function saveSlotsConfig(int $somativaId, array $slots): void {
        $this->execute('DELETE FROM somativa_slots_config WHERE somativa_id = ?', [$somativaId]);

        $stmt = $this->db->prepare(
            'INSERT INTO somativa_slots_config (somativa_id, ordem, horario_inicio, horario_fim, label)
             VALUES (?, ?, ?, ?, ?)'
        );

        foreach ($slots as $i => $slot) {
            $stmt->execute([
                $somativaId,
                $i + 1,
                $slot['horario_inicio'],
                $slot['horario_fim'],
                trim($slot['label'] ?? '') ?: null,
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────
    // Turmas Participantes
    // ──────────────────────────────────────────────────────────

    public function getTurmasComDisciplinas(int $somativaId): array {
        $turmas = $this->fetchAll(
            "SELECT st.id AS somativa_turma_id, st.turma_id,
                    t.description AS turma_desc, t.ano,
                    c.name AS course_name, c.id AS course_id
             FROM somativa_turmas st
             JOIN turmas t  ON t.id  = st.turma_id
             JOIN courses c ON c.id  = t.course_id
             WHERE st.somativa_id = ?
             ORDER BY c.name, t.ano, t.description",
            [$somativaId]
        );

        foreach ($turmas as &$turma) {
            $turma['disciplinas'] = $this->fetchAll(
                "SELECT sd.id, sd.disciplina_codigo, sd.professor_aplicador, d.descricao AS disc_nome,
                        (SELECT GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ', ')
                         FROM turma_disciplinas td
                         JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
                         JOIN users u ON u.id = tdp.professor_id
                         WHERE td.turma_id = ? AND td.disciplina_codigo = sd.disciplina_codigo) AS professores
                 FROM somativa_disciplinas sd
                 JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
                 WHERE sd.somativa_turma_id = ?
                 ORDER BY d.descricao",
                [$turma['turma_id'], $turma['somativa_turma_id']]
            );
        }

        return $turmas;
    }

    public function addTurma(int $somativaId, int $turmaId): int {
        $existing = $this->fetchOne(
            'SELECT id FROM somativa_turmas WHERE somativa_id = ? AND turma_id = ?',
            [$somativaId, $turmaId]
        );
        if ($existing) return (int)$existing['id'];

        $this->execute(
            'INSERT INTO somativa_turmas (somativa_id, turma_id) VALUES (?, ?)',
            [$somativaId, $turmaId]
        );
        return $this->lastInsertId();
    }

    public function removeTurma(int $somativaTurmaId): bool {
        return $this->execute(
            'DELETE FROM somativa_turmas WHERE id = ?',
            [$somativaTurmaId]
        ) > 0;
    }

    public function addDisciplina(int $somativaTurmaId, string $disciplinaCodigo): int {
        $existing = $this->fetchOne(
            'SELECT id FROM somativa_disciplinas WHERE somativa_turma_id = ? AND disciplina_codigo = ?',
            [$somativaTurmaId, $disciplinaCodigo]
        );
        if ($existing) return (int)$existing['id'];

        $this->execute(
            'INSERT INTO somativa_disciplinas (somativa_turma_id, disciplina_codigo) VALUES (?, ?)',
            [$somativaTurmaId, $disciplinaCodigo]
        );
        return $this->lastInsertId();
    }

    public function removeDisciplina(int $disciplinaId): bool {
        return $this->execute(
            'DELETE FROM somativa_disciplinas WHERE id = ?',
            [$disciplinaId]
        ) > 0;
    }

    public function toggleProfAplicador(int $sdId): array {
        $old = $this->fetchOne('SELECT * FROM somativa_disciplinas WHERE id = ?', [$sdId]);
        if (!$old) return ['success' => false, 'message' => 'Disciplina não encontrada'];
        
        $newValue = $old['professor_aplicador'] ? 0 : 1;
        $this->execute(
            'UPDATE somativa_disciplinas SET professor_aplicador = ? WHERE id = ?',
            [$newValue, $sdId]
        );
        $this->audit('UPDATE', 'somativa_disciplinas', $sdId, $old, ['professor_aplicador' => $newValue]);
        
        return ['success' => true, 'professor_aplicador' => $newValue];
    }

    // ──────────────────────────────────────────────────────────
    // Grade de Horários
    // ──────────────────────────────────────────────────────────

    public function getGrade(int $somativaId, int $somativaTurmaId): array {
        return $this->fetchAll(
            "SELECT sg.*,
                    sd.disciplina_codigo, d.descricao AS disciplina_nome,
                    u1.name AS aplicador_nome,
                    u2.name AS volante_nome,
                    u3.name AS naapi_aplicador_nome,
                    ma.descricao  AS ambiente_desc, ma.predio_campus AS ambiente_predio,
                    sc.horario_inicio, sc.horario_fim, sc.ordem AS slot_ordem, sc.label AS slot_label
             FROM somativa_grade sg
             LEFT JOIN somativa_disciplinas sd  ON sd.id  = sg.somativa_disciplina_id
             LEFT JOIN disciplinas d            ON d.codigo = sd.disciplina_codigo
             LEFT JOIN users u1                 ON u1.id  = sg.aplicador_id
             LEFT JOIN users u2                 ON u2.id  = sg.volante_id
             LEFT JOIN users u3                 ON u3.id  = sg.naapi_aplicador_id
             LEFT JOIN manutencao_ambientes ma   ON ma.id  = sg.ambiente_id
             JOIN  somativa_slots_config sc      ON sc.id  = sg.slot_config_id
             WHERE sg.somativa_id = ? AND sg.somativa_turma_id = ?
             ORDER BY sg.data_prova, sc.ordem",
            [$somativaId, $somativaTurmaId]
        );
    }

    public function saveGradeSlot(array $data): array {
        if (!empty($data['somativa_disciplina_id'])) {
            $this->execute(
                'DELETE FROM somativa_grade WHERE somativa_turma_id = ? AND somativa_disciplina_id = ?',
                [(int)$data['somativa_turma_id'], (int)$data['somativa_disciplina_id']]
            );
        }

        $existing = $this->fetchOne(
            'SELECT id FROM somativa_grade WHERE somativa_turma_id = ? AND data_prova = ? AND slot_config_id = ?',
            [$data['somativa_turma_id'], $data['data_prova'], $data['slot_config_id']]
        );

        if ($existing) {
            $this->execute(
                'UPDATE somativa_grade SET
                    somativa_disciplina_id = ?, aplicador_id = ?, volante_id = ?,
                    naapi_aplicador_id = ?, ambiente_id = ?, tipo = ?,
                    observacoes = ?, updated_at = NOW()
                 WHERE id = ?',
                [
                    $data['somativa_disciplina_id'] ?: null,
                    $data['aplicador_id'] ?: null,
                    $data['volante_id'] ?: null,
                    $data['naapi_aplicador_id'] ?: null,
                    $data['ambiente_id'] ?: null,
                    $data['tipo'] ?? 'Normal',
                    $data['observacoes'] ?? null,
                    $existing['id'],
                ]
            );
            return ['success' => true, 'id' => $existing['id']];
        }

        $this->db->prepare(
            'INSERT INTO somativa_grade
                (somativa_id, somativa_turma_id, somativa_disciplina_id, data_prova, slot_config_id,
                 aplicador_id, volante_id, naapi_aplicador_id, ambiente_id, tipo, observacoes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $data['somativa_id'],
            $data['somativa_turma_id'],
            $data['somativa_disciplina_id'] ?: null,
            $data['data_prova'],
            $data['slot_config_id'],
            $data['aplicador_id'] ?: null,
            $data['volante_id'] ?: null,
            $data['naapi_aplicador_id'] ?: null,
            $data['ambiente_id'] ?: null,
            $data['tipo'] ?? 'Normal',
            $data['observacoes'] ?? null,
            $data['created_by'],
        ]);

        return ['success' => true, 'id' => $this->lastInsertId()];
    }

    public function clearGradeSlot(int $gradeId): bool {
        return $this->execute('DELETE FROM somativa_grade WHERE id = ?', [$gradeId]) > 0;
    }

    public function clearAllGradeSlotsByTurma(int $somativaTurmaId): int {
        return $this->execute('DELETE FROM somativa_grade WHERE somativa_turma_id = ?', [$somativaTurmaId]);
    }

    // ──────────────────────────────────────────────────────────
    // Sugestões Automáticas
    // ──────────────────────────────────────────────────────────

    public function getSuggestions(int $somativaId): array {
        $suggestions = [];

        // Busca todas as disciplinas/professores da somativa agrupados por disciplina
        $rows = $this->fetchAll(
            "SELECT sd.id AS som_disc_id, sd.disciplina_codigo, d.descricao AS disc_nome,
                    st.turma_id, st.id AS som_turma_id, t.description AS turma_desc,
                    GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ', ') AS professores,
                    GROUP_CONCAT(DISTINCT u.id   ORDER BY u.name SEPARATOR ',')  AS professor_ids
             FROM somativa_disciplinas sd
             JOIN somativa_turmas st ON st.id = sd.somativa_turma_id AND st.somativa_id = ?
             JOIN disciplinas d      ON d.codigo = sd.disciplina_codigo
             JOIN turmas t           ON t.id = st.turma_id
             LEFT JOIN turma_disciplinas td    ON td.turma_id = st.turma_id AND td.disciplina_codigo = sd.disciplina_codigo
             LEFT JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
             LEFT JOIN users u ON u.id = tdp.professor_id
             GROUP BY sd.id, st.id",
            [$somativaId]
        );

        // Agrupa por disciplina para detectar professores em múltiplas turmas
        $byDisc = [];
        foreach ($rows as $row) {
            $byDisc[$row['disciplina_codigo']][] = $row;
        }

        foreach ($byDisc as $cod => $turmaRows) {
            if (count($turmaRows) > 1) {
                $discNome  = $turmaRows[0]['disc_nome'];
                $turmaDesc = implode(', ', array_column($turmaRows, 'turma_desc'));
                $suggestions[] = [
                    'tipo'        => 'mesmo_dia',
                    'disciplina'  => $cod,
                    'disc_nome'   => $discNome,
                    'mensagem'    => "Agendar {$discNome} no mesmo dia em todas as turmas ({$turmaDesc}), o professor pode ser volante nas demais.",
                    'som_disc_ids' => array_column($turmaRows, 'som_disc_id'),
                ];
            }
        }

        // Sugestão de data para segunda chamada (apenas se configurada)
        $som = $this->fetchOne('SELECT data_fim, segunda_chamada_data FROM somativas WHERE id = ?', [$somativaId]);
        if ($som && !empty($som['segunda_chamada_data'])) {
            $suggestions[] = [
                'tipo'      => 'segunda_chamada',
                'mensagem'  => 'Reservar o dia (' . date('d/m/Y', strtotime($som['segunda_chamada_data'])) . ') para Segundas Chamadas.',
                'data'      => $som['segunda_chamada_data'],
            ];
        }

        // Sugestões de disciplinas com baixo rendimento (não alocar no mesmo dia)
        foreach ($this->getLowGradeSuggestions($somativaId) as $s) {
            $suggestions[] = $s;
        }

        return $suggestions;
    }

    // ──────────────────────────────────────────────────────────
    // Restrições de Agendamento
    // ──────────────────────────────────────────────────────────

    public function getRestricoes(int $somativaId): array
    {
        return $this->fetchAll(
            'SELECT * FROM somativa_restricoes WHERE somativa_id = ? ORDER BY tipo DESC, categoria, id',
            [$somativaId]
        );
    }

    public function saveRestricao(int $somativaId, int $userId, array $data): array
    {
        $params = $data['params'];
        if (is_array($params)) $params = json_encode($params, JSON_UNESCAPED_UNICODE);

        if (!empty($data['id'])) {
            $this->execute(
                'UPDATE somativa_restricoes
                 SET tipo = ?, categoria = ?, params = ?, peso = ?, descricao = ?, updated_at = NOW()
                 WHERE id = ? AND somativa_id = ?',
                [
                    $data['tipo'], $data['categoria'], $params,
                    (int)($data['peso'] ?? 5), trim($data['descricao'] ?? '') ?: null,
                    (int)$data['id'], $somativaId,
                ]
            );
            return ['success' => true, 'id' => (int)$data['id']];
        }

        $this->db->prepare(
            'INSERT INTO somativa_restricoes (somativa_id, tipo, categoria, params, peso, descricao, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $somativaId,
            $data['tipo'],
            $data['categoria'],
            $params,
            (int)($data['peso'] ?? 5),
            trim($data['descricao'] ?? '') ?: null,
            $userId,
        ]);

        return ['success' => true, 'id' => $this->lastInsertId()];
    }

    public function deleteRestricao(int $id, int $somativaId): bool
    {
        return $this->execute(
            'DELETE FROM somativa_restricoes WHERE id = ? AND somativa_id = ?',
            [$id, $somativaId]
        ) > 0;
    }

    public function toggleRestricao(int $id, int $somativaId): bool
    {
        return $this->execute(
            'UPDATE somativa_restricoes SET is_active = !is_active WHERE id = ? AND somativa_id = ?',
            [$id, $somativaId]
        ) > 0;
    }

    // ──────────────────────────────────────────────────────────
    // Turma → Ambiente (Sala)
    // ──────────────────────────────────────────────────────────

    public function setTurmaAmbiente(int $turmaId, ?int $ambienteId, int $instId): bool
    {
        return $this->execute(
            "UPDATE turmas t
             JOIN courses c ON c.id = t.course_id
             SET t.ambiente_id = ?
             WHERE t.id = ? AND c.institution_id = ?",
            [$ambienteId ?: null, $turmaId, $instId]
        ) > 0;
    }

    public function getTurmaAmbiente(int $turmaId): ?array
    {
        return $this->fetchOne(
            "SELECT t.ambiente_id, ma.descricao AS ambiente_desc, ma.predio_campus
             FROM turmas t
             LEFT JOIN manutencao_ambientes ma ON ma.id = t.ambiente_id
             WHERE t.id = ?",
            [$turmaId]
        );
    }

    // ──────────────────────────────────────────────────────────
    // Segunda Chamada — data configurável
    // ──────────────────────────────────────────────────────────

    public function updateSegundaChamadaData(int $somativaId, ?string $data): bool
    {
        return $this->execute(
            'UPDATE somativas SET segunda_chamada_data = ? WHERE id = ?',
            [$data ?: null, $somativaId]
        ) > 0;
    }

    // ──────────────────────────────────────────────────────────
    // Slots livres (para o AI Scheduler)
    // ──────────────────────────────────────────────────────────

    /**
     * Retorna todos os slots do período ainda não ocupados,
     * considerando as alocações já existentes e as propostas pelo greedy.
     */
    public function getSlotsLivres(int $somativaId, array $alocacoesGreedy): array
    {
        $som   = $this->fetchOne('SELECT data_inicio, data_fim FROM somativas WHERE id = ?', [$somativaId]);
        $slots = $this->getSlotsConfig($somativaId);

        if (!$som || empty($slots)) return [];

        // Ocupados pelo greedy
        $ocupados = [];
        foreach ($alocacoesGreedy as $a) {
            $ocupados[$a['somativa_turma_id'] . '_' . $a['data_prova'] . '_' . $a['slot_config_id']] = true;
        }

        // Turmas da somativa
        $turmas = $this->fetchAll(
            'SELECT id AS som_turma_id FROM somativa_turmas WHERE somativa_id = ?',
            [$somativaId]
        );

        $livres  = [];
        $cursor  = new \DateTime($som['data_inicio']);
        $end     = new \DateTime($som['data_fim']);

        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            foreach ($slots as $slot) {
                $turmasLivres = [];
                foreach ($turmas as $t) {
                    $key = $t['som_turma_id'] . '_' . $date . '_' . $slot['id'];
                    if (empty($ocupados[$key])) {
                        $turmasLivres[] = (int)$t['som_turma_id'];
                    }
                }
                if ($turmasLivres) {
                    $livres[] = [
                        'date'          => $date,
                        'slot_id'       => (int)$slot['id'],
                        'slot_label'    => $slot['label'] ?? ($slot['horario_inicio'] . '–' . $slot['horario_fim']),
                        'turmas_livres' => $turmasLivres,
                    ];
                }
            }
            $cursor->modify('+1 day');
        }

        return $livres;
    }

    /**
     * Detecta disciplinas com média abaixo da aprovação por turma.
     * Quando 2+ disciplinas têm baixo rendimento na mesma turma, sugere
     * que não sejam agendadas no mesmo dia.
     */
    private function getLowGradeSuggestions(int $somativaId): array {
        $rows = $this->fetchAll(
            "SELECT sd.disciplina_codigo,
                    d.descricao AS disc_nome,
                    st.id       AS som_turma_id,
                    t.description AS turma_desc,
                    ROUND(AVG(en.nota), 1)       AS media_nota,
                    ROUND(AVG(e.media_nota), 2)  AS media_aprovacao
             FROM somativa_disciplinas sd
             JOIN somativa_turmas st ON st.id = sd.somativa_turma_id AND st.somativa_id = ?
             JOIN turmas t           ON t.id  = st.turma_id
             JOIN disciplinas d      ON d.codigo = sd.disciplina_codigo
             JOIN etapas e           ON e.turma_id = st.turma_id AND e.is_active = 1
             JOIN etapa_notas en     ON en.etapa_id = e.id
                                    AND en.disciplina_codigo = sd.disciplina_codigo
                                    AND en.nota IS NOT NULL
             GROUP BY sd.disciplina_codigo, st.id, d.descricao, t.description",
            [$somativaId]
        );

        // Agrupa por turma, filtrando disciplinas abaixo da média de aprovação
        $byTurma = [];
        foreach ($rows as $row) {
            $stId = $row['som_turma_id'];
            if (!isset($byTurma[$stId])) {
                $byTurma[$stId] = ['turma_desc' => $row['turma_desc'], 'baixo' => []];
            }
            $aprovacao = (float)($row['media_aprovacao'] ?: 6.0);
            if ((float)$row['media_nota'] < $aprovacao) {
                $byTurma[$stId]['baixo'][] = [
                    'disc_nome'  => $row['disc_nome'],
                    'media_nota' => $row['media_nota'],
                ];
            }
        }

        $suggestions = [];
        foreach ($byTurma as $info) {
            $baixo = $info['baixo'];
            if (count($baixo) < 2) continue;

            $nomes = array_map(function ($d) {
                return $d['disc_nome'] . ' (' . number_format((float)$d['media_nota'], 1, ',', '') . ')';
            }, $baixo);

            $suggestions[] = [
                'tipo'     => 'evitar_mesmo_dia',
                'mensagem' => 'Turma ' . $info['turma_desc'] . ': '
                            . implode(', ', $nomes)
                            . ' têm baixo rendimento — distribua em dias diferentes.',
            ];
        }

        return $suggestions;
    }

    // ──────────────────────────────────────────────────────────
    // Helpers para a Grade
    // ──────────────────────────────────────────────────────────

    /** Retorna datas entre data_inicio e data_fim (inclusive) */
    public function getDatesInRange(string $inicio, string $fim): array {
        $dates  = [];
        $cursor = new \DateTime($inicio);
        $end    = new \DateTime($fim);

        while ($cursor <= $end) {
            $dates[] = [
                'data'          => $cursor->format('Y-m-d'),
                'label'         => $cursor->format('d/m'),
                'dia_semana'    => (int)$cursor->format('N'), // 1=Seg … 7=Dom
                'dia_semana_br' => ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'][(int)$cursor->format('N')],
                'is_weekend'    => (int)$cursor->format('N') >= 6,
                'is_last_day'   => $cursor->format('Y-m-d') === $fim,
            ];
            $cursor->modify('+1 day');
        }

        return $dates;
    }

    /** Para cada data/slot, quais professores têm aula em qualquer turma da instituição */
    public function getTeacherAvailability(int $instId, array $dates, array $slots): array {
        $availability = [];

        foreach ($dates as $dateInfo) {
            $diaSemana = $dateInfo['dia_semana'];

            $teachers = $this->fetchAll(
                "SELECT u.id, u.name,
                        gta.horario_inicio, gta.horario_fim, gta.disciplina_codigo,
                        d.descricao AS disc_nome, tr.description AS turma_desc,
                        gta.turma_id, tr.course_id
                 FROM gestao_turma_aulas gta
                 JOIN turmas tr ON tr.id = gta.turma_id
                 JOIN courses c ON c.id = tr.course_id
                 JOIN turma_disciplinas td ON td.turma_id = gta.turma_id AND td.disciplina_codigo = gta.disciplina_codigo
                 JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
                 JOIN users u ON u.id = tdp.professor_id
                 JOIN disciplinas d ON d.codigo = gta.disciplina_codigo
                 WHERE c.institution_id = ? AND gta.dia_semana = ? AND gta.is_active = 1",
                [$instId, $diaSemana]
            );

            foreach ($slots as $slot) {
                $key    = $dateInfo['data'] . '_' . $slot['id'];
                $byProf = [];
                foreach ($teachers as $t) {
                    if ($t['horario_inicio'] < $slot['horario_fim'] && $t['horario_fim'] > $slot['horario_inicio']) {
                        $pid = (int)$t['id'];
                        if (!isset($byProf[$pid])) {
                            $byProf[$pid] = ['id' => $pid, 'name' => $t['name'], 'discs' => [], 'turmas' => [], 'turma_ids' => [], 'course_ids' => []];
                        }
                        $byProf[$pid]['discs'][]      = $t['disc_nome'];
                        $byProf[$pid]['turmas'][]     = $t['turma_desc'];
                        $byProf[$pid]['turma_ids'][]  = (int)$t['turma_id'];
                        $byProf[$pid]['course_ids'][] = (int)$t['course_id'];
                    }
                }
                $avail = [];
                foreach ($byProf as $p) {
                    $avail[] = [
                        'id'         => $p['id'],
                        'name'       => $p['name'],
                        'disc'       => implode(', ', array_unique($p['discs'])),
                        'turma'      => implode(', ', array_unique($p['turmas'])),
                        'turma_ids'  => array_values(array_unique($p['turma_ids'])),
                        'course_ids' => array_values(array_unique($p['course_ids'])),
                    ];
                }
                $availability[$key] = $avail;
            }
        }

        return $availability;
    }

    /** Disciplinas ainda não alocadas para uma turma nesta somativa */
    public function getDisciplinasNaoAlocadas(int $somativaId, int $somativaTurmaId): array {
        return $this->fetchAll(
            "SELECT sd.id AS som_disc_id, sd.disciplina_codigo, d.descricao AS disc_nome,
                    GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ', ') AS professores,
                    GROUP_CONCAT(DISTINCT u.id   ORDER BY u.name SEPARATOR ',')  AS professor_ids
             FROM somativa_disciplinas sd
             JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
             LEFT JOIN turma_disciplinas td ON td.disciplina_codigo = sd.disciplina_codigo
                AND td.turma_id = (SELECT turma_id FROM somativa_turmas WHERE id = ?)
             LEFT JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
             LEFT JOIN users u ON u.id = tdp.professor_id
             WHERE sd.somativa_turma_id = ?
               AND sd.id NOT IN (
                   SELECT somativa_disciplina_id FROM somativa_grade
                   WHERE somativa_turma_id = ? AND somativa_disciplina_id IS NOT NULL
                   AND tipo = 'Normal'
               )
             GROUP BY sd.id",
            [$somativaTurmaId, $somativaTurmaId, $somativaTurmaId]
        );
    }

    public function getSuggestedSlotDetails(int $somId, int $stId, ?int $sdId, string $date, int $slotId): array {
        $turma = $this->fetchOne(
            "SELECT st.turma_id, t.ambiente_id, ma.descricao AS ambiente_desc
             FROM somativa_turmas st
             JOIN turmas t ON t.id = st.turma_id
             LEFT JOIN manutencao_ambientes ma ON ma.id = t.ambiente_id
             WHERE st.id = ?",
            [$stId]
        );

        $som = $this->fetchOne('SELECT institution_id FROM somativas WHERE id = ?', [$somId]);
        $instId = $som ? (int)$som['institution_id'] : 0;

        $dow = (int)(new \DateTime($date))->format('N');
        $slot = $this->fetchOne('SELECT horario_inicio, horario_fim FROM somativa_slots_config WHERE id = ?', [$slotId]);

        $availableTeachers = [];
        if ($slot && $instId) {
            $q = "SELECT DISTINCT u.id, u.name
                  FROM gestao_turma_aulas gta
                  JOIN turmas tr ON tr.id = gta.turma_id
                  JOIN courses c ON c.id = tr.course_id
                  JOIN turma_disciplinas td ON td.turma_id = gta.turma_id AND td.disciplina_codigo = gta.disciplina_codigo
                  JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
                  JOIN users u ON u.id = tdp.professor_id
                  WHERE c.institution_id = ?
                    AND gta.dia_semana = ?
                    AND gta.is_active = 1
                    AND gta.horario_inicio < ?
                    AND gta.horario_fim > ?
                    AND u.id NOT IN (
                        SELECT COALESCE(aplicador_id, 0) FROM somativa_grade
                        WHERE data_prova = ? AND slot_config_id = ?
                    )
                  ORDER BY u.name";
            $availableTeachers = $this->fetchAll($q, [$instId, $dow, $slot['horario_fim'], $slot['horario_inicio'], $date, $slotId]);
        }

        $scheduledTeacher = null;
        if ($turma && $slot) {
            $scheduledTeacher = $this->fetchOne(
                "SELECT tdp.professor_id, u.name
                 FROM gestao_turma_aulas gta
                 JOIN turma_disciplinas td ON td.turma_id = gta.turma_id AND td.disciplina_codigo = gta.disciplina_codigo
                 JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
                 JOIN users u ON u.id = tdp.professor_id
                 WHERE gta.turma_id = ?
                   AND gta.dia_semana = ?
                   AND gta.is_active = 1
                   AND gta.horario_inicio < ?
                   AND gta.horario_fim > ?
                   AND tdp.professor_id NOT IN (
                       SELECT COALESCE(aplicador_id, 0) FROM somativa_grade
                       WHERE data_prova = ? AND slot_config_id = ?
                   )
                 LIMIT 1",
                [(int)$turma['turma_id'], $dow, $slot['horario_fim'], $slot['horario_inicio'], $date, $slotId]
            );
        }

        $profIds = [];
        if ($sdId) {
            $profRows = $this->fetchAll(
                "SELECT tdp.professor_id
                 FROM somativa_disciplinas sd
                 JOIN somativa_turmas st ON st.id = sd.somativa_turma_id
                 JOIN turma_disciplinas td ON td.turma_id = st.turma_id AND td.disciplina_codigo = sd.disciplina_codigo
                 JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
                 WHERE sd.id = ?",
                [$sdId]
            );
            foreach ($profRows as $row) {
                if ($row['professor_id']) {
                    $profIds[] = (int)$row['professor_id'];
                }
            }
        }

        $disponiveis = [];
        if (!empty($profIds) && $slot) {
            $placeholders = implode(',', array_fill(0, count($profIds), '?'));
            $q = "SELECT DISTINCT tdp.professor_id, u.name
                  FROM gestao_turma_aulas gta
                  JOIN turma_disciplinas td ON td.turma_id = gta.turma_id AND td.disciplina_codigo = gta.disciplina_codigo
                  JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
                  JOIN users u ON u.id = tdp.professor_id
                  WHERE tdp.professor_id IN ($placeholders)
                    AND gta.dia_semana = ?
                    AND gta.is_active = 1
                    AND gta.horario_inicio < ?
                    AND gta.horario_fim > ?
                    AND tdp.professor_id NOT IN (
                        SELECT COALESCE(aplicador_id, 0) FROM somativa_grade
                        WHERE data_prova = ? AND slot_config_id = ?
                    )";
            $params = array_merge($profIds, [$dow, $slot['horario_fim'], $slot['horario_inicio'], $date, $slotId]);
            $disponiveis = $this->fetchAll($q, $params);
        }

        // Encontra quem já é volante neste slot
        $busyVolantes = [];
        $volanteRows = $this->fetchAll(
            "SELECT DISTINCT volante_id FROM somativa_grade WHERE data_prova = ? AND slot_config_id = ? AND volante_id IS NOT NULL",
            [$date, $slotId]
        );
        foreach ($volanteRows as $r) {
            $busyVolantes[] = (int)$r['volante_id'];
        }

        $aplicador = null;
        $volante   = null;

        if ($sdId && !empty($disponiveis)) {
            // Professor(es) da disciplina estão disponíveis. Queremos tentar colocá-los como volante!
            $volanteCandidate = $disponiveis[0];

            // Tenta colocar o professor regular da turma como aplicador se não estiver ocupado como volante
            if ($scheduledTeacher !== null && !in_array((int)$scheduledTeacher['professor_id'], $busyVolantes)) {
                $aplicador = [
                    'professor_id' => (int)$scheduledTeacher['professor_id'],
                    'name' => $scheduledTeacher['name']
                ];
                $volante = [
                    'professor_id' => (int)$volanteCandidate['professor_id'],
                    'name' => $volanteCandidate['name']
                ];
            } else {
                // Caso contrário, tenta encontrar um aplicador dos professores disponíveis da disciplina que não esteja ocupado como volante
                $availForAplicador = [];
                foreach ($disponiveis as $d) {
                    if (!in_array((int)$d['professor_id'], $busyVolantes)) {
                        $availForAplicador[] = $d;
                    }
                }

                if (!empty($availForAplicador)) {
                    $aplicador = [
                        'professor_id' => (int)$availForAplicador[0]['professor_id'],
                        'name' => $availForAplicador[0]['name']
                    ];
                    // Volante será outro professor da disciplina (se houver)
                    foreach ($disponiveis as $d) {
                        if ((int)$d['professor_id'] !== (int)$aplicador['professor_id']) {
                            $volante = [
                                'professor_id' => (int)$d['professor_id'],
                                'name' => $d['name']
                            ];
                            break;
                        }
                    }
                } else {
                    $volante = [
                        'professor_id' => (int)$volanteCandidate['professor_id'],
                        'name' => $volanteCandidate['name']
                    ];
                    $aplicador = null;
                }
            }
        } else {
            // Nenhum professor da disciplina está disponível no momento (não tem aula regular).
            // O aplicador preferencial é o regular da turma, se houver e não estiver ocupado como volante.
            if ($scheduledTeacher !== null && !in_array((int)$scheduledTeacher['professor_id'], $busyVolantes)) {
                $aplicador = [
                    'professor_id' => (int)$scheduledTeacher['professor_id'],
                    'name' => $scheduledTeacher['name']
                ];

                if (!empty($availableTeachers)) {
                    foreach ($availableTeachers as $av) {
                        if ((int)$av['id'] !== (int)$aplicador['professor_id']) {
                            $volante = $av;
                            break;
                        }
                    }
                }
                if ($volante === null && !empty($profIds)) {
                    $placeholders = implode(',', array_fill(0, count($profIds), '?'));
                    $volanteRow = $this->fetchOne(
                        "SELECT id, name FROM users
                         WHERE id IN ($placeholders)
                           AND id NOT IN (
                               SELECT COALESCE(aplicador_id, 0) FROM somativa_grade
                               WHERE data_prova = ? AND slot_config_id = ?
                           )
                         LIMIT 1",
                        array_merge($profIds, [$date, $slotId])
                    );
                    if ($volanteRow && (int)$volanteRow['id'] !== (int)$aplicador['professor_id']) {
                        $volante = [
                            'professor_id' => (int)$volanteRow['id'],
                            'name' => $volanteRow['name']
                        ];
                    }
                }
            } else {
                // Seleciona dos professores disponíveis gerais
                $availForAplicador = [];
                foreach ($availableTeachers as $av) {
                    if (!in_array((int)$av['id'], $busyVolantes)) {
                        $availForAplicador[] = $av;
                    }
                }

                if (!empty($availForAplicador)) {
                    $aplicador = [
                        'professor_id' => (int)$availForAplicador[0]['id'],
                        'name' => $availForAplicador[0]['name']
                    ];
                    if (count($availableTeachers) > 1) {
                        foreach ($availableTeachers as $av) {
                            if ((int)$av['id'] !== (int)$aplicador['professor_id']) {
                                $volante = [
                                    'professor_id' => (int)$av['id'],
                                    'name' => $av['name']
                                ];
                                break;
                            }
                        }
                    }
                } else {
                    if (!empty($profIds)) {
                        $placeholders = implode(',', array_fill(0, count($profIds), '?'));
                        $validProfs = $this->fetchAll(
                            "SELECT id, name FROM users
                             WHERE id IN ($placeholders)
                               AND id NOT IN (
                                   SELECT COALESCE(aplicador_id, 0) FROM somativa_grade
                                   WHERE data_prova = ? AND slot_config_id = ?
                               )",
                            array_merge($profIds, [$date, $slotId])
                        );

                        $validForAplicador = [];
                        foreach ($validProfs as $vp) {
                            if (!in_array((int)$vp['id'], $busyVolantes)) {
                                $validForAplicador[] = $vp;
                            }
                        }

                        if (!empty($validForAplicador)) {
                            $aplicador = [
                                'professor_id' => (int)$validForAplicador[0]['id'],
                                'name' => $validForAplicador[0]['name']
                            ];
                            foreach ($validProfs as $vp) {
                                if ((int)$vp['id'] !== (int)$aplicador['professor_id']) {
                                    $volante = [
                                        'professor_id' => (int)$vp['id'],
                                        'name' => $vp['name']
                                    ];
                                    break;
                                }
                            }
                        } else if (!empty($validProfs)) {
                            $volante = [
                                'professor_id' => (int)$validProfs[0]['id'],
                                'name' => $validProfs[0]['name']
                            ];
                            $aplicador = null;
                        }
                    }
                }
            }
        }

        if ($aplicador === null && $instId) {
            $fallbackProfs = $this->fetchAll(
                "SELECT u.id, u.name FROM users u
                 JOIN user_institutions ui ON ui.user_id = u.id AND ui.institution_id = ?
                 WHERE u.is_active = 1 AND (u.is_teacher = 1 OR u.profile = 'Professor')
                   AND u.id NOT IN (
                       SELECT COALESCE(aplicador_id, 0) FROM somativa_grade
                       WHERE data_prova = ? AND slot_config_id = ?
                   )
                 ORDER BY u.name LIMIT 2",
                [$instId, $date, $slotId]
            );

            $fallbackForAplicador = [];
            foreach ($fallbackProfs as $fp) {
                if (!in_array((int)$fp['id'], $busyVolantes)) {
                    $fallbackForAplicador[] = $fp;
                }
            }

            if (!empty($fallbackForAplicador)) {
                $aplicador = [
                    'professor_id' => (int)$fallbackForAplicador[0]['id'],
                    'name' => $fallbackForAplicador[0]['name']
                ];
                foreach ($fallbackProfs as $fp) {
                    if ((int)$fp['id'] !== (int)$aplicador['professor_id']) {
                        $volante = [
                            'professor_id' => (int)$fp['id'],
                            'name' => $fp['name']
                        ];
                        break;
                    }
                }
            } else if (!empty($fallbackProfs)) {
                $volante = [
                    'professor_id' => (int)$fallbackProfs[0]['id'],
                    'name' => $fallbackProfs[0]['name']
                ];
                $aplicador = null;
            }
        }

        $aplicadorIdResolved = $aplicador ? (int)($aplicador['professor_id'] ?? $aplicador['id'] ?? null) : null;

        // Sugere aplicador NAAPI: professor disponível no horário, diferente do aplicador principal
        $naapiAplicadorId   = null;
        $naapiAplicadorNome = null;

        $somNaapi = $this->fetchOne(
            'SELECT naapi_ambiente_id FROM somativas WHERE id = ?',
            [$somId]
        );

        if (!empty($somNaapi['naapi_ambiente_id']) && $slot) {
            $q = "SELECT DISTINCT tdp.professor_id AS id, u.name
                  FROM gestao_turma_aulas gta
                  JOIN turmas tr ON tr.id = gta.turma_id
                  JOIN courses c ON c.id = tr.course_id
                  JOIN turma_disciplinas td ON td.turma_id = gta.turma_id AND td.disciplina_codigo = gta.disciplina_codigo
                  JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
                  JOIN users u ON u.id = tdp.professor_id
                  WHERE c.institution_id = ?
                    AND gta.dia_semana = ?
                    AND gta.is_active = 1
                    AND gta.horario_inicio < ?
                    AND gta.horario_fim > ?
                    AND tdp.professor_id != COALESCE(?, 0)
                    AND tdp.professor_id NOT IN (
                        SELECT COALESCE(naapi_aplicador_id, 0) FROM somativa_grade
                        WHERE data_prova = ? AND slot_config_id = ? AND naapi_aplicador_id IS NOT NULL
                    )
                  ORDER BY u.name LIMIT 1";
            $naapiFit = $this->fetchOne($q, [
                $instId, $dow,
                $slot['horario_fim'], $slot['horario_inicio'],
                $aplicadorIdResolved,
                $date, $slotId,
            ]);
            if ($naapiFit) {
                $naapiAplicadorId   = (int)$naapiFit['id'];
                $naapiAplicadorNome = $naapiFit['name'];
            }
        }

        return [
            'ambiente_id'          => $turma ? $turma['ambiente_id'] : null,
            'ambiente_desc'        => $turma ? $turma['ambiente_desc'] : null,
            'aplicador_id'         => $aplicadorIdResolved,
            'aplicador_nome'       => $aplicador ? ($aplicador['name'] ?? null) : null,
            'volante_id'           => $volante ? (int)($volante['professor_id'] ?? $volante['id'] ?? null) : null,
            'volante_nome'         => $volante ? ($volante['name'] ?? null) : null,
            'naapi_aplicador_id'   => $naapiAplicadorId,
            'naapi_aplicador_nome' => $naapiAplicadorNome,
        ];
    }

    public function validateGrade(int $somativaId): array {
        $restricoes = $this->fetchAll(
            'SELECT * FROM somativa_restricoes WHERE somativa_id = ? AND is_active = 1',
            [$somativaId]
        );
        
        $idx = [
            'bloquear_data'               => [],
            'evitar_mesmo_dia'            => [],
            'mesmo_dia_turmas'            => [],
            'mesmo_horario_turmas'        => [],
            'mesmo_dia_horario_diferente' => [],
            'mesmo_dia_horario_grupo'     => [],
            'professor_indisponivel'      => [],
        ];
        foreach ($restricoes as $r) {
            $cat    = $r['categoria'];
            $params = json_decode($r['params'], true) ?? [];
            if (array_key_exists($cat, $idx)) {
                $idx[$cat][] = array_merge(
                    ['tipo' => $r['tipo'], 'peso' => (int)($r['peso'] ?? 5), 'descricao' => $r['descricao']],
                    $params
                );
            }
        }

        $allocs = $this->fetchAll(
            "SELECT sg.*, sd.disciplina_codigo, d.descricao AS disc_nome, t.description AS turma_desc,
                    GROUP_CONCAT(DISTINCT tdp.professor_id SEPARATOR ',') AS professor_ids
             FROM somativa_grade sg
             JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
             JOIN disciplinas d           ON d.codigo = sd.disciplina_codigo
             JOIN somativa_turmas st      ON st.id = sg.somativa_turma_id
             JOIN turmas t                ON t.id = st.turma_id
             LEFT JOIN turma_disciplinas td ON td.turma_id = st.turma_id AND td.disciplina_codigo = sd.disciplina_codigo
             LEFT JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
             WHERE sg.somativa_id = ?
             GROUP BY sg.id",
            [$somativaId]
        );

        $violations = [];

        foreach ($allocs as $a) {
            $vList = [];
            $gradeId = (int)$a['id'];
            $cod = $a['disciplina_codigo'];

            // 1. bloquear_data
            foreach ($idx['bloquear_data'] as $r) {
                if (($r['data'] ?? '') === $a['data_prova']) {
                    $vList[] = "Data bloqueada: " . ($r['motivo'] ?? 'restrição cadastrada');
                }
            }

            // 2. professor_indisponivel
            $profIds = array_filter(explode(',', $a['professor_ids'] ?? ''));
            foreach ($profIds as $pid) {
                foreach ($idx['professor_indisponivel'] as $r) {
                    if ((int)($r['professor_id'] ?? 0) === (int)$pid) {
                        if (in_array($a['data_prova'], $r['datas'] ?? [])) {
                            $vList[] = "Professor indisponível nesta data";
                        }
                    }
                }
            }

            // 3. mesmo_dia_turmas
            foreach ($idx['mesmo_dia_turmas'] as $r) {
                if (($r['disciplina_codigo'] ?? '') === $cod) {
                    foreach ($allocs as $other) {
                        if ($other['disciplina_codigo'] === $cod && (int)$other['id'] !== $gradeId) {
                            if ($other['data_prova'] !== $a['data_prova']) {
                                $vList[] = "Mesmo Dia: deve ocorrer no mesmo dia que na turma " . $other['turma_desc'];
                                break;
                            }
                        }
                    }
                }
            }

            // 4. mesmo_horario_turmas
            foreach ($idx['mesmo_horario_turmas'] as $r) {
                $match = false;
                $scope = $r['scope'] ?? 'disciplina';
                if ($scope === 'disciplina' && ($r['disciplina_codigo'] ?? '') === $cod) {
                    $match = true;
                } else if ($scope === 'turma' && (int)($r['turma_id'] ?? 0) === (int)$a['somativa_turma_id']) {
                    $match = true;
                }

                if ($match) {
                    foreach ($allocs as $other) {
                        if ($other['disciplina_codigo'] === $cod && (int)$other['id'] !== $gradeId) {
                            if ($other['data_prova'] !== $a['data_prova'] || (int)$other['slot_config_id'] !== (int)$a['slot_config_id']) {
                                $vList[] = "Mesmo Horário: deve ocorrer no mesmo horário que na turma " . $other['turma_desc'];
                                break;
                            }
                        }
                    }
                }
            }

            // 5. evitar_mesmo_dia
            foreach ($idx['evitar_mesmo_dia'] as $r) {
                $discs = $r['disciplinas'] ?? [];
                if (in_array($cod, $discs)) {
                    $parceiros = array_diff($discs, [$cod]);
                    foreach ($allocs as $other) {
                        if ((int)$other['somativa_turma_id'] === (int)$a['somativa_turma_id'] && (int)$other['id'] !== $gradeId) {
                            if (in_array($other['disciplina_codigo'], $parceiros) && $other['data_prova'] === $a['data_prova']) {
                                $vList[] = "Evitar no mesmo dia: conflito com " . $other['disc_nome'];
                            }
                        }
                    }
                }
            }

            // 7. mesmo_dia_horario_diferente
            foreach ($idx['mesmo_dia_horario_diferente'] as $r) {
                $codA  = $r['disciplina_codigo_a'] ?? '';
                $codB  = $r['disciplina_codigo_b'] ?? '';
                $scope = $r['scope'] ?? 'mesma_turma';
                $regra = $r['regra'] ?? 'deve';

                if ($cod === $codA || $cod === $codB) {
                    $targetCod = ($cod === $codA) ? $codB : $codA;
                    foreach ($allocs as $other) {
                        if ((int)$other['id'] === $gradeId) continue;
                        if ($other['disciplina_codigo'] !== $targetCod) continue;

                        $scopeMatch = false;
                        if ($scope === 'mesma_turma') {
                            if ((int)$other['somativa_turma_id'] === (int)$a['somativa_turma_id']) {
                                $scopeMatch = true;
                            }
                        } else {
                            if ((int)$other['somativa_turma_id'] !== (int)$a['somativa_turma_id']) {
                                $scopeMatch = true;
                            }
                        }

                        if ($scopeMatch) {
                            if ($regra === 'deve') {
                                if ($other['data_prova'] !== $a['data_prova']) {
                                    $vList[] = "Relação de dia/horário: deve acontecer no mesmo dia que " . $other['disc_nome'] . " (turma " . $other['turma_desc'] . ")";
                                } elseif ((int)$other['slot_config_id'] === (int)$a['slot_config_id']) {
                                    $vList[] = "Relação de dia/horário: deve acontecer em horário diferente de " . $other['disc_nome'] . " (turma " . $other['turma_desc'] . ")";
                                }
                            } else { // nao_deve
                                if ($other['data_prova'] === $a['data_prova']) {
                                    $vList[] = "Relação de dia/horário: não deve acontecer no mesmo dia que " . $other['disc_nome'] . " (turma " . $other['turma_desc'] . ")";
                                }
                            }
                        }
                    }
                }
            }

            // 8. mesmo_dia_horario_grupo
            foreach ($idx['mesmo_dia_horario_grupo'] as $r) {
                $groupSdIds = array_map('intval', array_column($r['pares'] ?? [], 'somativa_disciplina_id'));
                if (!in_array((int)$a['somativa_disciplina_id'], $groupSdIds)) continue;
                foreach ($allocs as $other) {
                    if ((int)$other['id'] === $gradeId) continue;
                    if (!in_array((int)$other['somativa_disciplina_id'], $groupSdIds)) continue;
                    if ($other['data_prova'] !== $a['data_prova'] || (int)$other['slot_config_id'] !== (int)$a['slot_config_id']) {
                        $vList[] = "Grupo Mesmo Slot: não coincide com " . $other['disc_nome'] . " (" . $other['turma_desc'] . ")";
                        break;
                    }
                }
            }

            // 6. Proctor conflicts
            if ($a['aplicador_id'] || $a['volante_id']) {
                foreach ($allocs as $other) {
                    if ((int)$other['id'] === $gradeId) continue;
                    if ($other['data_prova'] !== $a['data_prova'] || (int)$other['slot_config_id'] !== (int)$a['slot_config_id']) continue;

                    if ($a['aplicador_id']) {
                        if ((int)$other['aplicador_id'] === (int)$a['aplicador_id']) {
                            $vList[] = "Conflito de Aplicador: já está aplicando na turma " . $other['turma_desc'];
                        }
                        if ((int)$other['volante_id'] === (int)$a['aplicador_id']) {
                            $vList[] = "Conflito de Aplicador: já está como volante na turma " . $other['turma_desc'];
                        }
                    }
                    if ($a['volante_id']) {
                        if ((int)$other['aplicador_id'] === (int)$a['volante_id']) {
                            $vList[] = "Conflito de Volante: já está aplicando na turma " . $other['turma_desc'];
                        }
                    }
                }
            }

            if (!empty($vList)) {
                $violations[$gradeId] = $vList;
            }
        }

        return $violations;
    }

    // ════════════════════════════════════════════════════
    // MÉTODOS DE ANÁLISE DE PROFESSORES (SALA DE EXAME/PROCTORS)
    // ════════════════════════════════════════════════════

    /**
     * Retorna a carga de trabalho de todos os professores de forma agregada por slots de tempo únicos.
     */
    private function getTeachersUniqueWorkloads(int $somativaId, int $institutionId): array {
        // Busca todos os professores ativos da instituição
        $teachers = $this->fetchAll(
            "SELECT u.id, u.name
             FROM users u
             JOIN user_institutions ui ON ui.user_id = u.id AND ui.institution_id = ?
             WHERE u.is_active = 1 AND (u.is_teacher = 1 OR u.profile = 'Professor')
             ORDER BY u.name",
            [$institutionId]
        );

        $workloads = [];
        foreach ($teachers as $t) {
            $workloads[(int)$t['id']] = [
                'professor_id'   => (int)$t['id'],
                'professor_name' => $t['name'],
                'aplicador'      => 0,
                'volante'        => 0,
                'naapi'          => 0,
                'total'          => 0,
                'slots_aplicador' => [],
                'slots_volante'   => [],
                'slots_naapi'     => [],
                'slots_total'     => []
            ];
        }

        // Busca todas as alocações da somativa
        $allocs = $this->fetchAll(
            "SELECT sg.aplicador_id, sg.volante_id, sg.naapi_aplicador_id, sg.data_prova, sg.slot_config_id
             FROM somativa_grade sg
             WHERE sg.somativa_id = ?",
            [$somativaId]
        );

        foreach ($allocs as $a) {
            $slotKey = $a['data_prova'] . '_' . $a['slot_config_id'];
            $ap = (int)$a['aplicador_id'];
            $vo = (int)$a['volante_id'];
            $na = (int)$a['naapi_aplicador_id'];

            if ($ap && isset($workloads[$ap])) {
                $workloads[$ap]['slots_aplicador'][$slotKey] = true;
                $workloads[$ap]['slots_total'][$slotKey] = true;
            }
            if ($vo && isset($workloads[$vo])) {
                $workloads[$vo]['slots_volante'][$slotKey] = true;
                $workloads[$vo]['slots_total'][$slotKey] = true;
            }
            if ($na && isset($workloads[$na])) {
                $workloads[$na]['slots_naapi'][$slotKey] = true;
                $workloads[$na]['slots_total'][$slotKey] = true;
            }
        }

        // Calcula os totais baseados nos conjuntos de slots únicos
        foreach ($workloads as &$w) {
            $w['aplicador'] = count($w['slots_aplicador']);
            $w['volante']   = count($w['slots_volante']);
            $w['naapi']     = count($w['slots_naapi']);
            $w['total']     = count($w['slots_total']);

            unset($w['slots_aplicador']);
            unset($w['slots_volante']);
            unset($w['slots_naapi']);
            unset($w['slots_total']);
        }

        return $workloads;
    }

    /**
     * Retorna professores alocados fora de seu horário de trabalho convencional.
     */
    public function getTeacherAnalysisConventionalConflict(int $somativaId, int $institutionId): array {
        // Busca todas as alocações da somativa que têm professores definidos
        $allocs = $this->fetchAll(
            "SELECT sg.id AS grade_id, sg.data_prova, sg.slot_config_id,
                    sc.label AS slot_label, sc.horario_inicio, sc.horario_fim,
                    t.description AS turma_desc, d.descricao AS disc_nome,
                    p.id AS professor_id, p.name AS professor_nome,
                    'Aplicador' AS papel
             FROM somativa_grade sg
             JOIN users p ON p.id = sg.aplicador_id
             JOIN somativa_slots_config sc ON sc.id = sg.slot_config_id
             JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
             JOIN turmas t ON t.id = st.turma_id
             LEFT JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
             LEFT JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
             WHERE sg.somativa_id = ?
             
             UNION ALL
             
             SELECT sg.id AS grade_id, sg.data_prova, sg.slot_config_id,
                    sc.label AS slot_label, sc.horario_inicio, sc.horario_fim,
                    t.description AS turma_desc, d.descricao AS disc_nome,
                    p.id AS professor_id, p.name AS professor_nome,
                    'Volante' AS papel
             FROM somativa_grade sg
             JOIN users p ON p.id = sg.volante_id
             JOIN somativa_slots_config sc ON sc.id = sg.slot_config_id
             JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
             JOIN turmas t ON t.id = st.turma_id
             LEFT JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
             LEFT JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
             WHERE sg.somativa_id = ?
             
             UNION ALL
             
             SELECT sg.id AS grade_id, sg.data_prova, sg.slot_config_id,
                    sc.label AS slot_label, sc.horario_inicio, sc.horario_fim,
                    t.description AS turma_desc, d.descricao AS disc_nome,
                    p.id AS professor_id, p.name AS professor_nome,
                    'Aplicador NAAPI' AS papel
             FROM somativa_grade sg
             JOIN users p ON p.id = sg.naapi_aplicador_id
             JOIN somativa_slots_config sc ON sc.id = sg.slot_config_id
             JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
             JOIN turmas t ON t.id = st.turma_id
             LEFT JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
             LEFT JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
             WHERE sg.somativa_id = ?",
            [$somativaId, $somativaId, $somativaId]
        );

        // Busca todas as aulas normais ativas de professores desta instituição
        $regularClasses = $this->fetchAll(
            "SELECT tdp.professor_id, gta.dia_semana, gta.horario_inicio, gta.horario_fim
             FROM gestao_turma_aulas gta
             JOIN turmas tr ON tr.id = gta.turma_id
             JOIN courses c ON c.id = tr.course_id
             JOIN turma_disciplinas td ON td.turma_id = gta.turma_id AND td.disciplina_codigo = gta.disciplina_codigo
             JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
             WHERE c.institution_id = ? AND gta.is_active = 1",
            [$institutionId]
        );

        // Agrupa as aulas normais por professor e dia da semana
        $groupedClasses = [];
        foreach ($regularClasses as $rc) {
            $pid = (int)$rc['professor_id'];
            $ds  = (int)$rc['dia_semana'];
            $groupedClasses[$pid][$ds][] = [
                'horario_inicio' => $rc['horario_inicio'],
                'horario_fim'    => $rc['horario_fim']
            ];
        }

        $groupedConflicts = [];
        foreach ($allocs as $aloc) {
            $pid = (int)$aloc['professor_id'];
            $date = $aloc['data_prova'];
            $slotId = (int)$aloc['slot_config_id'];
            $dow = (int)date('N', strtotime($date));
            $start = $aloc['horario_inicio'];
            $end = $aloc['horario_fim'];

            $hasRegularClass = false;
            if (isset($groupedClasses[$pid][$dow])) {
                foreach ($groupedClasses[$pid][$dow] as $class) {
                    if ($class['horario_inicio'] < $end && $class['horario_fim'] > $start) {
                        $hasRegularClass = true;
                        break;
                    }
                }
            }

            if (!$hasRegularClass) {
                $key = $pid . '_' . $date . '_' . $slotId;
                if (!isset($groupedConflicts[$key])) {
                    $groupedConflicts[$key] = [
                        'grade_id'       => (int)$aloc['grade_id'],
                        'data_prova'     => $date,
                        'dia_semana_br'  => ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'][$dow],
                        'slot_config_id' => $slotId,
                        'slot_label'     => $aloc['slot_label'] ?: substr($start, 0, 5) . '–' . substr($end, 0, 5),
                        'professor_id'   => $pid,
                        'professor_name' => $aloc['professor_nome'],
                        'papeis'         => [],
                        'turmas_disciplinas' => []
                    ];
                }
                $groupedConflicts[$key]['papeis'][] = $aloc['papel'];
                $groupedConflicts[$key]['turmas_disciplinas'][] = [
                    'turma_desc' => $aloc['turma_desc'],
                    'disc_name'  => $aloc['disc_nome'] ?: 'Segunda Chamada'
                ];
            }
        }

        $conflicts = [];
        foreach ($groupedConflicts as $c) {
            $uniquePapeis = array_unique($c['papeis']);
            
            // Deduplicate same class / subject combinations
            $uniqueTD = [];
            foreach ($c['turmas_disciplinas'] as $td) {
                $tdKey = $td['turma_desc'] . ' - ' . $td['disc_name'];
                $uniqueTD[$tdKey] = $td;
            }

            $conflicts[] = [
                'grade_id'           => $c['grade_id'],
                'data_prova'         => $c['data_prova'],
                'dia_semana_br'      => $c['dia_semana_br'],
                'slot_config_id'     => $c['slot_config_id'],
                'slot_label'         => $c['slot_label'],
                'professor_id'       => $c['professor_id'],
                'professor_name'     => $c['professor_name'],
                'papel'              => implode(', ', $uniquePapeis),
                'turmas_disciplinas' => array_values($uniqueTD)
            ];
        }

        // Ordena por data, slot, nome do professor
        usort($conflicts, function ($a, $b) {
            if ($a['data_prova'] !== $b['data_prova']) {
                return strcmp($a['data_prova'], $b['data_prova']);
            }
            if (($a['slot_config_id'] ?? 0) !== ($b['slot_config_id'] ?? 0)) {
                return ($a['slot_config_id'] ?? 0) - ($b['slot_config_id'] ?? 0);
            }
            return strcmp($a['professor_name'], $b['professor_name']);
        });

        return $conflicts;
    }

    /**
     * Retorna a agenda do período de somativas por professor.
     * Agrupa e concatena atividades caso o professor esteja exercendo múltiplos papéis (ou volante em várias salas) no mesmo horário.
     */
    public function getTeacherSchedules(int $somativaId, int $institutionId): array {
        $allocs = $this->fetchAll(
            "SELECT sg.data_prova, sg.slot_config_id,
                    sc.horario_inicio, sc.horario_fim, sc.label AS slot_label,
                    t.description AS turma_desc, d.descricao AS disc_nome,
                    p.id AS professor_id, p.name AS professor_nome,
                    'Aplicador' AS papel, ma.descricao AS ambiente_desc
             FROM somativa_grade sg
             JOIN users p ON p.id = sg.aplicador_id
             JOIN somativa_slots_config sc ON sc.id = sg.slot_config_id
             JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
             JOIN turmas t ON t.id = st.turma_id
             LEFT JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
             LEFT JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
             LEFT JOIN manutencao_ambientes ma ON ma.id = sg.ambiente_id
             WHERE sg.somativa_id = ?
             
             UNION ALL
             
             SELECT sg.data_prova, sg.slot_config_id,
                    sc.horario_inicio, sc.horario_fim, sc.label AS slot_label,
                    t.description AS turma_desc, d.descricao AS disc_nome,
                    p.id AS professor_id, p.name AS professor_nome,
                    'Volante' AS papel, ma.descricao AS ambiente_desc
             FROM somativa_grade sg
             JOIN users p ON p.id = sg.volante_id
             JOIN somativa_slots_config sc ON sc.id = sg.slot_config_id
             JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
             JOIN turmas t ON t.id = st.turma_id
             LEFT JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
             LEFT JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
             LEFT JOIN manutencao_ambientes ma ON ma.id = sg.ambiente_id
             WHERE sg.somativa_id = ?
             
             UNION ALL
             
             SELECT sg.data_prova, sg.slot_config_id,
                    sc.horario_inicio, sc.horario_fim, sc.label AS slot_label,
                    t.description AS turma_desc, d.descricao AS disc_nome,
                    p.id AS professor_id, p.name AS professor_nome,
                    'Aplicador NAAPI' AS papel, ma2.descricao AS ambiente_desc
             FROM somativa_grade sg
             JOIN users p ON p.id = sg.naapi_aplicador_id
             JOIN somativa_slots_config sc ON sc.id = sg.slot_config_id
             JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
             JOIN turmas t ON t.id = st.turma_id
             JOIN somativas s ON s.id = sg.somativa_id
             LEFT JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
             LEFT JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
             LEFT JOIN manutencao_ambientes ma2 ON ma2.id = s.naapi_ambiente_id
             WHERE sg.somativa_id = ?",
            [$somativaId, $somativaId, $somativaId]
        );

        $agendasRaw = [];
        foreach ($allocs as $aloc) {
            $pid = (int)$aloc['professor_id'];
            $pname = $aloc['professor_nome'];
            $date = $aloc['data_prova'];
            $slotId = (int)$aloc['slot_config_id'];
            $key = $date . '_' . $slotId;

            if (!isset($agendasRaw[$pid])) {
                $agendasRaw[$pid] = [
                    'professor_id'   => $pid,
                    'professor_name' => $pname,
                    'agenda_by_slot' => []
                ];
            }

            if (!isset($agendasRaw[$pid]['agenda_by_slot'][$key])) {
                $dow = (int)date('N', strtotime($date));
                $agendasRaw[$pid]['agenda_by_slot'][$key] = [
                    'data_prova'     => $date,
                    'dia_semana_br'  => ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'][$dow],
                    'horario_inicio' => $aloc['horario_inicio'],
                    'horario_fim'    => $aloc['horario_fim'],
                    'slot_label'     => $aloc['slot_label'] ?: substr($aloc['horario_inicio'], 0, 5) . '–' . substr($aloc['horario_fim'], 0, 5),
                    'turmas'         => [],
                    'disciplinas'    => [],
                    'papeis'         => [],
                    'ambientes'      => []
                ];
            }

            $agendasRaw[$pid]['agenda_by_slot'][$key]['turmas'][] = $aloc['turma_desc'];
            $agendasRaw[$pid]['agenda_by_slot'][$key]['disciplinas'][] = $aloc['disc_nome'] ?: 'Segunda Chamada';
            $agendasRaw[$pid]['agenda_by_slot'][$key]['papeis'][] = $aloc['papel'];
            if ($aloc['ambiente_desc']) {
                $agendasRaw[$pid]['agenda_by_slot'][$key]['ambientes'][] = $aloc['ambiente_desc'];
            }
        }

        $agendas = [];
        foreach ($agendasRaw as $pid => $pData) {
            $agenda = [];
            foreach ($pData['agenda_by_slot'] as $slotKey => $slotData) {
                $uniqueTurmas = array_unique($slotData['turmas']);
                $uniqueDiscs  = array_unique($slotData['disciplinas']);
                $uniquePapeis = array_unique($slotData['papeis']);
                $uniqueAmbs   = array_unique($slotData['ambientes']);

                $papelMerged = implode(', ', $uniquePapeis);
                $turmaMerged = implode(', ', $uniqueTurmas);
                $discMerged  = implode(', ', $uniqueDiscs);
                $ambMerged   = !empty($uniqueAmbs) ? implode(', ', $uniqueAmbs) : '—';

                $agenda[] = [
                    'data_prova'     => $slotData['data_prova'],
                    'dia_semana_br'  => $slotData['dia_semana_br'],
                    'horario_inicio' => $slotData['horario_inicio'],
                    'horario_fim'    => $slotData['horario_fim'],
                    'slot_label'     => $slotData['slot_label'],
                    'turma_desc'     => $turmaMerged,
                    'disc_nome'      => $discMerged,
                    'papel'          => $papelMerged,
                    'ambiente_desc'  => $ambMerged
                ];
            }

            usort($agenda, function ($a, $b) {
                if ($a['data_prova'] !== $b['data_prova']) {
                    return strcmp($a['data_prova'], $b['data_prova']);
                }
                return strcmp($a['horario_inicio'], $b['horario_inicio']);
            });

            $agendas[] = [
                'professor_id'   => $pid,
                'professor_name' => $pData['professor_name'],
                'agenda'         => $agenda
            ];
        }

        uasort($agendas, function ($a, $b) {
            return strcmp($a['professor_name'], $b['professor_name']);
        });

        return array_values($agendas);
    }

    /**
     * Retorna a carga de ocupação de trabalho por professor.
     */
    public function getTeacherWorkload(int $somativaId, int $institutionId): array {
        $workloads = $this->getTeachersUniqueWorkloads($somativaId, $institutionId);

        // Ordena por carga de trabalho total decrescente, depois nome
        usort($workloads, function ($a, $b) {
            if ($a['total'] !== $b['total']) {
                return $b['total'] - $a['total'];
            }
            return strcmp($a['professor_name'], $b['professor_name']);
        });

        return $workloads;
    }

    /**
     * Gera sugestões de equalização de carga de professores.
     */
    public function getEqualizationSuggestions(int $somativaId, int $institutionId): array {
        $allocs = $this->fetchAll(
            "SELECT sg.id AS grade_id, sg.data_prova, sg.slot_config_id, sg.somativa_disciplina_id, sg.somativa_turma_id,
                    sg.aplicador_id, sg.volante_id, sg.naapi_aplicador_id,
                    sc.label AS slot_label, sc.horario_inicio, sc.horario_fim,
                    t.description AS turma_desc, d.descricao AS disc_nome, sd.disciplina_codigo
             FROM somativa_grade sg
             JOIN somativa_slots_config sc ON sc.id = sg.slot_config_id
             JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
             JOIN turmas t ON t.id = st.turma_id
             LEFT JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
             LEFT JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
             WHERE sg.somativa_id = ?
             ORDER BY sg.data_prova, sc.ordem",
            [$somativaId]
        );

        if (empty($allocs)) return [];

        $workloads = [];
        $teachers = $this->fetchAll(
            "SELECT u.id, u.name
             FROM users u
             JOIN user_institutions ui ON ui.user_id = u.id AND ui.institution_id = ?
             WHERE u.is_active = 1 AND (u.is_teacher = 1 OR u.profile = 'Professor')",
            [$institutionId]
        );

        foreach ($teachers as $t) {
            $workloads[(int)$t['id']] = [
                'id' => (int)$t['id'],
                'name' => $t['name'],
                'load' => 0
            ];
        }

        $uniqueLoads = $this->getTeachersUniqueWorkloads($somativaId, $institutionId);
        $totalAllocations = 0;
        $allocatedCount = 0;

        foreach ($uniqueLoads as $ul) {
            $pid = $ul['professor_id'];
            if (isset($workloads[$pid])) {
                $workloads[$pid]['load'] = $ul['total'];
                if ($ul['total'] > 0) {
                    $totalAllocations += $ul['total'];
                    $allocatedCount++;
                }
            }
        }

        $avgLoad = $allocatedCount > 0 ? ($totalAllocations / $allocatedCount) : 0;

        $regularClasses = $this->fetchAll(
            "SELECT tdp.professor_id, gta.dia_semana, gta.horario_inicio, gta.horario_fim
             FROM gestao_turma_aulas gta
             JOIN turmas tr ON tr.id = gta.turma_id
             JOIN courses c ON c.id = tr.course_id
             JOIN turma_disciplinas td ON td.turma_id = gta.turma_id AND td.disciplina_codigo = gta.disciplina_codigo
             JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
             WHERE c.institution_id = ? AND gta.is_active = 1",
            [$institutionId]
        );

        $groupedClasses = [];
        foreach ($regularClasses as $rc) {
            $pid = (int)$rc['professor_id'];
            $ds  = (int)$rc['dia_semana'];
            $groupedClasses[$pid][$ds][] = [
                'horario_inicio' => $rc['horario_inicio'],
                'horario_fim'    => $rc['horario_fim']
            ];
        }

        $disciplineTeachers = [];
        $dtRows = $this->fetchAll(
            "SELECT tdp.professor_id, td.disciplina_codigo, u.name
             FROM turma_disciplina_professores tdp
             JOIN turma_disciplinas td ON td.id = tdp.turma_disciplina_id
             JOIN users u ON u.id = tdp.professor_id"
        );
        foreach ($dtRows as $dtr) {
            $cod = $dtr['disciplina_codigo'];
            $pid = (int)$dtr['professor_id'];
            $disciplineTeachers[$cod][$pid] = $dtr['name'];
        }

        $busyInSlot = [];
        foreach ($allocs as $a) {
            $key = $a['data_prova'] . '_' . $a['slot_config_id'];
            $ap = (int)$a['aplicador_id'];
            $vo = (int)$a['volante_id'];
            $na = (int)$a['naapi_aplicador_id'];

            if ($ap) $busyInSlot[$key][$ap] = true;
            if ($vo) $busyInSlot[$key][$vo] = true;
            if ($na) $busyInSlot[$key][$na] = true;
        }

        $suggestions = [];

        foreach ($allocs as $a) {
            $key = $a['data_prova'] . '_' . $a['slot_config_id'];
            $date = $a['data_prova'];
            $dow = (int)date('N', strtotime($date));
            $start = $a['horario_inicio'];
            $end = $a['horario_fim'];
            $slotLabel = $a['slot_label'] ?: substr($start, 0, 5) . '–' . substr($end, 0, 5);

            $roles = [
                'aplicador_id'       => ['label' => 'Aplicador', 'id' => (int)$a['aplicador_id']],
                'volante_id'         => ['label' => 'Volante', 'id' => (int)$a['volante_id']],
                'naapi_aplicador_id' => ['label' => 'Aplicador NAAPI', 'id' => (int)$a['naapi_aplicador_id']]
            ];

            foreach ($roles as $field => $roleInfo) {
                $currentProfId = $roleInfo['id'];
                if (!$currentProfId) continue;

                $currentLoad = $workloads[$currentProfId]['load'] ?? 0;
                $isOverloaded = ($currentLoad > $avgLoad + 1 && $currentLoad >= 3);
                $isVolanteSwap = false;

                if ($field === 'volante_id') {
                    $cod = $a['disciplina_codigo'];
                    $isRegularTeacher = isset($disciplineTeachers[$cod][$currentProfId]);

                    if (!$isRegularTeacher && !empty($disciplineTeachers[$cod])) {
                        foreach (array_keys($disciplineTeachers[$cod]) as $dpId) {
                            if ($dpId === $currentProfId) continue;
                            if (isset($busyInSlot[$key][$dpId])) continue;
                            $isVolanteSwap = true;
                        }
                    }
                }

                if ($isOverloaded || $isVolanteSwap) {
                    $candidates = [];

                    foreach ($workloads as $candidateId => $wl) {
                        if ($candidateId === $currentProfId) continue;
                        if (isset($busyInSlot[$key][$candidateId])) continue;

                        $candidateLoad = $wl['load'];
                        if ($candidateLoad >= $currentLoad) continue;

                        $hasConventionalClass = false;
                        if (isset($groupedClasses[$candidateId][$dow])) {
                            foreach ($groupedClasses[$candidateId][$dow] as $class) {
                                if ($class['horario_inicio'] < $end && $class['horario_fim'] > $start) {
                                    $hasConventionalClass = true;
                                    break;
                                }
                            }
                        }

                        $score = 0;
                        $isRegular = false;
                        if ($field === 'volante_id') {
                            $cod = $a['disciplina_codigo'];
                            if (isset($disciplineTeachers[$cod][$candidateId])) {
                                $score += 100;
                                $isRegular = true;
                            }
                        }

                        if ($hasConventionalClass) {
                            $score += 50;
                        }

                        $score += (20 - $candidateLoad);

                        $candidates[] = [
                            'id' => $candidateId,
                            'name' => $wl['name'],
                            'load' => $candidateLoad,
                            'score' => $score,
                            'is_regular' => $isRegular,
                            'has_conventional' => $hasConventionalClass
                        ];
                    }

                    if (!empty($candidates)) {
                        usort($candidates, function($x, $y) {
                            return $y['score'] - $x['score'];
                        });

                        $best = $candidates[0];
                        $diff = $currentLoad - $best['load'];
                        if ($isOverloaded && !$best['is_regular'] && $diff < 2) {
                            continue;
                        }

                        $suggestions[] = [
                            'grade_id'               => (int)$a['grade_id'],
                            'data_prova'             => $date,
                            'dia_semana_br'          => ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'][$dow],
                            'slot_label'             => $slotLabel,
                            'turma_desc'             => $a['turma_desc'],
                            'disc_nome'              => $a['disc_nome'] ?: 'Segunda Chamada',
                            'role_field'             => $field,
                            'role_label'             => $roleInfo['label'],
                            'current_teacher_id'     => $currentProfId,
                            'current_teacher_name'   => $workloads[$currentProfId]['name'] ?? '',
                            'current_teacher_load'   => $currentLoad,
                            'suggested_teacher_id'   => $best['id'],
                            'suggested_teacher_name' => $best['name'],
                            'suggested_teacher_load' => $best['load'],
                            'reason'                 => $best['is_regular'] 
                                                        ? "Professor regular da disciplina ({$best['name']}) disponível."
                                                        : "Reduzir carga de {$workloads[$currentProfId]['name']} ({$currentLoad}) repassando para {$best['name']} ({$best['load']})."
                        ];
                    }
                }
            }
        }

        $uniqueSuggestions = [];
        $seen = [];
        foreach ($suggestions as $s) {
            $ukey = $s['grade_id'] . '_' . $s['role_field'];
            if (!isset($seen[$ukey])) {
                $seen[$ukey] = true;
                $uniqueSuggestions[] = $s;
            }
        }

        return $uniqueSuggestions;
    }

    // ════════════════════════════════════════════════════
    // ALOCAÇÃO INTELIGENTE DE APLICADORES
    // ════════════════════════════════════════════════════

    /**
     * Propõe aplicadores e volantes para todas as provas normais da grade.
     *
     * Critérios (por ordem de prioridade):
     *   Volante  → sempre o professor da disciplina.
     *   Aplicador → 1º professor da PRÓPRIA turma com aula no horário,
     *               2º qualquer professor com aula no horário,
     *               3º qualquer professor disponível.
     *   Equalização → entre candidatos equivalentes, escolhe o de menor carga.
     *   Naapi     → mesmo critério do aplicador, campo independente.
     */
    public function previewIntelligentAllocation(int $somativaId, int $instId): array
    {
        $som = $this->fetchOne(
            'SELECT naapi_ambiente_id FROM somativas WHERE id = ?',
            [$somativaId]
        );
        $naapiAtivo = !empty($som['naapi_ambiente_id']);

        // Todas as provas normais alocadas na grade
        $allocs = $this->fetchAll(
            "SELECT sg.id AS grade_id, sg.data_prova, sg.slot_config_id, sg.somativa_turma_id,
                    sg.somativa_disciplina_id,
                    sc.horario_inicio, sc.horario_fim, sc.label AS slot_label,
                    t.id AS turma_id, t.description AS turma_desc,
                    d.descricao AS disc_nome, sd.disciplina_codigo
             FROM somativa_grade sg
             JOIN somativa_slots_config sc ON sc.id = sg.slot_config_id
             JOIN somativa_turmas st       ON st.id = sg.somativa_turma_id
             JOIN turmas t                 ON t.id  = st.turma_id
             LEFT JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
             LEFT JOIN disciplinas d           ON d.codigo = sd.disciplina_codigo
             WHERE sg.somativa_id = ? AND sg.tipo = 'Normal'
             ORDER BY sg.data_prova, sc.ordem",
            [$somativaId]
        );

        if (empty($allocs)) {
            return ['proposals' => [], 'stats' => ['total' => 0, 'com_volante' => 0, 'com_aplicador' => 0, 'com_naapi' => null], 'naapi_ativo' => $naapiAtivo, 'load_dist' => []];
        }

        // Professores ativos da instituição
        $allTeachers = $this->fetchAll(
            "SELECT u.id, u.name FROM users u
             JOIN user_institutions ui ON ui.user_id = u.id AND ui.institution_id = ?
             WHERE u.is_active = 1 AND (u.is_teacher = 1 OR u.profile = 'Professor')
             ORDER BY u.name",
            [$instId]
        );
        $teacherMap = [];
        foreach ($allTeachers as $t) {
            $teacherMap[(int)$t['id']] = $t['name'];
        }

        // Aulas regulares agrupadas por professor → dia_semana → [{turma_id, inicio, fim}]
        $regularClasses = $this->fetchAll(
            "SELECT DISTINCT tdp.professor_id, gta.turma_id, gta.dia_semana,
                    gta.horario_inicio, gta.horario_fim
             FROM gestao_turma_aulas gta
             JOIN turmas tr ON tr.id = gta.turma_id
             JOIN courses c ON c.id = tr.course_id
             JOIN turma_disciplinas td ON td.turma_id = gta.turma_id
                 AND td.disciplina_codigo = gta.disciplina_codigo
             JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
             WHERE c.institution_id = ? AND gta.is_active = 1",
            [$instId]
        );
        $byProfDay = [];
        foreach ($regularClasses as $rc) {
            $pid = (int)$rc['professor_id'];
            $dow = (int)$rc['dia_semana'];
            $byProfDay[$pid][$dow][] = [
                'turma_id' => (int)$rc['turma_id'],
                'inicio'   => $rc['horario_inicio'],
                'fim'      => $rc['horario_fim'],
            ];
        }

        // Professores por disciplina+turma (para determinar o volante)
        $dtRows = $this->fetchAll(
            "SELECT tdp.professor_id, td.turma_id, td.disciplina_codigo
             FROM turma_disciplina_professores tdp
             JOIN turma_disciplinas td ON td.id = tdp.turma_disciplina_id
             JOIN turmas t ON t.id = td.turma_id
             JOIN courses c ON c.id = t.course_id
             WHERE c.institution_id = ?",
            [$instId]
        );
        $discTeachers = [];
        foreach ($dtRows as $r) {
            $turmaId = (int)$r['turma_id'];
            $cod     = $r['disciplina_codigo'];
            $pid     = (int)$r['professor_id'];
            $discTeachers[$turmaId][$cod][] = $pid;
        }

        // Contador de carga acumulada nesta rodada (começa zerado — equalização pura)
        $runningLoad = [];
        foreach (array_keys($teacherMap) as $pid) {
            $runningLoad[$pid] = 0;
        }

        // Ocupação por slot: slotKey → [profId => true]
        $slotOccupancy = [];
        // Aplicador NAAPI definido por slot: slotKey → profId
        $slotNaapiApplicators = [];

        $proposals = [];

        foreach ($allocs as $a) {
            $gradeId   = (int)$a['grade_id'];
            $date      = $a['data_prova'];
            $slotId    = (int)$a['slot_config_id'];
            $turmaId   = (int)$a['turma_id'];
            $cod       = $a['disciplina_codigo'] ?? '';
            $dow       = (int)(new \DateTime($date))->format('N');
            $slotStart = $a['horario_inicio'];
            $slotEnd   = $a['horario_fim'];
            $slotKey   = $date . '_' . $slotId;

            if (!isset($slotOccupancy[$slotKey])) {
                $slotOccupancy[$slotKey] = [];
            }

            // ── Volante = professor da disciplina ─────────────────
            $volanteId   = null;
            $volanteName = null;

            if ($cod && isset($discTeachers[$turmaId][$cod])) {
                foreach ($discTeachers[$turmaId][$cod] as $pid) {
                    if (isset($teacherMap[$pid]) && !isset($slotOccupancy[$slotKey][$pid])) {
                        $volanteId   = $pid;
                        $volanteName = $teacherMap[$pid];
                        break;
                    }
                }
                // Se todos os professores da disciplina já estão no slot, usa o primeiro mesmo assim
                if ($volanteId === null && !empty($discTeachers[$turmaId][$cod])) {
                    $pid = $discTeachers[$turmaId][$cod][0];
                    if (isset($teacherMap[$pid])) {
                        $volanteId   = $pid;
                        $volanteName = $teacherMap[$pid];
                    }
                }
            }

            if ($volanteId !== null) {
                $slotOccupancy[$slotKey][$volanteId] = true;
            }

            // ── Aplicador ──────────────────────────────────────────
            $aplicadorId   = null;
            $aplicadorName = null;

            $apCandidates = [];
            foreach ($teacherMap as $pid => $pname) {
                if (isset($slotOccupancy[$slotKey][$pid])) continue;

                $thisTurma = false;
                $anyTurma  = false;

                if (isset($byProfDay[$pid][$dow])) {
                    foreach ($byProfDay[$pid][$dow] as $cls) {
                        if ($cls['inicio'] < $slotEnd && $cls['fim'] > $slotStart) {
                            $anyTurma = true;
                            if ($cls['turma_id'] === $turmaId) {
                                $thisTurma = true;
                            }
                        }
                    }
                }

                $apCandidates[$pid] = [
                    'turma_match' => $thisTurma,
                    'any_class'   => $anyTurma,
                    'load'        => $runningLoad[$pid] ?? 0,
                    'name'        => $pname,
                ];
            }

            // Ordena: turma_match ↓ → any_class ↓ → load ↑
            uasort($apCandidates, function ($x, $y) {
                if ($x['turma_match'] !== $y['turma_match']) {
                    return $y['turma_match'] <=> $x['turma_match'];
                }
                if ($x['any_class'] !== $y['any_class']) {
                    return $y['any_class'] <=> $x['any_class'];
                }
                return $x['load'] <=> $y['load'];
            });

            if (!empty($apCandidates)) {
                $bestPid       = (int)array_key_first($apCandidates);
                $aplicadorId   = $bestPid;
                $aplicadorName = $teacherMap[$bestPid];
                $slotOccupancy[$slotKey][$bestPid] = true;
                $runningLoad[$bestPid] = ($runningLoad[$bestPid] ?? 0) + 1;
            }

            // ── Aplicador NAAPI ────────────────────────────────────
            $naapiId   = null;
            $naapiName = null;

            if ($naapiAtivo) {
                if (isset($slotNaapiApplicators[$slotKey])) {
                    $naapiId   = $slotNaapiApplicators[$slotKey];
                    $naapiName = $teacherMap[$naapiId];
                    $slotOccupancy[$slotKey][$naapiId] = true;
                } else {
                    $naCandidates = [];
                    foreach ($teacherMap as $pid => $pname) {
                        if (isset($slotOccupancy[$slotKey][$pid])) continue;

                        $thisTurma = false;
                        $anyTurma  = false;

                        if (isset($byProfDay[$pid][$dow])) {
                            foreach ($byProfDay[$pid][$dow] as $cls) {
                                if ($cls['inicio'] < $slotEnd && $cls['fim'] > $slotStart) {
                                    $anyTurma = true;
                                    if ($cls['turma_id'] === $turmaId) {
                                        $thisTurma = true;
                                    }
                                }
                            }
                        }

                        $naCandidates[$pid] = [
                            'turma_match' => $thisTurma,
                            'any_class'   => $anyTurma,
                            'load'        => $runningLoad[$pid] ?? 0,
                        ];
                    }

                    uasort($naCandidates, function ($x, $y) {
                        if ($x['turma_match'] !== $y['turma_match']) {
                            return $y['turma_match'] <=> $x['turma_match'];
                        }
                        if ($x['any_class'] !== $y['any_class']) {
                            return $y['any_class'] <=> $x['any_class'];
                        }
                        return $x['load'] <=> $y['load'];
                    });

                    if (!empty($naCandidates)) {
                        $bestNaapi     = (int)array_key_first($naCandidates);
                        $naapiId       = $bestNaapi;
                        $naapiName     = $teacherMap[$bestNaapi];
                        $slotOccupancy[$slotKey][$bestNaapi] = true;
                        $runningLoad[$bestNaapi] = ($runningLoad[$bestNaapi] ?? 0) + 1;
                        $slotNaapiApplicators[$slotKey] = $bestNaapi;
                    }
                }
            }

            $proposals[] = [
                'grade_id'             => $gradeId,
                'data_prova'           => $date,
                'dia_semana_br'        => ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'][$dow],
                'slot_label'           => $a['slot_label'] ?: (substr($slotStart, 0, 5) . '–' . substr($slotEnd, 0, 5)),
                'turma_desc'           => $a['turma_desc'],
                'disc_nome'            => $a['disc_nome'] ?: 'Segunda Chamada',
                'volante_id'           => $volanteId,
                'volante_nome'         => $volanteName,
                'aplicador_id'         => $aplicadorId,
                'aplicador_nome'       => $aplicadorName,
                'naapi_aplicador_id'   => $naapiId,
                'naapi_aplicador_nome' => $naapiName,
            ];
        }

        // Distribuição de carga resultante
        $loadDist = [];
        foreach ($runningLoad as $pid => $load) {
            if ($load > 0) {
                $loadDist[] = ['name' => $teacherMap[$pid] ?? "#{$pid}", 'load' => $load];
            }
        }
        usort($loadDist, function ($a, $b) { return $b['load'] - $a['load']; });

        $stats = [
            'total'          => count($proposals),
            'com_volante'    => count(array_filter($proposals, function ($p) { return $p['volante_id'] !== null; })),
            'com_aplicador'  => count(array_filter($proposals, function ($p) { return $p['aplicador_id'] !== null; })),
            'com_naapi'      => $naapiAtivo
                ? count(array_filter($proposals, function ($p) { return $p['naapi_aplicador_id'] !== null; }))
                : null,
        ];

        return [
            'proposals' => $proposals,
            'stats'     => $stats,
            'load_dist' => $loadDist,
            'naapi_ativo' => $naapiAtivo,
        ];
    }

    /**
     * Aplica as propostas geradas por previewIntelligentAllocation.
     */
    public function applyIntelligentAllocation(int $somativaId, array $proposals, int $userId): array
    {
        $this->beginTransaction();
        try {
            $updated = 0;
            foreach ($proposals as $p) {
                $gradeId = (int)($p['grade_id'] ?? 0);
                if (!$gradeId) continue;

                $existing = $this->fetchOne(
                    'SELECT id FROM somativa_grade WHERE id = ? AND somativa_id = ?',
                    [$gradeId, $somativaId]
                );
                if (!$existing) continue;

                $old = $this->fetchOne('SELECT * FROM somativa_grade WHERE id = ?', [$gradeId]);

                $this->execute(
                    'UPDATE somativa_grade
                     SET volante_id = ?, aplicador_id = ?, naapi_aplicador_id = ?, updated_at = NOW()
                     WHERE id = ?',
                    [
                        $p['volante_id']   ?: null,
                        $p['aplicador_id'] ?: null,
                        $p['naapi_aplicador_id'] ?: null,
                        $gradeId,
                    ]
                );

                $this->audit('UPDATE', 'somativa_grade', $gradeId, $old, [
                    'volante_id'         => $p['volante_id'],
                    'aplicador_id'       => $p['aplicador_id'],
                    'naapi_aplicador_id' => $p['naapi_aplicador_id'],
                ]);
                $updated++;
            }

            $this->commit();
            return ['success' => true, 'updated' => $updated];
        } catch (\Exception $e) {
            $this->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Aplica as trocas de equalização selecionadas.
     */
    public function applyEqualization(int $somativaId, array $swaps, int $userId): array {
        $db = $this->db;
        $this->beginTransaction();
        try {
            $updated = 0;
            foreach ($swaps as $swap) {
                $gradeId   = (int)($swap['grade_id'] ?? 0);
                $field     = trim($swap['role_field'] ?? '');
                $newProfId = (int)($swap['suggested_teacher_id'] ?? 0) ?: null;

                if (!$gradeId || !in_array($field, ['aplicador_id', 'volante_id', 'naapi_aplicador_id'])) {
                    continue;
                }

                $old = $this->fetchOne(
                    "SELECT id, somativa_id, aplicador_id, volante_id, naapi_aplicador_id 
                     FROM somativa_grade WHERE id = ? AND somativa_id = ?",
                    [$gradeId, $somativaId]
                );

                if (!$old) continue;

                $stmt = $db->prepare(
                    "UPDATE somativa_grade SET {$field} = ?, updated_at = NOW() WHERE id = ?"
                );
                $stmt->execute([$newProfId, $gradeId]);

                $this->audit('UPDATE', 'somativa_grade', $gradeId, $old, [$field => $newProfId]);
                $updated++;
            }

            $this->commit();
            return ['success' => true, 'updated' => $updated];
        } catch (\Exception $e) {
            $this->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

