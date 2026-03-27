<?php
/**
 * Vértice Acadêmico — Listagem de Atendimentos
 */
require_once __DIR__ . '/../includes/auth.php';
hasDbPermission('atendimentos.index'); // Já redireciona internamente se não houver acesso

$user = getCurrentUser();

$db      = getDB();
$inst    = getCurrentInstitution();
$instId  = $inst['id'];

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/atendimentos/index.php'));
    exit;
}

require_once __DIR__ . '/../includes/atendimentos_functions.php';

// Filtros
$search = trim($_GET['search'] ?? '');
$conselhoId = (int)($_GET['conselho_id'] ?? 0);
$activeTab = $_GET['tab'] ?? 'tab-realizados';

$filters = ['search' => $search];

// Busca apenas os conselhos que possuem encaminhamentos pendentes
$stC = $db->prepare("
    SELECT DISTINCT cc.id, cc.descricao, c.name as course_name, t.description as turma_name, cc.created_at
    FROM conselhos_classe cc
    JOIN courses c ON cc.course_id = c.id
    JOIN turmas t ON cc.turma_id = t.id
    JOIN conselho_encaminhamentos ce ON ce.conselho_id = cc.id
    WHERE cc.institution_id = ? 
    AND ce.id NOT IN (SELECT encaminhamento_id FROM atendimentos WHERE encaminhamento_id IS NOT NULL AND deleted_at IS NULL)
    ORDER BY cc.created_at DESC
");
$stC->execute([$instId]);
$conselhosList = $stC->fetchAll();

// Busca dados para as abas
$atendimentos = getAllAtendimentos($instId, $filters);
$pendentes    = getPendingReferrals($instId, $conselhoId);

$pageTitle = 'Atendimentos Profissionais';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Estrutura de Abas */
.tabs-nav { display:flex; gap:.25rem; border-bottom:1px solid var(--border-color); margin-bottom:1.5rem; }
.tab-btn { 
    padding:.75rem 1.25rem; border:none; background:none; cursor:pointer; 
    font-size:.875rem; font-weight:500; color:var(--text-muted); 
    border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .2s;
}
.tab-btn:hover { color:var(--text-primary); }
.tab-btn.active { color:var(--color-primary); border-bottom-color:var(--color-primary); }
.tab-content { display:none; }
.tab-content.active { display:block; }

/* Tabelas */
.atend-table-wrap { overflow-x:auto; border-radius:var(--radius-lg); }
.atend-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.atend-table th {
    padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:600;
    text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted);
    background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color);
    white-space:nowrap;
}
.atend-table td { padding:.875rem 1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; }
.atend-table tr:hover td { background:var(--bg-hover); }

.badge-referral { 
    display:inline-block; padding:.125rem .5rem; background:var(--color-primary-light); 
    color:var(--color-primary); border-radius:var(--radius-sm); font-size:.7rem; font-weight:600;
}

.action-btn {
    display:inline-flex; align-items:center; justify-content:center;
    width:32px; height:32px; border-radius:var(--radius-md);
    border:1px solid var(--border-color); background:var(--bg-surface);
    color:var(--text-muted); cursor:pointer; font-size:.875rem;
    transition:all var(--transition-fast); text-decoration:none;
}
.action-btn:hover { background:var(--bg-hover); color:var(--text-primary); }
</style>

<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 class="page-title">📝 Atendimentos Profissionais</h1>
        <p class="page-subtitle">Registro e acompanhamento de intervenções pedagógicas e sociais.</p>
    </div>
    <button class="btn btn-primary" onclick="openAtendimentoModal({})">➕ Novo Atendimento</button>
</div>

<!-- Tabs Control -->
<div class="tabs-nav fade-in">
    <button class="tab-btn <?= $activeTab === 'tab-realizados' ? 'active' : '' ?>" data-tab="tab-realizados" onclick="showTab('tab-realizados')">
        Atendimentos Realizados (<?= count($atendimentos) ?>)
    </button>
    <button class="tab-btn <?= $activeTab === 'tab-pendentes' ? 'active' : '' ?>" data-tab="tab-pendentes" onclick="showTab('tab-pendentes')">
        Encaminhamentos Pendentes (<?= count($pendentes) ?>)
    </button>
