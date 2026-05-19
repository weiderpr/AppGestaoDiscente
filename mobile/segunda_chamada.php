<?php
/**
 * Vértice Acadêmico — Segunda Chamada (Mobile Professor)
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
if (($user['profile'] ?? '') !== 'Professor' && ($user['is_teacher'] ?? 0) != 1) {
    header('Location: /mobile/index.php');
    exit;
}

require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SegundaChamadaService.php';

$db = getDB();
$scService = new \App\Services\SegundaChamadaService($db);
$curInst = getCurrentInstitution();
$instId = $curInst['id'] ?? null;

if (!$instId) {
    header('Location: /select_institution.php');
    exit;
}

$showApplied = isset($_GET['show_applied']) && $_GET['show_applied'] === '1';

// POST Handler (Mark as Applied / Undo Applied / Mark Not Applied / Undo Not Applied)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $scId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'];
    if ($scId > 0) {
        try {
            if ($action === 'mark_applied') {
                $scService->markAsApplied($scId);
                $_SESSION['toast_success'] = "Instrumento avaliativo marcado como aplicado!";
            } elseif ($action === 'undo_applied') {
                $scService->undoMarkAsApplied($scId);
                $_SESSION['toast_success'] = "Marcação de instrumento aplicado desfeita!";
            } elseif ($action === 'mark_not_applied') {
                $justificativa = trim($_POST['justificativa'] ?? '');
                if (empty($justificativa)) {
                    throw new Exception("Justificativa é obrigatória.");
                }
                $scService->markAsNotApplied($scId, $justificativa);
                $_SESSION['toast_success'] = "Instrumento avaliativo marcado como não aplicado!";
            } elseif ($action === 'undo_not_applied') {
                $scService->undoMarkAsNotApplied($scId);
                $_SESSION['toast_success'] = "Marcação de não aplicado desfeita!";
            }
        } catch (Exception $e) {
            $_SESSION['toast_error'] = "Erro: " . $e->getMessage();
        }
    }
    header("Location: /mobile/segunda_chamada.php" . ($showApplied ? "?show_applied=1" : ""));
    exit;
}

$requests = $scService->getRequestsForTeacher((int)$user['id'], (int)$instId, $showApplied);

// Agrupa por disciplina
$groupedRequests = [];
foreach ($requests as $r) {
    $groupedRequests[$r['disciplina_nome']][] = $r;
}

$pageTitle = 'Segunda Chamada';
$currentPage = 'segunda_chamada';
require_once __DIR__ . '/header.php';
?>

<style>
    .m-header-details {
        margin-bottom: 0.75rem;
    }

    .m-breadcrumbs {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
    }

    .m-breadcrumbs a { color: var(--color-primary); text-decoration: none; }

    .m-discipline-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--color-primary);
        margin: 1.75rem 0 0.75rem 0;
        padding-bottom: 0.375rem;
        border-bottom: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .m-discipline-count {
        font-size: 0.75rem;
        background: var(--color-primary-light);
        color: var(--color-primary);
        padding: 2px 8px;
        border-radius: 12px;
        font-weight: 800;
    }

    .m-requests-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .m-request-card {
        padding: 1.25rem;
    }

    .m-request-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.875rem;
    }

    .m-request-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--border-color);
        box-shadow: var(--shadow-sm);
    }

    .m-request-avatar-text {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: var(--gradient-brand);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1rem;
        box-shadow: var(--shadow-sm);
    }

    .m-request-meta {
        flex: 1;
        min-width: 0;
    }

    .m-student-name {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 1rem;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 0.125rem;
    }

    .m-student-class {
        font-size: 0.75rem;
        color: var(--text-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .m-badge {
        font-size: 0.7rem;
        font-weight: 800;
        padding: 0.325rem 0.625rem;
        border-radius: 8px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .badge-pendente {
        background: #fef3c7;
        color: #d97706;
    }

    .badge-deferido {
        background: #d1fae5;
        color: #059669;
    }

    .m-request-details {
        border-top: 1px solid var(--border-color);
        padding-top: 0.75rem;
        font-size: 0.875rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        color: var(--text-secondary);
    }

    .m-detail-item strong {
        color: var(--text-primary);
    }

    .m-status-note {
        background: var(--bg-body);
        padding: 0.75rem;
        border-radius: 12px;
        border-left: 4px solid var(--color-primary);
        margin-top: 0.25rem;
    }

    .m-btn-anexo {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--color-primary);
        font-weight: 700;
        text-decoration: none;
        background: var(--color-primary-light);
        padding: 0.5rem 0.875rem;
        border-radius: 10px;
        transition: transform 0.1s;
        align-self: flex-start;
    }

    .m-btn-anexo:active {
        transform: scale(0.97);
    }

    .m-btn-aplicar {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: white;
        font-weight: 700;
        text-decoration: none;
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
        font-family: inherit;
        font-size: 0.8125rem;
        padding: 0.5rem 0.875rem;
        border-radius: 10px;
        transition: transform 0.1s, box-shadow 0.1s;
        cursor: pointer;
        box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);
    }

    .m-btn-aplicar:active {
        transform: scale(0.97);
        box-shadow: 0 2px 3px rgba(16, 185, 129, 0.1);
    }

    /* Ajustes específicos para tema escuro */
    [data-theme="dark"] .badge-pendente {
        background: rgba(217, 119, 6, 0.15);
        color: #fbbf24;
    }

    [data-theme="dark"] .badge-deferido {
        background: rgba(5, 150, 105, 0.15);
        color: #34d399;
    }

    [data-theme="dark"] .m-btn-anexo {
        background: rgba(79, 70, 229, 0.15);
        color: #818cf8;
    }

    [data-theme="dark"] .m-discipline-count {
        background: rgba(79, 70, 229, 0.2);
        color: #818cf8;
    }

    /* Toggle Switch & Undo Button Styling */
    .m-toggle-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--bg-surface-2nd);
        border: 1px solid var(--border-color);
        padding: 0.75rem 1rem;
        border-radius: 12px;
        margin-bottom: 1.25rem;
    }
    .m-toggle-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .m-switch {
        position: relative;
        display: inline-block;
        width: 46px;
        height: 24px;
    }
    .m-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .m-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--border-color);
        transition: .3s;
        border-radius: 24px;
    }
    .m-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    input:checked + .m-slider {
        background-color: var(--color-primary);
    }
    input:checked + .m-slider:before {
        transform: translateX(22px);
    }

    .badge-aplicado {
        background: #d1fae5;
        color: #059669;
        border: 1px solid #10b981;
    }
    [data-theme="dark"] .badge-aplicado {
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
        border-color: rgba(16, 185, 129, 0.3);
    }

    .m-btn-undo {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-secondary);
        font-weight: 700;
        text-decoration: none;
        background: var(--bg-surface-2nd);
        border: 1px solid var(--border-color);
        font-family: inherit;
        font-size: 0.8125rem;
        padding: 0.5rem 0.875rem;
        border-radius: 10px;
        transition: transform 0.1s, background-color 0.1s;
        cursor: pointer;
    }
    .m-btn-undo:active {
        transform: scale(0.97);
        background-color: var(--border-color);
    }

    .badge-nao-aplicado {
        background: #fee2e2;
        color: #dc2626;
        border: 1px solid #f87171;
    }
    [data-theme="dark"] .badge-nao-aplicado {
        background: rgba(220, 38, 38, 0.15);
        color: #f87171;
        border-color: rgba(220, 38, 38, 0.3);
    }

    .m-btn-nao-aplicar {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: white;
        font-weight: 700;
        text-decoration: none;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border: none;
        font-family: inherit;
        font-size: 0.8125rem;
        padding: 0.5rem 0.875rem;
        border-radius: 10px;
        transition: transform 0.1s, box-shadow 0.1s;
        cursor: pointer;
        box-shadow: 0 4px 6px rgba(239, 68, 68, 0.2);
    }
    .m-btn-nao-aplicar:active {
        transform: scale(0.97);
        box-shadow: 0 2px 3px rgba(239, 68, 68, 0.1);
    }
