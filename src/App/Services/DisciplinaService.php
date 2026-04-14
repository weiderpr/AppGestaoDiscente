<?php
/**
 * Vértice Acadêmico — Serviço de Disciplinas
 */

namespace App\Services;

use Exception;
use PDOException;

class DisciplinaService extends Service {

    /**
     * Gera um record_id inteiro consistente a partir do código (string PK)
     */
    private function codeToId(string $codigo): int {
        return abs(crc32($codigo));
    }

    /**
     * Busca uma disciplina pelo código e instituição
     */
    public function findByCodigo(string $codigo, int $institutionId): ?array {
        return $this->fetchOne(
            "SELECT d.*, c.nome as categoria_nome FROM disciplinas d JOIN disciplina_categorias c ON c.id = d.categoria_id WHERE d.codigo = ? AND d.institution_id = ?",
            [$codigo, $institutionId]
        );
    }

    /**
     * Cria uma nova disciplina
     */
    public function create(array $data, int $institutionId): void {
        $this->execute(
            "INSERT INTO disciplinas (codigo, institution_id, categoria_id, descricao, observacoes) VALUES (?, ?, ?, ?, ?)",
            [$data['codigo'], $institutionId, $data['categoria_id'], $data['descricao'], $data['observacoes'] ?? '']
        );

        $this->audit('CREATE', 'disciplinas', $this->codeToId($data['codigo']), null, [
            'codigo'       => $data['codigo'],
            'descricao'    => $data['descricao'],
            'categoria_id' => $data['categoria_id'],
        ]);
    }

    /**
     * Atualiza uma disciplina existente
     */
    public function update(string $oldCodigo, array $data, int $institutionId): void {
        $old = $this->findByCodigo($oldCodigo, $institutionId);

        $this->execute(
            "UPDATE disciplinas SET codigo=?, categoria_id=?, descricao=?, observacoes=? WHERE codigo=? AND institution_id=?",
            [$data['codigo'], $data['categoria_id'], $data['descricao'], $data['observacoes'] ?? '', $oldCodigo, $institutionId]
        );

        $this->audit('UPDATE', 'disciplinas', $this->codeToId($oldCodigo), $old, [
            'codigo'       => $data['codigo'],
            'descricao'    => $data['descricao'],
            'categoria_id' => $data['categoria_id'],
        ]);
    }

    /**
     * Remove uma disciplina
     */
    public function delete(string $codigo, int $institutionId): void {
        $old = $this->findByCodigo($codigo, $institutionId);

        $this->execute(
            "DELETE FROM disciplinas WHERE codigo=? AND institution_id=?",
            [$codigo, $institutionId]
        );

        $this->audit('DELETE', 'disciplinas', $this->codeToId($codigo), $old, null);
    }

    /**
     * Importa disciplinas em lote a partir de um array de linhas CSV
     * Retorna o número de registros processados
     */
    public function importFromCsv(string $filePath, int $institutionId): int {
        $handle = fopen($filePath, "r");
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (str_contains($firstLine, ';')) ? ';' : ',';
        $imported = 0;

        $this->beginTransaction();
        try {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                // Pula cabeçalho
                if (str_contains(strtolower($row[0] ?? ''), 'codi')) continue;

                $codigo    = trim($row[0] ?? '');
                $descricao = trim($row[1] ?? '');
                $catId     = (int)($row[2] ?? 0);

                if (!$codigo || !$descricao || !$catId) continue;

                // Verifica categoria válida
                $stCat = $this->db->prepare("SELECT 1 FROM disciplina_categorias WHERE id = ? AND institution_id = ?");
                $stCat->execute([$catId, $institutionId]);
                if (!$stCat->fetch()) continue;

                $existing = $this->findByCodigo($codigo, $institutionId);

                if ($existing) {
                    $this->execute(
                        "UPDATE disciplinas SET descricao = ?, categoria_id = ? WHERE codigo = ? AND institution_id = ?",
                        [$descricao, $catId, $codigo, $institutionId]
                    );
                    $this->audit('UPDATE', 'disciplinas', $this->codeToId($codigo), $existing, [
                        'codigo' => $codigo, 'descricao' => $descricao, 'categoria_id' => $catId, 'origem' => 'CSV_IMPORT'
                    ]);
                } else {
                    $this->execute(
                        "INSERT INTO disciplinas (codigo, descricao, categoria_id, institution_id) VALUES (?, ?, ?, ?)",
                        [$codigo, $descricao, $catId, $institutionId]
                    );
                    $this->audit('CREATE', 'disciplinas', $this->codeToId($codigo), null, [
                        'codigo' => $codigo, 'descricao' => $descricao, 'categoria_id' => $catId, 'origem' => 'CSV_IMPORT'
                    ]);
                }
                $imported++;
            }
            $this->commit();
            fclose($handle);
            return $imported;
        } catch (Exception $e) {
            $this->rollBack();
            fclose($handle);
            throw $e;
        }
    }

    /**
     * Lista disciplinas com filtro de busca
     */
    public function list(int $institutionId, string $search = ''): array {
        $sql = "
            SELECT d.*, c.nome as categoria_nome 
            FROM disciplinas d
            JOIN disciplina_categorias c ON c.id = d.categoria_id
            WHERE d.institution_id = ?
        ";
        $params = [$institutionId];

        if ($search) {
            $sql .= " AND (d.descricao LIKE ? OR c.nome LIKE ? OR d.codigo LIKE ?)";
            $term = "%$search%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY d.descricao ASC";
        return $this->fetchAll($sql, $params);
    }
}
