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
            'INSERT INTO somativas (institution_id, nome, descricao, data_inicio, data_fim, max_provas_por_dia, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $instId,
            trim($data['nome']),
            trim($data['descricao'] ?? '') ?: null,
            $data['data_inicio'],
            $data['data_fim'],
            (int)($data['max_provas_por_dia'] ?? 2),
            'Rascunho',
            $userId,
        ]);

        $newId = $this->lastInsertId();
        $this->audit('CREATE', 'somativas', $newId, null, $data);
        return ['success' => true, 'id' => $newId];
    }

    public function update(int $id, array $data): array {
        $allowed = ['nome', 'descricao', 'data_inicio', 'data_fim', 'max_provas_por_dia', 'status', 'segunda_chamada_data', 'naapi_ambiente_id', 'naapi_tempo_extra_min'];
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
                "SELECT sd.id, sd.disciplina_codigo, d.descricao AS disc_nome
                 FROM somativa_disciplinas sd
                 JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
                 WHERE sd.somativa_turma_id = ?
                 ORDER BY d.descricao",
                [$turma['somativa_turma_id']]
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
                "SELECT DISTINCT u.id, u.name,
                        gta.horario_inicio, gta.horario_fim, gta.disciplina_codigo, d.descricao AS disc_nome
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
                            $byProf[$pid] = ['id' => $pid, 'name' => $t['name'], 'discs' => []];
                        }
                        $byProf[$pid]['discs'][] = $t['disc_nome'];
                    }
                }
                $avail = [];
                foreach ($byProf as $p) {
                    $avail[] = [
                        'id'   => $p['id'],
                        'name' => $p['name'],
                        'disc' => implode(', ', array_unique($p['discs'])),
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
            'bloquear_data'          => [],
            'evitar_mesmo_dia'       => [],
            'mesmo_dia_turmas'       => [],
            'mesmo_horario_turmas'   => [],
            'mesmo_dia_horario_diferente' => [],
            'professor_indisponivel' => [],
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
}
