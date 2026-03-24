<?php
/**
 * Vértice Acadêmico — Gestão de Usuários (somente Administrador)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$user = getCurrentUser();
if (!$user || $user['profile'] !== 'Administrador') {
    header('Location: /dashboard.php');
    exit;
}

$db      = getDB();
$success = '';
$error   = '';

// ---- AÇÕES POST ----
$action = $_POST['action'] ?? '';

// Verificação CSRF para todas as ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify($_POST['csrf_token'] ?? '')) {
    $error = 'Token de segurança expirado. Tente novamente.';
} elseif ($action === 'create') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $profile = $_POST['profile']      ?? '';
    $pass    = $_POST['password']     ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($name) < 3) {
        $error = 'Nome deve ter pelo menos 3 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido.';
    } elseif (!in_array($profile, PROFILES)) {
        $error = 'Selecione um perfil válido.';
    } elseif (strlen($pass) < 6) {
        $error = 'Senha deve ter pelo menos 6 caracteres.';
    } elseif ($pass !== $confirm) {
        $error = 'As senhas não coincidem.';
    } else {
        // Verifica e-mail único
        $st = $db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $st->execute([strtolower($email)]);
        if ($st->fetch()) {
            $error = 'Este e-mail já está cadastrado.';
        } else {
            // Upload de foto opcional
            $photoPath = null;
            if (!empty($_FILES['photo']['tmp_name'])) {
                $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','gif','webp'];
                if (in_array($ext, $allowed) && $_FILES['photo']['size'] <= 5*1024*1024) {
                    $destDir  = __DIR__ . '/../assets/uploads/avatars/';
                    $fileName = uniqid('avatar_', true) . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $destDir . $fileName)) {
                        $photoPath = 'assets/uploads/avatars/' . $fileName;
                    }
                }
            }
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $st   = $db->prepare('INSERT INTO users (name,email,password,phone,photo,profile,theme) VALUES(?,?,?,?,?,?,?)');
            $st->execute([$name, strtolower($email), $hash, $phone, $photoPath, $profile, 'light']);
            $newUserId = (int)$db->lastInsertId();

            // Vincula às instituições selecionadas
            $selectedInsts = $_POST['institutions'] ?? [];
            if (!empty($selectedInsts)) {
                $stInst = $db->prepare('INSERT IGNORE INTO user_institutions (user_id, institution_id) VALUES (?, ?)');
                foreach ($selectedInsts as $instId) {
                    $stInst->execute([$newUserId, (int)$instId]);
                }
            }

            $success = "Usuário «{$name}» cadastrado com sucesso!";
        }
    }

// Ativar / Desativar usuário
} elseif ($action === 'toggle' && !empty($_POST['user_id'])) {
    $uid = (int)$_POST['user_id'];
    if ($uid !== $user['id']) {
        $st = $db->prepare('UPDATE users SET is_active = !is_active WHERE id=?');
        $st->execute([$uid]);
        $success = 'Status do usuário atualizado.';
    }

// Excluir usuário
} elseif ($action === 'delete' && !empty($_POST['user_id'])) {
    $uid = (int)$_POST['user_id'];
    if ($uid !== $user['id']) {
        $st = $db->prepare('DELETE FROM users WHERE id=?');
        $st->execute([$uid]);
        $success = 'Usuário removido do sistema.';
    }
}

// ---- LISTA DE USUÁRIOS ----
$search  = trim($_GET['search'] ?? '');
$profile_filter = $_GET['profile'] ?? '';
$sql = 'SELECT id, name, email, phone, photo, profile, theme, is_active, created_at FROM users WHERE 1=1';
$params = [];
if ($search) {
    $sql .= ' AND (name LIKE ? OR email LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($profile_filter && in_array($profile_filter, PROFILES)) {
    $sql .= ' AND profile = ?';
    $params[] = $profile_filter;
}
$sql .= ' ORDER BY id ASC';
$st = $db->prepare($sql);
$st->execute($params);
$users = $st->fetchAll();

// Instituições ativas para o select
$allInstitutions = $db->query('SELECT id, name FROM institutions WHERE is_active=1 ORDER BY name ASC')->fetchAll();

$pageTitle = 'Gestão de Usuários';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Estilos locais da gestão de usuários */
.users-table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
.users-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.users-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--text-muted);
    background: var(--bg-surface-2nd);
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.users-table td {
    padding: 0.875rem 1rem;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}
