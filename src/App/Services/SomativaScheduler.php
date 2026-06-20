<?php
/**
 * Vértice Acadêmico — Engine de Alocação Automática de Somativas
 *
 * Algoritmo greedy com pontuação por restrições.
 * Complexidade: O(D × S × T) onde D = disciplinas totais, S = slots, T = turmas.
 *
 * Restrições suportadas:
 *   hard  → bloquear_data, professor_indisponivel
 *   soft  → mesmo_dia_turmas, evitar_mesmo_dia, baixo_rendimento, preferencia_data
 *
 * Fluxo:
 *   1. Carrega dados (somativa, turmas, disciplinas, slots, restrições, disponibilidade)
 *   2. Ordena disciplinas por dificuldade de alocação (mais restritas primeiro)
 *   3. Para cada disciplina, pontua todos os (data × slot) livres
 *   4. Aloca no slot de maior pontuação
 *   5. Retorna: alocações prontas + conflitos + avisos
 */

namespace App\Services;

class SomativaScheduler extends Service
{
    // ──────────────────────────────────────────────────────────
    // Ponto de entrada
    // ──────────────────────────────────────────────────────────

    /**
     * @return array{
     *   alocacoes: array,
     *   conflitos: array,
     *   avisos:    array
     * }
     */
    public function run(int $somativaId, int $instId): array
    {
        $data = $this->loadData($somativaId, $instId);

        if (empty($data)) {
            return ['alocacoes' => [], 'conflitos' => [], 'avisos' => ['Somativa não encontrada.']];
        }

        if (empty($data['turmas']) || empty($data['slots']) || empty($data['dates'])) {
            return ['alocacoes' => [], 'conflitos' => [], 'avisos' => ['Configure turmas, slots e datas antes de alocar.']];
        }

        return $this->greedyAllocate($data);
    }

    // ──────────────────────────────────────────────────────────
    // Carregamento de dados
    // ──────────────────────────────────────────────────────────

    private function loadData(int $somativaId, int $instId): array
    {
        $som = $this->fetchOne(
            'SELECT s.*, ma.descricao AS naapi_ambiente_desc
             FROM somativas s
             LEFT JOIN manutencao_ambientes ma ON ma.id = s.naapi_ambiente_id
             WHERE s.id = ? AND s.institution_id = ?',
            [$somativaId, $instId]
        );
        if (!$som) return [];

        $slots = $this->fetchAll(
            'SELECT * FROM somativa_slots_config WHERE somativa_id = ? ORDER BY ordem',
            [$somativaId]
        );

        $dates  = $this->buildDates($som['data_inicio'], $som['data_fim']);

        // Data de 2ª Chamada: configurada na somativa
        $scData = $som['segunda_chamada_data'] ?: null;

        $turmas = $this->fetchAll(
            "SELECT st.id AS som_turma_id, st.turma_id,
                    t.description AS turma_desc,
                    t.ambiente_id AS turma_ambiente_id,
                    c.name AS course_name
             FROM somativa_turmas st
             JOIN turmas t  ON t.id  = st.turma_id
             JOIN courses c ON c.id  = t.course_id
             WHERE st.somativa_id = ?
             ORDER BY c.name, t.description",
            [$somativaId]
        );

        foreach ($turmas as &$turma) {
            $turma['disciplinas'] = $this->loadDisciplinas(
                (int)$turma['som_turma_id'],
                (int)$turma['turma_id']
            );
        }
        unset($turma);

        $restricoes = $this->fetchAll(
            'SELECT * FROM somativa_restricoes WHERE somativa_id = ? AND is_active = 1',
            [$somativaId]
        );

        // Disponibilidade de professores por dia-da-semana
        $profAvail = $this->loadProfAvailability($instId);

        // Horário de turmas (para determinar aplicador)
        $turmaSchedule = $this->loadTurmaSchedule($instId);

        // Mapa [profId][discCod] => [stId, ...] — para restrições professor_mesmo_dia_horario
        $profDiscTurmas = [];
        foreach ($turmas as $t) {
            $stId = (int)$t['som_turma_id'];
            foreach ($t['disciplinas'] as $d) {
                $cod     = $d['disciplina_codigo'];
                $profIds = array_filter(explode(',', $d['professor_ids'] ?? ''));
                foreach ($profIds as $pid) {
                    $profDiscTurmas[(int)$pid][$cod][] = $stId;
                }
            }
        }

        return compact('som', 'slots', 'dates', 'turmas', 'restricoes', 'profAvail', 'turmaSchedule', 'scData', 'profDiscTurmas');
    }

    private function loadDisciplinas(int $somTurmaId, int $turmaId): array
    {
        return $this->fetchAll(
            "SELECT sd.id AS som_disc_id,
                    sd.disciplina_codigo,
                    sd.professor_aplicador,
                    d.descricao  AS disc_nome,
                    ROUND(AVG(en.nota), 1)      AS media_turma,
                    COALESCE(MAX(e.media_nota), 6.00) AS media_aprovacao,
                    GROUP_CONCAT(DISTINCT tdp.professor_id
                                 ORDER BY tdp.professor_id
                                 SEPARATOR ',') AS professor_ids
             FROM somativa_disciplinas sd
             JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
             LEFT JOIN etapas e  ON e.turma_id = ? AND e.is_active = 1
             LEFT JOIN etapa_notas en
                    ON en.etapa_id = e.id
                   AND en.disciplina_codigo = sd.disciplina_codigo
                   AND en.nota IS NOT NULL
             LEFT JOIN turma_disciplinas td
                    ON td.turma_id = ? AND td.disciplina_codigo = sd.disciplina_codigo
             LEFT JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
             WHERE sd.somativa_turma_id = ?
             GROUP BY sd.id, sd.disciplina_codigo, d.descricao",
            [$turmaId, $turmaId, $somTurmaId]
        );
    }

