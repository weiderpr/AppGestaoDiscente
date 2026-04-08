<?php
/**
 * Vértice Acadêmico — Grade Horária do Aluno (Visual)
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db     = getDB();

$alunoId = (int)($_GET['aluno_id'] ?? 0);
if (!$alunoId) {
    echo 'Erro: Aluno ID Inválido';
    exit;
}

$canActivities = hasDbPermission('students.schedule.activities', false);
$canConfig     = hasDbPermission('students.schedule.config', false);

// 0. Buscar Foto e Nome do Aluno (para persistir no refresh)
$stAluno = $db->prepare('SELECT nome, photo FROM alunos WHERE id = ?');
$stAluno->execute([$alunoId]);
$alunoData = $stAluno->fetch();
$alunoPhoto = ($alunoData && isset($alunoData['photo'])) ? $alunoData['photo'] : '';
$alunoNome  = ($alunoData && isset($alunoData['nome'])) ? $alunoData['nome'] : 'Aluno';

$inst   = getCurrentInstitution();
$instId = $inst['id'];

// 1. Buscar Turma do Aluno
$stTurma = $db->prepare('
    SELECT t.id, t.description as turma_nome, c.name as curso_nome
    FROM turma_alunos ta
    JOIN turmas t ON t.id = ta.turma_id
    JOIN courses c ON c.id = t.course_id
    WHERE ta.aluno_id = ?
    LIMIT 1
');
$stTurma->execute([$alunoId]);
$turmaInfo = $stTurma->fetch();

if (!$turmaInfo) {
    // Caso o aluno não tenha turma, não exibimos erro. Apenas grade vazia + aba de atividades.
    $turmaId = 0;
    $turmaInfo = [
        'turma_nome' => 'Sem Turma Vinculada',
        'curso_nome' => 'Atividades Extra-curriculares'
    ];
} else {
    $turmaId = $turmaInfo['id'];
}

// 2. Buscar Aulas da Turma
$aulas = [];
if ($turmaId > 0) {
    // Agora consideramos a filtragem por grupo (gestao_turma_aluno_grupo)
    $stAulas = $db->prepare('
        SELECT a.*, d.descricao as disciplina_nome, "aula" as tipo, gtag.grupo as grupo_atribuido
        FROM gestao_turma_aulas a
        JOIN disciplinas d ON d.codigo = a.disciplina_codigo
        LEFT JOIN gestao_turma_aluno_grupo gtag ON gtag.aula_id = a.id AND gtag.aluno_id = ?
        WHERE a.turma_id = ?
        AND (
            a.ocupacao = "Turma inteira" OR 
            (gtag.grupo IS NOT NULL AND a.ocupacao = gtag.grupo) OR
            (
                gtag.grupo IS NULL AND 
                a.ocupacao = "Grupo 1" AND 
                NOT EXISTS (
                    SELECT 1 FROM gestao_turma_aluno_grupo g2
                    JOIN gestao_turma_aulas a2 ON a2.id = g2.aula_id
                    WHERE g2.aluno_id = ? AND a2.disciplina_codigo = a.disciplina_codigo AND a2.turma_id = a.turma_id
                )
            )
        )
    ');
    $stAulas->execute([$alunoId, $turmaId, $alunoId]);
    $aulas = $stAulas->fetchAll();
}

// 2.1 Buscar Sessões que exigem configuração de grupo (para a aba de Configurações)
$sessoesGrupo = [];
if ($turmaId > 0) {
    $stGrp = $db->prepare('
        SELECT a.*, d.descricao as disciplina_nome, gtag.grupo as grupo_atribuido
        FROM gestao_turma_aulas a
        JOIN disciplinas d ON d.codigo = a.disciplina_codigo
        LEFT JOIN gestao_turma_aluno_grupo gtag ON gtag.aula_id = a.id AND gtag.aluno_id = ?
        WHERE a.turma_id = ? AND a.ocupacao != "Turma inteira"
        ORDER BY d.descricao, a.dia_semana, a.horario_inicio
    ');
    $stGrp->execute([$alunoId, $turmaId]);
    $sessoesGrupo = $stGrp->fetchAll();
}

// 3. Buscar Atividades Extra-curriculares
$stExtra = $db->prepare('
    SELECT *, titulo as disciplina_nome, "extra" as tipo 
    FROM gestao_alunos_atividadesextra 
    WHERE aluno_id = ? AND is_active = 1
');
$stExtra->execute([$alunoId]);
$atividades = $stExtra->fetchAll();

// Mesclar tudo para a Grade
$eventos = array_merge($aulas, $atividades);

// --- LÓGICA DE ANÁLISE ---
$stats = [
    'academic_min' => 0,
    'extra_min'    => 0,
    'daily'        => array_fill(1, 6, 0)
];

foreach ($eventos as $ev) {
    $t1 = strtotime($ev['horario_inicio']);
    $t2 = strtotime($ev['horario_fim']);
    $diff = ($t2 - $t1) / 60;
    if ($diff < 0) $diff = 0;

    if ($ev['tipo'] === 'aula') $stats['academic_min'] += $diff;
    else $stats['extra_min'] += $diff;

    if (isset($stats['daily'][$ev['dia_semana']])) {
        $stats['daily'][$ev['dia_semana']] += $diff;
    }
}

function formatMinutos($min) {
    if ($min <= 0) return '0h';
    $h = floor($min / 60);
    $m = $min % 60;
    return $h . "h " . ($m > 0 ? $m . "min" : "");
}

// --- LÓGICA DE ESFORÇO ACADÊMICO (Neuroeducação) ---
$totalMinutosT = $stats['academic_min'] + $stats['extra_min'];
$mediaDiariaT  = $totalMinutosT / 6;

$escalas = [
    'ideal' => [
        'label' => 'Ideal',
        'color' => '#10b981', 
        'bg'    => 'rgba(16, 185, 129, 0.1)',
        'hint'  => 'Alta retenção cognitiva e equilíbrio total.',
        'msg'   => 'Até 6h/dia (30h/sem). Perfeito para o aprendizado profundo.'
    ],
    'razoavel' => [
        'label' => 'Razoável',
        'color' => '#06b6d4', 
        'bg'    => 'rgba(6, 182, 212, 0.1)',
        'hint'  => 'Limite do esforço sustentável.',
        'msg'   => '7h a 8h/dia (35-40h/sem). Requer atenção contínua.'
    ],
    'excessiva' => [
        'label' => 'Excessiva',
        'color' => '#f59e0b', 
        'bg'    => 'rgba(245, 158, 11, 0.1)',
        'hint'  => 'Risco de fadiga cognitiva.',
        'msg'   => '9h a 10h/dia (45-50h/sem). Queda de desempenho iminente.'
    ],
    'critica' => [
        'label' => 'Crítica',
        'color' => '#ef4444', 
        'bg'    => 'rgba(239, 68, 68, 0.1)',
        'hint'  => 'Zona de Burnout / Exaustão.',
        'msg'   => '>10h/dia (>55h/sem). Privação de sono e fadiga severa.'
    ]
];

$nivelKey = 'ideal';
if ($mediaDiariaT > 600) $nivelKey = 'critica';
elseif ($mediaDiariaT > 480) $nivelKey = 'excessiva';
elseif ($mediaDiariaT > 360) $nivelKey = 'razoavel';

$diag = $escalas[$nivelKey];

// --- ANÁLISE PREMIUM (Turnos, Janelas e Consistência) ---
$turnos = ['Manhã (07-13h)' => 0, 'Tarde (13-19h)' => 0, 'Noite (19-00h)' => 0];
$janelasLazer = 0;
$payloads = [];

foreach ($diasLabels as $d => $label) {
    $eventosDia = array_filter($eventos, fn($e) => $e['dia_semana'] == $d);
    usort($eventosDia, fn($a, $b) => strcmp($a['horario_inicio'], $b['horario_inicio']));
    
    $totalDia = 0;
    $lastEnd = '08:00:00';
    
    foreach ($eventosDia as $ev) {
        $ini = $ev['horario_inicio'];
        $fim = $ev['horario_fim'];
        
        // Janelas de Lazer (entre 08h e 20h)
        $t1 = strtotime($ini);
        $t0 = strtotime($lastEnd);
        if ($t1 > $t0 && $t1 <= strtotime('20:00:00')) {
            if (($t1 - $t0) >= 3600) $janelasLazer++;
        }
        $lastEnd = max($lastEnd, $fim);

        // Turnos
        $hIni = (int)explode(':', $ini)[0];
        $dur  = (strtotime($fim) - strtotime($ini)) / 60;
        $totalDia += $dur;

        if ($hIni < 13) $turnos['Manhã (07-13h)'] += $dur;
        elseif ($hIni < 19) $turnos['Tarde (13-19h)'] += $dur;
        else $turnos['Noite (19-00h)'] += $dur;
    }
    if ($totalDia > 0) $payloads[] = $totalDia;
}

arsort($turnos);
$turnoPredom = key($turnos);
$consistencia = count($payloads) > 1 ? (max($payloads) - min($payloads)) : 0;
$consistenciaLabel = ($consistencia < 120) ? 'Estável' : (($consistencia < 240) ? 'Variável' : 'Irregular');

// --- CONFIGURAÇÃO DA GRADE ---
$minHour = 7; // Padrão Inicial
$maxHour = 22; // Padrão Final

if (!empty($eventos)) {
    $firstClass = 23;
    $lastClass  = 0;
    foreach ($eventos as $a) {
        $start = (int)explode(':', $a['horario_inicio'])[0];
        $end   = (int)explode(':', $a['horario_fim'])[0];
        if ($start < $firstClass) $firstClass = $start;
        if ($end > $lastClass) $lastClass = $end;
    }
    // Add 1 hour buffer for better visuals
    $minHour = max(0, $firstClass - 1);
    $maxHour = min(23, $lastClass + 1);
    
    // Ensure a minimum height (e.g. at least 4 hours)
    if (($maxHour - $minHour) < 4) {
        $maxHour = min(23, $minHour + 4);
    }
}

// 3. Preparar Estrutura da Grade
$diasLabels = [
    1 => 'Segunda',
    2 => 'Terça',
    3 => 'Quarta',
    4 => 'Quinta',
    5 => 'Sexta',
    6 => 'Sábado'
];

// Definir os limites de tempo dinâmicos
$horaInicioGlobal = $minHour;
$horaFimGlobal    = $maxHour;
$totalMinutos     = ($horaFimGlobal - $horaInicioGlobal) * 60;

/**
 * Converte HH:MM para o índice da linha na Grid (Minute-Precision)
 */
