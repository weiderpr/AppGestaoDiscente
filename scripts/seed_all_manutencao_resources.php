<?php
/**
 * Vértice Acadêmico — Semeia todos os recursos de manutenção para que apareçam na matriz de controle de acesso (RBAC)
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    // Lista de perfis do sistema
    $profiles = [
        'Administrador', 'Coordenador', 'Diretor', 'Professor', 'Pedagogo', 
        'Assistente Social', 'Naapi', 'Psicólogo', 'Outro'
    ];

    // Todos os recursos relacionados a manutenção
    $resources = [
        'manutencao.index',
        'manutencao.ambientes',
        'manutencao.problemas',
        'manutencao.create',
        'manutencao.update',
        'manutencao.move',
        'manutencao.materials',
        'manutencao.comments',
        'manutencao.delete'
    ];

    // Busca todas as instituições cadastradas
    $stmtInst = $db->query("SELECT id FROM institutions");
    $institutions = $stmtInst->fetchAll(PDO::FETCH_COLUMN);

    echo "Semeando recursos de manutenção nas permissões...\n";

    $stCheck = $db->prepare("SELECT COUNT(*) FROM profile_permissions WHERE profile = ? AND resource = ? AND instituicao_id = ?");
    $stInsert = $db->prepare("INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id) VALUES (?, ?, ?, ?)");

    foreach ($institutions as $instId) {
        foreach ($profiles as $profile) {
            foreach ($resources as $resource) {
                // Verifica se já existe
                $stCheck->execute([$profile, $resource, $instId]);
                $count = (int)$stCheck->fetchColumn();

                if ($count === 0) {
                    // Administrador tem acesso padrão (1), outros têm 0 (desativado por padrão)
                    $canAccess = ($profile === 'Administrador') ? 1 : 0;
                    
                    $stInsert->execute([$profile, $resource, $canAccess, $instId]);
                    echo "Adicionado: [Inst: $instId] [Perfil: $profile] -> Recurso: $resource (Acesso: $canAccess)\n";
                }
            }
        }
    }

    echo "Semeamento de permissões de manutenção concluído com sucesso!\n";

} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
    exit(1);
}