    private function buildDates(string $inicio, string $fim): array
    {
        $dates  = [];
        $cursor = new \DateTime($inicio);
        $end    = new \DateTime($fim);
        while ($cursor <= $end) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor->modify('+1 day');
        }
        return $dates;
    }

    /**
     * Indexa disponibilidade por [dia_semana][professor_id] → [[ini, fim], ...]
     */
    private function loadProfAvailability(int $instId): array
    {
        $rows = $this->fetchAll(
            "SELECT DISTINCT gta.dia_semana,
                    tdp.professor_id,
                    gta.horario_inicio,
                    gta.horario_fim
             FROM gestao_turma_aulas gta
             JOIN turmas t  ON t.id  = gta.turma_id
             JOIN courses c ON c.id  = t.course_id
             JOIN turma_disciplinas td
                    ON td.turma_id = gta.turma_id
                   AND td.disciplina_codigo = gta.disciplina_codigo
             JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
             WHERE c.institution_id = ? AND gta.is_active = 1",
            [$instId]
        );

        $avail = [];
        foreach ($rows as $r) {
            $avail[(int)$r['dia_semana']][(int)$r['professor_id']][] = [
                $r['horario_inicio'],
                $r['horario_fim'],
            ];
        }
        return $avail;
    }

    /**
     * Indexa qual professor tem aula em qual turma/dia_semana → [[horario_inicio, horario_fim, professor_id], ...]
     */
    private function loadTurmaSchedule(int $instId): array
    {
        $rows = $this->fetchAll(
            "SELECT gta.turma_id,
                    gta.dia_semana,
                    gta.horario_inicio,
                    gta.horario_fim,
                    tdp.professor_id
             FROM gestao_turma_aulas gta
             JOIN turmas t  ON t.id  = gta.turma_id
             JOIN courses c ON c.id  = t.course_id
             JOIN turma_disciplinas td
                    ON td.turma_id = gta.turma_id
                   AND td.disciplina_codigo = gta.disciplina_codigo
             JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
             WHERE c.institution_id = ? AND gta.is_active = 1",
            [$instId]
        );

        $schedule = [];
        foreach ($rows as $r) {
            $schedule[(int)$r['turma_id']][(int)$r['dia_semana']][] = [
                'horario_inicio' => $r['horario_inicio'],
                'horario_fim'    => $r['horario_fim'],
                'professor_id'   => (int)$r['professor_id']
            ];
        }
        return $schedule;
    }

    // ──────────────────────────────────────────────────────────
    // Algoritmo Greedy
    // ──────────────────────────────────────────────────────────

    private function greedyAllocate(array $data): array
    {
        $alocacoes = [];
        $conflitos = [];
        $avisos    = [];

        $maxPorDia = (int)$data['som']['max_provas_por_dia'];
        $scData    = $data['scData'];
        $evitarConflitoProf = !empty($data['som']['evitar_conflito_professor']);

        // Estado de alocação
        $ocupados    = [];  // [stId][date][slotId] = true
        $countDia    = [];  // [stId][date] = n
        $discNoDia   = [];  // [stId][date] = [discCod, ...]
        $discEmData  = [];  // [stId][sdId] = date  (para rastrear mesmo_dia_turmas)
        $codEmData   = [];  // [date][discCod] = count (global, para mesmo_dia_turmas cross-turma)

        $restricoes = $this->indexRestricoes($data['restricoes']);

        $constrainedHorario = [];
        $hardConstrainedHorario = [];
        foreach ($restricoes['mesmo_horario_turmas'] as $r) {
            $scope = $r['scope'] ?? 'disciplina';
            $isHard = ($r['tipo'] ?? 'soft') === 'hard';
            
            if ($scope === 'disciplina') {
                $c = $r['disciplina_codigo'] ?? '';
                if ($c) {
                    $constrainedHorario[$c] = true;
                    if ($isHard) {
                        $hardConstrainedHorario[$c] = true;
                    }
                }
            } else if ($scope === 'turma') {
                $tId = (int)($r['turma_id'] ?? 0);
                foreach ($data['turmas'] as $t) {
                    if ((int)$t['turma_id'] === $tId) {
                        foreach ($t['disciplinas'] as $d) {
                            $c = $d['disciplina_codigo'];
                            $constrainedHorario[$c] = true;
                            if ($isHard) {
                                $hardConstrainedHorario[$c] = true;
                            }
                        }
                    }
                }
            }
        }

        $constrainedDia = [];
        $hardConstrainedDia = [];
        foreach ($restricoes['mesmo_dia_turmas'] as $r) {
            $isHard = ($r['tipo'] ?? 'soft') === 'hard';
            $c = $r['disciplina_codigo'] ?? '';
            if ($c) {
                $constrainedDia[$c] = true;
                if ($isHard) {
                    $hardConstrainedDia[$c] = true;
                }
            }
        }

        // Restrições professor_mesmo_dia_horario
        $hardProfDisc = [];
        $softProfDisc = [];
        $hardProfAll  = [];
        $softProfAll  = [];
        $profDiscTurmas = $data['profDiscTurmas'] ?? [];

        // profAllStIds[profId] = [stId, ...] — turmas distintas com disciplinas do professor
        $profAllStIds = [];
        foreach ($profDiscTurmas as $pid => $discMap) {
            $stIds = array_unique(array_merge(...array_values($discMap)));
            if (count($stIds) >= 2) {
                $profAllStIds[$pid] = $stIds;
            }
        }

        foreach ($restricoes['professor_mesmo_dia_horario'] as $r) {
            $isHard  = ($r['tipo'] ?? 'soft') === 'hard';
            $peso    = max(1, (int)($r['peso'] ?? 5));
            $isTodos = !empty($r['todos']);

            if ($isTodos) {
                foreach (array_keys($profAllStIds) as $profId) {
                    if ($isHard) {
                        $hardProfAll[$profId] = true;
                    } else {
                        $softProfAll[$profId] = max($softProfAll[$profId] ?? 0, $peso);
                    }
                }
            } else {
                $profId = (int)($r['professor_id'] ?? 0);
                if (!$profId || !isset($profDiscTurmas[$profId])) continue;
                foreach ($profDiscTurmas[$profId] as $discCod => $stIds) {
                    if (count($stIds) < 2) continue;
                    if ($isHard) {
                        $hardProfDisc[$profId][$discCod] = true;
                    } else {
                        $softProfDisc[$profId][$discCod] = $peso;
                    }
                }
            }
        }

        // Restrições mesmo_dia_horario_grupo
        $sdIdLookup = [];
        foreach ($data['turmas'] as $t) {
            foreach ($t['disciplinas'] as $d) {
                $sdIdLookup[(int)$d['som_disc_id']] = [
                    'stId'  => (int)$t['som_turma_id'],
                    'turma' => $t,
                    'disc'  => $d,
                ];
            }
        }

        $hardGrupos      = [];
        $softGrupos      = [];
        foreach ($restricoes['mesmo_dia_horario_grupo'] as $r) {
            $isHard = ($r['tipo'] ?? 'soft') === 'hard';
            $sdIds  = array_values(array_unique(array_filter(
                array_map(fn($p) => (int)($p['somativa_disciplina_id'] ?? 0), $r['pares'] ?? [])
            )));
            if (count($sdIds) < 2) continue;
            $entry = ['sdIds' => $sdIds, 'peso' => max(1, (int)($r['peso'] ?? 5))];
            if ($isHard) $hardGrupos[] = $entry;
            else         $softGrupos[] = $entry;
        }

        $discInHardGrupo = [];
        foreach ($hardGrupos as $gi => $g) {
            foreach ($g['sdIds'] as $sid) { $discInHardGrupo[$sid][] = $gi; }
        }
        $discInSoftGrupo = [];
        foreach ($softGrupos as $gi => $g) {
            foreach ($g['sdIds'] as $sid) { $discInSoftGrupo[$sid][] = $gi; }
        }

        // Datas válidas para provas normais (tenta excluir a data de 2ª chamada se configurada)
        $datesNormais = $data['dates'];
        if ($scData !== null) {
            $datesNormais = array_filter($data['dates'], fn($d) => $d !== $scData);
            if (empty($datesNormais)) {
                $datesNormais = $data['dates'];
                $avisos[] = 'A data de 2ª Chamada coincide com o único dia disponível — provas normais serão alocadas nela também.';
            }
            $datesNormais = array_values($datesNormais);
        }

        // Mapeia código de disciplina para as som_turma_ids que a contêm
        $discTurmas = [];
        foreach ($data['turmas'] as $t) {
            $tId = (int)$t['som_turma_id'];
            foreach ($t['disciplinas'] as $d) {
                $discTurmas[$d['disciplina_codigo']][] = $tId;
            }
        }

        // Avisos de viabilidade para min_provas_por_dia
        foreach ($restricoes['min_provas_por_dia'] as $r) {
            $min   = max(1, (int)($r['min'] ?? 2));
            $scope = $r['scope'] ?? 'todas';
            $totalDays = count($datesNormais);
            foreach ($data['turmas'] as $turma) {
                $stId = (int)$turma['som_turma_id'];
                $applies = $scope === 'todas' ||
                           ($scope === 'turma' && (int)($r['somativa_turma_id'] ?? 0) === $stId);
                if (!$applies) continue;
                $totalDiscs = count($turma['disciplinas']);
                if ($totalDiscs < $totalDays) {
                    $avisos[] = "Turma \"{$turma['turma_desc']}\": apenas {$totalDiscs} disciplinas para {$totalDays} dias — não é possível garantir 1 prova por dia. Geração normal aplicada.";
                } elseif ($totalDiscs < $min * $totalDays) {
                    $avisos[] = "Turma \"{$turma['turma_desc']}\": {$totalDiscs} disciplinas para {$totalDays} dias — mínimo de {$min}/dia não é possível em todos os dias.";
                }
            }
        }

        // ── Fase 0: pré-alocação de grupos Hard/Todos por professor ──────────
        $preAllocated = [];
        if (!empty($hardProfAll)) {
            $context = [
                'hardConstrainedHorario' => $hardConstrainedHorario,
                'hardConstrainedDia'     => $hardConstrainedDia,
                'discTurmas'             => $discTurmas,
                'hardProfDisc'           => $hardProfDisc,
                'profDiscTurmas'         => $profDiscTurmas,
                'hardGrupos'             => $hardGrupos,
                'discInHardGrupo'        => $discInHardGrupo,
                'sdIdLookup'             => $sdIdLookup,
                'evitarConflitoProf'     => $evitarConflitoProf,
            ];
            $preAllocated = $this->preAllocateHardProfAll(
                $data, $restricoes, $hardProfAll, $profAllStIds, $datesNormais,
                $alocacoes, $ocupados, $countDia, $discNoDia, $codEmData,
                $context
            );
        }

        $queue = $this->buildAllocationQueue($data, $restricoes, $constrainedHorario, $constrainedDia, $hardProfDisc, $softProfDisc, $discInHardGrupo, $discInSoftGrupo, $hardProfAll, $softProfAll);

        // Algoritmo de Busca por Retrocesso com Heurística (Heuristic Backtracking)
        $bestAllocCount = 0;
        $bestAllocations = [];
        $calls = 0;

        $success = $this->solveBacktrack(
            0, $queue, $data, $restricoes, $datesNormais, $maxPorDia, $scData, $evitarConflitoProf,
            $constrainedHorario, $hardConstrainedHorario, $hardConstrainedDia,
            $hardProfDisc, $profDiscTurmas, $hardGrupos, $discInHardGrupo, $sdIdLookup, $hardProfAll, $profAllStIds,
            $softProfDisc, $softGrupos, $discInSoftGrupo, $softProfAll, $discTurmas,
            $ocupados, $countDia, $discNoDia, $codEmData, $discEmData, $alocacoes, $preAllocated,
            $calls, $bestAllocCount, $bestAllocations
        );

        if (!$success) {
            // Caso não encontre uma solução 100% perfeita devido a regras impossíveis,
            // restaura a melhor alocação parcial encontrada.
            $alocacoes = $bestAllocations;
            $allocatedSdIds = array_column($alocacoes, 'somativa_disciplina_id');

            // Reconstrói estado temporário para gerar justificativas de erro precisas
            $tempOcupados = [];
            $tempCountDia = [];
            foreach ($alocacoes as $aloc) {
                $sId = (int)$aloc['somativa_turma_id'];
                $d = $aloc['data_prova'];
                $scId = (int)$aloc['slot_config_id'];
                $tempOcupados[$sId][$d][$scId] = true;
                $tempCountDia[$sId][$d] = ($tempCountDia[$sId][$d] ?? 0) + 1;
            }

            foreach ($queue as $item) {
                $sdId = (int)$item['disciplina']['som_disc_id'];
                if (in_array($sdId, $allocatedSdIds)) continue;

                $blockMotivos = [];
                foreach ($datesNormais as $date) {
                    if (($tempCountDia[$item['som_turma_id']][$date] ?? 0) >= $maxPorDia) {
                        $blockMotivos[] = "{$date}: limite de provas/dia atingido";
                        continue;
                    }
                    foreach ($data['slots'] as $slot) {
                        $slotId = (int)$slot['id'];
                        if (!empty($tempOcupados[$item['som_turma_id']][$date][$slotId])) continue;

                        [$blocked, $blockReason] = $this->checkHardBlocks(
                            $date, $slot, $item['disciplina'], $restricoes, $alocacoes, $item['som_turma_id'],
                            $hardConstrainedHorario, $hardConstrainedDia,
                            $tempOcupados, $tempCountDia, $discTurmas, $data['slots'], $maxPorDia,
                            $hardProfDisc, $profDiscTurmas,
                            $hardGrupos, $discInHardGrupo, $sdIdLookup,
                            $hardProfAll, $profAllStIds,
                            $evitarConflitoProf
                        );
                        if ($blocked) {
                            $blockMotivos[] = "{$date}/slot{$slotId}: {$blockReason}";
                        }
                    }
                }

                $resumoBloqueios = implode(' | ', array_slice(array_unique($blockMotivos), 0, 3));
                $conflitos[] = [
                    'somativa_disciplina_id' => $sdId,
                    'disciplina_codigo'      => $item['disciplina']['disciplina_codigo'],
                    'disc_nome'              => $item['disciplina']['disc_nome'],
                    'turma_desc'             => $item['turma']['turma_desc'],
                    'motivo'                 => $resumoBloqueios ?: 'Nenhum slot livre sob as restrições obrigatórias',
                ];
            }

            $avisos[] = 'Aviso: Não foi possível encontrar uma grade 100% perfeita com as restrições atuais. ' . count($conflitos) . ' disciplinas não puderam ser alocadas automaticamente.';
        }

        return ['alocacoes' => $alocacoes, 'conflitos' => $conflitos, 'avisos' => $avisos];
    }

    /**
     * Solucionador Recursivo de Backtracking com Heurística de Seleção de Slots.
     */
    private function solveBacktrack(
        int $queueIdx,
        array $queue,
        array $data,
        array $restricoes,
        array $datesNormais,
        int $maxPorDia,
        ?string $scData,
        bool $evitarConflitoProf,
        array $constrainedHorario,
        array $hardConstrainedHorario,
        array $hardConstrainedDia,
        array $hardProfDisc,
        array $profDiscTurmas,
        array $hardGrupos,
        array $discInHardGrupo,
        array $sdIdLookup,
        array $hardProfAll,
        array $profAllStIds,
        array $softProfDisc,
        array $softGrupos,
        array $discInSoftGrupo,
        array $softProfAll,
        array $discTurmas,
        array &$ocupados,
        array &$countDia,
        array &$discNoDia,
        array &$codEmData,
        array &$discEmData,
        array &$alocacoes,
        array &$preAllocated,
        int &$calls,
        int &$bestAllocCount,
        array &$bestAllocations
    ): bool {
        $calls++;
        if ($calls > 50000) {
            return false; // Evita loop infinito em grades impossíveis
        }

        // Registra o maior progresso parcial
        if ($queueIdx > $bestAllocCount) {
            $bestAllocCount = $queueIdx;
            $bestAllocations = $alocacoes;
        }

        if ($queueIdx >= count($queue)) {
            return true;
        }

        $item    = $queue[$queueIdx];
        $stId    = $item['som_turma_id'];
        $disc    = $item['disciplina'];
        $turma   = $item['turma'];
        $sdId    = (int)$disc['som_disc_id'];
        $discCod = $disc['disciplina_codigo'];

        // Se já foi pré-alocado na Fase 0, pula para o próximo
        if (isset($preAllocated[$sdId])) {
            return $this->solveBacktrack(
                $queueIdx + 1, $queue, $data, $restricoes, $datesNormais, $maxPorDia, $scData, $evitarConflitoProf,
                $constrainedHorario, $hardConstrainedHorario, $hardConstrainedDia,
                $hardProfDisc, $profDiscTurmas, $hardGrupos, $discInHardGrupo, $sdIdLookup, $hardProfAll, $profAllStIds,
                $softProfDisc, $softGrupos, $discInSoftGrupo, $softProfAll, $discTurmas,
                $ocupados, $countDia, $discNoDia, $codEmData, $discEmData, $alocacoes, $preAllocated,
                $calls, $bestAllocCount, $bestAllocations
            );
        }

        $candidates = [];

        foreach ($datesNormais as $date) {
            if (($countDia[$stId][$date] ?? 0) >= $maxPorDia) {
                continue;
            }

            // Hard: min_provas_por_dia
            $hardMinBlock = false;
            foreach ($restricoes['min_provas_por_dia'] as $r) {
                if ($r['tipo'] !== 'hard') continue;
                $rMin    = max(1, (int)($r['min'] ?? 2));
                $rScope  = $r['scope'] ?? 'todas';
                $rApply  = $rScope === 'todas' || ($rScope === 'turma' && (int)($r['somativa_turma_id'] ?? 0) === $stId);
                if (!$rApply) continue;
                $dateCount = count($discNoDia[$stId][$date] ?? []);
                if ($dateCount >= $rMin) {
                    foreach ($datesNormais as $d) {
                        if ($d === $date) continue;
                        if (count($discNoDia[$stId][$d] ?? []) < $rMin) {
                            $hardMinBlock = true;
                            break;
                        }
                    }
                }
                if ($hardMinBlock) break;
            }
            if ($hardMinBlock) continue;

            foreach ($data['slots'] as $slot) {
                $slotId = (int)$slot['id'];
                if (!empty($ocupados[$stId][$date][$slotId])) continue;

                // Verifica bloqueios hard
                [$blocked, $blockReason] = $this->checkHardBlocks(
                    $date, $slot, $disc, $restricoes, $alocacoes, $stId,
                    $hardConstrainedHorario, $hardConstrainedDia,
                    $ocupados, $countDia, $discTurmas, $data['slots'], $maxPorDia,
                    $hardProfDisc, $profDiscTurmas,
                    $hardGrupos, $discInHardGrupo, $sdIdLookup,
                    $hardProfAll, $profAllStIds,
                    $evitarConflitoProf
                );
                if ($blocked) continue;

                // Pontua o slot (Heurística)
                [$score, $reasons] = $this->scoreSlot(
                    $date, $slot, $disc, $turma, $restricoes, $data,
                    $discNoDia, $codEmData, $stId, $scData,
                    $constrainedHorario, $alocacoes,
                    $softProfDisc, $profDiscTurmas,
                    $softGrupos, $discInSoftGrupo,
                    $softProfAll, $profAllStIds
                );

                $candidates[] = [
                    'date'          => $date,
                    'slot'          => $slot,
                    'score'         => $score,
                    'justification' => implode('; ', $reasons) ?: 'alocação padrão',
                ];
            }
        }

        // Ordena candidatos de forma decrescente por score (Heurística de valor mais promissor)
        usort($candidates, fn($a, $b) => $b['score'] - $a['score']);

        foreach ($candidates as $cand) {
            $date   = $cand['date'];
            $slot   = $cand['slot'];
            $slotId = (int)$slot['id'];

            // Aplica a alocação
            $ocupados[$stId][$date][$slotId] = true;
            $countDia[$stId][$date] = ($countDia[$stId][$date] ?? 0) + 1;
            $discNoDia[$stId][$date][] = $discCod;
            $codEmData[$date][$discCod] = ($codEmData[$date][$discCod] ?? 0) + 1;
            $discEmData[$stId][$sdId] = $date;

            [$aplicadorId, $volanteId] = $this->chooseProfessores(
                $disc, ['date' => $date, 'slot' => $slot], $data['profAvail'], $data['turmaSchedule'], (int)$turma['turma_id'], $alocacoes
            );
            $naapiAplicadorId = null;
            if (!empty($data['som']['naapi_ambiente_id'])) {
                $naapiAplicadorId = $this->chooseNaapiAplicador(
                    ['date' => $date, 'slot' => $slot], $data['profAvail'], $aplicadorId, $volanteId, $alocacoes
                );
            }

            $aloc = [
                'somativa_id'            => (int)$data['som']['id'],
                'somativa_turma_id'      => $stId,
                'somativa_disciplina_id' => $sdId,
                'disciplina_codigo'      => $discCod,
                'disc_nome'              => $disc['disc_nome'],
                'disc_professor_ids'     => $disc['professor_ids'] ?? '',
                'turma_desc'             => $turma['turma_desc'],
                'data_prova'             => $date,
                'slot_config_id'         => $slotId,
                'slot_label'             => $slot['label'] ?? null,
                'horario_inicio'         => $slot['horario_inicio'],
                'horario_fim'            => $slot['horario_fim'],
                'aplicador_id'           => $aplicadorId,
                'volante_id'             => $volanteId,
                'naapi_aplicador_id'     => $naapiAplicadorId,
                'ambiente_id'            => $turma['turma_ambiente_id'] ?: null,
                'tipo'                   => 'Normal',
                'observacoes'            => null,
                'justificativa'          => $cand['justification'],
            ];
            $alocacoes[] = $aloc;

            // Recorre recursivamente para a próxima
            if ($this->solveBacktrack(
                $queueIdx + 1, $queue, $data, $restricoes, $datesNormais, $maxPorDia, $scData, $evitarConflitoProf,
                $constrainedHorario, $hardConstrainedHorario, $hardConstrainedDia,
                $hardProfDisc, $profDiscTurmas, $hardGrupos, $discInHardGrupo, $sdIdLookup, $hardProfAll, $profAllStIds,
                $softProfDisc, $softGrupos, $discInSoftGrupo, $softProfAll, $discTurmas,
                $ocupados, $countDia, $discNoDia, $codEmData, $discEmData, $alocacoes, $preAllocated,
                $calls, $bestAllocCount, $bestAllocations
            )) {
                return true;
            }

            // Desfaz a alocação (Backtrack)
            array_pop($alocacoes);
            unset($ocupados[$stId][$date][$slotId]);
            $countDia[$stId][$date]--;
            if ($countDia[$stId][$date] <= 0) {
                unset($countDia[$stId][$date]);
            }

            $idx = array_search($discCod, $discNoDia[$stId][$date]);
            if ($idx !== false) {
                unset($discNoDia[$stId][$date][$idx]);
                $discNoDia[$stId][$date] = array_values($discNoDia[$stId][$date]);
                if (empty($discNoDia[$stId][$date])) {
                    unset($discNoDia[$stId][$date]);
                }
            }

            $codEmData[$date][$discCod]--;
            if ($codEmData[$date][$discCod] <= 0) {
                unset($codEmData[$date][$discCod]);
            }
            if (empty($codEmData[$date])) {
                unset($codEmData[$date]);
            }

            unset($discEmData[$stId][$sdId]);
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────
    // Índice de Restrições
    // ──────────────────────────────────────────────────────────

    private function indexRestricoes(array $restricoes): array
    {
        $idx = [
            'bloquear_data'               => [],
            'evitar_mesmo_dia'            => [],
            'mesmo_dia_turmas'            => [],
            'mesmo_horario_turmas'        => [],
            'mesmo_dia_horario_diferente' => [],
            'professor_indisponivel'      => [],
            'professor_mesmo_dia_horario' => [],
            'preferir_primeiros_horarios' => [],
            'mesmo_dia_horario_grupo'     => [],
            'min_provas_por_dia'          => [],
        ];

        foreach ($restricoes as $r) {
            $cat    = $r['categoria'];
            $params = json_decode($r['params'], true) ?? [];
            if (!array_key_exists($cat, $idx)) $idx[$cat] = [];
            $idx[$cat][] = array_merge(
                ['tipo' => $r['tipo'], 'peso' => max(1, (int)($r['peso'] ?? 5))],
                $params
            );
        }

        return $idx;
    }

    // ──────────────────────────────────────────────────────────
    // Fila de alocação (mais restrita primeiro)
    // ──────────────────────────────────────────────────────────

    private function buildAllocationQueue(
        array $data, array $restricoes,
        array $constrainedHorario, array $constrainedDia,
        array $hardProfDisc = [], array $softProfDisc = [],
        array $discInHardGrupo = [], array $discInSoftGrupo = [],
        array $hardProfAll = [], array $softProfAll = []
    ): array {
        $queue = [];

        foreach ($data['turmas'] as $turma) {
            $stId = (int)$turma['som_turma_id'];

            foreach ($turma['disciplinas'] as $disc) {
                $cod      = $disc['disciplina_codigo'];
                $priority = 0;

                foreach ($restricoes['mesmo_dia_turmas'] as $r) {
                    if (($r['disciplina_codigo'] ?? '') === $cod) { $priority += 100; break; }
                }
                foreach ($restricoes['evitar_mesmo_dia'] as $r) {
                    if (in_array($cod, $r['disciplinas'] ?? [])) { $priority += 50; break; }
                }
                if (isset($constrainedHorario[$cod])) {
                    $priority += 150;
                }
                if (isset($constrainedDia[$cod])) {
                    $priority += 100;
                }
                if (isset($restricoes['mesmo_dia_horario_diferente'])) {
                    foreach ($restricoes['mesmo_dia_horario_diferente'] as $r) {
                        if (($r['disciplina_codigo_a'] ?? '') === $cod || ($r['disciplina_codigo_b'] ?? '') === $cod) {
                            $priority += (($r['tipo'] ?? '') === 'hard') ? 120 : 40;
                        }
                    }
                }

                // professor_mesmo_dia_horario: prioriza disciplinas que precisam sincronizar por professor
                $profIds = array_filter(explode(',', $disc['professor_ids'] ?? ''));
                foreach ($profIds as $pid) {
                    $pid = (int)$pid;
                    if (isset($hardProfAll[$pid]))        { $priority += 160; break; }
                    if (isset($softProfAll[$pid]))        { $priority += 80;  break; }
                    if (isset($hardProfDisc[$pid][$cod])) { $priority += 160; break; }
                    if (isset($softProfDisc[$pid][$cod])) { $priority += 80;  break; }
                }

                // mesmo_dia_horario_grupo: prioriza disciplinas em grupos de sincronização
                $curSdId = (int)$disc['som_disc_id'];
                if (isset($discInHardGrupo[$curSdId])) { $priority += 140; }
                elseif (isset($discInSoftGrupo[$curSdId])) { $priority += 70; }

                // O rendimento escolar (sugestão) não é mais considerado como regra automática de prioridade.

                $queue[] = [
                    'som_turma_id' => $stId,
                    'disciplina'   => $disc,
                    'turma'        => $turma,
                    'priority'     => $priority,
                ];
            }
        }

        usort($queue, fn($a, $b) => $b['priority'] - $a['priority']);
        return $queue;
    }

    // ──────────────────────────────────────────────────────────
    // Bloqueios Hard
    // ──────────────────────────────────────────────────────────

    private function checkHardBlocks(
        string $date, array $slot, array $disc,
        array $restricoes, array $alocacoes, int $stId,
        array $hardConstrainedHorario, array $hardConstrainedDia,
        array $ocupados, array $countDia, array $discTurmas, array $allSlots, int $maxPorDia,
        array $hardProfDisc = [], array $profDiscTurmas = [],
        array $hardGrupos = [], array $discInHardGrupo = [], array $sdIdLookup = [],
        array $hardProfAll = [], array $profAllStIds = [],
        bool $evitarConflitoProf = false
    ): array {
        // Data bloqueada
        foreach ($restricoes['bloquear_data'] as $r) {
            if (($r['data'] ?? '') === $date) {
                return [true, "Data bloqueada: " . ($r['motivo'] ?? 'restrição cadastrada')];
            }
        }

        // Professor indisponível
        $profIds = array_filter(explode(',', $disc['professor_ids'] ?? ''));
        foreach ($profIds as $pid) {
            foreach ($restricoes['professor_indisponivel'] as $r) {
                if ((int)($r['professor_id'] ?? 0) !== (int)$pid) continue;
                if (in_array($date, $r['datas'] ?? [])) {
                    return [true, "Professor {$pid} indisponível em {$date}"];
                }
            }
        }

        // Professor Aplicador: exige que o professor da disciplina esteja livre para aplicar a prova
        if (!empty($disc['professor_aplicador'])) {
            $disciplineProfs = array_map('intval', $profIds);
            
            // Encontra quais professores estão ocupados neste slot em outras alocações
            $busyProfs = [];
            foreach ($alocacoes as $aloc) {
                if ($aloc['data_prova'] === $date && (int)$aloc['slot_config_id'] === (int)$slot['id']) {
                    if ($aloc['aplicador_id']) {
                        $busyProfs[] = (int)$aloc['aplicador_id'];
                    }
                    if ($aloc['volante_id']) {
                        $busyProfs[] = (int)$aloc['volante_id'];
                    }
                    if (!empty($aloc['naapi_aplicador_id'])) {
                        $busyProfs[] = (int)$aloc['naapi_aplicador_id'];
                    }
                }
            }
            
            $hasFreeProf = false;
            foreach ($disciplineProfs as $pid) {
                if (!in_array($pid, $busyProfs)) {
                    $hasFreeProf = true;
                    break;
                }
            }
            if (!$hasFreeProf && !empty($disciplineProfs)) {
                return [true, "O professor da disciplina está ocupado em outro papel neste slot"];
            }
        }

        // Hard constraint: mesmo_horario_turmas
        $cod = $disc['disciplina_codigo'];
        if (isset($hardConstrainedHorario[$cod])) {
            $alreadyAllocated = null;
            foreach ($alocacoes as $aloc) {
                if ($aloc['disciplina_codigo'] === $cod && (int)$aloc['somativa_turma_id'] !== $stId) {
                    $alreadyAllocated = $aloc;
                    break;
                }
            }

            if ($alreadyAllocated !== null) {
                if ($alreadyAllocated['data_prova'] !== $date || (int)$alreadyAllocated['slot_config_id'] !== (int)$slot['id']) {
                    return [true, "Mesmo Horário (Hard): a disciplina {$cod} deve ser alocada no mesmo slot que a turma " . $alreadyAllocated['turma_desc']];
                }
            } else {
                // Nenhuma outra turma foi alocada ainda. Garante que o slot candidato está livre e válido nas outras turmas da restrição.
                $otherTurmas = $discTurmas[$cod] ?? [];
                foreach ($otherTurmas as $otherStId) {
                    if ($otherStId === $stId) continue;
                    // Slot já ocupado por outra disciplina na turma parceira
                    if (!empty($ocupados[$otherStId][$date][$slot['id']])) {
                        return [true, "Mesmo Horário (Hard/Antecipado): slot já ocupado na turma ID {$otherStId} em {$date}"];
                    }
                    // Dia já esgotou o limite de provas nessa turma — não conseguirá encaixar aqui
                    if (($countDia[$otherStId][$date] ?? 0) >= $maxPorDia) {
                        return [true, "Mesmo Horário (Hard/Antecipado): limite de provas/dia atingido na turma ID {$otherStId} em {$date}"];
                    }
                }
            }
        }

        // Hard constraint: mesmo_dia_turmas
        if (isset($hardConstrainedDia[$cod])) {
            $alreadyAllocated = null;
            foreach ($alocacoes as $aloc) {
                if ($aloc['disciplina_codigo'] === $cod && (int)$aloc['somativa_turma_id'] !== $stId) {
                    $alreadyAllocated = $aloc;
                    break;
                }
            }

            if ($alreadyAllocated !== null) {
                if ($alreadyAllocated['data_prova'] !== $date) {
                    return [true, "Mesmo Dia (Hard): a disciplina {$cod} deve ser alocada no mesmo dia que a turma " . $alreadyAllocated['turma_desc']];
                }
            } else {
                // Nenhuma outra turma foi alocada ainda. Garante que o dia candidato é livre e válido nas outras turmas da restrição.
                $otherTurmas = $discTurmas[$cod] ?? [];
                foreach ($otherTurmas as $otherStId) {
                    if ($otherStId === $stId) continue;
                    if (($countDia[$otherStId][$date] ?? 0) >= $maxPorDia) {
                        return [true, "Mesmo Dia (Hard/Antecipado): limite de provas atingido em {$date} na turma ID {$otherStId}"];
                    }
                    $hasFreeSlot = false;
                    foreach ($allSlots as $s) {
                        if (empty($ocupados[$otherStId][$date][$s['id']])) {
                            $dateBlocked = false;
                            foreach ($restricoes['bloquear_data'] as $r) {
                                if (($r['data'] ?? '') === $date) {
                                    $dateBlocked = true;
                                    break;
                                }
                            }
                            if (!$dateBlocked) {
                                $hasFreeSlot = true;
                                break;
                            }
                        }
                    }
                    if (!$hasFreeSlot) {
                        return [true, "Mesmo Dia (Hard/Antecipado): nenhum slot livre em {$date} na turma ID {$otherStId}"];
                    }
                }
            }
        }

        // Hard constraint: mesmo_dia_horario_diferente
        if (isset($restricoes['mesmo_dia_horario_diferente'])) {
            foreach ($restricoes['mesmo_dia_horario_diferente'] as $r) {
                if (($r['tipo'] ?? 'soft') !== 'hard') continue;
                $codA  = $r['disciplina_codigo_a'] ?? '';
                $codB  = $r['disciplina_codigo_b'] ?? '';
                $scope = $r['scope'] ?? 'mesma_turma';
                $regra = $r['regra'] ?? 'deve';

                if ($cod === $codA || $cod === $codB) {
                    $targetCod = ($cod === $codA) ? $codB : $codA;
                    
                    // Procuramos se já existe alguma alocação do targetCod
                    $alreadyAllocated = [];
                    foreach ($alocacoes as $aloc) {
                        if ($aloc['disciplina_codigo'] === $targetCod) {
                            $scopeMatch = false;
                            if ($scope === 'mesma_turma') {
                                if ((int)$aloc['somativa_turma_id'] === $stId) {
                                    $scopeMatch = true;
                                }
                            } else {
                                if ((int)$aloc['somativa_turma_id'] !== $stId) {
                                    $scopeMatch = true;
                                }
                            }
                            if ($scopeMatch) {
                                $alreadyAllocated[] = $aloc;
                            }
                        }
                    }

                    if (!empty($alreadyAllocated)) {
                        foreach ($alreadyAllocated as $aloc) {
                            if ($regra === 'deve') {
                                if ($aloc['data_prova'] !== $date) {
                                    return [true, "Relação de dia/horário (Hard): a disciplina {$cod} deve ser alocada no mesmo dia que {$targetCod} ({$aloc['data_prova']})"];
                                }
                                if ((int)$aloc['slot_config_id'] === (int)$slot['id']) {
                                    return [true, "Relação de dia/horário (Hard): a disciplina {$cod} deve ser alocada em horário diferente de {$targetCod} na turma " . $aloc['turma_desc']];
                                }
                            } else { // nao_deve
                                if ($aloc['data_prova'] === $date) {
                                    return [true, "Relação de dia/horário (Hard): a disciplina {$cod} não pode ser alocada no mesmo dia que {$targetCod} na turma " . $aloc['turma_desc']];
                                }
                            }
                        }
                    } else {
                        // Look-ahead: se a outra disciplina ainda não foi alocada
                        if ($regra === 'deve') {
                            if ($scope === 'mesma_turma') {
                                // Garante que a turma stId tem capacidade para pelo menos 2 provas no dia candidate
                                if (($countDia[$stId][$date] ?? 0) >= $maxPorDia - 1) {
                                    return [true, "Relação de dia/horário (Hard/Antecipado): o dia {$date} na turma ID {$stId} precisa de capacidade para pelo menos 2 provas"];
                                }
                            } else { // turmas_diferentes
                                // Para cada outra turma que contém o targetCod, ela precisa ter um slot livre na data candidate diferente de $slot['id']
                                $otherTurmas = $discTurmas[$targetCod] ?? [];
                                foreach ($otherTurmas as $otherStId) {
                                    if ($otherStId === $stId) continue;
                                    if (($countDia[$otherStId][$date] ?? 0) >= $maxPorDia) {
                                        return [true, "Relação de dia/horário (Hard/Antecipado): limite de provas excedido na outra turma ID {$otherStId} em {$date}"];
                                    }
                                    $hasValidSlot = false;
                                    foreach ($allSlots as $s) {
                                        if ((int)$s['id'] === (int)$slot['id']) continue;
                                        if (empty($ocupados[$otherStId][$date][$s['id']])) {
                                            $hasValidSlot = true;
                                            break;
                                        }
                                    }
                                    if (!$hasValidSlot) {
                                        return [true, "Relação de dia/horário (Hard/Antecipado): a outra turma ID {$otherStId} não tem slots disponíveis em {$date} diferentes do slot candidato"];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Hard constraint: preferir_primeiros_horarios
        foreach ($restricoes['preferir_primeiros_horarios'] as $r) {
            if (($r['tipo'] ?? 'soft') !== 'hard') continue;
            $scope = $r['scope'] ?? 'todas';
            $applies = ($scope === 'todas') ||
                       ($scope === 'turma' && (int)($r['somativa_turma_id'] ?? 0) === $stId);
            if (!$applies) continue;

            $currentOrdem = (int)($slot['ordem'] ?? 1);
            foreach ($allSlots as $s) {
                if ((int)($s['ordem'] ?? 1) >= $currentOrdem) continue;
                if (empty($ocupados[$stId][$date][$s['id']])) {
                    return [true, 'Primeiros Horários (Hard): slot de ordem ' . ($s['ordem'] ?? '?') . ' ainda disponível em ' . $date];
                }
            }
            break;
        }

        // Hard constraint: professor_mesmo_dia_horario
        if (!empty($hardProfDisc)) {
            $profIds = array_filter(explode(',', $disc['professor_ids'] ?? ''));
            foreach ($profIds as $pid) {
                $pid = (int)$pid;
                if (!isset($hardProfDisc[$pid][$cod])) continue;

                // Encontrar a primeira alocação desta disciplina por este professor em outra turma
                $anchor = null;
                foreach ($alocacoes as $aloc) {
                    if ($aloc['disciplina_codigo'] !== $cod) continue;
                    if ((int)$aloc['somativa_turma_id'] === $stId) continue;
                    // Verifica se o prof X está nessa alocação como professor da disciplina
                    $otherProfIds = array_filter(explode(',', $aloc['disc_professor_ids'] ?? ''));
                    if (empty($otherProfIds)) {
                        // Fallback: se não temos os IDs armazenados, confia no mapa
                        if (isset($profDiscTurmas[$pid][$cod]) && in_array((int)$aloc['somativa_turma_id'], $profDiscTurmas[$pid][$cod])) {
                            $anchor = $aloc;
                            break;
                        }
                    } elseif (in_array((string)$pid, $otherProfIds)) {
                        $anchor = $aloc;
                        break;
                    }
                }

                if ($anchor !== null) {
                    if ($anchor['data_prova'] !== $date || (int)$anchor['slot_config_id'] !== (int)$slot['id']) {
                        return [true, "Professor {$pid} (Hard): {$cod} deve ser no mesmo dia/horário que na turma {$anchor['turma_desc']}"];
                    }
                } else {
                    // Look-ahead: garante que as outras turmas onde (pid, cod) existe têm este slot livre
                    $otherStIds = $profDiscTurmas[$pid][$cod] ?? [];
                    foreach ($otherStIds as $otherStId) {
                        if ($otherStId === $stId) continue;
                        if (!empty($ocupados[$otherStId][$date][$slot['id']])) {
                            return [true, "Professor {$pid} (Hard/Antecipado): slot {$slot['id']} em {$date} já ocupado na outra turma ID {$otherStId}"];
                        }
                        foreach ($restricoes['bloquear_data'] as $r) {
                            if (($r['data'] ?? '') === $date) {
                                return [true, "Professor {$pid} (Hard/Antecipado): data {$date} está bloqueada"];
                            }
                        }
                        if (($countDia[$otherStId][$date] ?? 0) >= $maxPorDia) {
                            return [true, "Professor {$pid} (Hard/Antecipado): limite de provas atingido em {$date} na turma ID {$otherStId}"];
                        }
                    }
                }
                break; // restrição aplicada — não precisa checar outros profs da mesma disciplina
            }
        }

        // Hard constraint: professor_mesmo_dia_horario (todos) — qualquer disciplina do prof em outra turma
        if (!empty($hardProfAll)) {
            $profIds = array_filter(explode(',', $disc['professor_ids'] ?? ''));
            foreach ($profIds as $pid) {
                $pid = (int)$pid;
                if (!isset($hardProfAll[$pid])) continue;

                // Anchor: qualquer alocação deste professor em outra turma
                $anchor = null;
                foreach ($alocacoes as $aloc) {
                    if ((int)$aloc['somativa_turma_id'] === $stId) continue;
                    $otherProfIds = array_filter(explode(',', $aloc['disc_professor_ids'] ?? ''));
                    $otherStIds   = $profAllStIds[$pid] ?? [];
                    $inOtherTurma = in_array((int)$aloc['somativa_turma_id'], $otherStIds);
                    if (!empty($otherProfIds)) {
                        if (!in_array((string)$pid, $otherProfIds)) continue;
                    } elseif (!$inOtherTurma) {
                        continue;
                    }
                    $anchor = $aloc;
                    break;
                }

                if ($anchor !== null) {
                    if ($anchor['data_prova'] !== $date || (int)$anchor['slot_config_id'] !== (int)$slot['id']) {
                        return [true, "Professor {$pid} (Hard/Todos): {$disc['disciplina_codigo']} deve ser no mesmo dia/horário que {$anchor['disciplina_codigo']} na turma {$anchor['turma_desc']}"];
                    }
                } else {
                    // Look-ahead: verifica se as demais turmas do prof têm este slot livre
                    foreach ($profAllStIds[$pid] ?? [] as $otherStId) {
                        if ($otherStId === $stId) continue;
                        if (!empty($ocupados[$otherStId][$date][$slot['id']])) {
                            return [true, "Professor {$pid} (Hard/Todos/Antecipado): slot {$slot['id']} em {$date} já ocupado na turma ID {$otherStId}"];
                        }
                        if (($countDia[$otherStId][$date] ?? 0) >= $maxPorDia) {
                            return [true, "Professor {$pid} (Hard/Todos/Antecipado): limite de provas atingido em {$date} na turma ID {$otherStId}"];
                        }
                    }
                }
                break;
            }
        }

        // Hard constraint: mesmo_dia_horario_grupo
        $curSdId = (int)$disc['som_disc_id'];
        foreach ($discInHardGrupo[$curSdId] ?? [] as $gi) {
            $grupo = $hardGrupos[$gi];

            // Procura anchor: outro disc do grupo já alocado
            $anchor = null;
            foreach ($alocacoes as $aloc) {
                if ((int)$aloc['somativa_disciplina_id'] === $curSdId) continue;
                if (in_array((int)$aloc['somativa_disciplina_id'], $grupo['sdIds'])) {
                    $anchor = $aloc;
                    break;
                }
            }

            if ($anchor !== null) {
                if ($anchor['data_prova'] !== $date || (int)$anchor['slot_config_id'] !== (int)$slot['id']) {
                    return [true, "Grupo Mesmo Slot (Hard): deve coincidir com {$anchor['disc_nome']} ({$anchor['turma_desc']}) em {$anchor['data_prova']}"];
                }
            } else {
                // Look-ahead: verifica que os demais membros do grupo têm este slot viável
                foreach ($grupo['sdIds'] as $otherSdId) {
                    if ($otherSdId === $curSdId) continue;
                    if (!isset($sdIdLookup[$otherSdId])) continue;
                    $otherStId      = $sdIdLookup[$otherSdId]['stId'];
                    $otherTurmaDesc = $sdIdLookup[$otherSdId]['turma']['turma_desc'] ?? "ID {$otherStId}";

                    // Outro membro do grupo está na MESMA turma: alocar aqui tornaria
                    // impossível que ele também use este slot (turma não pode ter 2 provas simultâneas).
                    if ($otherStId === $stId) {
                        return [true, "Grupo Mesmo Slot (Hard): outra disciplina do grupo está na mesma turma ({$otherTurmaDesc}) — impossível compartilhar o mesmo slot"];
                    }

                    if (!empty($ocupados[$otherStId][$date][$slot['id']])) {
                        return [true, "Grupo Mesmo Slot (Hard/Antecipado): slot {$slot['id']} em {$date} já ocupado na turma {$otherTurmaDesc}"];
                    }
                    foreach ($restricoes['bloquear_data'] as $r) {
                        if (($r['data'] ?? '') === $date) {
                            return [true, "Grupo Mesmo Slot (Hard/Antecipado): data {$date} bloqueada"];
                        }
                    }
                    if (($countDia[$otherStId][$date] ?? 0) >= $maxPorDia) {
                        return [true, "Grupo Mesmo Slot (Hard/Antecipado): limite de provas atingido em {$date} na turma {$otherTurmaDesc}"];
                    }
                }
            }
        }

        // Conflito de professor com outra turma no mesmo slot simultâneo
        if ($evitarConflitoProf) {
            $slotId = (int)$slot['id'];
            foreach ($alocacoes as $aloc) {
                if ($aloc['data_prova'] !== $date) continue;
                if ((int)$aloc['slot_config_id'] !== $slotId) continue;
                if ((int)$aloc['somativa_turma_id'] === $stId) continue;
                // Se o aplicador do aloc existente é professor desta disciplina → conflito
                if ($aloc['aplicador_id'] && in_array((string)$aloc['aplicador_id'], $profIds)) {
                    return [true, "Professor já alocado como aplicador em outra turma neste slot"];
                }
            }
        }

        return [false, ''];
    }

    // ──────────────────────────────────────────────────────────
    // Pontuação Soft
    // ──────────────────────────────────────────────────────────

    private function scoreSlot(
        string $date, array $slot, array $disc, array $turma,
        array $restricoes, array $data,
        array $discNoDia, array $codEmData, int $stId, ?string $scData,
        array $constrainedCodes, array $alocacoes,
        array $softProfDisc = [], array $profDiscTurmas = [],
        array $softGrupos = [], array $discInSoftGrupo = [],
        array $softProfAll = [], array $profAllStIds = []
    ): array {
        $score   = 0;
        $reasons = [];
        $cod     = $disc['disciplina_codigo'];
        $dow     = (int)(new \DateTime($date))->format('N');

        // Fim de semana: penaliza levemente
        if ($dow >= 6) {
            $score -= 5;
            $reasons[] = 'fim de semana (−5)';
        }

        // Data de 2ª chamada: penaliza fortemente se configurada
        if ($scData !== null && $date === $scData) {
            $score -= 50;
            $reasons[] = 'data reservada 2ª chamada (−50)';
        }

        // mesmo_dia_turmas: já existe outra turma com esta disciplina neste dia → bônus
        foreach ($restricoes['mesmo_dia_turmas'] as $r) {
            if (($r['disciplina_codigo'] ?? '') !== $cod) continue;
            if (!empty($codEmData[$date][$cod])) {
                $score += 25;
                $reasons[] = 'mesmo dia que outra turma (+25)';
            }
        }

        // mesmo_horario_turmas (soft): match date and slot config of existing identical subject allocations
        if (isset($constrainedCodes[$cod])) {
            $alocSlot = null;
            foreach ($alocacoes as $aloc) {
                if ($aloc['disciplina_codigo'] === $cod && (int)$aloc['somativa_turma_id'] !== $stId) {
                    $alocSlot = $aloc;
                    break;
                }
            }
            if ($alocSlot !== null) {
                // Find constraint weight (peso)
                $peso = 5;
                foreach ($restricoes['mesmo_horario_turmas'] as $r) {
                    $scope = $r['scope'] ?? 'disciplina';
                    if ($scope === 'disciplina' && ($r['disciplina_codigo'] ?? '') === $cod) {
                        $peso = max(1, (int)($r['peso'] ?? 5));
                        break;
                    } else if ($scope === 'turma' && (int)($r['turma_id'] ?? 0) === (int)$turma['turma_id']) {
                        $peso = max(1, (int)($r['peso'] ?? 5));
                        break;
                    }
                }

                if ($alocSlot['data_prova'] === $date && (int)$alocSlot['slot_config_id'] === (int)$slot['id']) {
                    $bonus = $peso * 40; // e.g. weight 5 => +200 bonus
                    $score += $bonus;
                    $reasons[] = "mesmo horário que outra turma (+{$bonus})";
                } else {
                    $penalty = $peso * 40; // e.g. weight 5 => -200 penalty
                    $score -= $penalty;
                    $reasons[] = "horário divergente da outra turma (−{$penalty})";
                }
            }
        }

        // Professor disponível neste horário → bônus (pode ser volante)
        $profIds = array_filter(explode(',', $disc['professor_ids'] ?? ''));
        foreach ($profIds as $pid) {
            $pSlots = $data['profAvail'][$dow][(int)$pid] ?? [];
            foreach ($pSlots as [$ini, $fim]) {
                if ($ini < $slot['horario_fim'] && $fim > $slot['horario_inicio']) {
                    $score += 15;
                    $reasons[] = 'professor disponível no horário (+15)';
                    break 2;
                }
            }
        }

        // evitar_mesmo_dia: parceiro já alocado neste dia → penaliza
        foreach ($restricoes['evitar_mesmo_dia'] as $r) {
            $discs = $r['disciplinas'] ?? [];
            if (!in_array($cod, $discs)) continue;
            $parceiros = array_diff($discs, [$cod]);
            foreach ($parceiros as $parcCod) {
                if (!empty($codEmData[$date][$parcCod]) ||
                    in_array($parcCod, $discNoDia[$stId][$date] ?? [])
                ) {
                    $penalidade = $r['peso'] * 2;
                    $score -= $penalidade;
                    $reasons[] = "parceiro em evitar_mesmo_dia já no dia (−{$penalidade})";
                    break 2;
                }
            }
        }

        // Baixo rendimento não é mais penalizado automaticamente (somente se houver restrição explícita de evitar_mesmo_dia)

        // mesmo_dia_horario_diferente (soft)
        if (isset($restricoes['mesmo_dia_horario_diferente'])) {
            foreach ($restricoes['mesmo_dia_horario_diferente'] as $r) {
                if (($r['tipo'] ?? 'soft') === 'hard') continue;
                $codA  = $r['disciplina_codigo_a'] ?? '';
                $codB  = $r['disciplina_codigo_b'] ?? '';
                $scope = $r['scope'] ?? 'mesma_turma';
                $regra = $r['regra'] ?? 'deve';
                $peso  = max(1, (int)($r['peso'] ?? 5));

                if ($cod === $codA || $cod === $codB) {
                    $targetCod = ($cod === $codA) ? $codB : $codA;
                    foreach ($alocacoes as $aloc) {
                        if ($aloc['disciplina_codigo'] === $targetCod) {
                            $scopeMatch = false;
                            if ($scope === 'mesma_turma') {
                                if ((int)$aloc['somativa_turma_id'] === $stId) {
                                    $scopeMatch = true;
                                }
                            } else {
                                if ((int)$aloc['somativa_turma_id'] !== $stId) {
                                    $scopeMatch = true;
                                }
                            }

                            if ($scopeMatch) {
                                if ($regra === 'deve') {
                                    if ($aloc['data_prova'] === $date && (int)$aloc['slot_config_id'] !== (int)$slot['id']) {
                                        $bonus = $peso * 30;
                                        $score += $bonus;
                                        $reasons[] = "relação de dia/horário atendida (+{$bonus})";
                                    } else {
                                        $penalty = $peso * 30;
                                        $score -= $penalty;
                                        $reasons[] = "relação de dia/horário violada (−{$penalty})";
                                    }
                                } else { // nao_deve
                                    if ($aloc['data_prova'] !== $date) {
                                        $bonus = $peso * 30;
                                        $score += $bonus;
                                        $reasons[] = "relação de dia/horário atendida (+{$bonus})";
                                    } else {
                                        $penalty = $peso * 30;
                                        $score -= $penalty;
                                        $reasons[] = "relação de dia/horário violada (−{$penalty})";
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // preferir_primeiros_horarios (soft): bônus decrescente conforme a ordem do slot
        foreach ($restricoes['preferir_primeiros_horarios'] as $r) {
            $scope = $r['scope'] ?? 'todas';
            $applies = ($scope === 'todas') ||
                       ($scope === 'turma' && (int)($r['somativa_turma_id'] ?? 0) === $stId);
            if (!$applies) continue;

            $peso       = max(1, (int)($r['peso'] ?? 5));
            $totalSlots = max(1, count($data['slots']));
            $ordem      = (int)($slot['ordem'] ?? 1);
            $bonus      = $peso * ($totalSlots - $ordem + 1) * 15;
            $score     += $bonus;
            $reasons[]  = "primeiros horários slot {$ordem}/{$totalSlots} (+{$bonus})";
            break;
        }

        // professor_mesmo_dia_horario (soft): bonifica/penaliza por coincidência de slot com outras turmas do mesmo professor
        if (!empty($softProfDisc)) {
            $profIds = array_filter(explode(',', $disc['professor_ids'] ?? ''));
            foreach ($profIds as $pid) {
                $pid = (int)$pid;
                if (!isset($softProfDisc[$pid][$cod])) continue;
                $peso = $softProfDisc[$pid][$cod];

                foreach ($alocacoes as $aloc) {
                    if ($aloc['disciplina_codigo'] !== $cod) continue;
                    if ((int)$aloc['somativa_turma_id'] === $stId) continue;
                    $otherStIds = $profDiscTurmas[$pid][$cod] ?? [];
                    if (!in_array((int)$aloc['somativa_turma_id'], $otherStIds)) continue;

                    if ($aloc['data_prova'] === $date && (int)$aloc['slot_config_id'] === (int)$slot['id']) {
                        $bonus = $peso * 40;
                        $score += $bonus;
                        $reasons[] = "mesmo slot (professor {$pid}) (+{$bonus})";
                    } else {
                        $penalty = $peso * 40;
                        $score -= $penalty;
                        $reasons[] = "slot divergente (professor {$pid}) (−{$penalty})";
                    }
                    break 2; // um anchor encontrado é suficiente
                }
                break;
            }
        }

        // professor_mesmo_dia_horario (soft, todos): bonifica/penaliza por qualquer disc do prof em outra turma
        if (!empty($softProfAll)) {
            $profIds = array_filter(explode(',', $disc['professor_ids'] ?? ''));
            foreach ($profIds as $pid) {
                $pid = (int)$pid;
                if (!isset($softProfAll[$pid])) continue;
                $peso = $softProfAll[$pid];

                foreach ($alocacoes as $aloc) {
                    if ((int)$aloc['somativa_turma_id'] === $stId) continue;
                    $otherStIds = $profAllStIds[$pid] ?? [];
                    if (!in_array((int)$aloc['somativa_turma_id'], $otherStIds)) continue;
                    $otherProfIds = array_filter(explode(',', $aloc['disc_professor_ids'] ?? ''));
                    if (!empty($otherProfIds) && !in_array((string)$pid, $otherProfIds)) continue;

                    if ($aloc['data_prova'] === $date && (int)$aloc['slot_config_id'] === (int)$slot['id']) {
                        $bonus = $peso * 40;
                        $score += $bonus;
                        $reasons[] = "mesmo slot (professor {$pid}/todos) (+{$bonus})";
                    } else {
                        $penalty = $peso * 40;
                        $score -= $penalty;
                        $reasons[] = "slot divergente (professor {$pid}/todos) (−{$penalty})";
                    }
                    break 2;
                }
                break;
            }
        }

        // mesmo_dia_horario_grupo (soft): bonifica coincidência, penaliza divergência
        $curSdId = (int)$disc['som_disc_id'];
        foreach ($discInSoftGrupo[$curSdId] ?? [] as $gi) {
            $grupo = $softGrupos[$gi];
            foreach ($alocacoes as $aloc) {
                if ((int)$aloc['somativa_disciplina_id'] === $curSdId) continue;
                if (!in_array((int)$aloc['somativa_disciplina_id'], $grupo['sdIds'])) continue;

                if ($aloc['data_prova'] === $date && (int)$aloc['slot_config_id'] === (int)$slot['id']) {
                    $bonus = $grupo['peso'] * 40;
                    $score += $bonus;
                    $reasons[] = "grupo mesmo slot: coincide com {$aloc['disc_nome']} (+{$bonus})";
                } else {
                    $penalty = $grupo['peso'] * 40;
                    $score -= $penalty;
                    $reasons[] = "grupo mesmo slot: diverge de {$aloc['disc_nome']} (−{$penalty})";
                }
                break;
            }
        }

        // min_provas_por_dia: bonifica dias incompletos abaixo do mínimo;
        // penaliza iniciar dia vazio quando outros dias ainda estão abaixo do mínimo.
        foreach ($restricoes['min_provas_por_dia'] as $r) {
            $min   = max(1, (int)($r['min'] ?? 2));
            $rScope = $r['scope'] ?? 'todas';
            $applies = $rScope === 'todas' ||
                       ($rScope === 'turma' && (int)($r['somativa_turma_id'] ?? 0) === $stId);
            if (!$applies) continue;

            $peso         = max(1, (int)($r['peso'] ?? 5));
            $currentCount = count($discNoDia[$stId][$date] ?? []);

            if ($currentCount < $min) {
                // Dia abaixo do mínimo (incluindo vazio): bônus para preencher
                $bonus  = $peso * 20;
                $score += $bonus;
                $reasons[] = "min_provas_por_dia: dia abaixo do mínimo ({$currentCount}/{$min}) (+{$bonus})";
            } else {
                // Dia já atingiu o mínimo: penaliza se outros dias normais ainda estão abaixo
                $hasDayBelowMin = false;
                foreach ($data['dates'] as $d) {
                    if ($d === $date) continue;
                    if ($scData !== null && $d === $scData) continue; // ignora data de 2ª chamada
                    $n = count($discNoDia[$stId][$d] ?? []);
                    if ($n < $min) { $hasDayBelowMin = true; break; }
                }
                if ($hasDayBelowMin) {
                    $penalty = $peso * 15;
                    $score  -= $penalty;
                    $reasons[] = "min_provas_por_dia: dia já tem {$currentCount}>={$min} enquanto outros estão abaixo (−{$penalty})";
                }
            }
        }

        // Distribuição: bonifica datas iniciais levemente (espalha melhor)
        $dayIdx = array_search($date, $data['dates']);
        if ($dayIdx !== false) {
            $bonus = max(0, 8 - (int)($dayIdx * 0.4));
            $score += $bonus;
        }

        return [$score, $reasons];
    }

    // ──────────────────────────────────────────────────────────
    // Escolha de Professores
    // ──────────────────────────────────────────────────────────

    /**
     * Escolhe aplicador e volante.
     * Regra: professor disponível no horário → aplicador preferencial.
     * Se múltiplos disponíveis → segundo é volante.
     */
    private function chooseProfessores(
        array $disc,
        array $bestSlot,
        array $profAvail,
        array $turmaSchedule,
        int $turmaId,
        array $alocacoes
    ): array {
        $profIds = array_values(array_filter(explode(',', $disc['professor_ids'] ?? '')));
        if (empty($profIds)) return [null, null];

        $date = $bestSlot['date'];
        $slot = $bestSlot['slot'];
        $slotId = (int)$slot['id'];
        $dow  = (int)(new \DateTime($date))->format('N');

        // Encontra quem já está alocado como aplicador, volante ou aplicador NAAPI neste slot
        $busyAplicadores = [];
        $busyVolantes = [];
        $busyNaapi = [];
        foreach ($alocacoes as $aloc) {
            if ($aloc['data_prova'] === $date && (int)$aloc['slot_config_id'] === $slotId) {
                if ($aloc['aplicador_id']) {
                    $busyAplicadores[] = (int)$aloc['aplicador_id'];
                }
                if ($aloc['volante_id']) {
                    $busyVolantes[] = (int)$aloc['volante_id'];
                }
                if (!empty($aloc['naapi_aplicador_id'])) {
                    $busyNaapi[] = (int)$aloc['naapi_aplicador_id'];
                }
            }
        }

        // Se a regra é "professor_aplicador" (o professor da disciplina DEVE ser o aplicador e não há volante)
        if (!empty($disc['professor_aplicador'])) {
            $aplicador = null;
            $disciplineProfs = array_map('intval', $profIds);
            foreach ($disciplineProfs as $pid) {
                if (!in_array($pid, $busyAplicadores) && !in_array($pid, $busyVolantes) && !in_array($pid, $busyNaapi)) {
                    $aplicador = $pid;
                    break;
                }
            }
            if ($aplicador === null) {
                $aplicador = $disciplineProfs[0];
            }
            return [$aplicador, null];
        }

        // 1. Identificar professor regular da turma nesse slot
        $scheduledTeacher = null;
        $classes = $turmaSchedule[$turmaId][$dow] ?? [];
        foreach ($classes as $c) {
            if ($c['horario_inicio'] < $slot['horario_fim'] && $c['horario_fim'] > $slot['horario_inicio']) {
                $scheduledTeacher = (int)$c['professor_id'];
                break;
            }
        }

        // 2. Identificar professores da disciplina
        $disciplineProfs = array_map('intval', $profIds);

        // 3. Escolher o Volante primeiro (para garantir que o professor da disciplina seja volante se possível,
        // e seja o mesmo volante em todas as turmas no mesmo dia/horário)
        $volante = null;
        
        // Verifica se outra turma já alocou esta mesma disciplina neste slot e obteve um volante
        $sameDiscAloc = null;
        foreach ($alocacoes as $aloc) {
            if ($aloc['data_prova'] === $date && (int)$aloc['slot_config_id'] === $slotId && $aloc['disciplina_codigo'] === $disc['disciplina_codigo']) {
                $sameDiscAloc = $aloc;
                break;
            }
        }

        if ($sameDiscAloc !== null && !empty($sameDiscAloc['volante_id'])) {
            $volante = (int)$sameDiscAloc['volante_id'];
        } else {
            // Tenta escolher um professor da disciplina que não esteja ocupado como aplicador em outro lugar ou no NAAPI
            foreach ($disciplineProfs as $pid) {
                if (!in_array($pid, $busyAplicadores) && !in_array($pid, $busyNaapi)) {
                    $volante = $pid;
                    break;
                }
            }
        }

        // 4. Escolher o Aplicador
        $aplicador = null;
        // Candidato preferencial: o professor regular agendado da turma (desde que não seja o volante e não esteja ocupado ou no NAAPI)
        if ($scheduledTeacher !== null 
            && $scheduledTeacher !== $volante 
            && !in_array($scheduledTeacher, $busyAplicadores) 
            && !in_array($scheduledTeacher, $busyVolantes)
            && !in_array($scheduledTeacher, $busyNaapi)) {
            $aplicador = $scheduledTeacher;
        } else {
            // Fallback: tenta pegar outro professor da disciplina que não seja o volante e não esteja ocupado ou no NAAPI
            foreach ($disciplineProfs as $pid) {
                if ($pid !== $volante 
                    && !in_array($pid, $busyAplicadores) 
                    && !in_array($pid, $busyVolantes)
                    && !in_array($pid, $busyNaapi)) {
                    $aplicador = $pid;
                    break;
                }
            }
        }

        return [$aplicador, $volante];
    }

    /**
     * Escolhe o professor aplicador NAAPI para um slot.
     *
     * Regra: professor disponível no horário (tem aula neste dia da semana),
     * diferente de aplicadores/volantes já escalados para este slot.
     * Reutiliza o aplicador NAAPI se já houver um definido para o mesmo slot.
     * Retorna null se não for possível encontrar alguém disponível.
     */
    private function chooseNaapiAplicador(
        array $bestSlot,
        array $profAvail,
        ?int  $mainAplicadorId,
        ?int  $volanteId,
        array $alocacoes
    ): ?int {
        $date   = $bestSlot['date'];
        $slot   = $bestSlot['slot'];
        $slotId = (int)$slot['id'];
        $dow    = (int)(new \DateTime($date))->format('N');

        // 1. Verifica se já existe um aplicador NAAPI definido para este dia e horário
        foreach ($alocacoes as $aloc) {
            if ($aloc['data_prova'] === $date && (int)$aloc['slot_config_id'] === $slotId) {
                if (!empty($aloc['naapi_aplicador_id'])) {
                    $existingNaapi = (int)$aloc['naapi_aplicador_id'];
                    if ($existingNaapi !== $mainAplicadorId && $existingNaapi !== $volanteId) {
                        return $existingNaapi;
                    }
                }
            }
        }

        // 2. Caso contrário, busca um novo aplicador disponível
        $busyProfs = [];
        if ($mainAplicadorId) {
            $busyProfs[] = $mainAplicadorId;
        }
        if ($volanteId) {
            $busyProfs[] = $volanteId;
        }
        foreach ($alocacoes as $aloc) {
            if ($aloc['data_prova'] === $date && (int)$aloc['slot_config_id'] === $slotId) {
                if ($aloc['aplicador_id']) {
                    $busyProfs[] = (int)$aloc['aplicador_id'];
                }
                if ($aloc['volante_id']) {
                    $busyProfs[] = (int)$aloc['volante_id'];
                }
            }
        }

        // Itera professores disponíveis neste horário
        foreach ($profAvail[$dow] ?? [] as $profId => $slots) {
            if (in_array($profId, $busyProfs, true)) continue;

            foreach ($slots as [$ini, $fim]) {
                if ($ini < $slot['horario_fim'] && $fim > $slot['horario_inicio']) {
                    return $profId;
                }
            }
        }

        return null;
    }

    // ──────────────────────────────────────────────────────────
    // Fase 0 — Pré-alocação de grupos Hard/Todos por professor
    // ──────────────────────────────────────────────────────────

    /**
     * Aloca todas as disciplinas de cada professor com restrição Hard/Todos (profAllStIds)
     * de forma simultânea, no mesmo (date, slot), antes que o greedy principal ocupe os slots.
     *
     * Processos professores em ordem decrescente de número de turmas (mais restrito primeiro).
     * Retorna mapa [som_disc_id => true] das disciplinas pré-alocadas.
     *
     * @param array $datesNormais  Datas válidas excluindo segunda_chamada_data
     */
    private function preAllocateHardProfAll(
        array  $data,
        array  $restricoes,
        array  $hardProfAll,
        array  $profAllStIds,
        array  $datesNormais,
        array  &$alocacoes,
        array  &$ocupados,
        array  &$countDia,
        array  &$discNoDia,
        array  &$codEmData,
        array  $context
    ): array {
        $preAllocated = [];
        $maxPorDia    = (int)$data['som']['max_provas_por_dia'];

        // Ordena por número de turmas decrescente (mais restrito primeiro)
        $profGroups = [];
        foreach (array_keys($hardProfAll) as $pid) {
            $stIds = $profAllStIds[$pid] ?? [];
            if (count($stIds) >= 2) $profGroups[$pid] = $stIds;
        }
        uasort($profGroups, fn($a, $b) => count($b) - count($a));

        foreach ($profGroups as $pid => $stIds) {
            // Coleta (disc, turma) deste professor ainda não pré-alocados
            $items = [];
            foreach ($data['turmas'] as $turma) {
                $stId = (int)$turma['som_turma_id'];
                if (!in_array($stId, $stIds)) continue;
                foreach ($turma['disciplinas'] as $disc) {
                    $profIds = array_filter(explode(',', $disc['professor_ids'] ?? ''));
                    if (!in_array((string)$pid, $profIds)) continue;
                    $sdId = (int)$disc['som_disc_id'];
                    if (isset($preAllocated[$sdId])) continue;
                    $items[] = [
                        'stId'  => $stId,
                        'turma' => $turma,
                        'disc'  => $disc,
                        'sdId'  => $sdId,
                    ];
                }
            }
            if (empty($items)) continue;

            // Busca (date, slot) livre em TODAS as turmas simultaneamente
            $foundDate = null;
            $foundSlot = null;
            foreach ($datesNormais as $date) {
                foreach ($data['slots'] as $slot) {
                    $slotId  = (int)$slot['id'];
                    $canUse  = true;

                    foreach ($items as $item) {
                        $sId = $item['stId'];
                        if (!empty($ocupados[$sId][$date][$slotId])) { $canUse = false; break; }
                        if (($countDia[$sId][$date] ?? 0) >= $maxPorDia) { $canUse = false; break; }

                        [$blocked, $blockReason] = $this->checkHardBlocks(
                            $date, $slot, $item['disc'], $restricoes, $alocacoes, $sId,
                            $context['hardConstrainedHorario'], $context['hardConstrainedDia'],
                            $ocupados, $countDia, $context['discTurmas'], $data['slots'], $maxPorDia,
                            $context['hardProfDisc'], $context['profDiscTurmas'],
                            $context['hardGrupos'], $context['discInHardGrupo'], $context['sdIdLookup'],
                            $hardProfAll, $profAllStIds,
                            $context['evitarConflitoProf']
                        );
                        if ($blocked) {
                            $canUse = false;
                            break;
                        }
                    }
                    if (!$canUse) continue;

                    $foundDate = $date;
                    $foundSlot = $slot;
                    break 2;
                }
            }

            if ($foundDate === null) continue; // Nenhum slot viável → greedy principal tentará

            // Aloca todos os itens deste professor no slot encontrado
            foreach ($items as $item) {
                $sId    = $item['stId'];
                $disc   = $item['disc'];
                $turma  = $item['turma'];
                $sdId   = $item['sdId'];
                $cod    = $disc['disciplina_codigo'];
                $slotId = (int)$foundSlot['id'];

                [$aplicadorId, $volanteId] = $this->chooseProfessores(
                    $disc, ['date' => $foundDate, 'slot' => $foundSlot],
                    $data['profAvail'], $data['turmaSchedule'],
                    (int)$turma['turma_id'], $alocacoes
                );
                $naapiAplicadorId = null;
                if (!empty($data['som']['naapi_ambiente_id'])) {
                    $naapiAplicadorId = $this->chooseNaapiAplicador(
                        ['date' => $foundDate, 'slot' => $foundSlot],
                        $data['profAvail'], $aplicadorId, $volanteId, $alocacoes
                    );
                }

                $alocacoes[] = [
                    'somativa_id'            => (int)$data['som']['id'],
                    'somativa_turma_id'      => $sId,
                    'somativa_disciplina_id' => $sdId,
                    'disciplina_codigo'      => $cod,
                    'disc_nome'              => $disc['disc_nome'],
                    'disc_professor_ids'     => $disc['professor_ids'] ?? '',
                    'turma_desc'             => $turma['turma_desc'],
                    'data_prova'             => $foundDate,
                    'slot_config_id'         => $slotId,
                    'slot_label'             => $foundSlot['label'] ?? null,
                    'horario_inicio'         => $foundSlot['horario_inicio'],
                    'horario_fim'            => $foundSlot['horario_fim'],
                    'aplicador_id'           => $aplicadorId,
                    'volante_id'             => $volanteId,
                    'naapi_aplicador_id'     => $naapiAplicadorId,
                    'ambiente_id'            => $turma['turma_ambiente_id'] ?: null,
                    'tipo'                   => 'Normal',
                    'observacoes'            => null,
                    'justificativa'          => "Hard/Todos professor {$pid}",
                ];

                $ocupados[$sId][$foundDate][$slotId]     = true;
                $countDia[$sId][$foundDate]              = ($countDia[$sId][$foundDate] ?? 0) + 1;
                $discNoDia[$sId][$foundDate][]            = $cod;
                $codEmData[$foundDate][$cod]              = ($codEmData[$foundDate][$cod] ?? 0) + 1;
                $preAllocated[$sdId]                      = true;
            }
        }

        return $preAllocated;
    }
}
