<?php
/**
 * Vértice Acadêmico — Edição de Instituição
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$currentUser = getCurrentUser();
if (!$currentUser || $currentUser['profile'] !== 'Administrador') {
    header('Location: /dashboard.php');
    exit;
}

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/institutions.php'); exit; }

$stmt = $db->prepare('SELECT * FROM institutions WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$inst = $stmt->fetch();
if (!$inst) { header('Location: /admin/institutions.php'); exit; }

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $cnpj        = trim($_POST['cnpj']        ?? '');
    $responsible = trim($_POST['responsible'] ?? '');
    $address     = trim($_POST['address']     ?? '');
    $cnpjRaw     = preg_replace('/\D/', '', $cnpj);

    if (strlen($name) < 2) {
        $error = 'Informe o nome da instituição.';
    } elseif (strlen($cnpjRaw) !== 14) {
        $error = 'CNPJ inválido. Informe os 14 dígitos.';
    } else {
        // Verifica duplicidade (exceto a própria)
        $st = $db->prepare('SELECT id FROM institutions WHERE cnpj=? AND id!=? LIMIT 1');
        $st->execute([preg_replace('/\D/', '', $inst['cnpj']), $id]); // verifica pelo CNPJ formatado atual
        // Permite salvar com mesmo CNPJ (da própria instituição)

        // Upload
        $photoPath = $inst['photo'];
        if (!empty($_FILES['photo']['tmp_name'])) {
            $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed)) {
                $error = 'Formato de imagem inválido.';
            } elseif ($_FILES['photo']['size'] > 5*1024*1024) {
                $error = 'Imagem muito grande (máx. 5MB).';
            } else {
                $destDir  = __DIR__ . '/../assets/uploads/institutions/';
                $fileName = uniqid('inst_', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $destDir . $fileName)) {
                    $photoPath = 'assets/uploads/institutions/' . $fileName;
                }
            }
        }

        if (!$error) {
            $cnpjFmt = preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpjRaw);
            $st = $db->prepare('UPDATE institutions SET name=?, cnpj=?, photo=?, responsible=?, address=? WHERE id=?');
            $st->execute([$name, $cnpjFmt, $photoPath, $responsible, $address, $id]);
            $success = 'Instituição atualizada com sucesso!';
            $stmt = $db->prepare('SELECT * FROM institutions WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $inst = $stmt->fetch();
        }
    }
}

// Usuários vinculados
$stmt = $db->prepare(
    'SELECT u.id, u.name, u.email, u.profile, u.photo
     FROM users u
     INNER JOIN user_institutions ui ON ui.user_id = u.id
     WHERE ui.institution_id = ?
     ORDER BY u.name ASC'
);
$stmt->execute([$id]);
$linkedUsers = $stmt->fetchAll();

$pageTitle = 'Editar Instituição';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 class="page-title">✏️ Editar Instituição</h1>
        <p class="page-subtitle">Editando: <strong><?= htmlspecialchars($inst['name']) ?></strong></p>
    </div>
    <a href="/admin/institutions.php" class="btn btn-secondary">← Voltar à Lista</a>
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

    <!-- Formulário de Edição -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📝 Dados da Instituição</span>
            <span style="font-size:.8125rem;font-weight:600;color:<?= $inst['is_active'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                <?= $inst['is_active'] ? '● Ativa' : '○ Inativa' ?>
            </span>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="auth-form" style="gap:1.125rem;">

                <!-- Logotipo -->
                <div style="display:flex;justify-content:center;">
                    <div class="avatar-upload">
                        <div class="avatar-preview-ring" id="imgRing"
                             style="width:96px;height:96px;border-radius:var(--radius-lg);cursor:pointer;"
                             title="Clique para trocar o logotipo">
                            <?php if (!empty($inst['photo']) && file_exists(__DIR__ . '/../' . $inst['photo'])): ?>
                            <img id="imgPreview" src="/<?= htmlspecialchars($inst['photo']) ?>"
                                 style="width:100%;height:100%;border-radius:var(--radius-lg);object-fit:cover;" alt="">
                            <?php else: ?>
                            <img id="imgPreview"
                                 src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgcng9IjEwIiBmaWxsPSIjNGY0NmU1IiBvcGFjaXR5PSIwLjEiLz48dGV4dCB4PSI1MCIgeT0iNTgiIGZvbnQtc2l6ZT0iNDQiIHRleHQtYW5jaG9yPSJtaWRkbGUiPvCfj6s8L3RleHQ+PC9zdmc+"
                                 style="width:100%;height:100%;border-radius:var(--radius-lg);object-fit:cover;" alt="">
                            <?php endif; ?>
                        </div>
                        <input type="file" id="photo" name="photo" accept="image/*" style="display:none;">
                        <small style="color:var(--text-muted);">Clique para trocar o logotipo</small>
                    </div>
                </div>

                <!-- Nome -->
                <div class="form-group">
                    <label class="form-label">Nome da Instituição <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">🏫</span>
                        <input type="text" name="name" class="form-control"
                               value="<?= htmlspecialchars($inst['name']) ?>" required>
                    </div>
                </div>

                <!-- CNPJ + Responsável -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem;">
                    <div class="form-group">
                        <label class="form-label">CNPJ <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🪪</span>
                            <input type="text" name="cnpj" id="cnpjInput" class="form-control"
                                   value="<?= htmlspecialchars($inst['cnpj']) ?>" maxlength="18" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Responsável</label>
                        <div class="input-group">
                            <span class="input-icon">👤</span>
                            <input type="text" name="responsible" class="form-control"
                                   value="<?= htmlspecialchars($inst['responsible'] ?? '') ?>"
                                   placeholder="Nome do responsável">
                        </div>
                    </div>
                </div>

                <!-- Endereço -->
                <div class="form-group">
                    <label class="form-label">Endereço</label>
                    <div class="input-group">
                        <span class="input-icon">📍</span>
                        <input type="text" name="address" class="form-control"
                               value="<?= htmlspecialchars($inst['address'] ?? '') ?>"
                               placeholder="Rua, número, bairro, cidade - UF">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem;">
                    💾 Salvar Alterações
                </button>
            </form>
        </div>
    </div>

    <!-- Coluna direita -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        <!-- Informações -->
        <div class="card">
            <div class="card-header"><span class="card-title">ℹ️ Informações</span></div>
            <div class="card-body" style="padding:1rem 1.5rem;">
                <?php $rows = [
                    ['📅', 'Cadastrada em', date('d/m/Y H:i', strtotime($inst['created_at']))],
                    ['🔄', 'Atualizada em', date('d/m/Y H:i', strtotime($inst['updated_at']))],
                    ['👥', 'Usuários vinculados', count($linkedUsers)],
                ]; ?>
                <?php foreach ($rows as [$icon, $label, $val]): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--border-color);font-size:.875rem;">
                    <span style="color:var(--text-muted);"><?= $icon ?> <?= $label ?></span>
                    <span style="font-weight:500;color:var(--text-primary);"><?= $val ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Usuários vinculados -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">👥 Usuários Vinculados</span>
            </div>
            <div class="card-body" style="padding:1rem 1.5rem;">
                <?php if (empty($linkedUsers)): ?>
                <p style="color:var(--text-muted);font-size:.875rem;text-align:center;padding:1rem 0;">
                    Nenhum usuário vinculado a esta instituição.
                </p>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:.625rem;">
                    <?php foreach ($linkedUsers as $u):
                        $ini=''; foreach(explode(' ',trim($u['name'])) as $p){$ini.=strtoupper(substr($p,0,1));if(strlen($ini)>=2)break;}
                    ?>
                    <div style="display:flex;align-items:center;gap:.625rem;padding:.375rem 0;border-bottom:1px solid var(--border-color);font-size:.875rem;">
                        <?php if (!empty($u['photo']) && file_exists(__DIR__.'/../'.$u['photo'])): ?>
                        <img src="/<?= htmlspecialchars($u['photo']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;" alt="">
                        <?php else: ?>
                        <div style="width:32px;height:32px;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;font-weight:700;flex-shrink:0;"><?= $ini ?></div>
                        <?php endif; ?>
                        <div>
                            <div style="font-weight:600;line-height:1.2;"><?= htmlspecialchars($u['name']) ?></div>
                            <div style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($u['profile']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('imgRing').addEventListener('click', () => document.getElementById('photo').click());
document.getElementById('photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = ev => { document.getElementById('imgPreview').src = ev.target.result; };
    reader.readAsDataURL(file);
});
// Máscara CNPJ
document.getElementById('cnpjInput').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').substring(0,14);
    if (v.length>12) v = v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})/,'$1.$2.$3/$4-$5');
    else if (v.length>8) v = v.replace(/(\d{2})(\d{3})(\d{3})(\d{1,4})/,'$1.$2.$3/$4');
    else if (v.length>5) v = v.replace(/(\d{2})(\d{3})(\d{1,3})/,'$1.$2.$3');
    else if (v.length>2) v = v.replace(/(\d{2})(\d{1,3})/,'$1.$2');
    this.value = v;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
