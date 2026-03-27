<?php
/**
 * Vértice Acadêmico — Edição de Usuário (somente Administrador)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$currentUser = getCurrentUser();
if (!$currentUser || $currentUser['profile'] !== 'Administrador') {
    header('Location: /dashboard.php');
    exit;
}

$db = getDB();

// ID do usuário a editar
$uid = (int)($_GET['id'] ?? 0);
if (!$uid) {
    header('Location: /admin/users.php');
    exit;
}

// Busca o usuário
$stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$uid]);
$editUser = $stmt->fetch();
if (!$editUser) {
    header('Location: /admin/users.php');
    exit;
}

$success = '';
$error   = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança expirado. Tente novamente.';
    } else {
        $action = $_POST['action'] ?? 'update';

        // ---- ATUALIZAR DADOS ----
        if ($action === 'update') {
        $name    = trim($_POST['name']    ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $profile = $_POST['profile']      ?? '';
        $theme   = in_array($_POST['theme'] ?? '', ['light','dark']) ? $_POST['theme'] : 'light';

        if (strlen($name) < 3) {
            $error = 'Nome deve ter pelo menos 3 caracteres.';
        } elseif (!in_array($profile, PROFILES)) {
            $error = 'Selecione um perfil válido.';
        } else {
            $isTeacher = ($profile !== 'Professor' && !empty($_POST['is_teacher'])) ? 1 : 0;
            // Upload de foto
            $photoPath = $editUser['photo'];
            if (!empty($_FILES['photo']['tmp_name'])) {
                $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','gif','webp'];
                if (!in_array($ext, $allowed)) {
                    $error = 'Formato de imagem inválido. Use JPG, PNG, GIF ou WEBP.';
                } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                    $error = 'A imagem deve ter no máximo 5MB.';
                } else {
                    $destDir  = __DIR__ . '/../assets/uploads/avatars/';
                    $fileName = uniqid('avatar_', true) . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $destDir . $fileName)) {
                        $photoPath = 'assets/uploads/avatars/' . $fileName;
                    } else {
                        $error = 'Falha ao salvar a foto.';
                    }
                }
            }

            if (!$error) {
                $stmt = $db->prepare('UPDATE users SET name=?, phone=?, photo=?, profile=?, is_teacher=?, theme=? WHERE id=?');
                $stmt->execute([$name, $phone, $photoPath, $profile, $isTeacher, $theme, $uid]);

                // Atualiza vínculos de instituições
                $selectedInsts = $_POST['institutions'] ?? [];
                $db->prepare('DELETE FROM user_institutions WHERE user_id=?')->execute([$uid]);
                if (!empty($selectedInsts)) {
                    $stInst = $db->prepare('INSERT IGNORE INTO user_institutions (user_id, institution_id) VALUES (?,?)');
                    foreach ($selectedInsts as $instId) {
                        $stInst->execute([$uid, (int)$instId]);
                    }
                }

                $success = 'Usuário atualizado com sucesso!';
                $stmt = $db->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
                $stmt->execute([$uid]);
                $editUser = $stmt->fetch();
            }
        }
    }

    // ---- ALTERAR SENHA ----
    if ($action === 'password') {
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 6) {
            $error = 'A nova senha deve ter pelo menos 6 caracteres.';
        } elseif ($new !== $confirm) {
            $error = 'As senhas não coincidem.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $db->prepare('UPDATE users SET password=? WHERE id=?');
            $stmt->execute([$hash, $uid]);
            $success = 'Senha redefinida com sucesso!';
        }
        }
    }
}

// Iniciais para avatar
$initials = '';
foreach (explode(' ', trim($editUser['name'])) as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
    if (strlen($initials) >= 2) break;
}

// Instituições ativas e IDs já vinculados
$allInstitutions   = $db->query('SELECT id, name FROM institutions WHERE is_active=1 ORDER BY name ASC')->fetchAll();
$linkedInstIds     = $db->prepare('SELECT institution_id FROM user_institutions WHERE user_id=?');
$linkedInstIds->execute([$uid]);
$linkedInstIds     = array_column($linkedInstIds->fetchAll(), 'institution_id');

$pageTitle = 'Editar Usuário';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 class="page-title">✏️ Editar Usuário</h1>
        <p class="page-subtitle">Editando: <strong><?= htmlspecialchars($editUser['name']) ?></strong></p>
    </div>
    <a href="/admin/users.php" class="btn btn-secondary">← Voltar à Lista</a>
</div>

<?php if ($success): ?>
<div class="alert alert-success fade-in" style="margin-bottom:1.5rem;">
    ✅ <?= htmlspecialchars($success) ?>
    <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:1.1rem;">✕</button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger fade-in" style="margin-bottom:1.5rem;">
    ⚠️ <?= htmlspecialchars($error) ?>
    <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:1.1rem;">✕</button>
</div>
<?php endif; ?>

<div class="dashboard-grid fade-in">

    <!-- Card: Dados do Usuário -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📝 Dados do Usuário</span>
            <span class="badge-profile badge-<?= htmlspecialchars(explode(' ', $editUser['profile'])[0]) ?>">
                <?= htmlspecialchars($editUser['profile']) ?>
            </span>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="auth-form" style="gap:1.125rem;">
                <input type="hidden" name="action" value="update">
                <?= csrf_field() ?>

                <!-- Avatar -->
                <div style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;margin-bottom:0.5rem;">
                    <div class="avatar-preview-ring" id="avatarRing" style="width:96px;height:96px;cursor:pointer;" title="Clique para trocar a foto">
                        <?php if (!empty($editUser['photo']) && file_exists(__DIR__ . '/../' . $editUser['photo'])): ?>
                        <img src="/<?= htmlspecialchars($editUser['photo']) ?>"
                             alt="Foto de <?= htmlspecialchars($editUser['name']) ?>"
                             id="avatarPreview"
                             style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                        <div id="avatarInitials" style="width:100%;height:100%;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;color:white;font-size:2rem;font-weight:700;">
                            <?= $initials ?>
                        </div>
                        <img id="avatarPreview" style="display:none;width:100%;height:100%;border-radius:50%;object-fit:cover;" alt="">
                        <?php endif; ?>
                    </div>
                    <input type="file" id="photo" name="photo" accept="image/*" style="display:none;" aria-label="Foto de perfil">
                    <p style="font-size:0.8125rem;color:var(--text-muted);text-align:center;">
                        Clique na foto para alterar<br><small>JPG, PNG, WEBP · máx. 5MB</small>
                    </p>
                </div>

                <!-- Nome -->
                <div class="form-group">
                    <label for="name" class="form-label">Nome Completo <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">👤</span>
                        <input type="text" id="name" name="name" class="form-control"
                               value="<?= htmlspecialchars($editUser['name']) ?>" required>
                    </div>
                </div>

                <!-- E-mail (leitura) -->
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <div class="input-group">
                        <span class="input-icon">✉️</span>
                        <input type="email" class="form-control"
                               value="<?= htmlspecialchars($editUser['email']) ?>"
                               disabled style="opacity:.7;cursor:not-allowed;">
                    </div>
                    <small style="color:var(--text-muted);font-size:.75rem;">O e-mail não pode ser alterado.</small>
                </div>

                <!-- Telefone + Perfil -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem;">
                    <div class="form-group">
                        <label for="phone" class="form-label">Telefone</label>
                        <div class="input-group">
                            <span class="input-icon">📱</span>
                            <input type="tel" id="phone" name="phone" class="form-control"
                                   placeholder="(00) 00000-0000"
                                   value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="profile" class="form-label">Perfil de Acesso <span class="required">*</span></label>
                        <select id="profile" name="profile" class="form-control" required onchange="toggleTeacherFieldEdit()">
                            <?php foreach (PROFILES as $p): ?>
                            <option value="<?= $p ?>" <?= $editUser['profile'] === $p ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Campo para indicar que também é professor (só aparece se perfil não for Professor) -->
                    <div class="form-group" id="teacherFieldGroup" style="<?= $editUser['profile'] === 'Professor' ? 'display:none;' : 'display:block;' ?>">
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;padding:.5rem;border:1.5px solid var(--color-primary);border-radius:var(--radius-md);background:rgba(79,70,229,0.05);">
                            <input type="checkbox" name="is_teacher" id="editUserIsTeacher" value="1" <?= !empty($editUser['is_teacher']) ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--color-primary);">
                            <span style="font-size:.9375rem;color:var(--text-primary);">
                                👨‍🏫 Este usuário <strong>também é professor</strong>
                            </span>
                        </label>
                        <small style="color:var(--text-muted);display:block;margin-top:.375rem;">
                            Ao marcar, o usuário poderá ser vinculado a disciplinas e turmas como professor.
                        </small>
                    </div>
                </div>

                <!-- Tema -->
                <div class="form-group">
                    <label for="theme" class="form-label">🎨 Tema de Interface</label>
                    <select id="theme" name="theme" class="form-control">
                        <option value="light" <?= $editUser['theme'] === 'light' ? 'selected' : '' ?>>☀️ Claro</option>
                        <option value="dark"  <?= $editUser['theme'] === 'dark'  ? 'selected' : '' ?>>🌙 Escuro</option>
                    </select>
                </div>

                <!-- Instituições -->
                <?php if (!empty($allInstitutions)): ?>
                <div class="form-group">
                    <label class="form-label">🏫 Instituições de Acesso</label>
                    <div style="max-height:140px;overflow-y:auto;border:1.5px solid var(--border-color);border-radius:var(--radius-md);padding:.5rem;background:var(--bg-input);display:flex;flex-direction:column;gap:.375rem;">
                        <?php foreach ($allInstitutions as $inst): ?>
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;padding:.25rem .375rem;border-radius:var(--radius-sm);transition:background var(--transition-fast);">
                            <input type="checkbox" name="institutions[]" value="<?= $inst['id'] ?>"
                                   <?= in_array($inst['id'], $linkedInstIds) ? 'checked' : '' ?>
                                   style="width:15px;height:15px;accent-color:var(--color-primary);cursor:pointer;">
                            <span style="font-size:.875rem;color:var(--text-primary);"><?= htmlspecialchars($inst['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <small style="color:var(--text-muted);">Selecione as instituições que este usuário pode acessar</small>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem;">
                    💾 Salvar Alterações
                </button>
            </form>
        </div>
    </div>

    <!-- Coluna direita -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        <!-- Card: Redefinir Senha -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">🔒 Redefinir Senha</span>
            </div>
            <div class="card-body">
                <form method="POST" class="auth-form" style="gap:1.125rem;">
                    <input type="hidden" name="action" value="password">

                    <div class="form-group">
                        <label for="new_password" class="form-label">Nova Senha <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🔑</span>
                            <input type="password" id="new_password" name="new_password"
                                   class="form-control" placeholder="Mín. 6 caracteres">
                            <button type="button" class="input-action" data-toggle-password="new_password">👁️</button>
                        </div>
                        <div class="password-strength mt-sm">
                            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                            <span class="strength-text" id="strengthText"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmar Senha <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🔑</span>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   class="form-control" placeholder="Repita a senha">
                            <button type="button" class="input-action" data-toggle-password="confirm_password">👁️</button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-secondary btn-full" style="margin-top:.5rem;">
                        🔄 Redefinir Senha
                    </button>
                </form>
            </div>
        </div>

        <!-- Card: Informações da Conta -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">ℹ️ Informações da Conta</span>
            </div>
            <div class="card-body" style="padding:1rem 1.5rem;">
                <?php $rows = [
                    ['📅', 'Cadastrado em', date('d/m/Y H:i', strtotime($editUser['created_at']))],
                    ['🔄', 'Atualizado em', date('d/m/Y H:i', strtotime($editUser['updated_at']))],
                    ['🔘', 'Status', $editUser['is_active'] ? '✅ Ativo' : '❌ Inativo'],
                ]; ?>
                <?php foreach ($rows as [$icon, $label, $val]): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--border-color);font-size:.875rem;">
                    <span style="color:var(--text-muted);"><?= $icon ?> <?= $label ?></span>
                    <span style="font-weight:500;color:var(--text-primary);"><?= $val ?></span>
                </div>
                <?php endforeach; ?>

                <?php if ($editUser['id'] !== $currentUser['id']): ?>
                <form method="POST" action="/admin/users.php" style="margin-top:1rem;">
                    <input type="hidden" name="action"  value="toggle">
                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                    <button type="submit"
                            class="btn btn-full <?= $editUser['is_active'] ? 'btn-secondary' : 'btn-primary' ?>"
                            onclick="return confirm('<?= $editUser['is_active'] ? 'Desativar' : 'Ativar' ?> este usuário?')"
                            style="margin-top:.25rem;">
                        <?= $editUser['is_active'] ? '⏸ Desativar Usuário' : '▶ Ativar Usuário' ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /coluna direita -->
</div><!-- /dashboard-grid -->

<script>
function toggleTeacherFieldEdit() {
    const profile = document.getElementById('profile').value;
    const teacherGroup = document.getElementById('teacherFieldGroup');
    if (profile && profile !== 'Professor') {
        teacherGroup.style.display = 'block';
    } else {
        teacherGroup.style.display = 'none';
        document.getElementById('editUserIsTeacher').checked = false;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
