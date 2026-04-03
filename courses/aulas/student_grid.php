<?php
/**
 * Vértice Acadêmico — Grade Horária do Aluno (Visual)
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db     = getDB();
$inst   = getCurrentInstitution();
$instId = $inst['id'];

$alunoId = (int)($_GET['aluno_id'] ?? 0);
if (!$alunoId) {
    echo 'Erro: Aluno ID Inválido';
    exit;
}

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
    echo '<div style="padding:4rem; text-align:center; color:var(--text-muted);">';
    echo '<div style="font-size:3rem; margin-bottom:1rem;">🔍</div>';
    echo '<p>Não encontramos uma turma ativa vinculada a este aluno (ID: '.$alunoId.').</p>';
    echo '</div>';
    exit;
}

$turmaId = $turmaInfo['id'];

// 2. Buscar Aulas da Turma
$stAulas = $db->prepare('
    SELECT a.*, d.descricao as disciplina_nome
    FROM gestao_turma_aulas a
    JOIN disciplinas d ON d.codigo = a.disciplina_codigo
    WHERE a.turma_id = ? AND a.is_active = 1
    ORDER BY a.dia_semana, a.horario_inicio
');
$stAulas->execute([$turmaId]);
$aulas = $stAulas->fetchAll();

// Dynamic Time Range Calculation
$minHour = 7; // Default Lower Limit
$maxHour = 22; // Default Upper Limit

if (!empty($aulas)) {
    $firstClass = 23;
    $lastClass  = 0;
    foreach ($aulas as $a) {
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

<div class="schedule-grid-wrap">
    
    <!-- Tab Navigation -->
    <div class="modal-tabs-on-grid">
        <button class="tab-btn active" data-tab="grade" onclick="switchStudentTab(this, 'grade')">
            <span>🗓️</span> Grade
        </button>
        <button class="tab-btn" data-tab="atividades" onclick="switchStudentTab(this, 'atividades')">
            <span>📝</span> Atividades
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

                <!-- Aulas -->
                <?php foreach ($aulas as $aula): ?>
                    <?php 
                        $rowStart = timeToRowIndex($aula['horario_inicio'], $horaInicioGlobal);
                        $rowEnd   = timeToRowIndex($aula['horario_fim'], $horaInicioGlobal);
                        $diaCol   = $aula['dia_semana'] + 1;
                        
                        // Gerar cor baseada na disciplina (puro visual)
                        $colorSeed = md5($aula['disciplina_codigo']);
                        $hue = hexdec(substr($colorSeed, 0, 2)) % 360;
                    ?>
                    <div class="grid-item-aula" 
                         style="grid-column: <?= $diaCol ?>; grid-row: <?= $rowStart ?> / <?= $rowEnd ?>; --item-hue: <?= $hue ?>;">
                        <div class="aula-content">
                            <div class="aula-title" title="<?= htmlspecialchars($aula['disciplina_nome']) ?>">
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
        <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:3rem; color:var(--text-muted); background:var(--bg-surface-2nd); border:1px solid var(--border-color); border-radius:var(--radius-xl);">
            <div style="font-size:3rem; margin-bottom:1rem; opacity:0.5;">📝</div>
            <h4 style="margin:0; font-size:1.1rem; color:var(--text-primary);">Atividades e Tarefas</h4>
            <p style="margin:0.5rem 0 0; font-size:0.875rem;">Em breve: Acompanhamento de atividades e entregas do aluno.</p>
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
</script>

<style>
.schedule-grid-wrap {
    height: 80vh; /* Mantém o modal com tamanho fixo ao alternar abas */
    display: flex;
    flex-direction: column;
    padding: 0; /* Padding movido para as abas */
    box-sizing: border-box;
    background: var(--bg-surface);
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
