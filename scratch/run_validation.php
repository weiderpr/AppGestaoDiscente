<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaService.php';

$output = "";

try {
    $db = getDB();
    $somativa = $db->query("SELECT * FROM somativas ORDER BY id DESC LIMIT 1")->fetch();
    if (!$somativa) {
        echo "No somativa found.\n";
        exit;
    }
    
    $service = new \App\Services\SomativaService();
    $violations = $service->validateGrade($somativa['id']);
    
    $output .= "=== VALIDATION ON CURRENT USER-ADJUSTED GRADE ===\n";
    $output .= "Somativa: {$somativa['nome']} (ID: {$somativa['id']})\n\n";
    
    if (empty($violations)) {
        $output .= "PERFECT! No hard constraints violated by the current manual layout.\n";
    } else {
        $output .= "WARNING: The current layout violates some active hard constraints:\n\n";
        foreach ($violations as $gradeId => $vList) {
            // Get some details about this grade slot
            $info = $db->prepare("
                SELECT sg.data_prova, sg.slot_config_id, sc.label AS slot_label, t.description AS turma_desc, d.descricao AS disc_nome
                FROM somativa_grade sg
                JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
                JOIN turmas t ON t.id = st.turma_id
                LEFT JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
                LEFT JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
                JOIN somativa_slots_config sc ON sc.id = sg.slot_config_id
                WHERE sg.id = ?
            ");
            $info->execute([$gradeId]);
            $g = $info->fetch();
            
            $output .= "Grade ID {$gradeId} | Turma: {$g['turma_desc']} | Data: {$g['data_prova']} | Slot: {$g['slot_label']} | Disciplina: {$g['disc_nome']}\n";
            foreach ($vList as $v) {
                $output .= "  -> [VIOLATION] {$v}\n";
            }
            $output .= "\n";
        }
    }
    
} catch (Exception $e) {
    $output .= "Error: " . $e->getMessage() . "\n";
}

file_put_contents(__DIR__ . '/validation_output.txt', $output);
echo "Validation completed. Output in scratch/validation_output.txt\n";
