<?php
/**
 * Vértice Acadêmico — Categorias de Disciplinas
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$user = getCurrentUser();
if (!$user || !in_array($user['profile'], ['Administrador', 'Coordenador'])) {
    header('Location: /dashboard.php');
    exit;
}

$db = getDB();
$inst = getCurrentInstitution();
$instId = $inst['id'] ?? 0;

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/subjects/categories.php'));
    exit;
}

$success = '';
$error = '';

// --- AÇÕES ---
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança expirado. Tente novamente.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        if ($nome) {
            try {
                $st = $db->prepare("INSERT INTO disciplina_categorias (institution_id, nome) VALUES (?, ?)");
                $st->execute([$instId, $nome]);
                $success = 'Categoria adicionada com sucesso!';
            } catch (PDOException $e) {
                $error = 'Erro ao adicionar categoria: ' . $e->getMessage();
            }
        } else {
            $error = 'O nome da categoria é obrigatório.';
        }
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if ($id && $nome) {
            try {
                $st = $db->prepare("UPDATE disciplina_categorias SET nome=? WHERE id=? AND institution_id=?");
                $st->execute([$nome, $id, $instId]);
                $success = 'Categoria atualizada!';
            } catch (PDOException $e) {
                $error = 'Erro ao atualizar: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                $st = $db->prepare("DELETE FROM disciplina_categorias WHERE id=? AND institution_id=?");
                $st->execute([$id, $instId]);
                $success = 'Categoria removida!';
            } catch (PDOException $e) {
                $error = 'Erro ao remover. Verifique se existem disciplinas vinculadas.';
            }
        }
    }
}

// --- LISTAGEM ---
$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM disciplina_categorias WHERE institution_id = ?";
$params = [$instId];

if ($search) {
    $sql .= ' AND nome LIKE ?';
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY nome ASC";

$st = $db->prepare($sql);
$st->execute($params);
$categories = $st->fetchAll();

$pageTitle = 'Categorias de Disciplinas';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.categories-table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
.categories-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.categories-table th {
    padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted);
    background: var(--bg-surface-2nd); border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.categories-table td { padding: .875rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.categories-table tr:last-child td { border-bottom: none; }
.categories-table tr:hover td { background: var(--bg-hover); }
.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: var(--radius-md);
    border: 1px solid var(--border-color); background: var(--bg-surface);
    color: var(--text-muted); cursor: pointer; font-size: .875rem;
    transition: all var(--transition-fast); text-decoration: none;
}
.action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
.action-btn.danger:hover { background: #fef2f2; color: var(--color-danger); border-color: var(--color-danger); }
[data-theme="dark"] .action-btn.danger:hover { background: #450a0a; }

.modal-backdrop { position: fixed; inset: 0; z-index: 3000; background: rgba(0,0,0,.5);
    backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center;
    padding: 1rem; opacity: 0; visibility: hidden; transition: all .25s ease; }
.modal-backdrop.show { opacity: 1; visibility: visible; }
.modal { background: var(--bg-surface); border: 1px solid var(--border-color);
    border-radius: var(--radius-xl); width: 100%; max-width: 480px;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,.3);
    transform: translateY(20px) scale(.97); transition: all .25s ease; }
.modal-backdrop.show .modal { transform: translateY(0) scale(1); }
.modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: space-between; }
.modal-title { font-size: 1.0625rem; font-weight: 700; color: var(--text-primary); }
.modal-close { width: 32px; height: 32px; border-radius: var(--radius-md);
    border: 1px solid var(--border-color); background: var(--bg-surface);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    color: var(--text-muted); font-size: 1.125rem; transition: all var(--transition-fast); }
.modal-close:hover { background: var(--bg-hover); color: var(--text-primary); }
.modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
.modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-color);
    display: flex; gap: .75rem; justify-content: flex-end; }
</style>

<!-- Page Header -->
<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 class="page-title">📂 Categorias de Disciplinas</h1>
        <p class="page-subtitle">
            Instituição: <strong><?= htmlspecialchars($inst['name']) ?></strong>
        </p>
    </div>
    <div style="display:flex;gap:.75rem;">
        <a href="/subjects/index.php" class="btn btn-secondary">📖 Disciplinas</a>
        <button class="btn btn-primary" onclick="openAddModal()">➕ Nova Categoria</button>
    </div>
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
                           placeholder="Buscar por nome..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($search): ?>
            <a href="/subjects/categories.php" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabela -->
<div class="card fade-in">
    <div class="card-header">
        <span class="card-title">Lista de Categorias</span>
        <span style="font-size:.875rem;color:var(--text-muted);"><?= count($categories) ?> categoria(s)</span>
    </div>
    <div class="categories-table-wrap">
        <table class="categories-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>Cadastro</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="4" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
                        📂 Nenhuma categoria cadastrada ainda.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td style="color:var(--text-muted);font-size:.8125rem;"><?= $cat['id'] ?></td>
                        <td>
                            <span style="font-weight:600;"><?= htmlspecialchars($cat['nome']) ?></span>
                        </td>
                        <td style="color:var(--text-muted);white-space:nowrap;font-size:.8125rem;">
                            <?= date('d/m/Y', strtotime($cat['created_at'])) ?>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                                <button class="action-btn" onclick='editCategory(<?= json_encode($cat) ?>)' title="Editar">✏️</button>
                                <button class="action-btn danger" onclick="deleteCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['nome'])) ?>')" title="Excluir">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Adicionar Categoria -->
<div class="modal-backdrop" id="addModal" role="dialog" aria-modal="true">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">➕ Nova Categoria</span>
            <button class="modal-close" onclick="closeAddModal()">✕</button>
        </div>
        <form method="POST" action="?action=add">
            <?= csrf_field() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nome da Categoria <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">📂</span>
                        <input type="text" name="nome" id="add_nome" class="form-control" 
                               placeholder="Ex: Ciências Exatas" required autofocus>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">💾 Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Categoria -->
<div class="modal-backdrop" id="editModal" role="dialog" aria-modal="true">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">✏️ Editar Categoria</span>
            <button class="modal-close" onclick="closeEditModal()">✕</button>
        </div>
        <form method="POST" action="?action=edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nome da Categoria <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">📂</span>
                        <input type="text" name="nome" id="edit_nome" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">💾 Atualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('add_nome').value = '';
    document.getElementById('addModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    document.getElementById('add_nome').focus();
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('show');
    document.body.style.overflow = '';
}

function editCategory(cat) {
    document.getElementById('edit_id').value = cat.id;
    document.getElementById('edit_nome').value = cat.nome;
    document.getElementById('editModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
    document.body.style.overflow = '';
}

function deleteCategory(id, name) {
    if (confirm('Excluir permanentemente a categoria «' + name + '»?\n\nNota: Disciplinas vinculadas a esta categoria também serão afetadas.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '?action=delete';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id';
        input.value = id;
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddModal();
});
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddModal();
        closeEditModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
