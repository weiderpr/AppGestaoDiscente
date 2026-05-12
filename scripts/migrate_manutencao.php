<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Iniciando migração de Manutenção...\n";

    // Table: manutencao_ambientes
    $db->exec("CREATE TABLE IF NOT EXISTS `manutencao_ambientes` (
      `id` int unsigned NOT NULL AUTO_INCREMENT,
      `institution_id` int unsigned NOT NULL,
      `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
      `predio_campus` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
      `status` enum('Ativo','Inativo') COLLATE utf8mb4_unicode_ci DEFAULT 'Ativo',
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_ma_inst` (`institution_id`),
      CONSTRAINT `fk_ma_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Tabela manutencao_ambientes criada/verificada.\n";

    // Table: manutencao_problemas_padrao
    $db->exec("CREATE TABLE IF NOT EXISTS `manutencao_problemas_padrao` (
      `id` int unsigned NOT NULL AUTO_INCREMENT,
      `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Tabela manutencao_problemas_padrao criada/verificada.\n";

    // Table: manutencao_ambiente_problemas (Pivot)
    $db->exec("CREATE TABLE IF NOT EXISTS `manutencao_ambiente_problemas` (
      `ambiente_id` int unsigned NOT NULL,
      `problema_id` int unsigned NOT NULL,
      PRIMARY KEY (`ambiente_id`,`problema_id`),
      KEY `idx_map_prob` (`problema_id`),
      CONSTRAINT `fk_map_amb` FOREIGN KEY (`ambiente_id`) REFERENCES `manutencao_ambientes` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_map_prob` FOREIGN KEY (`problema_id`) REFERENCES `manutencao_problemas_padrao` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Tabela manutencao_ambiente_problemas criada/verificada.\n";

    // Seeds for standard problems
    $stmt = $db->query("SELECT COUNT(*) FROM manutencao_problemas_padrao");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO manutencao_problemas_padrao (descricao) VALUES 
        ('Lâmpada'), 
        ('Quadro'), 
        ('Tomada'), 
        ('Projetor');");
        echo "Problemas padrão semeados.\n";
    } else {
        echo "Problemas padrão já existem. Pulando semeadura.\n";
    }

    echo "Migração concluída com sucesso!\n";

} catch (Exception $e) {
    echo "ERRO NA MIGRAÇÃO: " . $e->getMessage() . "\n";
}
