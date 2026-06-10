<?php
/**
 * Vértice Acadêmico — Adiciona coluna ambiente_id à tabela conselhos_classe
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Alterando tabela conselhos_classe para adicionar coluna ambiente_id...\n";
    
    // Verifica se ambiente_id existe
    $stmt = $db->query("SHOW COLUMNS FROM `conselhos_classe` LIKE 'ambiente_id'");
    $col = $stmt->fetch();
    
    if (!$col) {
        $db->exec("ALTER TABLE `conselhos_classe` ADD `ambiente_id` INT UNSIGNED NULL AFTER `local_reuniao`");
        $db->exec("ALTER TABLE `conselhos_classe` ADD CONSTRAINT `fk_conselhos_ambiente` FOREIGN KEY (`ambiente_id`) REFERENCES `manutencao_ambientes` (`id`) ON DELETE SET NULL");
        echo "Coluna ambiente_id adicionada com sucesso!\n";
    } else {
        echo "Coluna ambiente_id já existe.\n";
    }
    
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
    exit(1);
}
