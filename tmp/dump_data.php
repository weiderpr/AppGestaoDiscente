<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/auth.php';
$db = getDB();
$total = $db->query("SELECT COUNT(*) FROM gestao_alunos_atividadesextra")->fetchColumn();
echo "TOTAL NA TABELA: $total\n";
if ($total > 0) {
    echo "ULTIMOS 5 REGISTROS:\n";
    $st = $db->query("SELECT * FROM gestao_alunos_atividadesextra ORDER BY id DESC LIMIT 5");
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
}