</style>

<div class="m-content">
    
    <div class="m-header-details">
        <div class="m-breadcrumbs">
            <a href="/mobile/index.php">Início</a>
            <span>/</span>
            <span>Segunda Chamada</span>
        </div>
        <h1 class="m-section-title" style="margin-bottom: 0.5rem;">Segunda Chamada</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem;">
            <?php 
                $countTotal = count($requests);
                echo $countTotal === 1 ? '1 solicitação vinculada.' : "$countTotal solicitações vinculadas.";
            ?>
        </p>
        
        <div class="m-toggle-container">
            <div class="m-toggle-label">
                <span>🗓️ Exibir histórico de aplicadas</span>
            </div>
            <label class="m-switch">
                <input type="checkbox" id="toggle_show_applied" <?= $showApplied ? 'checked' : '' ?> onchange="toggleApplied(this.checked)">
                <span class="m-slider"></span>
            </label>
        </div>
    </div>

    <script>
    function toggleApplied(checked) {
        window.location.href = '/mobile/segunda_chamada.php' + (checked ? '?show_applied=1' : '');
    }

    function handleNotApplied(event, id) {
        event.preventDefault();
        const just = prompt("Por favor, informe a justificativa para a não aplicação do instrumento:");
        if (just === null) return;
        const trimmed = just.trim();
        if (trimmed === "") {
            alert("A justificativa é obrigatória para marcar como não aplicado.");
            return;
        }
        document.getElementById('justificativa_' + id).value = trimmed;
        document.getElementById('form_not_applied_' + id).submit();
    }
    </script>

    <?php if (empty($groupedRequests)): ?>
        <div class="m-card" style="text-align:center; padding: 4rem 2rem; box-shadow: var(--shadow-md);">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📝</div>
            <p style="color:var(--text-muted); font-weight: 500;">Nenhuma solicitação de segunda chamada encontrada.</p>
        </div>
    <?php else: ?>
        <?php foreach ($groupedRequests as $disciplinaNome => $reqs): ?>
            <h2 class="m-discipline-title">
                <span><?= htmlspecialchars($disciplinaNome) ?></span>
                <span class="m-discipline-count"><?= count($reqs) ?></span>
            </h2>
            <div class="m-requests-list">
                <?php foreach ($reqs as $r): ?>
                    <div class="m-card m-request-card" style="<?= (int)$r['instrumento_aplicado'] === 1 || (int)$r['nao_aplicado'] === 1 ? 'opacity: 0.85;' : '' ?>">
                        <div class="m-request-header">
                            <?php if (!empty($r['aluno_photo']) && file_exists(__DIR__ . '/../' . $r['aluno_photo'])): ?>
                                <img src="/<?= htmlspecialchars($r['aluno_photo']) ?>" alt="" class="m-request-avatar">
                            <?php else: 
                                $initials = '';
                                foreach (explode(' ', trim($r['aluno_nome'])) as $part) {
                                    if(!empty($part)) $initials .= strtoupper(substr($part, 0, 1));
                                    if (strlen($initials) >= 2) break;
                                }
                            ?>
                                <div class="m-request-avatar-text"><?= $initials ?></div>
                            <?php endif; ?>
                            
                            <div class="m-request-meta">
                                <div class="m-student-name"><?= htmlspecialchars($r['aluno_nome']) ?></div>
                                <div class="m-student-class"><?= htmlspecialchars($r['curso_nome']) ?> • <?= htmlspecialchars($r['turma_nome']) ?></div>
                            </div>
                            
                            <div style="display:flex; flex-direction:column; align-items:flex-end; gap:0.25rem;">
                                <span class="m-badge badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span>
                                <?php if ((int)$r['instrumento_aplicado'] === 1): ?>
                                    <span class="m-badge badge-aplicado">✓ Aplicado</span>
                                <?php elseif ((int)$r['nao_aplicado'] === 1): ?>
                                    <span class="m-badge badge-nao-aplicado">✗ Não Aplicado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="m-request-details">
                            <div class="m-detail-item">
                                <strong>Atividade:</strong> <?= htmlspecialchars($r['atividade_nome']) ?>
                            </div>
                            <div class="m-detail-item">
                                <strong>Data Perdida:</strong> <?= date('d/m/Y', strtotime($r['data_atividade_perdida'])) ?>
                            </div>
                            <?php if (!empty($r['justificativa'])): ?>
                                <div class="m-detail-item">
                                    <strong>Justificativa:</strong> <?= nl2br(htmlspecialchars($r['justificativa'])) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($r['observacoes_status'])): ?>
                                <div class="m-detail-item m-status-note">
                                    <strong>Resolução:</strong> <?= nl2br(htmlspecialchars($r['observacoes_status'])) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ((int)$r['nao_aplicado'] === 1 && !empty($r['justificativa_nao_aplicacao'])): ?>
                                <div class="m-detail-item m-status-note" style="border-left-color: #ef4444; background: rgba(239, 68, 68, 0.05);">
                                    <strong>Justificativa da Não Aplicação:</strong> <?= nl2br(htmlspecialchars($r['justificativa_nao_aplicacao'])) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.25rem; align-items: center;">
                                <?php if (!empty($r['anexo_caminho'])): ?>
                                    <a href="/<?= htmlspecialchars($r['anexo_caminho']) ?>" target="_blank" class="m-btn-anexo">
                                        📎 Ver Anexo
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ((int)$r['instrumento_aplicado'] === 1): ?>
                                    <form method="POST" action="" onsubmit="return confirm('Deseja realmente desfazer a marcação de aplicado?');" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= \CsrfToken::generate() ?>">
                                        <input type="hidden" name="action" value="undo_applied">
                                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="m-btn-undo">
                                            ↩ Desfazer Aplicado
                                        </button>
                                    </form>
                                <?php elseif ((int)$r['nao_aplicado'] === 1): ?>
                                    <form method="POST" action="" onsubmit="return confirm('Deseja realmente desfazer a marcação de não aplicado?');" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= \CsrfToken::generate() ?>">
                                        <input type="hidden" name="action" value="undo_not_applied">
                                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="m-btn-undo">
                                            ↩ Desfazer Não Aplicado
                                        </button>
                                    </form>
                                <?php elseif ($r['status'] === 'Deferido'): ?>
                                    <div style="display: flex; gap: 0.5rem; width: 100%; align-items: center;">
                                        <form method="POST" action="" onsubmit="return confirm('Deseja realmente marcar este instrumento avaliativo como aplicado?');" style="margin: 0; flex: 1;">
                                            <input type="hidden" name="csrf_token" value="<?= \CsrfToken::generate() ?>">
                                            <input type="hidden" name="action" value="mark_applied">
                                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="m-btn-aplicar" style="width: 100%; justify-content: center; box-sizing: border-box; text-align: center; white-space: nowrap;">
                                                ✓ Aplicado
                                            </button>
                                        </form>

                                        <form method="POST" action="" id="form_not_applied_<?= $r['id'] ?>" style="margin: 0; flex: 1;" onsubmit="handleNotApplied(event, <?= $r['id'] ?>)">
                                            <input type="hidden" name="csrf_token" value="<?= \CsrfToken::generate() ?>">
                                            <input type="hidden" name="action" value="mark_not_applied">
                                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="justificativa" id="justificativa_<?= $r['id'] ?>">
                                            <button type="submit" class="m-btn-nao-aplicar" style="width: 100%; justify-content: center; box-sizing: border-box; text-align: center; white-space: nowrap;">
                                                ✗ Não Aplicado
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($r['status'] === 'Pendente'): ?>
                                    <span style="font-size: 0.8125rem; font-weight: 600; color: var(--text-muted); display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0;">
                                        ⏳ Aguardando encaminhamento da coordenação/colegiado.
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>

<?php if (isset($_SESSION['toast_success'])): ?>
<script>
    window.addEventListener('DOMContentLoaded', () => {
        showSuccess(<?= json_encode($_SESSION['toast_success']) ?>);
    });
</script>
<?php unset($_SESSION['toast_success']); endif; ?>

<?php if (isset($_SESSION['toast_error'])): ?>
<script>
    window.addEventListener('DOMContentLoaded', () => {
        showError(<?= json_encode($_SESSION['toast_error']) ?>);
    });
</script>
<?php unset($_SESSION['toast_error']); endif; ?>
