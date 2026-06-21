<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

class DebugScheduler extends \App\Services\SomativaScheduler {
    public function debugScore(int $somativaId, int $instId) {
        $reflection = new ReflectionClass(\App\Services\SomativaScheduler::class);
        $loadDataMethod = $reflection->getMethod('loadData');
        $loadDataMethod->setAccessible(true);
        $data = $loadDataMethod->invoke($this, $somativaId, $instId);
        
        $datesNormais = $data['dates'];
        if ($data['scData'] !== null) {
            $datesNormais = array_filter($data['dates'], fn($d) => $d !== $data['scData']);
            $datesNormais = array_values($datesNormais);
        }
        
        $indexRestricoesMethod = $reflection->getMethod('indexRestricoes');
        $indexRestricoesMethod->setAccessible(true);
        $restricoes = $indexRestricoesMethod->invoke($this, $data['restricoes']);
        
        // Find Biologia in Primeira Série (stId 2)
        $biologia = null;
        $turmaBiologia = null;
        foreach ($data['turmas'] as $t) {
            if ((int)$t['som_turma_id'] === 2) {
                $turmaBiologia = $t;
                foreach ($t['disciplinas'] as $d) {
                    if ($d['disciplina_codigo'] === '8FG.173') {
                        $biologia = $d;
                        break 2;
                    }
                }
            }
        }
        
        if (!$biologia) {
            echo "Biologia not found.\n";
            return;
        }
        
        $scoreSlotMethod = $reflection->getMethod('scoreSlot');
        $scoreSlotMethod->setAccessible(true);
        
        // build constrained codes
        $constrainedCodes = [];
        foreach ($restricoes['mesmo_horario_turmas'] as $r) {
            $c = $r['disciplina_codigo'] ?? '';
            if ($c) $constrainedCodes[$c] = true;
        }
        
        $discNoDia = [];
        $codEmData = [];
        $alocacoes = [];
        
        echo "=== DEBUGGING SCORE FOR BIOLOGIA (8FG.173) ===\n";
        foreach ($datesNormais as $date) {
            foreach ($data['slots'] as $slot) {
                $slotId = (int)$slot['id'];
                
                // Let's run scoreSlot
                $res = $scoreSlotMethod->invokeArgs($this, [
                    $date, $slot, $biologia, $turmaBiologia, $restricoes, $data,
                    $discNoDia, $codEmData, 2, $data['scData'],
                    $constrainedCodes, $alocacoes
                ]);
                
                $score = $res[0];
                $reasons = $res[1];
                
                echo "Date: {$date} | Slot: {$slot['label']} (ID: {$slotId}) | Score: {$score}\n";
                foreach ($reasons as $reason) {
                    echo "  -> {$reason}\n";
                }
            }
        }
    }
}

try {
    $db = getDB();
    $somativa = $db->query("SELECT * FROM somativas ORDER BY id DESC LIMIT 1")->fetch();
    $diag = new DebugScheduler();
    $diag->debugScore($somativa['id'], $somativa['institution_id']);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
