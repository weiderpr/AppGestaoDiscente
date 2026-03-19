<?php
/**
 * Vértice Acadêmico — Login
 */
require_once __DIR__ . '/includes/auth.php';

// Já logado? Vai pro dashboard
if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Preencha e-mail e senha para continuar.';
    } else {
        $user = loginUser($email, $password);
        if ($user) {
            $instCount = countUserInstitutions($user['id']);
            if ($instCount > 1) {
                // Tem mais de uma instituição: vai selecionar
                $defaultDest = $user['profile'] === 'Administrador' ? '/admin/users.php' : '/dashboard.php';
                header('Location: /select_institution.php?redirect=' . urlencode($defaultDest));
            } else {
                // Zero ou uma instituição: select_institution.php tratará automaticamente
                $defaultDest = $user['profile'] === 'Administrador' ? '/admin/users.php' : '/dashboard.php';
                header('Location: /select_institution.php?redirect=' . urlencode($defaultDest));
            }
            exit;
        } else {
            $error = 'E-mail ou senha incorretos. Verifique e tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login — Vértice Acadêmico">
    <title>Login — Vértice Acadêmico</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>

<!-- Botão de tema -->
<button class="auth-theme-toggle" data-action="toggleTheme" data-theme-icon title="Alternar tema" aria-label="Alternar tema">🌙</button>

<div class="auth-page">
    <div class="auth-container">

        <div class="auth-card">

            <!-- Cabeçalho -->
            <div class="auth-header">
                <div class="auth-logo">
                    <div class="auth-logo-icon" aria-hidden="true">VA</div>
                    <div class="auth-logo-text">
                        <div class="auth-logo-name">Vértice Acadêmico</div>
                        <div class="auth-logo-tagline">Gestão de Indicadores Discentes</div>
                    </div>
                </div>
                <p class="auth-title">Acesse sua conta para continuar</p>
            </div>

            <!-- Formulário -->
            <div class="auth-body">

                <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    ⚠️ <?= htmlspecialchars($error) ?>
                    <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:1.1rem;" aria-label="Fechar">✕</button>
                </div>
                <?php endif; ?>

                <form method="POST" action="/login.php" class="auth-form" id="loginForm" novalidate>

                    <!-- E-mail -->
                    <div class="form-group">
                        <label for="email" class="form-label">E-mail <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon" aria-hidden="true">✉️</span>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                placeholder="seu@email.com"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                required
                                autocomplete="email"
                                autofocus>
                        </div>
                    </div>

                    <!-- Senha -->
                    <div class="form-group">
                        <label for="password_login" class="form-label">Senha <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon" aria-hidden="true">🔒</span>
                            <input
                                type="password"
                                id="password_login"
                                name="password"
                                class="form-control"
                                placeholder="Sua senha"
                                required
                                autocomplete="current-password">
                            <button type="button" class="input-action" data-toggle-password="password_login" aria-label="Mostrar/ocultar senha">👁️</button>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn btn-primary btn-lg btn-full" id="loginBtn">
                        <span class="btn-text">Entrar no Sistema</span>
                        <span class="spinner" aria-hidden="true"></span>
                    </button>

                </form>
            </div>


        </div><!-- /auth-card -->
    </div><!-- /auth-container -->
</div><!-- /auth-page -->

<script src="/assets/js/main.js"></script>
<script>
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.classList.add('loading');
    btn.disabled = true;
});
</script>
</body>
</html>
