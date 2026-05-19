<?php
/**
 * Vértice Acadêmico — Adiciona coluna atividade_nome à tabela segunda_chamada
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Alterando tabela segunda_chamada para adicionar atividade_nome...\n";
    
    // Verifica se a coluna já existe
    $stmt = $db->query("SHOW COLUMNS FROM `segunda_chamada` LIKE 'atividade_nome'");
    $column = $stmt->fetch();
    
    if (!$column) {
        $db->exec("ALTER TABLE `segunda_chamada` ADD `atividade_nome` VARCHAR(255) NOT NULL AFTER `disciplina_codigo`");
        echo "Coluna atividade_nome adicionada com sucesso!\n";
    } else {
        echo "Coluna atividade_nome já existe.\n";
    }
    
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
    exit(1);
}
