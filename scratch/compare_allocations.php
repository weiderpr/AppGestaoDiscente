<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

$db = getDB();
$scheduler = new \App\Services\SomativaScheduler();

// Run with restriction INACTIVE
$db->exec("UPDATE somativa_restricoes SET is_active = 0 WHERE id = 73");
$planoInactive = $scheduler->run(1, 1);

// Run with restriction ACTIVE
$db->exec("UPDATE somativa_restricoes SET is_active = 1 WHERE id = 73");
$planoActive = $scheduler->run(1, 1);

// Revert to inactive
$db->exec("UPDATE somativa_restricoes SET is_active = 0 WHERE id = 73");

function printAllocations(array $plano, int $stId) {
    echo "Allocations count: " . count($plano['alocacoes']) . "\n";
    echo "Conflicts count: " . count($plano['conflitos']) . "\n";
    echo "Warnings count: " . count($plano['avisos']) . "\n";
    foreach ($plano['avisos'] as $w) {
        echo "  Warning: $w\n";
    }
    
    // Sort allocations by date and slot
    usort($plano['alocacoes'], function($a, $b) {
        if ($a['data_prova'] !== $b['data_prova']) {
            return strcmp($a['data_prova'], $b['data_prova']);
        }
        return $a['slot_config_id'] - $b['slot_config_id'];
    });

    foreach ($plano['alocacoes'] as $aloc) {
        if ((int)$aloc['somativa_turma_id'] === $stId) {
            echo "  Date: {$aloc['data_prova']} | Slot: {$aloc['slot_label']} (ID: {$aloc['slot_config_id']}) | Code: {$aloc['disciplina_codigo']} | Name: {$aloc['disc_nome']} | Justification: {$aloc['justificativa']}\n";
        }
    }
}

echo "=== WITHOUT RESTRICTION 73 ===\n";
printAllocations($planoInactive, 5);

echo "\n=== WITH RESTRICTION 73 ===\n";
printAllocations($planoActive, 5);
