<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

class DiagnosticScheduler extends \App\Services\SomativaScheduler {
    public function traceBiologia(int $somativaId, int $instId) {
        $reflection = new ReflectionClass(\App\Services\SomativaScheduler::class);
        $loadDataMethod = $reflection->getMethod('loadData');
        $loadDataMethod->setAccessible(true);
        $data = $loadDataMethod->invoke($this, $somativaId, $instId);
        
        $datesNormais = $data['dates'];
        if ($data['scData'] !== null) {
            $datesNormais = array_filter($data['dates'], fn($d) => $d !== $data['scData']);
            $datesNormais = array_values($datesNormais);
        }
        
        $maxPorDia = (int)$data['som']['max_provas_por_dia'];
        $scData    = $data['scData'];
        $evitarConflitoProf = !empty($data['som']['evitar_conflito_professor']);
        
        $ocupados    = [];
        $countDia    = [];
        $discNoDia   = [];
        $discEmData  = [];
        $codEmData   = [];
        $alocacoes   = [];
        
        $indexRestricoesMethod = $reflection->getMethod('indexRestricoes');
        $indexRestricoesMethod->setAccessible(true);
        $restricoes = $indexRestricoesMethod->invoke($this, $data['restricoes']);
        
        // build constrained logic
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
            } else if ($scope === 'turma') {
                $tId = (int)($r['turma_id'] ?? 0);
                foreach ($data['turmas'] as $t) {
                    if ((int)$t['turma_id'] === $tId) {
                        foreach ($t['disciplinas'] as $d) {
                            $c = $d['disciplina_codigo'];
                            $constrainedHorario[$c] = true;
                            if ($isHard) $hardConstrainedHorario[$c] = true;
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
        foreach ($restricoes['professor_mesmo_dia_horario'] as $r) {
            $isHard  = ($r['tipo'] ?? 'soft') === 'hard';
            $peso    = max(1, (int)($r['peso'] ?? 5));
            $isTodos = !empty($r['todos']);
            if ($isTodos) {
                foreach (array_keys($profAllStIds) as $profId) {
                    if ($isHard) $hardProfAll[$profId] = true;
                    else         $softProfAll[$profId] = max($softProfAll[$profId] ?? 0, $peso);
                }
            } else {
                $profId = (int)$r['professor_id'] ?? 0;
                if (!$profId || !isset($profDiscTurmas[$profId])) continue;
                foreach ($profDiscTurmas[$profId] as $discCod => $stIds) {
                    if (count($stIds) < 2) continue;
                    if ($isHard) $hardProfDisc[$profId][$discCod] = true;
                    else         $softProfDisc[$profId][$discCod] = $peso;
                }
            }
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
        
        $hardGrupos = [];
        $softGrupos = [];
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
        
        $discTurmas = [];
        foreach ($data['turmas'] as $t) {
            $tId = (int)$t['som_turma_id'];
            foreach ($t['disciplinas'] as $d) {
                $discTurmas[$d['disciplina_codigo']][] = $tId;
            }
        }
        
        $preAllocated = [];
        
        $buildAllocationQueueMethod = $reflection->getMethod('buildAllocationQueue');
        $buildAllocationQueueMethod->setAccessible(true);
        $queue = $buildAllocationQueueMethod->invoke($this, $data, $restricoes, $constrainedHorario, $constrainedDia, $hardProfDisc, $softProfDisc, $discInHardGrupo, $discInSoftGrupo, $hardProfAll, $softProfAll, $hardGrupos);
        
        // Find the index of Biologia in the queue
        $biologiaIndices = [];
        foreach ($queue as $idx => $item) {
            if ($item['disciplina']['disciplina_codigo'] === '8FG.173' || $item['disciplina']['disciplina_codigo'] === '8FG.174') {
                $biologiaIndices[] = $idx;
            }
        }
        
        echo "Biologia indices in the queue: " . implode(', ', $biologiaIndices) . "\n";
        
        // We will mock a solveBacktrack run that stops right when the first Biologia is about to be evaluated
        // and prints candidate slots and checkHardBlocks outcomes!
        
        $firstBioIdx = min($biologiaIndices);
        echo "First Biologia index: {$firstBioIdx}\n";
        
        // We run a simulation to see what the state of $ocupados, $countDia, $alocacoes is at step $firstBioIdx
        // by running solveBacktrack normally but tracing when $queueIdx === $firstBioIdx
        
        $checkHardBlocksMethod = $reflection->getMethod('checkHardBlocks');
        $checkHardBlocksMethod->setAccessible(true);
        $scoreSlotMethod = $reflection->getMethod('scoreSlot');
        $scoreSlotMethod->setAccessible(true);
        
        $traceFn = function($queueIdx, $alocacoes, $ocupados, $countDia, $discNoDia, $codEmData) use (
            $queue, $firstBioIdx, $data, $restricoes, $datesNormais, $maxPorDia, $evitarConflitoProf,
            $hardConstrainedHorario, $hardConstrainedDia, $hardProfDisc, $profDiscTurmas, $hardGrupos, $discInHardGrupo, $sdIdLookup, $hardProfAll, $profAllStIds,
            $constrainedHorario, $discTurmas, $checkHardBlocksMethod, $scoreSlotMethod
        ) {
            if ($queueIdx === $firstBioIdx) {
                echo "\n=== TRACING DECISION FOR FIRST BIOLOGIA IN QUEUE ===\n";
                $item = $queue[$queueIdx];
                $stId = $item['som_turma_id'];
                $disc = $item['disciplina'];
                
                echo "Turma: {$item['turma']['turma_desc']} (som_turma_id: {$stId}) | Disc: {$disc['disc_nome']}\n";
                echo "Current allocations count in trace state: " . count($alocacoes) . "\n";
                foreach ($alocacoes as $a) {
                    echo "  * {$a['turma_desc']} | {$a['data_prova']} | {$a['slot_label']} | {$a['disc_nome']}\n";
                }
                
                echo "\nEvaluating candidate slots:\n";
                foreach ($datesNormais as $date) {
                    foreach ($data['slots'] as $slot) {
                        $slotId = (int)$slot['id'];
                        
                        // Check hard blocks
                        [$blocked, $reason] = $checkHardBlocksMethod->invokeArgs($this, [
                            $date, $slot, $disc, $restricoes, $alocacoes, $stId,
                            $hardConstrainedHorario, $hardConstrainedDia,
                            $ocupados, $countDia, $discTurmas, $data['slots'], $maxPorDia,
                            $hardProfDisc, $profDiscTurmas,
                            $hardGrupos, $discInHardGrupo, $sdIdLookup,
                            $hardProfAll, $profAllStIds,
                            $evitarConflitoProf
                        ]);
                        
                        if ($blocked) {
                            echo "  - Date: {$date} | Slot: {$slot['label']} -> BLOCKED: {$reason}\n";
                        } else {
                            [$score, $reasons] = $scoreSlotMethod->invokeArgs($this, [
                                $date, $slot, $disc, $item['turma'], $restricoes, $data,
                                $discNoDia, $codEmData, $stId, $data['scData'],
                                $constrainedHorario, $alocacoes
                            ]);
                            echo "  - Date: {$date} | Slot: {$slot['label']} -> FREE | Score: {$score}\n";
                        }
                    }
                }
                exit; // Stop execution after printing
            }
        };
        
        // We run a custom solveBacktrack that calls $traceFn
        $this->solveBacktrackWithTrace(
            0, $queue, $data, $restricoes, $datesNormais, $maxPorDia, $scData, $evitarConflitoProf,
            $constrainedHorario, $hardConstrainedHorario, $hardConstrainedDia,
            $hardProfDisc, $profDiscTurmas, $hardGrupos, $discInHardGrupo, $sdIdLookup, $hardProfAll, $profAllStIds,
            $softProfDisc, $softGrupos, $discInSoftGrupo, $softProfAll, $discTurmas,
            $ocupados, $countDia, $discNoDia, $codEmData, $discEmData, $alocacoes, $preAllocated,
            $traceFn
        );
    }
    
    private function solveBacktrackWithTrace(
        int $queueIdx, array $queue, array $data, array $restricoes, array $datesNormais, int $maxPorDia, ?string $scData, bool $evitarConflitoProf,
        array $constrainedHorario, array $hardConstrainedHorario, array $hardConstrainedDia,
        array $hardProfDisc, array $profDiscTurmas, array $hardGrupos, array $discInHardGrupo, array $sdIdLookup, array $hardProfAll, array $profAllStIds,
        array $softProfDisc, array $softGrupos, array $discInSoftGrupo, array $softProfAll, array $discTurmas,
        array &$ocupados, array &$countDia, array &$discNoDia, array &$codEmData, array &$discEmData, array &$alocacoes, array &$preAllocated,
        callable $traceFn
    ) {
        $traceFn($queueIdx, $alocacoes, $ocupados, $countDia, $discNoDia, $codEmData);
        
        if ($queueIdx >= count($queue)) {
            return true;
        }
        
        $item    = $queue[$queueIdx];
        $stId    = $item['som_turma_id'];
        $disc    = $item['disciplina'];
        $turma   = $item['turma'];
        $sdId    = (int)$disc['som_disc_id'];
        $discCod = $disc['disciplina_codigo'];
        
        $reflection = new ReflectionClass(\App\Services\SomativaScheduler::class);
        $checkHardBlocksMethod = $reflection->getMethod('checkHardBlocks');
        $checkHardBlocksMethod->setAccessible(true);
        $scoreSlotMethod = $reflection->getMethod('scoreSlot');
        $scoreSlotMethod->setAccessible(true);
        
        $candidates = [];
        foreach ($datesNormais as $date) {
            if (($countDia[$stId][$date] ?? 0) >= $maxPorDia) continue;
            
            foreach ($data['slots'] as $slot) {
                $slotId = (int)$slot['id'];
                if (!empty($ocupados[$stId][$date][$slotId])) continue;
                
                [$blocked, $blockReason] = $checkHardBlocksMethod->invokeArgs($this, [
                    $date, $slot, $disc, $restricoes, $alocacoes, $stId,
                    $hardConstrainedHorario, $hardConstrainedDia,
                    $ocupados, $countDia, $discTurmas, $data['slots'], $maxPorDia,
                    $hardProfDisc, $profDiscTurmas,
                    $hardGrupos, $discInHardGrupo, $sdIdLookup,
                    $hardProfAll, $profAllStIds,
                    $evitarConflitoProf
                ]);
                if ($blocked) continue;
                
                [$score, $reasons] = $scoreSlotMethod->invokeArgs($this, [
                    $date, $slot, $disc, $turma, $restricoes, $data,
                    $discNoDia, $codEmData, $stId, $scData,
                    $constrainedHorario, $alocacoes
                ]);
                
                $candidates[] = [
                    'date' => $date,
                    'slot' => $slot,
                    'score' => $score
                ];
            }
        }
        
        usort($candidates, fn($a, $b) => $b['score'] - $a['score']);
        
        foreach ($candidates as $cand) {
            $date = $cand['date'];
            $slot = $cand['slot'];
            $slotId = (int)$slot['id'];
            
            $ocupados[$stId][$date][$slotId] = true;
            $countDia[$stId][$date] = ($countDia[$stId][$date] ?? 0) + 1;
            $discNoDia[$stId][$date][] = $discCod;
            $codEmData[$date][$discCod] = ($codEmData[$date][$discCod] ?? 0) + 1;
            $discEmData[$stId][$sdId] = $date;
            
            // Proctor choices
            $chooseProfessoresMethod = $reflection->getMethod('chooseProfessores');
            $chooseProfessoresMethod->setAccessible(true);
            [$aplicadorId, $volanteId] = $chooseProfessoresMethod->invokeArgs($this, [
                $disc, ['date' => $date, 'slot' => $slot], $data['profAvail'], $data['turmaSchedule'], (int)$turma['turma_id'], $alocacoes
            ]);
            $naapiAplicadorId = null;
            
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
                'aplicador_id'           => $aplicadorId,
                'volante_id'             => $volanteId,
                'naapi_aplicador_id'     => $naapiAplicadorId,
                'tipo'                   => 'Normal',
            ];
            $alocacoes[] = $aloc;
            
            if ($this->solveBacktrackWithTrace(
                $queueIdx + 1, $queue, $data, $restricoes, $datesNormais, $maxPorDia, $scData, $evitarConflitoProf,
                $constrainedHorario, $hardConstrainedHorario, $hardConstrainedDia,
                $hardProfDisc, $profDiscTurmas, $hardGrupos, $discInHardGrupo, $sdIdLookup, $hardProfAll, $profAllStIds,
                $softProfDisc, $softGrupos, $discInSoftGrupo, $softProfAll, $discTurmas,
                $ocupados, $countDia, $discNoDia, $codEmData, $discEmData, $alocacoes, $preAllocated,
                $traceFn
            )) {
                return true;
            }
            
            array_pop($alocacoes);
            unset($ocupados[$stId][$date][$slotId]);
            $countDia[$stId][$date]--;
            $idx = array_search($discCod, $discNoDia[$stId][$date]);
            if ($idx !== false) {
                unset($discNoDia[$stId][$date][$idx]);
                $discNoDia[$stId][$date] = array_values($discNoDia[$stId][$date]);
            }
            $codEmData[$date][$discCod]--;
            unset($discEmData[$stId][$sdId]);
        }
        return false;
    }
}

try {
    $db = getDB();
    $somativa = $db->query("SELECT * FROM somativas ORDER BY id DESC LIMIT 1")->fetch();
    $diag = new DiagnosticScheduler();
    $diag->traceBiologia($somativa['id'], $somativa['institution_id']);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
