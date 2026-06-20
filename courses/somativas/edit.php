<?php
/**
 * Vértice Acadêmico — Criar / Editar Avaliação Somativa
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

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/courses/somativas/edit.php'));
    exit;
}

$service  = new \App\Services\SomativaService();
$id       = (int)($_GET['id'] ?? 0);
$isEdit   = $id > 0;
$somativa = null;

if ($isEdit) {
    hasDbPermission('somativas.update');
    $somativa = $service->findById($id, $instId);
    if (!$somativa) { header('Location: /courses/somativas/index.php'); exit; }
} else {
    hasDbPermission('somativas.create');
}

$statusOptions = ['Rascunho', 'Configurando', 'Publicado', 'Encerrado'];

// Helpers para exibir restrições na listagem
function labelCategoria(string $cat): string {
    return match($cat) {
        'bloquear_data'               => 'Bloquear Data',
        'evitar_mesmo_dia'            => 'Evitar no Mesmo Dia',
        'mesmo_dia_turmas'            => 'Mesmo Dia em Todas as Turmas',
        'mesmo_horario_turmas'        => 'Mesmo Horário em Turmas Diferentes',
        'mesmo_dia_horario_diferente' => 'Relação de Dia/Horário entre Disciplinas',
        'professor_indisponivel'      => 'Professor Indisponível',
        'professor_mesmo_dia_horario' => 'Mesmo Dia e Horário (por Professor)',
        'preferir_primeiros_horarios' => 'Concentrar nos Primeiros Horários',
        'min_provas_por_dia'          => 'Mínimo de Provas por Dia',
        'mesmo_dia_horario_grupo'     => 'Mesmo Dia e Horário (Grupo de Disciplinas)',
        default                       => $cat,
    };
}

function getTurmaLabelById(int $turmaId, array $turmas): string {
    foreach ($turmas as $t) {
        if ((int)$t['turma_id'] === $turmaId) {
            return $t['course_name'] . ' — ' . $t['turma_desc'];
        }
    }
    return 'Turma ID ' . $turmaId;
}

function getDisciplinaLabel(string $code, array $discs): string {
    if (isset($discs[$code]) && $discs[$code] !== $code) {
        return $code . ' — ' . $discs[$code];
    }
    return $code;
}

function resumoParams(string $cat, array $params, array $turmas = [], array $discs = []): string {
    return match($cat) {
        'bloquear_data'          => ($params['data'] ?? '') . ($params['motivo'] ? ' — ' . $params['motivo'] : ''),
        'evitar_mesmo_dia'       => implode(', ', array_map(fn($c) => getDisciplinaLabel($c, $discs), $params['disciplinas'] ?? [])),
        'mesmo_dia_turmas'       => getDisciplinaLabel($params['disciplina_codigo'] ?? '', $discs),
        'mesmo_horario_turmas'   => ($params['scope'] ?? 'disciplina') === 'turma' 
            ? 'Toda a turma: ' . getTurmaLabelById((int)($params['turma_id'] ?? 0), $turmas)
            : getDisciplinaLabel($params['disciplina_codigo'] ?? '', $discs),
        'mesmo_dia_horario_diferente' => sprintf(
            'Relação entre %s e %s (%s | %s)',
            getDisciplinaLabel($params['disciplina_codigo_a'] ?? '', $discs),
            getDisciplinaLabel($params['disciplina_codigo_b'] ?? '', $discs),
            ($params['scope'] ?? '') === 'mesma_turma' ? 'Na mesma turma' : 'Em turmas diferentes',
            ($params['regra'] ?? '') === 'deve' ? 'Deve acontecer' : 'Não deve acontecer'
        ),
        'professor_indisponivel'      => 'Prof. ID ' . ($params['professor_id'] ?? '') . ' em ' . implode(', ', $params['datas'] ?? []),
        'professor_mesmo_dia_horario' => (!empty($params['todos']))
            ? 'Todos os professores — disciplinas em turmas diferentes no mesmo slot'
            : 'Prof. ID ' . ($params['professor_id'] ?? '') . ' — disciplinas em turmas diferentes no mesmo slot',
        'mesmo_dia_horario_grupo' => (function() use ($params, $turmas): string {
            $sdIds = array_column($params['pares'] ?? [], 'somativa_disciplina_id');
            if (empty($sdIds)) return 'Grupo vazio';
            $labels = [];
            foreach ($turmas as $t) {
                foreach ($t['disciplinas'] ?? [] as $d) {
                    if (in_array((int)$d['id'], $sdIds)) {
                        $labels[] = $d['disciplina_codigo'] . ' — ' . ($d['disc_nome'] ?? $d['disciplina_codigo']);
                    }
                }
            }
            return $labels ? implode(', ', $labels) : count($sdIds) . ' disciplinas';
        })(),
        'preferir_primeiros_horarios' => (function() use ($params, $turmas): string {
            if (($params['scope'] ?? 'todas') !== 'turma') return 'Todas as turmas';
            $stId = (int)($params['somativa_turma_id'] ?? 0);
            foreach ($turmas as $t) {
                if ((int)$t['somativa_turma_id'] === $stId) {
                    return 'Turma: ' . $t['course_name'] . ' — ' . $t['turma_desc'];
                }
            }
            return 'Turma ID ' . $stId;
        })(),
        'min_provas_por_dia' => (function() use ($params, $turmas): string {
            $min   = (int)($params['min'] ?? 2);
            $scope = $params['scope'] ?? 'todas';
            if ($scope !== 'turma') return "Mín. {$min}/dia — todas as turmas";
            $stId = (int)($params['somativa_turma_id'] ?? 0);
            foreach ($turmas as $t) {
                if ((int)$t['somativa_turma_id'] === $stId) {
                    return "Mín. {$min}/dia — Turma: " . $t['course_name'] . ' — ' . $t['turma_desc'];
                }
            }
            return "Mín. {$min}/dia — Turma ID {$stId}";
        })(),
        default                       => json_encode($params),
    };
}
$pageTitle     = $isEdit ? 'Editar Somativa' : 'Nova Somativa';
$extraCSS      = ['/assets/css/somativas.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ── Tabs ─────────────────────────────────────────────────── */
.som-tabs { display:flex; gap:.25rem; border-bottom:2px solid var(--border-color); margin-bottom:1.5rem; }
.som-tab  {
    display:flex; align-items:center; gap:.5rem;
    padding:.75rem 1.25rem; background:transparent; border:none;
    border-bottom:3px solid transparent; margin-bottom:-2px;
    font-size:.875rem; font-weight:500; color:var(--text-secondary);
    cursor:pointer; transition:color var(--transition-fast);
}
.som-tab:hover:not(:disabled) { color:var(--color-primary); }
.som-tab.active { color:var(--color-primary); border-bottom-color:var(--color-primary); font-weight:600; }
.som-tab:disabled { opacity:.4; cursor:not-allowed; }
.tab-num {
    width:22px; height:22px; border-radius:50%;
    background:var(--bg-surface-2nd); font-size:.75rem; font-weight:700;
    display:flex; align-items:center; justify-content:center;
}
.som-tab.active .tab-num { background:var(--color-primary); color:#fff; }
.tab-panel { display:none; }
.tab-panel.active { display:block; }

/* ── Slots ────────────────────────────────────────────────── */
.slots-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap; }
.slot-empty-hint {
    text-align:center; color:var(--text-muted); font-size:.875rem;
    padding:2rem 1rem; background:var(--bg-surface-2nd);
    border:2px dashed var(--border-color); border-radius:var(--radius-lg);
}
.slot-row {
    display:flex; align-items:center; gap:.75rem;
    background:var(--bg-surface-2nd); border:1px solid var(--border-color);
    border-radius:var(--radius-md); padding:.75rem 1rem; margin-bottom:.5rem;
}
.slot-num {
    min-width:28px; height:28px; background:var(--color-primary); color:#fff;
    border-radius:50%; font-size:.8rem; font-weight:700;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.slot-fields { display:flex; align-items:center; gap:.625rem; flex:1; flex-wrap:wrap; }
.slot-sep { font-size:.8125rem; color:var(--text-muted); white-space:nowrap; }
.slot-time { width:140px; }
.slot-label-input { flex:1; min-width:160px; }
.slot-remove {
    width:32px; height:32px; border-radius:var(--radius-md);
    border:1px solid var(--border-color); background:transparent;
    color:var(--text-muted); cursor:pointer; font-size:.875rem;
    display:flex; align-items:center; justify-content:center;
    transition:all var(--transition-fast); flex-shrink:0;
}
.slot-remove:hover { background:#fef2f2; color:var(--color-danger); border-color:var(--color-danger); }

/* ── Turmas ───────────────────────────────────────────────── */
.turmas-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1.5rem; margin-bottom:1.25rem; flex-wrap:wrap; }
.turma-item {
    background:var(--bg-surface-2nd); border:1px solid var(--border-color);
    border-radius:var(--radius-lg); margin-bottom:.75rem;
    overflow:visible;
}
.turma-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:.875rem 1.25rem; gap:1rem; flex-wrap:wrap;
}
.turma-nome { font-weight:700; font-size:.9375rem; }
.turma-sub  { font-size:.875rem; color:var(--text-secondary); margin-left:.3rem; }
.turma-actions { display:flex; gap:.5rem; }
.disc-panel {
    border-top:1px solid var(--border-color); padding:1rem 1.25rem; background:var(--bg-surface);
    border-bottom-left-radius: calc(var(--radius-lg) - 1px);
    border-bottom-right-radius: calc(var(--radius-lg) - 1px);
}
.disc-panel-top { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:.875rem; flex-wrap:wrap; }
.disc-panel-title { font-size:.8125rem; font-weight:600; color:var(--text-secondary); }
.disc-tags { display:flex; flex-wrap:wrap; gap:.5rem; }
.disc-tag {
    display:inline-flex; align-items:center; gap:.35rem;
    background:rgba(79,70,229,.1); color:var(--color-primary);
    border-radius:var(--radius-sm); padding:.25rem .625rem; font-size:.8125rem; font-weight:500;
    border: 1px solid transparent;
    transition: all var(--transition-fast);
}
.disc-tag.prof-ap-active {
    background: rgba(16,185,129,.1);
    color: #059669;
    border-color: #059669;
}
.disc-tag-code { font-weight:700; }
.disc-tag-toggle-ap { background:none; border:none; color:inherit; opacity:.6; cursor:pointer; font-size:.85rem; padding:0 2px; line-height:1; }
.disc-tag-toggle-ap:hover { opacity:1; }
.disc-tag-remove { background:none; border:none; color:inherit; opacity:.6; cursor:pointer; font-size:1rem; padding:0; line-height:1; }
.disc-tag-remove:hover { opacity:1; }
.disc-empty, .turma-empty { font-size:.875rem; color:var(--text-muted); font-style:italic; padding:.375rem 0; }

