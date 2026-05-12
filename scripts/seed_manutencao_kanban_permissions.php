<?php
/**
 * Seed: Permissão para Kanban de Manutenções
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();

    // Adiciona permissão para Administrador
    $db->prepare("INSERT IGNORE INTO profile_permissions (profile, resource) VALUES (?, ?)")
       ->execute(['Administrador', 'manutencao.index']);

    echo "Permissões de manutenção (Kanban) atualizadas!\n";

} catch (Exception $e) {
    die("Erro: " . $e->getMessage() . "\n");
}
