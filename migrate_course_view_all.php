<?php
/**
 * Migração: Adicionar permissão courses.view_all
 */
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    
    // 1. Buscar todas as instituições
    $instituicoes = $db->query("SELECT id FROM institutions")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($instituicoes)) {
        echo "Nenhuma instituição encontrada para migrar.\n";
        exit;
    }

    $resource = 'courses.view_all';
    
    // Perfis que devem ter acesso '1' por padrão (comportamento atual)
    $vips = ['Administrador', 'Pedagogo', 'Assistente Social', 'Psicólogo'];
    
    // Todos os perfis definidos no ENUM da tabela
    $allProfiles = ['Administrador', 'Coordenador', 'Diretor', 'Professor', 'Pedagogo', 'Assistente Social', 'Naapi', 'Psicólogo', 'Outro'];

    $db->beginTransaction();

    $st = $db->prepare("INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id) 
                       VALUES (?, ?, ?, ?) 
                       ON DUPLICATE KEY UPDATE can_access = VALUES(can_access)");

    foreach ($instituicoes as $instId) {
        foreach ($allProfiles as $profile) {
            $canAccess = in_array($profile, $vips) ? 1 : 0;
            $st->execute([$profile, $resource, $canAccess, $instId]);
        }
    }

    $db->commit();
    echo "Migração concluída com sucesso! Recurso '$resource' adicionado para " . count($instituicoes) . " instituições.\n";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo "ERRO NA MIGRAÇÃO: " . $e->getMessage() . "\n";
}