.users-table tr:last-child td { border-bottom: none; }
.users-table tr:hover td { background: var(--bg-hover); }
.user-row-inactive td { opacity: 0.55; }
.user-thumb {
    width: 36px; height: 36px; border-radius: 50%;
    object-fit: cover; border: 2px solid var(--border-color);
}
.user-thumb-placeholder {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--gradient-brand);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 0.8125rem; font-weight: 700;
    border: 2px solid transparent; flex-shrink: 0;
}
.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--text-muted);
    cursor: pointer; font-size: 0.875rem;
    transition: all var(--transition-fast);
}
.action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
.action-btn.danger:hover { background: #fef2f2; color: var(--color-danger); border-color: var(--color-danger); }
[data-theme="dark"] .action-btn.danger:hover { background: #450a0a; }

/* Modal - Usa o padrão do componente */
.modal-backdrop { position:fixed; inset:0; z-index:3000; background:rgba(0,0,0,.5);
    backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center;
    padding:1rem; opacity:0; visibility:hidden; transition:all .25s ease; }
.modal-backdrop.show { opacity:1; visibility:visible; }
.modal { background:var(--bg-surface); border:1px solid var(--border-color);
    border-radius:var(--radius-xl); width:100%; max-width:520px;
    max-height:90vh; overflow-y:auto; box-shadow:0 25px 60px rgba(0,0,0,.3);
    transform:translateY(20px) scale(.97); transition:all .25s ease; }
.modal-backdrop.show .modal { transform:translateY(0) scale(1); }
.modal-header { padding:1.5rem; border-bottom:1px solid var(--border-color);
    display:flex; align-items:center; justify-content:space-between; }
.modal-title { font-size:1.0625rem; font-weight:700; color:var(--text-primary); }
.modal-close { width:32px; height:32px; border-radius:var(--radius-md);
    border:1px solid var(--border-color); background:var(--bg-surface);
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    color:var(--text-muted); font-size:1.125rem; transition:all var(--transition-fast); }
.modal-close:hover { background:var(--bg-hover); }
.modal-body { padding:1.5rem; display:flex; flex-direction:column; gap:1rem; }
.modal-footer { padding:1rem 1.5rem; border-top:1px solid var(--border-color);
    display:flex; gap:.75rem; justify-content:flex-end; }
</style>

<!-- Page Header -->
<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 class="page-title">👥 Gestão de Usuários</h1>
        <p class="page-subtitle">Cadastre e gerencie os usuários do sistema</p>
    </div>
    <button class="btn btn-primary" onclick="openModal()">
        ➕ Novo Usuário
    </button>
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

<!-- Filtros -->
<div class="card fade-in" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.5rem;">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:200px;margin:0;">
                <div class="input-group">
                    <span class="input-icon">🔍</span>
                    <input type="text" name="search" class="form-control" placeholder="Buscar por nome ou e-mail..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="form-group" style="min-width:180px;margin:0;">
                <select name="profile" class="form-control">
                    <option value="">Todos os perfis</option>
                    <?php foreach (PROFILES as $p): ?>
                    <option value="<?= $p ?>" <?= $profile_filter === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($search || $profile_filter): ?>
            <a href="/admin/users.php" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabela de Usuários -->
<div class="card fade-in">
    <div class="card-header">
        <span class="card-title">Lista de Usuários</span>
        <span style="font-size:0.875rem;color:var(--text-muted);"><?= count($users) ?> usuário(s) encontrado(s)</span>
    </div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>E-mail</th>
                    <th>Telefone</th>
                    <th>Perfil</th>
                    <th>Tema</th>
                    <th>Status</th>
                    <th>Cadastro</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted);">
                        Nenhum usuário encontrado.
                    </td>
                </tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                <?php
                    $ini = '';
                    foreach (explode(' ', trim($u['name'])) as $p) {
                        $ini .= strtoupper(substr($p,0,1));
                        if (strlen($ini)>=2) break;
                    }
                    $isSelf = $u['id'] === $user['id'];
                ?>
                <tr class="<?= !$u['is_active'] ? 'user-row-inactive' : '' ?>">
                    <td>
                        <div style="display:flex;align-items:center;gap:.625rem;">
                            <?php if (!empty($u['photo']) && file_exists(__DIR__ . '/../' . $u['photo'])): ?>
                            <img src="/<?= htmlspecialchars($u['photo']) ?>" alt="" class="user-thumb">
                            <?php else: ?>
                            <div class="user-thumb-placeholder"><?= $ini ?></div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:600;line-height:1.2;"><?= htmlspecialchars($u['name']) ?></div>
                                <?php if ($isSelf): ?><small style="color:var(--color-primary);font-size:.7rem;">Você</small><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--text-secondary);"><?= htmlspecialchars($u['email']) ?></td>
                    <td style="color:var(--text-secondary);"><?= $u['phone'] ? htmlspecialchars($u['phone']) : '—' ?></td>
                    <td><span class="badge-profile badge-<?= htmlspecialchars(explode(' ',$u['profile'])[0]) ?>"><?= htmlspecialchars($u['profile']) ?></span></td>
                    <td><?= $u['theme'] === 'dark' ? '🌙 Escuro' : '☀️ Claro' ?></td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:.25rem;font-size:.8125rem;font-weight:600;color:<?= $u['is_active'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                            <?= $u['is_active'] ? '● Ativo' : '○ Inativo' ?>
                        </span>
                    </td>
                    <td style="color:var(--text-muted);white-space:nowrap;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <!-- Editar -->
                            <a href="/admin/edit_user.php?id=<?= $u['id'] ?>"
                               class="action-btn" title="Editar usuário">✏️</a>

                            <?php if (!$isSelf): ?>
                            <!-- Toggle Ativo/Inativo -->
                            <button type="button" class="action-btn"
                                    title="<?= $u['is_active'] ? 'Desativar' : 'Ativar' ?> usuário"
                                    onclick="toggleUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name']) ?>', <?= $u['is_active'] ? 'true' : 'false' ?>)">
                                <?= $u['is_active'] ? '⏸' : '▶' ?>
                            </button>
                            <!-- Excluir -->
                            <button type="button" class="action-btn danger"
                                    title="Excluir usuário"
                                    onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name']) ?>')">
                                🗑
                            </button>
                            <?php else: ?>
                            <span style="font-size:.75rem;color:var(--text-muted);">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Cadastrar Novo Usuário -->