/* ── Smart Search ─────────────────────────────────────────── */
.smart-wrap { position:relative; }
.smart-drop {
    position:absolute; top:calc(100% + 4px); left:0; right:0;
    background:var(--bg-surface); border:1px solid var(--border-color);
    border-radius:var(--radius-md); box-shadow:0 8px 24px rgba(0,0,0,.12);
    z-index:500; max-height:240px; overflow-y:auto;
}
.sr { padding:.625rem .875rem; cursor:pointer; display:flex; flex-direction:column; gap:.1rem; transition:background var(--transition-fast); }
.sr:hover { background:var(--bg-hover); }
.sr-label { font-size:.875rem; font-weight:500; }
.sr-sub   { font-size:.75rem; color:var(--text-muted); }

/* ── Form ─────────────────────────────────────────────────── */
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; }
@media(max-width:640px){ .form-grid-2{ grid-template-columns:1fr; } }
.form-hint { font-size:.75rem; color:var(--text-muted); margin-top:.25rem; }
.label-req { color:var(--color-danger); margin-left:2px; }
</style>

<!-- Cabeçalho -->
<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <p style="font-size:.8125rem;color:var(--text-muted);margin:0 0 .25rem;">
            <a href="index.php" style="color:var(--color-primary);text-decoration:none;">Avaliações Somativas</a>
            &rsaquo;
            <?= $isEdit ? htmlspecialchars($somativa['nome']) : 'Nova Somativa' ?>
        </p>
        <h1 class="page-title"><?= $isEdit ? '✏️ Editar Somativa' : '📋 Nova Somativa' ?></h1>
    </div>
</div>

<!-- Tabs -->
<div class="som-tabs fade-in" id="som-tabs">
    <button type="button" class="som-tab active" data-tab="dados">
        <span class="tab-num">1</span> Dados Básicos
    </button>
    <button type="button" class="som-tab" data-tab="slots">
        <span class="tab-num">2</span> Horários das Provas
    </button>
    <button type="button" class="som-tab" data-tab="turmas"
            <?= !$isEdit ? 'disabled title="Salve os dados básicos primeiro"' : '' ?>>
        <span class="tab-num">3</span> Turmas &amp; Disciplinas
    </button>
    <button type="button" class="som-tab" data-tab="restricoes"
            <?= !$isEdit ? 'disabled title="Salve os dados básicos primeiro"' : '' ?>>
        <span class="tab-num">4</span> Restrições
    </button>
</div>

<!-- ══════════════════════════════════════════════════════════
     TAB 1 — Dados Básicos
══════════════════════════════════════════════════════════ -->
<div class="tab-panel active fade-in" id="tab-dados">
    <div class="card">
        <div class="card-body">
            <form id="form-dados">
                <input type="hidden" name="id"         value="<?= $isEdit ? $somativa['id'] : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="form-grid-2">
                    <!-- Nome -->
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Nome da Somativa<span class="label-req">*</span></label>
                        <input type="text" name="nome" class="form-control"
                               placeholder="Ex.: Somativa 1 — 2026"
                               value="<?= htmlspecialchars($somativa['nome'] ?? '') ?>"
                               required maxlength="255">
                    </div>

                    <!-- Descrição -->
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Descrição / Observações</label>
                        <textarea name="descricao" class="form-control" rows="3"
                                  style="resize:vertical"
                                  placeholder="Observações gerais sobre esta avaliação somativa..."><?= htmlspecialchars($somativa['descricao'] ?? '') ?></textarea>
                    </div>

                    <!-- Data início -->
                    <div class="form-group">
                        <label class="form-label">Data de Início<span class="label-req">*</span></label>
                        <input type="date" name="data_inicio" class="form-control"
                               value="<?= $somativa['data_inicio'] ?? '' ?>" required>
                    </div>

                    <!-- Data fim -->
                    <div class="form-group">
                        <label class="form-label">Data de Fim<span class="label-req">*</span></label>
                        <input type="date" name="data_fim" class="form-control"
                               value="<?= $somativa['data_fim'] ?? '' ?>" required>
                    </div>

                    <!-- Máx provas -->
                    <div class="form-group">
                        <label class="form-label">Máx. Provas por Dia (por turma)<span class="label-req">*</span></label>
                        <input type="number" name="max_provas_por_dia" class="form-control"
                               min="1" max="10" value="<?= $somativa['max_provas_por_dia'] ?? 2 ?>" required>
                        <span class="form-hint">Quantas provas podem ser aplicadas num mesmo dia para a mesma turma</span>
                    </div>

                    <!-- Data 2ª Chamada -->
                    <div class="form-group">
                        <label class="form-label">Data para 2ª Chamada</label>
                        <input type="date" name="segunda_chamada_data" class="form-control"
                               value="<?= $somativa['segunda_chamada_data'] ?? '' ?>">
                        <span class="form-hint">Se não definida, o sistema considera que não há dia específico para segunda chamada.</span>
                    </div>

                    <!-- Evitar Conflito de Aplicador (Professor) -->
                    <div class="form-group" style="grid-column: 1 / -1; margin-top: 0.5rem; margin-bottom: 0.5rem;">
                        <label class="form-label" style="display:flex; align-items:center; gap:0.625rem; cursor:pointer; font-weight:600;">
                            <input type="checkbox" name="evitar_conflito_professor" value="1" <?= !empty($somativa['evitar_conflito_professor']) ? 'checked' : '' ?> style="width:18px; height:18px; cursor:pointer;">
                            <span>Evitar conflito de aplicação simultânea para o mesmo professor</span>
                        </label>
                        <span class="form-hint" style="margin-left: 28px; display:block;">Se marcado, impede que um mesmo professor seja escalado como aplicador principal em provas de turmas diferentes no mesmo dia e horário (bloqueio absoluto). Deixe desmarcado se a instituição permitir que o professor supervisione provas simultâneas (o que facilita o agendamento).</span>
                    </div>

                    <?php if ($isEdit): ?>
                    <!-- Status -->
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control" style="cursor:pointer">
                            <?php foreach ($statusOptions as $opt): ?>
                            <option value="<?= $opt ?>" <?= ($somativa['status'] ?? '') === $opt ? 'selected' : '' ?>>
                                <?= $opt ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- ── Seção NAAPI ──────────────────────────────── -->
                    <div class="form-group" style="grid-column:1/-1">
                        <div style="border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:1.25rem;background:var(--bg-surface-2nd);">
                            <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:1rem;">
                                <span style="font-size:.8125rem;font-weight:700;color:var(--color-primary);background:rgba(79,70,229,.1);padding:.2rem .625rem;border-radius:var(--radius-sm);">NAAPI</span>
                                <span style="font-size:.875rem;font-weight:600;">Configuração de Atendimento NAAPI</span>
                            </div>
                            <p style="font-size:.8125rem;color:var(--text-muted);margin:0 0 1rem;">
                                Alunos NAAPI realizam as mesmas provas no mesmo horário, mas em sala própria e com professor aplicador dedicado.
                                Deixe a sala em branco se não houver atendimento NAAPI nesta somativa.
                            </p>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                                <div class="form-group" style="margin:0">
                                    <label class="form-label">Sala de Aplicação NAAPI</label>
                                    <div class="smart-wrap" id="naapi-ambiente-search" data-type="ambiente">
                                        <input type="text" class="form-control smart-input"
                                               autocomplete="off" placeholder="Buscar sala...">
                                        <div class="smart-drop" hidden></div>
                                    </div>
                                    <input type="hidden" name="naapi_ambiente_id" id="field-naapi-ambiente-id"
                                           value="<?= (int)($somativa['naapi_ambiente_id'] ?? 0) ?: '' ?>">
                                    <div id="selected-naapi-ambiente" style="margin-top:.375rem;font-size:.8125rem;color:var(--color-primary)"
                                         <?= empty($somativa['naapi_ambiente_id']) ? 'hidden' : '' ?>>
                                        <?php if (!empty($somativa['naapi_ambiente_id'])): ?>
                                        <span>✓ <?= htmlspecialchars($somativa['naapi_ambiente_desc'] ?? 'Sala #' . $somativa['naapi_ambiente_id']) ?></span>
                                        <button type="button" onclick="clearNaapiAmbiente()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;margin-left:.25rem;">✕</button>
                                        <?php endif; ?>
                                    </div>
                                    <span class="form-hint">Se vazia, o NAAPI não será configurado para esta somativa.</span>
                                </div>
                                <div class="form-group" style="margin:0">
                                    <label class="form-label">Tempo Extra por Prova (minutos)</label>
                                    <input type="number" name="naapi_tempo_extra_min" class="form-control"
                                           min="0" max="180" value="<?= (int)($somativa['naapi_tempo_extra_min'] ?? 60) ?>">
                                    <span class="form-hint">Informativo — o professor aplicador saberá quanto tempo a mais conceder.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--border-color);">
                    <a href="index.php" class="btn btn-ghost">Cancelar</a>
                    <button type="submit" class="btn btn-primary" id="btn-save-dados">
                        <?= $isEdit ? 'Salvar Alterações' : 'Criar Somativa' ?>
                    </button>
                    <?php if (!$isEdit): ?>
                    <span class="form-hint" style="align-self:center">Após salvar, configure os horários e turmas</span>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     TAB 2 — Slots de Horário
