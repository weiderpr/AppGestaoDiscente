<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

$db = getDB();
$scheduler = new \App\Services\SomativaScheduler();

$db->exec("UPDATE somativa_restricoes SET is_active = 1, params = '{\"regra\": \"deve\", \"scope\": \"mesma_turma\", \"disciplina_codigo_a\": \"8FG.176\", \"disciplina_codigo_b\": \"8MECT.144\"}' WHERE id = 73");
$plano = $scheduler->run(1, 1);
$db->exec("UPDATE somativa_restricoes SET is_active = 0, params = '{\"regra\": \"nao_deve\", \"scope\": \"mesma_turma\", \"disciplina_codigo_a\": \"8FG.176\", \"disciplina_codigo_b\": \"8MECT.144\"}' WHERE id = 73");

foreach ($plano['alocacoes'] as $aloc) {
    if ((int)$aloc['somativa_turma_id'] === 5) {
        echo "Date: {$aloc['data_prova']} | Slot: {$aloc['slot_label']} (ID: {$aloc['slot_config_id']}) | Code: {$aloc['disciplina_codigo']} | Name: {$aloc['disc_nome']} | Justification: {$aloc['justificativa']}\n";
    }
}
