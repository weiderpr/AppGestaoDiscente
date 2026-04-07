<?php
/**
 * Vértice Acadêmico — Partial: Gerenciar Respostas de Avaliação (Melhorado)
 */
$avId = (int)($_GET['id'] ?? 0);

// Buscar dados da avaliação
$stAv = $db->prepare("SELECT * FROM avaliacoes WHERE id = ? AND deleted_at IS NULL");
$stAv->execute([$avId]);
$avaliacao = $stAv->fetch();

if (!$avaliacao) {
    echo '<div class="alert alert-danger">Avaliação não encontrada.</div>';
    return;
}

// Buscar respostas com curso e turma
$stResp = $db->prepare("
    SELECT ra.*, cc.descricao as conselho_nome, c.name as curso_nome, t.description as turma_nome,
    (SELECT AVG(rp.nota) FROM respostas_perguntas rp WHERE rp.resposta_id = ra.id) as media_resposta
    FROM respostas_avaliacao ra
    JOIN conselhos_classe cc ON cc.id = ra.conselho_id
    JOIN courses c ON c.id = cc.course_id
    JOIN turmas t ON t.id = cc.turma_id
    WHERE ra.avaliacao_id = ?
    ORDER BY ra.created_at DESC
");

$stResp->execute([$avId]);
$respostas = $stResp->fetchAll();

// Função para estrelas
if (!function_exists('renderStarsDetailed')) {
    function renderStarsDetailed($rating) {
        $rating = round($rating, 1);
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= round($rating)) {
                $stars .= '<span style="color:#fbbf24; font-size: 1rem;">★</span>';
            } else {
                $stars .= '<span style="color:#94a3b8;opacity:0.4; font-size: 1rem;">★</span>';
            }
        }
        return $stars;
    }
}
?>

<style>
.av-table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
.av-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.av-table th {
    padding: .875rem 1rem; text-align: left; font-size: .75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted);
    background: var(--bg-surface-2nd); border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.av-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
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

.course-turma-info { display: flex; flex-direction: column; gap: 2px; }
.course-name { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.02em; }
.turma-name { font-weight: 600; color: var(--text-primary); }

.comment-box {
    max-width: 350px;
    font-size: 0.8125rem;
    color: var(--text-secondary);
    line-height: 1.4;
    background: var(--bg-surface-2nd);
    padding: 0.5rem 0.75rem;
    border-radius: var(--radius-md);
    border-left: 3px solid var(--border-color);
}
</style>

<div class="card settings-card fade-in">
    <div class="settings-card-header">
        <div class="settings-card-icon shadow-sm">📊</div>
        <div style="flex:1;">
            <div class="settings-card-title">Respostas: <?= htmlspecialchars($avaliacao['nome']) ?></div>
            <div class="settings-card-desc">Visualize e gerencie os feedbacks recebidos nesta avaliação.</div>
        </div>
        <div>
            <a href="?section=avaliacoes&sub=lista" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:0.5rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Voltar
            </a>
        </div>
    </div>
    <div class="av-table-wrap">
        <table class="av-table">
            <thead>
                <tr>
                    <th>Curso / Turma</th>
                    <th>Conselho</th>
                    <th>Data</th>
                    <th>Comentário</th>
                    <th style="text-align:center;">Média</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($respostas)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:3.5rem;color:var(--text-muted);">
                        <div style="font-size:2.5rem;margin-bottom:1rem;opacity:0.5;">📋</div>
                        <div>Nenhuma resposta encontrada para esta avaliação.</div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($respostas as $r): ?>
                    <tr>
                        <td>
                            <div class="course-turma-info">
                                <span class="course-name" title="<?= htmlspecialchars($r['curso_nome']) ?>"><?= htmlspecialchars($r['curso_nome']) ?></span>
                                <span class="turma-name"><?= htmlspecialchars($r['turma_nome']) ?></span>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:500;" title="<?= htmlspecialchars($r['conselho_nome']) ?>">
                                <?= htmlspecialchars($r['conselho_nome']) ?>
                            </div>
                        </td>
                        <td style="white-space:nowrap;color:var(--text-muted);">
                            <div style="font-weight:500;"><?= date('d/m', strtotime($r['created_at'])) ?></div>
                            <div style="font-size:0.75rem;"><?= date('H:i', strtotime($r['created_at'])) ?></div>
                        </td>
                        <td>
                            <?php if ($r['comentario']): ?>
                            <div class="comment-box" title="<?= htmlspecialchars($r['comentario']) ?>">
                                <?= htmlspecialchars(mb_strimwidth($r['comentario'], 0, 150, '...')) ?>
                            </div>
                            <?php else: ?>
                            <span style="color:var(--text-muted);font-style:italic;font-size:0.75rem;">Sem comentário</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($r['media_resposta'] > 0): ?>
                            <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                                <div style="line-height:1;"><?= renderStarsDetailed($r['media_resposta']) ?></div>
                                <div style="font-size:0.65rem;color:var(--text-muted);font-weight:600;"><?= round($r['media_resposta'], 1) ?> / 5.0</div>
                            </div>
                            <?php else: ?>
                                <span style="color:var(--text-muted);font-size:.75rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;justify-content:center;">
                                <button class="action-btn danger" onclick="deleteResposta(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['conselho_nome'])) ?>')" title="Excluir Resposta">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </button>
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
    function deleteResposta(id, conselho) {
        confirmModal({
            title: 'Confirmar Exclusão',
            message: `Deseja realmente excluir esta resposta vinculada ao conselho <strong>«${conselho}»</strong>? Esta ação não pode ser desfeita.`,
            confirmText: 'Sim, Excluir',
            confirmClass: 'btn-danger',
            onConfirm: () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_resposta">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
