<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

class ExperimentalScheduler extends \App\Services\SomativaScheduler {
    
    protected function buildAllocationQueue(
        array $data, array $restricoes,
        array $constrainedHorario, array $constrainedDia,
        array $hardProfDisc = [], array $softProfDisc = [],
        array $discInHardGrupo = [], array $discInSoftGrupo = [],
        array $hardProfAll = [], array $softProfAll = [],
        array $hardGrupos = []
    ): array {
        $queue = [];
        
        $hardGroupSizes = [];
        foreach ($hardGrupos as $gi => $g) {
            $hardGroupSizes[$gi] = count($g['sdIds']);
        }
        
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
                
                $profIds = array_filter(explode(',', $disc['professor_ids'] ?? ''));
                foreach ($profIds as $pid) {
                    $pid = (int)$pid;
                    if (isset($hardProfAll[$pid]))        { $priority += 160; break; }
                    if (isset($softProfAll[$pid]))        { $priority += 80;  break; }
                    if (isset($hardProfDisc[$pid][$cod])) { $priority += 160; break; }
                    if (isset($softProfDisc[$pid][$cod])) { $priority += 80;  break; }
                }
                
                $curSdId = (int)$disc['som_disc_id'];
                if (isset($discInHardGrupo[$curSdId])) {
                    $maxSize = 0;
                    foreach ($discInHardGrupo[$curSdId] as $gi) {
                        $maxSize = max($maxSize, $hardGroupSizes[$gi] ?? 0);
                    }
                    $priority += 100 * $maxSize;
                } elseif (isset($discInSoftGrupo[$curSdId])) {
                    $priority += 70;
                }
                
                $queue[] = [
                    'som_turma_id' => $stId,
                    'disciplina'   => $disc,
                    'turma'        => $turma,
                    'priority'     => $priority,
                ];
            }
        }
        
        usort($queue, fn($a, $b) => $b['priority'] - $a['priority']);
        
        if (!empty($hardGrupos)) {
            $groupedQueue = [];
            $visited = [];
            foreach ($queue as $item) {
                $sdId = (int)$item['disciplina']['som_disc_id'];
                if (isset($visited[$sdId])) continue;
                
                $groupedQueue[] = $item;
                $visited[$sdId] = true;
                
                if (isset($discInHardGrupo[$sdId])) {
                    foreach ($discInHardGrupo[$sdId] as $gi) {
                        if (!isset($hardGrupos[$gi])) continue;
                        $grupo = $hardGrupos[$gi];
                        foreach ($grupo['sdIds'] as $otherSdId) {
                            if (isset($visited[$otherSdId])) continue;
                            foreach ($queue as $qItem) {
                                if ((int)$qItem['disciplina']['som_disc_id'] === $otherSdId) {
                                    $groupedQueue[] = $qItem;
                                    $visited[$otherSdId] = true;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            $queue = $groupedQueue;
        }
        
        return $queue;
    }
    
    public function runExperiment(int $somativaId, int $instId) {
        $reflection = new ReflectionClass(\App\Services\SomativaScheduler::class);
        $loadDataMethod = $reflection->getMethod('loadData');
        $loadDataMethod->setAccessible(true);
        $data = $loadDataMethod->invoke($this, $somativaId, $instId);
        
        // Force the min_provas_por_dia restriction to be HARD!
        foreach ($data['restricoes'] as &$r) {
            if ($r['categoria'] === 'min_provas_por_dia') {
                $r['tipo'] = 'hard';
                echo "Forced min_provas_por_dia constraint to HARD.\n";
            }
        }
        unset($r);
        
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
        
        // theoreticalMinScore
        $theoreticalMinScore = 0;
        $totalDays = count($datesNormais);
        foreach ($data['turmas'] as $t) {
            $n = count($t['disciplinas']);
            if ($n === 0) continue;
            if ($n >= $totalDays) {
                $q = (int)$n / $totalDays;
                $r = $n % $totalDays;
                $theoreticalMinScore += $r * ($q + 1) * ($q + 1) + ($totalDays - $r) * $q * $q;
            } else {
                $theoreticalMinScore += $n * 1 * 1 + ($totalDays - $n) * 100;
            }
        }
        
        // Set in parent
        $refMinScore = new ReflectionProperty(\App\Services\SomativaScheduler::class, 'theoreticalMinScore');
        $refMinScore->setAccessible(true);
        $refMinScore->setValue($this, $theoreticalMinScore);
        
        $preAllocated = [];
        
        $queue = $this->buildAllocationQueue($data, $restricoes, $constrainedHorario, $constrainedDia, $hardProfDisc, $softProfDisc, $discInHardGrupo, $discInSoftGrupo, $hardProfAll, $softProfAll, $hardGrupos);
        
        $callsRef = 0;
        $bestAllocCount = 0;
        $bestAllocations = [];
        $solutionsRef = [];
        
        $solveBacktrackMethod = $reflection->getMethod('solveBacktrack');
        $solveBacktrackMethod->setAccessible(true);
        
        $success = $solveBacktrackMethod->invokeArgs($this, [
            0, $queue, $data, $restricoes, $datesNormais, $maxPorDia, $scData, $evitarConflitoProf,
            $constrainedHorario, $hardConstrainedHorario, $hardConstrainedDia,
            $hardProfDisc, $profDiscTurmas, $hardGrupos, $discInHardGrupo, $sdIdLookup, $hardProfAll, $profAllStIds,
            $softProfDisc, $softGrupos, $discInSoftGrupo, $softProfAll, $discTurmas,
            &$ocupados, &$countDia, &$discNoDia, &$codEmData, &$discEmData, &$alocacoes, &$preAllocated,
            &$callsRef, &$bestAllocCount, &$bestAllocations, &$solutionsRef
        ]);
        
        echo "=== EXPERIMENTAL RESULTS ===\n";
        echo "Total search steps (calls): {$callsRef}\n";
        echo "Solutions found: " . count($solutionsRef) . "\n";
        
        if (!empty($solutionsRef)) {
            $scores = [];
            $calculateSolutionScoreMethod = $reflection->getMethod('calculateSolutionScore');
            $calculateSolutionScoreMethod->setAccessible(true);
            
            foreach ($solutionsRef as $idx => $sol) {
                $score = $calculateSolutionScoreMethod->invoke($this, $sol, $data, $datesNormais);
                $scores[] = $score;
            }
            
            echo "Best score found with new queue priority: " . min($scores) . "\n";
            
            if (min($scores) == 123) {
                echo "SUCCESS! The algorithm successfully found the optimal score (123) even with min_provas_por_dia as HARD!\n";
            } else {
                echo "Failed to reach optimal score. Best is " . min($scores) . "\n";
            }
        } else {
            echo "No solutions found.\n";
            if (!empty($bestAllocations)) {
                echo "Best partial allocation count: " . count($bestAllocations) . " / " . count($queue) . "\n";
            }
        }
    }
}

try {
    $db = getDB();
    $somativa = $db->query("SELECT * FROM somativas ORDER BY id DESC LIMIT 1")->fetch();
    $exp = new ExperimentalScheduler();
    $exp->runExperiment($somativa['id'], $somativa['institution_id']);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
