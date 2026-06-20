-- ══════════════════════════════════════════════════════════════════════
-- Migração: Configurações NAAPI — mesma sala e uso de volante
--
-- Adiciona em `somativas`:
--   naapi_mesma_sala   — sala NAAPI compartilhada entre todas as turmas
--   naapi_usa_volante  — se o NAAPI terá volante dedicado
--
-- Adiciona em `somativa_grade`:
--   naapi_volante_id   — volante da sala NAAPI
--
-- Como rodar:
--   mysql -u USER -p vertice_academico < sql/migrations/add_naapi_mesma_sala.sql
-- ══════════════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS _migrate_naapi_mesma_sala;

DELIMITER $$

CREATE PROCEDURE _migrate_naapi_mesma_sala()
BEGIN
    -- 1. naapi_mesma_sala em somativas
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'somativas'
          AND COLUMN_NAME  = 'naapi_mesma_sala'
    ) THEN
        ALTER TABLE `somativas`
            ADD COLUMN `naapi_mesma_sala` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
                COMMENT 'Sala NAAPI compartilhada entre todas as turmas no mesmo horário'
                AFTER `naapi_tempo_extra_min`;
    END IF;

    -- 2. naapi_usa_volante em somativas
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'somativas'
          AND COLUMN_NAME  = 'naapi_usa_volante'
    ) THEN
        ALTER TABLE `somativas`
            ADD COLUMN `naapi_usa_volante` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
                COMMENT 'Se o NAAPI terá volante dedicado (além do aplicador)'
                AFTER `naapi_mesma_sala`;
    END IF;

    -- 3. naapi_volante_id em somativa_grade
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'somativa_grade'
          AND COLUMN_NAME  = 'naapi_volante_id'
    ) THEN
        ALTER TABLE `somativa_grade`
            ADD COLUMN `naapi_volante_id` INT UNSIGNED NULL DEFAULT NULL
                COMMENT 'Volante da sala NAAPI neste slot'
                AFTER `naapi_aplicador_id`;
    END IF;

    -- 4. FK naapi_volante_id → users
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA    = DATABASE()
          AND TABLE_NAME      = 'somativa_grade'
          AND CONSTRAINT_NAME = 'fk_sg_naapi_volante'
    ) THEN
        ALTER TABLE `somativa_grade`
            ADD CONSTRAINT `fk_sg_naapi_volante`
                FOREIGN KEY (`naapi_volante_id`)
                REFERENCES `users` (`id`)
                ON DELETE SET NULL;
    END IF;
END$$

DELIMITER ;

CALL _migrate_naapi_mesma_sala();
DROP PROCEDURE IF EXISTS _migrate_naapi_mesma_sala;
