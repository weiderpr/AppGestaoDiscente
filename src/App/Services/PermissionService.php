<?php
/**
 * Vértice Acadêmico — Serviço de Permissões
 */

namespace App\Services;

class PermissionService extends Service {
    private ?int $institutionId;

    public function __construct() {
        parent::__construct();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->institutionId = $_SESSION['institution_id'] ?? null;
    }

    /**
     * Verifica se um perfil ou usuário específico tem acesso a um recurso
     */
    public function canAccess(string $profile, string $resource, ?int $userId = null): bool {
        if (!$this->institutionId) return false;

        // Se for perfil 'Outro' e tivermos o ID do usuário, verificamos a permissão individual
        if ($profile === 'Outro' && $userId) {
            $userPerm = $this->fetchOne(
                'SELECT can_access FROM user_individual_permissions 
                 WHERE user_id = ? AND resource = ? AND institution_id = ?',
                [$userId, $resource, $this->institutionId]
            );
            
            // Se houver definição individual para este recurso, ela tem precedência
            if ($userPerm !== null) {
                return (bool)$userPerm['can_access'];
            }
        }

        // Caso contrário (ou se não houver definição individual), usa a permissão do Perfil
        $result = $this->fetchOne(
            'SELECT can_access FROM profile_permissions 
             WHERE profile = ? AND resource = ? AND instituicao_id = ?',
            [$profile, $resource, $this->institutionId]
        );
        
        return $result ? (bool)$result['can_access'] : false;
    }

    /**
     * Retorna todas as permissões de um perfil
     */
    public function getPermissionsByProfile(string $profile): array {
        if (!$this->institutionId) return [];

        return $this->fetchAll(
            'SELECT resource, can_access FROM profile_permissions WHERE profile = ? AND instituicao_id = ?',
            [$profile, $this->institutionId]
        );
    }

    /**
     * Salva ou atualiza uma permissão
     */
    public function updatePermission(string $profile, string $resource, bool $canAccess): bool {
        if (!$this->institutionId) return false;

        $old = $this->fetchOne(
            'SELECT * FROM profile_permissions WHERE profile = ? AND resource = ? AND instituicao_id = ?',
            [$profile, $resource, $this->institutionId]
        );

        $sql = 'INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE can_access = ?, updated_at = NOW()';
        
        $execution = $this->execute($sql, [$profile, $resource, $canAccess ? 1 : 0, $this->institutionId, $canAccess ? 1 : 0]);
        
        if ($execution > 0) {
            $action = $old ? 'UPDATE' : 'CREATE';
            $this->audit($action, 'profile_permissions', $old['id'] ?? 0, $old, [
                'profile' => $profile,
                'resource' => $resource,
                'can_access' => $canAccess ? 1 : 0
            ]);
        }
        
        return $execution > 0;
    }
    
    /**
     * Retorna todos os recursos únicos cadastrados na tabela de permissões para a instituição
     */
    public function getUniqueResources(): array {
        if (!$this->institutionId) return [];
        return $this->fetchAll('SELECT DISTINCT resource FROM profile_permissions WHERE instituicao_id = ? ORDER BY resource', [$this->institutionId]);
    }

    /**
     * Retorna as permissões individuais de um usuário
     */
    public function getPermissionsByUser(int $userId): array {
        if (!$this->institutionId) return [];
        return $this->fetchAll(
            'SELECT resource, can_access FROM user_individual_permissions WHERE user_id = ? AND institution_id = ?',
            [$userId, $this->institutionId]
        );
    }

    /**
     * Atualiza uma permissão individual de usuário
     */
    public function updateUserPermission(int $userId, string $resource, bool $canAccess): bool {
        if (!$this->institutionId) return false;

        $old = $this->fetchOne(
            'SELECT * FROM user_individual_permissions WHERE user_id = ? AND resource = ? AND institution_id = ?',
            [$userId, $resource, $this->institutionId]
        );

        $sql = 'INSERT INTO user_individual_permissions (user_id, resource, can_access, institution_id) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE can_access = ?, updated_at = NOW()';
        
        $execution = $this->execute($sql, [$userId, $resource, $canAccess ? 1 : 0, $this->institutionId, $canAccess ? 1 : 0]);
        
        if ($execution > 0) {
            $action = $old ? 'UPDATE' : 'CREATE';
            $this->audit($action, 'user_individual_permissions', $old['id'] ?? 0, $old, [
                'user_id' => $userId,
                'resource' => $resource,
                'can_access' => $canAccess ? 1 : 0
            ]);
        }
        
        return $execution > 0;
    }
}
