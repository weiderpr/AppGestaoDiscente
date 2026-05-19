<?php
/**
 * Vértice Acadêmico — Adiciona colunas de encaminhamento à tabela segunda_chamada
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Alterando tabela segunda_chamada para adicionar campos de encaminhamento...\n";
    
    // Verifica se encaminhado_por_usuario_id existe
    $stmt = $db->query("SHOW COLUMNS FROM `segunda_chamada` LIKE 'encaminhado_por_usuario_id'");
    $colUser = $stmt->fetch();
    
    if (!$colUser) {
        $db->exec("ALTER TABLE `segunda_chamada` ADD `encaminhado_por_usuario_id` INT UNSIGNED NULL AFTER `instrumento_aplicado`");
        echo "Coluna encaminhado_por_usuario_id adicionada com sucesso!\n";
        
        // Adiciona foreign key
        try {
            $db->exec("ALTER TABLE `segunda_chamada` ADD CONSTRAINT `fk_sc_encaminhado_por` FOREIGN KEY (`encaminhado_por_usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
            echo "Constraint FK adicionada com sucesso!\n";
        } catch (Exception $fkEx) {
            echo "Aviso ao adicionar FK (pode ser que já exista ou tabela seja MyISAM): " . $fkEx->getMessage() . "\n";
        }
    } else {
        echo "Coluna encaminhado_por_usuario_id já existe.\n";
    }

    // Verifica se data_encaminhamento existe
    $stmt = $db->query("SHOW COLUMNS FROM `segunda_chamada` LIKE 'data_encaminhamento'");
    $colDate = $stmt->fetch();
    
    if (!$colDate) {
        $db->exec("ALTER TABLE `segunda_chamada` ADD `data_encaminhamento` TIMESTAMP NULL AFTER `encaminhado_por_usuario_id`");
        echo "Coluna data_encaminhamento adicionada com sucesso!\n";
    } else {
        echo "Coluna data_encaminhamento já existe.\n";
    }
    
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
    exit(1);
}
