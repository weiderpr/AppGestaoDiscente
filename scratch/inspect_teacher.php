<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "=== TEACHER REGULAR AULAS (ID: 23 - Cristina Roscoe Vianna) ===\n";
    $stmt = $db->prepare("
        SELECT gta.*, t.description AS turma_desc, d.descricao AS disc_nome
        FROM gestao_turma_aulas gta
        JOIN turmas t ON t.id = gta.turma_id
        JOIN disciplinas d ON d.codigo = gta.disciplina_codigo
        JOIN turma_disciplinas td ON td.turma_id = gta.turma_id AND td.disciplina_codigo = gta.disciplina_codigo
        JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
        WHERE tdp.professor_id = 23 AND gta.is_active = 1
        ORDER BY gta.dia_semana, gta.horario_inicio
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    
    $dias = ['', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
    foreach ($rows as $r) {
        echo "Dia: {$dias[$r['dia_semana']]} | Horário: {$r['horario_inicio']} - {$r['horario_fim']} | Turma: {$r['turma_desc']} | Disciplina: {$r['disc_nome']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
