-- =============================================================
-- Vértice Acadêmico — Migração: Módulo de Avaliações Somativas
-- Execute: mysql -u USER -p vertice_academico < sql/migrations/create_somativas.sql
-- =============================================================

-- 1. Flag de somativa no cadastro de cursos
ALTER TABLE `courses`
    ADD COLUMN IF NOT EXISTS `tem_somativa` tinyint(1) NOT NULL DEFAULT 0
    COMMENT 'Indica que o curso participa de avaliações somativas'
    AFTER `location`;

-- 2. Registro principal da avaliação somativa
CREATE TABLE IF NOT EXISTS `somativas` (
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
  CONSTRAINT `fk_som_creator` FOREIGN KEY (`created_by`)     REFERENCES `users`         (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Slots de horário configurados para cada somativa
--    Ex: Slot 1 = 07:30–08:30, Slot 2 = 08:30–09:30 ...
CREATE TABLE IF NOT EXISTS `somativa_slots_config` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `somativa_id`    int unsigned NOT NULL,
  `ordem`          tinyint unsigned NOT NULL DEFAULT 1,
  `horario_inicio` time NOT NULL,
  `horario_fim`    time NOT NULL,
  `label`          varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rótulo opcional, ex: "Manhã 1"',
  PRIMARY KEY (`id`),
  KEY `idx_ssc_som` (`somativa_id`),
  CONSTRAINT `fk_ssc_som` FOREIGN KEY (`somativa_id`) REFERENCES `somativas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Turmas participantes da somativa
CREATE TABLE IF NOT EXISTS `somativa_turmas` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `somativa_id` int unsigned NOT NULL,
  `turma_id`    int unsigned NOT NULL,
  `created_at`  timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_som_turma` (`somativa_id`, `turma_id`),
  KEY `idx_st_turma` (`turma_id`),
  CONSTRAINT `fk_st_som`   FOREIGN KEY (`somativa_id`) REFERENCES `somativas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_st_turma` FOREIGN KEY (`turma_id`)    REFERENCES `turmas`    (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Disciplinas que farão prova por turma dentro desta somativa
CREATE TABLE IF NOT EXISTS `somativa_disciplinas` (
  `id`                  int unsigned NOT NULL AUTO_INCREMENT,
  `somativa_turma_id`   int unsigned NOT NULL,
  `disciplina_codigo`   varchar(15)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at`          timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_std_disc` (`somativa_turma_id`, `disciplina_codigo`),
  KEY `idx_sd_disc` (`disciplina_codigo`),
  CONSTRAINT `fk_sd_st`   FOREIGN KEY (`somativa_turma_id`) REFERENCES `somativa_turmas`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sd_disc` FOREIGN KEY (`disciplina_codigo`) REFERENCES `disciplinas`       (`codigo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Grade de horários: alocações (célula = turma × data × slot)
CREATE TABLE IF NOT EXISTS `somativa_grade` (
  `id`                      int unsigned NOT NULL AUTO_INCREMENT,
  `somativa_id`             int unsigned NOT NULL,
  `somativa_turma_id`       int unsigned NOT NULL,
  `somativa_disciplina_id`  int unsigned DEFAULT NULL COMMENT 'NULL = slot reservado sem disciplina',
  `data_prova`              date         NOT NULL,
  `slot_config_id`          int unsigned NOT NULL,
  `aplicador_id`            int unsigned DEFAULT NULL COMMENT 'Professor que aplica a prova',
  `volante_id`              int unsigned DEFAULT NULL COMMENT 'Professor volante/fiscal',
  `ambiente_id`             int unsigned DEFAULT NULL COMMENT 'Sala/ambiente da prova',
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
  CONSTRAINT `fk_sg_som`        FOREIGN KEY (`somativa_id`)            REFERENCES `somativas`           (`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_sg_st`         FOREIGN KEY (`somativa_turma_id`)      REFERENCES `somativa_turmas`     (`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_sg_sd`         FOREIGN KEY (`somativa_disciplina_id`) REFERENCES `somativa_disciplinas`(`id`)     ON DELETE SET NULL,
  CONSTRAINT `fk_sg_slot`       FOREIGN KEY (`slot_config_id`)         REFERENCES `somativa_slots_config`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_sg_aplicador`  FOREIGN KEY (`aplicador_id`)           REFERENCES `users`               (`id`)     ON DELETE SET NULL,
  CONSTRAINT `fk_sg_volante`    FOREIGN KEY (`volante_id`)             REFERENCES `users`               (`id`)     ON DELETE SET NULL,
  CONSTRAINT `fk_sg_ambiente`   FOREIGN KEY (`ambiente_id`)            REFERENCES `manutencao_ambientes`(`id`)     ON DELETE SET NULL,
  CONSTRAINT `fk_sg_creator`    FOREIGN KEY (`created_by`)             REFERENCES `users`               (`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
