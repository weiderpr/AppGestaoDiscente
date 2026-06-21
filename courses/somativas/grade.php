<?php
/**
 * Vértice Acadêmico — Grade de Horários da Somativa
 * Página de configuração drag-and-drop do calendário somativo
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/SomativaService.php';

requireLogin();
$user   = getCurrentUser();
hasDbPermission('somativas.index');

$inst   = getCurrentInstitution();
$instId = $inst['id'] ?? 0;

$id      = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /courses/somativas/index.php'); exit; }

$service  = new \App\Services\SomativaService();
$somativa = $service->findById($id, $instId);

if (!$somativa) { header('Location: /courses/somativas/index.php'); exit; }
if (empty($somativa['turmas'])) {
    header('Location: /courses/somativas/edit.php?id=' . $id . '&tab=turmas');
    exit;
}
if (empty($somativa['slots'])) {
    header('Location: /courses/somativas/edit.php?id=' . $id . '&tab=slots');
    exit;
}

$turmas       = $somativa['turmas'];
$slots        = $somativa['slots'];
$dates        = $service->getDatesInRange($somativa['data_inicio'], $somativa['data_fim']);
$suggestions  = $service->getSuggestions($id);
$violations   = $service->validateGrade($id);
$regrasAtivas = $service->getRestricoes($id);

// Dados completos de grade: indexado por somativa_turma_id
$gradeData  = [];
$naoAlocado = [];
foreach ($turmas as $t) {
    $stId              = (int)$t['somativa_turma_id'];
    $grade             = $service->getGrade($id, $stId);
    $gradeIndex        = [];
    foreach ($grade as $g) {
        $gradeIndex[$g['data_prova'] . '_' . $g['slot_config_id']] = $g;
    }
    $gradeData[$stId]  = $gradeIndex;
    $naoAlocado[$stId] = $service->getDisciplinasNaoAlocadas($id, $stId);
}

// Professores ocupados (aplicador/volante/naapi) por data+slot em toda a somativa
$ocupadosPorSlot = [];
foreach ($gradeData as $_stIdOc => $gradeIndexOc) {
    foreach ($gradeIndexOc as $keyOc => $gOc) {
        if (!empty($gOc['aplicador_id']))       $ocupadosPorSlot[$keyOc][(int)$gOc['aplicador_id']] = true;
        if (!empty($gOc['volante_id']))          $ocupadosPorSlot[$keyOc][(int)$gOc['volante_id']] = true;
        if (!empty($gOc['naapi_aplicador_id'])) $ocupadosPorSlot[$keyOc][(int)$gOc['naapi_aplicador_id']] = true;
    }
}

// Disponibilidade de professores por data/slot — todas as turmas da instituição
$availability = $service->getTeacherAvailability(
    (int)$somativa['institution_id'], $dates, $slots
);

$statusLabels = [
    'Rascunho'     => 'badge-neutral',
    'Configurando' => 'badge-warning',
    'Publicado'    => 'badge-success',
    'Encerrado'    => 'badge-muted',
];

$pageTitle = 'Grade Somativa — ' . $somativa['nome'];
$extraCSS  = ['/assets/css/somativas.css'];
$extraScripts = ['/assets/js/somativas_grade.js'];
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $user['theme'] ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Grade Somativa — <?= htmlspecialchars($somativa['nome']) ?> — Vértice Acadêmico</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/components/toast.css">
    <link rel="stylesheet" href="/assets/css/components/loading.css">
    <link rel="stylesheet" href="/assets/css/components/modal.css?v=1.5">
    <link rel="stylesheet" href="/assets/css/somativas.css?v=<?= time() ?>">

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/components/Toast.js"></script>
    <script src="/assets/js/components/Loading.js"></script>
    <script src="/assets/js/components/Modal.js?v=3"></script>
    <script>
        window.AppPermissions = <?= json_encode([
            'somativas.update' => hasDbPermission('somativas.update', false) ? 1 : 0,
        ]) ?>;
        window.csrfToken = "<?= csrf_token() ?>";
        window.AppConfig = { exibirNotificacoes: 0 };
    </script>
</head>
<body class="grade-page-body">

<!-- ════════════════════════════════════════════════════
     BARRA SUPERIOR DA GRADE
════════════════════════════════════════════════════ -->
<div class="grade-topbar">
    <div class="grade-topbar-left">
        <a href="index.php" class="grade-back-btn" title="Voltar para lista">
            ← Voltar
        </a>
        <div class="grade-topbar-info">
            <h1 class="grade-title"><?= htmlspecialchars($somativa['nome']) ?></h1>
            <div class="grade-period">
                📅 <?= date('d/m/Y', strtotime($somativa['data_inicio'])) ?>
                &mdash; <?= date('d/m/Y', strtotime($somativa['data_fim'])) ?>
                <span class="grade-period-sep">·</span>
                Máx. <?= $somativa['max_provas_por_dia'] ?> prova<?= $somativa['max_provas_por_dia'] != 1 ? 's' : '' ?>/dia
            </div>
        </div>
        <span class="badge <?= $statusLabels[$somativa['status']] ?? 'badge-neutral' ?>">
            <?= $somativa['status'] ?>
        </span>
    </div>
    <div class="grade-topbar-right">
        <button type="button" class="btn btn-secondary btn-sm" id="btn-analisar-professores">
            📊 Analisar Professores
        </button>
        <?php if (hasDbPermission('somativas.update', false)): ?>
        <button type="button" class="btn btn-danger btn-sm" id="btn-limpar-cards">
            🗑 Limpar Cards
        </button>
        <button type="button" class="btn btn-primary btn-sm" id="btn-auto-alocar">
            ⚡ Alocar Automaticamente
        </button>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-imprimir">🖨️ Imprimir</button>
        <a href="edit.php?id=<?= $id ?>" class="btn btn-ghost btn-sm">✏️ Editar</a>
    </div>
</div>

<!-- ════════════════════════════════════════════════════
     TABS DE TURMA
════════════════════════════════════════════════════ -->
<div class="grade-turma-tabs" id="grade-turma-tabs">
    <?php foreach ($turmas as $i => $t): ?>
    <button type="button"
            class="grade-turma-tab <?= $i === 0 ? 'active' : '' ?>"
            data-st-id="<?= $t['somativa_turma_id'] ?>"
            data-turma-id="<?= $t['turma_id'] ?>">
        <span class="gtt-course"><?= htmlspecialchars($t['course_name']) ?></span>
        <span class="gtt-turma"><?= htmlspecialchars($t['turma_desc']) ?></span>
        <?php $pendentes = count($naoAlocado[$t['somativa_turma_id']] ?? []); ?>
        <?php if ($pendentes > 0): ?>
        <span class="gtt-badge"><?= $pendentes ?></span>
        <?php endif; ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- ════════════════════════════════════════════════════
     CORPO PRINCIPAL: GRADE + SIDEBAR
════════════════════════════════════════════════════ -->
<div class="grade-body">

    <!-- ── Grade (centro) ─────────────────────────────── -->
    <div class="grade-grid-wrap" id="grade-grid-wrap">

        <?php foreach ($turmas as $i => $t): ?>
        <?php
            $stId     = (int)$t['somativa_turma_id'];
            $gData    = $gradeData[$stId] ?? [];
            $avail    = $availability;
        ?>
        <div class="grade-panel <?= $i === 0 ? 'active' : '' ?>"
             id="grade-panel-<?= $stId ?>"
             data-st-id="<?= $stId ?>"
             data-turma-id="<?= $t['turma_id'] ?>">

            <div class="grade-table-scroll">
                <table class="grade-table">
                    <thead>
                        <tr>
                            <th class="grade-th-slot">Horário</th>
                            <?php foreach ($dates as $d): ?>
                            <?php 
                                $isSecondCall = (!empty($somativa['segunda_chamada_data']) && $d['data'] === $somativa['segunda_chamada_data']);
                            ?>
                            <th class="grade-th-date <?= $d['is_weekend'] ? 'weekend' : '' ?> <?= $isSecondCall ? 'last-day' : '' ?>">
                                <span class="date-dow"><?= $d['dia_semana_br'] ?></span>
                                <span class="date-label"><?= $d['label'] ?></span>
                                <?php if ($isSecondCall): ?>
                                <span class="date-hint" title="Data reservada para Segunda Chamada">2ª Ch.</span>
                                <?php endif; ?>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $slot): ?>
                        <tr class="grade-row" data-slot-id="<?= $slot['id'] ?>">
                            <td class="grade-td-slot">
                                <div class="slot-time-label">
                                    <?php if ($slot['label']): ?>
                                    <span class="slot-label-txt"><?= htmlspecialchars($slot['label']) ?></span>
                                    <?php endif; ?>
                                    <span class="slot-time"><?= substr($slot['horario_inicio'],0,5) ?>–<?= substr($slot['horario_fim'],0,5) ?></span>
                                </div>
                            </td>

                            <?php foreach ($dates as $d): ?>
                            <?php
                                $cellKey  = $d['data'] . '_' . $slot['id'];
                                $cellData = $gData[$cellKey] ?? null;
                                $cellAvail= $avail[$cellKey] ?? [];
                                $hasCard  = !empty($cellData);
                                $isSecondCall = (!empty($somativa['segunda_chamada_data']) && $d['data'] === $somativa['segunda_chamada_data']);

                                // Professores com aula neste horário que não foram alocados em nenhuma função
                                $professoresLivres = [];
                                if ($hasCard) {
                                    $ocupadosNeste = $ocupadosPorSlot[$cellKey] ?? [];
                                    foreach ($cellAvail as $av) {
                                        if (!isset($ocupadosNeste[$av['id']])) {
                                            $daTurma        = in_array((int)$t['turma_id'], $av['turma_ids'] ?? []);
                                            $av['da_turma'] = $daTurma;
                                            $av['da_curso']  = !$daTurma && in_array((int)$t['course_id'], $av['course_ids'] ?? []);
                                            $professoresLivres[] = $av;
                                        }
                                    }
                                    // Ordem: turma > curso > outros
                                    usort($professoresLivres, function($a, $b) {
                                        $sa = $a['da_turma'] ? 2 : ($a['da_curso'] ? 1 : 0);
                                        $sb = $b['da_turma'] ? 2 : ($b['da_curso'] ? 1 : 0);
                                        return $sb <=> $sa;
                                    });
                                }
                            ?>
                            <td class="grade-cell <?= $d['is_weekend'] ? 'weekend' : '' ?> <?= $isSecondCall ? 'last-day' : '' ?> <?= $hasCard ? 'has-card' : 'empty-cell' ?>"
                                data-date="<?= $d['data'] ?>"
                                data-slot-id="<?= $slot['id'] ?>"
                                data-st-id="<?= $stId ?>"
                                data-turma-id="<?= $t['turma_id'] ?>"
                                data-somativa-id="<?= $id ?>"
                                <?php if ($hasCard): ?>
                                data-grade-id="<?= $cellData['id'] ?>"
                                <?php endif; ?>>

                                <?php if ($hasCard): ?>
                                <!-- Card alocado -->
                                <div class="grade-card <?= strtolower(str_replace(' ', '-', $cellData['tipo'])) ?>"
                                     draggable="true"
                                     data-grade-id="<?= $cellData['id'] ?>"
                                     data-disc-id="<?= $cellData['somativa_disciplina_id'] ?>"
                                     data-disc-cod="<?= htmlspecialchars($cellData['disciplina_codigo'] ?? '') ?>"
                                     data-disc-nome="<?= htmlspecialchars($cellData['disciplina_nome'] ?? '') ?>"
                                     data-tipo="<?= $cellData['tipo'] ?>"
                                     data-aplicador-id="<?= $cellData['aplicador_id'] ?? '' ?>"
                                     data-aplicador-nome="<?= htmlspecialchars($cellData['aplicador_nome'] ?? '') ?>"
                                     data-volante-id="<?= $cellData['volante_id'] ?? '' ?>"
                                     data-volante-nome="<?= htmlspecialchars($cellData['volante_nome'] ?? '') ?>"
                                     data-naapi-aplicador-id="<?= $cellData['naapi_aplicador_id'] ?? '' ?>"
                                     data-naapi-aplicador-nome="<?= htmlspecialchars($cellData['naapi_aplicador_nome'] ?? '') ?>"
                                     data-ambiente-id="<?= $cellData['ambiente_id'] ?? '' ?>"
                                     data-ambiente-nome="<?= htmlspecialchars($cellData['ambiente_desc'] ?? '') ?>"
                                     data-obs="<?= htmlspecialchars($cellData['observacoes'] ?? '') ?>">
                                    <div class="gc-disc">
                                        <?php if ($cellData['tipo'] !== 'Normal'): ?>
                                        <span class="gc-tipo-badge"><?= $cellData['tipo'] ?></span>
                                        <?php endif; ?>
                                        <div class="gc-disc-title-row">
                                            <?php if (!empty($violations[$cellData['id']])): ?>
                                            <span class="gc-warning-icon" title="<?= htmlspecialchars(implode("\n", $violations[$cellData['id']])) ?>" role="img" aria-label="Aviso">⚠️</span>
                                            <?php endif; ?>
                                            <?php if ($professoresLivres): ?>
                                            <span class="gc-livres-icon" role="img" aria-label="Professores disponíveis não alocados">👤
                                                <span class="gc-livres-tooltip">
                                                    <strong>Livre neste horário:</strong>
                                                    <?php foreach ($professoresLivres as $lp):
                                                        if (!empty($lp['da_turma'])) {
                                                            $lpClass = ' gc-livres-item--turma';
                                                            $lpBadge = '<span class="gc-livres-badge gc-livres-badge--turma">turma</span>';
                                                        } elseif (!empty($lp['da_curso'])) {
                                                            $lpClass = ' gc-livres-item--curso';
                                                            $lpBadge = '<span class="gc-livres-badge gc-livres-badge--curso">curso</span>';
                                                        } else {
                                                            $lpClass = '';
                                                            $lpBadge = '';
                                                        }
                                                    ?>
                                                    <span class="gc-livres-item<?= $lpClass ?>">
                                                        <?= $lpBadge ?>
                                                        <span class="gc-livres-name"><?= htmlspecialchars($lp['name']) ?></span>
                                                        <?php if (!empty($lp['turma'])): ?>
                                                        <span class="gc-livres-sub"><?= htmlspecialchars($lp['turma']) ?><?= !empty($lp['disc']) ? ' · ' . htmlspecialchars($lp['disc']) : '' ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <?php endforeach; ?>
                                                </span>
                                            </span>
                                            <?php endif; ?>
                                            <span class="gc-disc-name"><?= htmlspecialchars($cellData['disciplina_nome'] ?? '—') ?></span>
                                        </div>
                                    </div>
                                    <?php if ($cellData['aplicador_nome']): ?>
                                    <div class="gc-pessoa gc-aplicador">
                                        <span class="gc-pessoa-role">Aplic.</span>
                                        <span class="gc-pessoa-name"><?= htmlspecialchars($cellData['aplicador_nome']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($cellData['volante_nome']): ?>
                                    <div class="gc-pessoa gc-volante">
                                        <span class="gc-pessoa-role">Vol.</span>
                                        <span class="gc-pessoa-name"><?= htmlspecialchars($cellData['volante_nome']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($cellData['ambiente_desc']): ?>
                                    <div class="gc-sala">
                                        🏫 <?= htmlspecialchars($cellData['ambiente_desc']) ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php
                                    // Bloco NAAPI: só para provas Normais quando NAAPI está ativo na somativa
                                    $naapiAtivo = !empty($somativa['naapi_ambiente_id']);
                                    if ($naapiAtivo && $cellData['tipo'] === 'Normal'):
                                    ?>
                                    <div class="gc-naapi-block">
                                        <div class="gc-naapi-header">
                                            <span class="gc-naapi-badge">NAAPI</span>
                                            <span class="gc-naapi-tempo">+<?= (int)($somativa['naapi_tempo_extra_min'] ?? 60) ?> min</span>
                                        </div>
                                        <?php if ($cellData['naapi_aplicador_nome']): ?>
                                        <div class="gc-pessoa gc-naapi-aplicador">
                                            <span class="gc-pessoa-role">Aplic.</span>
                                            <span class="gc-pessoa-name"><?= htmlspecialchars($cellData['naapi_aplicador_nome']) ?></span>
                                        </div>
                                        <?php else: ?>
                                        <div class="gc-naapi-sem-aplicador">Aplicador não definido</div>
                                        <?php endif; ?>
                                        <?php if ($somativa['naapi_ambiente_desc']): ?>
                                        <div class="gc-sala gc-naapi-sala">
                                            🏫 <?= htmlspecialchars($somativa['naapi_ambiente_desc']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <div class="gc-actions">
                                        <button type="button" class="gc-btn-edit" title="Editar"
                                                onclick="openSlotModal(this.closest('.grade-cell'))">✏️</button>
                                        <button type="button" class="gc-btn-clear" title="Limpar slot"
                                                onclick="clearSlot(<?= $cellData['id'] ?>, this.closest('.grade-cell'))">✕</button>
                                    </div>
                                </div>

                                <?php else: ?>
                                <!-- Célula vazia + dica de disponibilidade -->
                                <div class="grade-cell-empty">
                                    <span class="grade-cell-drop-hint">Solte aqui</span>
                                    <?php if ($cellAvail): ?>
                                    <div class="grade-cell-avail">
                                        <?php foreach ($cellAvail as $av): ?>
                                        <span class="avail-chip" title="Em aula neste horário: <?= htmlspecialchars($av['name']) ?> (<?= htmlspecialchars($av['disc']) ?>)">
                                            <?= htmlspecialchars(explode(' ', $av['name'])[0]) ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

    </div><!-- /grade-grid-wrap -->

    <!-- ── Sidebar Wrapper ─────────────────────────────── -->
    <div class="grade-sidebar-wrapper">
        <button type="button" class="sidebar-toggle-btn" id="btn-toggle-sidebar" title="Recolher/Expandir barra lateral">
            <span>›</span>
        </button>
        <aside class="grade-sidebar" id="grade-sidebar">
            <div class="gs-section">
                <h3 class="gs-title">📚 Disciplinas a Alocar</h3>
                <div class="gs-subtitle">Arraste para um horário na grade</div>
            </div>

            <?php foreach ($turmas as $i => $t): ?>
            <?php $stId = (int)$t['somativa_turma_id']; ?>
            <div class="gs-turma-disc <?= $i === 0 ? 'active' : '' ?>"
                 id="gs-disc-<?= $stId ?>">

                <div class="disc-cards-list" id="disc-cards-<?= $stId ?>">
                    <?php foreach ($naoAlocado[$stId] ?? [] as $nd): ?>
                    <div class="disc-drag-card"
                         draggable="true"
                         id="dcard-<?= $nd['som_disc_id'] ?>"
                         data-som-disc-id="<?= $nd['som_disc_id'] ?>"
                         data-disc-cod="<?= htmlspecialchars($nd['disciplina_codigo']) ?>"
                         data-disc-nome="<?= htmlspecialchars($nd['disc_nome']) ?>"
                         data-tipo="Normal"
                         data-professores="<?= htmlspecialchars($nd['professores'] ?? '') ?>"
                         data-professor-ids="<?= htmlspecialchars($nd['professor_ids'] ?? '') ?>">
                        <div class="ddc-info">
                            <span class="ddc-code"><?= htmlspecialchars($nd['disciplina_codigo']) ?></span>
                            <span class="ddc-name"><?= htmlspecialchars($nd['disc_nome']) ?></span>
                        </div>
                        <?php if (!empty($nd['professores'])): ?>
                        <div class="ddc-prof">👤 <?= htmlspecialchars($nd['professores']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($naoAlocado[$stId])): ?>
                    <div class="disc-all-done" id="disc-all-done-<?= $stId ?>">
                        ✅ Todas as disciplinas alocadas
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Cards especiais -->
                <div class="gs-section gs-special">
                    <h4 class="gs-special-title">Especiais</h4>
                    <div class="disc-drag-card disc-special-card"
                         draggable="true"
                         data-som-disc-id=""
                         data-disc-cod=""
                         data-disc-nome="Segunda Chamada"
                         data-tipo="Segunda Chamada">
                        <div class="ddc-info">
                            <span class="ddc-special-icon">📋</span>
                            <span class="ddc-name">Segunda Chamada</span>
                        </div>
                    </div>
                    <?php if (!empty($somativa['naapi_ambiente_id'])): ?>
                    <div class="naapi-config-info">
                        <span class="gc-naapi-badge" style="font-size:.7rem;">NAAPI</span>
                        <span style="font-size:.75rem;color:var(--text-secondary);">
                            <?= htmlspecialchars($somativa['naapi_ambiente_desc'] ?? '') ?>
                            &middot; +<?= (int)($somativa['naapi_tempo_extra_min'] ?? 60) ?> min
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Sugestões compactas -->
            <details class="gs-suggestions" id="gs-suggestions-panel" hidden>
                <summary class="gs-suggestions-title">💡 Sugestões (<span id="sug-count-sidebar">0</span>)</summary>
                <div class="gs-suggestions-container" id="gs-suggestions-container"></div>
            </details>

        </aside><!-- /grade-sidebar -->
    </div><!-- /grade-sidebar-wrapper -->

</div><!-- /grade-body -->

<!-- ════════════════════════════════════════════════════
     CONSOLE DE LOGS E DIAGNÓSTICOS (ESTILO VS CODE)
     ════════════════════════════════════════════════════ -->
<div class="grade-console collapsed" id="grade-console">
    <!-- Barra de Cabeçalho do Console -->
    <div class="console-header">
        <div class="console-tabs">
            <button type="button" class="console-tab active" data-tab="console-tab-problems">
                ⚠️ Problemas <span class="console-badge badge-warning" id="console-problems-count">0</span>
            </button>
            <button type="button" class="console-tab" data-tab="console-tab-output">
                🖥️ Console/Saída
            </button>
            <button type="button" class="console-tab" data-tab="console-tab-rules">
                ⚖️ Diretrizes/Regras <span class="console-badge badge-neutral"><?= count($regrasAtivas) ?></span>
            </button>
        </div>
        <div class="console-actions">
            <button type="button" class="console-action-btn" id="btn-console-toggle" title="Minimizar/Maximizar Console">
                <span class="console-toggle-icon">▲</span>
            </button>
        </div>
    </div>

    <!-- Corpo do Console -->
    <div class="console-body">
        <!-- Painel: Problemas -->
        <div class="console-panel active" id="console-tab-problems">
            <div class="problems-list" id="console-problems-list">
                <!-- Preenchido via JS -->
            </div>
        </div>

        <!-- Painel: Console/Saída -->
        <div class="console-panel" id="console-tab-output">
            <pre class="console-output" id="console-output-text">[INFO] Inicializando visualização do console de logs.
[INFO] Grade carregada com sucesso.
[INFO] Aguardando execução do algoritmo de alocação automática ou diagnósticos...</pre>
        </div>

        <!-- Painel: Diretrizes/Regras -->
        <?php
        // Build discipline code to name lookup mapping
        $disciplinaNomes = [];
        $somativaDisciplinaNomes = [];
        if (!empty($turmas)) {
            foreach ($turmas as $t) {
                if (!empty($t['disciplinas'])) {
                    foreach ($t['disciplinas'] as $d) {
                        $disciplinaNomes[$d['disciplina_codigo']] = $d['disc_nome'];
                        $somativaDisciplinaNomes[(int)$d['id']] = $d['disc_nome'];
                    }
                }
            }
        }

        // Helper to format restriction parameters beautifully
        $formatParamVal = function($key, $val) use ($disciplinaNomes, $somativaDisciplinaNomes, $turmas) {
            if (is_array($val)) {
                $items = [];
                foreach ($val as $item) {
                    if (is_array($item)) {
                        if (isset($item['disciplina_codigo'])) {
                            $cod = $item['disciplina_codigo'];
                            $items[] = $disciplinaNomes[$cod] ?? $cod;
                        } elseif (isset($item['somativa_disciplina_id'])) {
                            $sdId = (int)$item['somativa_disciplina_id'];
                            $items[] = $somativaDisciplinaNomes[$sdId] ?? "#{$sdId}";
                        } else {
                            $items[] = json_encode($item, JSON_UNESCAPED_UNICODE);
                        }
                    } else {
                        $items[] = $item;
                    }
                }
                return implode(', ', $items);
            }
            if ($key === 'somativa_turma_id' || $key === 'turma_id') {
                foreach ($turmas as $t) {
                    $matchId = ($key === 'somativa_turma_id') ? ($t['somativa_turma_id'] ?? $t['id'] ?? 0) : ($t['turma_id'] ?? 0);
                    if ((int)$matchId === (int)$val) {
                        return $t['course_name'] . ' — ' . $t['turma_desc'];
                    }
                }
                return 'Turma ID ' . $val;
            }
            if (strpos($key, 'disciplina_codigo') !== false) {
                return $disciplinaNomes[$val] ?? $val;
            }
            if (strpos($key, 'somativa_disciplina_id') !== false) {
                return $somativaDisciplinaNomes[(int)$val] ?? $val;
            }
            return $val;
        };
        ?>
        <div class="console-panel" id="console-tab-rules">
            <div class="rules-list-container">
                <?php if (empty($regrasAtivas)): ?>
                    <p class="rules-empty-state">Nenhuma diretriz ou restrição cadastrada para esta somativa.</p>
                <?php else: ?>
                    <table class="console-rules-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Categoria</th>
                                <th>Descrição / Regra</th>
                                <th>Peso</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($regrasAtivas as $r):
                                $params = json_decode($r['params'], true) ?? [];
                                $badgeClass = $r['tipo'] === 'hard' ? 'badge-danger' : 'badge-warning';
                                $statusClass = $r['is_active'] ? 'rule-active' : 'rule-inactive';
                            ?>
                                <tr class="<?= $statusClass ?>">
                                    <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(strtoupper($r['tipo'])) ?></span></td>
                                    <td><strong><?= htmlspecialchars(strtoupper(str_replace('_', ' ', $r['categoria']))) ?></strong></td>
                                    <td>
                                        <div class="rule-desc-title"><?= htmlspecialchars($r['descricao'] ?: 'Sem descrição') ?></div>
                                        <?php if (!empty($params)): ?>
                                            <div class="rule-desc-params">
                                                <?php foreach ($params as $k => $v): ?>
                                                    <span><strong><?= htmlspecialchars(str_replace('_', ' ', $k)) ?>:</strong> <?= htmlspecialchars($formatParamVal($k, $v)) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $r['peso'] ?></td>
                                    <td>
                                        <span class="rule-status-dot"></span>
                                        <?= $r['is_active'] ? 'Ativa' : 'Inativa' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════════
     MODAL DE CONFIGURAÇÃO DE SLOT
════════════════════════════════════════════════════ -->
<div id="slot-modal-overlay" class="slot-modal-overlay" hidden>
    <div class="slot-modal" role="dialog" aria-modal="true" aria-labelledby="slot-modal-title">
        <div class="slot-modal-header">
            <h2 class="slot-modal-title" id="slot-modal-title">Configurar Prova</h2>
            <button type="button" class="slot-modal-close" onclick="closeSlotModal()">✕</button>
        </div>

        <div class="slot-modal-body">
            <!-- Info do slot -->
            <div class="slot-modal-info" id="slot-modal-info"></div>

            <form id="slot-modal-form">
                <input type="hidden" name="action"                  value="save_slot">
                <input type="hidden" name="csrf_token"              value="<?= csrf_token() ?>">
                <input type="hidden" name="somativa_id"             value="<?= $id ?>">
                <input type="hidden" name="somativa_turma_id"       id="smi-st-id">
                <input type="hidden" name="somativa_disciplina_id"  id="smi-sd-id">
                <input type="hidden" name="slot_config_id"          id="smi-slot-id">
                <input type="hidden" name="data_prova"              id="smi-data">
                <input type="hidden" name="tipo"                    id="smi-tipo">
                <input type="hidden" name="clear_grade_id"          id="smi-clear-grade-id">

                <div class="slot-modal-grid">
                    <!-- Tipo -->
                    <div class="form-group">
                        <label class="form-label">Tipo de Avaliação</label>
                        <select name="tipo" class="form-control" id="smi-tipo-select">
                            <option value="Normal">Normal</option>
                            <option value="Segunda Chamada">Segunda Chamada</option>
                        </select>
                    </div>

                    <!-- Aplicador -->
                    <div class="form-group">
                        <label class="form-label">Aplicador <span class="label-hint">(quem aplica a prova)</span></label>
                        <div class="smart-search-modal" id="search-aplicador" data-type="professor" data-field="aplicador_id">
                            <input type="text" class="form-control smart-search-input"
                                   autocomplete="off" placeholder="Buscar professor...">
                            <div class="smart-search-dropdown" hidden></div>
                        </div>
                        <input type="hidden" name="aplicador_id" id="field-aplicador-id">
                        <div class="selected-person" id="selected-aplicador" hidden></div>
                    </div>

                    <!-- Volante -->
                    <div class="form-group">
                        <label class="form-label">Volante <span class="label-hint">(fiscal / apoio)</span></label>
                        <div class="smart-search-modal" id="search-volante" data-type="professor" data-field="volante_id">
                            <input type="text" class="form-control smart-search-input"
                                   autocomplete="off" placeholder="Buscar professor...">
                            <div class="smart-search-dropdown" hidden></div>
                        </div>
                        <input type="hidden" name="volante_id" id="field-volante-id">
                        <div class="selected-person" id="selected-volante" hidden></div>
                    </div>

                    <!-- Sala / Ambiente -->
                    <div class="form-group">
                        <label class="form-label">Sala / Ambiente</label>
                        <div class="smart-search-modal" id="search-ambiente" data-type="ambiente" data-field="ambiente_id">
                            <input type="text" class="form-control smart-search-input"
                                   autocomplete="off" placeholder="Buscar sala...">
                            <div class="smart-search-dropdown" hidden></div>
                        </div>
                        <input type="hidden" name="ambiente_id" id="field-ambiente-id">
                        <div class="selected-person" id="selected-ambiente" hidden></div>
                    </div>

                    <!-- Seção NAAPI (visível apenas quando NAAPI configurado na somativa) -->
                    <?php if (!empty($somativa['naapi_ambiente_id'])): ?>
                    <div class="form-group naapi-modal-section" style="grid-column:1/-1;
                         border:1px solid rgba(79,70,229,.2);border-radius:var(--radius-md);
                         padding:.875rem 1rem;background:rgba(79,70,229,.04);">
                        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.75rem;">
                            <span class="gc-naapi-badge">NAAPI</span>
                            <span style="font-size:.8125rem;color:var(--text-secondary);">
                                Sala: <strong><?= htmlspecialchars($somativa['naapi_ambiente_desc'] ?? '') ?></strong>
                                &middot; +<?= (int)($somativa['naapi_tempo_extra_min'] ?? 60) ?> min por prova
                            </span>
                        </div>
                        <label class="form-label" style="font-size:.8125rem;">Aplicador NAAPI <span class="label-hint">(professor responsável pela sala NAAPI)</span></label>
                        <div class="smart-search-modal" id="search-naapi-aplicador" data-type="professor" data-field="naapi_aplicador_id">
                            <input type="text" class="form-control smart-search-input"
                                   autocomplete="off" placeholder="Buscar professor..."
                                   style="font-size:.8125rem;">
                            <div class="smart-search-dropdown" hidden></div>
                        </div>
                        <input type="hidden" name="naapi_aplicador_id" id="field-naapi-aplicador-id">
                        <div class="selected-person" id="selected-naapi-aplicador" hidden></div>
                    </div>
                    <?php endif; ?>

                    <!-- Observações -->
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" style="resize:vertical" rows="2"
                                  id="smi-obs" placeholder="Observações opcionais sobre esta prova..."></textarea>
                    </div>
                </div>

                <div class="slot-modal-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeSlotModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-slot-save">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════
     MODAL DE PRÉ-VISUALIZAÇÃO DA ALOCAÇÃO AUTOMÁTICA
════════════════════════════════════════════════════ -->
<div id="autoalocar-overlay" class="slot-modal-overlay" hidden>
    <div class="slot-modal autoalocar-modal" role="dialog" aria-modal="true"
         aria-labelledby="autoalocar-title">

        <!-- Cabeçalho -->
        <div class="slot-modal-header">
            <h2 class="slot-modal-title" id="autoalocar-title">⚡ Pré-visualização da Alocação Automática</h2>
            <button type="button" class="slot-modal-close" id="autoalocar-close">✕</button>
        </div>

        <div class="slot-modal-body" style="padding:0">

            <!-- Estado: carregando -->
            <div id="autoalocar-loading" class="autoalocar-state" style="padding:3rem;text-align:center;">
                <div class="spinner" style="margin:0 auto 1rem;"></div>
                <p style="margin:0;color:var(--text-secondary)">Calculando alocação ideal...</p>
                <p id="autoalocar-loading-sub" style="margin:.5rem 0 0;font-size:.8125rem;color:var(--text-muted)"></p>
            </div>

            <!-- Estado: resultado -->
            <div id="autoalocar-result" class="autoalocar-state" hidden>

                <!-- Alerta de Sucesso -->
                <div class="alert alert-success autoalocar-alert" id="autoalocar-success-alert">
                    <span class="alert-icon">✨</span>
                    <div>
                        <strong>Alocação inteligente concluída!</strong>
                        <div class="alert-sub">Confira a pré-visualização das alocações na tabela abaixo antes de confirmar.</div>
                    </div>
                </div>

                <!-- Resumo (badges) -->
                <div class="autoalocar-summary" id="autoalocar-summary"></div>

                <!-- Avisos e conflitos -->
                <div id="autoalocar-warnings" hidden></div>
                <div id="autoalocar-conflicts" hidden></div>

                <!-- Tabela de alocações propostas -->
                <div class="autoalocar-table-wrap">
                    <table class="autoalocar-table" id="autoalocar-table">
                        <thead>
                            <tr>
                                <th>Turma</th>
                                <th>Disciplina</th>
                                <th>Data</th>
                                <th>Slot</th>
                                <th>Sala</th>
                                <th style="width:28px"></th>
                            </tr>
                        </thead>
                        <tbody id="autoalocar-tbody"></tbody>
                    </table>
                </div>

                <!-- Rodapé de ações -->
                <div class="slot-modal-actions" style="border-top:1px solid var(--border-color);padding:1rem 1.5rem;">
                    <span id="autoalocar-provider" style="font-size:.75rem;color:var(--text-muted);flex:1"></span>
                    <button type="button" class="btn btn-ghost" id="autoalocar-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="autoalocar-confirm">
                        ✅ Confirmar e Salvar
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════
     MODAL DE ANÁLISE DE PROFESSORES
════════════════════════════════════════════════════ -->
<div id="analysis-modal-overlay" class="slot-modal-overlay" hidden>
    <div class="slot-modal analysis-modal-lg" role="dialog" aria-modal="true"
         aria-labelledby="analysis-modal-title">

        <!-- Cabeçalho -->
        <div class="slot-modal-header flex-shrink-0">
            <h2 class="slot-modal-title" id="analysis-modal-title">📊 Análise de Alocação por Professor</h2>
            <button type="button" class="slot-modal-close" onclick="closeAnalysisModal()">✕</button>
        </div>

        <!-- Barra de abas -->
        <div class="analysis-tabs-bar-flex flex-shrink-0">
            <button class="analysis-tab active" data-tab="tab-desacordos">⚠️ Horários em Desacordo</button>
            <button class="analysis-tab" data-tab="tab-agenda">📅 Agenda do Professor</button>
            <button class="analysis-tab" data-tab="tab-carga">⚡ Carga de Ocupação</button>
            <button class="analysis-tab" data-tab="tab-equalizacao">💡 Sugestão de Equalização</button>
            <button class="analysis-tab" data-tab="tab-inteligencia">✨ Inteligência</button>
        </div>

        <!-- Corpo -->
        <div class="analysis-modal-body-pad flex-1 overflow-y-auto">

            <!-- ── Aba 1: Horários Fora do Convencional ─── -->
            <div id="tab-desacordos" class="analysis-tab-panel">
                <p class="tab-desc-muted">Professores alocados em horários fora do seu horário convencional de aulas.</p>
                <div class="analysis-table-wrap">
                    <table class="analysis-table">
                        <thead>
                            <tr>
                                <th>Professor</th>
                                <th>Data / Dia</th>
                                <th>Horário</th>
                                <th>Papel</th>
                                <th>Turmas / Disciplinas</th>
                            </tr>
                        </thead>
                        <tbody id="desacordos-tbody">
                            <tr><td colspan="5" class="loading-td text-center">
                                <div class="spinner spinner-margin"></div>Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Aba 2: Agenda ─────────────────────────── -->
            <div id="tab-agenda" class="analysis-tab-panel" hidden>
                <div class="flex-between-center">
                    <p class="tab-desc-muted-no-margin">Agenda individual de cada professor durante o período somativo.</p>
                    <div class="flex-align-center">
                        <label class="label-agenda-select" for="agenda-prof-select">Filtrar:</label>
                        <select id="agenda-prof-select" class="form-control select-agenda">
                            <option value="">-- Todos --</option>
                        </select>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="printAgendas()">🖨 Imprimir</button>
                    </div>
                </div>
                <div id="agendas-container" class="loading-div">
                    <div class="spinner spinner-margin"></div>Carregando...
                </div>
            </div>

            <!-- ── Aba 3: Carga de Trabalho ──────────────── -->
            <div id="tab-carga" class="analysis-tab-panel" hidden>
                <p class="tab-desc-muted-lg">Distribuição de alocações entre os professores (Aplicador, Volante, NAAPI).</p>
                <div class="grid-carga" id="carga-grid">
                    <div class="loading-div-grid"><div class="spinner spinner-margin"></div>Carregando...</div>
                </div>
            </div>

            <!-- ── Aba 4: Equalização de Carga ──────────── -->
            <div id="tab-equalizacao" class="analysis-tab-panel" hidden>
                <div class="flex-between-start">
                    <p class="tab-desc-muted-no-margin">Sugestões automáticas de trocas para equilibrar a carga de trabalho entre os professores.</p>
                    <?php if (hasDbPermission('somativas.update', false)): ?>
                    <form id="equalizacao-form">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-aplicar-equalizacao" disabled>
                            Aplicar Selecionadas
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="analysis-table-wrap">
                    <table class="analysis-table">
                        <thead>
                            <tr>
                                <th class="th-width-checkbox">
                                    <input type="checkbox" id="chk-select-all-swaps" class="cursor-pointer"
                                           title="Selecionar todas">
                                </th>
                                <th>Turma / Disciplina</th>
                                <th>Data / Horário</th>
                                <th>Papel</th>
                                <th>Professor Atual</th>
                                <th>Sugerido</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody id="equalizacao-tbody">
                            <tr><td colspan="7" class="loading-td text-center">
                                <div class="spinner spinner-margin"></div>Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Aba 5: Inteligência ───────────────────── -->
            <div id="tab-inteligencia" class="analysis-tab-panel" hidden>

                <!-- Estado inicial: explicação + botão -->
                <div id="intel-initial">
                    <div class="intel-info-box">
                        <div class="intel-info-header">
                            <span class="intel-info-icon">✨</span>
                            <h3 class="intel-info-title">Alocação Inteligente de Aplicadores</h3>
                        </div>
                        <p class="intel-info-desc">
                            O sistema analisa todas as provas normais alocadas na grade e distribui automaticamente
                            os aplicadores e aplicadores NAAPI, seguindo os critérios abaixo:
                        </p>
                        <ul class="intel-rules-list">
                            <li class="intel-rule-item">
                                <span class="intel-rule-icon">🔒</span>
                                <div>
                                    <strong>Volante = sempre o professor da disciplina</strong>
                                    <span>O professor que leciona a disciplina sendo avaliada é designado como volante da prova, independentemente de qualquer outro critério.</span>
                                </div>
                            </li>
                            <li class="intel-rule-item">
                                <span class="intel-rule-icon">📅</span>
                                <div>
                                    <strong>Aplicador com aulas no horário</strong>
                                    <span>O sistema prioriza aplicadores que possuem aulas em andamento naquele mesmo horário — garantindo que o professor já esteja presente na instituição.</span>
                                </div>
                            </li>
                            <li class="intel-rule-item">
                                <span class="intel-rule-icon">🏫</span>
                                <div>
                                    <strong>Preferência pelo professor da turma</strong>
                                    <span>Entre os candidatos com aulas no horário, o sistema prioriza quem leciona para a própria turma sendo avaliada.</span>
                                </div>
                            </li>
                            <li class="intel-rule-item">
                                <span class="intel-rule-icon">⚖️</span>
                                <div>
                                    <strong>Distribuição equalitária</strong>
                                    <span>O sistema mantém a distribuição a mais equilibrada possível, sempre escolhendo o candidato qualificado com menor carga acumulada.</span>
                                </div>
                            </li>
                            <?php if (!empty($somativa['naapi_ambiente_id'])): ?>
                            <li class="intel-rule-item">
                                <span class="intel-rule-icon">♿</span>
                                <div>
                                    <strong>Aplicador NAAPI independente</strong>
                                    <span>Um aplicador exclusivo para a sala NAAPI é designado por meio dos mesmos critérios, sempre diferente do aplicador regular e do volante.</span>
                                </div>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <div class="intel-warning-box">
                            <span>⚠️</span>
                            <span>Esta operação irá <strong>substituir</strong> os aplicadores e volantes de todas as provas normais da grade. Você poderá revisar a proposta antes de confirmar.</span>
                        </div>
                    </div>
                    <div class="intel-action-row">
                        <button type="button" class="btn btn-primary" id="btn-processar-inteligente">
                            ✨ Gerar Proposta de Alocação
                        </button>
                    </div>
                </div>

                <!-- Estado: carregando -->
                <div id="intel-loading" hidden style="text-align:center;padding:3rem 1rem;">
                    <div class="spinner" style="margin:0 auto 1rem;"></div>
                    <p style="color:var(--text-secondary);margin:0;">Analisando horários e calculando alocações ideais...</p>
                    <p style="color:var(--text-muted);font-size:.8125rem;margin:.5rem 0 0;">Isso pode levar alguns segundos</p>
                </div>

                <!-- Estado: resultado -->
                <div id="intel-result" hidden>
                    <div id="intel-summary" class="autoalocar-summary" style="margin-bottom:1rem;"></div>

                    <div class="analysis-table-wrap">
                        <table class="analysis-table intel-preview-table">
                            <thead>
                                <tr>
                                    <th>Data / Slot</th>
                                    <th>Turma / Disciplina</th>
                                    <th>Volante</th>
                                    <th>Aplicador</th>
                                    <?php if (!empty($somativa['naapi_ambiente_id'])): ?>
                                    <th>Aplic. NAAPI</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="intel-tbody"></tbody>
                        </table>
                    </div>

                    <div class="intel-result-actions">
                        <button type="button" class="btn btn-ghost" id="btn-intel-reset">← Refazer</button>
                        <button type="button" class="btn btn-primary" id="btn-intel-confirm">
                            ✅ Confirmar e Aplicar
                        </button>
                    </div>
                </div>

            </div>

        </div><!-- /analysis-modal-body-pad -->
    </div>
</div>

<!-- Dados PHP → JS -->
<script>
window.GradeData = {
    somativaId:        <?= $id ?>,
    maxProvas:         <?= (int)$somativa['max_provas_por_dia'] ?>,
    turmas:            <?= json_encode(array_map(function($t) {
                           return [
                               'somativa_turma_id' => (int)$t['somativa_turma_id'],
                               'turma_id'          => (int)$t['turma_id'],
                               'course_name'       => $t['course_name'],
                               'turma_desc'        => $t['turma_desc'],
                           ];
                       }, $turmas)) ?>,
    currentStId:       <?= (int)($turmas[0]['somativa_turma_id'] ?? 0) ?>,
    naapiAtivo:        <?= !empty($somativa['naapi_ambiente_id']) ? 'true' : 'false' ?>,
    naapiAmbienteDesc: <?= json_encode($somativa['naapi_ambiente_desc'] ?? '') ?>,
    naapiTempoExtra:   <?= (int)($somativa['naapi_tempo_extra_min'] ?? 60) ?>,
};
window.GradeSuggestions = <?= json_encode($suggestions) ?>;
window.GradeViolations  = <?= json_encode($violations) ?>;
</script>
<script src="/assets/js/somativas_grade.js?v=<?= time() ?>"></script>

<!-- ════════════════════════════════════════════════════
     MODAL DE IMPRESSÃO
════════════════════════════════════════════════════ -->
<div id="print-modal-overlay" class="slot-modal-overlay" hidden>
    <div class="slot-modal print-modal-lg" role="dialog" aria-modal="true" aria-labelledby="print-modal-title">
        <div class="slot-modal-header flex-shrink-0">
            <h2 class="slot-modal-title" id="print-modal-title">Impressão da Grade</h2>
            <button type="button" class="slot-modal-close" onclick="closePrintModal()">✕</button>
        </div>
        <div class="iframe-container">
            <iframe id="print-modal-iframe" src="" class="iframe-print"></iframe>
        </div>
    </div>
</div>

</body>
</html>
