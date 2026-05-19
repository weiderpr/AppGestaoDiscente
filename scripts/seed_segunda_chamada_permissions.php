<?php
/**
 * Vértice Acadêmico — RBAC Seeder para Segunda Chamada
 */
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();

$permissionsConfig = [
    'segundachamada.index' => [
        'Administrador', 'Coordenador', 'Diretor', 'Pedagogo', 'Psicólogo', 'Assistente Social', 'Professor', 'Outro'
    ],
    'segundachamada.manage' => [
        'Administrador', 'Coordenador', 'Diretor', 'Pedagogo', 'Psicólogo', 'Assistente Social', 'Professor', 'Outro'
    ],
    'segundachamada.andamento' => [
        'Administrador', 'Coordenador', 'Diretor', 'Pedagogo'
    ]
];

try {
    // Buscar todas as instituições ativas
    $institutions = $db->query("SELECT id, name FROM institutions WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

    echo "Iniciando semeação de permissões para o módulo Segunda Chamada...\n";
    echo "--------------------------------------------------\n";

    foreach ($institutions as $inst) {
        echo "Processando Instituição: {$inst['name']} (ID: {$inst['id']})\n";
        
        foreach ($permissionsConfig as $res => $profiles) {
            foreach ($profiles as $profile) {
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
    echo "Sucesso! Permissões de Segunda Chamada registradas.\n";

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
