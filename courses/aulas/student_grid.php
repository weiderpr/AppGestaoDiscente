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
$slotsPorHora     = 2; // Slots de 30 minutos

/**
 * Converte HH:MM para o índice da linha na Grid
 */
function timeToRowIndex($time, $startHour, $slotsPerHour) {
    $parts = explode(':', $time);
    $h = (int)$parts[0];
    $m = (int)$parts[1];
    
    // (Hora atual - Hora inicial) * slots_per_hour + (minutos/30) + 1 (header row)
    $index = (($h - $startHour) * $slotsPerHour) + floor($m / 30) + 2; 
    return (int)$index;
}
?>

<!-- Debug Marker -->
<div class="grid-ready-marker" style="display:none;">READY</div>

<div class="schedule-grid-wrap">
    
    <div style="padding:0 0 1rem; border-bottom:1px solid var(--border-color); margin-bottom:1rem; flex-shrink:0;">
        <h4 style="margin:0; font-size:1rem; color:var(--text-primary);"><?= htmlspecialchars($turmaInfo['turma_nome']) ?></h4>
        <p style="margin:2px 0 0; font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($turmaInfo['curso_nome']) ?></p>
    </div>

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
                <div class="grid-time-label" style="grid-row: <?= timeToRowIndex($h.":00", $horaInicioGlobal, $slotsPorHora) ?>; grid-column: 1;">
                    <?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00
                </div>
                <div class="grid-time-label" style="grid-row: <?= timeToRowIndex($h.":30", $horaInicioGlobal, $slotsPorHora) ?>; grid-column: 1; font-size: 0.65rem; opacity: 0.5;">
                    &nbsp;
                </div>
            <?php endfor; ?>

            <!-- Aulas -->
            <?php foreach ($aulas as $aula): ?>
                <?php 
                    $rowStart = timeToRowIndex($aula['horario_inicio'], $horaInicioGlobal, $slotsPorHora);
                    $rowEnd   = timeToRowIndex($aula['horario_fim'], $horaInicioGlobal, $slotsPorHora);
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

<style>
.schedule-grid-wrap {
    height: 100%;
    display: flex;
    flex-direction: column;
    padding: 1.5rem;
    box-sizing: border-box;
    background: var(--bg-surface);
}

.schedule-grid-header-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-shrink: 0;
}

.student-pill {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 1.25rem;
    background: var(--color-primary-light);
    color: var(--color-primary);
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.875rem;
}

.legend {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.8125rem;
    color: var(--text-muted);
}

.legend-item { display: flex; align-items: center; gap: 0.375rem; }
.legend-item .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--color-primary); }

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
    grid-template-rows: 50px repeat(<?= ($horaFimGlobal - $horaInicioGlobal) * $slotsPorHora ?>, 25px);
    background: var(--border-color);
    gap: 1px;
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
    margin: 2px;
    border-radius: 6px;
    padding: 0.5rem;
    font-size: 0.8125rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
    z-index: 5;
    overflow: hidden;
}

.grid-item-aula:hover {
    transform: scale(1.02);
    z-index: 21;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.aula-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    height: 100%;
}

.aula-title {
    font-weight: 800;
    line-height: 1.2;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.aula-meta {
    font-size: 0.6875rem;
    opacity: 0.8;
}

.aula-meta span { display: block; margin-top: 2px; }

/* Adicionar linhas de fundo para facilitar leitura */
.schedule-grid::after {
    content: '';
    grid-column: 1 / -1;
    grid-row: 1 / -1;
    z-index: 1;
    pointer-events: none;
    /* Grid de fundo */
    background-image: 
        linear-gradient(to right, var(--border-color) 1px, transparent 1px),
        linear-gradient(to bottom, var(--border-color) 1px, transparent 1px);
    background-size: calc(100% / 7) 25px;
}
</style>
