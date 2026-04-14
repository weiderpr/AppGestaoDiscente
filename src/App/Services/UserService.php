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

    /**
     * Atualiza os dados de perfil do usuário
     */
    public function updateProfile(int $userId, array $data): bool {
        $old = $this->fetchOne("SELECT name, phone, photo, theme FROM users WHERE id = ?", [$userId]);
        if (!$old) return false;

        $sql = "UPDATE users SET name = ?, phone = ?, photo = ?, theme = ?, updated_at = NOW() WHERE id = ?";
        $updated = $this->execute($sql, [
            $data['name'] ?? $old['name'],
            $data['phone'] ?? $old['phone'],
            $data['photo'] ?? $old['photo'],
            $data['theme'] ?? $old['theme'],
            $userId
        ]) > 0;

        if ($updated) {
            $this->audit('UPDATE', 'users', $userId, $old, $data);
        }
        return $updated;
    }

    /**
     * Altera a senha do usuário
     */
    public function changePassword(int $userId, string $newPassword): bool {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $updated = $this->execute("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", [$hash, $userId]) > 0;
        
        if ($updated) {
            $this->audit('UPDATE', 'users', $userId, ['password' => '[PROTECTED]'], ['password' => '[PROTECTED]']);
        }
        return $updated;
    }

    /**
     * Registra o evento de login
     */
    public function logLogin(int $userId): void {
        $this->audit('LOGIN', 'users', $userId, null, ['status' => 'success'], $userId);
    }

    /**
     * Registra o evento de logout
     */
    public function logLogout(int $userId): void {
        $this->audit('LOGOUT', 'users', $userId, null, ['status' => 'success'], $userId);
    }

    /**
     * Registra um novo usuário
     */
    public function register(array $data): int {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $sql = "INSERT INTO users (name, email, password, phone, photo, profile, theme) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $this->execute($sql, [
            $data['name'],
            strtolower(trim($data['email'])),
            $hash,
            $data['phone'] ?? '',
            $data['photo'] ?? null,
            $data['profile'],
            $data['theme'] ?? 'light'
        ]);

        $newId = (int)$this->db->lastInsertId();
        $this->audit('CREATE', 'users', $newId, null, [
            'name' => $data['name'],
            'email' => $data['email'],
            'profile' => $data['profile']
        ]);

        return $newId;
    }
}
