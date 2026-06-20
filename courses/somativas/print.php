<?php
/**
 * Vértice Acadêmico — Impressão da Grade Somativa
 * Visualização e impressão formatada da grade de horários agrupada por curso (Orientação: Retrato)
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
    echo "Nenhuma turma vinculada a esta somativa.";
    exit;
}
if (empty($somativa['slots'])) {
    echo "Nenhum slot de horário configurado para esta somativa.";
    exit;
}

$turmas     = $somativa['turmas'];
$slots      = $somativa['slots'];
$dates      = $service->getDatesInRange($somativa['data_inicio'], $somativa['data_fim']);

// Group turmas by course
$turmasPorCurso = [];
foreach ($turmas as $t) {
    $courseName = $t['course_name'] ?? 'Outros';
    $turmasPorCurso[$courseName][] = $t;
}

// Load grade data for all turmas
$gradeData  = [];
foreach ($turmas as $t) {
    $stId       = (int)$t['somativa_turma_id'];
    $grade      = $service->getGrade($id, $stId);
    $gradeIndex = [];
    foreach ($grade as $g) {
        $gradeIndex[$g['data_prova'] . '_' . $g['slot_config_id']] = $g;
    }
    $gradeData[$stId] = $gradeIndex;
}

// Calcula tamanho de fonte proporcional ao nº de datas (A4 landscape, slot col ≈ 17mm, restam ~264mm)
$numDates     = count($dates);
$printFontPt  = match(true) {
    $numDates <= 5  => 7.5,
    $numDates <= 8  => 7,
    $numDates <= 11 => 6.5,
    $numDates <= 14 => 6,
    $numDates <= 18 => 5.5,
    default         => 5,
};

?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $user['theme'] ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Grade — <?= htmlspecialchars($somativa['nome']) ?> — Vértice Acadêmico</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/components/toast.css">
    <link rel="stylesheet" href="/assets/css/components/loading.css">
    <link rel="stylesheet" href="/assets/css/somativas.css">

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/components/Toast.js"></script>
    <script src="/assets/js/components/Loading.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @media print {
            .print-table { font-size: <?= $printFontPt ?>pt !important; }
            .print-slot-time { font-size: <?= $printFontPt ?>pt !important; }
            .print-slot-label { font-size: <?= round($printFontPt - 1, 1) ?>pt !important; }
        }
    </style>
</head>
<body class="print-page-body font-md">

<!-- ════════════════════════════════════════════════════
     PAINEL DE CONTROLE (OCULTO NA IMPRESSÃO)
     ════════════════════════════════════════════════════ -->
<div class="print-control-panel no-print">
    <div class="pcp-left">
        <a href="grade.php?id=<?= $id ?>" class="grade-back-btn">
            ← Voltar para Grade
        </a>
        <h3 class="pcp-title">Impressão de Horários — <?= htmlspecialchars($somativa['nome']) ?></h3>
    </div>
    <div class="pcp-options">
        <div class="pcp-option-group">
            <label class="pcp-label" for="select-font-size">Tamanho:</label>
            <select id="select-font-size" class="form-control form-input-sm" style="width: auto;">
                <option value="font-sm">Compacto (Pequeno)</option>
                <option value="font-md" selected>Normal (Médio)</option>
                <option value="font-lg">Legível (Grande)</option>
            </select>
        </div>
        
        <label class="pcp-option-checkbox">
            <input type="checkbox" id="toggle-professors" checked>
            <span>Professores</span>
        </label>
        
        <label class="pcp-option-checkbox">
            <input type="checkbox" id="toggle-rooms" checked>
            <span>Salas</span>
        </label>

        <label class="pcp-option-checkbox">
            <input type="checkbox" id="toggle-naapi" checked>
            <span>NAAPI</span>
        </label>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="exportToPDF()">
            📄 Baixar PDF
        </button>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
            🖨️ Imprimir
        </button>
    </div>
</div>

<!-- ════════════════════════════════════════════════════
     CONTEÚDO DE IMPRESSÃO
     ════════════════════════════════════════════════════ -->
<div class="print-preview-container">
    
    <?php foreach ($turmasPorCurso as $courseName => $courseTurmas): ?>
    <div class="print-course-section">
        <h2 class="print-course-title">Curso: <?= htmlspecialchars($courseName) ?></h2>
        
        <?php foreach ($courseTurmas as $t): ?>
        <?php 
            $stId = (int)$t['somativa_turma_id'];
            $gData = $gradeData[$stId] ?? [];
        ?>
        <div class="print-turma-block">
            <h3 class="print-turma-title">Turma: <?= htmlspecialchars($t['turma_desc']) ?></h3>
            <table class="print-table">
                <thead>
                    <tr>
                        <th class="print-th-slot">Horário</th>
                        <?php foreach ($dates as $d): ?>
                        <th class="print-th-date">
                            <span class="print-date-dow"><?= $d['dia_semana_br'] ?></span>
                            <span class="print-date-label"><?= $d['label'] ?></span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($slots as $slot): ?>
                    <tr>
                        <td class="print-td-slot">
                            <span class="print-slot-time"><?= substr($slot['horario_inicio'],0,5) ?>–<?= substr($slot['horario_fim'],0,5) ?></span>
                            <?php if ($slot['label']): ?>
                            <span class="print-slot-label"><?= htmlspecialchars($slot['label']) ?></span>
                            <?php endif; ?>
                        </td>

                        <?php foreach ($dates as $d): ?>
                        <?php
                            $cellKey  = $d['data'] . '_' . $slot['id'];
                            $cellData = $gData[$cellKey] ?? null;
                            $hasCard  = !empty($cellData);
                        ?>
                        <td class="print-cell <?= $hasCard ? 'print-has-card' : 'print-empty-cell' ?>">
                            <?php if ($hasCard): ?>
                            <div class="print-card-content">
                                <div class="pcc-discipline">
                                    <span class="pcc-disc-name"><?= htmlspecialchars($cellData['disciplina_nome'] ?? '—') ?></span>
                                    <?php if (!empty($cellData['disciplina_codigo'])): ?>
                                    <span class="pcc-disc-code">(<?= htmlspecialchars($cellData['disciplina_codigo']) ?>)</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($cellData['tipo'] !== 'Normal'): ?>
                                <span class="pcc-badge"><?= $cellData['tipo'] ?></span>
                                <?php endif; ?>

                                <div class="pcc-professor-info">
                                    <?php if (!empty($cellData['aplicador_nome'])): ?>
                                    <div class="pcc-prof-row">
                                        <span class="pcc-label">A:</span> <?= htmlspecialchars($cellData['aplicador_nome']) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($cellData['volante_nome'])): ?>
                                    <div class="pcc-prof-row">
                                        <span class="pcc-label">V:</span> <?= htmlspecialchars($cellData['volante_nome']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($cellData['ambiente_desc'])): ?>
                                <div class="pcc-room">
                                    🏫 <?= htmlspecialchars($cellData['ambiente_desc']) ?>
                                </div>
                                <?php endif; ?>

                                <?php
                                $naapiAtivo = !empty($somativa['naapi_ambiente_id']);
                                if ($naapiAtivo && $cellData['tipo'] === 'Normal'):
                                ?>
                                <div class="pcc-naapi-block">
                                    <span class="pcc-naapi-badge">NAAPI</span>
                                    <?php if (!empty($cellData['naapi_aplicador_nome'])): ?>
                                    <div class="pcc-prof-row">
                                        <span class="pcc-label">A:</span> <?= htmlspecialchars($cellData['naapi_aplicador_nome']) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($somativa['naapi_ambiente_desc']): ?>
                                    <div class="pcc-room">
                                        🏫 <?= htmlspecialchars($somativa['naapi_ambiente_desc']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="print-empty-text">—</div>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    
    // Toggle Font Size
    const fontSizeSelect = document.getElementById('select-font-size');
    fontSizeSelect.addEventListener('change', (e) => {
        body.classList.remove('font-sm', 'font-md', 'font-lg');
        body.classList.add(e.target.value);
    });

    // Toggle Professors visibility
    const toggleProf = document.getElementById('toggle-professors');
    toggleProf.addEventListener('change', (e) => {
        body.classList.toggle('hide-professors', !e.target.checked);
    });

    // Toggle Rooms visibility
    const toggleRooms = document.getElementById('toggle-rooms');
    toggleRooms.addEventListener('change', (e) => {
        body.classList.toggle('hide-rooms', !e.target.checked);
    });

    // Toggle NAAPI block visibility
    const toggleNaapi = document.getElementById('toggle-naapi');
    toggleNaapi.addEventListener('change', (e) => {
        body.classList.toggle('hide-naapi', !e.target.checked);
    });
});

window.exportToPDF = function() {
    if (typeof Loading !== 'undefined') Loading.show();

    const htmlEl      = document.documentElement;
    const bodyEl      = document.body;
    const currentTheme = htmlEl.getAttribute('data-theme');
    const fontClass   = Array.from(bodyEl.classList).find(c => /^font-/.test(c)) || 'font-md';

    htmlEl.setAttribute('data-theme', 'light');

    const element  = document.querySelector('.print-preview-container');
    const filename = 'Grade-Somativa-' + '<?= addslashes($somativa['nome']) ?>'.replace(/[^a-z0-9]/gi, '_') + '.pdf';

    const restore = () => {
        htmlEl.setAttribute('data-theme', currentTheme);
        if (typeof Loading !== 'undefined') Loading.hide();
    };

    // Aguarda fontes carregarem antes de capturar
    document.fonts.ready.then(() => {
        const opt = {
            margin:    [8, 8, 8, 8],
            filename:  filename,
            image:     { type: 'jpeg', quality: 0.95 },
            html2canvas: {
                scale:           1.8,
                useCORS:         true,
                logging:         false,
                backgroundColor: '#ffffff',
                scrollX:         0,
                scrollY:         0,
                onclone: (doc) => {
                    // garante que o clone tem tema claro e a classe de fonte correta
                    doc.documentElement.setAttribute('data-theme', 'light');
                    doc.body.classList.remove('font-sm', 'font-md', 'font-lg');
                    doc.body.classList.add(fontClass);
                }
            },
            jsPDF:     { unit: 'mm', format: 'a4', orientation: 'landscape' },
            pagebreak: { mode: ['css', 'legacy'] }
        };

        html2pdf().set(opt).from(element).save()
            .then(() => {
                restore();
                if (typeof Toast !== 'undefined') Toast.success("PDF baixado com sucesso!");
            })
            .catch(err => {
                restore();
                if (typeof Toast !== 'undefined') Toast.error("Erro ao gerar PDF.");
                console.error(err);
            });
    });
};
</script>

</body>
</html>
