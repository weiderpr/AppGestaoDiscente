<?php
/**
 * Vértice Acadêmico — Service: Relatos via QR Code de Manutenção
 */
namespace App\Services\Manutencao;

use App\Services\Service;
use App\Services\Traits\Auditable;
use PDO;
use Exception;

class ManutencaoRelatoService extends Service {
    use Auditable;

    /**
     * Cria um novo relato a partir de leitura de QR Code.
     */
    public function create(array $data): array {
        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO manutencao_relatos
                    (ambiente_id, user_id, nome_relator, email_relator, descricao, comentario, outros_detalhes, ip_address)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                (int)$data['ambiente_id'],
                $data['user_id'] ?? null,
                $data['nome_relator'] ?? null,
                $data['email_relator'] ?? null,
                trim($data['descricao']),
                $data['comentario'] ?? null,
                $data['outros_detalhes'] ?? null,
                $data['ip_address'] ?? null,
            ]);

            $relatoId = (int)$this->db->lastInsertId();

            // Vincula problemas selecionados
            if (!empty($data['problemas']) && is_array($data['problemas'])) {
                $stmtP = $this->db->prepare(
                    "INSERT IGNORE INTO manutencao_relato_problemas (relato_id, problema_id) VALUES (?, ?)"
                );
                foreach ($data['problemas'] as $pid) {
                    $stmtP->execute([$relatoId, (int)$pid]);
                }
            }

            // Gera automaticamente uma manutenção na coluna "Demandas"
            $sqlM = "INSERT INTO manutencoes (institution_id, ambiente_id, descricao, outros_detalhes, status, data_manutencao) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmtM = $this->db->prepare($sqlM);
            $stmtM->execute([
                (int)$data['institution_id'],
                (int)$data['ambiente_id'],
                trim($data['descricao']),
                $data['outros_detalhes'] ?? null,
                'Demandas',
                date('Y-m-d H:i:s')
            ]);
            $manutencaoId = (int)$this->db->lastInsertId();

            // Vincula problemas à manutenção
            if (!empty($data['problemas']) && is_array($data['problemas'])) {
                $stmtVP = $this->db->prepare(
                    "INSERT IGNORE INTO manutencao_vinculo_problemas (manutencao_id, problema_id) VALUES (?, ?)"
                );
                foreach ($data['problemas'] as $pid) {
                    $stmtVP->execute([$manutencaoId, (int)$pid]);
                }
            }

            // Vincula o relato à manutenção gerada
            $this->db->prepare("UPDATE manutencao_relatos SET manutencao_id = ? WHERE id = ?")
                ->execute([$manutencaoId, $relatoId]);

            $this->audit('CREATE', 'manutencao_relatos', $relatoId, null, array_diff_key($data, ['ip_address' => '']));
            $this->db->commit();

            return ['success' => true, 'id' => $relatoId];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Busca dados do ambiente com seus problemas padrão e informação da instituição.
     */
    public function getAmbienteParaRelato(int $ambienteId): ?array {
        $sql = "SELECT a.*, i.name AS institution_name, i.id AS institution_id
                FROM manutencao_ambientes a
                INNER JOIN institutions i ON i.id = a.institution_id
                WHERE a.id = ? AND a.status = 'Ativo'";

        $ambiente = $this->fetchOne($sql, [$ambienteId]);
        if (!$ambiente) return null;

        $ambiente['problemas'] = $this->fetchAll(
            "SELECT p.* FROM manutencao_problemas_padrao p
             INNER JOIN manutencao_ambiente_problemas map ON p.id = map.problema_id
             WHERE map.ambiente_id = ?
             ORDER BY p.descricao ASC",
            [$ambienteId]
        );

        return $ambiente;
    }
}
