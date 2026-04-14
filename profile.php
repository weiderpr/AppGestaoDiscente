<?php
/**
 * Vértice Acadêmico — Meu Perfil
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
requireLogin();

$user      = getCurrentUser();
$pageTitle = 'Meu Perfil';
$db        = getDB();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança expirado. Tente novamente.';
    } else {
        $name  = trim($_POST['name']  ?? '');
        $phone = trim($_POST['phone'] ?? '');
    $theme = in_array($_POST['theme'] ?? '', ['light', 'dark']) ? $_POST['theme'] : 'light';

    if (strlen($name) < 3) {
        $error = 'Nome deve ter pelo menos 3 caracteres.';
    } else {
        // Foto nova?
        $photoPath = $user['photo'];
        if (!empty($_FILES['photo']['tmp_name'])) {
            $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $allowed)) {
                $error = 'Formato de imagem inválido. Use JPG, PNG, GIF ou WEBP.';
            } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                $error = 'A imagem deve ter no máximo 5MB.';
            } else {
                $destDir  = __DIR__ . '/assets/uploads/avatars/';
                $fileName = uniqid('avatar_', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $destDir . $fileName)) {
                    $photoPath = 'assets/uploads/avatars/' . $fileName;
                } else {
                    $error = 'Falha ao salvar a foto de perfil.';
                }
            }
        }

        if (!$error) {
            require_once __DIR__ . '/src/App/Services/Service.php';
            require_once __DIR__ . '/src/App/Services/UserService.php';
            $userService = new \App\Services\UserService();

            $userService->updateProfile($user['id'], [
                'name' => $name,
                'phone' => $phone,
                'photo' => $photoPath,
                'theme' => $theme
            ]);

            // Atualiza sessão
            $_SESSION['user_name']  = $name;
            $_SESSION['user_theme'] = $theme;

            $success = 'Perfil atualizado com sucesso!';
            $user    = getCurrentUser(); // recarrega
        }
        }
    }
}

// Troca de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt  = $db->prepare('SELECT password FROM users WHERE id=?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!password_verify($current, $row['password'])) {
        $error = 'Senha atual incorreta.';
    } elseif (strlen($new) < 6) {
        $error = 'Nova senha deve ter pelo menos 6 caracteres.';
    } elseif ($new !== $confirm) {
        $error = 'As novas senhas não coincidem.';
    } else {
        require_once __DIR__ . '/src/App/Services/Service.php';
        require_once __DIR__ . '/src/App/Services/UserService.php';
        $userService = new \App\Services\UserService();
        
        $userService->changePassword($user['id'], $new);
        $success = 'Senha alterada com sucesso!';
    }
}

// Iniciais para avatar
$initials = '';
foreach (explode(' ', trim($user['name'])) as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
    if (strlen($initials) >= 2) break;
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header fade-in">
    <h1 class="page-title">👤 Meu Perfil</h1>
    <p class="page-subtitle">Gerencie suas informações pessoais e preferências</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success fade-in" role="alert" style="margin-bottom:1.5rem;">
    ✅ <?= htmlspecialchars($success) ?>
    <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:1.1rem;" aria-label="Fechar">✕</button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger fade-in" role="alert" style="margin-bottom:1.5rem;">
    ⚠️ <?= htmlspecialchars($error) ?>
    <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:1.1rem;" aria-label="Fechar">✕</button>
</div>
<?php endif; ?>

<div class="dashboard-grid fade-in">

    <!-- Card: Dados Pessoais -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📝 Dados Pessoais</span>
            <span class="badge-profile badge-<?= htmlspecialchars(explode(' ', $user['profile'])[0]) ?>">
                <?= htmlspecialchars($user['profile']) ?>
            </span>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="auth-form" style="gap:1.125rem;">
                <?= csrf_field() ?>

                <!-- Avatar -->
                <div style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;margin-bottom:0.5rem;">
                    <div class="avatar-preview-ring" id="avatarRing" title="Clique para trocar a foto" style="width:96px;height:96px;">
                        <?php if (!empty($user['photo']) && file_exists(__DIR__ . '/' . $user['photo'])): ?>
                        <img src="/<?= htmlspecialchars($user['photo']) ?>"
                             alt="Foto de <?= htmlspecialchars($user['name']) ?>"
                             id="avatarPreview"
                             style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                        <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIgZmlsbD0iIzRmNDZlNSIgb3BhY2l0eT0iMC4xNSIvPjx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1zaXplPSI0MCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzRmNDZlNSI+8J+RpDwvdGV4dD48L3N2Zz4="
                             id="avatarPreview"
                             style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                        <?php endif; ?>
                    </div>
                    <input type="file" id="photo" name="photo" accept="image/*" style="display:none;" aria-label="Foto de perfil">
                    <p style="font-size:0.8125rem;color:var(--text-muted);text-align:center;">Clique na foto para alterar<br><small>JPG, PNG, WEBP · máx. 5MB</small></p>
                </div>

                <!-- Nome -->
                <div class="form-group">
                    <label for="name" class="form-label">Nome Completo <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">👤</span>
                        <input type="text" id="name" name="name" class="form-control"
                               value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                </div>

                <!-- E-mail (somente leitura) -->
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <div class="input-group">
                        <span class="input-icon">✉️</span>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled style="opacity:.7;cursor:not-allowed;">
                    </div>
                    <small style="color:var(--text-muted);font-size:0.75rem;">O e-mail não pode ser alterado.</small>
                </div>

                <!-- Telefone -->
                <div class="form-group">
                    <label for="phone" class="form-label">Telefone</label>
                    <div class="input-group">
                        <span class="input-icon">📱</span>
                        <input type="tel" id="phone" name="phone" class="form-control"
                               placeholder="(00) 00000-0000"
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                </div>

                <!-- Tema -->
                <div class="form-group">
                    <label for="theme" class="form-label">🎨 Tema de Interface</label>
                    <select id="theme" name="theme" class="form-control">
                        <option value="light" <?= $user['theme'] === 'light' ? 'selected' : '' ?>>☀️ Claro</option>
                        <option value="dark"  <?= $user['theme'] === 'dark'  ? 'selected' : '' ?>>🌙 Escuro</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-full" style="margin-top:0.5rem;">💾 Salvar Alterações</button>
            </form>
        </div>
    </div>

    <!-- Card: Alterar Senha -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">🔒 Alterar Senha</span>
        </div>
        <div class="card-body">
            <form method="POST" class="auth-form" style="gap:1.125rem;">
                <?= csrf_field() ?>
                <!-- Campo hidden para evitar conflito com o form de dados -->
                <input type="hidden" name="name" value="<?= htmlspecialchars($user['name']) ?>">
                <input type="hidden" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                <input type="hidden" name="theme" value="<?= htmlspecialchars($user['theme']) ?>">

                <div class="form-group">
                    <label for="current_password" class="form-label">Senha Atual <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="current_password" name="current_password" class="form-control"
                               placeholder="Sua senha atual">
                        <button type="button" class="input-action" data-toggle-password="current_password" aria-label="Mostrar senha">👁️</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password" class="form-label">Nova Senha <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">🔑</span>
                        <input type="password" id="new_password" name="new_password" class="form-control"
                               placeholder="Mín. 6 caracteres">
                        <button type="button" class="input-action" data-toggle-password="new_password" aria-label="Mostrar nova senha">👁️</button>
                    </div>
                    <div class="password-strength mt-sm">
                        <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                        <span class="strength-text" id="strengthText"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirmar Nova Senha <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">🔑</span>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                               placeholder="Repita a nova senha">
                        <button type="button" class="input-action" data-toggle-password="confirm_password" aria-label="Mostrar confirmação">👁️</button>
                    </div>
                </div>

                <button type="submit" class="btn btn-secondary btn-full" style="margin-top:0.5rem;">🔄 Alterar Senha</button>
            </form>

            <!-- Info do perfil (somente leitura) -->
            <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--border-color);">
                <p style="font-size:0.8125rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.875rem;text-transform:uppercase;letter-spacing:.05em;">Informações da conta</p>
                <?php $rows = [
                    ['🎭', 'Perfil de acesso', $user['profile']],
                    ['📅', 'Membro desde', date('d/m/Y', strtotime($user['created_at'] ?? 'now'))],
                ]; ?>
                <?php foreach ($rows as [$icon, $label, $val]): ?>
                <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--border-color);font-size:0.875rem;">
                    <span style="color:var(--text-muted);"><?= $icon ?> <?= $label ?></span>
                    <span style="font-weight:500;color:var(--text-primary);"><?= htmlspecialchars($val) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
