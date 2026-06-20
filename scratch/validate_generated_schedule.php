<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaService.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

$db = getDB();
$db->beginTransaction();

try {
    // Get a valid user ID
    $userId = (int)$db->query("SELECT id FROM users LIMIT 1")->fetchColumn();

    // 1. Activate BOTH restriction 73 and 38
    $db->exec("UPDATE somativa_restricoes SET is_active = 1 WHERE id = 73");
    $db->exec("UPDATE somativa_restricoes SET is_active = 1 WHERE id = 38");

    // 2. Run scheduler
    $scheduler = new \App\Services\SomativaScheduler();
    $plano = $scheduler->run(1, 1);

    // 3. Clear existing grade slots for somativa 1
    $db->exec("DELETE FROM somativa_grade WHERE somativa_id = 1");

    // 4. Save the generated allocations
    $service = new \App\Services\SomativaService();
    foreach ($plano['alocacoes'] as $aloc) {
        $saveData = [
            'somativa_id'            => 1,
            'somativa_turma_id'      => (int)$aloc['somativa_turma_id'],
            'somativa_disciplina_id' => (int)$aloc['somativa_disciplina_id'] ?: null,
            'data_prova'             => $aloc['data_prova'],
            'slot_config_id'         => (int)$aloc['slot_config_id'],
            'aplicador_id'           => !empty($aloc['aplicador_id']) ? (int)$aloc['aplicador_id'] : null,
            'volante_id'             => !empty($aloc['volante_id']) ? (int)$aloc['volante_id'] : null,
            'naapi_aplicador_id'     => !empty($aloc['naapi_aplicador_id']) ? (int)$aloc['naapi_aplicador_id'] : null,
            'ambiente_id'            => !empty($aloc['ambiente_id']) ? (int)$aloc['ambiente_id'] : null,
            'tipo'                   => $aloc['tipo'] ?? 'Normal',
            'observacoes'            => $aloc['observacoes'] ?? null,
            'created_by'             => $userId,
        ];
        $service->saveGradeSlot($saveData);
    }

    // 5. Run validateGrade
    $violations = $service->validateGrade(1);

    echo "=== GENERATED TIMETABLE VIOLATIONS (BOTH 73 and 38 ACTIVE) ===\n";
    if (empty($violations)) {
        echo "No violations found! The timetable is 100% valid under all active restrictions.\n";
    } else {
        echo "Found " . count($violations) . " violations:\n";
        foreach ($violations as $gradeId => $vList) {
            // Fetch some info about the slot
            $info = $db->query("
                SELECT sg.data_prova, sc.label as slot_label, d.descricao as disc_name, t.description as turma_desc
                FROM somativa_grade sg
                JOIN somativa_slots_config sc ON sc.id = sg.slot_config_id
                JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
                JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
                JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
                JOIN turmas t ON t.id = st.turma_id
                WHERE sg.id = $gradeId
            ")->fetch(PDO::FETCH_ASSOC);
            
            if ($info) {
                echo "Grade ID {$gradeId} | {$info['turma_desc']} | {$info['disc_name']} | Date: {$info['data_prova']} | Slot: {$info['slot_label']}:\n";
            } else {
                echo "Grade ID {$gradeId} (No info found):\n";
            }
            foreach ($vList as $v) {
                echo "  - $v\n";
            }
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    // Rollback so database is completely unchanged!
    $db->rollBack();
    echo "Transaction rolled back. Database restored to original state.\n";
}
