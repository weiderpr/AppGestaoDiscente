<?php
/**
 * Vértice Acadêmico — Trait Auditable
 * Captura e registra alterações em dados para auditoria global.
 */

namespace App\Services\Traits;

use Exception;
use PDO;

trait Auditable {
    /**
     * Registra uma ação no log de auditoria.
     * 
     * @param string $action CREATE, UPDATE, DELETE, etc.
     * @param string $tableName Nome da tabela afetada.
     * @param int $recordId ID do registro afetado.
     * @param array|null $oldValues Valores antes da alteração.
     * @param array|null $newValues Valores após a alteração.
     * @param int|null $actorId ID do usuário que realizou a ação (Opcional, sobrescreve a sessão).
     */
    protected function audit(string $action, string $tableName, int $recordId, ?array $oldValues = [], ?array $newValues = [], ?int $actorId = null): void {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $userId = $actorId ?? $_SESSION['user_id'] ?? 0;
            $institutionId = $_SESSION['institution_id'] ?? $_SESSION['current_institution_id'] ?? null;
            
            // LGPD: Omitir campos sensíveis
            $sensitiveFields = ['password', 'token', 'secret', 'csrf_token', 'auth_key', 'reset_token'];
            
            $filter = function($data) use ($sensitiveFields) {
                if (!$data) return null;
                foreach ($sensitiveFields as $field) {
                    if (isset($data[$field])) {
                        $data[$field] = '[PROTECTED]';
                    }
                }
                return $data;
            };

            $oldValues = $filter($oldValues);
            $newValues = $filter($newValues);

            $jsonOld = null;
            $jsonNew = null;

            if ($oldValues !== null) {
                $jsonOld = json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
                if ($jsonOld === false) $jsonOld = '{"error": "json_encode failed"}';
            }

            if ($newValues !== null) {
                $jsonNew = json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
                if ($jsonNew === false) $jsonNew = '{"error": "json_encode failed"}';
            }

            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown';

            $sql = "INSERT INTO audit_logs (
                        user_id, institution_id, action, table_name, record_id, 
                        old_values, new_values, ip_address, user_agent
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $userId,
                $institutionId,
                strtoupper($action),
                $tableName,
                $recordId,
                $jsonOld,
                $jsonNew,
                $ipAddress,
                $userAgent
            ]);

        } catch (Exception $e) {
            // Em auditoria, geralmente falhas no log não devem travar a operação principal,
            // mas devem ser registradas no log de erro do PHP.
            $msg = $e->getMessage();
            error_log("❌ ERRO DE AUDITORIA (Tabela: $tableName, Ação: $action): $msg");
        }
    }
}
