<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

$db = getDB();
$scheduler = new \App\Services\SomativaScheduler();

// Activate BOTH 73 and 38
$db->exec("UPDATE somativa_restricoes SET is_active = 1 WHERE id = 73");
$db->exec("UPDATE somativa_restricoes SET is_active = 1 WHERE id = 38");

$plano = $scheduler->run(1, 1);

// Revert
$db->exec("UPDATE somativa_restricoes SET is_active = 0 WHERE id = 73");
$db->exec("UPDATE somativa_restricoes SET is_active = 0 WHERE id = 38");

echo "=== RUN WITH BOTH 73 AND 38 ACTIVE ===\n";
echo "Allocations count: " . count($plano['alocacoes']) . "\n";
echo "Conflicts count: " . count($plano['conflitos']) . "\n";
echo "Warnings count: " . count($plano['avisos']) . "\n";

foreach ($plano['avisos'] as $w) {
    echo "  Warning: $w\n";
}

foreach ($plano['conflitos'] as $c) {
    echo "  Conflict: SD_ID: {$c['somativa_disciplina_id']} | Code: {$c['disciplina_codigo']} | Name: {$c['disc_nome']} | Turma: {$c['turma_desc']} | Motivo: {$c['motivo']}\n";
}
