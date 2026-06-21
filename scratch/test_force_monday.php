<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

class ForceScheduler extends \App\Services\SomativaScheduler {
    private $checkHardBlocksMethod;
    private $scoreSlotMethod;
    private $chooseProfessoresMethod;
    private $chooseNaapiAplicadorMethod;

    public function testForce($somativaId, $instId) {
        $loadDataMethod = new ReflectionMethod('App\Services\SomativaScheduler', 'loadData');
        $loadDataMethod->setAccessible(true);
        $data = $loadDataMethod->invoke($this, $somativaId, $instId);
        
        $this->checkHardBlocksMethod = new ReflectionMethod('App\Services\SomativaScheduler', 'checkHardBlocks');
        $this->checkHardBlocksMethod->setAccessible(true);
        
        $this->scoreSlotMethod = new ReflectionMethod('App\Services\SomativaScheduler', 'scoreSlot');
        $this->scoreSlotMethod->setAccessible(true);
        
        $this->chooseProfessoresMethod = new ReflectionMethod('App\Services\SomativaScheduler', 'chooseProfessores');
        $this->chooseProfessoresMethod->setAccessible(true);
        
        $this->chooseNaapiAplicadorMethod = new ReflectionMethod('App\Services\SomativaScheduler', 'chooseNaapiAplicador');
        $this->chooseNaapiAplicadorMethod->setAccessible(true);
        
        $maxPorDia = (int)$data['som']['max_provas_por_dia'];
        $scData    = $data['scData'];
        $evitarConflitoProf = !empty($data['som']['evitar_conflito_professor']);

        $ocupados    = [];  
        $countDia    = [];  
        $discNoDia   = [];  
        $discEmData  = [];  
        $codEmData   = [];  
        $alocacoes   = [];

        $indexRestricoesMethod = new ReflectionMethod('App\Services\SomativaScheduler', 'indexRestricoes');
        $indexRestricoesMethod->setAccessible(true);
        $restricoes = $indexRestricoesMethod->invoke($this, $data['restricoes']);

        $constrainedHorario = [];
        $hardConstrainedHorario = [];
        foreach ($restricoes['mesmo_horario_turmas'] as $r) {
            $scope = $r['scope'] ?? 'disciplina';
            $isHard = ($r['tipo'] ?? 'soft') === 'hard';
            if ($scope === 'disciplina') {
                $c = $r['disciplina_codigo'] ?? '';
                if ($c) {
                    $constrainedHorario[$c] = true;
                    if ($isHard) $hardConstrainedHorario[$c] = true;
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
                if ($isHard) $hardConstrainedDia[$c] = true;
            }
        }

        $hardProfDisc = [];
        $softProfDisc = [];
        $hardProfAll  = [];
        $softProfAll  = [];
        $profDiscTurmas = $data['profDiscTurmas'] ?? [];
        $profAllStIds = [];
        foreach ($profDiscTurmas as $pid => $discMap) {
            $stIds = array_unique(array_merge(...array_values($discMap)));
            if (count($stIds) >= 2) $profAllStIds[$pid] = $stIds;
        }

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

        $datesNormais = $data['dates'];
        $discTurmas = [];
        foreach ($data['turmas'] as $t) {
            $tId = (int)$t['som_turma_id'];
            foreach ($t['disciplinas'] as $d) {
                $discTurmas[$d['disciplina_codigo']][] = $tId;
            }
        }

        $preAllocated = [];
        $bestAllocCount = 0;
        $bestAllocations = [];
        $calls = 0;
        $solutions = [];
        
        $buildAllocationQueueMethod = new ReflectionMethod('App\Services\SomativaScheduler', 'buildAllocationQueue');
        $buildAllocationQueueMethod->setAccessible(true);
        $queue = $buildAllocationQueueMethod->invokeArgs($this, [
            $data, $restricoes, $constrainedHorario, $constrainedDia, $hardProfDisc, $softProfDisc, $discInHardGrupo, $discInSoftGrupo, $hardProfAll, $softProfAll, $hardGrupos
        ]);

        $success = $this->solveBacktrackForce(
            0, $queue, $data, $restricoes, $datesNormais, $maxPorDia, $scData, $evitarConflitoProf,
            $constrainedHorario, $hardConstrainedHorario, $hardConstrainedDia,
            $hardProfDisc, $profDiscTurmas, $hardGrupos, $discInHardGrupo, $sdIdLookup, $hardProfAll, $profAllStIds,
            $softProfDisc, $softGrupos, $discInSoftGrupo, $softProfAll, $discTurmas,
            $ocupados, $countDia, $discNoDia, $codEmData, $discEmData, $alocacoes, $preAllocated,
            $calls, $bestAllocCount, $bestAllocations, $solutions
        );

        echo "Total solutions found with Monday constraint: " . count($solutions) . "\n";
        if (empty($solutions)) {
            echo "Failed to find any solution when forcing Portuguese 2ª to Monday.\n";
            echo "Best progress: allocated " . $bestAllocCount . " out of " . count($queue) . " subjects.\n";
            // Let's see what was not allocated
            $allocatedSdIds = array_column($bestAllocations, 'somativa_disciplina_id');
            foreach ($queue as $item) {
                if (!in_array($item['disciplina']['som_disc_id'], $allocatedSdIds)) {
                    echo "  Could not allocate: " . $item['disciplina']['disc_nome'] . " (" . $item['turma']['turma_desc'] . ")\n";
                }
            }
        } else {
            // Find best
            $bestSolution = null;
            $bestScore = 99999999;
            foreach ($solutions as $sol) {
                $turmaDays = [];
                foreach ($sol as $aloc) {
                    $tId = (int)$aloc['somativa_turma_id'];
                    $d = $aloc['data_prova'];
                    $turmaDays[$tId][$d] = ($turmaDays[$tId][$d] ?? 0) + 1;
                }
                $score = 0;
                foreach ($data['turmas'] as $t) {
                    $tId = (int)$t['som_turma_id'];
                    if (count($t['disciplinas']) === 0) continue;
                    foreach ($datesNormais as $date) {
                        $count = $turmaDays[$tId][$date] ?? 0;
                        $score += $count * $count;
                        if ($count === 0) $score += 100;
                    }
                }
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestSolution = $sol;
                }
            }
            echo "Best Score with Monday constraint: " . $bestScore . "\n";
            
            $turmaLookup = [];
            foreach ($data['turmas'] as $t) {
                $turmaLookup[(int)$t['som_turma_id']] = $t['course_name'] . " - " . $t['turma_desc'];
            }
            $turmaAlloc = [];
            foreach ($bestSolution as $aloc) {
                $tKey = $turmaLookup[(int)$aloc['somativa_turma_id']] ?? $aloc['turma_desc'];
                $d = $aloc['data_prova'];
                $turmaAlloc[$tKey][$d][] = $aloc['disc_nome'] . " (Slot " . $aloc['slot_config_id'] . ")";
            }
            ksort($turmaAlloc);
            foreach ($turmaAlloc as $tKey => $days) {
                echo "\nTURMA: $tKey\n";
                ksort($days);
                foreach ($datesNormais as $date) {
                    $dayOfWeek = (new DateTime($date))->format('l');
                    $exams = $days[$date] ?? [];
                    echo "  $date ($dayOfWeek): " . (empty($exams) ? "SEM PROVA" : implode(", ", $exams)) . "\n";
                }
            }
        }
    }

