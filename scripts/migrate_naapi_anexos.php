<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    $sql = "
    CREATE TABLE IF NOT EXISTS `alunos_naapi_anexos` (
      `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
      `aluno_id` int UNSIGNED NOT NULL,
      `usuario_id` int UNSIGNED NOT NULL,
      `arquivo` varchar(255) NOT NULL,
      `descricao` varchar(255) DEFAULT NULL,
      `extensao` varchar(10) NOT NULL,
      `tamanho` int UNSIGNED DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_naapi_anexo_aluno` (`aluno_id`),
      CONSTRAINT `fk_naapi_anexo_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_naapi_anexo_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->exec($sql);
    echo "Tabela alunos_naapi_anexos criada com sucesso!\n";
    
    // Criar diretório de uploads se não existir
    $uploadDir = __DIR__ . '/../assets/uploads/naapi';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        echo "Diretório assets/uploads/naapi criado!\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
