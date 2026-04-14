<?php
/**
 * Vértice Acadêmico — Serviço de Cursos
 */

namespace App\Services;

class CourseService extends Service {
    public function findById(int $id): ?array {
        return $this->fetchOne(
            'SELECT * FROM courses WHERE id = ?',
            [$id]
        );
    }

    public function getAll(int $institutionId, string $search = ''): array {
        $sql = 'SELECT * FROM courses 
                WHERE institution_id = ? AND is_active = 1';
        $params = [$institutionId];

        if ($search) {
            $sql .= ' AND name LIKE ?';
            $params[] = "%{$search}%";
        }

        $sql .= ' ORDER BY name';
        return $this->fetchAll($sql, $params);
    }

    public function create(int $institutionId, array $data): array {
        $this->db->prepare(
            'INSERT INTO courses (institution_id, name, location) VALUES (?, ?, ?)'
        )->execute([
            $institutionId,
            trim($data['name']),
            $data['location'] ?? null
        ]);

        $newId = $this->lastInsertId();
        $this->audit('CREATE', 'courses', $newId, null, array_merge(['institution_id' => $institutionId], $data));

        return ['success' => true, 'id' => $newId];
    }

    public function update(int $id, array $data): array {
        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $params[] = trim($data['name']);
        }
        if (isset($data['location'])) {
            $fields[] = 'location = ?';
            $params[] = $data['location'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
        }

        if (empty($fields)) {
            return ['error' => 'Nenhum campo para atualizar'];
        }

        $params[] = $id;
        $sql = 'UPDATE courses SET ' . implode(', ', $fields) . ' WHERE id = ?';
        
        $old = $this->fetchOne('SELECT * FROM courses WHERE id = ?', [$id]);
        $this->execute($sql, $params);
        $this->audit('UPDATE', 'courses', $id, $old, $data);

        return ['success' => true];
    }

    public function delete(int $id): bool {
        $old = $this->fetchOne('SELECT * FROM courses WHERE id = ?', [$id]);
        $deleted = $this->execute(
            'DELETE FROM courses WHERE id = ?',
            [$id]
        ) > 0;
        if ($deleted && $old) {
            $this->audit('DELETE', 'courses', $id, $old, ['deleted' => true]);
        }
        return $deleted;
    }

    public function getTurmas(int $courseId): array {
        return $this->fetchAll(
            'SELECT * FROM turmas 
             WHERE course_id = ? AND is_active = 1
             ORDER BY ano DESC, description',
            [$courseId]
        );
    }

    public function getCoordinators(int $courseId): array {
        return $this->fetchAll(
            'SELECT u.id, u.name, u.email, u.profile
             FROM users u
             INNER JOIN course_coordinators cc ON u.id = cc.user_id
             WHERE cc.course_id = ? AND u.deleted_at IS NULL',
            [$courseId]
        );
    }
}
