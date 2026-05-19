<?php
/**
 * Vértice Acadêmico — Adiciona coluna usuario_id à tabela manutencoes
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Alterando tabela manutencoes para adicionar coluna usuario_id...\n";
    
    // Verifica se usuario_id existe
    $stmt = $db->query("SHOW COLUMNS FROM `manutencoes` LIKE 'usuario_id'");
    $col = $stmt->fetch();
    
    if (!$col) {
        $db->exec("ALTER TABLE `manutencoes` ADD `usuario_id` INT UNSIGNED NULL AFTER `institution_id`");
        $db->exec("ALTER TABLE `manutencoes` ADD CONSTRAINT `fk_manutencoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
        echo "Coluna usuario_id adicionada com sucesso!\n";
    } else {
        echo "Coluna usuario_id já existe.\n";
    }
    
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
    exit(1);
}
