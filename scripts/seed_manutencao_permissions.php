<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Semeando permissões de Manutenção...\n";

    $permissions = [
        ['Administrador', 'manutencao.index', 1],
        ['Administrador', 'manutencao.ambientes', 1],
        ['Administrador', 'manutencao.problemas', 1], // Preemptively adding this
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO profile_permissions (profile, resource, can_access, instituicao_id) VALUES (?, ?, ?, 1)");

    foreach ($permissions as $p) {
        $stmt->execute($p);
        echo "Permissão {$p[1]} para {$p[0]} adicionada.\n";
    }

    echo "Permissões semeadas com sucesso!\n";

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
