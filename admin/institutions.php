<?php
/**
 * Vértice Acadêmico — Gestão de Instituições (somente Administrador)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
hasDbPermission('institutions.index');

$currentUser = getCurrentUser();

$db      = getDB();
$success = '';
$error   = '';
$action  = $_POST['action'] ?? '';

// Verificação CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify($_POST['csrf_token'] ?? '')) {
    $error = 'Token de segurança expirado. Tente novamente.';
} elseif ($action === 'create') {
    $name        = trim($_POST['name']        ?? '');
    $cnpj        = trim($_POST['cnpj']        ?? '');
    $responsible = trim($_POST['responsible'] ?? '');
    $address     = trim($_POST['address']     ?? '');

    // Formata CNPJ (remove tudo que não for número)
    $cnpjRaw = preg_replace('/\D/', '', $cnpj);

    if (strlen($name) < 2) {
        $error = 'Informe o nome da instituição.';
    } elseif (strlen($cnpjRaw) !== 14) {
        $error = 'CNPJ inválido. Informe os 14 dígitos.';
    } else {
        // Verifica duplicidade
        $st = $db->prepare('SELECT id FROM institutions WHERE cnpj=? LIMIT 1');
        $st->execute([$cnpjRaw]);
        if ($st->fetch()) {
            $error = 'Já existe uma instituição com este CNPJ.';
        } else {
            // Upload de foto
            $photoPath = null;
            if (!empty($_FILES['photo']['tmp_name'])) {
                $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','gif','webp'];
                if (in_array($ext, $allowed) && $_FILES['photo']['size'] <= 5*1024*1024) {
                    $destDir  = __DIR__ . '/../assets/uploads/institutions/';
                    $fileName = uniqid('inst_', true) . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $destDir . $fileName)) {
                        $photoPath = 'assets/uploads/institutions/' . $fileName;
                    }
                }
            }

            // Formata CNPJ para exibição XX.XXX.XXX/XXXX-XX
            $cnpjFmt = preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpjRaw);

            $st = $db->prepare('INSERT INTO institutions (name, cnpj, photo, responsible, address) VALUES (?,?,?,?,?)');
            $st->execute([$name, $cnpjFmt, $photoPath, $responsible, $address]);
            $newInstId = $db->lastInsertId();

            // Duplica as permissões da instituição atual (onde o usuário está logado) para a nova
            $currentInstId = $_SESSION['current_institution_id'] ?? null;
            if ($currentInstId && $newInstId) {
                // Clona os registros trocando o instituicao_id
                $stCopy = $db->prepare('
                    INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id)
                    SELECT profile, resource, can_access, ? 
                    FROM profile_permissions 
                    WHERE instituicao_id = ?
                ');
                $stCopy->execute([$newInstId, $currentInstId]);
            }

            $success = "Instituição «{$name}» cadastrada com sucesso!";
        }
    }

// ---- TOGGLE ATIVO ----
} elseif ($action === 'toggle' && !empty($_POST['inst_id'])) {
    $id = (int)$_POST['inst_id'];
    $db->prepare('UPDATE institutions SET is_active = !is_active WHERE id=?')->execute([$id]);
    $success = 'Status da instituição atualizado.';
// ---- EXCLUIR ----
} elseif ($action === 'delete' && !empty($_POST['inst_id'])) {
    $id = (int)$_POST['inst_id'];
    $db->prepare('DELETE FROM institutions WHERE id=?')->execute([$id]);
    $success = 'Instituição removida do sistema.';
}

// ---- LISTAR ----
$search = trim($_GET['search'] ?? '');
$sql    = 'SELECT i.*, (SELECT COUNT(*) FROM user_institutions ui WHERE ui.institution_id = i.id) AS user_count
           FROM institutions i WHERE 1=1';
$params = [];
if ($search) {
    $sql    .= ' AND (i.name LIKE ? OR i.cnpj LIKE ? OR i.responsible LIKE ?)';
    $params  = ["%$search%", "%$search%", "%$search%"];
}
$sql .= ' ORDER BY i.name ASC';
$st   = $db->prepare($sql);
$st->execute($params);
$institutions = $st->fetchAll();

$pageTitle = 'Instituições';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.inst-table-wrap { overflow-x:auto; border-radius: var(--radius-lg); }
.inst-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.inst-table th {
    padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:600;
    text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted);
    background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color);
    white-space:nowrap;
}
.inst-table td { padding:.875rem 1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; }
.inst-table tr:last-child td { border-bottom:none; }
.inst-table tr:hover td { background:var(--bg-hover); }
.inst-thumb {
    width:40px; height:40px; border-radius:var(--radius-md);
    object-fit:cover; border:1px solid var(--border-color);
}
.inst-thumb-placeholder {
    width:40px; height:40px; border-radius:var(--radius-md);
    background:var(--gradient-brand); display:flex; align-items:center;
    justify-content:center; color:white; font-size:1.125rem;
}
.action-btn {
    display:inline-flex; align-items:center; justify-content:center;
    width:32px; height:32px; border-radius:var(--radius-md);
    border:1px solid var(--border-color); background:var(--bg-surface);
    color:var(--text-muted); cursor:pointer; font-size:.875rem;
    transition:all var(--transition-fast); text-decoration:none;
}
.action-btn:hover { background:var(--bg-hover); color:var(--text-primary); }
.action-btn.danger:hover { background:#fef2f2; color:var(--color-danger); border-color:var(--color-danger); }
[data-theme="dark"] .action-btn.danger:hover { background:#450a0a; }
/* Modal - padrão */
.modal-backdrop { position:fixed; inset:0; z-index:3000; background:rgba(0,0,0,.5);
    backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center;
    padding:1rem; opacity:0; visibility:hidden; transition:all .25s ease; }
