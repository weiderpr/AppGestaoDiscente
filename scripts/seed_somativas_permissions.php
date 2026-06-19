<?php
/**
 * Vértice Acadêmico — Seeder de Permissões: Módulo Somativas
 *
 * Execute após rodar a migração create_somativas.sql:
 *   php scripts/seed_somativas_permissions.php
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

$resources = [
    'somativas.index'  => ['Administrador', 'Coordenador', 'Diretor', 'Pedagogo'],
    'somativas.create' => ['Administrador', 'Coordenador'],
    'somativas.update' => ['Administrador', 'Coordenador'],
    'somativas.delete' => ['Administrador'],
    'somativas.view_all' => ['Administrador', 'Coordenador', 'Diretor'],
];

try {
    $institutions = $db->query("SELECT id, name FROM institutions WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($institutions)) {
        echo "Nenhuma instituição ativa encontrada.\n";
        exit(1);
    }

    echo "Semeando permissões do módulo Somativas...\n";
    echo str_repeat('-', 50) . "\n";

    $stmt = $db->prepare(
        "INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id)
         VALUES (?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE can_access = 1, updated_at = NOW()"
    );

    foreach ($institutions as $inst) {
        echo "\nInstituição: {$inst['name']} (ID: {$inst['id']})\n";

        foreach ($resources as $resource => $profiles) {
            foreach ($profiles as $profile) {
                $stmt->execute([$profile, $resource, $inst['id']]);
                echo "  ✓ {$profile} → {$resource}\n";
            }
        }
    }

    echo "\n" . str_repeat('-', 50) . "\n";
    echo "Permissões registradas com sucesso!\n";

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
