<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

class CountScheduler extends \App\Services\SomativaScheduler {
    public function getSolutionsCount($somativaId, $instId) {
        $loadDataMethod = new ReflectionMethod('App\Services\SomativaScheduler', 'loadData');
        $loadDataMethod->setAccessible(true);
        $data = $loadDataMethod->invoke($this, $somativaId, $instId);
        
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
        
        $buildAllocationQueueMethod = new ReflectionMethod('App\Services\SomativaScheduler', 'buildAllocationQueue');
        $buildAllocationQueueMethod->setAccessible(true);
        $queue = $buildAllocationQueueMethod->invokeArgs($this, [
            $data, $restricoes, $constrainedHorario, $constrainedDia, $hardProfDisc, $softProfDisc, $discInHardGrupo, $discInSoftGrupo, $hardProfAll, $softProfAll, $hardGrupos
        ]);

        $bestAllocCount = 0;
        $bestAllocations = [];
        $calls = 0;
        $solutions = [];

        $solveBacktrackMethod = new ReflectionMethod('App\Services\SomativaScheduler', 'solveBacktrack');
        $solveBacktrackMethod->setAccessible(true);

        $success = $solveBacktrackMethod->invokeArgs($this, [
            0, $queue, $data, $restricoes, $datesNormais, $maxPorDia, $scData, $evitarConflitoProf,
            $constrainedHorario, $hardConstrainedHorario, $hardConstrainedDia,
            $hardProfDisc, $profDiscTurmas, $hardGrupos, $discInHardGrupo, $sdIdLookup, $hardProfAll, $profAllStIds,
            $softProfDisc, $softGrupos, $discInSoftGrupo, $softProfAll, $discTurmas,
            &$ocupados, &$countDia, &$discNoDia, &$codEmData, &$discEmData, &$alocacoes, &$preAllocated,
            &$calls, &$bestAllocCount, &$bestAllocations, &$solutions
        ]);

        echo "Total solutions found: " . count($solutions) . "\n";
        echo "Total calls: " . $calls . "\n";
        
        $scores = [];
        $first327Index = -1;
        foreach ($solutions as $index => $sol) {
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
            $scores[] = $score;
            if ($score === 327 && $first327Index === -1) {
                $first327Index = $index;
            }
        }
        
        echo "First index of score 327: " . $first327Index . "\n";
        print_r(array_count_values($scores));
    }
}

$cs = new CountScheduler();
$cs->getSolutionsCount(1, 1);
