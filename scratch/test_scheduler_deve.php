<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

$db = getDB();
$scheduler = new \App\Services\SomativaScheduler();

// Set restriction 73 to "deve" and active
$db->exec("UPDATE somativa_restricoes SET is_active = 1, params = '{\"regra\": \"deve\", \"scope\": \"mesma_turma\", \"disciplina_codigo_a\": \"8FG.176\", \"disciplina_codigo_b\": \"8MECT.144\"}' WHERE id = 73");

$plano = $scheduler->run(1, 1);

// Revert to original database state
$db->exec("UPDATE somativa_restricoes SET is_active = 0, params = '{\"regra\": \"nao_deve\", \"scope\": \"mesma_turma\", \"disciplina_codigo_a\": \"8FG.176\", \"disciplina_codigo_b\": \"8MECT.144\"}' WHERE id = 73");

echo "=== SCHEDULER RUN WITH 'DEVE' ===\n";
echo "Allocations count: " . count($plano['alocacoes']) . "\n";
echo "Conflicts count: " . count($plano['conflitos']) . "\n";
echo "Warnings count: " . count($plano['avisos']) . "\n";

foreach ($plano['avisos'] as $w) {
    echo "  Warning: $w\n";
}

foreach ($plano['conflitos'] as $c) {
    echo "  Conflict: SD_ID: {$c['somativa_disciplina_id']} | Code: {$c['disciplina_codigo']} | Name: {$c['disc_nome']} | Turma: {$c['turma_desc']} | Motivo: {$c['motivo']}\n";
}
