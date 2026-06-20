<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

$scheduler = new \App\Services\SomativaScheduler();
$plano = $scheduler->run(1, 1);

echo "=== CONFLICTS ===\n";
foreach ($plano['conflitos'] as $c) {
    echo "SD_ID: {$c['somativa_disciplina_id']} | Code: {$c['disciplina_codigo']} | Name: {$c['disc_nome']} | Turma: {$c['turma_desc']} | Motivo: {$c['motivo']}\n";
}

echo "\n=== WARNINGS ===\n";
foreach ($plano['avisos'] as $w) {
    echo "- {$w}\n";
}
