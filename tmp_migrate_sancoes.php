<?php
require_once __DIR__ . '/includes/auth.php';

$db = getDB();

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `sancao_tipo` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `titulo` varchar(150) NOT NULL,
          `descricao` text,
          `institution_id` int unsigned NOT NULL,
          `is_active` tinyint(1) DEFAULT '1',
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `sancao_acao` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `descricao` varchar(255) NOT NULL,
          `institution_id` int unsigned NOT NULL,
          `is_active` tinyint(1) DEFAULT '1',
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `sancao` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `institution_id` int unsigned NOT NULL,
          `author_id` int unsigned NOT NULL,
          `aluno_id` int unsigned NOT NULL,
          `turma_id` int unsigned NOT NULL,
          `sancao_tipo_id` int unsigned NOT NULL,
          `data_sancao` date NOT NULL,
          `observacoes` text,
          `status` enum('Em aberto', 'Concluído', 'Cancelado') DEFAULT 'Em aberto',
          `anexo_path` varchar(255) DEFAULT NULL,
          `data_conclusao` date DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`aluno_id`) REFERENCES `alunos`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`turma_id`) REFERENCES `turmas`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`sancao_tipo_id`) REFERENCES `sancao_tipo`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `sancao_acoes_rel` (
          `sancao_id` int unsigned NOT NULL,
          `sancao_acao_id` int unsigned NOT NULL,
          PRIMARY KEY (`sancao_id`, `sancao_acao_id`),
          FOREIGN KEY (`sancao_id`) REFERENCES `sancao`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`sancao_acao_id`) REFERENCES `sancao_acao`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // Seed data
    $st = $db->query("SELECT id FROM institutions LIMIT 1");
    $inst = $st->fetch();
    if ($inst) {
        $instId = $inst['id'];
        
        $db->exec("INSERT IGNORE INTO sancao_tipo (id, titulo, descricao, institution_id) VALUES 
            (1, 'Advertência Verbal', 'Aviso formal verbal ao discente', $instId),
            (2, 'Advertência Escrita', 'Documento formal de advertência anexado ao dossiê', $instId),
            (3, 'Suspensão Temporária', 'Afastamento das atividades escolares', $instId),
            (4, 'Termo de Conduta', 'Termo onde o aluno/responsável se compromete com novas condutas', $instId)
        ");
        
        $db->exec("INSERT IGNORE INTO sancao_acao (id, descricao, institution_id) VALUES 
            (1, 'Comunicar Responsáveis', $instId),
            (2, 'Assinatura do Aluno', $instId),
            (3, 'Assinatura dos Responsáveis', $instId),
            (4, 'Reunião com Coordenação/Direção', $instId),
            (5, 'Aviso ao Conselho Tutelar', $instId),
            (6, 'Agendamento com Psicologia/Serviço Social', $instId)
        ");
        
        // Permissoes
        $profiles = ['Administrador', 'Coordenador', 'Diretor', 'Pedagogo'];
        $st = $db->prepare("INSERT IGNORE INTO profile_permissions (profile, resource, can_access, instituicao_id) VALUES (?, 'sancoes.index', 1, ?)");
        $st2 = $db->prepare("INSERT IGNORE INTO profile_permissions (profile, resource, can_access, instituicao_id) VALUES (?, 'sancoes.manage', 1, ?)");
        
        foreach ($profiles as $p) {
            $st->execute([$p, $instId]);
            $st2->execute([$p, $instId]);
        }
    }

    echo "OK - Tabelas e populacao inicial criadas com sucesso.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
