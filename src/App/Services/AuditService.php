<?php
/**
 * Vértice Acadêmico — Serviço de Auditoria
 * Centraliza a lógica de recuperação e persistência de logs.
 */

namespace App\Services;

use PDO;

class AuditService extends Service {
    /**
     * Recupera logs de auditoria com filtros e paginação.
     *
     * @param array $filters
     * @param int   $limit
     * @param int   $offset
     * @return array
     */
    public function getLogs(array $filters = [], int $limit = 20, int $offset = 0): array {
        $params = [];
        $where = ["1=1"];

        if (!empty($filters['date_from'])) {
            $where[] = "a.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "a.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['user_id'])) {
            $where[] = "a.user_id = ?";
            $params[] = (int)$filters['user_id'];
        }
        if (!empty($filters['table_name'])) {
            $where[] = "a.table_name = ?";
            $params[] = $filters['table_name'];
        }
        
        if (isset($filters['inst_id']) && $filters['inst_id'] !== '' && (int)$filters['inst_id'] > 0) {
            $where[] = "a.institution_id = ?";
            $params[] = (int)$filters['inst_id'];
        }

        $sql = "SELECT a.*, u.name as user_name, i.name as inst_name 
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                LEFT JOIN institutions i ON i.id = a.institution_id
                WHERE " . implode(" AND ", $where) . "
                ORDER BY a.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $i => $p) {
            $stmt->bindValue($i + 1, $p);
        }
        
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca tabelas únicas que possuem logs.
     */
    public function getUniqueTables(): array {
        return $this->db->query("SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    }
}
