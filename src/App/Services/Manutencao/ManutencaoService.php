<?php
namespace App\Services\Manutencao;

use App\Services\Service;
use App\Services\Traits\Auditable;
use Exception;
use PDO;

class ManutencaoService extends Service {
    use Auditable;

    public function __construct() {
        parent::__construct();
    }

    /**
     * Retorna todas as manutenções da instituição para o Kanban
     */
    public function getKanbanData(int $institutionId): array {
        $sql = "SELECT m.*, a.descricao as ambiente_nome, a.predio_campus 
                FROM manutencoes m
                INNER JOIN manutencao_ambientes a ON a.id = m.ambiente_id
                WHERE m.institution_id = ?
                ORDER BY m.updated_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$institutionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $kanban = [
            'Demandas' => [],
            'Em Aberto' => [],
            'Em Execução' => [],
            'Finalizado' => []
        ];

        foreach ($rows as $row) {
            $row['problemas'] = $this->getProblemasByManutencao((int)$row['id']);
            $kanban[$row['status']][] = $row;
        }

        return $kanban;
    }

    /**
     * Retorna os problemas vinculados a uma manutenção
     */
    public function getProblemasByManutencao(int $manutencaoId): array {
        $sql = "SELECT p.* FROM manutencao_problemas_padrao p
                INNER JOIN manutencao_vinculo_problemas mvp ON mvp.problema_id = p.id
                WHERE mvp.manutencao_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$manutencaoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria uma nova manutenção
     */
    public function create(array $data): array {
        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO manutencoes (institution_id, ambiente_id, descricao, outros_detalhes, status, data_manutencao) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['institution_id'],
                $data['ambiente_id'],
                $data['descricao'],
                $data['outros_detalhes'] ?? null,
                $data['status'] ?? 'Demandas',
                $data['data_manutencao'] ?? date('Y-m-d H:i:s')
            ]);

            $id = (int)$this->db->lastInsertId();

            if (!empty($data['problemas'])) {
                $this->syncProblemas($id, $data['problemas']);
            }

            $this->audit('create', 'manutencoes', $id, $data);
            $this->db->commit();

            return ['success' => true, 'id' => $id, 'message' => 'Manutenção registrada com sucesso!'];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Atualiza o status de uma manutenção (Drag & Drop Kanban)
     */
    public function updateStatus(int $id, string $status): bool {
        $validStatus = ['Demandas', 'Em Aberto', 'Em Execução', 'Finalizado'];
        if (!in_array($status, $validStatus)) return false;

        $stmt = $this->db->prepare("UPDATE manutencoes SET status = ?, updated_at = NOW() WHERE id = ?");
        $success = $stmt->execute([$status, $id]);

        if ($success) {
            $this->audit('update_status', 'manutencoes', $id, ['status' => $status]);
        }

        return $success;
    }

    /**
     * Sincroniza problemas vinculados
     */
    private function syncProblemas(int $manutencaoId, array $problemasIds): void {
        $this->db->prepare("DELETE FROM manutencao_vinculo_problemas WHERE manutencao_id = ?")->execute([$manutencaoId]);
        foreach ($problemasIds as $pid) {
            $this->db->prepare("INSERT INTO manutencao_vinculo_problemas (manutencao_id, problema_id) VALUES (?, ?)")
                     ->execute([$manutencaoId, $pid]);
        }
    }

    /**
     * Retorna uma manutenção pelo ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT m.*, a.descricao as ambiente_nome, a.predio_campus 
                FROM manutencoes m
                INNER JOIN manutencao_ambientes a ON a.id = m.ambiente_id
                WHERE m.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['problemas'] = $this->getProblemasByManutencao($id);
        }
        return $row ?: null;
    }
}
