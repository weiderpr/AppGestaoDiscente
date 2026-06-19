<?php
/**
 * Vértice Acadêmico — RBAC Seeder for Dashboard do Aluno
 * Registra a permissão 'acompanhamento.index' para todas as instituições ativas.
 */
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();

$profilesToGrant = [
    'Administrador',
    'Coordenador',
    'Diretor',
    'Pedagogo',
    'Assistente Social',
    'Psicólogo',
    'Naapi'
];

$resource = 'acompanhamento.index';

try {
    $institutions = $db->query("SELECT id, name FROM institutions WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

    echo "Iniciando semeação de permissões para o módulo Acompanhamento (Dashboard do Aluno)...\n";
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
    echo "Sucesso! Permissões do Dashboard do Aluno registradas.\n";

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
