<?php
/**
 * Vértice Acadêmico — Helper de Auditoria
 *
 * Permite disparar logs de auditoria diretamente em scripts procedurais
 * sem a necessidade de criar um Service dedicado para cada módulo.
 *
 * Uso:
 *   $audit = new AuditHelper();
 *   $audit->log('CREATE', 'tabela', $id, null, $newData);
 *   $audit->log('UPDATE', 'tabela', $id, $oldData, $newData);
 *   $audit->log('DELETE', 'tabela', $id, $oldData, null);
 */

namespace App\Services;

class AuditHelper extends Service {
    /**
     * Registra um evento de auditoria.
     *
     * @param string     $action    CREATE | UPDATE | DELETE
     * @param string     $table     Nome da tabela afetada
     * @param int        $recordId  ID do registro afetado
     * @param array|null $old       Estado anterior do registro
     * @param array|null $new       Novo estado do registro
     * @param int|null   $actorId   ID do autor (sobrescreve a sessão)
     */
    public function log(
        string $action,
        string $table,
        int $recordId,
        ?array $old = null,
        ?array $new = null,
        ?int $actorId = null
    ): void {
        $this->audit($action, $table, $recordId, $old, $new, $actorId);
    }
}
