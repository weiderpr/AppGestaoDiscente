<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

class DiagnosticScheduler extends \App\Services\SomativaScheduler {
    // We override solveBacktrack to count calls and solutions and record their scores!
    private $myCalls = 0;
    private $mySolutions = [];
    
    public function runCustomSearch(int $somativaId, int $instId) {
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
        
        $preAllocated = [];
        if (!empty($hardProfAll)) {
            $preAllocateHardProfAllMethod = $reflection->getMethod('preAllocateHardProfAll');
            $preAllocateHardProfAllMethod->setAccessible(true);
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
            $preAllocated = $preAllocateHardProfAllMethod->invokeArgs($this, [
                $data, $restricoes, $hardProfAll, $profAllStIds, $datesNormais,
                &$alocacoes, &$ocupados, &$countDia, &$discNoDia, &$codEmData,
                $context
            ]);
        }
        
        $buildAllocationQueueMethod = $reflection->getMethod('buildAllocationQueue');
        $buildAllocationQueueMethod->setAccessible(true);
        $queue = $buildAllocationQueueMethod->invoke($this, $data, $restricoes, $constrainedHorario, $constrainedDia, $hardProfDisc, $softProfDisc, $discInHardGrupo, $discInSoftGrupo, $hardProfAll, $softProfAll, $hardGrupos);
        
        $bestAllocCount = 0;
        $bestAllocations = [];
        
        $solveBacktrackMethod = $reflection->getMethod('solveBacktrack');
        $solveBacktrackMethod->setAccessible(true);
        
        // We set theoreticalMinScore in this object so the backtrack solver uses the correct value!
        $refMinScore = new ReflectionProperty(\App\Services\SomativaScheduler::class, 'theoreticalMinScore');
        $refMinScore->setAccessible(true);
        $refMinScore->setValue($this, $theoreticalMinScore);
        
        $callsRef = 0;
        $solutionsRef = [];
        $success = $solveBacktrackMethod->invokeArgs($this, [
            0, $queue, $data, $restricoes, $datesNormais, $maxPorDia, $scData, $evitarConflitoProf,
            $constrainedHorario, $hardConstrainedHorario, $hardConstrainedDia,
            $hardProfDisc, $profDiscTurmas, $hardGrupos, $discInHardGrupo, $sdIdLookup, $hardProfAll, $profAllStIds,
            $softProfDisc, $softGrupos, $discInSoftGrupo, $softProfAll, $discTurmas,
            &$ocupados, &$countDia, &$discNoDia, &$codEmData, &$discEmData, &$alocacoes, &$preAllocated,
            &$callsRef, &$bestAllocCount, &$bestAllocations, &$solutionsRef
        ]);
        
        echo "=== SEARCH METRICS ===\n";
        echo "Total search steps (calls): {$callsRef}\n";
        echo "Number of complete solutions found: " . count($solutionsRef) . "\n";
        
        if (!empty($solutionsRef)) {
            $scores = [];
            $calculateSolutionScoreMethod = $reflection->getMethod('calculateSolutionScore');
            $calculateSolutionScoreMethod->setAccessible(true);
            
            foreach ($solutionsRef as $idx => $sol) {
                $score = $calculateSolutionScoreMethod->invoke($this, $sol, $data, $datesNormais);
                $scores[] = $score;
            }
            
            // Count occurrences of each score
            $scoreCounts = array_count_values($scores);
            ksort($scoreCounts);
            echo "Scores distribution of solutions found:\n";
            foreach ($scoreCounts as $score => $cnt) {
                echo "  - Score {$score}: {$cnt} solutions\n";
            }
            
            echo "Best score found: " . min($scores) . "\n";
        } else {
            echo "No complete solutions found!\n";
        }
    }
}

try {
    $db = getDB();
    $somativa = $db->query("SELECT * FROM somativas ORDER BY id DESC LIMIT 1")->fetch();
    $diag = new DiagnosticScheduler();
    $diag->runCustomSearch($somativa['id'], $somativa['institution_id']);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
