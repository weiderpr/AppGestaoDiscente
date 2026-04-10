<?php
/**
 * Migration: Create alunos_dispensa table
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

$sql = "
CREATE TABLE IF NOT EXISTS alunos_dispensa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT UNSIGNED NOT NULL,
    turma_id INT UNSIGNED NOT NULL,
    disciplina_codigo VARCHAR(50) NOT NULL,
    created_by INT UNSIGNED NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_aluno_turma_disc (aluno_id, turma_id, disciplina_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql);
    echo "Tabela 'alunos_dispensa' criada com sucesso.\n";
} catch (PDOException $e) {
    echo "Erro ao criar tabela: " . $e->getMessage() . "\n";
    exit(1);
}
