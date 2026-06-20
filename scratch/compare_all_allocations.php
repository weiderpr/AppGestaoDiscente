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

function getMap(array $plano) {
    $map = [];
    foreach ($plano['alocacoes'] as $aloc) {
        $key = $aloc['turma_desc'] . ' | ' . $aloc['disc_nome'];
        $map[$key] = $aloc['data_prova'] . ' @ ' . $aloc['slot_label'];
    }
    ksort($map);
    return $map;
}

$mapInactive = getMap($planoInactive);
$mapActive = getMap($planoActive);

echo "=== TIMETABLE COMPARISON (ACTIVE VS INACTIVE) ===\n";
$changedCount = 0;
foreach ($mapInactive as $key => $timeInactive) {
    $timeActive = $mapActive[$key] ?? 'NOT ALLOCATED';
    if ($timeInactive !== $timeActive) {
        echo "Changed: $key\n";
        echo "  Inactive: $timeInactive\n";
        echo "  Active:   $timeActive\n";
        $changedCount++;
    }
}
echo "Total changes: $changedCount / " . count($mapInactive) . "\n";
