<?php
/**
 * Vértice Acadêmico — Serviço de Usuários
 */

namespace App\Services;

class UserService extends Service {
    public function findById(int $id): ?array {
        return $this->fetchOne(
            'SELECT id, name, email, phone, photo, profile, theme, is_active, created_at 
             FROM users WHERE id = ? AND deleted_at IS NULL',
            [$id]
        );
    }

    public function findByEmail(string $email): ?array {
        return $this->fetchOne(
            'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1',
            [strtolower(trim($email))]
        );
    }

    public function getAll(int $institutionId = null, string $search = '', int $limit = 50, int $offset = 0): array {
        if ($institutionId) {
            $sql = 'SELECT u.id, u.name, u.email, u.phone, u.profile, u.is_active, u.created_at
                    FROM users u
                    INNER JOIN user_institutions ui ON u.id = ui.user_id
                    WHERE ui.institution_id = ? AND u.deleted_at IS NULL';
            $params = [$institutionId];
        } else {
            $sql = 'SELECT id, name, email, phone, profile, is_active, created_at 
                    FROM users WHERE deleted_at IS NULL';
            $params = [];
        }

        if ($search) {
            $sql .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $sql .= ' ORDER BY u.name LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        return $this->fetchAll($sql, $params);
    }

    public function create(array $data): array {
        if ($this->findByEmail($data['email'])) {
            return ['error' => 'E-mail já cadastrado'];
        }

        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        
        $this->db->prepare(
            'INSERT INTO users (name, email, password, phone, photo, profile, theme)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            trim($data['name']),
            strtolower(trim($data['email'])),
            $hash,
            trim($data['phone'] ?? ''),
            $data['photo'] ?? null,
            $data['profile'],
            $data['theme'] ?? 'light'
        ]);

        return ['success' => true, 'id' => $this->lastInsertId()];
    }

    public function update(int $id, array $data): array {
        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $params[] = trim($data['name']);
        }
        if (isset($data['phone'])) {
            $fields[] = 'phone = ?';
            $params[] = trim($data['phone']);
        }
        if (isset($data['profile'])) {
            $fields[] = 'profile = ?';
            $params[] = $data['profile'];
        }
        if (isset($data['theme'])) {
            $fields[] = 'theme = ?';
            $params[] = $data['theme'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
        }

        if (empty($fields)) {
            return ['error' => 'Nenhum campo para atualizar'];
        }

        $params[] = $id;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        
        $this->execute($sql, $params);
        return ['success' => true];
    }

    public function delete(int $id): bool {
        return $this->execute(
            'UPDATE users SET deleted_at = NOW() WHERE id = ?',
            [$id]
        ) > 0;
    }

    public function count(int $institutionId = null): int {
        if ($institutionId) {
            $sql = 'SELECT COUNT(*) FROM users u
                    INNER JOIN user_institutions ui ON u.id = ui.user_id
                    WHERE ui.institution_id = ? AND u.deleted_at IS NULL';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$institutionId]);
        } else {
            $stmt = $this->db->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
        }
        return (int) $stmt->fetchColumn();
    }
}
