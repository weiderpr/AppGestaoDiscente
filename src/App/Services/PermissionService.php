<?php
/**
 * Vértice Acadêmico — Serviço de Permissões
 */

namespace App\Services;

class PermissionService extends Service {
    /**
     * Verifica se um perfil tem acesso a um recurso
     */
    public function canAccess(string $profile, string $resource): bool {
        $result = $this->fetchOne(
            'SELECT can_access FROM profile_permissions 
             WHERE profile = ? AND resource = ?',
            [$profile, $resource]
        );
        
        return $result ? (bool)$result['can_access'] : false;
    }

    /**
     * Retorna todas as permissões de um perfil
     */
    public function getPermissionsByProfile(string $profile): array {
        return $this->fetchAll(
            'SELECT resource, can_access FROM profile_permissions WHERE profile = ?',
            [$profile]
        );
    }

    /**
     * Salva ou atualiza uma permissão
     */
    public function updatePermission(string $profile, string $resource, bool $canAccess): bool {
        $sql = 'INSERT INTO profile_permissions (profile, resource, can_access) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE can_access = ?, updated_at = NOW()';
        
        return $this->execute($sql, [$profile, $resource, $canAccess ? 1 : 0, $canAccess ? 1 : 0]) > 0;
    }
    
    /**
     * Retorna todos os recursos únicos cadastrados na tabela de permissões
     */
    public function getUniqueResources(): array {
        return $this->fetchAll('SELECT DISTINCT resource FROM profile_permissions ORDER BY resource');
    }
}
