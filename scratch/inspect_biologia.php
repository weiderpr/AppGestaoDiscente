<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

echo "=== ALLOCATIONS FOR BIOLOGIA - 2ª SÉRIE (8FG.174) ===\n";
$allocations = $db->query("
    SELECT sg.*, sd.disciplina_codigo, d.descricao as disc_nome, t.description as turma_desc, c.name as course_name, p1.name as aplicador, p2.name as volante
    FROM somativa_grade sg
    JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
    JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
    JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
    JOIN turmas t ON t.id = st.turma_id
    JOIN courses c ON c.id = t.course_id
    LEFT JOIN users p1 ON p1.id = sg.aplicador_id
    LEFT JOIN users p2 ON p2.id = sg.volante_id
    WHERE sd.disciplina_codigo = '8FG.174'
    ORDER BY sg.data_prova, sg.slot_config_id, c.name, t.description
")->fetchAll(PDO::FETCH_ASSOC);
print_r($allocations);

echo "\n=== ALLOCATIONS INVOLVING Cristina Roscoe ===\n";
$cristina = $db->query("
    SELECT sg.*, sd.disciplina_codigo, d.descricao as disc_nome, t.description as turma_desc, c.name as course_name, p1.name as aplicador, p2.name as volante
    FROM somativa_grade sg
    JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
    JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
    JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
    JOIN turmas t ON t.id = st.turma_id
    JOIN courses c ON c.id = t.course_id
    LEFT JOIN users p1 ON p1.id = sg.aplicador_id
    LEFT JOIN users p2 ON p2.id = sg.volante_id
    WHERE p1.name LIKE '%Cristina%' OR p2.name LIKE '%Cristina%'
    ORDER BY sg.data_prova, sg.slot_config_id
")->fetchAll(PDO::FETCH_ASSOC);
print_r($cristina);
