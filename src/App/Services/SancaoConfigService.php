<?php
/**
 * Vértice Acadêmico — Serviço de Configurações de Sanção
 * (Tipos e Ações de Sanção)
 */

namespace App\Services;

class SancaoConfigService extends Service {

    // ── Tipos de Sanção ────────────────────────────────────────────────

    public function findTipoById(int $id, int $institutionId): ?array {
        return $this->fetchOne("SELECT * FROM sancao_tipo WHERE id = ? AND institution_id = ?", [$id, $institutionId]);
    }

    public function listTipos(int $institutionId): array {
        return $this->fetchAll("SELECT * FROM sancao_tipo WHERE institution_id = ? ORDER BY titulo", [$institutionId]);
    }

    public function saveTipo(array $data, int $institutionId): void {
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $old = $this->findTipoById($id, $institutionId);
            $this->execute(
                "UPDATE sancao_tipo SET titulo = ?, descricao = ? WHERE id = ? AND institution_id = ?",
                [$data['titulo'], $data['descricao'], $id, $institutionId]
            );
            $this->audit('UPDATE', 'sancao_tipo', $id, $old, $data);
        } else {
            $this->execute(
                "INSERT INTO sancao_tipo (titulo, descricao, institution_id) VALUES (?, ?, ?)",
                [$data['titulo'], $data['descricao'], $institutionId]
            );
            $newId = $this->lastInsertId();
            $this->audit('CREATE', 'sancao_tipo', $newId, null, $data);
        }
    }

    public function toggleTipo(int $id, int $institutionId): void {
        $old = $this->findTipoById($id, $institutionId);
        if ($old) {
            $newActive = $old['is_active'] ? 0 : 1;
            $this->execute("UPDATE sancao_tipo SET is_active = ? WHERE id = ? AND institution_id = ?", [$newActive, $id, $institutionId]);
            $this->audit('UPDATE', 'sancao_tipo', $id, ['is_active' => $old['is_active']], ['is_active' => $newActive]);
        }
    }

    // ── Ações de Sanção ────────────────────────────────────────────────

    public function findAcaoById(int $id, int $institutionId): ?array {
        return $this->fetchOne("SELECT * FROM sancao_acao WHERE id = ? AND institution_id = ?", [$id, $institutionId]);
    }

    public function listAcoes(int $institutionId): array {
        return $this->fetchAll("SELECT * FROM sancao_acao WHERE institution_id = ? ORDER BY descricao", [$institutionId]);
    }

    public function saveAcao(array $data, int $institutionId): void {
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $old = $this->findAcaoById($id, $institutionId);
            $this->execute("UPDATE sancao_acao SET descricao = ? WHERE id = ? AND institution_id = ?", [$data['descricao'], $id, $institutionId]);
            $this->audit('UPDATE', 'sancao_acao', $id, $old, $data);
        } else {
            $this->execute("INSERT INTO sancao_acao (descricao, institution_id) VALUES (?, ?)", [$data['descricao'], $institutionId]);
            $newId = $this->lastInsertId();
            $this->audit('CREATE', 'sancao_acao', $newId, null, $data);
        }
    }

    public function toggleAcao(int $id, int $institutionId): void {
        $old = $this->findAcaoById($id, $institutionId);
        if ($old) {
            $newActive = $old['is_active'] ? 0 : 1;
            $this->execute("UPDATE sancao_acao SET is_active = ? WHERE id = ? AND institution_id = ?", [$newActive, $id, $institutionId]);
            $this->audit('UPDATE', 'sancao_acao', $id, ['is_active' => $old['is_active']], ['is_active' => $newActive]);
        }
    }
}
