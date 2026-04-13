<?php
/**
 * Vértice Acadêmico — NAAPI (Acompanhamento Especializado)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/NaapiService.php';

requireLogin();
hasDbPermission('naapi.index');

$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = $inst['id'] ?? 0;

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/naapi/index.php'));
    exit;
}

require_once __DIR__ . '/../includes/modal.php';
require_once __DIR__ . '/../includes/toast.php';

$service = new \App\Services\NaapiService();
$search = trim($_GET['search'] ?? '');
$students = $service->getAll($instId, $search);

$pageTitle = "NAAPI";
require_once __DIR__ . '/../includes/header.php';
renderModalStyles(); 
?>

<style>
.naapi-table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
.naapi-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.naapi-table th {
    padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted);
    background: var(--bg-surface-2nd); border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.naapi-table td { padding: .875rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.naapi-table tr:hover td { background: var(--bg-hover); }

.student-info { display: flex; align-items: center; gap: .75rem; }
.student-avatar { 
    width: 32px; height: 32px; border-radius: 50%; object-fit: cover; 
    background: var(--bg-surface-2nd); display: flex; align-items: center; 
    justify-content: center; font-weight: 700; font-size: .75rem; color: var(--text-muted);
}

.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: var(--radius-md);
    border: 1px solid var(--border-color); background: var(--bg-surface);
    color: var(--text-muted); cursor: pointer; transition: all var(--transition-fast);
}
.action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
.action-btn.danger:hover { color: var(--color-danger); border-color: var(--color-danger); }

/* Estilos para o Autocomplete de Alunos */
.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    z-index: 1000;
    max-height: 300px;
    overflow-y: auto;
    margin-top: 5px;
}
.search-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: background var(--transition-fast);
    border-bottom: 1px solid var(--border-color);
}
.search-item:last-child { border-bottom: none; }
.search-item:hover { background: var(--bg-hover); }
.search-item img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    background: var(--bg-hover);
}
.search-item .no-photo {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--color-primary-light);
    color: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
}
.search-item-info {
    display: flex;
    flex-direction: column;
}
.search-item-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
}
.search-item-meta {
    font-size: 0.75rem;
    color: var(--text-muted);
}
</style>

<div class="page-header fade-in" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom: 1.5rem;">
    <div>
        <h1 class="page-title">🧠 NAAPI</h1>
        <p class="page-subtitle">Acompanhamento e Atendimento Especializado</p>
    </div>
    <div style="display:flex; gap:.75rem;">
        <?php if (hasDbPermission('naapi.manage', false)): ?>
        <button class="btn btn-primary" onclick="openNaapiModal()">➕ Adicionar Aluno</button>
        <?php endif; ?>
    </div>
</div>

<!-- Filtro -->
<div class="card fade-in" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.5rem;">
        <form method="GET" style="display:flex; gap:.75rem; flex-wrap:wrap;">
            <div class="form-group" style="flex:1; min-width:250px; margin:0;">
                <div class="input-group">
                    <span class="input-icon">🔍</span>
                    <input type="text" name="search" class="form-control" placeholder="Buscar por aluno, matrícula ou neurodivergência..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($search): ?>
            <a href="/naapi/index.php" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Listagem -->
