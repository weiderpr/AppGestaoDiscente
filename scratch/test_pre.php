<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

class DebugScheduler extends \App\Services\SomativaScheduler {
    public function debugPre() {
        $ref = new ReflectionMethod(\App\Services\SomativaScheduler::class, 'loadData');
        $ref->setAccessible(true);
        $data = $ref->invoke($this, 1, 1);

        $datesNormais = $data['dates'];
        if ($data['scData'] !== null) {
            $datesNormais = array_values(array_filter($datesNormais, fn($d) => $d !== $data['scData']));
        }
        
        $ref2 = new ReflectionMethod(\App\Services\SomativaScheduler::class, 'indexRestricoes');
        $ref2->setAccessible(true);
        $restricoes = $ref2->invoke($this, $data['restricoes']);

        $hardProfAll = [];
        foreach ($restricoes['professor_mesmo_dia_horario'] as $r) {
            if (($r['tipo'] ?? 'soft') === 'hard' && !empty($r['todos'])) {
                $profDiscTurmas = $data['profDiscTurmas'] ?? [];
                foreach ($profDiscTurmas as $pid => $discMap) {
                    $stIds = array_unique(array_merge(...array_values($discMap)));
                    if (count($stIds) >= 2) $hardProfAll[$pid] = true;
                }
            }
        }
        $profAllStIds = [];
        foreach ($data['profDiscTurmas'] as $pid => $discMap) {
            $stIds = array_unique(array_merge(...array_values($discMap)));
            if (count($stIds) >= 2) $profAllStIds[$pid] = $stIds;
        }

        // Run pre-allocate logic and print candidates for professor 33
        $preAllocated = [];
        $ocupados = [];
        $countDia = [];
        $discNoDia = [];
        $codEmData = [];
        $alocacoes = [];

        // Let's emulate the exact loop
        $profGroups = [];
        foreach (array_keys($hardProfAll) as $pid) {
            $stIds = $profAllStIds[$pid] ?? [];
            if (count($stIds) >= 2) $profGroups[$pid] = $stIds;
        }
        uasort($profGroups, fn($a, $b) => count($b) - count($a));

        $maxPorDia = (int)$data['som']['max_provas_por_dia'];

        foreach ($profGroups as $pid => $stIds) {
            $items = [];
            foreach ($data['turmas'] as $turma) {
                $stId = (int)$turma['som_turma_id'];
                if (!in_array($stId, $stIds)) continue;
                foreach ($turma['disciplinas'] as $disc) {
                    $profIds = array_filter(explode(',', $disc['professor_ids'] ?? ''));
                    if (!in_array((string)$pid, $profIds)) continue;
                    $sdId = (int)$disc['som_disc_id'];
                    $items[] = ['stId' => $stId, 'turma' => $turma, 'disc' => $disc, 'sdId' => $sdId];
                }
            }
            if (empty($items)) continue;

            $foundDate = null;
            $foundSlot = null;
            foreach ([true, false] as $enforceMinProvas) {
                foreach ($datesNormais as $date) {
                    foreach ($data['slots'] as $slot) {
                        $slotId = $slot['id'];
                        
                        // Let's print candidate checking
                        $canUse = true;
                        foreach ($items as $item) {
                            $sId = $item['stId'];
                            if (!empty($ocupados[$sId][$date][$slotId])) { $canUse = false; }
                        }
                        
                        // Check min_provas_por_dia
                        $hardMinBlock = false;
                        if ($enforceMinProvas) {
                            foreach ($items as $item) {
                                $sId = $item['stId'];
                                foreach ($restricoes['min_provas_por_dia'] as $r) {
                                    if ($r['tipo'] !== 'hard') continue;
                                    $rMin    = max(1, (int)($r['min'] ?? 2));
                                    $rScope  = $r['scope'] ?? 'todas';
                                    $rApply  = $rScope === 'todas' || ($rScope === 'turma' && (int)($r['somativa_turma_id'] ?? 0) === $sId);
                                    if (!$rApply) continue;
                                    $dateCount = count($discNoDia[$sId][$date] ?? []);
                                    if ($dateCount >= $rMin) {
                                        foreach ($datesNormais as $d) {
                                            if ($d === $date) continue;
                                            if (count($discNoDia[$sId][$d] ?? []) < $rMin) {
                                                $hardMinBlock = true;
                                                break 2;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Check preferir_primeiros_horarios
                        $hardPrefBlock = false;
                        if ($enforceMinProvas) {
                            foreach ($items as $item) {
                                $sId = $item['stId'];
                                foreach ($restricoes['preferir_primeiros_horarios'] as $r) {
                                    if (($r['tipo'] ?? 'soft') !== 'hard') continue;
                                    $scope = $r['scope'] ?? 'todas';
                                    $applies = ($scope === 'todas') ||
                                               ($scope === 'turma' && (int)($r['somativa_turma_id'] ?? 0) === $sId);
                                    if (!$applies) continue;

                                    $currentOrdem = (int)$slot['ordem'];
                                    foreach ($data['slots'] as $s) {
                                        if ((int)$s['ordem'] >= $currentOrdem) continue;
                                        if (empty($ocupados[$sId][$date][$s['id']])) {
                                            $hardPrefBlock = true;
                                            break 2;
                                        }
                                    }
                                    break;
                                }
                                if ($hardPrefBlock) break;
                            }
                        }

                        if ($pid == 33) {
                            echo "Checking Prof 33 on $date Slot $slotId (enforce: " . ($enforceMinProvas ? 'Y' : 'N') . ") | canUse: " . ($canUse ? 'Y' : 'N') . " | minBlock: " . ($hardMinBlock ? 'Y' : 'N') . " | prefBlock: " . ($hardPrefBlock ? 'Y' : 'N') . "\n";
                        }
                    }
                }
            }
            // Actually allocate to preserve state
            // (find the first candidate)
            $allocated = false;
            foreach ([true, false] as $enforceMinProvas) {
                foreach ($datesNormais as $date) {
                    // check min blocker
                    $hardMinBlock = false;
                    if ($enforceMinProvas) {
                        foreach ($items as $item) {
                            $sId = $item['stId'];
                            foreach ($restricoes['min_provas_por_dia'] as $r) {
                                if ($r['tipo'] !== 'hard') continue;
                                $rMin    = max(1, (int)($r['min'] ?? 2));
                                $rScope  = $r['scope'] ?? 'todas';
                                $rApply  = $rScope === 'todas' || ($rScope === 'turma' && (int)($r['somativa_turma_id'] ?? 0) === $sId);
                                if (!$rApply) continue;
                                $dateCount = count($discNoDia[$sId][$date] ?? []);
                                if ($dateCount >= $rMin) {
                                    foreach ($datesNormais as $d) {
                                        if ($d === $date) continue;
                                        if (count($discNoDia[$sId][$d] ?? []) < $rMin) {
                                            $hardMinBlock = true;
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if ($hardMinBlock) continue;

                    foreach ($data['slots'] as $slot) {
                        $slotId = $slot['id'];
                        
                        $hardPrefBlock = false;
                        if ($enforceMinProvas) {
                            foreach ($items as $item) {
                                $sId = $item['stId'];
                                foreach ($restricoes['preferir_primeiros_horarios'] as $r) {
                                    if (($r['tipo'] ?? 'soft') !== 'hard') continue;
                                    $scope = $r['scope'] ?? 'todas';
                                    $applies = ($scope === 'todas') ||
                                               ($scope === 'turma' && (int)($r['somativa_turma_id'] ?? 0) === $sId);
                                    if (!$applies) continue;

                                    $currentOrdem = (int)$slot['ordem'];
                                    foreach ($data['slots'] as $s) {
                                        if ((int)$s['ordem'] >= $currentOrdem) continue;
                                        if (empty($ocupados[$sId][$date][$s['id']])) {
                                            $hardPrefBlock = true;
                                            break 2;
                                        }
                                    }
                                    break;
                                }
                                if ($hardPrefBlock) break;
                            }
                        }
                        if ($hardPrefBlock) continue;

                        $canUse = true;
                        foreach ($items as $item) {
                            $sId = $item['stId'];
                            if (!empty($ocupados[$sId][$date][$slotId])) { $canUse = false; break; }
                        }
                        if ($canUse) {
                            $foundDate = $date;
                            $foundSlot = $slot;
                            $allocated = true;
                            break 2;
                        }
                    }
                }
                if ($allocated) break;
            }

            if ($allocated) {
                foreach ($items as $item) {
                    $ocupados[$item['stId']][$foundDate][$foundSlot['id']] = true;
                    $discNoDia[$item['stId']][$foundDate][] = $item['disc']['disciplina_codigo'];
                }
            }
        }
    }
}

$d = new DebugScheduler();
$d->debugPre();
