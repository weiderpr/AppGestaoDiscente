-- =============================================================
-- Vértice Acadêmico — Migração: Turma→Ambiente + Restrições de Somativa
-- Execute: mysql -u USER -p vertice_academico < sql/migrations/add_turma_ambiente_restricoes.sql
-- =============================================================

-- 1. Associação turma → sala principal (ambiente)
ALTER TABLE `turmas`
    ADD COLUMN IF NOT EXISTS `ambiente_id` INT UNSIGNED NULL
        COMMENT 'Sala principal da turma para aplicação de provas'
        AFTER `media_aprovacao`;

ALTER TABLE `turmas`
    ADD CONSTRAINT IF NOT EXISTS `fk_turmas_ambiente`
        FOREIGN KEY (`ambiente_id`) REFERENCES `manutencao_ambientes`(`id`) ON DELETE SET NULL;

-- 2. Data preferencial para segunda chamada na somativa
ALTER TABLE `somativas`
    ADD COLUMN IF NOT EXISTS `segunda_chamada_data` DATE NULL
        COMMENT 'Data reservada para 2ª Chamada/NAAPI; NULL = último dia da somativa'
        AFTER `max_provas_por_dia`;

-- 3. Tabela de restrições customizadas de agendamento
--    Categorias suportadas pelo SomativaScheduler:
--      bloquear_data        → {"data":"2026-07-04","motivo":"Feriado"}
--      evitar_mesmo_dia     → {"disciplinas":["QUI","FIS"]}
--      mesmo_dia_turmas     → {"disciplina_codigo":"MAT"}
--      professor_indisponivel → {"professor_id":5,"datas":["2026-07-01"]}
CREATE TABLE IF NOT EXISTS `somativa_restricoes` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `somativa_id` INT UNSIGNED    NOT NULL,
  `tipo`        ENUM('hard','soft') NOT NULL DEFAULT 'soft'
                    COMMENT 'hard = bloqueia; soft = penaliza/bonifica',
  `categoria`   VARCHAR(50)     COLLATE utf8mb4_unicode_ci NOT NULL,
  `params`      JSON            NOT NULL,
  `peso`        TINYINT UNSIGNED NOT NULL DEFAULT 5
                    COMMENT 'Peso (1-10) para soft constraints; ignorado em hard',
  `descricao`   VARCHAR(255)    COLLATE utf8mb4_unicode_ci NULL
                    COMMENT 'Descrição legível para exibição na interface',
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_by`  INT UNSIGNED    NULL,
  `created_at`  TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sr_somativa`  (`somativa_id`),
  KEY `idx_sr_categoria` (`categoria`),
  CONSTRAINT `fk_sr_somativa`    FOREIGN KEY (`somativa_id`) REFERENCES `somativas`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sr_created_by`  FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