</div>

<!-- Filtros: Atendimentos Realizados -->
<div id="filters-realizados" class="card fade-in" style="margin-bottom:1.25rem; <?= $activeTab === 'tab-realizados' ? '' : 'display:none;' ?>">
    <div class="card-body" style="padding:1rem 1.5rem;">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="tab" value="tab-realizados">
            <div class="form-group" style="flex:1;min-width:220px;margin:0;">
                <div class="input-group">
                    <span class="input-icon">🔍</span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Buscar por nome do aluno, turma ou conteúdo..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($search): ?>
            <a href="/atendimentos/index.php?tab=tab-realizados" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Filtros: Encaminhamentos Pendentes -->
<div id="filters-pendentes" class="card fade-in" style="margin-bottom:1.25rem; <?= $activeTab === 'tab-pendentes' ? '' : 'display:none;' ?>">
    <div class="card-body" style="padding:1rem 1.5rem;">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="tab" value="tab-pendentes">
            <div class="form-group" style="flex:1; min-width:350px; margin:0;">
                <label class="form-label" style="font-size:0.75rem;">Filtrar por Conselho de Classe (Curso - Turma - Etapa)</label>
                <select name="conselho_id" class="form-control" onchange="this.form.submit()">
                    <option value="0">--- Todos os Conselhos ---</option>
                    <?php foreach ($conselhosList as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $conselhoId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['course_name']) ?> - <?= htmlspecialchars($c['turma_name']) ?> (<?= htmlspecialchars($c['descricao']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($conselhoId > 0): ?>
            <a href="/atendimentos/index.php?tab=tab-pendentes" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Conteúdo: Atendimentos Realizados -->
<div id="tab-realizados" class="tab-content <?= $activeTab === 'tab-realizados' ? 'active' : '' ?> fade-in">
    <div class="card">
        <div class="atend-table-wrap">
            <table class="atend-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Profissional</th>
                        <th>Aluno / Turma</th>
                        <th>Encaminhamento</th>
                        <th style="width:100px; text-align:center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($atendimentos)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:3rem;color:var(--text-muted);">
                        Nenhum atendimento realizado encontrado.
                    </td></tr>
                    <?php endif; ?>
                    <?php foreach ($atendimentos as $a): ?>
                    <tr>
                        <td style="white-space:nowrap;">
                            <div style="font-weight:600; color:var(--text-primary);">
                                <?= date('d/m/Y', strtotime($a['data_atendimento'])) ?>
                            </div>
                            <div style="font-size:.7rem; color:var(--text-muted);">
                                <?= date('H:i', strtotime($a['created_at'])) ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($a['user_name']) ?></div>
                            <div style="font-size:.7rem; color:var(--text-muted);"><?= htmlspecialchars($a['user_profile']) ?></div>
                        </td>
                        <td>
                            <?php if ($a['aluno_nome']): ?>
                                <div style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($a['aluno_nome']) ?></div>
                            <?php endif; ?>
                            <div style="font-size:.75rem; color:var(--text-muted);">
                                <?= $a['turma_nome'] ? 'Turma: ' . htmlspecialchars($a['turma_nome']) : '—' ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($a['encaminhamento_id']): ?>
                                <span class="badge-referral" title="<?= htmlspecialchars($a['encaminhamento_texto']) ?>">
                                    📌 #<?= $a['encaminhamento_id'] ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:.75rem;">Espontâneo</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex; justify-content:center; gap:.5rem;">
                                <button class="action-btn" title="Ver Detalhes" 
                                        onclick="showAtendimentoDetails(<?= htmlspecialchars(json_encode($a)) ?>)">👁️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Conteúdo: Encaminhamentos Pendentes -->
<div id="tab-pendentes" class="tab-content <?= $activeTab === 'tab-pendentes' ? 'active' : '' ?> fade-in">
    <div class="card">
        <div class="atend-table-wrap">
            <table class="atend-table">
                <thead>
                    <tr>
                        <th>Data Pedido</th>
                        <th>Origem (Conselho)</th>
                        <th>Aluno / Turma</th>
                        <th>O que foi solicitado?</th>
                        <th style="width:150px; text-align:center;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendentes)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:3rem;color:var(--text-muted);">
                        Nenhum encaminhamento pendente no momento.
                    </td></tr>
                    <?php endif; ?>
                    <?php foreach ($pendentes as $p): ?>
                    <tr>
                        <td style="white-space:nowrap;">
                            <div style="font-weight:600; color:var(--text-primary);">
                                <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($p['conselho_nome']) ?></div>
                            <div style="font-size:.7rem; color:var(--text-muted);">Referencial: #<?= $p['id'] ?></div>
                        </td>
                        <td>
                            <?php if ($p['aluno_nome']): ?>
                                <div style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($p['aluno_nome']) ?></div>
                            <?php endif; ?>
                            <div style="font-size:.75rem; color:var(--text-muted);">
                                <?= $p['turma_nome'] ? 'Turma: ' . htmlspecialchars($p['turma_nome']) : '—' ?>
                            </div>
                        </td>
                        <td style="max-width:300px;">
                            <div style="font-size:.875rem; color:var(--text-secondary); line-height:1.4;">
                                <?= nl2br(htmlspecialchars($p['texto'])) ?>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <button class="btn btn-primary btn-sm" onclick="realizarAtendimento(<?= htmlspecialchars(json_encode($p)) ?>)">
                                ✍️ Atender
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    
    document.getElementById(tabId).classList.add('active');
    document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
    
    // Alterna áreas de filtro
    document.getElementById('filters-realizados').style.display = (tabId === 'tab-realizados' ? 'block' : 'none');
    document.getElementById('filters-pendentes').style.display = (tabId === 'tab-pendentes' ? 'block' : 'none');
    
    // Atualiza URL sem recarregar (opcional, mas bom para UX)
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url);
}

