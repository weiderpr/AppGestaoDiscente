<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

$output = "";

try {
    $db = getDB();
    $somativa = $db->query("SELECT * FROM somativas ORDER BY id DESC LIMIT 1")->fetch();
    if (!$somativa) {
        echo "No somativa found.\n";
        exit;
    }
    
    $scheduler = new \App\Services\SomativaScheduler();
    $result = $scheduler->run($somativa['id'], $somativa['institution_id']);
    
    $output .= "=== SCHEDULER RUN RESULTS ===\n";
    $output .= "Somativa ID: " . $somativa['id'] . " | " . $somativa['nome'] . "\n\n";
    
    $output .= "--- WARNINGS (AVISOS) ---\n";
    foreach ($result['avisos'] as $aviso) {
        $output .= "  * {$aviso}\n";
    }
    $output .= "\n";
    
    $output .= "--- CONFLICTS (CONFLITOS) ---\n";
    foreach ($result['conflitos'] as $conf) {
        $output .= "  * Disciplina: {$conf['disc_nome']} ({$conf['disciplina_codigo']}) | Turma: {$conf['turma_desc']} | Motivo: {$conf['motivo']}\n";
    }
    $output .= "\n";
    
    $output .= "--- PROPOSED GRADE (ALOCAÇÕES) ---\n";
    // Sort by date, slot order, then turma
    usort($result['alocacoes'], function($a, $b) use ($db) {
        if ($a['data_prova'] !== $b['data_prova']) {
            return strcmp($a['data_prova'], $b['data_prova']);
        }
        if ($a['slot_config_id'] !== $b['slot_config_id']) {
            return $a['slot_config_id'] <=> $b['slot_config_id'];
        }
        return strcmp($a['turma_desc'], $b['turma_desc']);
    });
    
    foreach ($result['alocacoes'] as $aloc) {
        $output .= "Date: {$aloc['data_prova']} | Slot: " . ($aloc['slot_label'] ?? $aloc['slot_config_id']) . " | Turma: {$aloc['turma_desc']} | Disc: {$aloc['disc_nome']} ({$aloc['disciplina_codigo']})\n";
    }
    
} catch (Exception $e) {
    $output .= "Error: " . $e->getMessage() . "\n";
}

file_put_contents(__DIR__ . '/scheduler_output.txt', $output);
echo "Scheduler run completed. Output in scratch/scheduler_output.txt\n";
