<?php
/**
 * Migration: Adicionar campo outros_detalhes em manutenções
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    $db->exec("ALTER TABLE manutencoes ADD COLUMN outros_detalhes TEXT NULL AFTER descricao;");
    echo "Coluna outros_detalhes adicionada com sucesso!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
