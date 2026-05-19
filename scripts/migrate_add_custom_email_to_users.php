<?php
/**
 * Vértice Acadêmico — Migration para adicionar email personalizado de Segunda Chamada no perfil de usuários
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Verificando se a coluna segundachamada_custom_email já existe...\n";
    
    // Verifica se a coluna já existe
    $check = $db->query("SHOW COLUMNS FROM `users` LIKE 'segundachamada_custom_email'")->fetch();
    
    if (!$check) {
        echo "Adicionando coluna segundachamada_custom_email na tabela users...\n";
        $db->exec("ALTER TABLE `users` ADD COLUMN `segundachamada_custom_email` varchar(255) DEFAULT NULL;");
        echo "Coluna segundachamada_custom_email adicionada com sucesso!\n";
    } else {
        echo "A coluna segundachamada_custom_email já existe na tabela users.\n";
    }
    
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
    exit(1);
}
