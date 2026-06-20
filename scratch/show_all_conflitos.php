<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

$scheduler = new \App\Services\SomativaScheduler();
$plano = $scheduler->run(1, 1);

echo "=== ALL CONFLICTS ===\n";
foreach ($plano['conflitos'] as $c) {
    echo "SD_ID: {$c['somativa_disciplina_id']} | Code: {$c['disciplina_codigo']} | Name: {$c['disc_nome']} | Turma: {$c['turma_desc']} | Motivo: {$c['motivo']}\n";
}

echo "\n=== ALL ALLOCATIONS FOR SEGUNDA SÉRIE MECATRÔNICA ===\n";
foreach ($plano['alocacoes'] as $aloc) {
    if (strpos($aloc['turma_desc'], 'Mecatrônica') !== false && strpos($aloc['turma_desc'], 'Segunda') !== false) {
        echo "Data: {$aloc['data_prova']} | Slot: {$aloc['slot_label']} | Disc: {$aloc['disc_nome']} | Just: {$aloc['justificativa']}\n";
    }
}