function realizarAtendimento(referral) {
    // Abre o modal de atendimento com os dados do encaminhamento
    openAtendimentoModal({
        aluno_id: referral.aluno_id,
        aluno_photo: referral.aluno_photo,
        turma_id: referral.turma_id,
        encaminhamento_id: referral.id,
        target_name: referral.aluno_nome || referral.turma_nome || 'Encaminhamento #' + referral.id,
        referral_text: referral.texto
    });
}

function showAtendimentoDetails(atend) {
    // Por enquanto, podemos usar o alertModal ou Modal.open para mostrar os textos
    Modal.open({
        title: 'Detalhes do Atendimento — ' + atend.data_atendimento,
        content: `
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; padding:1.5rem;">
                <div>
                    <h4 style="margin-bottom:0.5rem; color:var(--color-primary);">🔒 Profissional (Privado)</h4>
                    <div style="background:var(--bg-surface-2nd); padding:1rem; border-radius:var(--radius-md); font-size:0.875rem; max-height:300px; overflow-y:auto;">
                        ${atend.professional_text || '<em>Sem conteúdo registrado.</em>'}
                    </div>
                </div>
                <div>
                    <h4 style="margin-bottom:0.5rem; color:var(--color-primary);">📢 Público / Encaminhamento</h4>
                    <div style="background:var(--bg-surface-2nd); padding:1rem; border-radius:var(--radius-md); font-size:0.875rem; max-height:300px; overflow-y:auto;">
                        ${atend.public_text || '<em>Sem conteúdo registrado.</em>'}
                    </div>
                </div>
            </div>
            ${atend.encaminhamento_texto ? `
                <div style="margin: 0 1.5rem 1.5rem; padding:1rem; border-top:1px solid var(--border-color); background:#fff9db; border-radius:var(--radius-md);">
                    <strong style="font-size:0.75rem; text-transform:uppercase; color:#856404;">Contexto (Pedido Original):</strong>
                    <p style="font-size:0.875rem; margin-top:0.25rem;">${atend.encaminhamento_texto}</p>
                </div>
            ` : ''}
        `,
        size: 'lg'
    });
}

// Escuta evento de salvamento para recarregar a página
window.addEventListener('atendimentoSaved', () => {
    setTimeout(() => {
        window.location.reload();
    }, 1500);
});
</script>

<?php 
require_once __DIR__ . '/../includes/atendimento_modal.php';
require_once __DIR__ . '/../includes/footer.php'; 
?>