function timeToRowIndex($time, $startHour) {
    if (!$time) return 0;
    $parts = explode(':', $time);
    $h = (int)$parts[0];
    $m = (int)$parts[1];
    
    // (Hora atual - Hora inicial) * 60 + minutos + 1 (header row) + 1 (1-indexed grid)
    $index = (($h - $startHour) * 60) + $m + 2; 
    return (int)$index;
}
?>

<div class="schedule-grid-wrap" data-student-photo="<?= htmlspecialchars($alunoPhoto ?? '') ?>">
    
    <!-- Tab Navigation -->
    <div class="modal-tabs-on-grid">
        <button class="tab-btn active" data-tab="grade" onclick="switchStudentTab(this, 'grade')">
            <span>🗓️</span> Grade
        </button>
        <button class="tab-btn" data-tab="analise" onclick="switchStudentTab(this, 'analise')">
            <span>📊</span> Análise
        </button>
        <button class="tab-btn" data-tab="info" onclick="switchStudentTab(this, 'info')">
            <span>ℹ️</span> Informações
        </button>
        <?php if ($canActivities): ?>
        <button class="tab-btn" data-tab="atividades" onclick="switchStudentTab(this, 'atividades')">
            <span>📝</span> Atividades
        </button>
        <?php endif; ?>
        <?php if ($canConfig): ?>
        <button class="tab-btn" data-tab="confs" onclick="switchStudentTab(this, 'confs')">
            <span>⚙️</span> Configurações
        </button>
        <?php endif; ?>
    </div>

    <!-- Tab Content: Grade -->
    <div id="tab-grade" class="tab-content-pane active">
        <div class="schedule-container">
            <div class="schedule-grid">
                
                <!-- Canto superior esquerdo vago -->
                <div class="grid-header-corner"></div>
                
                <!-- Dias da Semana (Top Header) -->
                <?php foreach ($diasLabels as $d => $label): ?>
                    <div class="grid-day-header" style="grid-column: <?= $d + 1 ?>; grid-row: 1;">
                        <?= $label ?>
                    </div>
                <?php endforeach; ?>

                <!-- Linhas de Horário (Left Labels) -->
                <?php for ($h = $horaInicioGlobal; $h < $horaFimGlobal; $h++): ?>
                    <?php $rowIndex = timeToRowIndex($h.":00", $horaInicioGlobal); ?>
                    <div class="grid-time-label" style="grid-row: <?= $rowIndex ?> / span 60; grid-column: 1; font-size: 0.6875rem;">
                        <?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00
                    </div>
                <?php endfor; ?>

                <!-- Eventos (Aulas e Extras) -->
                <?php foreach ($eventos as $aula): ?>
                    <?php 
                        $rowStart = timeToRowIndex($aula['horario_inicio'], $horaInicioGlobal);
                        $rowEnd   = timeToRowIndex($aula['horario_fim'], $horaInicioGlobal);
                        $diaCol   = $aula['dia_semana'] + 1;
                        
                        // Gerar cor baseada no tipo e nome
                        $isExtra   = ($aula['tipo'] === 'extra');
                        $colorSeed = md5($aula['disciplina_nome'] . ($isExtra ? 'extra' : 'aula'));
                        $hue       = hexdec(substr($colorSeed, 0, 2)) % 360;
                    ?>
                    <div class="grid-item-aula <?= $isExtra ? 'is-extra' : '' ?>" 
                         style="grid-column: <?= $diaCol ?>; grid-row: <?= $rowStart ?> / <?= $rowEnd ?>; --item-hue: <?= $hue ?>;">
                        <div class="aula-content">
                            <div class="aula-title" title="<?= htmlspecialchars($aula['disciplina_nome']) ?>">
                                <?= $isExtra ? '<span class="extra-badge">EXTRA</span> ' : '' ?>
                                <?= htmlspecialchars($aula['disciplina_nome']) ?>
                            </div>
                            <div class="aula-meta">
                                <span class="aula-time">🕒 <?= substr($aula['horario_inicio'], 0, 5) ?> - <?= substr($aula['horario_fim'], 0, 5) ?></span>
                                <?php if ($aula['local']): ?>
                                    <span class="aula-local">📍 <?= htmlspecialchars($aula['local']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <?php if ($canActivities): ?>
    <!-- Tab Content: Atividades -->
    <div id="tab-atividades" class="tab-content-pane" style="display:none;">
        <div class="activities-manager">
            <div class="activities-header">
                <button class="btn btn-primary btn-sm" onclick="toggleActivityForm()">+ Adicionar</button>
            </div>

            <!-- Form: Nova/Editar Atividade -->
            <div id="activityFormContainer" style="display:none; background:var(--bg-surface-2nd); padding:1.25rem; border-radius:var(--radius-lg); border:1px solid var(--border-color); margin-bottom:1.5rem;">
                <form id="activityForm" onsubmit="saveActivity(event)">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="act_id" value="">
                    <input type="hidden" name="aluno_id" value="<?= $alunoId ?>">
                    
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:0.75rem; margin-bottom:0.75rem;">
                        <div class="form-group">
                            <label class="form-label">Título da Atividade</label>
                            <input type="text" name="titulo" id="act_titulo" class="form-control" placeholder="Ex: Natação, Inglês..." required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Dia da Semana</label>
                            <select name="dia_semana" id="act_dia" class="form-control" required>
                                <?php foreach ($diasLabels as $val => $label): ?>
                                    <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap:0.75rem; margin-bottom:0.75rem;">
                        <div class="form-group">
                            <label class="form-label">Início</label>
                            <input type="time" name="horario_inicio" id="act_inicio" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Término</label>
                            <input type="time" name="horario_fim" id="act_fim" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Data Inicial</label>
                            <input type="date" name="data_inicio" id="act_data_ini" class="form-control" placeholder="Indeterminado">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Data Final</label>
                            <input type="date" name="data_fim" id="act_data_fim" class="form-control" placeholder="Indeterminado">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Local</label>
                        <input type="text" name="local" id="act_local" class="form-control" placeholder="Opcional">
                    </div>

                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" id="act_desc" class="form-control" rows="2"></textarea>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                        <button type="button" class="btn btn-ghost btn-sm" onclick="toggleActivityForm()">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm">💾 Salvar Atividade</button>
                    </div>
                </form>
            </div>

            <div id="activitiesList" class="activities-list">
                <?php if (empty($atividades)): ?>
                    <div style="text-align:center; padding:3rem; color:var(--text-muted);">
                        <div style="font-size:2.5rem; margin-bottom:0.5rem; opacity:0.3;">📝</div>
                        <p>Nenhuma atividade extracurricular cadastrada.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($atividades as $act): ?>
                        <div class="activity-card" data-act='<?= json_encode($act) ?>'>
                            <div class="activity-info">
                                <div class="activity-title"><?= htmlspecialchars($act['titulo']) ?></div>
                                <div class="activity-meta">
                                    <div style="display:flex; gap:0.75rem; margin-bottom:2px;">
                                        <span>🗓️ <?= $diasLabels[$act['dia_semana']] ?></span>
                                        <span>🕒 <?= substr($act['horario_inicio'], 0, 5) ?> - <?= substr($act['horario_fim'], 0, 5) ?></span>
                                    </div>
                                    <div style="font-size:0.6875rem; color:var(--text-muted); display:flex; align-items:center; gap:0.375rem;">
                                        <span>📅 Período:</span>
                                        <?php if ($act['data_inicio'] || $act['data_fim']): ?>
                                            <span><?= $act['data_inicio'] ? date('d/m/y', strtotime($act['data_inicio'])) : '...' ?></span>
                                            <span>até</span>
                                            <span><?= $act['data_fim'] ? date('d/m/y', strtotime($act['data_fim'])) : 'Indeterminado' ?></span>
                                        <?php else: ?>
                                            <span>Indeterminado</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($act['local']): ?><div style="margin-top:2px;">📍 <?= htmlspecialchars($act['local']) ?></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="activity-actions">
                                <button onclick="editActivity(this)" class="btn-icon" title="Editar">✏️</button>
                                <button onclick="deleteActivity(<?= $act['id'] ?>)" class="btn-icon danger" title="Excluir">🗑️</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab Content: Análise -->
    <div id="tab-analise" class="tab-content-pane" style="display:none;">
        <div class="analysis-dashboard">
            
            <!-- Diagnóstico Principal (Header) -->
            <div class="diag-header-compact" style="border-left-color: <?= $diag['color'] ?>; background: <?= $diag['bg'] ?>;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:1.125rem; font-weight:800; color:<?= $diag['color'] ?>;">
                        <span style="font-size:0.625rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; display:block; letter-spacing:0.1em; margin-bottom:2px;">Status de Esforço</span>
                        <?= $diag['label'] ?>
                    </div>
                    <div class="stat-badge-mini" style="background:#fff; border:1px solid <?= $diag['color'] ?>70; color:<?= $diag['color'] ?>;">
                        <?= round($mediaDiariaT / 60, 1) ?>h/dia
                    </div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="analysis-grid">
                
                <!-- Coluna Esquerda: Carga e Saúde -->
                <div class="analysis-col">
                    <div class="premium-card">
                        <div class="card-title-mini">Neurologia do Aprendizado</div>
                        <p style="font-size:0.75rem; color:var(--text-primary); font-weight:700; margin:0.5rem 0 0.25rem 0;"><?= $diag['hint'] ?></p>
                        <p style="font-size:0.6875rem; color:var(--text-secondary); line-height:1.4; margin:0;"><?= $diag['msg'] ?></p>
                    </div>

                    <div class="premium-card" style="margin-top:0.75rem;">
                        <div class="card-title-mini">Carga Diária (Evolução)</div>
                        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.75rem;">
                            <?php 
                            $maxDaily = max(max($stats['daily']), 1);
                            foreach ($diasLabels as $d => $label): 
                                $pct = ($stats['daily'][$d] / $maxDaily) * 100;
                                $isHeavy = ($stats['daily'][$d] === max($stats['daily']) && max($stats['daily']) > 0);
                            ?>
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <span style="width:28px; font-size:0.625rem; font-weight:800; color:var(--text-muted);"><?= substr($label, 0, 3) ?></span>
                                    <div style="flex:1; height:4px; background:var(--bg-surface-2nd); border-radius:2px; overflow:hidden;">
                                        <div style="height:100%; width:<?= $pct ?>%; background:<?= $isHeavy ? 'var(--color-primary)' : 'var(--text-secondary)' ?>; opacity:0.8;"></div>
                                    </div>
                                    <span style="width:35px; font-size:0.625rem; font-weight:700; color:var(--text-secondary); text-align:right;"><?= round($stats['daily'][$d]/60,1) ?>h</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Coluna Direita: Estilo de Vida e Gaps -->
                <div class="analysis-col">
                    <div class="metric-grid-mini">
                        <div class="mini-metric-item">
                            <span class="mini-metric-lbl">Total Semanal</span>
                            <span class="mini-metric-val"><?= formatMinutos($totalMinutosT) ?></span>
                        </div>
                        <div class="mini-metric-item">
                            <span class="mini-metric-lbl">Turno Predom.</span>
                            <span class="mini-metric-val" style="font-size:0.75rem;"><?= explode(' ', $turnoPredom)[0] ?></span>
                        </div>
                        <div class="mini-metric-item">
                            <span class="mini-metric-lbl">Janelas Lazer</span>
                            <span class="mini-metric-val"><?= $janelasLazer ?></span>
                        </div>
                        <div class="mini-metric-item">
                            <span class="mini-metric-lbl">Consistência</span>
                            <span class="mini-metric-val" style="color:<?= $consistencia < 150 ? '#10b981':'#f59e0b' ?>"><?= $consistenciaLabel ?></span>
                        </div>
                    </div>
                    
                    <div class="premium-card" style="margin-top:0.75rem;">
                        <div class="card-title-mini">Balanço de Atividades</div>
                        <div style="margin-top:0.75rem;">
                            <div style="display:flex; justify-content:space-between; font-size:0.625rem; font-weight:700; margin-bottom:4px;">
                                <span style="color:var(--color-primary);">Acadêmico: <?= round(($stats['academic_min']/max($totalMinutosT,1))*100) ?>%</span>
                                <span style="color:var(--text-muted);">Extra: <?= round(($stats['extra_min']/max($totalMinutosT,1))*100) ?>%</span>
                            </div>
                            <div style="height:6px; background:var(--bg-surface-2nd); border-radius:3px; display:flex; overflow:hidden;">
                                <div style="height:100%; width:<?= ($stats['academic_min']/max($totalMinutosT,1))*100 ?>%; background:var(--color-primary);"></div>
                                <div style="height:100%; width:<?= ($stats['extra_min']/max($totalMinutosT,1))*100 ?>%; background:var(--text-muted); opacity:0.4;"></div>
                            </div>
                        </div>
                        <div style="margin-top:0.75rem; padding-top:0.5rem; border-top:1px solid var(--border-color); font-size:0.625rem; color:var(--text-muted); line-height:1.3;">
                            Insights: <?= $janelasLazer > 4 ? "Ótima distribuição de pausas." : "Considere adicionar pequenas pausas cognitivas." ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Tab Content: Informações (Nota Técnica) -->
    <div id="tab-info" class="tab-content-pane" style="display:none;">
        <div class="technical-note">
            <div class="note-header" style="text-align:center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--border-color);">
                <span style="font-size:0.625rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.12em; display:block; margin-bottom:4px;">Documento de Referência</span>
                <h3 style="margin:0; font-size:1.125rem; color:var(--text-primary);">Referencial de Saúde e Desempenho na Carga Horária Escolar</h3>
            </div>

            <div class="note-section">
                <h6>1. Fundamentação Teórica</h6>
                <p>A definição de uma carga horária "sadia" baseia-se no equilíbrio entre o tempo de instrução, a janela de sono e o tempo de recuperação cognitiva. Segundo a Organização Mundial da Saúde (OMS) e a Academia Americana de Pediatria, o desenvolvimento cerebral na adolescência exige, obrigatoriamente, entre <strong>8 e 10 horas de sono</strong> para a consolidação da memória de longo prazo.</p>
                <p>Ademais, dados da OCDE (PISA) demonstram que o aumento linear das horas de estudo não resulta em ganho proporcional de aprendizagem após o ponto de saturação cognitiva, fenômeno conhecido na neuropsicologia como <em>fadiga de decisão</em> e <em>esgotamento atencional</em>.</p>
            </div>

            <div class="note-section" style="margin-top:1.5rem;">
                <h6>2. Matriz de Classificação de Esforço Acadêmico</h6>
                <p>Para fins de análise diagnóstica, estabelece-se a seguinte escala de dedicação integral (considerando horas-aula presenciais somadas ao tempo estimado de atividades extracurriculares e tarefas):</p>
                
                <div class="zone-grid">
                    <div class="zone-item" style="border-left-color:#10b981;">
                        <strong>Zona de Eficiência (Ideal): Até 30h semanais</strong>
                        <span>Impacto: Máxima plasticidade neural e retenção de conteúdo. Permite o desenvolvimento socioemocional e a prática de atividades físicas essenciais.</span>
                    </div>
                    <div class="zone-item" style="border-left-color:#06b6d4;">
                        <strong>Zona de Sustentabilidade (Razoável): 31h a 40h semanais</strong>
                        <span>Impacto: Padrão compatível com regimes de tempo integral, desde que haja diversificação de estímulos (atividades práticas vs. teóricas). Exige monitoramento de estresse.</span>
                    </div>
                    <div class="zone-item" style="border-left-color:#f59e0b;">
                        <strong>Zona de Risco (Excessiva): 41h a 50h semanais</strong>
                        <span>Impacto: Início da curva de rendimentos decrescentes. Sinais frequentes de sonolência diurna, irritabilidade e redução da capacidade de resolução de problemas complexos.</span>
                    </div>
                    <div class="zone-item" style="border-left-color:#ef4444;">
                        <strong>Zona de Exaustão: Acima de 50h semanais</strong>
                        <span>Impacto: Risco elevado de Burnout estudantil, transtornos de ansiedade e privação crônica de sono. O aprendizado torna-se puramente mecânico.</span>
                    </div>
                </div>
            </div>

            <div class="note-section" style="margin-top:1.5rem;">
                <h6>3. Diretrizes para uma Rotina Saudável</h6>
                <p>Para que qualquer carga horária seja considerada sadia, ela deve respeitar os seguintes critérios qualitativos:</p>
                <ul class="note-list">
                    <li><strong>Higiene do Sono:</strong> A jornada escolar não deve impedir o repouso mínimo de 8 horas.</li>
                    <li><strong>Intervalos de Descompressão:</strong> Pausas ativas a cada 50-90 minutos de esforço concentrado.</li>
                    <li><strong>Proporção de Esforço:</strong> Equilíbrio entre a recepção passiva de conteúdo (aulas) e a produção ativa (projetos e estudos autônomos).</li>
                </ul>
            </div>
            
            <div style="margin-top:2rem; font-size:0.625rem; color:var(--text-muted); font-style:italic; text-align:center;">
                * Baseado em estudos de Neurociência Cognitiva e recomendações da OMS para o desenvolvimento infanto-juvenil.
            </div>
        </div>
    </div>

    <?php if ($canConfig): ?>
    <!-- Tab Content: Configurações -->
    <div id="tab-confs" class="tab-content-pane" style="display:none;">
        <div class="config-manager">
            <div class="config-header" style="margin-bottom: 1.5rem;">
                <h4 style="margin:0; font-size:1rem; color:var(--text-primary);">⚙️ Configuração de Grupos</h4>
                <p style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Selecione os grupos aos quais o aluno pertence nas disciplinas divididas.</p>
            </div>
            
            <form id="groupConfigForm" onsubmit="saveGroupConfig(event)">
                <?= csrf_field() ?>
                <input type="hidden" name="aluno_id" value="<?= $alunoId ?>">
                <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                
                <div class="config-list-container">
                    <?php if (empty($sessoesGrupo)): ?>
                        <div style="text-align:center; padding:3rem; color:var(--text-muted);">
                            <div style="font-size:2.5rem; margin-bottom:0.5rem; opacity:0.3;">⚙️</div>
                            <p>Esta turma não possui disciplinas divididas por grupo.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $currentDisc = '';
                        foreach ($sessoesGrupo as $sessao): 
                            if ($currentDisc !== $sessao['disciplina_nome']):
                                $currentDisc = $sessao['disciplina_nome'];
                                echo '<h5 class="disc-group-title">' . htmlspecialchars($currentDisc) . '</h5>';
                            endif;
                        ?>
                            <div class="config-row-item">
                                <div class="config-row-info">
                                    <div class="config-row-day"><?= $diasLabels[$sessao['dia_semana']] ?></div>
                                    <div class="config-row-time">🕒 <?= substr($sessao['horario_inicio'], 0, 5) ?> - <?= substr($sessao['horario_fim'], 0, 5) ?></div>
                                    <div class="config-row-ocup">📍 <?= $sessao['ocupacao'] ?></div>
                                </div>
                                <div class="config-row-action">
                                    <label class="custom-chk-container">
                                        <input type="checkbox" name="groups[<?= $sessao['id'] ?>]" value="<?= $sessao['ocupacao'] ?>" <?= ($sessao['grupo_atribuido'] === $sessao['ocupacao']) ? 'checked' : '' ?>>
                                        <span class="custom-chk-mark"></span>
                                        <span class="custom-chk-label">Vincular Aluno</span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($sessoesGrupo)): ?>
                    <div style="margin-top:2rem; display:flex; justify-content:flex-end; border-top:1px solid var(--border-color); padding-top:1rem;">
                        <button type="submit" class="btn btn-primary">💾 Salvar Configurações</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