<div class="modal-backdrop" id="userModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">➕ Cadastrar Novo Usuário</span>
            <button class="modal-close" onclick="closeModal()" aria-label="Fechar">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="createUserForm">
            <input type="hidden" name="action" value="create">
            <?= csrf_field() ?>
            <div class="modal-body">

                <!-- Avatar preview -->
                <div style="display:flex;justify-content:center;">
                    <div class="avatar-upload">
                        <div class="avatar-preview-ring" id="modalAvatarRing" style="width:80px;height:80px;" title="Clique para escolher foto">
                            <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIgZmlsbD0iIzRmNDZlNSIgb3BhY2l0eT0iMC4xNSIvPjx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1zaXplPSI0MCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzRmNDZlNSI+8J+RpDwvdGV4dD48L3N2Zz4="
                                 id="modalAvatarPreview"
                                 style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                        </div>
                        <input type="file" id="modalPhoto" name="photo" accept="image/*" style="display:none;">
                        <small style="color:var(--text-muted);">Foto (opcional)</small>
                    </div>
                </div>

                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem;">
                    <div class="form-group">
                        <label class="form-label">Nome Completo <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Nome do usuário" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telefone</label>
                        <input type="tel" name="phone" class="form-control" placeholder="(00) 00000-0000">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">E-mail <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="email@dominio.com" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Perfil de Acesso <span class="required">*</span></label>
                    <select name="profile" class="form-control" required>
                        <option value="" disabled selected>Selecione o perfil...</option>
                        <?php foreach (PROFILES as $p): ?>
                        <option value="<?= $p ?>"><?= htmlspecialchars($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem;">
                    <div class="form-group">
                        <label class="form-label">Senha <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="modalPass" name="password" class="form-control" placeholder="Mín. 6 caracteres" required>
                            <button type="button" class="input-action" data-toggle-password="modalPass">👁️</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirmar Senha <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="modalPassConfirm" name="password_confirm" class="form-control" placeholder="Repita" required>
                            <button type="button" class="input-action" data-toggle-password="modalPassConfirm">👁️</button>
                        </div>
                    </div>
                </div>

                <!-- Instituições -->
                <?php if (!empty($allInstitutions)): ?>
                <div class="form-group">
                    <label class="form-label">🏫 Instituições de Acesso</label>
                    <div style="max-height:130px;overflow-y:auto;border:1.5px solid var(--border-color);border-radius:var(--radius-md);padding:.5rem;background:var(--bg-input);display:flex;flex-direction:column;gap:.375rem;">
                        <?php foreach ($allInstitutions as $inst): ?>
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;padding:.25rem .375rem;border-radius:var(--radius-sm);transition:background var(--transition-fast);">
                            <input type="checkbox" name="institutions[]" value="<?= $inst['id'] ?>"
                                   style="width:15px;height:15px;accent-color:var(--color-primary);cursor:pointer;">
                            <span style="font-size:.875rem;color:var(--text-primary);"><?= htmlspecialchars($inst['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <small style="color:var(--text-muted);">Selecione uma ou mais instituições</small>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">💾 Cadastrar Usuário</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('userModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('userModal').classList.remove('show');
    document.body.style.overflow = '';
}
// Fecha ao clicar no backdrop
document.getElementById('userModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// Avatar preview no modal
document.getElementById('modalAvatarRing').addEventListener('click', function() {
    document.getElementById('modalPhoto').click();
});
document.getElementById('modalPhoto').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = ev => {
        document.getElementById('modalAvatarPreview').src = ev.target.result;
    };
    reader.readAsDataURL(file);
});

<?php if ($success || $error): ?>
// Mostrar toast após ação
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success): ?>
    showSuccess(<?= json_encode($success) ?>);
    <?php endif; ?>
    <?php if ($error): ?>
    showError(<?= json_encode($error) ?>);
    <?php endif; ?>
});
<?php endif; ?>

