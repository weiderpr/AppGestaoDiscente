<?php
/**
 * Vértice Acadêmico — Seleção de Instituição
 * Exibida após o login quando o usuário tem vínculo com mais de uma instituição.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();

// Auditoria
require_once __DIR__ . '/src/App/Services/Service.php';
require_once __DIR__ . '/src/App/Services/UserService.php';
$userService = new \App\Services\UserService();


// Busca as instituições vinculadas ao usuário logado
$stmt = $db->prepare(
    'SELECT i.id, i.name, i.photo, i.cnpj, i.responsible, i.address
     FROM institutions i
     INNER JOIN user_institutions ui ON ui.institution_id = i.id
     WHERE ui.user_id = ? AND i.is_active = 1
     ORDER BY i.name ASC'
);
$stmt->execute([$_SESSION['user_id']]);
$institutions = $stmt->fetchAll();

// Se não houver nenhuma, vai direto ao destino sem bloquear
if (empty($institutions)) {
    $_SESSION['institution_id']           = null;
    $_SESSION['current_institution_id']   = null;
    $_SESSION['current_institution_name'] = null;
    $userService->logLogin((int)$_SESSION['user_id']);
    header('Location: ' . getHomepage());
    exit;
}

// Se só tiver uma, seleciona automaticamente
if (count($institutions) === 1) {
    $_SESSION['institution_id']           = $institutions[0]['id'];
    $_SESSION['current_institution_id']   = $institutions[0]['id'];
    $_SESSION['current_institution_name'] = $institutions[0]['name'];
    $_SESSION['current_institution_photo']= $institutions[0]['photo'];
    $userService->logLogin((int)$_SESSION['user_id']);
    header('Location: ' . getHomepage());
    exit;
}

// Seleção via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['institution_id'])) {
    $chosenId = (int)$_POST['institution_id'];
    foreach ($institutions as $inst) {
        if ($inst['id'] === $chosenId) {
            $_SESSION['institution_id']            = $inst['id'];
            $_SESSION['current_institution_id']    = $inst['id'];
            $_SESSION['current_institution_name']  = $inst['name'];
            $_SESSION['current_institution_photo'] = $inst['photo'];
            $userService->logLogin((int)$_SESSION['user_id']);
            $dest = $_POST['redirect'] ?? getHomepage();
            // Sanitiza destino
            if (!preg_match('/^\/[a-zA-Z0-9\/\-_.?=&]*$/', $dest)) $dest = getHomepage();
            header('Location: ' . $dest);
            exit;
        }
    }
}

// Destino após seleção
$redirect = $_GET['redirect'] ?? getHomepage();

$user = getCurrentUser();
$firstName = explode(' ', $user['name'])[0];
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= htmlspecialchars($_SESSION['user_theme'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar Instituição — Vértice Acadêmico</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: var(--bg-base); padding: 1.5rem; }
        .sel-container { width: 100%; max-width: 680px; }
        .sel-header { text-align: center; margin-bottom: 2rem; }
        .sel-logo { width: 52px; height: 52px; border-radius: var(--radius-lg);
                    background: var(--gradient-brand); display: inline-flex; align-items: center;
                    justify-content: center; color: white; font-size: 1.375rem; font-weight: 800;
                    margin-bottom: 1rem; box-shadow: 0 4px 16px rgba(79,70,229,.35); }
        .sel-title { font-size: 1.5rem; font-weight: 800; color: var(--text-primary);
                     letter-spacing: -.4px; margin-bottom: .375rem; }
        .sel-subtitle { font-size: .9375rem; color: var(--text-muted); }
        .sel-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
        .inst-card {
            background: var(--card-bg); border: 2px solid var(--border-color);
            border-radius: var(--radius-xl); padding: 1.5rem;
            cursor: pointer; transition: all var(--transition-base);
            display: flex; align-items: center; gap: 1rem;
            position: relative; overflow: hidden; text-align: left;
        }
        .inst-card::before {
            content: ''; position: absolute; inset: 0;
            background: var(--gradient-brand); opacity: 0;
            transition: opacity var(--transition-base);
        }
        .inst-card:hover {
            border-color: var(--color-primary);
            box-shadow: 0 8px 30px rgba(79,70,229,.2);
            transform: translateY(-3px);
        }
        .inst-card:hover::before { opacity: .04; }
        .inst-card.selected {
            border-color: var(--color-primary);
            background: var(--color-primary-light);
        }
        .inst-logo {
            width: 56px; height: 56px; flex-shrink: 0;
            border-radius: var(--radius-md); object-fit: cover;
            border: 1px solid var(--border-color);
        }
        .inst-logo-placeholder {
            width: 56px; height: 56px; flex-shrink: 0;
            border-radius: var(--radius-md); background: var(--gradient-brand);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.625rem;
        }
        .inst-info { flex: 1; min-width: 0; }
        .inst-name { font-size: 1rem; font-weight: 700; color: var(--text-primary);
                     margin-bottom: .25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .inst-cnpj { font-size: .8125rem; color: var(--text-muted); font-family: monospace; }
        .inst-resp { font-size: .8125rem; color: var(--text-secondary); margin-top: .25rem;
                     white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .inst-check { width: 22px; height: 22px; flex-shrink: 0;
                      border-radius: 50%; border: 2px solid var(--border-color);
                      display: flex; align-items: center; justify-content: center;
                      transition: all var(--transition-fast); }
        .inst-card:hover .inst-check, .inst-card.selected .inst-check {
            background: var(--color-primary); border-color: var(--color-primary); color: white; }
        .sel-footer { margin-top: 1.75rem; display: flex; justify-content: center; }
        @media(max-width:480px){ .sel-grid{grid-template-columns:1fr;} .sel-title{font-size:1.25rem;} }
    </style>
</head>
<body>
<div class="sel-container">
    <div class="sel-header fade-in">
        <div class="sel-logo">VA</div>
        <div class="sel-title">Olá, <?= htmlspecialchars($firstName) ?>! 👋</div>
        <p class="sel-subtitle">Você tem acesso a <?= count($institutions) ?> instituições.<br>Selecione com qual deseja trabalhar agora.</p>
    </div>

    <form method="POST" id="instForm">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <input type="hidden" name="institution_id" id="selectedInstId">

        <div class="sel-grid fade-in">
            <?php foreach ($institutions as $inst): ?>
            <button type="button" class="inst-card" data-id="<?= $inst['id'] ?>"
                    onclick="selectInst(<?= $inst['id'] ?>, this)">
                <?php if (!empty($inst['photo']) && file_exists(__DIR__ . '/' . $inst['photo'])): ?>
                <img src="/<?= htmlspecialchars($inst['photo']) ?>" alt="" class="inst-logo">
                <?php else: ?>
                <div class="inst-logo-placeholder">🏫</div>
                <?php endif; ?>
                <div class="inst-info">
                    <div class="inst-name"><?= htmlspecialchars($inst['name']) ?></div>
                    <div class="inst-cnpj"><?= htmlspecialchars($inst['cnpj']) ?></div>
                    <?php if ($inst['responsible']): ?>
                    <div class="inst-resp">👤 <?= htmlspecialchars($inst['responsible']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="inst-check">✓</div>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="sel-footer">
            <button type="submit" id="confirmBtn" class="btn btn-primary btn-lg" disabled style="min-width:220px;">
                <span class="btn-text">Entrar na Instituição</span>
                <span class="spinner"></span>
            </button>
        </div>
    </form>

    <p style="text-align:center;margin-top:1rem;font-size:.8125rem;color:var(--text-muted);">
        Você pode trocar de instituição a qualquer momento pelo menu do seu perfil.
    </p>
</div>

<script>
function selectInst(id, el) {
    document.querySelectorAll('.inst-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedInstId').value = id;
    const btn = document.getElementById('confirmBtn');
    btn.disabled = false;
}
document.getElementById('instForm').addEventListener('submit', function() {
    const btn = document.getElementById('confirmBtn');
    btn.classList.add('loading');
    btn.disabled = true;
});
</script>
</body>
</html>