/**
 * Tab Switching Logic
 */
function switchStudentTab(btn, tabId) {
    const container = btn.closest('.schedule-grid-wrap');
    if (!container) return;

    // Toggle Buttons
    container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Toggle Panes
    container.querySelectorAll('.tab-content-pane').forEach(p => p.style.display = 'none');
    const target = container.querySelector('#tab-' + tabId);
    if (target) {
        target.style.display = 'flex';
        target.style.flexDirection = 'column';
        target.style.flex = '1';
    }
}

/**
 * Activity CRUD Logic
 */
function toggleActivityForm() {
    const container = document.getElementById('activityFormContainer');
    const form = document.getElementById('activityForm');
    if (container.style.display === 'none') {
        form.reset();
        document.getElementById('act_id').value = '';
        container.style.display = 'block';
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        container.style.display = 'none';
    }
}

async function saveActivity(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const alunoId = formData.get('aluno_id');
    
    try {
        const resp = await fetch('aulas/student_activities_ajax.php?action=save', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const res = await resp.json();
        
        if (res.success) {
            Toast.show(res.message, 'success');
            // Recarregar mantendo o contexto
            const container = document.querySelector('.schedule-grid-wrap');
            const photo     = container?.dataset.studentPhoto || '';
            const name      = document.querySelector('.modal-title strong, .modal-title div')?.innerText || 'Aluno';
            const activeTab = document.querySelector('.tab-btn.active')?.dataset.tab || 'atividades';
            
            openScheduleModal(alunoId, name, photo, activeTab);
        } else {
            Toast.show(res.message, 'danger');
        }
    } catch (err) {
        Toast.show('Erro ao salvar atividade.', 'danger');
    }
}

function editActivity(btn) {
    const card = btn.closest('.activity-card');
    const data = JSON.parse(card.dataset.act);
    
    document.getElementById('act_id').value = data.id;
    document.getElementById('act_titulo').value = data.titulo;
    document.getElementById('act_dia').value = data.dia_semana;
    document.getElementById('act_inicio').value = data.horario_inicio;
    document.getElementById('act_fim').value = data.horario_fim;
    document.getElementById('act_data_ini').value = data.data_inicio || '';
    document.getElementById('act_data_fim').value = data.data_fim || '';
    document.getElementById('act_local').value = data.local || '';
    document.getElementById('act_desc').value = data.descricao || '';
    
    const container = document.getElementById('activityFormContainer');
    container.style.display = 'block';
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function deleteActivity(id) {
    if (!confirm('Deseja realmente excluir esta atividade?')) return;
    
    const alunoId = <?= $alunoId ?>;
    const formData = new FormData();
    formData.append('id', id);
    formData.append('aluno_id', alunoId);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    
    try {
        const resp = await fetch('aulas/student_activities_ajax.php?action=delete', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const res = await resp.json();
        
        if (res.success) {
            Toast.show(res.message, 'success');
            const container = document.querySelector('.schedule-grid-wrap');
            const photo     = container?.dataset.studentPhoto || '';
            const name      = document.querySelector('.modal-title strong, .modal-title div')?.innerText || 'Aluno';
            const activeTab = document.querySelector('.tab-btn.active')?.dataset.tab || 'atividades';
            
            openScheduleModal(alunoId, name, photo, activeTab);
        } else {
            Toast.show(res.message, 'danger');
        }
    } catch (err) {
        Toast.show('Erro ao excluir atividade.', 'danger');
    }
}

async function saveGroupConfig(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    try {
        const resp = await fetch('aulas/student_groups_ajax.php?action=save', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const res = await resp.json();
        
        if (res.success) {
            Toast.show(res.message, 'success');
            // Recarregar para aplicar o filtro na grade
            const container = document.querySelector('.schedule-grid-wrap');
            const alunoId   = form.querySelector('[name="aluno_id"]').value;
            const photo     = container?.dataset.studentPhoto || '';
            const name      = document.querySelector('.modal-title strong, .modal-title div')?.innerText || 'Aluno';
            
            openScheduleModal(alunoId, name, photo, 'confs');
        } else {
            Toast.show(res.message, 'danger');
        }
    } catch (err) {
        Toast.show('Erro ao salvar configurações de grupo.', 'danger');
    }
}
</script>

<style>
.schedule-grid-wrap {
    height: 80vh; 
    display: flex;
    flex-direction: column;
    padding: 0; 
    box-sizing: border-box;
    background: var(--bg-surface);
    overflow: hidden; /* Scroll movido apenas para o interior das abas */
}

.modal-tabs-on-grid {
    display: flex;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-surface-2nd);
    padding: 0 1.5rem;
    flex-shrink: 0;
}

.tab-btn {
    background: none;
    border: none;
    padding: 0.875rem 1.25rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.tab-btn:hover {
    color: var(--text-secondary);
    background: var(--bg-surface);
}

.tab-btn.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
    background: var(--bg-surface);
}

.tab-content-pane {
    flex: 1;
    display: none;
    flex-direction: column;
    padding: 1.5rem;
    min-height: 0;
    overflow: hidden;
}

.tab-content-pane.active {
    display: flex;
}

/* Analysis Tab Styles */
.analysis-dashboard {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    height: 100%;
    overflow-y: auto;
    padding: 0.25rem;
}

/* Technical Note Styles */
.technical-note {
    background: var(--bg-surface);
    color: var(--text-secondary);
    line-height: 1.6;
    padding: 0.5rem;
    height: 100%;
    overflow-y: auto;
}

.technical-note h6 {
    color: var(--color-primary);
    font-size: 0.875rem;
    font-weight: 800;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.technical-note p {
    font-size: 0.8125rem;
    margin-bottom: 1rem;
}

.zone-grid {
    display: grid;
    gap: 0.75rem;
    margin-top: 1rem;
}

.zone-item {
    background: var(--bg-surface-2nd);
    border-left: 4px solid var(--border-color);
    padding: 0.75rem;
    border-radius: var(--radius-md);
}

.zone-item strong {
    display: block;
    font-size: 0.8125rem;
    color: var(--text-primary);
    margin-bottom: 2px;
}

.zone-item span {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.note-list {
    margin: 0;
    padding-left: 1.25rem;
    list-style: none;
}

.note-list li {
    font-size: 0.8125rem;
    position: relative;
    margin-bottom: 0.5rem;
}

.note-list li::before {
    content: '•';
    color: var(--color-primary);
    font-weight: bold;
    position: absolute;
    left: -1rem;
}

.diag-header-compact {
    border: 1px solid var(--border-color);
    border-left-width: 6px;
    border-radius: var(--radius-lg);
    padding: 0.875rem 1.125rem;
}

.analysis-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.analysis-col {
    display: flex;
    flex-direction: column;
}

.premium-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 0.875rem;
    flex: 1;
}

.card-title-mini {
    font-size: 0.625rem;
    font-weight: 800;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.metric-grid-mini {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.mini-metric-item {
    background: var(--bg-surface-2nd);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 0.625rem;
    display: flex;
    flex-direction: column;
}

.mini-metric-lbl {
    font-size: 0.5625rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
}

.mini-metric-val {
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--text-primary);
}

.stat-badge-mini {
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.6875rem;
    font-weight: 800;
}

/* Activities CRUD Styles */
.activities-manager {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    height: 100%;
}

.activities-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.activities-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    overflow-y: auto;
}

.activity-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

/* Config Tab Styles */
.config-manager {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    max-width: 800px;
    margin: 0 auto;
    width: 100%;
}

#groupConfigForm {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
}

.config-list-container {
    background: var(--bg-surface-2nd);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}

.disc-group-title {
    margin: 1.5rem 0 0.75rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--color-primary);
    color: var(--text-primary);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.disc-group-title:first-child {
    margin-top: 0;
}

.config-row-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    margin-bottom: 0.75rem;
    transition: all 0.2s;
}

.config-row-item:hover {
    border-color: var(--color-primary);
    box-shadow: var(--shadow-sm);
}

.config-row-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.config-row-day {
    font-weight: 700;
    font-size: 0.8125rem;
    width: 80px;
    color: var(--text-primary);
}

.config-row-time {
    font-size: 0.75rem;
    color: var(--text-secondary);
    background: var(--bg-surface-2nd);
    padding: 2px 8px;
    border-radius: 4px;
}

.config-row-ocup {
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--color-primary);
}

.custom-chk-container {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-secondary);
}

