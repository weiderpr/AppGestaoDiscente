<?php
/**
 * Migração: Adicionar permissão students.comments
 * 
 * Perfis que devem ter acesso a comentários de alunos:
 * - Administrador: sim
 * - Coordenador: sim
 * - Professor: sim (só vê seus turmas, controlado na API)
 * - Pedagogo: sim
 * - Assistente Social: sim
 * - Psicólogo: sim
 * - Naapi: sim
 * - Diretor: não
 * - Outro: não
 */
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    
    $instituicoes = $db->query("SELECT id FROM institutions")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($instituicoes)) {
        echo "Nenhuma instituição encontrada.\n";
        exit;
    }

    $resource = 'students.comments';
    
    $permissions = [
        'Administrador'   => 1,
        'Coordenador'     => 1,
        'Diretor'         => 0,
        'Professor'       => 1,
        'Pedagogo'        => 1,
        'Assistente Social' => 1,
        'Naapi'           => 1,
        'Psicólogo'       => 1,
        'Outro'           => 0,
    ];

    $db->beginTransaction();
    $st = $db->prepare("INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id) 
                       VALUES (?, ?, ?, ?) 
                       ON DUPLICATE KEY UPDATE can_access = VALUES(can_access)");

    foreach ($instituicoes as $instId) {
        foreach ($permissions as $profile => $access) {
            $st->execute([$profile, $resource, $access, $instId]);
        }
    }

    $db->commit();
    echo "Migração concluída! Permissão '$resource' adicionada para " . count($instituicoes) . " instituições.\n";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo "ERRO: " . $e->getMessage() . "\n";
}
