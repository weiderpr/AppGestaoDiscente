<?php
/**
 * Vértice Acadêmico — Partial: Listagem de Avaliações
 */
function renderStars($rating) {
    $rating = round($rating, 1);
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= round($rating)) {
            $stars .= '<span style="color:#fbbf24;">★</span>';
        } else {
            $stars .= '<span style="color:#94a3b8;opacity:0.4;">★</span>';
        }
    }
    return $stars;
}

// --- LISTAGEM ---
$st = $db->prepare("
    SELECT a.*, ta.nome as tipo_nome,
    (SELECT AVG(rp.nota) FROM respostas_perguntas rp 
     JOIN respostas_avaliacao ra ON rp.resposta_id = ra.id 
     WHERE ra.avaliacao_id = a.id) as media_respostas,
    (SELECT COUNT(DISTINCT ra.id) FROM respostas_avaliacao ra 
     WHERE ra.avaliacao_id = a.id) as total_respostas
    FROM avaliacoes a
    LEFT JOIN tipos_avaliacao ta ON ta.id = a.tipo_id
    WHERE a.deleted_at IS NULL 
    ORDER BY a.created_at DESC
");
$st->execute();
$avaliacoes = $st->fetchAll();
?>

<style>
.av-table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
.av-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.av-table th {
    padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted);
    background: var(--bg-surface-2nd); border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.av-table td { padding: .875rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.av-table tr:last-child td { border-bottom: none; }
.av-table tr:hover td { background: var(--bg-hover); }
.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: var(--radius-md);
    border: 1px solid var(--border-color); background: var(--bg-surface);
    color: var(--text-muted); cursor: pointer; font-size: .875rem;
    transition: all var(--transition-fast); text-decoration: none;
}
.action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
.action-btn.danger:hover { background: #fef2f2; color: var(--color-danger); border-color: var(--color-danger); }
</style>

<div class="card settings-card fade-in">
    <div class="settings-card-header">
        <div class="settings-card-icon">📋</div>
        <div style="flex:1;">
            <div class="settings-card-title">Gerenciar Avaliações</div>
            <div class="settings-card-desc">Visualize, edite ou remova as avaliações e questionários cadastrados.</div>
        </div>
        <div>
            <a href="?section=avaliacoes&sub=create" class="btn btn-primary btn-sm">➕ Nova Avaliação</a>
        </div>
    </div>
    <div class="av-table-wrap">
        <table class="av-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Média</th>
                    <th>Data de Criação</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($avaliacoes)): ?>
                <tr>
                    <td colspan="5" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
                        📋 Nenhuma avaliação cadastrada ainda.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($avaliacoes as $av): ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($av['nome']) ?></td>
                        <td>
                            <span class="badge" style="background:var(--bg-surface-2nd);color:var(--text-secondary);">
                                <?= htmlspecialchars($av['tipo_nome'] ?: 'Sem Tipo') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($av['total_respostas'] > 0): ?>
                            <div style="display:flex;flex-direction:column;gap:2px;" title="Média: <?= round($av['media_respostas'], 1) ?>/5 (<?= $av['total_respostas'] ?> respostas)">
                                <div style="font-size:1rem;line-height:1;"><?= renderStars($av['media_respostas']) ?></div>
                                <div style="font-size:0.65rem;color:var(--text-muted);"><?= $av['total_respostas'] ?> resposta(s)</div>
                            </div>
                            <?php else: ?>
                            <span style="color:var(--text-muted);font-size:.75rem;">Sem respostas</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--text-muted);"><?= date('d/m/Y H:i', strtotime($av['created_at'])) ?></td>
                        <td>
                            <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                                <a href="?section=avaliacoes&sub=create&id=<?= $av['id'] ?>" class="action-btn" title="Editar">✏️</a>
                                <button class="action-btn danger" onclick="deleteAvaliacao(<?= $av['id'] ?>, '<?= htmlspecialchars(addslashes($av['nome'])) ?>')" title="Excluir">🗑️</button>
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
    function deleteAvaliacao(id, name) {
        confirmModal({
            title: 'Confirmar Exclusão',
            message: `Deseja realmente excluir a avaliação <strong>«${name}»</strong>? Todas as perguntas associadas também serão removidas.`,
            confirmText: 'Sim, Excluir',
            confirmClass: 'btn-danger',
            onConfirm: () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_avaliacao">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
