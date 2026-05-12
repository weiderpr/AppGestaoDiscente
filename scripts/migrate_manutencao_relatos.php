<?php
/**
 * Vértice Acadêmico — Migration: Tabela de Relatos via QR Code
 * Execute uma única vez no ambiente de produção.
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

$sql = "
CREATE TABLE IF NOT EXISTS `manutencao_relatos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ambiente_id` int unsigned NOT NULL COMMENT 'Ambiente onde o problema foi relatado',
  `user_id` int unsigned DEFAULT NULL COMMENT 'Usuário logado que relatou (nullable)',
  `nome_relator` varchar(255) DEFAULT NULL COMMENT 'Nome informado por visitante',
  `email_relator` varchar(255) DEFAULT NULL COMMENT 'Email informado por visitante',
  `descricao` text NOT NULL COMMENT 'Descrição do problema',
  `comentario` text DEFAULT NULL COMMENT 'Comentário adicional do relator',
  `outros_detalhes` text DEFAULT NULL COMMENT 'Detalhes quando selecionado Outros',
  `status` enum('Demandas','Em Aberto','Em Execução','Finalizado') NOT NULL DEFAULT 'Demandas',
  `manutencao_id` int unsigned DEFAULT NULL COMMENT 'ID da manutenção gerada a partir deste relato',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mr_ambiente` (`ambiente_id`),
  KEY `idx_mr_user` (`user_id`),
  CONSTRAINT `fk_mr_ambiente` FOREIGN KEY (`ambiente_id`) REFERENCES `manutencao_ambientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `manutencao_relato_problemas` (
  `relato_id` int unsigned NOT NULL,
  `problema_id` int unsigned NOT NULL,
  PRIMARY KEY (`relato_id`, `problema_id`),
  CONSTRAINT `fk_mrp_relato` FOREIGN KEY (`relato_id`) REFERENCES `manutencao_relatos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mrp_problema` FOREIGN KEY (`problema_id`) REFERENCES `manutencao_problemas_padrao` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql);
    echo "✅ Tabelas criadas com sucesso.\n";
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