══════════════════════════════════════════════════════════ -->
<div class="tab-panel" id="tab-slots">
    <div class="card">
        <div class="card-header">
            <span class="card-title">⏰ Horários das Provas</span>
            <button type="button" class="btn btn-secondary btn-sm" id="btn-add-slot">+ Adicionar Horário</button>
        </div>
        <div class="card-body">
            <p style="font-size:.875rem;color:var(--text-muted);margin:0 0 1rem;">
                Defina os intervalos de horário para cada prova. Ex: Prova 1 das 07:30 às 08:30.
            </p>

            <div id="slots-list">
                <?php if (empty($somativa['slots'])): ?>
                <div class="slot-empty-hint" id="slot-empty-hint">
                    Nenhum horário configurado. Clique em "+ Adicionar Horário" para começar.
                </div>
                <?php endif; ?>

                <?php foreach ($somativa['slots'] ?? [] as $i => $slot): ?>
                <div class="slot-row">
                    <span class="slot-num"><?= $i + 1 ?></span>
                    <div class="slot-fields">
                        <input type="time" class="form-control slot-time" value="<?= htmlspecialchars($slot['horario_inicio']) ?>" required>
                        <span class="slot-sep">até</span>
                        <input type="time" class="form-control slot-time" value="<?= htmlspecialchars($slot['horario_fim']) ?>" required>
                        <input type="text"  class="form-control slot-label-input"
                               value="<?= htmlspecialchars($slot['label'] ?? '') ?>"
                               placeholder="Rótulo opcional, ex: Manhã 1" maxlength="50">
                    </div>
                    <button type="button" class="slot-remove" title="Remover">✕</button>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap;">
                <button type="button" class="btn btn-ghost" onclick="switchTab('dados')">← Voltar</button>
                <button type="button" class="btn btn-primary" id="btn-save-slots">Salvar Horários</button>
                <button type="button" class="btn btn-secondary" onclick="switchTab('turmas')"
                        <?= !$isEdit ? 'disabled' : '' ?>>Próximo: Turmas →</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     TAB 3 — Turmas e Disciplinas
══════════════════════════════════════════════════════════ -->
<div class="tab-panel" id="tab-turmas">
    <?php if ($isEdit): ?>
    <div class="card">
        <div class="card-header">
            <span class="card-title">🏫 Turmas Participantes</span>
        </div>
        <div class="card-body">
            <p style="font-size:.875rem;color:var(--text-muted);margin:0 0 1.25rem;">
                Selecione as turmas e, para cada uma, as disciplinas que farão avaliação.
            </p>

            <!-- Busca de turma para adicionar -->
            <div style="max-width:380px;margin-bottom:1.5rem;">
                <label class="form-label">Adicionar Turma</label>
                <div class="smart-wrap" id="turma-search" data-type="turma">
                    <input type="text" class="form-control smart-input"
                           autocomplete="off" placeholder="Digite para buscar curso/turma...">
                    <div class="smart-drop" hidden></div>
                </div>
            </div>

            <!-- Lista de turmas adicionadas -->
            <div id="turmas-list">
                <?php if (empty($somativa['turmas'])): ?>
                <p class="turma-empty" id="turma-empty">Nenhuma turma adicionada ainda.</p>
                <?php endif; ?>

                <?php foreach ($somativa['turmas'] ?? [] as $t):
                    $stId = $t['somativa_turma_id'];
                ?>
                <div class="turma-item" id="turma-item-<?= $stId ?>">
                    <div class="turma-header">
                        <div>
                            <span class="turma-nome"><?= htmlspecialchars($t['course_name']) ?></span>
                            <span class="turma-sub">— <?= htmlspecialchars($t['turma_desc']) ?> (<?= $t['ano'] ?>)</span>
                        </div>
                        <div class="turma-actions">
                            <button type="button" class="btn btn-secondary btn-sm"
                                    onclick="toggleDiscs(<?= $stId ?>)">
                                📋 Disciplinas
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm"
                                    style="color:var(--color-danger)"
                                    onclick="removeTurma(<?= $stId ?>, '<?= htmlspecialchars(addslashes($t['course_name'] . ' — ' . $t['turma_desc'])) ?>')">
                                ✕ Remover
                            </button>
                        </div>
                    </div>

                    <div class="disc-panel" id="disc-panel-<?= $stId ?>" hidden>
                        <div class="disc-panel-top">
                            <span class="disc-panel-title">Disciplinas avaliadas nesta turma:</span>
                            <div class="smart-wrap disc-search"
                                 id="disc-search-<?= $stId ?>"
                                 data-type="disciplina_turma"
                                 data-turma-id="<?= $t['turma_id'] ?>"
                                 data-st-id="<?= $stId ?>"
                                 style="min-width:260px">
                                <input type="text" class="form-control smart-input"
                                       style="font-size:.8125rem;padding:.4rem .75rem"
                                       autocomplete="off" placeholder="Buscar e adicionar disciplina...">
                                <div class="smart-drop" hidden></div>
                            </div>
                        </div>
                        <div class="disc-tags" id="disc-tags-<?= $stId ?>">
                            <?php if (empty($t['disciplinas'])): ?>
                            <span class="disc-empty" id="disc-empty-<?= $stId ?>">Nenhuma disciplina adicionada</span>
                            <?php endif; ?>
                            <?php foreach ($t['disciplinas'] as $d): ?>
                            <span class="disc-tag <?= !empty($d['professor_aplicador']) ? 'prof-ap-active' : '' ?>" id="disc-tag-<?= $d['id'] ?>">
                                <span class="disc-tag-code"><?= htmlspecialchars($d['disciplina_codigo']) ?></span>
                                <span><?= htmlspecialchars($d['disc_nome']) ?></span>
                                <button type="button" class="disc-tag-toggle-ap"
                                        onclick="toggleProfAplicador(<?= $d['id'] ?>)"
                                        title="<?= !empty($d['professor_aplicador']) ? 'Desmarcar: Professor aplica a própria prova' : 'Marcar: Professor aplica a própria prova (sem volante)' ?>">
                                    👤
                                </button>
                                <button type="button" class="disc-tag-remove"
                                        onclick="removeDisciplina(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['disc_nome'])) ?>')"
                                        title="Remover">×</button>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap;padding-top:1.25rem;border-top:1px solid var(--border-color);">
                <button type="button" class="btn btn-ghost" onclick="switchTab('slots')">← Voltar</button>
                <?php $podeGrade = !empty($somativa['turmas']) && !empty($somativa['slots']); ?>
                <a href="grade.php?id=<?= $id ?>" class="btn btn-primary"
                   <?= !$podeGrade ? 'style="opacity:.5;pointer-events:none" title="Configure turmas e slots primeiro"' : '' ?>>
                    📅 Ir para Grade de Horários
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:3rem 1rem;">
            <div style="font-size:2rem;margin-bottom:.75rem;">🔒</div>
            <p style="font-weight:600;margin:0 0 .375rem;">Salve os dados básicos primeiro</p>
            <p style="font-size:.875rem;color:var(--text-muted);margin:0 0 1.25rem;">Crie a somativa para depois configurar turmas.</p>
            <button type="button" class="btn btn-primary" onclick="switchTab('dados')">Ir para Dados Básicos</button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════
     TAB 4 — Restrições de Agendamento
