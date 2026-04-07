<?php
/**
 * Vértice Acadêmico — Configurações de Sanções Disciplinares
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
hasDbPermission('sancoes.config');

$db = getDB();
$inst = getCurrentInstitution();
$instId = $inst['id'];

$success = '';
$error = '';

// --- PROCESSAMENTO POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança expirado.';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            // CRUD Tipos
            if ($action === 'save_tipo') {
                $id = (int)($_POST['id'] ?? 0);
                $titulo = trim($_POST['titulo'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                
                if (empty($titulo)) throw new Exception("O título é obrigatório.");
                
                if ($id > 0) {
                    $st = $db->prepare("UPDATE sancao_tipo SET titulo = ?, descricao = ? WHERE id = ? AND institution_id = ?");
                    $st->execute([$titulo, $descricao, $id, $instId]);
                    $success = "Tipo de sanção atualizado com sucesso.";
                } else {
                    $st = $db->prepare("INSERT INTO sancao_tipo (titulo, descricao, institution_id) VALUES (?, ?, ?)");
                    $st->execute([$titulo, $descricao, $instId]);
                    $success = "Novo tipo de sanção cadastrado.";
                }
            }
            
            elseif ($action === 'toggle_tipo') {
                $id = (int)($_POST['id'] ?? 0);
                $active = (int)($_POST['active'] ?? 0);
                $st = $db->prepare("UPDATE sancao_tipo SET is_active = ? WHERE id = ? AND institution_id = ?");
                $st->execute([$active, $id, $instId]);
                $success = $active ? "Tipo de sanção reativado." : "Tipo de sanção desativado.";
            }

            // CRUD Ações
            elseif ($action === 'save_acao') {
                $id = (int)($_POST['id'] ?? 0);
                $descricao = trim($_POST['descricao'] ?? '');
                
                if (empty($descricao)) throw new Exception("A descrição é obrigatória.");
                
                if ($id > 0) {
                    $st = $db->prepare("UPDATE sancao_acao SET descricao = ? WHERE id = ? AND institution_id = ?");
                    $st->execute([$descricao, $id, $instId]);
                    $success = "Fato gerador de sanção atualizado.";
                } else {
                    $st = $db->prepare("INSERT INTO sancao_acao (descricao, institution_id) VALUES (?, ?)");
                    $st->execute([$descricao, $instId]);
                    $success = "Novo fato gerador cadastrado.";
                }
            }
            
            elseif ($action === 'toggle_acao') {
                $id = (int)($_POST['id'] ?? 0);
                $active = (int)($_POST['active'] ?? 0);
                $st = $db->prepare("UPDATE sancao_acao SET is_active = ? WHERE id = ? AND institution_id = ?");
                $st->execute([$active, $id, $instId]);
                $success = $active ? "Fato gerador reativado." : "Fato gerador desativado.";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// --- BUSCA DADOS ---
$tipos = $db->prepare("SELECT * FROM sancao_tipo WHERE institution_id = ? ORDER BY is_active DESC, titulo ASC");
$tipos->execute([$instId]);
$tipos = $tipos->fetchAll();

$acoes = $db->prepare("SELECT * FROM sancao_acao WHERE institution_id = ? ORDER BY is_active DESC, descricao ASC");
$acoes->execute([$instId]);
$acoes = $acoes->fetchAll();

$pageTitle = 'Configurações de Sanções';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.config-tabs {
    display: flex;
    gap: 1.5rem;
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 2rem;
}
.config-tab {
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all var(--transition-fast);
}
.config-tab:hover { color: var(--text-primary); }
.config-tab.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
}
.tab-content { display: none; }
.tab-content.active { display: block; }

.status-badge {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 2px 6px;
    border-radius: 4px;
}
.status-active { background: #dcfce7; color: #166534; }
.status-inactive { background: #fee2e2; color: #991b1b; }

.config-table { width: 100%; border-collapse: collapse; }
.config-table th { 
    text-align: left; padding: 0.75rem 1rem; 
    background: var(--bg-surface-2nd); color: var(--text-muted);
    font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;
    border-bottom: 1px solid var(--border-color);
}
.config-table td { 
    padding: 1rem; border-bottom: 1px solid var(--border-color); 
    vertical-align: middle;
}
.config-table tr:hover td { background: var(--bg-hover); }

.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 6px;
    border: 1px solid var(--border-color); background: var(--bg-surface);
    color: var(--text-muted); cursor: pointer; transition: all 0.2s;
}
.action-btn:hover { border-color: var(--color-primary); color: var(--color-primary); }
</style>

<div class="page-header fade-in">
    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
        <div>
            <div style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 0.25rem;">
                <a href="/sancao/index.php" style="color: var(--color-primary);">⚖️ Sanções</a> › Configurações
            </div>
            <h1 class="page-title">⚙️ Tabelas Auxiliares</h1>
            <p class="page-subtitle">Personalize os tipos de sanção e as ações automáticas.</p>
        </div>
        <a href="/sancao/index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<?php if($success): ?>
    <div class="alert alert-success mt-md"><?= $success ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="alert alert-danger mt-md"><?= $error ?></div>
<?php endif; ?>

<div class="card fade-in" style="margin-top: 1.5rem;">
    <div class="card-body">
        <div class="config-tabs">
            <div class="config-tab active" onclick="switchConfigTab(event, 'tab-tipos')">Tipos de Sanção</div>
            <div class="config-tab" onclick="switchConfigTab(event, 'tab-acoes')">Fatos Geradores</div>
        </div>

        <!-- ABA: Tipos de Sanção -->
        <div id="tab-tipos" class="tab-content active">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.125rem;">Categorias de Sanções</h3>
                <button class="btn btn-primary btn-sm" onclick="openTipoForm()">+ Novo Tipo</button>
            </div>
            
            <table class="config-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Descrição</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 100px; text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tipos as $t): ?>
                    <tr style="<?= !$t['is_active'] ? 'opacity: 0.6;' : '' ?>">
                        <td>
                            <div style="font-weight: 600;"><?= htmlspecialchars($t['titulo']) ?></div>
                        </td>
                        <td style="font-size: 0.875rem; color: var(--text-muted);">
                            <?= htmlspecialchars($t['descricao'] ?: '—') ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $t['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $t['is_active'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                <button class="action-btn" title="Editar" onclick='openTipoForm(<?= json_encode($t, ENT_QUOTES) ?>)'>✏️</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Deseja alterar o status deste item?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_tipo">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <input type="hidden" name="active" value="<?= $t['is_active'] ? '0' : '1' ?>">
                                    <button type="submit" class="action-btn" title="<?= $t['is_active'] ? 'Desativar' : 'Reativar' ?>">
                                        <?= $t['is_active'] ? '🚫' : '✅' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ABA: Fatos Geradores -->
        <div id="tab-acoes" class="tab-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.125rem;">Catálogo de Fatos Geradores</h3>
                <button class="btn btn-primary btn-sm" onclick="openAcaoForm()">+ Novo Fato</button>
            </div>

            <table class="config-table">
                <thead>
                    <tr>
                        <th>Descrição do Fato Gerador</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 100px; text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($acoes as $a): ?>
                    <tr style="<?= !$a['is_active'] ? 'opacity: 0.6;' : '' ?>">
                        <td style="font-weight: 600;">
                            <?= htmlspecialchars($a['descricao']) ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $a['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $a['is_active'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                <button class="action-btn" title="Editar" onclick='openAcaoForm(<?= json_encode($a, ENT_QUOTES) ?>)'>✏️</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Deseja alterar o status deste item?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_acao">
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="active" value="<?= $a['is_active'] ? '0' : '1' ?>">
                                    <button type="submit" class="action-btn" title="<?= $a['is_active'] ? 'Desativar' : 'Reativar' ?>">
                                        <?= $a['is_active'] ? '🚫' : '✅' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function switchConfigTab(event, tabId) {
    document.querySelectorAll('.config-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    const targetTab = event ? event.currentTarget : document.querySelector(`.config-tab[onclick*="${tabId}"]`);
    if (targetTab) targetTab.classList.add('active');
    
    document.getElementById(tabId).classList.add('active');
    
    // Persist tab
    sessionStorage.setItem('sancao_config_active_tab', tabId);
}

// Restore tab on load
document.addEventListener('DOMContentLoaded', () => {
    const savedTab = sessionStorage.getItem('sancao_config_active_tab');
    if (savedTab && document.getElementById(savedTab)) {
        switchConfigTab(null, savedTab);
    }
});

function openTipoForm(data = null) {
    const isEdit = !!data;
    const title = isEdit ? 'Editar Tipo de Sanção' : 'Novo Tipo de Sanção';
    
    const content = `
        <form id="formTipoSancao" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_tipo">
            <input type="hidden" name="id" value="${isEdit ? data.id : ''}">
            <div class="form-group">
                <label class="form-label">Título <span class="required">*</span></label>
                <input type="text" name="titulo" class="form-control" value="${isEdit ? data.titulo : ''}" required>
            </div>
            <div class="form-group" style="margin-top: 1rem;">
                <label class="form-label">Descrição</label>
                <textarea name="descricao" class="form-control" rows="3">${isEdit ? (data.descricao || '') : ''}</textarea>
            </div>
        </form>
    `;
    
    showModal({
        title,
        content,
        buttons: [
            { text: 'Cancelar', class: 'btn-secondary', action: (e, modalId) => closeModal(modalId) },
            { text: 'Salvar', class: 'btn-primary', action: () => document.getElementById('formTipoSancao').submit() }
        ]
    });
}

function openAcaoForm(data = null) {
    const isEdit = !!data;
    const title = isEdit ? 'Editar Fato Gerador' : 'Novo Fato Gerador';
    
    const content = `
        <form id="formAcaoSancao" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_acao">
            <input type="hidden" name="id" value="${isEdit ? data.id : ''}">
            <div class="form-group">
                <label class="form-label">Descrição do Fato <span class="required">*</span></label>
                <input type="text" name="descricao" class="form-control" value="${isEdit ? data.descricao : ''}" required>
            </div>
        </form>
    `;
    
    showModal({
        title,
        content,
        buttons: [
            { text: 'Cancelar', class: 'btn-secondary', action: (e, modalId) => closeModal(modalId) },
            { text: 'Salvar', class: 'btn-primary', action: () => document.getElementById('formAcaoSancao').submit() }
        ]
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
