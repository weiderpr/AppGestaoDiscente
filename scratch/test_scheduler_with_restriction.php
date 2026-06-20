<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

$db = getDB();

try {
    // 1. Activate restriction 73
    $db->exec("UPDATE somativa_restricoes SET is_active = 1 WHERE id = 73");
    echo "Restriction 73 activated.\n";

    // 2. Run scheduler
    $scheduler = new \App\Services\SomativaScheduler();
    $plano = $scheduler->run(1, 1);

    echo "Total allocations: " . count($plano['alocacoes']) . "\n";
    echo "Total conflicts: " . count($plano['conflitos']) . "\n";

    echo "\n=== MECATRÔNICA ALLOCATIONS ===\n";
    foreach ($plano['alocacoes'] as $aloc) {
        if ((int)$aloc['somativa_turma_id'] === 6) {
            echo "Data: {$aloc['data_prova']} | Slot: {$aloc['slot_label']} | Disc: {$aloc['disc_nome']} (Code: {$aloc['disciplina_codigo']}) | Justificativa: {$aloc['justificativa']}\n";
        }
    }

    echo "\n=== CONFLICTS ===\n";
    foreach ($plano['conflitos'] as $c) {
        echo "SD_ID: {$c['somativa_disciplina_id']} | Code: {$c['disciplina_codigo']} | Name: {$c['disc_nome']} | Turma: {$c['turma_desc']} | Motivo: {$c['motivo']}\n";
    }

    echo "\n=== WARNINGS ===\n";
    foreach ($plano['avisos'] as $w) {
        echo "- {$w}\n";
    }

} finally {
    // 3. Deactivate restriction 73
    $db->exec("UPDATE somativa_restricoes SET is_active = 0 WHERE id = 73");
    echo "Restriction 73 deactivated.\n";
}
