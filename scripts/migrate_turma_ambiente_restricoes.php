<?php
/**
 * Vértice Acadêmico — Migração: Turma→Ambiente + Restrições de Somativa
 *
 * Execução: php scripts/migrate_turma_ambiente_restricoes.php
 *
 * O que faz:
 *  1. Adiciona turmas.ambiente_id (se não existir)
 *  2. Adiciona somativas.segunda_chamada_data (se não existir)
 *  3. Cria tabela somativa_restricoes (se não existir)
 */

require_once __DIR__ . '/../config/database.php';

$db = getDB();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec('SET FOREIGN_KEY_CHECKS = 0');

$ok  = 0;
$skip = 0;

function colExists(PDO $db, string $table, string $col): bool {
    $row = $db->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = '{$table}'
           AND COLUMN_NAME  = '{$col}'"
    )->fetchColumn();
    return (int)$row > 0;
}

function fkExists(PDO $db, string $table, string $fk): bool {
    $row = $db->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA   = DATABASE()
           AND TABLE_NAME     = '{$table}'
           AND CONSTRAINT_NAME= '{$fk}'"
    )->fetchColumn();
    return (int)$row > 0;
}

function tableExists(PDO $db, string $table): bool {
    $row = $db->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = '{$table}'"
    )->fetchColumn();
    return (int)$row > 0;
}

function run(PDO $db, string $label, string $sql): void {
    global $ok, $skip;
    try {
        $db->exec($sql);
        echo "  ✅ {$label}\n";
        $ok++;
    } catch (PDOException $e) {
        echo "  ❌ {$label}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Migração: Turma→Ambiente + Restrições de Somativa ===\n\n";

// ── 1. turmas.ambiente_id ─────────────────────────────────────

echo "1. turmas.ambiente_id\n";
if (!colExists($db, 'turmas', 'ambiente_id')) {
    run($db, 'ADD COLUMN turmas.ambiente_id', "
        ALTER TABLE `turmas`
        ADD COLUMN `ambiente_id` INT UNSIGNED NULL
            COMMENT 'Sala principal da turma para aplicação de provas'
        AFTER `media_aprovacao`
    ");
} else {
    echo "  ⏭ turmas.ambiente_id já existe — ignorado\n";
    $skip++;
}

if (!fkExists($db, 'turmas', 'fk_turmas_ambiente') && colExists($db, 'turmas', 'ambiente_id')) {
    run($db, 'ADD FK fk_turmas_ambiente', "
        ALTER TABLE `turmas`
        ADD CONSTRAINT `fk_turmas_ambiente`
            FOREIGN KEY (`ambiente_id`)
            REFERENCES `manutencao_ambientes`(`id`)
            ON DELETE SET NULL
    ");
} else {
    if (fkExists($db, 'turmas', 'fk_turmas_ambiente')) {
        echo "  ⏭ FK fk_turmas_ambiente já existe — ignorado\n";
        $skip++;
    }
}

// ── 2. somativas.segunda_chamada_data ─────────────────────────

echo "\n2. somativas.segunda_chamada_data\n";
if (!colExists($db, 'somativas', 'segunda_chamada_data')) {
    run($db, 'ADD COLUMN somativas.segunda_chamada_data', "
        ALTER TABLE `somativas`
        ADD COLUMN `segunda_chamada_data` DATE NULL
            COMMENT 'Data reservada para 2ª Chamada/NAAPI; NULL = último dia da somativa'
        AFTER `max_provas_por_dia`
    ");
} else {
    echo "  ⏭ somativas.segunda_chamada_data já existe — ignorado\n";
    $skip++;
}

// ── 3. somativa_restricoes ────────────────────────────────────

echo "\n3. somativa_restricoes\n";
if (!tableExists($db, 'somativa_restricoes')) {
    run($db, 'CREATE TABLE somativa_restricoes', "
        CREATE TABLE `somativa_restricoes` (
          `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
          `somativa_id` INT UNSIGNED    NOT NULL,
          `tipo`        ENUM('hard','soft') NOT NULL DEFAULT 'soft'
                            COMMENT 'hard = bloqueia; soft = penaliza/bonifica',
          `categoria`   VARCHAR(50)     COLLATE utf8mb4_unicode_ci NOT NULL,
          `params`      JSON            NOT NULL,
          `peso`        TINYINT UNSIGNED NOT NULL DEFAULT 5
                            COMMENT 'Peso (1-10) para soft constraints; ignorado em hard',
          `descricao`   VARCHAR(255)    COLLATE utf8mb4_unicode_ci NULL,
          `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
          `created_by`  INT UNSIGNED    NULL,
          `created_at`  TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at`  TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_sr_somativa`  (`somativa_id`),
          KEY `idx_sr_categoria` (`categoria`),
          CONSTRAINT `fk_sr_somativa`   FOREIGN KEY (`somativa_id`) REFERENCES `somativas`(`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_sr_created_by` FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)     ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} else {
    echo "  ⏭ somativa_restricoes já existe — ignorado\n";
    $skip++;
}

// ─────────────────────────────────────────────────────────────

$db->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "\n=== Concluído: {$ok} executado(s), {$skip} ignorado(s) ===\n\n";
