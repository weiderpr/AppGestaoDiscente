<?php
/**
 * Migration: Módulo de Manutenções (Kanban e Atendimentos) - Corrigida
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();

    // 1. Tabela de Manutenções
    $db->exec("CREATE TABLE IF NOT EXISTS manutencoes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        institution_id INT UNSIGNED NOT NULL,
        ambiente_id INT UNSIGNED NOT NULL,
        descricao TEXT NOT NULL,
        status ENUM('Demandas', 'Em Aberto', 'Em Execução', 'Finalizado') DEFAULT 'Demandas',
        data_manutencao DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
        FOREIGN KEY (ambiente_id) REFERENCES manutencao_ambientes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Tabela Pivô: Manutenção <-> Problemas Padrão
    $db->exec("CREATE TABLE IF NOT EXISTS manutencao_vinculo_problemas (
        manutencao_id INT UNSIGNED NOT NULL,
        problema_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (manutencao_id, problema_id),
        FOREIGN KEY (manutencao_id) REFERENCES manutencoes(id) ON DELETE CASCADE,
        FOREIGN KEY (problema_id) REFERENCES manutencao_problemas_padrao(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    echo "Tabelas de manutenção (Kanban) criadas com sucesso!\n";

} catch (Exception $e) {
    die("Erro na migração: " . $e->getMessage() . "\n");
}
