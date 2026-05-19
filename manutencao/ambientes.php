<?php
/**
 * Vértice Acadêmico — Cadastro de Ambientes (Manutenção)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/Manutencao/AmbienteService.php';

requireLogin();

$user = getCurrentUser();
hasDbPermission('manutencao.ambientes');

$inst = getCurrentInstitution();
$instId = $inst['id'];

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/manutencao/ambientes.php'));
    exit;
}

$ambienteService = new \App\Services\Manutencao\AmbienteService();
$search = trim($_GET['search'] ?? '');
$ambientes = $ambienteService->getAll($instId, $search);
$problemasPadrao = $ambienteService->getAllProblemasPadrao();

$pageTitle = 'Cadastro de Ambientes';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.ambientes-table-wrap { overflow-x:auto; border-radius:var(--radius-lg); }
.ambientes-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.ambientes-table th {
    padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:600;
    text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted);
    background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color);
    white-space:nowrap;
}
.ambientes-table td { padding:.875rem 1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; }
.ambientes-table tr:last-child td { border-bottom:none; }
.ambientes-table tr:hover td { background:var(--bg-hover); }

.badge-prob {
    display:inline-block; padding:2px 8px; border-radius:12px;
    background:var(--bg-surface-2nd); color:var(--text-secondary);
    font-size:.75rem; font-weight:500; margin-right:4px; margin-bottom:4px;
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

.problemas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.problema-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border-radius: var(--radius-md);
    background: var(--bg-surface-2nd);
    cursor: pointer;
    transition: background 0.2s;
}
.problema-item:hover { background: var(--bg-hover); }
.problema-item input { cursor: pointer; }
</style>

<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <div>
        <h1 class="page-title">🛠️ Cadastro de Ambientes</h1>
        <p class="page-subtitle">
            Gestão de espaços físicos e problemas padrão do campus.
        </p>
    </div>
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
        <button type="button" class="btn btn-secondary" onclick="openQrCodeModal(0)">
            <span>📄</span>
            <span>Imprimir QR Codes</span>
        </button>
        <button class="btn btn-primary" onclick="openAmbienteModal()">
            <span class="btn-icon">➕</span>
            <span class="btn-text">Novo Ambiente</span>
        </button>
    </div>
</div>

<!-- Filtro -->
<div class="card fade-in" style="margin-bottom:1.5rem;">
    <div class="card-body" style="padding:1.25rem 1.5rem;">
        <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:280px;margin:0;">
                <label class="form-label" style="font-size:0.75rem;margin-bottom:0.375rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Buscar Ambientes</label>
                <div class="input-group">
                    <span class="input-icon">🔍</span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Descrição ou Prédio/Campus..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div style="display:flex;gap:0.5rem;">
                <button type="submit" class="btn btn-secondary">Filtrar</button>
                <?php if ($search): ?>
                <a href="ambientes.php" class="btn btn-ghost">Limpar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Resultados -->
<div class="card fade-in">
    <div class="card-header">
        <span class="card-title">Listagem de Ambientes</span>
        <span class="badge-outro-bg" style="font-size:0.75rem;padding:0.25rem 0.625rem;border-radius:var(--radius-full);color:var(--text-muted);font-weight:600;">
            <?= count($ambientes) ?> Ambiente(s)
        </span>
    </div>
    <div class="ambientes-table-wrap">
        <table class="ambientes-table">
            <thead>
                <tr>
                    <th style="width:60px;">#</th>
                    <th>Descrição</th>
                    <th>Prédio/Campus</th>
                    <th>Problemas Padrão</th>
                    <th style="width:120px;">Status</th>
                    <th style="width:60px;">QR Code</th>
                    <th style="width:100px;text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ambientes)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:5rem 2rem;">
                        <div style="display:flex;flex-direction:column;align-items:center;gap:1rem;">
                            <div style="width:64px;height:64px;background:var(--bg-surface-2nd);border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--text-muted);border:1px solid var(--border-color);">
                                🏢
                            </div>
                            <div style="text-align:center;">
                                <h4 style="margin:0;color:var(--text-primary);font-weight:600;">Nenhum ambiente encontrado</h4>
                                <p style="margin:0.25rem 0 0;color:var(--text-muted);font-size:0.875rem;">
                                    <?= $search ? 'Nenhum resultado para a busca realizada.' : 'Comece cadastrando o primeiro ambiente da escola.' ?>
                                </p>
                            </div>
                            <?php if (!$search): ?>
                            <button class="btn btn-primary btn-sm" style="margin-top:0.5rem;" onclick="openAmbienteModal()">Cadastrar Agora</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <?php foreach ($ambientes as $a): 
                    $probs = $ambienteService->findById($a['id'])['problemas'];
                ?>
                <tr id="row-<?= $a['id'] ?>" style="<?= $a['status'] === 'Inativo' ? 'opacity:.6' : '' ?>">
                    <td style="color:var(--text-muted);"><?= $a['id'] ?></td>
                    <td><strong class="text-primary"><?= htmlspecialchars($a['descricao']) ?></strong></td>
                    <td><?= htmlspecialchars($a['predio_campus']) ?></td>
                    <td>
                        <?php if (empty($probs)): ?>
                            <span class="text-muted" style="font-size:.8125rem;">Nenhum problema vinculado</span>
                        <?php else: ?>
                            <?php foreach ($probs as $p): ?>
                                <span class="badge-prob"><?= htmlspecialchars($p['descricao']) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $a['status'] === 'Ativo' ? 'badge-success' : 'badge-danger' ?>">
                            <?= $a['status'] ?>
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <button type="button" class="action-btn" onclick="openQrCodeModal(<?= $a['id'] ?>)" title="Ver / Imprimir QR Code">📱</button>
                    </td>
                    <td>
                        <div style="display:flex;justify-content:center;gap:.5rem;">
                            <button class="action-btn" onclick="editAmbiente(<?= $a['id'] ?>)" title="Editar">✏️</button>
                            <button class="action-btn danger" onclick="deleteAmbiente(<?= $a['id'] ?>, '<?= addslashes($a['descricao']) ?>')" title="Excluir">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Ambiente -->
<div id="ambienteModal" class="modal-wrapper" role="dialog" aria-modal="true">
    <div class="modal-overlay" onclick="closeModal('ambienteModal')">
        <div class="modal-dialog modal-md" onclick="event.stopPropagation()">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalTitle">Novo Ambiente</h3>
                    <button type="button" class="modal-close" onclick="closeModal('ambienteModal')">✕</button>
                </div>
                <form id="ambienteForm">
                    <input type="hidden" id="ambienteId" name="id">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Descrição <span class="required">*</span></label>
                            <input type="text" class="form-control" name="descricao" id="f_descricao" required placeholder="Ex: Sala de Aula 101">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Prédio / Campus <span class="required">*</span></label>
                            <input type="text" class="form-control" name="predio_campus" id="f_predio_campus" required placeholder="Ex: Bloco A - Campus Sede">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" id="f_status">
                                <option value="Ativo">Ativo</option>
                                <option value="Inativo">Inativo</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Problemas Padrão Vincular</label>
                            <div class="problemas-grid">
                                <?php foreach ($problemasPadrao as $p): ?>
                                <label class="problema-item">
                                    <input type="checkbox" name="problemas[]" value="<?= $p['id'] ?>" class="prob-check">
                                    <span><?= htmlspecialchars($p['descricao']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('ambienteModal')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Ambiente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: QR Code Print -->
<div id="qrCodeModal" class="modal-wrapper" role="dialog" aria-modal="true" style="z-index: 1050;">
    <div class="modal-overlay" onclick="closeQrCodeModal()">
        <div class="modal-dialog" style="max-width: 90%; width: 980px; height: 90vh; display: flex; flex-direction: column;" onclick="event.stopPropagation()">
            <div class="modal-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; height: 100%;">
                <div class="modal-header" style="flex-shrink: 0;">
                    <h3 class="modal-title">Imprimir QR Codes</h3>
                    <button type="button" class="modal-close" onclick="closeQrCodeModal()">✕</button>
                </div>
                <div class="modal-body" style="flex: 1; padding: 0; position: relative; overflow: hidden;">
                    <iframe id="qrCodeIframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    const m = document.getElementById(id);
    if (m) {
        m.classList.remove('modal-hide');
        m.classList.add('modal-show');
    }
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) {
        m.classList.remove('modal-show');
        m.classList.add('modal-hide');
    }
}

function openQrCodeModal(ambienteId = 0) {
    const iframe = document.getElementById('qrCodeIframe');
    const url = 'qrcode_print.php?iframe=1' + (ambienteId ? '&ambiente_id=' + ambienteId : '');
    iframe.src = url;
    openModal('qrCodeModal');
}

function closeQrCodeModal() {
    const iframe = document.getElementById('qrCodeIframe');
    iframe.src = '';
    closeModal('qrCodeModal');
}

function openAmbienteModal() {
    document.getElementById('ambienteForm').reset();
    document.getElementById('ambienteId').value = '';
    document.getElementById('modalTitle').innerText = 'Novo Ambiente';
    openModal('ambienteModal');
}

async function editAmbiente(id) {
    showLoading();
    try {
        const res = await fetch(`../api/manutencao/ambientes_ajax.php?action=get&id=${id}`);
        const data = await res.json();
        hideLoading();

        if (data.success) {
            const amb = data.data;
            document.getElementById('ambienteId').value = amb.id;
            document.getElementById('f_descricao').value = amb.descricao;
            document.getElementById('f_predio_campus').value = amb.predio_campus;
            document.getElementById('f_status').value = amb.status;

            // Reset checkboxes
            document.querySelectorAll('.prob-check').forEach(ck => ck.checked = false);
            
            // Check current problems
            if (amb.problemas) {
                amb.problemas.forEach(p => {
                    const ck = document.querySelector(`.prob-check[value="${p.id}"]`);
                    if (ck) ck.checked = true;
                });
            }

            document.getElementById('modalTitle').innerText = 'Editar Ambiente';
            openModal('ambienteModal');
        } else {
            Toast.error(data.message || 'Erro ao carregar dados.');
        }
    } catch (e) {
        hideLoading();
        Toast.error('Erro na requisição.');
    }
}

document.getElementById('ambienteForm').onsubmit = async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const id = formData.get('id');
    const action = id ? 'update' : 'create';

    showLoading();
    try {
        const res = await fetch(`../api/manutencao/ambientes_ajax.php?action=${action}`, {
            method: 'POST',
            body: formData,
            headers: { 
                'X-CSRF-TOKEN': window.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const data = await res.json();
        hideLoading();

        if (data.success) {
            Toast.success(data.message || 'Salvo com sucesso!');
            closeModal('ambienteModal');
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.error(data.message || 'Erro ao salvar.');
        }
    } catch (e) {
        hideLoading();
        Toast.error('Erro na requisição.');
    }
};

function deleteAmbiente(id, nome) {
    Modal.confirm({
        title: 'Excluir Ambiente',
        message: `Tem certeza que deseja excluir o ambiente <strong>${nome}</strong>?`,
        confirmText: 'Sim, Excluir',
        confirmClass: 'btn-danger',
        onConfirm: async () => {
            showLoading();
            try {
                const res = await fetch(`../api/manutencao/ambientes_ajax.php?action=delete`, {
                    method: 'POST',
                    body: new URLSearchParams({ id: id }),
                    headers: { 
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': window.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await res.json();
                hideLoading();

                if (data.success) {
                    Toast.success('Ambiente excluído!');
                    document.getElementById(`row-${id}`).remove();
                } else {
                    Toast.error(data.message || 'Erro ao excluir.');
                }
            } catch (e) {
                hideLoading();
                Toast.error('Erro na requisição.');
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
