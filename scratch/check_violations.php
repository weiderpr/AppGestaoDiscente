<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

$scheduler = new \App\Services\SomativaScheduler();
$plano = $scheduler->run(1, 1);

$slotsByTurmaDate = [];
foreach ($plano['alocacoes'] as $a) {
    $slotsByTurmaDate[$a['somativa_turma_id']][$a['data_prova']][$a['slot_config_id']] = $a;
}

$db = getDB();
$slots = $db->query('SELECT * FROM somativa_slots_config WHERE somativa_id = 1 ORDER BY ordem')->fetchAll(PDO::FETCH_ASSOC);

$violations = [];
foreach ($slotsByTurmaDate as $stId => $dates) {
    foreach ($dates as $date => $allocs) {
        foreach ($slots as $idx => $slot) {
            if (isset($allocs[$slot['id']])) {
                for ($i = 0; $i < $idx; $i++) {
                    $prevSlot = $slots[$i];
                    if (!isset($allocs[$prevSlot['id']])) {
                        $violations[] = [
                            'turma_id' => $stId,
                            'date' => $date,
                            'empty_slot' => $prevSlot['id'],
                            'empty_label' => $prevSlot['label'],
                            'occupied_slot' => $slot['id'],
                            'occupied_label' => $slot['label'],
                            'occupied_disc' => $allocs[$slot['id']]['disc_nome'],
                            'justificativa' => $allocs[$slot['id']]['justificativa']
                        ];
                    }
                }
            }
        }
    }
}

if (empty($violations)) {
    echo "No slot concentration violations found!\n";
} else {
    echo "Found " . count($violations) . " slot concentration violations:\n";
    print_r($violations);
}
