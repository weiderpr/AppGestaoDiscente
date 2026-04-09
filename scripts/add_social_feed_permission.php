<?php
/**
 * Vértice Acadêmico — RBAC Seeder for Social Feed
 * Run this script once to register the 'social.feed_view' permission.
 */
require_once __DIR__ . '/../includes/auth.php';

// This script needs to be run in a context where we can determine the institution
// OR it can populate for ALL active institutions.
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

$resource = 'social.feed_view';

try {
    // Get all institutions
    $institutions = $db->query("SELECT id, name FROM institutions WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

    echo "Starting permission seeding for resource: $resource\n";
    echo "--------------------------------------------------\n";

    foreach ($institutions as $inst) {
        echo "Processing Institution: {$inst['name']} (ID: {$inst['id']})\n";
        
        foreach ($profilesToGrant as $profile) {
            $sql = "INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id) 
                    VALUES (?, ?, 1, ?) 
                    ON DUPLICATE KEY UPDATE can_access = 1, updated_at = NOW()";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$profile, $resource, $inst['id']]);
            
            echo "  - Granted to: $profile\n";
        }
    }

    echo "--------------------------------------------------\n";
    echo "Done! Permissions registered successfully.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
