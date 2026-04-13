<?php
/**
 * Vértice Acadêmico — RBAC Seeder for NAAPI
 * Registra as permissões naapi.index e naapi.manage no sistema.
 */
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();

$profilesToGrant = [
    'Administrador',
    'Coordenador',
    'Diretor',
    'Pedagogo',
    'Psicólogo',
    'Assistente Social',
    'Naapi'
];

$resources = [
    'naapi.index',
    'naapi.manage'
];

try {
    // Buscar todas as instituições ativas
    $institutions = $db->query("SELECT id, name FROM institutions WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

    echo "Iniciando semeação de permissões para o módulo NAAPI...\n";
    echo "--------------------------------------------------\n";

    foreach ($institutions as $inst) {
        echo "Processando Instituição: {$inst['name']} (ID: {$inst['id']})\n";
        
        foreach ($resources as $res) {
            foreach ($profilesToGrant as $profile) {
                $sql = "INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id) 
                        VALUES (?, ?, 1, ?) 
                        ON DUPLICATE KEY UPDATE can_access = 1, updated_at = NOW()";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([$profile, $res, $inst['id']]);
                
                echo "  - Recurso '$res' concedido para: $profile\n";
            }
        }
    }

    echo "--------------------------------------------------\n";
    echo "Sucesso! Permissões do NAAPI registradas.\n";

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
