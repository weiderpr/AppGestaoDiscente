<?php
require_once 'config/database.php';
$db = getDB();

echo "--- manutencoes ---\n";
$stmt = $db->query("DESCRIBE manutencoes");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n--- manutencao_relatos ---\n";
$stmt = $db->query("DESCRIBE manutencao_relatos");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
