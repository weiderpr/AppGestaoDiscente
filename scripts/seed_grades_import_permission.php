<?php
/**
 * Vértice Acadêmico — RBAC Seeder for Grades Import
 * Registra a permissão grades.import para Administradores e Coordenadores.
 */
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();

$profilesToGrant = [
    'Administrador',
    'Coordenador'
];

$resource = 'grades.import';

try {
    // Buscar todas as instituições ativas
    $institutions = $db->query("SELECT id, name FROM institutions WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

    echo "Iniciando semeação de permissão para Importação de Notas...\n";
    echo "--------------------------------------------------\n";

    foreach ($institutions as $inst) {
        echo "Processando Instituição: {$inst['name']} (ID: {$inst['id']})\n";
        
        foreach ($profilesToGrant as $profile) {
            $sql = "INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id) 
                    VALUES (?, ?, 1, ?) 
                    ON DUPLICATE KEY UPDATE can_access = 1, updated_at = NOW()";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$profile, $resource, $inst['id']]);
            
            echo "  - Recurso '$resource' concedido para: $profile\n";
        }
    }

    echo "--------------------------------------------------\n";
    echo "Sucesso! Permissões de 'grades.import' registradas.\n";

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