══════════════════════════════════════════════════════════ -->
<div class="tab-panel" id="tab-restricoes">
    <?php if ($isEdit):
        try {
            $restricoes = $service->getRestricoes($id);
        } catch (\Throwable $e) {
            $restricoes = [];
            error_log('[somativas/edit.php] getRestricoes: ' . $e->getMessage());
        }

        // Disciplinas da somativa para autocomplete no formulário de restrições
        $discsSomativa = [];
        foreach ($somativa['turmas'] ?? [] as $t) {
            foreach ($t['disciplinas'] ?? [] as $d) {
                $discsSomativa[$d['disciplina_codigo']] = $d['disc_nome'] ?? $d['descricao'] ?? $d['disciplina_codigo'];
            }
        }
    ?>
    <div class="card">
        <div class="card-header">
            <span class="card-title">⚙️ Restrições de Agendamento</span>
            <button type="button" class="btn btn-secondary btn-sm" id="btn-add-restricao">+ Nova Restrição</button>
        </div>
        <div class="card-body">
            <p style="font-size:.875rem;color:var(--text-muted);margin:0 0 1.25rem;">
                Restrições orientam a alocação automática. <strong>Hard</strong> = bloqueio absoluto. <strong>Soft</strong> = preferência (pontuação).
            </p>

            <!-- Lista de restrições existentes -->
            <div id="restricoes-list">
                <?php if (empty($restricoes)): ?>
                <p class="turma-empty" id="restricoes-empty">Nenhuma restrição cadastrada. O sistema usará apenas as sugestões automáticas.</p>
                <?php endif; ?>
                <?php foreach ($restricoes as $r): ?>
                <div class="restricao-item <?= $r['is_active'] ? '' : 'restricao-inativa' ?>" id="ritem-<?= $r['id'] ?>">
                    <div class="restricao-header">
                        <div class="restricao-info">
                            <span class="restricao-badge <?= $r['tipo'] === 'hard' ? 'badge-danger' : 'badge-warning' ?>">
                                <?= strtoupper($r['tipo']) ?>
                            </span>
                            <span class="restricao-cat"><?= htmlspecialchars(labelCategoria($r['categoria'])) ?></span>
                            <?php if ($r['descricao']): ?>
                            <span class="restricao-desc">— <?= htmlspecialchars($r['descricao']) ?></span>
                            <?php endif; ?>
                            <span class="restricao-params">
                                <?= htmlspecialchars(resumoParams($r['categoria'], json_decode($r['params'], true) ?? [], $somativa['turmas'] ?? [], $discsSomativa ?? [])) ?>
                            </span>
                        </div>
                        <div class="restricao-actions">
                            <?php if ($r['tipo'] === 'soft'): ?>
                            <span class="restricao-peso" title="Peso">⚖️ <?= $r['peso'] ?></span>
                            <?php endif; ?>
                            <button type="button" class="btn btn-ghost btn-xs"
                                    onclick="openEditRestricao(<?= $r['id'] ?>)"
                                    title="Editar">
                                ✏️
                            </button>
                            <button type="button" class="btn btn-ghost btn-xs"
                                    onclick="toggleRestricao(<?= $r['id'] ?>)"
                                    title="<?= $r['is_active'] ? 'Desativar' : 'Ativar' ?>">
                                <?= $r['is_active'] ? '⏸' : '▶️' ?>
                            </button>
                            <button type="button" class="btn btn-ghost btn-xs"
                                    style="color:var(--color-danger)"
                                    onclick="deleteRestricao(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes(labelCategoria($r['categoria']))) ?>')">
                                ✕
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modal para nova restrição -->
    <div id="restricao-modal-overlay" class="slot-modal-overlay" hidden>
        <div class="slot-modal" style="max-width:520px">
            <div class="slot-modal-header">
                <h2 class="slot-modal-title" id="r-modal-title">Nova Restrição</h2>
                <button type="button" class="slot-modal-close" onclick="closeRestricaoModal()">✕</button>
            </div>
            <div class="slot-modal-body">
                <form id="form-restricao">
                    <input type="hidden" name="csrf_token"   value="<?= csrf_token() ?>">
                    <input type="hidden" name="somativa_id"  value="<?= $id ?>">
                    <input type="hidden" name="action"       value="save">
                    <input type="hidden" name="id"           id="r-id" value="">

                    <div class="slot-modal-grid">
                        <!-- Tipo -->
                        <div class="form-group">
                            <label class="form-label">Tipo<span class="label-req">*</span></label>
                            <select name="tipo" id="r-tipo" class="form-control">
                                <option value="soft">Soft — preferência (pontuação)</option>
                                <option value="hard">Hard — bloqueio absoluto</option>
                            </select>
                        </div>

                        <!-- Categoria -->
                        <div class="form-group">
                            <label class="form-label">Categoria<span class="label-req">*</span></label>
                            <select name="categoria" id="r-categoria" class="form-control"
                                    onchange="renderRestricaoParams()">
                                <option value="">Selecione...</option>
                                <option value="bloquear_data">Bloquear data (feriado / recesso)</option>
                                <option value="evitar_mesmo_dia">Evitar disciplinas no mesmo dia</option>
                                <option value="mesmo_dia_turmas">Manter disciplina no mesmo dia em todas as turmas</option>
                                <option value="mesmo_horario_turmas">Alocar disciplinas iguais no mesmo horário em turmas diferentes</option>
                                <option value="mesmo_dia_horario_diferente">Relação de dia/horário entre duas disciplinas</option>
                                <option value="professor_indisponivel">Professor indisponível em datas específicas</option>
                                <option value="professor_mesmo_dia_horario">Professor — Mesmo dia e horário em turmas diferentes</option>
                                <option value="preferir_primeiros_horarios">Concentrar provas nos primeiros horários</option>
                                <option value="min_provas_por_dia">Mínimo de provas por dia</option>
                                <option value="mesmo_dia_horario_grupo">Mesmo dia e horário — grupo de disciplinas</option>
                            </select>
                        </div>

                        <!-- Params dinâmicos -->
                        <div id="r-params-container" style="grid-column:1/-1"></div>

                        <!-- Peso (só soft) -->
                        <div class="form-group" id="r-peso-group">
                            <label class="form-label">Peso (1–10)</label>
                            <input type="number" name="peso" id="r-peso" class="form-control"
                                   min="1" max="10" value="5">
                            <span class="form-hint">Quanto maior, maior o impacto na pontuação do slot.</span>
                        </div>

                        <!-- Descrição -->
                        <div class="form-group" style="grid-column:1/-1">
                            <label class="form-label">Descrição (opcional)</label>
                            <input type="text" name="descricao" id="r-descricao" class="form-control"
                                   placeholder="Ex.: Feriado municipal" maxlength="255">
                        </div>
                    </div>

                    <div class="slot-modal-actions">
                        <button type="button" class="btn btn-ghost" onclick="closeRestricaoModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Restrição</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:3rem 1rem;">
            <div style="font-size:2rem;margin-bottom:.75rem;">🔒</div>
            <p style="font-weight:600;margin:0 0 .375rem;">Salve os dados básicos primeiro</p>
            <button type="button" class="btn btn-primary" onclick="switchTab('dados')">Ir para Dados Básicos</button>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const SOMATIVA_ID = <?= $isEdit ? $id : 'null' ?>;

/* ── Tabs ─────────────────────────────────────────────────── */
function switchTab(name) {
    document.querySelectorAll('.som-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelector(`[data-tab="${name}"]`).classList.add('active');
    document.getElementById(`tab-${name}`).classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
document.querySelectorAll('.som-tab').forEach(btn => {
    btn.addEventListener('click', () => { if (!btn.disabled) switchTab(btn.dataset.tab); });
});

function reloadPreservingTab() {
    const activeTabBtn = document.querySelector('.som-tab.active');
    const activeTab = activeTabBtn ? activeTabBtn.dataset.tab : '';
    const url = new URL(location.href);
    if (activeTab) {
        url.searchParams.set('tab', activeTab);
    }
    location.href = url.toString();
}

/* ── Tab inicial por URL ──────────────────────────────────── */
const urlTab = new URLSearchParams(location.search).get('tab');
if (urlTab) switchTab(urlTab);

/* ── Salvar dados básicos ─────────────────────────────────── */
document.getElementById('form-dados').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btn-save-dados');
    const fd  = new FormData(e.target);
    fd.set('action', SOMATIVA_ID ? 'update' : 'create');
    if (SOMATIVA_ID) fd.set('id', SOMATIVA_ID);

    btn.disabled = true; btn.textContent = 'Salvando...';
    try {
        const r = await fetch('/api/somativas/index.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            Toast.success('Somativa salva!');
            if (!SOMATIVA_ID && d.id) {
                setTimeout(() => { window.location.href = `edit.php?id=${d.id}&tab=slots`; }, 800);
            }
        } else {
            Toast.error(d.message || 'Erro ao salvar');
        }
    } catch { Toast.error('Erro de comunicação'); }
    finally { btn.disabled = false; btn.textContent = SOMATIVA_ID ? 'Salvar Alterações' : 'Criar Somativa'; }
});

/* ── Slots ────────────────────────────────────────────────── */
function buildSlotRow(index, data = {}) {
    const div = document.createElement('div');
    div.className = 'slot-row';
    div.innerHTML = `
        <span class="slot-num">${index + 1}</span>
        <div class="slot-fields">
            <input type="time" class="form-control slot-time" value="${data.inicio||''}" required>
            <span class="slot-sep">até</span>
            <input type="time" class="form-control slot-time" value="${data.fim||''}" required>
            <input type="text" class="form-control slot-label-input"
                   value="${data.label||''}" placeholder="Rótulo opcional" maxlength="50">
        </div>
        <button type="button" class="slot-remove" title="Remover">✕</button>`;
    div.querySelector('.slot-remove').addEventListener('click', () => { div.remove(); reNumSlots(); });
    return div;
}
function reNumSlots() {
    document.querySelectorAll('#slots-list .slot-row').forEach((r, i) => {
        r.querySelector('.slot-num').textContent = i + 1;
    });
    const hint = document.getElementById('slot-empty-hint');
    if (hint) hint.style.display = document.querySelectorAll('#slots-list .slot-row').length ? 'none' : '';
}

document.getElementById('btn-add-slot').addEventListener('click', () => {
    const hint = document.getElementById('slot-empty-hint');
    if (hint) hint.style.display = 'none';
    const rows = document.querySelectorAll('#slots-list .slot-row');
    document.getElementById('slots-list').appendChild(buildSlotRow(rows.length));
});

document.querySelectorAll('.slot-remove').forEach(btn => {
    btn.addEventListener('click', () => { btn.closest('.slot-row').remove(); reNumSlots(); });
});

document.getElementById('btn-save-slots').addEventListener('click', async () => {
    if (!SOMATIVA_ID) { Toast.error('Salve os dados básicos primeiro.'); return; }
    const rows = document.querySelectorAll('#slots-list .slot-row');
    if (!rows.length) { Toast.error('Adicione pelo menos um horário.'); return; }

    const slots = []; let valid = true;
    rows.forEach(r => {
        const ini = r.querySelectorAll('.slot-time')[0].value;
        const fim = r.querySelectorAll('.slot-time')[1].value;
        const lbl = r.querySelector('.slot-label-input').value.trim();
        if (!ini || !fim || ini >= fim) { valid = false; return; }
        slots.push({ horario_inicio: ini, horario_fim: fim, label: lbl });
    });
    if (!valid) { Toast.error('Verifique os horários: início deve ser anterior ao fim.'); return; }

    const fd = new FormData();
    fd.append('action', 'save_slots'); fd.append('id', SOMATIVA_ID);
    fd.append('csrf_token', window.csrfToken);
    fd.append('slots', JSON.stringify(slots));

    const btn = document.getElementById('btn-save-slots');
    btn.disabled = true; btn.textContent = 'Salvando...';
    try {
        const r = await fetch('/api/somativas/index.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            Toast.success('Horários salvos!');
            const tabTurmas = document.querySelector('[data-tab="turmas"]');
            tabTurmas.disabled = false; tabTurmas.removeAttribute('title');
        } else { Toast.error(d.message || 'Erro'); }
    } catch { Toast.error('Erro de comunicação'); }
    finally { btn.disabled = false; btn.textContent = 'Salvar Horários'; }
});

/* ── Smart Search ─────────────────────────────────────────── */
function initSearch(wrap) {
    const input = wrap.querySelector('.smart-input');
    const drop  = wrap.querySelector('.smart-drop');
    const type  = wrap.dataset.type;
    let   tmr   = null;

    input.addEventListener('input', () => {
        clearTimeout(tmr);
        const q = input.value.trim();
        if (!q) { drop.hidden = true; return; }
        tmr = setTimeout(() => doSearch(q), 250);
    });
    input.addEventListener('focus', () => { if (input.value.trim()) doSearch(input.value.trim()); });
    document.addEventListener('click', e => { if (!wrap.contains(e.target)) drop.hidden = true; });

    async function doSearch(q) {
        const turmaId = wrap.dataset.turmaId || '';
        try {
            const r = await fetch(`/api/somativas/search.php?type=${type}&q=${encodeURIComponent(q)}${turmaId?'&turma_id='+turmaId:''}`);
            const d = await r.json();
            renderDrop(d.results || []);
        } catch { drop.hidden = true; }
    }

    function renderDrop(results) {
        if (!results.length) { drop.hidden = true; return; }
        drop.innerHTML = results.map(r =>
            `<div class="sr" data-id="${esc(r.id)}" data-label="${esc(r.label)}">
                <span class="sr-label">${esc(r.label)}</span>
                ${r.sub ? `<span class="sr-sub">${esc(r.sub)}</span>` : ''}
            </div>`
        ).join('');
        drop.querySelectorAll('.sr').forEach(el => {
            el.addEventListener('click', () => pick(el.dataset.id, el.dataset.label));
        });
        drop.hidden = false;
    }

    function pick(id, label) {
        drop.hidden = true; input.value = '';
        if (type === 'turma') addTurma(id, label);
        else if (type === 'disciplina_turma') addDisciplina(wrap.dataset.stId, id, label);
    }
}

