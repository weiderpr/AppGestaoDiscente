<?php
/**
 * Seed: Problemas Padrão e Vínculo com Ambientes
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();

    // 1. Cadastrar problemas padrão
    $problemas = ['Lâmpada', 'Quadro', 'Tomada', 'Projetor', 'Ar Condicionado', 'Porta/Fechadura'];
    foreach ($problemas as $p) {
        $db->prepare("INSERT IGNORE INTO manutencao_problemas_padrao (descricao) VALUES (?)")->execute([$p]);
    }

    // 2. Pegar todos os IDs de problemas
    $stmt = $db->query("SELECT id FROM manutencao_problemas_padrao");
    $problemaIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 3. Pegar todos os ambientes
    $stmt = $db->query("SELECT id FROM manutencao_ambientes");
    $ambienteIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 4. Vincular todos os problemas a todos os ambientes (para teste inicial)
    foreach ($ambienteIds as $aid) {
        foreach ($problemaIds as $pid) {
            $db->prepare("INSERT IGNORE INTO manutencao_ambiente_problemas (ambiente_id, problema_id) VALUES (?, ?)")
               ->execute([$aid, $pid]);
        }
    }

    echo "Problemas padrão cadastrados e vinculados aos ambientes com sucesso!\n";

} catch (Exception $e) {
    die("Erro no seed: " . $e->getMessage() . "\n");
}
