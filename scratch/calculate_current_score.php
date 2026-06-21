<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

class DiagnosticScheduler extends \App\Services\SomativaScheduler {
    public function getScoreForCurrent(int $somativaId, int $instId) {
        $reflection = new ReflectionClass(\App\Services\SomativaScheduler::class);
        $loadDataMethod = $reflection->getMethod('loadData');
        $loadDataMethod->setAccessible(true);
        $data = $loadDataMethod->invoke($this, $somativaId, $instId);
        
        $datesNormais = $data['dates'];
        if ($data['scData'] !== null) {
            $datesNormais = array_filter($data['dates'], fn($d) => $d !== $data['scData']);
            $datesNormais = array_values($datesNormais);
        }
        
        // Let's load the current grade from the DB as the "solution" array
        $db = getDB();
        $allocs = $db->prepare("
            SELECT sg.*, sd.disciplina_codigo, d.descricao AS disc_nome, t.description AS turma_desc
            FROM somativa_grade sg
            JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
            JOIN turmas t                ON t.id = st.turma_id
            JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
            JOIN disciplinas d           ON d.codigo = sd.disciplina_codigo
            WHERE sg.somativa_id = ? AND sg.tipo = 'Normal'
        ");
        $allocs->execute([$somativaId]);
        $sol = $allocs->fetchAll();
        
        $calculateSolutionScoreMethod = $reflection->getMethod('calculateSolutionScore');
        $calculateSolutionScoreMethod->setAccessible(true);
        $score = $calculateSolutionScoreMethod->invoke($this, $sol, $data, $datesNormais);
        
        echo "=== CURRENT DATABASE STATE SCORE ===\n";
        echo "Score: {$score}\n";
        echo "Total allocated items: " . count($sol) . "\n";
        
        // Let's print empty days check
        $turmaDays = [];
        foreach ($sol as $aloc) {
            $tId = (int)$aloc['somativa_turma_id'];
            $d = $aloc['data_prova'];
            $turmaDays[$tId][$d] = ($turmaDays[$tId][$d] ?? 0) + 1;
        }
        
        foreach ($data['turmas'] as $t) {
            $stId = (int)$t['som_turma_id'];
            echo "Turma: {$t['turma_desc']} (som_turma_id: {$stId}) has exams on:\n";
            foreach ($datesNormais as $d) {
                $c = $turmaDays[$stId][$d] ?? 0;
                echo "  - {$d}: {$c} exams\n";
            }
        }
    }
}

try {
    $db = getDB();
    $somativa = $db->query("SELECT * FROM somativas ORDER BY id DESC LIMIT 1")->fetch();
    $diag = new DiagnosticScheduler();
    $diag->getScoreForCurrent($somativa['id'], $somativa['institution_id']);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