document.querySelectorAll('.smart-wrap').forEach(w => initSearch(w));

/* ── Smart Search NAAPI Ambiente ──────────────────────────── */
function clearNaapiAmbiente() {
    document.getElementById('field-naapi-ambiente-id').value = '';
    const sel = document.getElementById('selected-naapi-ambiente');
    sel.hidden = true;
    sel.innerHTML = '';
}

// Override do pick para o campo de ambiente NAAPI
(function () {
    const wrap = document.getElementById('naapi-ambiente-search');
    if (!wrap) return;
    const input = wrap.querySelector('.smart-input');
    const drop  = wrap.querySelector('.smart-drop');

    input.addEventListener('input', () => {
        clearTimeout(window._naapiTimer);
        const q = input.value.trim();
        if (!q) { drop.hidden = true; return; }
        window._naapiTimer = setTimeout(() => doNaapiSearch(q), 250);
    });
    input.addEventListener('focus', () => { if (input.value.trim()) doNaapiSearch(input.value.trim()); });
    document.addEventListener('click', e => { if (!wrap.contains(e.target)) drop.hidden = true; });

    async function doNaapiSearch(q) {
        try {
            const r = await fetch(`/api/somativas/search.php?type=ambiente&q=${encodeURIComponent(q)}`);
            const d = await r.json();
            const results = d.results || [];
            if (!results.length) { drop.hidden = true; return; }
            drop.innerHTML = results.map(r =>
                `<div class="sr" data-id="${esc(r.id)}" data-label="${esc(r.label)}">
                    <span class="sr-label">${esc(r.label)}</span>
                    ${r.sub ? `<span class="sr-sub">${esc(r.sub)}</span>` : ''}
                </div>`
            ).join('');
            drop.querySelectorAll('.sr').forEach(el => {
                el.addEventListener('click', () => {
                    drop.hidden = true;
                    input.value = '';
                    document.getElementById('field-naapi-ambiente-id').value = el.dataset.id;
                    const sel = document.getElementById('selected-naapi-ambiente');
                    sel.innerHTML = `<span>✓ ${esc(el.dataset.label)}</span>
                        <button type="button" onclick="clearNaapiAmbiente()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;margin-left:.25rem;">✕</button>`;
                    sel.hidden = false;
                });
            });
            drop.hidden = false;
        } catch { drop.hidden = true; }
    }
})();

