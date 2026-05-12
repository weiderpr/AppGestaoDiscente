<?php
/**
 * Vértice Acadêmico — Relato de Problema via QR Code
 * Página pública — não exige autenticação
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/Manutencao/ManutencaoService.php';
require_once __DIR__ . '/../src/App/Services/Manutencao/ManutencaoRelatoService.php';

$ambienteId = (int)($_GET['ambiente'] ?? 0);

$relatoService = new \App\Services\Manutencao\ManutencaoRelatoService();
$ambiente = null;

if ($ambienteId) {
    $ambiente = $relatoService->getAmbienteParaRelato($ambienteId);
}

$loggedUser = isLoggedIn() ? getCurrentUser() : null;
$success = false;

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST'] . str_replace('/manutencao/relatar.php', '', $_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Relatar Problema — Vértice Acadêmico</title>
    <meta name="description" content="Reporte um problema neste ambiente de forma rápida e simples.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #eef2ff;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #f1f5f9;
            --surface: #ffffff;
            --surface2: #f8fafc;
            --border: #e2e8f0;
            --text: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --radius: 12px;
            --radius-sm: 8px;
            --radius-lg: 20px;
            --shadow: 0 4px 24px rgba(0,0,0,0.08);
            --shadow-lg: 0 12px 48px rgba(0,0,0,0.14);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f172a;
                --surface: #1e293b;
                --surface2: #0f172a;
                --border: #334155;
                --text: #f1f5f9;
                --text-secondary: #94a3b8;
                --text-muted: #64748b;
                --primary-light: #1e1b4b;
            }
        }

        html, body {
            height: 100%;
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Header */
        .qr-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 1.5rem 1.25rem 3.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .qr-header::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 280px; height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
        }
        .qr-header::after {
            content: '';
            position: absolute;
            bottom: -60px; left: -40px;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }

        .header-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            position: relative;
            z-index: 1;
        }
        .brand-logo {
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; font-weight: 800; color: white;
        }
        .brand-name { color: rgba(255,255,255,0.9); font-size: 0.9rem; font-weight: 600; }

        .header-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.375rem;
            position: relative;
            z-index: 1;
        }
        .header-sub {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
        }

        /* Card principal */
        .main-card {
            background: var(--surface);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            margin-top: -2rem;
            position: relative;
            z-index: 2;
            flex: 1;
            padding: 2rem 1.5rem 3rem;
            box-shadow: var(--shadow-lg);
            max-width: 600px;
            width: 100%;
            margin-left: auto;
            margin-right: auto;
        }

        /* Ambiente Info Badge */
        .ambiente-badge {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--primary-light);
            border: 1px solid rgba(79,70,229,0.2);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            margin-bottom: 1.75rem;
        }
        .ambiente-icon {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(79,70,229,0.3);
        }
        .ambiente-info { flex: 1; }
        .ambiente-nome { font-weight: 700; color: var(--text); font-size: 1rem; }
        .ambiente-local { font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.125rem; }

        /* Section Headers */
        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 0.875rem;
        }

        /* Problemas Grid */
        .problemas-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.625rem;
            margin-bottom: 1.5rem;
        }
        .problema-card {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.875rem;
            border-radius: var(--radius-sm);
            border: 2px solid var(--border);
            background: var(--surface2);
            cursor: pointer;
            transition: all 0.15s ease;
            user-select: none;
        }
        .problema-card:active { transform: scale(0.97); }
        .problema-card.selected {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        .problema-card input[type="checkbox"] {
            width: 18px; height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
            flex-shrink: 0;
        }
        .problema-card span {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text);
            line-height: 1.3;
        }
        .problema-card.outros {
            border-color: rgba(79,70,229,0.35);
            background: var(--primary-light);
        }
        .problema-card.outros.selected {
            border-color: var(--primary);
        }

        /* Form */
        .form-group { margin-bottom: 1.25rem; }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: var(--surface2);
            color: var(--text);
            font-size: 0.9375rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.12);
        }
        .form-control::placeholder { color: var(--text-muted); }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .user-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.875rem;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            border-radius: 99px;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #14532d;
            margin-bottom: 1.25rem;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 1rem;
            border-radius: var(--radius-sm);
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            font-size: 1rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(79,70,229,0.4);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .btn-submit:active { transform: translateY(1px); box-shadow: 0 2px 8px rgba(79,70,229,0.3); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

        /* Error / Success States */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            margin-bottom: 1.25rem;
        }
        .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #14532d; }

        /* Success Screen */
        .success-screen {
            text-align: center;
            padding: 3rem 1rem;
        }
        .success-icon {
            width: 88px; height: 88px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 32px rgba(16,185,129,0.4);
        }
        .success-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; }
        .success-sub { color: var(--text-secondary); font-size: 0.9375rem; line-height: 1.6; }

        .separator {
            border: none;
            border-top: 1px solid var(--border);
            margin: 1.5rem 0;
        }

        /* Error Screen */
        .error-screen { text-align: center; padding: 4rem 2rem; }
        .error-icon { font-size: 4rem; margin-bottom: 1rem; }

        /* Footer */
        .qr-footer {
            text-align: center;
            padding: 1.5rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        @media (max-width: 480px) {
            .main-card { border-radius: 16px 16px 0 0; padding: 1.5rem 1.25rem 2.5rem; }
            .form-row { grid-template-columns: 1fr; }
            .problemas-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="qr-header">
    <div class="header-brand">
        <div class="brand-logo">V</div>
        <span class="brand-name">Vértice Acadêmico</span>
    </div>
    <h1 class="header-title">🛠️ Relatar Problema</h1>
    <p class="header-sub">Nos ajude a manter o ambiente em perfeitas condições</p>
</div>

<!-- Conteúdo -->
<div class="main-card">

<?php if (!$ambiente): ?>
    <!-- Ambiente Não Encontrado -->
    <div class="error-screen">
        <div class="error-icon">❌</div>
        <h2 style="font-weight:800;margin-bottom:0.5rem;">Ambiente não encontrado</h2>
        <p style="color:var(--text-secondary);">O QR Code utilizado não corresponde a nenhum ambiente ativo no sistema.</p>
    </div>

<?php elseif ($success): ?>
    <!-- Sucesso -->
    <div class="success-screen">
        <div class="success-icon">✅</div>
        <h2 class="success-title">Relato Enviado!</h2>
        <p class="success-sub">Obrigado por nos ajudar.<br>Sua solicitação foi registrada e será analisada em breve.</p>
    </div>

<?php else: ?>
    <!-- Formulário de Relato -->

    <!-- Identificação do Ambiente -->
    <div class="ambiente-badge">
        <div class="ambiente-icon">🏢</div>
        <div class="ambiente-info">
            <div class="ambiente-nome"><?= htmlspecialchars($ambiente['descricao']) ?></div>
            <div class="ambiente-local">📍 <?= htmlspecialchars($ambiente['predio_campus']) ?></div>
        </div>
    </div>

    <div id="formError" class="alert alert-danger" style="display:none;"></div>

    <form id="relatoForm">
        <input type="hidden" name="ambiente_id" value="<?= $ambienteId ?>">

        <!-- Problemas Padrão -->
        <?php if (!empty($ambiente['problemas'])): ?>
        <div class="section-label">Qual é o problema?</div>
        <div class="problemas-grid">
            <?php foreach ($ambiente['problemas'] as $p): ?>
            <label class="problema-card">
                <input type="checkbox" name="problemas[]" value="<?= $p['id'] ?>" onchange="toggleProblemaCard(this)">
                <span><?= htmlspecialchars($p['descricao']) ?></span>
            </label>
            <?php endforeach; ?>
            <label class="problema-card outros" onclick="toggleOutros(this)">
                <input type="checkbox" id="checkOutros" onchange="toggleOutrosField(this.checked)">
                <span style="font-weight:700;">✏️ Outro problema</span>
            </label>
        </div>
        <?php endif; ?>

        <!-- Outros Detalhes -->
        <div class="form-group" id="outrosGroup" style="display:none;">
            <label class="form-label">Descreva o problema</label>
            <textarea name="outros_detalhes" class="form-control" rows="3" id="outrosField"
                      placeholder="Descreva o que está acontecendo..."></textarea>
        </div>

        <!-- Descrição Geral -->
        <div class="form-group">
            <label class="form-label">Detalhes adicionais <span style="color:var(--text-muted);font-weight:400;">(opcional)</span></label>
            <textarea name="descricao" class="form-control" rows="3"
                      placeholder="Ex: O problema está no lado esquerdo, próximo à janela..."></textarea>
        </div>

        <!-- Comentário -->
        <div class="form-group">
            <label class="form-label">Comentário <span style="color:var(--text-muted);font-weight:400;">(opcional)</span></label>
            <textarea name="comentario" class="form-control" rows="2"
                      placeholder="Alguma informação extra que possa ajudar?"></textarea>
        </div>

        <hr class="separator">

        <!-- Identificação do Relator -->
        <?php if ($loggedUser): ?>
        <div class="user-pill">
            ✅ Identificado como <strong><?= htmlspecialchars($loggedUser['name']) ?></strong>
        </div>
        <?php else: ?>
        <div class="section-label">Sua identificação <span style="color:var(--text-muted);font-weight:400;">(opcional)</span></div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Nome</label>
                <input type="text" name="nome_relator" class="form-control" placeholder="Seu nome...">
            </div>
            <div class="form-group">
                <label class="form-label">E-mail</label>
                <input type="email" name="email_relator" class="form-control" placeholder="seu@email.com">
            </div>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn-submit" id="btnSubmit">
            <span id="btnText">📨 Enviar Relato</span>
            <span id="btnLoading" style="display:none;">Enviando...</span>
        </button>
    </form>
<?php endif; ?>

</div>

<script>
    const CONFIG_API_PATH = '<?= $baseUrl ?>/api/manutencao/relatar_ajax.php';
</script>

<div class="qr-footer">
    Powered by <strong>Vértice Acadêmico</strong> · <?= $ambiente['institution_name'] ?? 'Sistema de Gestão' ?>
</div>

<script>
function toggleProblemaCard(input) {
    input.closest('.problema-card').classList.toggle('selected', input.checked);
}

function toggleOutros(label) {
    const checkbox = label.querySelector('#checkOutros');
    checkbox.checked = !checkbox.checked;
    toggleOutrosField(checkbox.checked);
    label.classList.toggle('selected', checkbox.checked);
}

function toggleOutrosField(visible) {
    const group = document.getElementById('outrosGroup');
    const field = document.getElementById('outrosField');
    group.style.display = visible ? 'block' : 'none';
    if (visible) field.focus();
    else field.value = '';
}

document.getElementById('relatoForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const errorEl = document.getElementById('formError');
    errorEl.style.display = 'none';

    const btnText = document.getElementById('btnText');
    const btnLoading = document.getElementById('btnLoading');
    const btn = document.getElementById('btnSubmit');

    // Validação mínima
    const problemas = this.querySelectorAll('input[name="problemas[]"]:checked');
    const outros = this.querySelector('#checkOutros')?.checked;
    const descricao = this.querySelector('textarea[name="descricao"]')?.value?.trim();
    const outrosDetalhes = this.querySelector('textarea[name="outros_detalhes"]')?.value?.trim();

    if (problemas.length === 0 && !descricao && !outrosDetalhes) {
        errorEl.textContent = 'Por favor, selecione ao menos um problema ou descreva o ocorrido.';
        errorEl.style.display = 'block';
        return;
    }

    if (outros && !outrosDetalhes) {
        errorEl.textContent = 'Descreva o problema selecionado em "Outro problema".';
        errorEl.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btnText.style.display = 'none';
    btnLoading.style.display = 'inline';

    try {
        const formData = new FormData(this);
        const res = await fetch(CONFIG_API_PATH + '?action=submit', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            // Substitui o formulário pela tela de sucesso
            document.querySelector('.main-card').innerHTML = `
                <div class="success-screen">
                    <div class="success-icon">✅</div>
                    <h2 class="success-title">Relato Enviado!</h2>
                    <p class="success-sub">Obrigado por nos ajudar.<br>Sua solicitação foi registrada e será analisada em breve.</p>
                </div>
            `;
        } else {
            errorEl.textContent = data.message || 'Erro ao enviar o relato. Tente novamente.';
            errorEl.style.display = 'block';
            btn.disabled = false;
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
        }
    } catch (err) {
        console.error('Submit error:', err);
        errorEl.textContent = 'Ocorreu um erro ao processar sua solicitação. Por favor, tente novamente ou contate o administrador.';
        errorEl.style.display = 'block';
        btn.disabled = false;
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
    }
});
</script>
</body>
</html>
