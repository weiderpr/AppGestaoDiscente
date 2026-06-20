<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

$db = getDB();

// Run with current DB state
$scheduler = new \App\Services\SomativaScheduler();
$plano = $scheduler->run(1, 1);

echo "=== ALOCACOES DA SEGUNDA SÉRIE MECATRÔNICA ===\n";
// ST_ID for Segunda Série Mecatrônica is 6
foreach ($plano['alocacoes'] as $aloc) {
    if ((int)$aloc['somativa_turma_id'] === 6) {
        echo "Data: {$aloc['data_prova']} | Slot: {$aloc['slot_label']} (ID: {$aloc['slot_config_id']}) | Disc: {$aloc['disc_nome']} (Code: {$aloc['disciplina_codigo']}) | Aplicador ID: {$aloc['aplicador_id']} | Justificativa: {$aloc['justificativa']}\n";
    }
}

echo "\n=== CONFLITOS DE SISTEMAS EMBARCADOS (SD_ID 66) ===\n";
foreach ($plano['conflitos'] as $c) {
    if ((int)$c['somativa_disciplina_id'] === 66) {
        echo "Motivo detalhado: {$c['motivo']}\n";
    }
}
