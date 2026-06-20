<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

echo "=== COLUMNS OF somativa_disciplinas ===\n";
$stmt = $db->query("DESCRIBE somativa_disciplinas");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n=== COLUMNS OF somativas ===\n";
$stmt = $db->query("DESCRIBE somativas");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