/* ── Turmas ───────────────────────────────────────────────── */
async function addTurma(turmaId, label) {
    const fd = new FormData();
    fd.append('action', 'add_turma'); fd.append('csrf_token', window.csrfToken);
    fd.append('somativa_id', SOMATIVA_ID); fd.append('turma_id', turmaId);
    try {
        const r = await fetch('/api/somativas/index.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) { Toast.success('Turma adicionada!'); reloadPreservingTab(); }
        else Toast.error(d.message || 'Erro');
    } catch { Toast.error('Erro de comunicação'); }
}

async function removeTurma(stId, label) {
    Modal.confirm({
        title: 'Remover Turma', confirmText: 'Remover', confirmClass: 'btn-danger',
        message: `Remover <strong>${label}</strong>? As disciplinas e grade desta turma também serão removidas.`,
        onConfirm: async () => {
            const fd = new FormData();
            fd.append('action', 'remove_turma'); fd.append('csrf_token', window.csrfToken);
            fd.append('somativa_turma_id', stId);
            try {
                const r = await fetch('/api/somativas/index.php', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.success) { document.getElementById(`turma-item-${stId}`)?.remove(); Toast.success('Removida.'); }
                else Toast.error(d.message || 'Erro');
            } catch { Toast.error('Erro de comunicação'); }
        }
    });
}

/* ── Disciplinas ──────────────────────────────────────────── */
function toggleDiscs(stId) {
    const p = document.getElementById(`disc-panel-${stId}`);
    p.hidden = !p.hidden;
}

async function addDisciplina(stId, codigo, nome) {
    const fd = new FormData();
    fd.append('action', 'add_disciplina'); fd.append('csrf_token', window.csrfToken);
    fd.append('somativa_turma_id', stId); fd.append('disciplina_codigo', codigo);
    try {
        const r = await fetch('/api/somativas/index.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            const sdId  = d.somativa_disciplina_id;
            const tags  = document.getElementById(`disc-tags-${stId}`);
            const empty = document.getElementById(`disc-empty-${stId}`);
            if (empty) empty.remove();
            const span = document.createElement('span');
            span.className = 'disc-tag'; span.id = `disc-tag-${sdId}`;
            span.innerHTML = `<span class="disc-tag-code">${esc(codigo)}</span>
                <span>${esc(nome)}</span>
                <button type="button" class="disc-tag-toggle-ap"
                    onclick="toggleProfAplicador(${sdId})" title="Marcar: Professor aplica a própria prova (sem volante)">👤</button>
                <button type="button" class="disc-tag-remove"
                    onclick="removeDisciplina(${sdId},'${esc(nome)}')" title="Remover">×</button>`;
            tags.appendChild(span);
            Toast.success('Disciplina adicionada!');
        } else Toast.error(d.message || 'Erro');
    } catch { Toast.error('Erro de comunicação'); }
}

async function removeDisciplina(sdId, nome) {
    const fd = new FormData();
    fd.append('action', 'remove_disciplina'); fd.append('csrf_token', window.csrfToken);
    fd.append('somativa_disciplina_id', sdId);
    try {
        const r = await fetch('/api/somativas/index.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) { document.getElementById(`disc-tag-${sdId}`)?.remove(); Toast.success('Removida.'); }
        else Toast.error(d.message || 'Erro');
    } catch { Toast.error('Erro de comunicação'); }
}

async function toggleProfAplicador(sdId) {
    const fd = new FormData();
    fd.append('action', 'toggle_prof_aplicador'); fd.append('csrf_token', window.csrfToken);
    fd.append('somativa_disciplina_id', sdId);
    try {
        const r = await fetch('/api/somativas/index.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            const tag = document.getElementById(`disc-tag-${sdId}`);
            const btn = tag.querySelector('.disc-tag-toggle-ap');
            if (d.professor_aplicador) {
                tag.classList.add('prof-ap-active');
                btn.title = 'Desmarcar: Professor aplica a própria prova';
            } else {
                tag.classList.remove('prof-ap-active');
                btn.title = 'Marcar: Professor aplica a própria prova (sem volante)';
            }
            Toast.success('Configuração atualizada.');
        } else Toast.error(d.message || 'Erro');
    } catch { Toast.error('Erro de comunicação'); }
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

<?php if ($isEdit): ?>
/* ── Restrições de Agendamento ────────────────────────────── */
const DISCS_SOMATIVA = <?= json_encode($discsSomativa ?? [], JSON_UNESCAPED_UNICODE) ?>;
const TURMAS_SOMATIVA = <?= json_encode(array_map(function($t) {
    return [
        'somativa_turma_id' => $t['somativa_turma_id'],
        'turma_id' => $t['turma_id'],
        'label' => $t['course_name'] . ' — ' . $t['turma_desc'] . ' (' . $t['ano'] . ')'
    ];
}, $somativa['turmas'] ?? []), JSON_UNESCAPED_UNICODE) ?>;
const RESTRICOES = <?= json_encode($restricoes ?? [], JSON_UNESCAPED_UNICODE) ?>;
const DISC_TURMA_OPTIONS = <?= json_encode((static function() use ($somativa): array {
    $opts = [];
    foreach ($somativa['turmas'] ?? [] as $t) {
        foreach ($t['disciplinas'] ?? [] as $d) {
            $opts[] = [
                'sdId'       => (int)$d['id'],
                'discCod'    => $d['disciplina_codigo'],
                'discNome'   => $d['disc_nome'] ?? $d['disciplina_codigo'],
                'professores'=> $d['professores'] ?? '',
                'stId'       => (int)$t['somativa_turma_id'],
                'turmaLabel' => $t['course_name'] . ' — ' . $t['turma_desc'],
            ];
        }
    }
    return $opts;
})(), JSON_UNESCAPED_UNICODE) ?>;

let selectedEvitarKeys = [];
let selectedGrupoKeys = [];

const getEvitarOptions = () => {
    const seen = new Set();
    const options = [];
    for (const o of DISC_TURMA_OPTIONS) {
        const key = o.discCod + '|' + (o.professores || '');
        if (!seen.has(key)) {
            seen.add(key);
            options.push({
                discCod: o.discCod,
                discNome: o.discNome,
                professores: o.professores
            });
        }
    }
    return options;
};

const getGrupoOptions = () => {
    const groups = {};
    for (const o of DISC_TURMA_OPTIONS) {
        const key = o.discCod + '|' + (o.professores || '');
        if (!groups[key]) {
            groups[key] = {
                discCod: o.discCod,
                discNome: o.discNome,
                professores: o.professores,
                sdIds: []
            };
        }
        groups[key].sdIds.push(o.sdId);
    }
    return Object.values(groups);
};

window.updateEvitarTags = function() {
    const tagsContainer = document.getElementById('evitar-tags-container');
    const hiddenContainer = document.getElementById('evitar-hidden-inputs');
    if (!tagsContainer || !hiddenContainer) return;
    
    if (selectedEvitarKeys.length === 0) {
        tagsContainer.innerHTML = '<span class="disc-empty">Nenhuma disciplina selecionada</span>';
        hiddenContainer.innerHTML = '';
        return;
    }
    
    tagsContainer.innerHTML = selectedEvitarKeys.map(key => {
        const o = getEvitarOptions().find(item => (item.discCod + '|' + (item.professores || '')) === key);
        if (!o) return '';
        const profInfo = o.professores ? ` (Prof: ${o.professores})` : '';
        const safeKey = key.replace(/'/g, "\\'");
        return `
            <span class="disc-tag">
                <span class="disc-tag-code">${esc(o.discCod)}</span>
                <span>${esc(o.discNome)}${esc(profInfo)}</span>
                <button type="button" class="disc-tag-remove" onclick="removeEvitarKey('${esc(safeKey)}')" title="Remover">×</button>
            </span>
        `;
    }).join('');
    
    const uniqueCodes = [...new Set(selectedEvitarKeys.map(k => k.split('|')[0]))];
    hiddenContainer.innerHTML = uniqueCodes.map(cod => `
        <input type="hidden" name="param_disciplinas[]" value="${esc(cod)}">
    `).join('');
};

window.removeEvitarKey = function(key) {
    selectedEvitarKeys = selectedEvitarKeys.filter(item => item !== key);
    updateEvitarTags();
};

window.updateGrupoTags = function() {
    const tagsContainer = document.getElementById('grupo-tags-container');
    const hiddenContainer = document.getElementById('grupo-hidden-inputs');
    if (!tagsContainer || !hiddenContainer) return;
    
    if (selectedGrupoKeys.length === 0) {
        tagsContainer.innerHTML = '<span class="disc-empty">Nenhuma disciplina selecionada</span>';
        hiddenContainer.innerHTML = '';
        return;
    }
    
    tagsContainer.innerHTML = selectedGrupoKeys.map(key => {
        const o = getGrupoOptions().find(item => (item.discCod + '|' + (item.professores || '')) === key);
        if (!o) return '';
        const profInfo = o.professores ? ` (Prof: ${o.professores})` : '';
        const safeKey = key.replace(/'/g, "\\'");
        return `
            <span class="disc-tag">
                <span class="disc-tag-code">${esc(o.discCod)}</span>
                <span>${esc(o.discNome)}${esc(profInfo)}</span>
                <button type="button" class="disc-tag-remove" onclick="removeGrupoKey('${esc(safeKey)}')" title="Remover">×</button>
            </span>
        `;
    }).join('');
    
    const allSdIds = [];
    for (const key of selectedGrupoKeys) {
        const o = getGrupoOptions().find(item => (item.discCod + '|' + (item.professores || '')) === key);
        if (o) {
            allSdIds.push(...o.sdIds);
        }
    }
    hiddenContainer.innerHTML = allSdIds.map(sdId => `
        <input type="hidden" name="param_pares[]" value="${sdId}">
    `).join('');

    // Aviso: detecta múltiplas disciplinas do mesmo grupo na mesma turma (restrição impossível)
    const warnEl = document.getElementById('grupo-warn-mesma-turma');
    if (warnEl) {
        const stIdCounts = {};
        for (const sdId of allSdIds) {
            const opt = DISC_TURMA_OPTIONS.find(o => o.sdId === sdId);
            if (opt) { stIdCounts[opt.stId] = (stIdCounts[opt.stId] || 0) + 1; }
        }
        const hasConflict = Object.values(stIdCounts).some(c => c > 1);
        warnEl.hidden = !hasConflict;
    }
};

window.removeGrupoKey = function(key) {
    selectedGrupoKeys = selectedGrupoKeys.filter(item => item !== key);
    updateGrupoTags();
};

function renderRestricaoParams(params = {}) {
    const cat       = document.getElementById('r-categoria').value;
    const container = document.getElementById('r-params-container');
    const pesoGroup = document.getElementById('r-peso-group');
    const tipo      = document.getElementById('r-tipo').value;

    pesoGroup.hidden = (tipo === 'hard');
    container.innerHTML = '';

    if (!cat) return;

    let html = '';
    if (cat === 'bloquear_data') {
        html = `
        <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Data a Bloquear<span class="label-req">*</span></label>
            <input type="date" name="param_data" class="form-control" value="${esc(params.data || '')}" required>
        </div>
        <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Motivo</label>
            <input type="text" name="param_motivo" class="form-control" value="${esc(params.motivo || '')}" placeholder="Ex.: Feriado Nacional">
        </div>`;
    } else if (cat === 'evitar_mesmo_dia') {
        if (params && params.disciplinas) {
            selectedEvitarKeys = getEvitarOptions()
                .filter(o => params.disciplinas.includes(o.discCod))
                .map(o => o.discCod + '|' + (o.professores || ''));
        }
        html = `
        <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Disciplinas a separar<span class="label-req">*</span></label>
            <div class="smart-wrap" id="evitar-search-wrap">
                <input type="text" id="evitar-search-input" class="form-control smart-input" autocomplete="off" placeholder="Digite o código ou nome da disciplina...">
                <div class="smart-drop" id="evitar-search-drop" hidden></div>
            </div>
            <div class="disc-tags" id="evitar-tags-container" style="margin-top: 0.75rem;"></div>
            <div id="evitar-hidden-inputs"></div>
            <span class="form-hint">Mínimo 2 disciplinas.</span>
        </div>`;
    } else if (cat === 'mesmo_dia_turmas') {
        const selectedCod = params.disciplina_codigo || '';
        const options = Object.entries(DISCS_SOMATIVA)
            .map(([cod, nome]) => {
                const selected = cod === selectedCod ? 'selected' : '';
                return `<option value="${esc(cod)}" ${selected}>${esc(cod)} — ${esc(nome)}</option>`;
            })
            .join('');
        html = `
        <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Disciplina<span class="label-req">*</span></label>
            <select name="param_disciplina_codigo" class="form-control" required>
                <option value="">Selecione...</option>
                ${options}
            </select>
        </div>`;
    } else if (cat === 'mesmo_horario_turmas') {
        const scope = params.scope || 'disciplina';
        const selectedCod = params.disciplina_codigo || '';
        const selectedTurma = params.turma_id || '';

        const discOptions = Object.entries(DISCS_SOMATIVA)
            .map(([cod, nome]) => {
                const selected = cod === selectedCod ? 'selected' : '';
                return `<option value="${esc(cod)}" ${selected}>${esc(cod)} — ${esc(nome)}</option>`;
            })
            .join('');

        const turmaOptions = TURMAS_SOMATIVA
            .map(t => {
                const selected = t.turma_id == selectedTurma ? 'selected' : '';
                return `<option value="${esc(t.turma_id)}" ${selected}>${esc(t.label)}</option>`;
            })
            .join('');

        html = `
        <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Aplicar restrição por:<span class="label-req">*</span></label>
            <select name="param_scope" id="r-horario-scope" class="form-control" onchange="toggleHorarioScopeFields()">
                <option value="disciplina" ${scope === 'disciplina' ? 'selected' : ''}>Disciplina Específica</option>
                <option value="turma" ${scope === 'turma' ? 'selected' : ''}>Turma de Referência (todas as disciplinas iguais)</option>
            </select>
        </div>
        <div class="form-group" id="r-horario-disc-group" style="grid-column:1/-1" ${scope === 'turma' ? 'hidden' : ''}>
            <label class="form-label">Disciplina<span class="label-req">*</span></label>
            <select name="param_disciplina_codigo" class="form-control" id="r-horario-disc-select" ${scope === 'disciplina' ? 'required' : ''}>
                <option value="">Selecione...</option>
                ${discOptions}
            </select>
        </div>
        <div class="form-group" id="r-horario-turma-group" style="grid-column:1/-1" ${scope === 'disciplina' ? 'hidden' : ''}>
            <label class="form-label">Turma de Referência<span class="label-req">*</span></label>
            <select name="param_turma_id" class="form-control" id="r-horario-turma-select" ${scope === 'turma' ? 'required' : ''}>
                <option value="">Selecione...</option>
                ${turmaOptions}
            </select>
            <span class="form-hint">O agendador alocará todas as disciplinas desta turma nos mesmos horários em outras turmas que tenham disciplinas correspondentes.</span>
        </div>`;
    } else if (cat === 'mesmo_dia_horario_diferente') {
        const codA = params.disciplina_codigo_a || '';
        const codB = params.disciplina_codigo_b || '';
        const scope = params.scope || 'mesma_turma';
        const regra = params.regra || 'deve';

        const optionsA = Object.entries(DISCS_SOMATIVA)
            .map(([cod, nome]) => {
                const selected = cod === codA ? 'selected' : '';
                return `<option value="${esc(cod)}" ${selected}>${esc(cod)} — ${esc(nome)}</option>`;
            })
            .join('');

        const optionsB = Object.entries(DISCS_SOMATIVA)
            .map(([cod, nome]) => {
                const selected = cod === codB ? 'selected' : '';
                return `<option value="${esc(cod)}" ${selected}>${esc(cod)} — ${esc(nome)}</option>`;
            })
            .join('');

        html = `
        <div class="form-group">
            <label class="form-label">Disciplina A<span class="label-req">*</span></label>
            <select name="param_disciplina_codigo_a" class="form-control" required>
                <option value="">Selecione...</option>
                ${optionsA}
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Disciplina B<span class="label-req">*</span></label>
            <select name="param_disciplina_codigo_b" class="form-control" required>
                <option value="">Selecione...</option>
                ${optionsB}
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Relação / Escopo<span class="label-req">*</span></label>
            <select name="param_scope_relacao" class="form-control" required>
                <option value="mesma_turma" ${scope === 'mesma_turma' ? 'selected' : ''}>Na mesma turma</option>
                <option value="turmas_diferentes" ${scope === 'turmas_diferentes' ? 'selected' : ''}>Em turmas diferentes</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Regra<span class="label-req">*</span></label>
            <select name="param_regra_relacao" class="form-control" required>
                <option value="deve" ${regra === 'deve' ? 'selected' : ''}>Deve acontecer (mesmo dia, horários diferentes)</option>
                <option value="nao_deve" ${regra === 'nao_deve' ? 'selected' : ''}>Não deve acontecer no mesmo dia</option>
            </select>
        </div>`;
    } else if (cat === 'professor_indisponivel') {
        const profId = params.professor_id || '';
        const datas = (params.datas || []).join(', ');
        html = `
        <div class="form-group">
            <label class="form-label">Professor ID<span class="label-req">*</span></label>
            <input type="number" name="param_professor_id" class="form-control" min="1" value="${esc(profId)}" required
                   placeholder="ID do professor">
            <span class="form-hint">Busque o ID do professor no cadastro de usuários.</span>
        </div>
        <div class="form-group">
            <label class="form-label">Datas (separadas por vírgula)<span class="label-req">*</span></label>
            <input type="text" name="param_datas" class="form-control" value="${esc(datas)}"
                   placeholder="2026-07-01, 2026-07-02" required>
            <span class="form-hint">Formato AAAA-MM-DD, separadas por vírgula.</span>
        </div>`;
    } else if (cat === 'professor_mesmo_dia_horario') {
        const profId   = params.professor_id || '';
        const isTodos  = params.todos === true || params.todos == 1 || params.todos === 'true';
        const scopeVal = isTodos ? 'todos' : 'especifico';
        html = `
        <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Aplicar para<span class="label-req">*</span></label>
            <select name="param_pdh_scope" id="r-pdh-scope" class="form-control" onchange="togglePdhScopeFields()">
                <option value="especifico" ${scopeVal === 'especifico' ? 'selected' : ''}>Professor específico</option>
                <option value="todos"      ${scopeVal === 'todos'      ? 'selected' : ''}>Todos os professores</option>
            </select>
            <span class="form-hint" id="r-pdh-hint-todos" ${scopeVal !== 'todos' ? 'hidden' : ''}>
                Cada professor que leciona a mesma disciplina em turmas diferentes terá seus slots sincronizados.
            </span>
        </div>
        <div class="form-group" id="r-pdh-prof-group" style="grid-column:1/-1" ${scopeVal === 'todos' ? 'hidden' : ''}>
            <label class="form-label">Professor<span class="label-req">*</span></label>
            <div class="smart-wrap" id="r-prof-pdh-wrap" data-type="professor">
                <input type="text" class="form-control smart-input" autocomplete="off" placeholder="Buscar professor pelo nome...">
                <div class="smart-drop" hidden></div>
            </div>
            <input type="hidden" name="param_professor_id" id="r-param-professor-pdh-id" value="${esc(String(profId))}">
            <div id="r-selected-prof-pdh" style="margin-top:.375rem;font-size:.8125rem;color:var(--color-primary)" ${profId ? '' : 'hidden'}>
                ${profId ? '<span>&#10003; Prof. ID ' + esc(String(profId)) + '</span>' : ''}
            </div>
            <span class="form-hint">Todas as disciplinas deste professor em turmas diferentes serão alocadas no mesmo slot.</span>
        </div>`;
    } else if (cat === 'preferir_primeiros_horarios') {
        const scope         = params.scope || 'todas';
        const selectedStId  = params.somativa_turma_id || '';

        const turmaOptions = TURMAS_SOMATIVA
            .map(t => {
                const sel = String(t.somativa_turma_id) === String(selectedStId) ? 'selected' : '';
                return `<option value="${esc(t.somativa_turma_id)}" ${sel}>${esc(t.label)}</option>`;
            })
            .join('');

        html = `
        <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Aplicar para<span class="label-req">*</span></label>
            <select name="param_scope" id="r-pph-scope" class="form-control" onchange="togglePphTurmaField()">
                <option value="todas" ${scope === 'todas' ? 'selected' : ''}>Todas as turmas</option>
                <option value="turma" ${scope === 'turma' ? 'selected' : ''}>Turma específica</option>
            </select>
        </div>
        <div class="form-group" id="r-pph-turma-group" style="grid-column:1/-1" ${scope !== 'turma' ? 'hidden' : ''}>
            <label class="form-label">Turma<span class="label-req">*</span></label>
            <select name="param_somativa_turma_id" id="r-pph-turma-select" class="form-control"
                    ${scope === 'turma' ? 'required' : ''}>
                <option value="">Selecione...</option>
                ${turmaOptions}
            </select>
            <span class="form-hint">As provas desta turma serão concentradas nos primeiros slots disponíveis.</span>
        </div>`;
    } else if (cat === 'min_provas_por_dia') {
        const min           = params.min || 2;
        const scope         = params.scope || 'todas';
        const selectedStId  = params.somativa_turma_id || '';

        const turmaOptions = TURMAS_SOMATIVA
            .map(t => {
                const sel = String(t.somativa_turma_id) === String(selectedStId) ? 'selected' : '';
                return `<option value="${esc(t.somativa_turma_id)}" ${sel}>${esc(t.label)}</option>`;
            })
            .join('');

        html = `
        <div class="form-group">
            <label class="form-label">Mínimo de provas por dia<span class="label-req">*</span></label>
            <input type="number" name="param_min" class="form-control" min="1" max="20"
                   value="${esc(String(min))}" required style="max-width:120px">
            <span class="form-hint">Número mínimo de provas a alocar em cada dia do período.</span>
        </div>
        <div class="form-group">
            <label class="form-label">Aplicar para<span class="label-req">*</span></label>
            <select name="param_scope" id="r-mppd-scope" class="form-control" onchange="toggleMppdTurmaField()">
                <option value="todas" ${scope === 'todas' ? 'selected' : ''}>Todas as turmas</option>
                <option value="turma" ${scope === 'turma' ? 'selected' : ''}>Turma específica</option>
            </select>
        </div>
        <div class="form-group" id="r-mppd-turma-group" style="grid-column:1/-1" ${scope !== 'turma' ? 'hidden' : ''}>
            <label class="form-label">Turma<span class="label-req">*</span></label>
            <select name="param_somativa_turma_id" id="r-mppd-turma-select" class="form-control"
                    ${scope === 'turma' ? 'required' : ''}>
                <option value="">Selecione...</option>
                ${turmaOptions}
            </select>
            <span class="form-hint">Aplica a restrição somente à turma selecionada.</span>
        </div>`;
    } else if (cat === 'mesmo_dia_horario_grupo') {
        if (params && params.pares) {
            const sdIds = params.pares.map(p => Number(p.somativa_disciplina_id));
            const keys = new Set();
            for (const id of sdIds) {
                const o = DISC_TURMA_OPTIONS.find(item => item.sdId === id);
                if (o) {
                    keys.add(o.discCod + '|' + (o.professores || ''));
                }
            }
            selectedGrupoKeys = Array.from(keys);
        }
        html = `
        <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Disciplinas do grupo<span class="label-req">*</span></label>
            <div class="smart-wrap" id="grupo-search-wrap">
                <input type="text" id="grupo-search-input" class="form-control smart-input" autocomplete="off" placeholder="Digite a disciplina ou turma...">
                <div class="smart-drop" id="grupo-search-drop" hidden></div>
            </div>
            <div class="disc-tags" id="grupo-tags-container" style="margin-top: 0.75rem;"></div>
            <div id="grupo-hidden-inputs"></div>
            <div id="grupo-warn-mesma-turma" hidden style="margin-top:0.5rem;padding:0.5rem 0.75rem;border-radius:6px;background:#fff3cd;border:1px solid #ffc107;color:#856404;font-size:0.85rem;">
                ⚠️ <strong>Atenção:</strong> O grupo contém mais de uma disciplina da mesma turma. Como uma turma não pode ter duas provas no mesmo horário, a restrição será impossível de cumprir. Crie <strong>restrições separadas</strong> por código de disciplina (uma por código).
            </div>
            <span class="form-hint">Mínimo 2. Podem ser de turmas diferentes.</span>
        </div>`;
    }

    container.innerHTML = html;

    if (cat === 'professor_mesmo_dia_horario') {
        initRestricaoProfSearch();
    } else if (cat === 'evitar_mesmo_dia') {
        updateEvitarTags();
        const input = document.getElementById('evitar-search-input');
        const drop = document.getElementById('evitar-search-drop');
        if (input) {
            input.addEventListener('input', () => {
                const q = input.value.trim().toLowerCase();
                if (!q) { drop.hidden = true; return; }
                const matches = getEvitarOptions().filter(o => {
                    const key = o.discCod + '|' + (o.professores || '');
                    const isSelected = selectedEvitarKeys.includes(key);
                    return !isSelected && 
                           (o.discCod.toLowerCase().includes(q) || 
                            o.discNome.toLowerCase().includes(q) || 
                            (o.professores && o.professores.toLowerCase().includes(q)));
                });
                if (!matches.length) {
                    drop.innerHTML = '<div style="padding:.625rem .875rem;color:var(--text-muted);font-size:.875rem;">Nenhuma disciplina encontrada</div>';
                } else {
                    drop.innerHTML = matches.map(o => {
                        const key = o.discCod + '|' + (o.professores || '');
                        return `
                            <div class="sr" data-key="${esc(key)}">
                                <span class="sr-label">[${esc(o.discCod)}] — ${esc(o.discNome)}</span>
                                ${o.professores ? `<span class="sr-sub">Prof: ${esc(o.professores)}</span>` : '<span class="sr-sub" style="font-style:italic;color:var(--text-muted)">Sem professor</span>'}
                            </div>
                        `;
                    }).join('');
                    drop.querySelectorAll('.sr').forEach(el => {
                        el.addEventListener('click', () => {
                            const key = el.dataset.key;
                            selectedEvitarKeys.push(key);
                            input.value = '';
                            drop.hidden = true;
                            updateEvitarTags();
                            input.focus();
                        });
                    });
                }
                drop.hidden = false;
            });
        }
        document.addEventListener('click', e => {
            const wrap = document.getElementById('evitar-search-wrap');
            if (wrap && !wrap.contains(e.target) && drop) drop.hidden = true;
        });
    } else if (cat === 'mesmo_dia_horario_grupo') {
        updateGrupoTags();
        const input = document.getElementById('grupo-search-input');
        const drop = document.getElementById('grupo-search-drop');
        if (input) {
            input.addEventListener('input', () => {
                const q = input.value.trim().toLowerCase();
                if (!q) { drop.hidden = true; return; }
                const matches = getGrupoOptions().filter(o => {
                    const key = o.discCod + '|' + (o.professores || '');
                    const isSelected = selectedGrupoKeys.includes(key);
                    return !isSelected && 
                           (o.discCod.toLowerCase().includes(q) || 
                            o.discNome.toLowerCase().includes(q) || 
                            (o.professores && o.professores.toLowerCase().includes(q)));
                });
                if (!matches.length) {
                    drop.innerHTML = '<div style="padding:.625rem .875rem;color:var(--text-muted);font-size:.875rem;">Nenhuma disciplina encontrada</div>';
                } else {
                    drop.innerHTML = matches.map(o => {
                        const key = o.discCod + '|' + (o.professores || '');
                        return `
                            <div class="sr" data-key="${esc(key)}">
                                <span class="sr-label">[${esc(o.discCod)}] — ${esc(o.discNome)}</span>
                                ${o.professores ? `<span class="sr-sub">Prof: ${esc(o.professores)}</span>` : '<span class="sr-sub" style="font-style:italic;color:var(--text-muted)">Sem professor</span>'}
                            </div>
                        `;
                    }).join('');
                    drop.querySelectorAll('.sr').forEach(el => {
                        el.addEventListener('click', () => {
                            const key = el.dataset.key;
                            selectedGrupoKeys.push(key);
                            input.value = '';
                            drop.hidden = true;
                            updateGrupoTags();
                            input.focus();
                        });
                    });
                }
                drop.hidden = false;
            });
        }
        document.addEventListener('click', e => {
            const wrap = document.getElementById('grupo-search-wrap');
            if (wrap && !wrap.contains(e.target) && drop) drop.hidden = true;
        });
    }
}

function toggleHorarioScopeFields() {
    const scope = document.getElementById('r-horario-scope').value;
    const discGroup = document.getElementById('r-horario-disc-group');
    const turmaGroup = document.getElementById('r-horario-turma-group');
    const discSelect = document.getElementById('r-horario-disc-select');
    const turmaSelect = document.getElementById('r-horario-turma-select');

    if (scope === 'disciplina') {
        discGroup.hidden = false;
        turmaGroup.hidden = true;
        discSelect.required = true;
        turmaSelect.required = false;
        turmaSelect.value = '';
    } else {
        discGroup.hidden = true;
        turmaGroup.hidden = false;
        discSelect.required = false;
        discSelect.value = '';
        turmaSelect.required = true;
    }
}

function togglePphTurmaField() {
    const scope = document.getElementById('r-pph-scope').value;
    const group = document.getElementById('r-pph-turma-group');
    const sel   = document.getElementById('r-pph-turma-select');
    if (!group) return;
    group.hidden   = scope !== 'turma';
    sel.required   = scope === 'turma';
    if (scope !== 'turma') sel.value = '';
}

function toggleMppdTurmaField() {
    const scope = document.getElementById('r-mppd-scope').value;
    const group = document.getElementById('r-mppd-turma-group');
    const sel   = document.getElementById('r-mppd-turma-select');
    if (!group) return;
    group.hidden = scope !== 'turma';
    sel.required = scope === 'turma';
    if (scope !== 'turma') sel.value = '';
}

function togglePdhScopeFields() {
    const scope      = document.getElementById('r-pdh-scope')?.value;
    const profGroup  = document.getElementById('r-pdh-prof-group');
    const hintTodos  = document.getElementById('r-pdh-hint-todos');
    if (!profGroup) return;
    const isTodos = scope === 'todos';
    profGroup.hidden = isTodos;
    if (hintTodos) hintTodos.hidden = !isTodos;
    const hidden = document.getElementById('r-param-professor-pdh-id');
    if (isTodos && hidden) hidden.value = '';
}

function initRestricaoProfSearch() {
    const wrap = document.getElementById('r-prof-pdh-wrap');
    if (!wrap) return;
    const input  = wrap.querySelector('.smart-input');
    const drop   = wrap.querySelector('.smart-drop');
    const hidden = document.getElementById('r-param-professor-pdh-id');
    const sel    = document.getElementById('r-selected-prof-pdh');
    let tmr = null;

    input.addEventListener('input', () => {
        clearTimeout(tmr);
        hidden.value = '';
        sel.hidden = true; sel.innerHTML = '';
        const q = input.value.trim();
        if (!q) { drop.hidden = true; return; }
        tmr = setTimeout(() => doSearch(q), 250);
    });
    input.addEventListener('focus', () => { if (input.value.trim()) doSearch(input.value.trim()); });
    document.addEventListener('click', function onClickOutside(e) {
        if (!wrap.contains(e.target)) drop.hidden = true;
    });

    async function doSearch(q) {
        try {
            const r = await fetch(`/api/somativas/search.php?type=professor&q=${encodeURIComponent(q)}`);
            const d = await r.json();
            const results = d.results || [];
            if (!results.length) { drop.hidden = true; return; }
            drop.innerHTML = results.map(r =>
                `<div class="sr" data-id="${esc(r.id)}" data-label="${esc(r.label)}">
                    <span class="sr-label">${esc(r.label)}</span>
                    ${r.sub ? `<span class="sr-sub">${esc(r.sub)}</span>` : ''}
                </div>`
            ).join('');
            drop.querySelectorAll('.sr').forEach(el => {
                el.addEventListener('click', () => {
                    drop.hidden = true;
                    hidden.value = el.dataset.id;
                    input.value = el.dataset.label;
                    sel.innerHTML = `<span>&#10003; ${esc(el.dataset.label)}</span>
                        <button type="button" onclick="clearRestricaoProf()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;margin-left:.25rem;">&#10005;</button>`;
                    sel.hidden = false;
                });
            });
            drop.hidden = false;
        } catch { drop.hidden = true; }
    }
}

function clearRestricaoProf() {
    const hidden = document.getElementById('r-param-professor-pdh-id');
    const sel    = document.getElementById('r-selected-prof-pdh');
    const input  = document.querySelector('#r-prof-pdh-wrap .smart-input');
    if (hidden) hidden.value = '';
    if (sel) { sel.hidden = true; sel.innerHTML = ''; }
    if (input) input.value = '';
}

function openRestricaoModal() {
    selectedEvitarKeys = [];
    selectedGrupoKeys = [];
    document.getElementById('r-id').value = '';
    document.getElementById('r-modal-title').textContent = 'Nova Restrição';
    document.getElementById('form-restricao').reset();
    document.getElementById('r-params-container').innerHTML = '';
    document.getElementById('r-peso-group').hidden = false;
    document.getElementById('restricao-modal-overlay').hidden = false;
}

function openEditRestricao(id) {
    const r = RESTRICOES.find(item => item.id == id);
    if (!r) {
        Toast.error('Restrição não encontrada.');
        return;
    }

    document.getElementById('r-id').value = r.id;
    document.getElementById('r-modal-title').textContent = 'Editar Restrição';
    document.getElementById('r-tipo').value = r.tipo;
    document.getElementById('r-categoria').value = r.categoria;
    document.getElementById('r-peso').value = r.peso;
    document.getElementById('r-descricao').value = r.descricao || '';

    let params = {};
    try {
        params = JSON.parse(r.params) || {};
    } catch (e) {
        console.error('Erro ao parsear params:', e);
    }

    renderRestricaoParams(params);
    document.getElementById('restricao-modal-overlay').hidden = false;
}

function closeRestricaoModal() {
    document.getElementById('restricao-modal-overlay').hidden = true;
}

document.getElementById('r-tipo').addEventListener('change', () => {
    const tipo = document.getElementById('r-tipo').value;
    const pesoGroup = document.getElementById('r-peso-group');
    if (pesoGroup) pesoGroup.hidden = (tipo === 'hard');
});
document.getElementById('r-categoria').addEventListener('change', () => {
    selectedEvitarKeys = [];
    selectedGrupoKeys = [];
});
document.getElementById('btn-add-restricao').addEventListener('click', openRestricaoModal);
document.getElementById('restricao-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeRestricaoModal();
});

document.getElementById('form-restricao').addEventListener('submit', async (e) => {
    e.preventDefault();
    const cat = document.getElementById('r-categoria').value;
    if (cat === 'evitar_mesmo_dia') {
        const uniqueCodes = new Set(selectedEvitarKeys.map(k => k.split('|')[0]).filter(Boolean));
        if (uniqueCodes.size < 2) {
            Toast.error('Selecione pelo menos 2 disciplinas diferentes para evitar no mesmo dia.');
            return;
        }
    }
    if (cat === 'mesmo_dia_horario_grupo') {
        const allSdIds = [];
        for (const key of selectedGrupoKeys) {
            const o = getGrupoOptions().find(item => (item.discCod + '|' + (item.professores || '')) === key);
            if (o) {
                allSdIds.push(...o.sdIds);
            }
        }
        if (allSdIds.length < 2) {
            Toast.error('Selecione pelo menos 2 disciplinas para o grupo.');
            return;
        }
    }

    const btn = e.target.querySelector('[type=submit]');
    btn.disabled = true; btn.textContent = 'Salvando...';
    try {
        const res  = await fetch('/api/somativas/restricoes.php', { method: 'POST', body: new FormData(e.target) });
        const data = await res.json();
        if (data.success) {
            Toast.success('Restrição salva!');
            closeRestricaoModal();
            setTimeout(() => reloadPreservingTab(), 600);
        } else {
            Toast.error(data.message || 'Erro ao salvar');
        }
    } catch { Toast.error('Erro de comunicação'); }
    finally { btn.disabled = false; btn.textContent = 'Salvar Restrição'; }
});

async function toggleRestricao(id) {
    const fd = new FormData();
    fd.append('action',      'toggle');
    fd.append('id',          id);
    fd.append('somativa_id', SOMATIVA_ID);
    fd.append('csrf_token',  window.csrfToken);
    const res  = await fetch('/api/somativas/restricoes.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) { reloadPreservingTab(); } else { Toast.error('Erro ao alternar'); }
}

async function deleteRestricao(id, label) {
    Modal.confirm({
        title: 'Excluir Restrição', message: `Remover restrição "${label}"?`,
        confirmText: 'Excluir', confirmClass: 'btn-danger',
        onConfirm: async () => {
            const fd = new FormData();
            fd.append('action',      'delete');
            fd.append('id',          id);
            fd.append('somativa_id', SOMATIVA_ID);
            fd.append('csrf_token',  window.csrfToken);
            const res  = await fetch('/api/somativas/restricoes.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                document.getElementById('ritem-' + id)?.remove();
                Toast.success('Restrição removida.');
            } else { Toast.error('Erro ao excluir'); }
        }
    });
}
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
