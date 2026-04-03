<?php
require_once __DIR__ . '/includes/auth.php';
$db = getDB();

// Encontrar uma turma que possua aulas cadastradas
$stAulas = $db->query("SELECT turma_id FROM gestao_turma_aulas LIMIT 1");
$aula = $stAulas->fetch();

if ($aula) {
    $tid = $aula['turma_id'];
    // Encontrar um aluno nessa turma
    $stAluno = $db->prepare("SELECT a.id, a.nome 
                             FROM alunos a 
                             JOIN turma_alunos ta ON ta.aluno_id = a.id 
                             WHERE ta.turma_id = ? 
                             LIMIT 1");
    $stAluno->execute([$tid]);
    $row = $stAluno->fetch();
    
    if ($row) {
        echo "VALID_ALUNO_ID: " . $row['id'] . " (Turma: $tid - " . $row['nome'] . ")\n";
        
        // Simular a execução do student_grid.php
        $_GET['aluno_id'] = $row['id'];
        ob_start();
        include __DIR__ . '/courses/aulas/student_grid.php';
        $output = ob_get_clean();
        echo "GRID_OUTPUT_LENGTH: " . strlen($output) . "\n";
        echo "GRID_CONTAINS_GRID_CLASS: " . (strpos($output, 'schedule-grid') !== false ? 'YES' : 'NO') . "\n";
    } else {
        echo "Nenhum aluno encontrado para a turma $tid.";
    }
} else {
    echo "Nenhuma aula cadastrada no sistema.";
}