.modal-backdrop.show { opacity:1; visibility:visible; }
.modal { background:var(--bg-surface); border:1px solid var(--border-color);
    border-radius:var(--radius-xl); width:100%; max-width:560px;
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
        <h1 class="page-title">🏫 Instituições</h1>
        <p class="page-subtitle">Gerencie as instituições vinculadas ao sistema</p>
    </div>
    <button class="btn btn-primary" onclick="openModal()">➕ Nova Instituição</button>
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

<!-- Filtro -->
<div class="card fade-in" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.5rem;">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:220px;margin:0;">
                <div class="input-group">
                    <span class="input-icon">🔍</span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Buscar por nome, CNPJ ou responsável..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($search): ?>
            <a href="/admin/institutions.php" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabela -->
<div class="card fade-in">
    <div class="card-header">
        <span class="card-title">Lista de Instituições</span>
        <span style="font-size:.875rem;color:var(--text-muted);"><?= count($institutions) ?> encontrada(s)</span>
    </div>
    <div class="inst-table-wrap">
        <table class="inst-table">
            <thead>
                <tr>
                    <th>Instituição</th>
                    <th>CNPJ</th>
                    <th>Responsável</th>
                    <th>Endereço</th>
                    <th>Usuários</th>
                    <th>Status</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($institutions)): ?>
                <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
                    Nenhuma instituição cadastrada.
                </td></tr>
                <?php endif; ?>
                <?php foreach ($institutions as $inst): ?>
                <tr class="<?= !$inst['is_active'] ? 'user-row-inactive' : '' ?>"
                    style="<?= !$inst['is_active'] ? 'opacity:.55' : '' ?>">
                    <td>
                        <div style="display:flex;align-items:center;gap:.625rem;">
                            <?php if (!empty($inst['photo']) && file_exists(__DIR__ . '/../' . $inst['photo'])): ?>
                            <img src="/<?= htmlspecialchars($inst['photo']) ?>" alt="" class="inst-thumb">
                            <?php else: ?>
                            <div class="inst-thumb-placeholder">🏫</div>
                            <?php endif; ?>
                            <span style="font-weight:600;"><?= htmlspecialchars($inst['name']) ?></span>
                        </div>
                    </td>
                    <td style="color:var(--text-secondary);font-family:monospace;"><?= htmlspecialchars($inst['cnpj']) ?></td>
                    <td style="color:var(--text-secondary);"><?= $inst['responsible'] ? htmlspecialchars($inst['responsible']) : '—' ?></td>
                    <td style="color:var(--text-secondary);max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= $inst['address'] ? htmlspecialchars($inst['address']) : '—' ?>
                    </td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .625rem;border-radius:var(--radius-full);background:var(--color-primary-light);color:var(--color-primary);font-size:.8125rem;font-weight:600;">
                            👥 <?= $inst['user_count'] ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-size:.8125rem;font-weight:600;color:<?= $inst['is_active'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                            <?= $inst['is_active'] ? '● Ativa' : '○ Inativa' ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <a href="/admin/edit_institution.php?id=<?= $inst['id'] ?>"
                               class="action-btn" title="Editar">✏️</a>
                            <button type="button" class="action-btn" title="<?= $inst['is_active'] ? 'Desativar' : 'Ativar' ?>"
                                    onclick="toggleInst(<?= $inst['id'] ?>, '<?= htmlspecialchars($inst['name']) ?>', <?= $inst['is_active'] ? 'true' : 'false' ?>)">
                                <?= $inst['is_active'] ? '⏸' : '▶' ?>
                            </button>
                            <button type="button" class="action-btn danger" title="Excluir"
                                    onclick="deleteInst(<?= $inst['id'] ?>, '<?= htmlspecialchars($inst['name']) ?>')">🗑</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Nova Instituição -->
<div class="modal-backdrop" id="instModal" role="dialog" aria-modal="true">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">🏫 Nova Instituição</span>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="createInstForm">
            <input type="hidden" name="action" value="create">
            <?= csrf_field() ?>
            <div class="modal-body">

                <!-- Logo Preview -->
                <div style="display:flex;justify-content:center;">
                    <div class="avatar-upload">
                        <div class="avatar-preview-ring" id="instImgRing"
                             style="width:80px;height:80px;border-radius:var(--radius-md);"
                             title="Clique para selecionar logotipo">
                            <img id="instImgPreview"
                                 src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgcng9IjEwIiBmaWxsPSIjNGY0NmU1IiBvcGFjaXR5PSIwLjEiLz48dGV4dCB4PSI1MCIgeT0iNTgiIGZvbnQtc2l6ZT0iNDQiIHRleHQtYW5jaG9yPSJtaWRkbGUiPvCfj6s8L3RleHQ+PC9zdmc+"
                                 style="width:100%;height:100%;border-radius:var(--radius-md);object-fit:cover;"
                                 alt="Logotipo">
                        </div>
                        <input type="file" id="instPhoto" name="photo" accept="image/*" style="display:none;">
                        <small style="color:var(--text-muted);">Logotipo (opcional)</small>
                    </div>
                </div>

                <!-- Nome -->
                <div class="form-group">
                    <label class="form-label">Nome da Instituição <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">🏫</span>
                        <input type="text" name="name" class="form-control"
                               placeholder="Ex: Escola Municipal João da Silva" required>
                    </div>
                </div>

                <!-- CNPJ + Responsável -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem;">
                    <div class="form-group">
                        <label class="form-label">CNPJ <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">🪪</span>
                            <input type="text" name="cnpj" id="cnpjInput" class="form-control"
                                   placeholder="00.000.000/0000-00"
                                   maxlength="18" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Responsável</label>
                        <div class="input-group">
                            <span class="input-icon">👤</span>
                            <input type="text" name="responsible" class="form-control"
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
                               placeholder="Rua, número, bairro, cidade - UF">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">💾 Cadastrar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal()  { document.getElementById('instModal').classList.add('show'); document.body.style.overflow='hidden'; }
function closeModal() { document.getElementById('instModal').classList.remove('show'); document.body.style.overflow=''; }
document.getElementById('instModal').addEventListener('click', e => { if(e.target===document.getElementById('instModal')) closeModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });

// Toasts para feedback
<?php if ($success || $error): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success): ?>
    showSuccess(<?= json_encode($success) ?>);
    <?php endif; ?>
    <?php if ($error): ?>
    showError(<?= json_encode($error) ?>);
    <?php endif; ?>
});
<?php endif; ?>

