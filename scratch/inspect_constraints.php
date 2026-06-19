<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

echo "\n=== CONSTRAINTS BY CATEGORY ===\n";
$counts = $db->query("SELECT categoria, count(*) as count FROM somativa_restricoes GROUP BY categoria")->fetchAll(PDO::FETCH_ASSOC);
print_r($counts);

echo "\n=== MESMO HORARIO TURMAS CONSTRAINTS ===\n";
$restricoes = $db->query("SELECT * FROM somativa_restricoes WHERE categoria = 'mesmo_horario_turmas'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($restricoes as $r) {
    echo "ID: {$r['id']} | Tipo: {$r['tipo']} | Cat: {$r['categoria']} | Active: {$r['is_active']} | Desc: {$r['descricao']}\n";
    echo "  Params: " . $r['params'] . "\n";
}
