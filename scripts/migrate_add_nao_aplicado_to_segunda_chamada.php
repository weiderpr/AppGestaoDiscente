<?php
/**
 * Vértice Acadêmico — Adiciona colunas para não aplicação à tabela segunda_chamada
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Alterando tabela segunda_chamada para adicionar campos de não aplicação...\n";
    
    // Verifica se nao_aplicado existe
    $stmt = $db->query("SHOW COLUMNS FROM `segunda_chamada` LIKE 'nao_aplicado'");
    $colNao = $stmt->fetch();
    
    if (!$colNao) {
        $db->exec("ALTER TABLE `segunda_chamada` ADD `nao_aplicado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `instrumento_aplicado`");
        echo "Coluna nao_aplicado adicionada com sucesso!\n";
    } else {
        echo "Coluna nao_aplicado já existe.\n";
    }

    // Verifica se justificativa_nao_aplicacao existe
    $stmt = $db->query("SHOW COLUMNS FROM `segunda_chamada` LIKE 'justificativa_nao_aplicacao'");
    $colJust = $stmt->fetch();
    
    if (!$colJust) {
        $db->exec("ALTER TABLE `segunda_chamada` ADD `justificativa_nao_aplicacao` TEXT NULL AFTER `nao_aplicado`");
        echo "Coluna justificativa_nao_aplicacao adicionada com sucesso!\n";
    } else {
        echo "Coluna justificativa_nao_aplicacao já existe.\n";
    }
    
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
    exit(1);
}
