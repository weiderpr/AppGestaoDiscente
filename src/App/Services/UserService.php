<?php
/**
 * Vértice Acadêmico — Serviço de Usuários
 */

namespace App\Services;

class UserService extends Service {
    
    /**
     * Retorna a lista de usuários online em uma instituição nos últimos 5 minutos
     */
    public function getOnlineUsers(int $institutionId, int $excludeUserId = 0): array {
        $sql = "
            SELECT 
                u.id, 
                u.name, 
                u.photo, 
                u.profile, 
                u.last_access,
                CASE 
                    WHEN u.last_access >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 
                    ELSE 0 
                END as is_online
            FROM users u
            INNER JOIN user_institutions ui ON u.id = ui.user_id
            WHERE ui.institution_id = ?
              AND u.is_active = 1
              AND u.last_access >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
              AND u.id != ?
            ORDER BY u.name ASC
        ";
        
        return $this->fetchAll($sql, [$institutionId, $excludeUserId]);
    }

    /**
     * Retorna o total de usuários online
     */
    public function countOnlineUsers(int $institutionId): int {
        $sql = "
            SELECT COUNT(*) 
            FROM users u
            INNER JOIN user_institutions ui ON u.id = ui.user_id
            WHERE ui.institution_id = ?
              AND u.is_active = 1
              AND u.last_access >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$institutionId]);
        return (int) $stmt->fetchColumn();
    }
}