<div class="card fade-in">
    <div class="naapi-table-wrap">
        <table class="naapi-table">
            <thead>
                <tr>
                    <th>Aluno</th>
                    <th>Matrícula</th>
                    <th>Neurodivergência</th>
                    <th>Data Inclusão</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">
                        Nenhum aluno cadastrado no NAAPI nesta instituição.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td>
                            <div class="student-info">
                                <?php if ($s['aluno_photo']): ?>
                                    <img src="/<?= htmlspecialchars($s['aluno_photo']) ?>" class="student-avatar" alt="">
                                <?php else: ?>
                                    <div class="student-avatar"><?= strtoupper(substr($s['aluno_nome'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight:600;"><?= htmlspecialchars($s['aluno_nome']) ?></div>
                                    <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($s['curso_nome'] ?? 'Sem curso') ?> — <?= htmlspecialchars($s['turma_nome'] ?? 'Sem turma') ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge-profile badge-Outro"><?= htmlspecialchars($s['aluno_matricula']) ?></span></td>
                        <td>
                            <?php if ($s['neurodivergencia']): ?>
                            <span class="badge-profile" style="background:var(--color-primary-light); color:var(--color-primary);">
                                <?= htmlspecialchars($s['neurodivergencia']) ?>
                            </span>
                            <?php else: ?>
                            <span style="color:var(--text-muted); font-style:italic; font-size: 0.8rem;">Não informada</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--text-muted); font-size:0.8125rem;">
                            <?= date('d/m/Y', strtotime($s['data_inclusao'])) ?>
                        </td>
                        <td>
                            <div style="display:flex; justify-content:center; gap:.5rem;">
                                <button class="action-btn" onclick="editNaapi(<?= $s['id'] ?>)" title="Editar">✏️</button>
                                <?php if (hasDbPermission('naapi.manage', false)): ?>
                                <button class="action-btn danger" onclick="deleteNaapi(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['aluno_nome'])) ?>')" title="Remover do NAAPI">🗑️</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Adicionar/Editar Aluno no NAAPI -->
<div id="naapiModal" class="modal-backdrop">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3 class="modal-title" id="naapiModalTitle">Adicionar Aluno ao NAAPI</h3>
            <button class="modal-close" onclick="closeModal('naapiModal')">&times;</button>
        </div>
        <form id="naapiForm" onsubmit="saveNaapi(event)">
            <input type="hidden" name="id" id="field_id">
            <input type="hidden" name="aluno_id" id="field_aluno_id">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="modal-body">
                <!-- Busca de Aluno (Apenas para novos registros) -->
                <div class="form-group" id="aluno_search_group" style="position:relative;">
                    <label class="form-label">Buscar Aluno <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">🔍</span>
                        <input type="text" id="aluno_search_input" class="form-control" placeholder="Nome ou Matrícula..." oninput="searchAlunos(this.value)" autocomplete="off">
                    </div>
                    <div id="searchAlunoResults" class="search-results" style="display:none;"></div>
                </div>

                <!-- Info do Aluno Selecionado -->
                <div id="selected_aluno_info" style="display:none; background:var(--bg-surface-2nd); padding:.75rem; border-radius:8px; margin-bottom:1rem; border:1px solid var(--border-color);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Aluno Selecionado</div>
                            <div id="display_aluno_name" style="font-weight:600; color:var(--text-primary);"></div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" id="btn_change_aluno" onclick="clearAlunoSelection()" style="color:var(--color-danger);">Trocar</button>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Data de Inclusão <span class="required">*</span></label>
                        <input type="date" name="data_inclusao" id="field_data_inclusao" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Neurodivergência</label>
                        <input type="text" name="neurodivergencia" id="field_neurodivergencia" class="form-control" placeholder="Ex: TEA, TDAH, etc...">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Informações Adicionais (Restrito ao NAAPI)</label>
                    <textarea name="campo_texto" id="field_campo_texto" class="form-control" rows="3" placeholder="Detalhes técnicos, laudos, etc..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Observações Públicas (Visível para Coordenação)</label>
                    <textarea name="observacoes_publicas" id="field_observacoes_publicas" class="form-control" rows="3" placeholder="Orientações pedagógicas gerais..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('naapiModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Registro</button>
            </div>
        </form>
    </div>
</div>

<script>
let searchTimeout = null;

function openNaapiModal() {
    document.getElementById('naapiForm').reset();
    document.getElementById('field_id').value = '';
    document.getElementById('naapiModalTitle').innerText = 'Adicionar Aluno ao NAAPI';
    document.getElementById('aluno_search_group').style.display = 'block';
    document.getElementById('selected_aluno_info').style.display = 'none';
    document.getElementById('field_data_inclusao').value = '<?= date('Y-m-d') ?>';
    openModal('naapiModal');
}

function searchAlunos(query) {
    if (searchTimeout) clearTimeout(searchTimeout);
    const resultsDiv = document.getElementById('searchAlunoResults');
    
    if (query.trim().length < 3) {
        resultsDiv.style.display = 'none';
        return;
    }

    searchTimeout = setTimeout(async () => {
        try {
            const response = await fetch('/api/naapi.php?action=search_alunos&q=' + encodeURIComponent(query));
            const data = await response.json();
            
            if (data.success && data.alunos.length > 0) {
                let h = '';
                data.alunos.forEach(a => {
                    const photoHtml = a.photo 
                        ? `<img src="/${a.photo}" alt="${a.nome}">` 
                        : `<div class="no-photo">${a.nome.charAt(0)}</div>`;
                    
                    h += `
                    <div class="search-item" onclick="selectAluno(${a.id}, '${a.nome.replace(/'/g, "\\'")}', '${a.matricula}')">
                        ${photoHtml}
                        <div class="search-item-info">
                            <span class="search-item-name">${a.nome}</span>
                            <span class="search-item-meta">Matrícula: ${a.matricula}</span>
                        </div>
                    </div>`;
                });
                resultsDiv.innerHTML = h;
                resultsDiv.style.display = 'block';
            } else {
                resultsDiv.innerHTML = '<div style="padding:.75rem; color:var(--text-muted); font-size:.875rem;">Nenhum aluno disponível encontrado.</div>';
                resultsDiv.style.display = 'block';
            }
        } catch (e) {
            console.error(e);
        }
    }, 400);
}

function selectAluno(id, name, matricula) {
    document.getElementById('field_aluno_id').value = id;
    document.getElementById('display_aluno_name').innerText = name + ' (#' + matricula + ')';
    document.getElementById('aluno_search_group').style.display = 'none';
    document.getElementById('selected_aluno_info').style.display = 'block';
    document.getElementById('searchAlunoResults').style.display = 'none';
}

function clearAlunoSelection() {
    document.getElementById('field_aluno_id').value = '';
    document.getElementById('aluno_search_group').style.display = 'block';
    document.getElementById('selected_aluno_info').style.display = 'none';
    document.getElementById('aluno_search_input').value = '';
    document.getElementById('aluno_search_input').focus();
}

async function editNaapi(id) {
    try {
        const response = await fetch('/api/naapi.php?action=get&id=' + id);
        const res = await response.json();
        
        if (res.success) {
            const data = res.data;
            document.getElementById('field_id').value = data.id;
            document.getElementById('field_aluno_id').value = data.aluno_id;
            document.getElementById('display_aluno_name').innerText = data.aluno_nome + ' (#' + data.aluno_matricula + ')';
            
            document.getElementById('field_data_inclusao').value = data.data_inclusao;
            document.getElementById('field_neurodivergencia').value = data.neurodivergencia || '';
            document.getElementById('field_campo_texto').value = data.campo_texto || '';
            document.getElementById('field_observacoes_publicas').value = data.observacoes_publicas || '';
            
            document.getElementById('naapiModalTitle').innerText = 'Editar Registro NAAPI';
            document.getElementById('aluno_search_group').style.display = 'none';
            document.getElementById('selected_aluno_info').style.display = 'block';
            document.getElementById('btn_change_aluno').style.display = 'none'; // Não permite trocar o aluno na edição
            
            openModal('naapiModal');
        } else {
            Toast.error(res.error);
        }
    } catch (e) {
        Toast.error('Erro ao buscar dados.');
    }
}

async function saveNaapi(e) {
    e.preventDefault();
    const form = document.getElementById('naapiForm');
    const formData = new FormData(form);
    formData.append('action', 'save');

    try {
        if (typeof Loading !== 'undefined') Loading.show('Salvando...');
        
        const response = await fetch('/api/naapi.php', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        
        if (typeof Loading !== 'undefined') Loading.hide();

        if (res.success) {
            Toast.success(res.message);
            closeModal('naapiModal');
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.error(res.error);
        }
    } catch (error) {
        if (typeof Loading !== 'undefined') Loading.hide();
        Toast.error('Erro ao salvar registro.');
    }
}

function deleteNaapi(id, name) {
    Modal.confirm({
        title: 'Remover do NAAPI',
        message: `Tem certeza que deseja remover o aluno <strong>${name}</strong> do NAAPI? Esta ação não exclui o aluno do sistema, apenas seu registro no núcleo.`,
        confirmText: 'Sim, Remover',
        confirmClass: 'btn-danger',
        onConfirm: async () => {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            formData.append('csrf_token', '<?= csrf_token() ?>');

            try {
                if (typeof Loading !== 'undefined') Loading.show('Removendo...');
                const response = await fetch('/api/naapi.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
                if (typeof Loading !== 'undefined') Loading.hide();

                if (res.success) {
                    Toast.success(res.message);
                    setTimeout(() => location.reload(), 800);
                } else {
                    Toast.error(res.error);
                }
            } catch (e) {
                if (typeof Loading !== 'undefined') Loading.hide();
                Toast.error('Erro ao remover registro.');
            }
        }
    });
}
</script>

<?php 
renderModalScripts();
renderToastScripts();
require_once __DIR__ . '/../includes/footer.php'; 
?>
