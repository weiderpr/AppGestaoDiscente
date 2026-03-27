<?php
/**
 * Vértice Acadêmico — Disciplinas
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
hasDbPermission('subjects.index');

$user = getCurrentUser();

$db = getDB();
$inst = getCurrentInstitution();
$instId = $inst['id'] ?? 0;

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/subjects/index.php'));
    exit;
}

$success = '';
$error = '';

// --- AÇÕES ---
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança expirado. Tente novamente.';
    } elseif ($action === 'add' || $action === 'edit') {
        $old_codigo  = trim($_POST['old_codigo'] ?? '');
        $codigo      = trim($_POST['codigo'] ?? '');
        $descricao   = trim($_POST['descricao'] ?? '');
        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
        $obs         = trim($_POST['observacoes'] ?? '');

        if ($codigo && $descricao && $categoriaId) {
            try {
                if ($action === 'add') {
                    $st = $db->prepare("INSERT INTO disciplinas (codigo, institution_id, categoria_id, descricao, observacoes) VALUES (?, ?, ?, ?, ?)");
                    $st->execute([$codigo, $instId, $categoriaId, $descricao, $obs]);
                    $success = 'Disciplina cadastrada com sucesso!';
                } else {
                    $st = $db->prepare("UPDATE disciplinas SET codigo=?, categoria_id=?, descricao=?, observacoes=? WHERE codigo=? AND institution_id=?");
                    $st->execute([$codigo, $categoriaId, $descricao, $obs, $old_codigo, $instId]);
                    $success = 'Disciplina atualizada!';
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'Já existe uma disciplina com este código.';
                } else {
                    $error = 'Erro no banco de dados: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Código, Descrição e Categoria são obrigatórios.';
        }
    }

    if ($action === 'delete') {
        $codigo = trim($_POST['codigo'] ?? '');
        if ($codigo) {
            try {
                $st = $db->prepare("DELETE FROM disciplinas WHERE codigo=? AND institution_id=?");
                $st->execute([$codigo, $instId]);
                $success = 'Disciplina removida!';
            } catch (PDOException $e) {
                $error = 'Erro ao remover: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'import_file' && !empty($_FILES['import_file']['tmp_name'])) {
        $file = $_FILES['import_file']['tmp_name'];
        $handle = fopen($file, "r");
        $imported = 0;
        
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (str_contains($firstLine, ';')) ? ';' : ',';

        try {
            $db->beginTransaction();
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                if (str_contains(strtolower($data[0] ?? ''), 'codi')) continue;

                $codigo    = trim($data[0] ?? '');
                $descricao = trim($data[1] ?? '');
                $catId     = (int)($data[2] ?? 0);

                if (!$codigo || !$descricao || !$catId) continue;

                $stCat = $db->prepare("SELECT 1 FROM disciplina_categorias WHERE id = ? AND institution_id = ?");
                $stCat->execute([$catId, $instId]);
                if (!$stCat->fetch()) continue;

                $stCheck = $db->prepare("SELECT 1 FROM disciplinas WHERE codigo = ? AND institution_id = ?");
                $stCheck->execute([$codigo, $instId]);
                if ($stCheck->fetch()) {
                    $db->prepare("UPDATE disciplinas SET descricao = ?, categoria_id = ? WHERE codigo = ? AND institution_id = ?")
                       ->execute([$descricao, $catId, $codigo, $instId]);
                } else {
                    $db->prepare("INSERT INTO disciplinas (codigo, descricao, categoria_id, institution_id) VALUES (?, ?, ?, ?)")
                       ->execute([$codigo, $descricao, $catId, $instId]);
                }
                $imported++;
            }
            $db->commit();
            $success = "Importação concluída: {$imported} disciplinas processadas.";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erro na importação: " . $e->getMessage();
        }
        fclose($handle);
    }
}

// --- LISTAGEM ---
$search = trim($_GET['search'] ?? '');
$sql = "
    SELECT d.*, c.nome as categoria_nome 
    FROM disciplinas d
    JOIN disciplina_categorias c ON c.id = d.categoria_id
    WHERE d.institution_id = ?
";
$params = [$instId];

if ($search) {
    $sql .= ' AND (d.descricao LIKE ? OR c.nome LIKE ? OR d.codigo LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY d.descricao ASC";

$st = $db->prepare($sql);
$st->execute($params);
$subjects = $st->fetchAll();

// Buscar categorias para o select
$stCat = $db->prepare("SELECT * FROM disciplina_categorias WHERE institution_id = ? ORDER BY nome ASC");
$stCat->execute([$instId]);
$allCategories = $stCat->fetchAll();

$pageTitle = 'Disciplinas';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.subjects-table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
.subjects-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.subjects-table th {
    padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted);
    background: var(--bg-surface-2nd); border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.subjects-table td { padding: .875rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.subjects-table tr:last-child td { border-bottom: none; }
.subjects-table tr:hover td { background: var(--bg-hover); }
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

/* Modal styles are now handled by core CSS */
</style>