// Funções para toggle e delete com confirmModal
function toggleUser(userId, userName, isActive) {
    const action = isActive ? 'Desativar' : 'Ativar';
    confirmModal({
        title: action + ' Usuário',
        message: `Tem certeza que deseja ${action.toLowerCase()} o usuário "${userName}"?`,
        confirmText: action,
        confirmClass: isActive ? 'btn-warning' : 'btn-success',
        onConfirm: () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="user_id" value="${userId}">
                <input type="hidden" name="csrf_token" value="${document.querySelector('[name=csrf_token]')?.value || ''}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function deleteUser(userId, userName) {
    confirmModal({
        title: 'Excluir Usuário',
        message: `Tem certeza que deseja excluir permanentemente o usuário "${userName}"? Esta ação não pode ser desfeita.`,
        confirmText: 'Excluir',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="${userId}">
                <input type="hidden" name="csrf_token" value="${document.querySelector('[name=csrf_token]')?.value || ''}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Submeter formulário de criar usuário via AJAX
document.getElementById('createUserForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    showLoading('Criando usuário...');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(html => {
        hideLoading();
        // Recarrega a página para mostrar o resultado
        window.location.reload();
    })
    .catch(err => {
        hideLoading();
        showError('Erro ao criar usuário. Tente novamente.');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
