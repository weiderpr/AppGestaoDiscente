<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    $sql = "
    CREATE TABLE IF NOT EXISTS `alunos_naapi` (
      `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
      `aluno_id` int UNSIGNED NOT NULL,
      `institution_id` int UNSIGNED NOT NULL,
      `data_inclusao` date NOT NULL,
      `neurodivergencia` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `campo_texto` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `observacoes_publicas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_naapi_aluno` (`aluno_id`),
      KEY `idx_naapi_institution` (`institution_id`),
      CONSTRAINT `fk_naapi_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_naapi_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->exec($sql);
    echo "Tabela alunos_naapi criada com sucesso!\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
