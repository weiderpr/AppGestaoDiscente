<?php
/**
 * Vértice Acadêmico — Serviço de Ambientes (Manutenção)
 */

namespace App\Services\Manutencao;

use App\Services\Service;
use App\Services\Traits\Auditable;

class AmbienteService extends Service {
    use Auditable;

    public function getAll(int $institutionId, string $search = ''): array {
        $sql = "SELECT * FROM manutencao_ambientes WHERE institution_id = ?";
        $params = [$institutionId];

        if ($search) {
            $sql .= " AND (descricao LIKE ? OR predio_campus LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY descricao ASC";
        return $this->fetchAll($sql, $params);
    }

    public function findById(int $id): ?array {
        $ambiente = $this->fetchOne("SELECT * FROM manutencao_ambientes WHERE id = ?", [$id]);
        if ($ambiente) {
            $ambiente['problemas'] = $this->getProblemas($id);
        }
        return $ambiente;
    }

    public function create(array $data): array {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO manutencao_ambientes (institution_id, descricao, predio_campus, status) VALUES (?, ?, ?, ?)";
            $this->execute($sql, [
                $data['institution_id'],
                trim($data['descricao']),
                trim($data['predio_campus']),
                $data['status'] ?? 'Ativo'
            ]);

            $ambienteId = $this->lastInsertId();

            if (!empty($data['problemas']) && is_array($data['problemas'])) {
                $this->syncProblemas($ambienteId, $data['problemas']);
            }

            $this->audit('CREATE', 'manutencao_ambientes', $ambienteId, null, $data);
            
            $this->db->commit();
            return ['success' => true, 'id' => $ambienteId];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['error' => $e->getMessage()];
        }
    }

    public function update(int $id, array $data): array {
        try {
            $this->db->beginTransaction();

            $old = $this->fetchOne("SELECT * FROM manutencao_ambientes WHERE id = ?", [$id]);
            if (!$old) throw new \Exception("Ambiente não encontrado.");

            $fields = [];
            $params = [];

            if (isset($data['descricao'])) {
                $fields[] = "descricao = ?";
                $params[] = trim($data['descricao']);
            }
            if (isset($data['predio_campus'])) {
                $fields[] = "predio_campus = ?";
                $params[] = trim($data['predio_campus']);
            }
            if (isset($data['status'])) {
                $fields[] = "status = ?";
                $params[] = $data['status'];
            }

            if (!empty($fields)) {
                $params[] = $id;
                $sql = "UPDATE manutencao_ambientes SET " . implode(', ', $fields) . " WHERE id = ?";
                $this->execute($sql, $params);
            }

            if (isset($data['problemas']) && is_array($data['problemas'])) {
                $this->syncProblemas($id, $data['problemas']);
            }

            $this->audit('UPDATE', 'manutencao_ambientes', $id, $old, $data);

            $this->db->commit();
            return ['success' => true];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['error' => $e->getMessage()];
        }
    }

    public function delete(int $id): bool {
        $old = $this->fetchOne("SELECT * FROM manutencao_ambientes WHERE id = ?", [$id]);
        if (!$old) return false;

        $deleted = $this->execute("DELETE FROM manutencao_ambientes WHERE id = ?", [$id]) > 0;
        if ($deleted) {
            $this->audit('DELETE', 'manutencao_ambientes', $id, $old, null);
        }
        return $deleted;
    }

    private function getProblemas(int $ambienteId): array {
        return $this->fetchAll(
            "SELECT p.* FROM manutencao_problemas_padrao p
             INNER JOIN manutencao_ambiente_problemas map ON p.id = map.problema_id
             WHERE map.ambiente_id = ?",
            [$ambienteId]
        );
    }

    private function syncProblemas(int $ambienteId, array $problemaIds): void {
        $this->execute("DELETE FROM manutencao_ambiente_problemas WHERE ambiente_id = ?", [$ambienteId]);
        
        $stmt = $this->db->prepare("INSERT INTO manutencao_ambiente_problemas (ambiente_id, problema_id) VALUES (?, ?)");
        foreach ($problemaIds as $probId) {
            $stmt->execute([$ambienteId, $probId]);
        }
    }

    public function getAllProblemasPadrao(): array {
        return $this->fetchAll("SELECT * FROM manutencao_problemas_padrao ORDER BY descricao ASC");
    }
}
