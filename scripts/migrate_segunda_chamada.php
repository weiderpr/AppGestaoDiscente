<?php
/**
 * Vértice Acadêmico — Migration para Segunda Chamada
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Criando tabela segunda_chamada...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS `segunda_chamada` (
      `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
      `aluno_id` int UNSIGNED NOT NULL,
      `telefone_aluno` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
      `email_aluno` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
      `nome_responsavel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `telefone_responsavel` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `disciplina_codigo` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
      `justificativa` text COLLATE utf8mb4_unicode_ci NOT NULL,
      `anexo_caminho` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `anexo_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `anexo_tipo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `anexo_tamanho` int UNSIGNED DEFAULT NULL,
      `data_atividade_perdida` date NOT NULL,
      `institution_id` int UNSIGNED NOT NULL,
      `usuario_id` int UNSIGNED NOT NULL,
      `status` enum('Pendente','Deferido','Indeferido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pendente',
      `observacoes_status` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_sc_aluno` (`aluno_id`),
      KEY `idx_sc_disciplina` (`disciplina_codigo`),
      KEY `idx_sc_institution` (`institution_id`),
      KEY `idx_sc_usuario` (`usuario_id`),
      CONSTRAINT `fk_sc_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_sc_disciplina` FOREIGN KEY (`disciplina_codigo`) REFERENCES `disciplinas` (`codigo`) ON DELETE CASCADE,
      CONSTRAINT `fk_sc_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_sc_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->exec($sql);
    echo "Tabela segunda_chamada criada com sucesso!\n";
    
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
    exit(1);
}
