-- =============================================================
-- Vértice Acadêmico — Migração: Configuração NAAPI nas Somativas
--
-- Separa o NAAPI da Segunda Chamada, tratando-o como atributo
-- da própria prova (mesma data/horário, sala e aplicador distintos).
--
-- Compatível com MySQL 8.0 (usa procedure para ADD COLUMN idempotente).
--
-- Execute:
--   mysql -u USER -p vertice_academico < sql/migrations/add_naapi_config.sql
-- =============================================================

DROP PROCEDURE IF EXISTS _migrate_add_naapi_config;

DELIMITER $$

CREATE PROCEDURE _migrate_add_naapi_config()
BEGIN

    -- 1. naapi_ambiente_id em somativas
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'somativas'
          AND COLUMN_NAME  = 'naapi_ambiente_id'
    ) THEN
        ALTER TABLE `somativas`
            ADD COLUMN `naapi_ambiente_id` int unsigned NULL DEFAULT NULL
                COMMENT 'Sala global de aplicação NAAPI — NULL = NAAPI não configurado'
                AFTER `segunda_chamada_data`;
    END IF;

    -- 2. naapi_tempo_extra_min em somativas
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'somativas'
          AND COLUMN_NAME  = 'naapi_tempo_extra_min'
    ) THEN
        ALTER TABLE `somativas`
            ADD COLUMN `naapi_tempo_extra_min` smallint unsigned NOT NULL DEFAULT 60
                COMMENT 'Tempo extra em minutos por prova para alunos NAAPI (informativo)'
                AFTER `naapi_ambiente_id`;
    END IF;

    -- 3. FK naapi_ambiente_id → manutencao_ambientes (apenas se não existir)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA    = DATABASE()
          AND TABLE_NAME      = 'somativas'
          AND CONSTRAINT_NAME = 'fk_som_naapi_ambiente'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE `somativas`
            ADD CONSTRAINT `fk_som_naapi_ambiente`
                FOREIGN KEY (`naapi_ambiente_id`)
                REFERENCES `manutencao_ambientes` (`id`)
                ON DELETE SET NULL;
    END IF;

    -- 4. naapi_aplicador_id em somativa_grade
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'somativa_grade'
          AND COLUMN_NAME  = 'naapi_aplicador_id'
    ) THEN
        ALTER TABLE `somativa_grade`
            ADD COLUMN `naapi_aplicador_id` int unsigned NULL DEFAULT NULL
                COMMENT 'Professor aplicador NAAPI neste slot — NULL se NAAPI inativo ou não definido'
                AFTER `volante_id`;
    END IF;

    -- 5. FK naapi_aplicador_id → users (apenas se não existir)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA    = DATABASE()
          AND TABLE_NAME      = 'somativa_grade'
          AND CONSTRAINT_NAME = 'fk_sg_naapi_aplicador'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE `somativa_grade`
            ADD CONSTRAINT `fk_sg_naapi_aplicador`
                FOREIGN KEY (`naapi_aplicador_id`)
                REFERENCES `users` (`id`)
                ON DELETE SET NULL;
    END IF;

END$$

DELIMITER ;

CALL _migrate_add_naapi_config();
DROP PROCEDURE IF EXISTS _migrate_add_naapi_config;
