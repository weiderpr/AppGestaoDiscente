<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

$scheduler = new \App\Services\SomativaScheduler();
$plano = $scheduler->run(1, 1);

echo "=== SCHEDULER OUTPUT ALLOCATIONS COUNT: " . count($plano['alocacoes']) . " ===\n";
echo "=== SCHEDULER CONFLICTS COUNT: " . count($plano['conflitos']) . " ===\n";

$counts = [];
foreach ($plano['alocacoes'] as $a) {
    $key = $a['somativa_turma_id'] . '_' . $a['disciplina_codigo'];
    if (!isset($counts[$key])) {
        $counts[$key] = [];
    }
    $counts[$key][] = $a;
}

$hasDups = false;
foreach ($counts as $key => $alocs) {
    if (count($alocs) > 1) {
        $hasDups = true;
        echo "DUPLICATE FOUND for Turma {$alocs[0]['turma_desc']} | Subject {$alocs[0]['disc_nome']} ({$alocs[0]['disciplina_codigo']}):\n";
        foreach ($alocs as $a) {
            echo "  - ID: {$a['somativa_disciplina_id']} | Date: {$a['data_prova']} | Slot: {$a['slot_config_id']} | Just: {$a['justificativa']}\n";
        }
    }
}

if (!$hasDups) {
    echo "No duplicates found in scheduler output allocations.\n";
}