    private function solveBacktrackForce(
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
        array &$bestAllocations,
        array &$solutions
    ): bool {
        $calls++;
        if ($calls > 300000) {
            return false;
        }

        if (!empty($solutions) && $calls > 150000) {
            return true;
        }

        if ($queueIdx > $bestAllocCount) {
            $bestAllocCount = $queueIdx;
            $bestAllocations = $alocacoes;
        }

        if ($queueIdx >= count($queue)) {
            $solutions[] = $alocacoes;
            if (count($solutions) >= 1000) {
                return true;
            }
            return false;
        }

        $item    = $queue[$queueIdx];
        $stId    = $item['som_turma_id'];
        $disc    = $item['disciplina'];
        $turma   = $item['turma'];
        $sdId    = (int)$disc['som_disc_id'];
        $discCod = $disc['disciplina_codigo'];

        if (isset($preAllocated[$sdId])) {
            return $this->solveBacktrackForce(
                $queueIdx + 1, $queue, $data, $restricoes, $datesNormais, $maxPorDia, $scData, $evitarConflitoProf,
                $constrainedHorario, $hardConstrainedHorario, $hardConstrainedDia,
                $hardProfDisc, $profDiscTurmas, $hardGrupos, $discInHardGrupo, $sdIdLookup, $hardProfAll, $profAllStIds,
                $softProfDisc, $softGrupos, $discInSoftGrupo, $softProfAll, $discTurmas,
                $ocupados, $countDia, $discNoDia, $codEmData, $discEmData, $alocacoes, $preAllocated,
                $calls, $bestAllocCount, $bestAllocations, $solutions
            );
        }

        $candidates = [];

        foreach ($datesNormais as $date) {
            // Force Portuguese 2ª (8FG.165) to Monday (2026-07-13)
            if ($discCod === '8FG.165' && $date !== '2026-07-13') {
                continue;
            }

            if (($countDia[$stId][$date] ?? 0) >= $maxPorDia) {
                continue;
            }

            foreach ($data['slots'] as $slot) {
                $slotId = (int)$slot['id'];
                if (!empty($ocupados[$stId][$date][$slotId])) continue;

                // Force Portuguese 2ª (8FG.165) to Monday Slot 8 (to keep it separate from Sistemas Embarcados if needed)
                if ($discCod === '8FG.165' && $slotId !== 8) {
                    continue;
                }

                $blockedArgs = [
                    $date, $slot, $disc, $restricoes, $alocacoes, $stId,
                    $hardConstrainedHorario, $hardConstrainedDia,
                    $ocupados, $countDia, $discTurmas, $data['slots'], $maxPorDia,
                    $hardProfDisc, $profDiscTurmas,
                    $hardGrupos, $discInHardGrupo, $sdIdLookup,
                    $hardProfAll, $profAllStIds,
                    $evitarConflitoProf
                ];
                [$blocked, $blockReason] = $this->checkHardBlocksMethod->invokeArgs($this, $blockedArgs);
                if ($blocked) continue;

                $scoreArgs = [
                    $date, $slot, $disc, $turma, $restricoes, $data,
                    $discNoDia, $codEmData, $stId, $scData,
                    $constrainedHorario, $alocacoes,
                    $softProfDisc, $profDiscTurmas,
                    $softGrupos, $discInSoftGrupo,
                    $softProfAll, $profAllStIds
                ];
                [$score, $reasons] = $this->scoreSlotMethod->invokeArgs($this, $scoreArgs);

                $candidates[] = [
                    'date'          => $date,
                    'slot'          => $slot,
                    'score'         => $score,
                    'justification' => implode('; ', $reasons),
                ];
            }
        }

        usort($candidates, fn($a, $b) => $b['score'] - $a['score']);

        foreach ($candidates as $cand) {
            $date   = $cand['date'];
            $slot   = $cand['slot'];
            $slotId = (int)$slot['id'];

            $ocupados[$stId][$date][$slotId] = true;
            $countDia[$stId][$date] = ($countDia[$stId][$date] ?? 0) + 1;
            $discNoDia[$stId][$date][] = $discCod;
            $codEmData[$date][$discCod] = ($codEmData[$date][$discCod] ?? 0) + 1;
            $discEmData[$stId][$sdId] = $date;

            $chooseProfsArgs = [
                $disc, ['date' => $date, 'slot' => $slot], $data['profAvail'], $data['turmaSchedule'], (int)$turma['turma_id'], $alocacoes
            ];
            [$aplicadorId, $volanteId] = $this->chooseProfessoresMethod->invokeArgs($this, $chooseProfsArgs);
            $naapiAplicadorId = null;
            if (!empty($data['som']['naapi_ambiente_id'])) {
                $chooseNaapiArgs = [
                    ['date' => $date, 'slot' => $slot], $data['profAvail'], $aplicadorId, $volanteId, $alocacoes
                ];
                $naapiAplicadorId = $this->chooseNaapiAplicadorMethod->invokeArgs($this, $chooseNaapiArgs);
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

            if ($this->solveBacktrackForce(
                $queueIdx + 1, $queue, $data, $restricoes, $datesNormais, $maxPorDia, $scData, $evitarConflitoProf,
                $constrainedHorario, $hardConstrainedHorario, $hardConstrainedDia,
                $hardProfDisc, $profDiscTurmas, $hardGrupos, $discInHardGrupo, $sdIdLookup, $hardProfAll, $profAllStIds,
                $softProfDisc, $softGrupos, $discInSoftGrupo, $softProfAll, $discTurmas,
                $ocupados, $countDia, $discNoDia, $codEmData, $discEmData, $alocacoes, $preAllocated,
                $calls, $bestAllocCount, $bestAllocations, $solutions
            )) {
                return true;
            }

            array_pop($alocacoes);
            unset($ocupados[$stId][$date][$slotId]);
            $countDia[$stId][$date]--;
            if ($countDia[$stId][$date] <= 0) unset($countDia[$stId][$date]);

            $idx = array_search($discCod, $discNoDia[$stId][$date]);
            if ($idx !== false) {
                unset($discNoDia[$stId][$date][$idx]);
                $discNoDia[$stId][$date] = array_values($discNoDia[$stId][$date]);
                if (empty($discNoDia[$stId][$date])) unset($discNoDia[$stId][$date]);
            }

            $codEmData[$date][$discCod]--;
            if ($codEmData[$date][$discCod] <= 0) unset($codEmData[$date][$discCod]);
            if (empty($codEmData[$date])) unset($codEmData[$date]);

            unset($discEmData[$stId][$sdId]);
        }

        return false;
    }
}

$fs = new ForceScheduler();
$fs->testForce(1, 1);
