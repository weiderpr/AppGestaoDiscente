<?php
/**
 * Vértice Acadêmico — Partial: Tipos de Avaliação
 */
// --- LISTAGEM ---
$st = $db->prepare("SELECT * FROM tipos_avaliacao WHERE deleted_at IS NULL ORDER BY nome ASC");
$st->execute();
$tipos = $st->fetchAll();
?>

<style>
.tipos-table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
.tipos-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.tipos-table th {
    padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted);
    background: var(--bg-surface-2nd); border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.tipos-table td { padding: .875rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.tipos-table tr:last-child td { border-bottom: none; }
.tipos-table tr:hover td { background: var(--bg-hover); }
.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: var(--radius-md);
    border: 1px solid var(--border-color); background: var(--bg-surface);
    color: var(--text-muted); cursor: pointer; font-size: .875rem;
    transition: all var(--transition-fast); text-decoration: none;
}
.action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
.action-btn.danger:hover { background: #fef2f2; color: var(--color-danger); border-color: var(--color-danger); }
[data-theme="dark"] .action-btn.danger:hover { background: #450a0a; }
</style>

<div class="card settings-card fade-in">
    <div class="settings-card-header">
        <div class="settings-card-icon">📂</div>
        <div style="flex:1;">
            <div class="settings-card-title">Tipos de Avaliação</div>
            <div class="settings-card-desc">Gerencie as categorias de questionários (ex: Satisfação, Formativa).</div>
        </div>
        <div>
            <button class="btn btn-primary btn-sm" onclick="openTipoModal()">➕ Novo Tipo</button>
        </div>
    </div>
    <div class="tipos-table-wrap">
        <table class="tipos-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Descrição</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tipos)): ?>
                <tr>
                    <td colspan="3" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
                        📂 Nenhum tipo de avaliação cadastrado.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($tipos as $t): ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($t['nome']) ?></td>
                        <td style="color:var(--text-muted);"><?= htmlspecialchars($t['descricao'] ?: '—') ?></td>
                        <td>
                            <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                                <button class="action-btn" onclick='openTipoModal(<?= json_encode($t) ?>)' title="Editar">✏️</button>
                                <button class="action-btn danger" onclick="deleteTipo(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['nome'])) ?>')" title="Excluir">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function openTipoModal(tipo = null) {
        const id = tipo ? tipo.id : '';
        const nome = tipo ? tipo.nome : '';
        const desc = tipo ? (tipo.descricao || '') : '';
        
        const title = id ? '✏️ Editar Tipo' : '➕ Novo Tipo';
        const content = `
            <form id="dynamicTipoForm" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="${id ? 'edit_tipo' : 'add_tipo'}">
                <input type="hidden" name="id" value="${id}">
                
                <div class="form-group">
                    <label class="form-label">Nome <span class="required">*</span></label>
                    <input type="text" name="nome" class="form-control" value="${nome.replace(/"/g, '&quot;')}" required>
                </div>
                
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3" placeholder="Opcional...">${desc}</textarea>
                </div>
            </form>
        `;

        showModal({
            title,
            content,
            size: 'md',
            closeOnOverlay: false,
            onOpen: (modal) => {
                const input = modal.querySelector('input[name="nome"]');
                if (input) setTimeout(() => input.focus(), 100);
            },
            buttons: [
                { text: 'Cancelar', class: 'btn-secondary', action: (e) => hideModal(e.target.closest('.modal-wrapper').id) },
                { 
                    text: id ? '💾 Atualizar' : '💾 Cadastrar', 
                    class: 'btn-primary', 
                    action: () => document.getElementById('dynamicTipoForm').submit() 
                }
            ]
        });
    }

    function deleteTipo(id, name) {
        confirmModal({
            title: 'Confirmar Exclusão',
            message: `Deseja realmente excluir o tipo de avaliação <strong>«${name}»</strong>? Esta ação não pode ser desfeita.`,
            confirmText: 'Sim, Excluir',
            confirmClass: 'btn-danger',
            onConfirm: () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_tipo">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
