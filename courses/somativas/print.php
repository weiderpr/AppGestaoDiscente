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
    $numDates <= 5  => 7,
    $numDates <= 8  => 6.5,
    $numDates <= 11 => 6,
    $numDates <= 14 => 5.5,
    $numDates <= 18 => 5,
    default         => 4.5,
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
            <div class="print-turma-header">
                <h3 class="print-turma-title">Turma: <?= htmlspecialchars($t['turma_desc']) ?></h3>
                <?php if (!empty($somativa['naapi_ambiente_id'])): ?>
                <span class="print-naapi-ref">NAAPI — Sala: <?= htmlspecialchars($somativa['naapi_ambiente_desc'] ?? '') ?> · +<?= (int)($somativa['naapi_tempo_extra_min'] ?? 60) ?> min</span>
                <?php endif; ?>
            </div>
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
                                <!-- Disciplina: apenas o nome -->
                                <div class="pcc-discipline">
                                    <?= htmlspecialchars($cellData['disciplina_nome'] ?? '—') ?>
                                </div>

                                <?php if ($cellData['tipo'] !== 'Normal'): ?>
                                <span class="pcc-badge"><?= $cellData['tipo'] ?></span>
                                <?php endif; ?>

                                <!-- Aplicador e Volante: uma linha cada, nome nunca espremido -->
                                <div class="pcc-professor-info">
                                    <?php if (!empty($cellData['aplicador_nome'])): ?>
                                    <div class="pcc-prof-row">
                                        <span class="pcc-label">A:</span>
                                        <span class="pcc-person-name"><?= htmlspecialchars($cellData['aplicador_nome']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($cellData['volante_nome'])): ?>
                                    <div class="pcc-prof-row">
                                        <span class="pcc-label">V:</span>
                                        <span class="pcc-person-name"><?= htmlspecialchars($cellData['volante_nome']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Sala própria da disciplina -->
                                <?php if (!empty($cellData['ambiente_desc'])): ?>
                                <div class="pcc-room">Sala: <?= htmlspecialchars($cellData['ambiente_desc']) ?></div>
                                <?php endif; ?>

                                <!-- NAAPI: badge N + aplicador numa linha (sala mostrada no cabeçalho da turma) -->
                                <?php
                                $naapiAtivo = !empty($somativa['naapi_ambiente_id']);
                                if ($naapiAtivo && $cellData['tipo'] === 'Normal'):
                                ?>
                                <div class="pcc-naapi-block">
                                    <span class="pcc-naapi-badge">N</span>
                                    <?php if (!empty($cellData['naapi_aplicador_nome'])): ?>
                                    <span class="pcc-label">A:</span>
                                    <span class="pcc-person-name"><?= htmlspecialchars($cellData['naapi_aplicador_nome']) ?></span>
                                    <?php else: ?>
                                    <span class="pcc-naapi-sem-ap">—</span>
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

    // Quando carregado dentro de um modal (iframe), oculta o botão "Voltar"
    if (window.self !== window.top) {
        const backBtn = document.querySelector('.grade-back-btn');
        if (backBtn) backBtn.style.display = 'none';
    }

    document.getElementById('select-font-size').addEventListener('change', e => {
        body.classList.remove('font-sm', 'font-md', 'font-lg');
        body.classList.add(e.target.value);
    });

    document.getElementById('toggle-professors').addEventListener('change', e => {
        body.classList.toggle('hide-professors', !e.target.checked);
    });

    document.getElementById('toggle-rooms').addEventListener('change', e => {
        body.classList.toggle('hide-rooms', !e.target.checked);
    });

    document.getElementById('toggle-naapi').addEventListener('change', e => {
        body.classList.toggle('hide-naapi', !e.target.checked);
    });

    // Corrige preview em branco após fechar o diálogo de impressão do navegador
    // (Firefox e alguns Chromium não restauram CSS de tela após @media print)
    window.addEventListener('afterprint', () => {
        document.querySelectorAll('.print-course-section').forEach(s => {
            s.style.display = 'none';
            void s.offsetWidth; // força reflow
            s.style.display  = '';
        });
    });
});

// Exporta PDF como screenshot pixel-perfeito de cada seção da pré-visualização
window.exportToPDF = async function() {
    if (typeof Loading !== 'undefined') Loading.show();
    try {
        await document.fonts.ready;

        const { jsPDF }  = window.jspdf;
        const sections   = Array.from(document.querySelectorAll('.print-course-section'));
        if (!sections.length) throw new Error('Sem conteúdo para exportar.');

        const filename = 'Grade-Somativa-<?= addslashes($somativa['nome']) ?>'.replace(/[^a-z0-9]/gi, '_') + '.pdf';

        const pdf   = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
        const pageW = pdf.internal.pageSize.getWidth();   // 210 mm
        const pageH = pdf.internal.pageSize.getHeight();  // 297 mm

        for (let i = 0; i < sections.length; i++) {
            if (i > 0) pdf.addPage();

            const canvas = await html2canvas(sections[i], {
                scale:           3,       // 3× para texto mais nítido no PDF
                useCORS:         true,
                logging:         false,
                backgroundColor: '#ffffff',
                onclone: (doc, el) => {
                    doc.documentElement.setAttribute('data-theme', 'light');
                    el.style.boxShadow    = 'none';
                    el.style.border       = 'none';
                    el.style.borderRadius = '0';
                    el.style.margin       = '0';

                    // Remove truncamento de nomes (a tela usa nowrap+overflow:hidden
                    // para economizar espaço; no PDF queremos nomes completos com quebra de linha)
                    const s = doc.createElement('style');
                    s.textContent = `
                        .pcc-prof-row    { overflow: visible !important; }
                        .pcc-person-name { white-space: normal !important; overflow: visible !important; text-overflow: unset !important; }
                        .pcc-room        { white-space: normal !important; overflow: visible !important; }
                        .pcc-naapi-block { overflow: visible !important; }
                    `;
                    doc.head.appendChild(s);
                }
            });

            // Escala para preencher largura total; reduz proporcionalmente se altura exceder a página.
            // r é em mm/px — produto garante dimensões em mm para o jsPDF.
            const rW = pageW / canvas.width;
            const rH = pageH / canvas.height;
            const r  = Math.min(rW, rH);   // usa o fator menor: nunca corta, nunca ultrapassa
            const dw = canvas.width  * r;   // mm — igual a pageW quando rW <= rH
            const dh = canvas.height * r;   // mm — igual a pageH quando rH <= rW

            pdf.addImage(
                canvas.toDataURL('image/jpeg', 0.98),
                'JPEG',
                (pageW - dw) / 2,  // centraliza horizontal (≈ 0 quando rW é o fator limitante)
                0,                 // ancora no topo
                dw,
                dh
            );
        }

        pdf.save(filename);
        if (typeof Toast !== 'undefined') Toast.success('PDF baixado com sucesso!');
    } catch (err) {
        console.error('Erro ao gerar PDF:', err);
        if (typeof Toast !== 'undefined') Toast.error('Erro ao gerar PDF.');
    } finally {
        if (typeof Loading !== 'undefined') Loading.hide();
    }
};
</script>

</body>
</html>
