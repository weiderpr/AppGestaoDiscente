<?php
/**
 * Vértice Acadêmico — Impressão de QR Codes por Ambiente (2 por folha A4)
 * QR Codes gerados via API externa para máxima compatibilidade.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
hasDbPermission('manutencao.ambientes');

require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/Manutencao/AmbienteService.php';

$inst           = getCurrentInstitution();
$instId         = $inst['id'];
$ambienteService = new \App\Services\Manutencao\AmbienteService();

$ambienteId = (int)($_GET['ambiente_id'] ?? 0);
$isIframe = (int)($_GET['iframe'] ?? 0);
if ($ambienteId) {
    $amb = $ambienteService->findById($ambienteId);
    $ambientes = $amb ? [$amb] : [];
} else {
    $ambientes = $ambienteService->getAll($instId);
}

$baseUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
           . '://' . $_SERVER['HTTP_HOST'];
$instName = htmlspecialchars($inst['name'] ?? 'Escola');

// Agrupa de 2 em 2 (folhas A4)
$sheets = array_chunk($ambientes, 2);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Codes — Manutenção | Vértice Acadêmico</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }

        /* === Toolbar (apenas tela) === */
        .print-toolbar {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .toolbar-info h1 { font-size: 1rem; font-weight: 700; }
        .toolbar-info p  { font-size: 0.8125rem; opacity: 0.85; margin-top: 2px; }

        .btn-print {
            background: white;
            color: #4f46e5;
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .btn-print:hover { background: #eef2ff; transform: translateY(-1px); box-shadow: 0 6px 15px rgba(0,0,0,0.15); }
        .btn-print:active { transform: translateY(0); }

        .btn-back {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: background 0.15s;
        }
        .btn-back:hover { background: rgba(255,255,255,0.25); }
        .toolbar-actions { display: flex; gap: 0.75rem; align-items: center; }

        /* === Área das Folhas === */
        .pages-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2rem;
            padding: 2rem;
        }

        /* === Folha A4 === */
        .a4-sheet {
            width: 210mm;
            height: 297mm;
            background: white;
            box-shadow: 0 4px 24px rgba(0,0,0,0.12);
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* === Card — exatamente metade da A4 === */
        .qr-card {
            width: 100%;
            height: 148.5mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10mm 18mm;
            gap: 0;
            position: relative;
        }
        .qr-card:first-child {
            border-bottom: 2px dashed #cbd5e1;
        }

        /* === Cabeçalho === */
        .card-header-print { text-align: center; margin-bottom: 6mm; }
        .school-name {
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #64748b;
            margin-bottom: 2px;
        }
        .ambiente-title {
            font-size: 15pt;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }
        .ambiente-location {
            font-size: 8.5pt;
            color: #64748b;
            margin-top: 2px;
        }

        /* === QR Code === */
        .qr-wrapper {
            position: relative;
            margin: 5mm 0;
        }
        .qr-frame {
            width: 52mm;
            height: 52mm;
            border: 3px solid #4f46e5;
            border-radius: 14px;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            box-shadow: 0 4px 16px rgba(79,70,229,0.25);
        }
        .qr-frame img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }

        /* Cantos decorativos */
        .qr-corner {
            position: absolute;
            width: 10px; height: 10px;
            border-color: #4f46e5;
            border-style: solid;
        }
        .qr-corner.tl { top: -4px; left: -4px; border-width: 3px 0 0 3px; border-radius: 2px 0 0 0; }
        .qr-corner.tr { top: -4px; right: -4px; border-width: 3px 3px 0 0; border-radius: 0 2px 0 0; }
        .qr-corner.bl { bottom: -4px; left: -4px; border-width: 0 0 3px 3px; border-radius: 0 0 0 2px; }
        .qr-corner.br { bottom: -4px; right: -4px; border-width: 0 3px 3px 0; border-radius: 0 0 2px 0; }

        /* === CTA === */
        .cta-section { text-align: center; max-width: 130mm; }
        .cta-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: #eef2ff;
            color: #4f46e5;
            border: 1.5px solid #c7d2fe;
            border-radius: 99px;
            padding: 3px 12px;
            font-size: 7.5pt;
            font-weight: 700;
            margin-bottom: 3mm;
        }
        .cta-instruction {
            font-size: 11pt;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2mm;
            line-height: 1.3;
        }
        .cta-description {
            font-size: 7.5pt;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 4mm;
        }

        /* Passos */
        .steps { display: flex; gap: 8mm; justify-content: center; }
        .step  { display: flex; flex-direction: column; align-items: center; gap: 3px; max-width: 45px; }
        .step-num {
            width: 22px; height: 22px;
            background: #4f46e5;
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 7.5pt;
            font-weight: 800;
        }
        .step-text { font-size: 6.5pt; color: #475569; text-align: center; line-height: 1.3; font-weight: 500; }

        /* === Impressão === */
        @media print {
            @page { margin: 0; size: A4 portrait; }
            body { background: white; margin: 0; padding: 0; }
            .print-toolbar { display: none !important; }
            .pages-container { padding: 0; gap: 0; background: white; }

            .a4-sheet {
                width: 210mm;
                height: 297mm;
                box-shadow: none;
                border-radius: 0;
                page-break-after: always;
                break-after: page;
            }
            .a4-sheet:last-child {
                page-break-after: avoid;
                break-after: avoid;
            }
            .qr-card { height: 148.5mm; border-bottom: 1px dashed #ccc; }
        }
    </style>
</head>
<body>

<!-- Toolbar -->
<div class="print-toolbar">
    <div class="toolbar-info">
        <h1>📄 QR Codes para Impressão</h1>
        <p><?= count($ambientes) ?> ambiente(s) · 2 por folha A4 · <?= count($sheets) ?> folha(s)</p>
        <span style="font-size: 0.75rem; color: #fbbf24; font-weight: 600;">💡 Dica: Na tela de impressão, marque "Margens: Nenhuma" e desmarque "Cabeçalhos e Rodapés".</span>
    </div>
    <div class="toolbar-actions">
        <?php if (!$isIframe): ?>
            <a href="ambientes.php" class="btn-back">← Voltar</a>
        <?php endif; ?>
        <button type="button" class="btn-print" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
    </div>
</div>

<!-- Folhas -->
<div class="pages-container">
<?php foreach ($sheets as $sheetAmbientes): ?>
    <div class="a4-sheet">
        <?php foreach ($sheetAmbientes as $amb): ?>
        <div class="qr-card">
            <!-- Cabeçalho -->
            <div class="card-header-print">
                <div class="school-name"><?= $instName ?></div>
                <div class="ambiente-title">🏢 <?= htmlspecialchars($amb['descricao']) ?></div>
                <div class="ambiente-location">📍 <?= htmlspecialchars($amb['predio_campus']) ?></div>
            </div>

            <!-- QR Code -->
            <div class="qr-wrapper">
                <div class="qr-frame">
                    <?php 
                        $relatarUrl = $baseUrl . '/manutencao/relatar.php?ambiente=' . $amb['id'];
                        $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($relatarUrl);
                    ?>
                    <img src="<?= $qrApiUrl ?>" alt="QR Code — <?= htmlspecialchars($amb['descricao']) ?>">
                </div>
                <div class="qr-corner tl"></div>
                <div class="qr-corner tr"></div>
                <div class="qr-corner bl"></div>
                <div class="qr-corner br"></div>
            </div>

            <!-- CTA -->
            <div class="cta-section">
                <div class="cta-badge">📱 Leia o QR Code</div>
                <div class="cta-instruction">Encontrou um problema neste espaço?</div>
                <div class="cta-description">
                    Aponte a câmera do seu celular para o código acima e registre
                    o problema em segundos. Sua colaboração é fundamental para manter
                    este ambiente em boas condições!
                </div>
                <div class="steps">
                    <div class="step"><div class="step-num">1</div><div class="step-text">Abra a câmera</div></div>
                    <div class="step"><div class="step-num">2</div><div class="step-text">Aponte para o código</div></div>
                    <div class="step"><div class="step-num">3</div><div class="step-text">Selecione o problema</div></div>
                    <div class="step"><div class="step-num">4</div><div class="step-text">Toque em Enviar</div></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
</div>

</body>
</html>
