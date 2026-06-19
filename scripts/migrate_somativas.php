<?php
/**
 * Vértice Acadêmico — Migração: Módulo de Avaliações Somativas
 *
 * Execute: php scripts/migrate_somativas.php
 *
 * Seguro para rodar múltiplas vezes (idempotente).
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    echo "Iniciando migração do módulo Somativas...\n\n";

    // ── 1. Coluna tem_somativa em courses ──────────────────────────────────────
    $cols = $db->query("SHOW COLUMNS FROM `courses` LIKE 'tem_somativa'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE `courses`
            ADD COLUMN `tem_somativa` tinyint(1) NOT NULL DEFAULT 0
            COMMENT 'Indica que o curso participa de avaliações somativas'
            AFTER `location`");
        echo "✓ Coluna courses.tem_somativa adicionada.\n";
    } else {
        echo "· courses.tem_somativa já existe. Pulando.\n";
    }

    // ── 2. Tabela somativas ────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `somativas` (
      `id`                 int unsigned    NOT NULL AUTO_INCREMENT,
      `institution_id`     int unsigned    NOT NULL,
      `nome`               varchar(255)    COLLATE utf8mb4_unicode_ci NOT NULL,
      `descricao`          text            COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `data_inicio`        date            NOT NULL,
      `data_fim`           date            NOT NULL,
      `max_provas_por_dia` tinyint unsigned NOT NULL DEFAULT 2,
      `status`             enum('Rascunho','Configurando','Publicado','Encerrado')
                           COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Rascunho',
      `created_by`         int unsigned    NOT NULL,
      `created_at`         timestamp       NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`         timestamp       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_som_inst`       (`institution_id`),
      KEY `idx_som_created_by` (`created_by`),
      KEY `idx_som_status`     (`status`),
      CONSTRAINT `fk_som_inst`    FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_som_creator` FOREIGN KEY (`created_by`)     REFERENCES `users`        (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Tabela somativas criada/verificada.\n";

    // ── 3. Tabela somativa_slots_config ────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `somativa_slots_config` (
      `id`             int unsigned NOT NULL AUTO_INCREMENT,
      `somativa_id`    int unsigned NOT NULL,
      `ordem`          tinyint unsigned NOT NULL DEFAULT 1,
      `horario_inicio` time NOT NULL,
      `horario_fim`    time NOT NULL,
      `label`          varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_ssc_som` (`somativa_id`),
      CONSTRAINT `fk_ssc_som` FOREIGN KEY (`somativa_id`) REFERENCES `somativas` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Tabela somativa_slots_config criada/verificada.\n";

    // ── 4. Tabela somativa_turmas ──────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `somativa_turmas` (
      `id`          int unsigned NOT NULL AUTO_INCREMENT,
      `somativa_id` int unsigned NOT NULL,
      `turma_id`    int unsigned NOT NULL,
      `created_at`  timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_som_turma` (`somativa_id`, `turma_id`),
      KEY `idx_st_turma` (`turma_id`),
      CONSTRAINT `fk_st_som`   FOREIGN KEY (`somativa_id`) REFERENCES `somativas` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_st_turma` FOREIGN KEY (`turma_id`)    REFERENCES `turmas`    (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Tabela somativa_turmas criada/verificada.\n";

    // ── 5. Tabela somativa_disciplinas ─────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `somativa_disciplinas` (
      `id`                  int unsigned NOT NULL AUTO_INCREMENT,
      `somativa_turma_id`   int unsigned NOT NULL,
      `disciplina_codigo`   varchar(15)  COLLATE utf8mb4_unicode_ci NOT NULL,
      `created_at`          timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_std_disc` (`somativa_turma_id`, `disciplina_codigo`),
      KEY `idx_sd_disc` (`disciplina_codigo`),
      CONSTRAINT `fk_sd_st`   FOREIGN KEY (`somativa_turma_id`) REFERENCES `somativa_turmas` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_sd_disc` FOREIGN KEY (`disciplina_codigo`) REFERENCES `disciplinas`      (`codigo`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Tabela somativa_disciplinas criada/verificada.\n";

    // ── 6. Tabela somativa_grade ───────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `somativa_grade` (
      `id`                      int unsigned NOT NULL AUTO_INCREMENT,
      `somativa_id`             int unsigned NOT NULL,
      `somativa_turma_id`       int unsigned NOT NULL,
      `somativa_disciplina_id`  int unsigned DEFAULT NULL,
      `data_prova`              date         NOT NULL,
      `slot_config_id`          int unsigned NOT NULL,
      `aplicador_id`            int unsigned DEFAULT NULL,
      `volante_id`              int unsigned DEFAULT NULL,
      `ambiente_id`             int unsigned DEFAULT NULL,
      `tipo`                    enum('Normal','Segunda Chamada','NAAPI')
                                COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Normal',
      `observacoes`             text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `created_by`              int unsigned NOT NULL,
      `created_at`              timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`              timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_grade_slot` (`somativa_turma_id`, `data_prova`, `slot_config_id`),
      KEY `idx_sg_som`       (`somativa_id`),
      KEY `idx_sg_aplicador` (`aplicador_id`),
      KEY `idx_sg_volante`   (`volante_id`),
      KEY `idx_sg_ambiente`  (`ambiente_id`),
      KEY `idx_sg_slot`      (`slot_config_id`),
      CONSTRAINT `fk_sg_som`       FOREIGN KEY (`somativa_id`)            REFERENCES `somativas`            (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_sg_st`        FOREIGN KEY (`somativa_turma_id`)      REFERENCES `somativa_turmas`      (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_sg_sd`        FOREIGN KEY (`somativa_disciplina_id`) REFERENCES `somativa_disciplinas` (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_sg_slot`      FOREIGN KEY (`slot_config_id`)         REFERENCES `somativa_slots_config`(`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_sg_aplicador` FOREIGN KEY (`aplicador_id`)           REFERENCES `users`                (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_sg_volante`   FOREIGN KEY (`volante_id`)             REFERENCES `users`                (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_sg_creator`   FOREIGN KEY (`created_by`)             REFERENCES `users`                (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // FK para manutencao_ambientes (pode não existir em todas as instalações)
    $ambTable = $db->query("SHOW TABLES LIKE 'manutencao_ambientes'")->fetch();
    if ($ambTable) {
        // Verifica se a FK já existe antes de criar
        $fkExists = $db->query(
            "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'somativa_grade'
               AND CONSTRAINT_NAME = 'fk_sg_ambiente'"
        )->fetchColumn();

        if (!$fkExists) {
            $db->exec("ALTER TABLE `somativa_grade`
                ADD CONSTRAINT `fk_sg_ambiente`
                FOREIGN KEY (`ambiente_id`) REFERENCES `manutencao_ambientes`(`id`) ON DELETE SET NULL");
            echo "✓ FK somativa_grade → manutencao_ambientes adicionada.\n";
        } else {
            echo "· FK fk_sg_ambiente já existe. Pulando.\n";
        }
    } else {
        echo "· Tabela manutencao_ambientes não encontrada — FK de ambiente ignorada.\n";
    }

    echo "✓ Tabela somativa_grade criada/verificada.\n";

    // ── 7. Permissões RBAC ────────────────────────────────────────────────────
    echo "\nRegistrando permissões RBAC...\n";

    $resources = [
        'somativas.index'    => ['Administrador', 'Coordenador', 'Diretor', 'Pedagogo'],
        'somativas.create'   => ['Administrador', 'Coordenador'],
        'somativas.update'   => ['Administrador', 'Coordenador'],
        'somativas.delete'   => ['Administrador'],
        'somativas.view_all' => ['Administrador', 'Coordenador', 'Diretor'],
    ];

    $institutions = $db->query("SELECT id, name FROM institutions WHERE is_active = 1")->fetchAll();
    $stmt = $db->prepare(
        "INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id)
         VALUES (?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE can_access = 1, updated_at = NOW()"
    );

    foreach ($institutions as $inst) {
        foreach ($resources as $resource => $profiles) {
            foreach ($profiles as $profile) {
                $stmt->execute([$profile, $resource, $inst['id']]);
            }
        }
        echo "  ✓ Permissões registradas para: {$inst['name']}\n";
    }

    echo "\n" . str_repeat('─', 50) . "\n";
    echo "✅ Migração concluída com sucesso!\n";
    echo "\nPróximos passos:\n";
    echo "  → Acesse o menu Acadêmico > Horário de Somativas\n";

} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