// Toggle e Delete com confirmModal
function toggleInst(id, name, isActive) {
    const action = isActive ? 'Desativar' : 'Ativar';
    confirmModal({
        title: action + ' Instituição',
        message: `Tem certeza que deseja ${action.toLowerCase()} a instituição "${name}"?`,
        confirmText: action,
        confirmClass: isActive ? 'btn-warning' : 'btn-success',
        onConfirm: () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="inst_id" value="${id}">
                <input type="hidden" name="csrf_token" value="${(el = document.querySelector('[name=csrf_token]')) ? el.value : ''}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function deleteInst(id, name) {
    confirmModal({
        title: 'Excluir Instituição',
        message: `Tem certeza que deseja excluir a instituição "${name}"? Isso removerá o vínculo com todos os usuários.`,
        confirmText: 'Excluir',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="inst_id" value="${id}">
                <input type="hidden" name="csrf_token" value="${(el = document.querySelector('[name=csrf_token]')) ? el.value : ''}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Submit AJAX do formulário de criar
const createInstForm = document.getElementById('createInstForm');
if (createInstForm) createInstForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    showLoading('Criando instituição...');
    fetch('', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(html => { hideLoading(); window.location.reload(); })
    .catch(err => { hideLoading(); showError('Erro ao criar instituição.'); });
});

// Logotipo preview
document.getElementById('instImgRing').addEventListener('click', () => document.getElementById('instPhoto').click());
document.getElementById('instPhoto').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = ev => { document.getElementById('instImgPreview').src = ev.target.result; };
    reader.readAsDataURL(file);
});

// Máscara CNPJ
const cnpjInput = document.getElementById('cnpjInput');
if (cnpjInput) cnpjInput.addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').substring(0,14);
    if (v.length>12) v = v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})/,'$1.$2.$3/$4-$5');
    else if (v.length>8) v = v.replace(/(\d{2})(\d{3})(\d{3})(\d{1,4})/,'$1.$2.$3/$4');
    else if (v.length>5) v = v.replace(/(\d{2})(\d{3})(\d{1,3})/,'$1.$2.$3');
    else if (v.length>2) v = v.replace(/(\d{2})(\d{1,3})/,'$1.$2');
    this.value = v;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