<!-- Page Header -->
<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 class="page-title">📖 Disciplinas</h1>
        <p class="page-subtitle">
            Instituição: <strong><?= htmlspecialchars($inst['name']) ?></strong>
        </p>
    </div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="/subjects/categories.php" class="btn btn-secondary">📂 Categorias</a>
        <button class="btn btn-secondary" onclick="openImportModal()">📥 Importar CSV</button>
        <button class="btn btn-primary" onclick="openModal()">➕ Nova Disciplina</button>
    </div>
</div>

<?php if (empty($allCategories)): ?>
<div class="alert alert-warning fade-in" style="margin-bottom:1.5rem;">
    ⚠️ <strong>Atenção:</strong> Você precisa cadastrar pelo menos uma 
    <a href="/subjects/categories.php" style="color:inherit;font-weight:700;">Categoria</a> 
    antes de criar disciplinas.
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
                           placeholder="Buscar por nome ou categoria..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($search): ?>
            <a href="/subjects/index.php" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabela -->
<div class="card fade-in">
    <div class="card-header">
        <span class="card-title">Lista de Disciplinas</span>
        <span style="font-size:.875rem;color:var(--text-muted);"><?= count($subjects) ?> disciplina(s)</span>
    </div>
    <div class="subjects-table-wrap">
        <table class="subjects-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Cadastro</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjects)): ?>
                <tr>
                    <td colspan="5" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
                        📖 Nenhuma disciplina cadastrada ainda.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($subjects as $sub): ?>
                    <tr>
                        <td>
                            <span class="badge-profile badge-Outro" style="font-family:monospace;"><?= htmlspecialchars($sub['codigo']) ?></span>
                        </td>
                        <td>
                            <span style="font-weight:600;"><?= htmlspecialchars($sub['descricao']) ?></span>
                            <?php if (!empty($sub['observacoes'])): ?>
                            <div style="font-size:.75rem;color:var(--text-muted);margin-top:.25rem;max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= htmlspecialchars($sub['observacoes']) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-profile" style="background:var(--color-primary-light);color:var(--color-primary);">
                                <?= htmlspecialchars($sub['categoria_nome']) ?>
                            </span>
                        </td>
                        <td style="color:var(--text-muted);white-space:nowrap;font-size:.8125rem;">
                            <?= date('d/m/Y', strtotime($sub['created_at'])) ?>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                                <button class="action-btn" onclick='editSubject(<?= json_encode($sub) ?>)' title="Editar">✏️</button>
                                <button class="action-btn danger" onclick="deleteSubject('<?= htmlspecialchars(addslashes($sub['codigo'])) ?>', '<?= htmlspecialchars(addslashes($sub['descricao'])) ?>')" title="Excluir">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Adicionar/Editar Disciplina -->
