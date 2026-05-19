<?php
/**
 * Vértice Acadêmico — Adiciona coluna instrumento_aplicado à tabela segunda_chamada
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Alterando tabela segunda_chamada para adicionar instrumento_aplicado...\n";
    
    // Verifica se a coluna já existe
    $stmt = $db->query("SHOW COLUMNS FROM `segunda_chamada` LIKE 'instrumento_aplicado'");
    $column = $stmt->fetch();
    
    if (!$column) {
        $db->exec("ALTER TABLE `segunda_chamada` ADD `instrumento_aplicado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `status`");
        echo "Coluna instrumento_aplicado adicionada com sucesso!\n";
    } else {
        echo "Coluna instrumento_aplicado já existe.\n";
    }
    
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
    exit(1);
}
