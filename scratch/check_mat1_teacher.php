<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

$rows = $db->query("
    SELECT sd.id, sd.disciplina_codigo, d.descricao as disc_desc, t.description as turma_desc,
           GROUP_CONCAT(DISTINCT tdp.professor_id SEPARATOR ',') as professor_ids
    FROM somativa_disciplinas sd
    JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
    JOIN somativa_turmas st ON st.id = sd.somativa_turma_id
    JOIN turmas t ON t.id = st.turma_id
    LEFT JOIN turma_disciplinas td ON td.turma_id = t.id AND td.disciplina_codigo = sd.disciplina_codigo
    LEFT JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
    WHERE sd.id = 17
    GROUP BY sd.id, sd.disciplina_codigo, d.descricao, t.description
")->fetchAll(PDO::FETCH_ASSOC);

print_r($rows);

foreach ($rows as $r) {
    $profIds = array_filter(explode(',', $r['professor_ids']));
    foreach ($profIds as $pid) {
        echo "Teacher ID: {$pid}\n";
        $stmt2 = $db->prepare("
            SELECT DISTINCT dia_semana, horario_inicio, horario_fim 
            FROM gestao_turma_aulas gta
            JOIN turma_disciplinas td ON td.turma_id = gta.turma_id AND td.disciplina_codigo = gta.disciplina_codigo
            JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
            WHERE tdp.professor_id = ? AND gta.is_active = 1
            ORDER BY dia_semana, horario_inicio
        ");
        $stmt2->execute([$pid]);
        $avail = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($avail as $av) {
            echo "  Dia semana {$av['dia_semana']}: {$av['horario_inicio']} - {$av['horario_fim']}\n";
        }
    }
}
