<?php
/**
 * Migração: Adicionar permissão sancoes.index
 * Executar: php migrate_sancoes_permissions.php
 */
require_once __DIR__ . '/includes/auth.php';

$db = getDB();

// Buscar todas as instituições ativas
$institutions = $db->query("SELECT id FROM institutions WHERE is_active = 1")->fetchAll();

$profilesWithAccess = ['Administrador', 'Coordenador', 'Pedagogo', 'Assistente Social', 'Psicólogo'];
$profilesNoAccess = ['Diretor', 'Professor', 'Naapi', 'Outro'];

$inserted = 0;

foreach ($institutions as $inst) {
    $instId = $inst['id'];
    
    // Inserir acesso para perfis com permissão
    foreach ($profilesWithAccess as $profile) {
        try {
            $db->prepare("
                INSERT IGNORE INTO profile_permissions (profile, resource, can_access, instituicao_id)
                VALUES (?, 'sancoes.index', 1, ?)
            ")->execute([$profile, $instId]);
            $inserted++;
        } catch (Exception $e) {
            // Ignora duplicatas
        }
    }
    
    // Inserir recusa para perfis sem acesso
    foreach ($profilesNoAccess as $profile) {
        try {
            $db->prepare("
                INSERT IGNORE INTO profile_permissions (profile, resource, can_access, instituicao_id)
                VALUES (?, 'sancoes.index', 0, ?)
            ")->execute([$profile, $instId]);
        } catch (Exception $e) {
            // Ignora duplicatas
        }
    }
}

echo "Migração concluída. $inserted permissões adicionadas.\n";