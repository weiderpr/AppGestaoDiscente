<?php
require_once __DIR__ . '/../config/database.php';
try {
    $db = getDB();
    
    // Fix existing tables to be UNSIGNED to match core system
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    $db->exec("ALTER TABLE manutencao_ambientes MODIFY id INT UNSIGNED AUTO_INCREMENT;");
    $db->exec("ALTER TABLE manutencao_ambientes MODIFY institution_id INT UNSIGNED NOT NULL;");
    
    $db->exec("ALTER TABLE manutencao_problemas_padrao MODIFY id INT UNSIGNED AUTO_INCREMENT;");
    
    $db->exec("ALTER TABLE manutencao_ambiente_problemas MODIFY ambiente_id INT UNSIGNED NOT NULL;");
    $db->exec("ALTER TABLE manutencao_ambiente_problemas MODIFY problema_id INT UNSIGNED NOT NULL;");
    
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "Tabelas base de manutenção ajustadas para UNSIGNED.\n";
} catch (Exception $e) {
    echo "Erro ao ajustar tipos: " . $e->getMessage() . "\n";
}