.custom-chk-container input {
    display: none;
}

.custom-chk-mark {
    width: 20px;
    height: 20px;
    border: 2px solid var(--border-color);
    border-radius: 6px;
    display: inline-block;
    position: relative;
    transition: all 0.2s;
}

.custom-chk-container input:checked + .custom-chk-mark {
    background: var(--color-primary);
    border-color: var(--color-primary);
}

.custom-chk-container input:checked + .custom-chk-mark:after {
    content: '✓';
    position: absolute;
    color: white;
    font-size: 14px;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.custom-chk-container:hover .custom-chk-mark {
    border-color: var(--color-primary);
}

.activity-card:hover {
    border-color: var(--color-primary);
    box-shadow: var(--shadow-sm);
}

.activity-title {
    font-weight: 700;
    font-size: 0.9375rem;
    color: var(--text-primary);
    margin-bottom: 0.375rem;
}

.activity-meta {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.activity-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.1rem;
    padding: 4px;
    border-radius: 4px;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    background: var(--bg-surface-2nd);
}

.btn-icon.danger:hover {
    background: #fee2e2;
}

/* Grid Integration Styles */
.grid-item-aula.is-extra {
    border-left-style: dashed;
    background: hsla(var(--item-hue), 80%, 97%, 0.85);
}

.extra-badge {
    font-size: 0.6rem;
    font-weight: 800;
    background: hsla(var(--item-hue), 70%, 50%, 0.15);
    color: hsla(var(--item-hue), 70%, 40%, 1);
    padding: 1px 4px;
    border-radius: 4px;
    margin-right: 4px;
    border: 1px solid hsla(var(--item-hue), 70%, 50%, 0.3);
}

.schedule-container {
    flex: 1;
    min-height: 0;
    overflow: auto;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-xl);
    background: var(--bg-surface-2nd);
}