<div id="subjectModal" class="modal-wrapper" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-overlay" onclick="closeModal()"></div>
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title" id="modalTitle">➕ Nova Disciplina</span>
                <button type="button" class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <form method="POST" id="subjectForm">
                <input type="hidden" name="old_codigo" id="field_old_codigo">
                <?= csrf_field() ?>
                <div class="modal-body">

                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:.875rem;">
                        <div class="form-group">
                            <label class="form-label">Código <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon">🔢</span>
                                <input type="text" name="codigo" id="field_codigo" class="form-control" 
                                    placeholder="Ex: MAT101" maxlength="15" required autofocus>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Descrição da Disciplina <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon">📖</span>
                                <input type="text" name="descricao" id="field_descricao" class="form-control" 
                                    placeholder="Ex: Matemática Aplicada II" required>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($allCategories)): ?>
                    <div class="alert alert-warning" style="margin:0;">
                        ⚠️ Cadastre uma <a href="/subjects/categories.php">categoria</a> primeiro.
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label class="form-label">Categoria <span class="required">*</span></label>
                        <select name="categoria_id" id="field_categoria_id" class="form-control" required>
                            <option value="" disabled selected>Selecione a categoria...</option>
                            <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" id="field_observacoes" class="form-control" rows="4" 
                                placeholder="Conteúdo programático, pré-requisitos, etc..."></textarea>
                        <small style="color:var(--text-muted);">Opcional. Você pode usar quebras de linha para organizar.</small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmit" <?= empty($allCategories) ? 'disabled' : '' ?>>
                        💾 Salvar Disciplina
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Importar Disciplinas -->
<div id="importFileModal" class="modal-wrapper" role="dialog" aria-modal="true">
    <div class="modal-overlay" onclick="closeImportModal()"></div>
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title">📥 Importar Disciplinas via CSV</span>
                <button type="button" class="modal-close" onclick="closeImportModal()">✕</button>
            </div>
            <form method="POST" action="?action=import_file" enctype="multipart/form-data" id="importForm">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div style="padding:1rem; border-radius:var(--radius-md); background:var(--bg-surface-2nd); border:1px dashed var(--border-color); margin-bottom:0.5rem;">
                        <p style="font-size:0.875rem; font-weight:600; margin-bottom:0.5rem; color:var(--text-primary);">📝 Layout do Arquivo CSV:</p>
                        <ul style="font-size:0.8125rem; color:var(--text-muted); padding-left:1.25rem;">
                            <li>O arquivo deve ser um **CSV** (delimitado por `;` ou `,`).</li>
                            <li>Colunas na ordem: **Código**, **Descrição**, **ID Categoria**.</li>
                            <li>A primeira linha (cabeçalho) pode ser ignorada.</li>
                            <li>Exemplo: `MAT101;Matemática I;5`</li>
                        </ul>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Selecione o arquivo (.csv)</label>
                        <div class="input-group">
                            <span class="input-icon">📄</span>
                            <input type="file" name="import_file" class="form-control" accept=".csv" required style="padding-left:2.75rem;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeImportModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">🚀 Iniciar Importação</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').textContent = '➕ Nova Disciplina';
    document.getElementById('subjectForm').action = '?action=add';
    document.getElementById('field_old_codigo').value = '';
    document.getElementById('field_codigo').value = '';
    document.getElementById('field_descricao').value = '';
    document.getElementById('field_categoria_id').value = '';
    document.getElementById('field_observacoes').value = '';
    document.getElementById('btnSubmit').textContent = '💾 Cadastrar';
    document.getElementById('subjectModal').classList.add('modal-show');
    document.body.style.overflow = 'hidden';
}

function editSubject(sub) {
    document.getElementById('modalTitle').textContent = '✏️ Editar Disciplina';
    document.getElementById('subjectForm').action = '?action=edit';
    document.getElementById('field_old_codigo').value = sub.codigo;
    document.getElementById('field_codigo').value = sub.codigo;
    document.getElementById('field_descricao').value = sub.descricao;
    document.getElementById('field_categoria_id').value = sub.categoria_id;
    document.getElementById('field_observacoes').value = sub.observacoes || '';
    document.getElementById('btnSubmit').textContent = '💾 Atualizar';
    document.getElementById('subjectModal').classList.add('modal-show');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('subjectModal').classList.remove('modal-show');
    document.body.style.overflow = '';
}

function openImportModal() {
    document.getElementById('importFileModal').classList.add('modal-show');
    document.body.style.overflow = 'hidden';
}

function closeImportModal() {
    document.getElementById('importFileModal').classList.remove('modal-show');
    document.body.style.overflow = '';
}

function deleteSubject(codigo, name) {
    Modal.confirm({
        title: 'Excluir Disciplina',
        message: `Excluir permanentemente a disciplina «${name}»? Esta ação não pode ser desfeita.`,
        confirmText: 'Sim, Excluir',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?action=delete';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'codigo';
            input.value = codigo;
            form.appendChild(input);
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = 'csrf_token';
            csrf.value = '<?= csrf_token() ?>';
            form.appendChild(csrf);
            document.body.appendChild(form);
            form.submit();
        }
    });
}

const importForm = document.getElementById('importForm');
if (importForm) importForm.addEventListener('submit', () => { if(typeof Loading !== 'undefined') Loading.show('Importando...'); });

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeImportModal();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    <?php if ($success): ?> Toast.success(<?= json_encode($success) ?>); <?php endif; ?>
    <?php if ($error): ?> Toast.error(<?= json_encode($error) ?>); <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
