<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

echo "=== CHECKING DUPLICATES IN somativa_grade (same class and subject) ===\n";
$stmt = $db->query("
    SELECT count(*) as count, somativa_turma_id, somativa_disciplina_id 
    FROM somativa_grade 
    WHERE somativa_disciplina_id IS NOT NULL
    GROUP BY somativa_turma_id, somativa_disciplina_id 
    HAVING count(*) > 1
");
$dups = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($dups);

foreach ($dups as $dup) {
    echo "\nDuplicates details for Turma {$dup['somativa_turma_id']} | Subject ID {$dup['somativa_disciplina_id']}:\n";
    $stmt = $db->prepare("
        SELECT sg.*, sd.disciplina_codigo, d.descricao as disc_nome, t.description as turma_desc
        FROM somativa_grade sg
        JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
        JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
        JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
        JOIN turmas t ON t.id = st.turma_id
        WHERE sg.somativa_turma_id = ? AND sg.somativa_disciplina_id = ?
    ");
    $stmt->execute([$dup['somativa_turma_id'], $dup['somativa_disciplina_id']]);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
