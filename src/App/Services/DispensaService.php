<?php
/**
 * Vértice Acadêmico — Serviço de Dispensas de Disciplinas
 */

namespace App\Services;

class DispensaService extends Service {
    
    /**
     * Registra ou reativa uma dispensa para um aluno.
     * 
     * @param int $alunoId
     * @param int $turmaId
     * @param string $discCodigo
     * @param int $userId
     * @return array
     */
    public function saveDispensa(int $alunoId, int $turmaId, string $discCodigo, int $userId): array {
        try {
            // Busca registro existente
            $existing = $this->fetchOne(
                'SELECT * FROM alunos_dispensa WHERE aluno_id = ? AND turma_id = ? AND disciplina_codigo = ?',
                [$alunoId, $turmaId, $discCodigo]
            );

            if ($existing) {
                // Se já está ativo, nada a fazer
                if ($existing['is_active'] == 1) {
                    return ['success' => true, 'message' => 'Dispensa já está ativa.'];
                }

                // Reativa registro (UPDATE)
                $data = [
                    'is_active' => 1,
                    'created_by' => $userId
                ];
                
                $this->db->prepare('UPDATE alunos_dispensa SET is_active = 1, updated_at = NOW(), created_by = ? WHERE id = ?')
                         ->execute([$userId, $existing['id']]);

                $this->audit('UPDATE', 'alunos_dispensa', (int)$existing['id'], $existing, $data);
                
                return ['success' => true, 'message' => 'Dispensa reativada com sucesso!'];
            } else {
                // Cria novo registro (CREATE)
                $data = [
                    'aluno_id' => $alunoId,
                    'turma_id' => $turmaId,
                    'disciplina_codigo' => $discCodigo,
                    'created_by' => $userId,
                    'is_active' => 1
                ];

                $this->db->prepare('INSERT INTO alunos_dispensa (aluno_id, turma_id, disciplina_codigo, created_by) VALUES (?, ?, ?, ?)')
                         ->execute([$alunoId, $turmaId, $discCodigo, $userId]);

                $newId = $this->lastInsertId();
                $this->audit('CREATE', 'alunos_dispensa', $newId, null, $data);

                return ['success' => true, 'message' => 'Dispensa registrada com sucesso!', 'id' => $newId];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cancela (soft delete) uma dispensa de disciplina.
     * 
     * @param int $alunoId
     * @param int $turmaId
     * @param string $discCodigo
     * @param int $userId
     * @return array
     */
    public function removeDispensa(int $alunoId, int $turmaId, string $discCodigo, int $userId): array {
        try {
            $existing = $this->fetchOne(
                'SELECT * FROM alunos_dispensa WHERE aluno_id = ? AND turma_id = ? AND disciplina_codigo = ? AND is_active = 1',
                [$alunoId, $turmaId, $discCodigo]
            );

            if (!$existing) {
                return ['success' => false, 'error' => 'Dispensa ativa não encontrada.'];
            }

            $this->db->prepare('UPDATE alunos_dispensa SET is_active = 0, updated_at = NOW() WHERE id = ?')
                     ->execute([$existing['id']]);

            $this->audit('UPDATE', 'alunos_dispensa', (int)$existing['id'], $existing, ['is_active' => 0]);

            return ['success' => true, 'message' => 'Dispensa cancelada com sucesso!'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
