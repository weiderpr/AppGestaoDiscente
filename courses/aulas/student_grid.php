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
    $stAulas = $db->prepare('
        SELECT a.*, d.descricao as disciplina_nome, "aula" as tipo
        FROM gestao_turma_aulas a
        JOIN disciplinas d ON d.codigo = a.disciplina_codigo
        WHERE a.turma_id = ?
    ');
    $stAulas->execute([$turmaId]);
    $aulas = $stAulas->fetchAll();
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
        <button class="tab-btn" data-tab="atividades" onclick="switchStudentTab(this, 'atividades')">
            <span>📝</span> Atividades
        </button>
        <button class="tab-btn" data-tab="analise" onclick="switchStudentTab(this, 'analise')">
            <span>📊</span> Análise
        </button>
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

    <!-- Tab Content: Atividades -->
    <div id="tab-atividades" class="tab-content-pane" style="display:none;">
        <div class="activities-manager">
            <div class="activities-header">
                <h4 style="margin:0; font-size:1rem; color:var(--text-primary);">📍 Minhas Atividades Extras</h4>
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

    <!-- Tab Content: Análise -->
    <div id="tab-analise" class="tab-content-pane" style="display:none;">
        <div class="analysis-container">
            
            <!-- KPIs Compactos -->
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:0.75rem; margin-bottom:1rem;">
                <div class="stat-badge">
                    <div class="stat-badge-val"><?= formatMinutos($totalMinutosT) ?></div>
                    <div class="stat-badge-lbl">Total Semanal</div>
                </div>
                <div class="stat-badge">
                    <div class="stat-badge-val"><?= $totalMinutosT > 0 ? round(($stats['academic_min'] / $totalMinutosT) * 100) : 0 ?>%</div>
                    <div class="stat-badge-lbl">Acadêmico</div>
                </div>
                <div class="stat-badge">
                    <div class="stat-badge-val"><?= $totalMinutosT > 0 ? round(($stats['extra_min'] / $totalMinutosT) * 100) : 0 ?>%</div>
                    <div class="stat-badge-lbl">Extra</div>
                </div>
            </div>

            <!-- Diagnóstico de Esforço -->
            <div class="diagnostic-card" style="border-left-color: <?= $diag['color'] ?>; background: <?= $diag['bg'] ?>;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.5rem;">
                    <div>
                        <div style="font-size:0.6875rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">Diagnóstico Neuroeducacional</div>
                        <div style="font-size:1.125rem; font-weight:800; color:<?= $diag['color'] ?>;">Esforço: <?= $diag['label'] ?></div>
                    </div>
                    <div style="font-size:1.5rem;">🎓</div>
                </div>
                <div style="font-size:0.8125rem; font-weight:700; color:var(--text-primary); margin-bottom:0.25rem;"><?= $diag['hint'] ?></div>
                <div style="font-size:0.75rem; color:var(--text-secondary); line-height:1.4;"><?= $diag['msg'] ?></div>
            </div>

            <!-- Distribuição Diária Compacta -->
            <div class="daily-breakdown">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h5 style="font-size:0.75rem; font-weight:800; color:var(--text-muted); margin:0; text-transform:uppercase; letter-spacing:0.05em;">Carga Diária</h5>
                    <div style="font-size:0.6875rem; font-weight:700; color:var(--text-secondary); background:var(--bg-surface-2nd); padding:2px 6px; border-radius:4px;">Média: <?= formatMinutos($mediaDiariaT) ?></div>
                </div>
                <div style="display:flex; flex-direction:column; gap:0.625rem;">
                    <?php 
                    $maxDaily = max(max($stats['daily']), 1);
                    foreach ($diasLabels as $d => $label): 
                        $pct = ($stats['daily'][$d] / $maxDaily) * 100;
                        $isHeavy = ($stats['daily'][$d] === max($stats['daily']) && max($stats['daily']) > 0);
                    ?>
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div style="width:50px; font-size:0.6875rem; font-weight:700; color:<?= $isHeavy ? 'var(--color-primary)' : 'var(--text-muted)' ?>;"><?= substr($label, 0, 3) ?>.</div>
                            <div style="flex:1; height:6px; background:var(--bg-surface-2nd); border-radius:3px; overflow:hidden;">
                                <div style="height:100%; width:<?= $pct ?>%; background:<?= $isHeavy ? 'var(--color-primary)' : 'var(--text-secondary)' ?>; opacity:0.7; border-radius:3px;"></div>
                            </div>
                            <div style="width:50px; font-size:0.6875rem; font-weight:700; color:var(--text-secondary); text-align:right;">
                                <?= formatMinutos($stats['daily'][$d]) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>

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
.analysis-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    height: 100%;
    overflow-y: auto;
}

.stat-badge {
    background: var(--bg-surface-2nd);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 0.625rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.stat-badge-val {
    font-size: 1rem;
    font-weight: 800;
    color: var(--text-primary);
}

.stat-badge-lbl {
    font-size: 0.625rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
}

.diagnostic-card {
    border: 1px solid var(--border-color);
    border-left-width: 5px;
    border-radius: var(--radius-lg);
    padding: 1.125rem;
}

.daily-breakdown {
    background: var(--bg-surface-2nd);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 1rem;
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
