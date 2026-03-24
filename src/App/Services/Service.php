<?php
/**
 * Vértice Acadêmico — Camada de Serviços
 * Classe base para todos os serviços
 */

namespace App\Services;

use PDO;

abstract class Service {
    protected PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    protected function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    protected function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function execute(string $sql, array $params = []): int {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function lastInsertId(): int {
        return (int) $this->db->lastInsertId();
    }

    protected function beginTransaction(): void {
        $this->db->beginTransaction();
    }

    protected function commit(): void {
        $this->db->commit();
    }

    protected function rollBack(): void {
        $this->db->rollBack();
    }
}
