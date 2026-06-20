<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

$tables = ['somativas', 'somativa_turmas', 'somativa_disciplinas', 'somativa_slots_config', 'somativa_restricoes'];
foreach ($tables as $t) {
    $c = $db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    echo "Table: $t | Total Rows: $c\n";
}

$c1 = $db->query("SELECT COUNT(*) FROM somativa_turmas WHERE somativa_id = 1")->fetchColumn();
$c2 = $db->query("SELECT COUNT(*) FROM somativa_slots_config WHERE somativa_id = 1")->fetchColumn();
$c3 = $db->query("SELECT COUNT(*) FROM somativa_disciplinas sd JOIN somativa_turmas st ON st.id = sd.somativa_turma_id WHERE st.somativa_id = 1")->fetchColumn();
echo "For Somativa 1: Turmas: $c1 | Slots: $c2 | Disciplinas: $c3\n";