.schedule-grid {
    display: grid;
    grid-template-columns: 80px repeat(6, 1fr);
    grid-template-rows: 50px repeat(<?= $totalMinutos ?>, 0.75px);
    position: relative;
    min-width: 900px; /* Garante que as colunas apareçam bem */
}

.grid-header-corner, .grid-day-header, .grid-time-label {
    background: var(--bg-surface);
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid var(--border-color);
    border-right: 1px solid var(--border-color);
}

.grid-header-corner {
    position: sticky;
    top: 0;
    left: 0;
    z-index: 30;
    background: var(--bg-surface-2nd);
}

.grid-day-header {
    font-weight: 800;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
    position: sticky;
    top: 0;
    z-index: 20;
    border-bottom: 2px solid var(--color-primary);
}

.grid-time-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    position: sticky;
    left: 0;
    z-index: 15;
    border-right: 1px solid var(--border-color);
}

.grid-item-aula {
    position: relative;
    background: hsla(var(--item-hue), 80%, 95%, 0.9);
    border-left: 4px solid hsla(var(--item-hue), 70%, 50%, 1);
    color: hsla(var(--item-hue), 80%, 20%, 1);
    margin: 1px;
    border-radius: 4px;
    padding: 0.375rem;
    font-size: 0.75rem;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
    z-index: 5;
    overflow: hidden;
    min-height: 20px; /* Altura mínima para aulas curtas */
}

.grid-item-aula:hover {
    transform: scale(1.02);
    z-index: 21;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.aula-content {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    height: 100%;
}

.aula-title {
    font-weight: 700;
    line-height: 1.1;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    word-break: break-all;
}

.aula-meta {
    font-size: 0.625rem;
    opacity: 0.85;
}

.aula-meta span { display: block; margin-top: 2px; }

/* Linhas discretas na área das aulas */
.schedule-grid::after {
    content: '';
    grid-column: 2 / -1;
    grid-row: 2 / -1;
    z-index: 1;
    pointer-events: none;
    /* Linhas discretas: vertical (dias) e horizontal (horários) */
    background-image: 
        linear-gradient(to right, var(--border-color) 1px, transparent 1px),
        linear-gradient(to bottom, var(--border-color) 1px, transparent 1px);
    background-size: calc(100% / 6) 45px; /* 45px = 60 minutos x 0.75px */
    opacity: 0.4;
}
</style>
