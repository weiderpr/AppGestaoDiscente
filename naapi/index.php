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

/* Modal 80% e Abas (Padrão do Sistema) */
.modal-80 { 
    width: 80vw !important; height: 80vh !important; max-width: none !important; 
    display: flex !important; flex-direction: column !important; overflow: hidden !important;
}
.modal-80 .modal-body { flex: 1; overflow-y: auto; padding: 1.5rem 2rem; }

.tabs-nav { 
    display: flex; border-bottom: 2px solid var(--border-color); 
    background: var(--bg-surface-2nd); padding: 0 1rem; flex-shrink: 0; 
}
.tab-btn { 
    background: none; border: none; padding: 0.875rem 1.25rem; font-size: 0.875rem; 
    font-weight: 600; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; 
    transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem; 
}
.tab-btn:hover { color: var(--color-primary); background: var(--bg-surface); }
.tab-btn.active { color: var(--color-primary); border-bottom-color: var(--color-primary); background: var(--bg-surface); }
.tab-btn[disabled] { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }

.tab-content { display: none; flex: 1; flex-direction: column; overflow: hidden; height: 100%; }
.tab-content.active { display: flex !important; animation: fadeIn 0.3s ease; }

/* Sub-abas NAAPI */
.naapi-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0; }
.naapi-tab-btn { background: none; border: none; padding: 0.5rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; }
.naapi-tab-btn:hover { color: var(--text-secondary); }
.naapi-tab-btn.active { color: var(--color-primary); border-bottom-color: var(--color-primary); }

/* Estilos de Anexos */
.anexo-item { 
    display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1rem; 
    background: var(--bg-surface-2nd); border-radius: var(--radius-md); 
    border: 1px solid var(--border-color); margin-bottom: 0.5rem; transition: all 0.2s ease; 
}
.anexo-item:hover { border-color: var(--color-primary); background: var(--bg-surface); }
.anexo-icon { 
    width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; 
    background: var(--bg-surface); border-radius: var(--radius-sm); font-size: 1.25rem; 
}
.anexo-info { flex: 1; min-width: 0; }
.anexo-name { 
    font-weight: 600; font-size: 0.875rem; display: block; white-space: nowrap; 
    overflow: hidden; text-overflow: ellipsis; color: var(--text-primary); 
}
.anexo-meta { font-size: 0.75rem; color: var(--text-muted); }
.anexo-actions { display: flex; gap: 0.25rem; }

/* Estilos de Ocorrências / Relatos */
.ocorrencia-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 1.25rem;
    margin-bottom: 1rem;
    position: relative;
    transition: all 0.2s;
    border-left: 4px solid var(--color-primary);
}
.ocorrencia-card.privada { border-left-color: #f59e0b; background: rgba(245, 158, 11, 0.02); }
.ocorrencia-card:hover { box-shadow: var(--shadow-md); border-color: var(--color-primary-light); }
.ocorrencia-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.ocorrencia-user { display: flex; align-items: center; gap: 0.75rem; }
.ocorrencia-user img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
.ocorrencia-user .no-photo { width: 32px; height: 32px; border-radius: 50%; background: var(--bg-surface-2nd); display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; }
.ocorrencia-meta { font-size: 0.8rem; color: var(--text-muted); }
.ocorrencia-texto { font-size: 0.9375rem; line-height: 1.6; color: var(--text-primary); }
.ocorrencia-texto p { margin-bottom: 0.75rem; }
.ocorrencia-texto ul { padding-left: 1.25rem; margin-bottom: 0.75rem; }
.ocorrencia-actions { display: flex; gap: 0.5rem; }
.badge-privado { background: #fef3c7; color: #92400e; font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: 10px; font-weight: 700; text-transform: uppercase; }

/* Editor Rich Text Minimalista */
.rich-editor {
    min-height: 150px;
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 1rem;
    background: var(--bg-surface);
    font-size: 0.9375rem;
    line-height: 1.6;
}
.rich-editor:focus { border-color: var(--color-primary); outline: none; }
.editor-toolbar {
    display: flex;
    gap: 0.25rem;
    margin-bottom: 0.5rem;
    background: var(--bg-surface-2nd);
    padding: 0.35rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-color);
}
.toolbar-btn {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    cursor: pointer;
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    transition: all 0.2s;
}
.toolbar-btn:hover { background: var(--bg-hover); color: var(--color-primary); }
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
    <div class="modal modal-80">
        <div class="modal-header">
            <h3 class="modal-title" id="naapiModalTitle">Adicionar Aluno ao NAAPI</h3>
            <button class="modal-close" onclick="closeModal('naapiModal')">&times;</button>
        </div>

        <div class="tabs-nav">
            <button class="tab-btn active" data-tab="tab-ficha" onclick="switchModalTab('naapiModal', 'tab-ficha')">📋 Ficha do Aluno</button>
            <button class="tab-btn" id="btn-tab-anexos" data-tab="tab-anexos" onclick="switchModalTab('naapiModal', 'tab-anexos')" disabled title="Salve o registro básico primeiro para gerenciar anexos">📎 Anexos e Documentos</button>
            <button class="tab-btn" id="btn-tab-ocorrencias" data-tab="tab-ocorrencias" onclick="switchModalTab('naapiModal', 'tab-ocorrencias')" disabled title="Salve o registro básico primeiro">📝 Relatos / Ocorrências</button>
        </div>

        <!-- Aba 1: Ficha de Cadastro -->
        <div id="tab-ficha" class="tab-content active">
            <form id="naapiForm" onsubmit="saveNaapi(event)" style="display:flex; flex-direction:column; height:100%;">
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

        <!-- Aba 2: Anexos -->
        <div id="tab-anexos" class="tab-content">
            <div class="modal-body">
                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <div>
                        <h4 style="margin:0; font-size:0.9rem; font-weight:700;">Gestão de Documentos</h4>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin:0;">Anexe laudos, relatórios e documentos especializados.</p>
                    </div>
                    <button class="btn btn-secondary btn-sm" onclick="openAddNaapiAnexoModal()">
                        📎 Adicionar Anexo
                    </button>
                </div>
                <div id="naapiAnexosList">
                    <!-- Carregado via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('naapiModal')">Fechar</button>
            </div>
        </div>

        <!-- Aba 3: Relatos/Ocorrências -->
        <div id="tab-ocorrencias" class="tab-content">
            <div class="modal-body">
                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <div>
                        <h4 style="margin:0; font-size:0.9rem; font-weight:700;">Histórico de Relatos</h4>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin:0;">Registro cronológico de acompanhamentos e ocorrências.</p>
                    </div>
                    <button class="btn btn-primary btn-sm" onclick="openAddNaapiOcorrenciaModal()">
                        ➕ Novo Relato
                    </button>
                </div>
                <div id="naapiOcorrenciasList">
                    <!-- Carregado via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('naapiModal')">Fechar</button>
            </div>
        </div>
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
    
    // Reset abas
    document.getElementById('btn-tab-anexos').disabled = true;
    document.getElementById('btn-tab-ocorrencias').disabled = true;
    switchModalTab('naapiModal', 'tab-ficha');
    
    openModal('naapiModal');
}

function switchModalTab(modalId, tabId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    // Desativa todas
    modal.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    modal.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Ativa alvo
    const targetBtn = modal.querySelector(`.tab-btn[data-tab="${tabId}"]`);
    const targetContent = modal.querySelector(`#${tabId}`);
    
    if (targetBtn) targetBtn.classList.add('active');
    if (targetContent) targetContent.classList.add('active');

    // Lógica específica
    if (tabId === 'tab-anexos') {
        const alunoId = document.getElementById('field_aluno_id').value;
        if (alunoId) loadNaapiAnexos(alunoId);
    } else if (tabId === 'tab-ocorrencias') {
        const alunoId = document.getElementById('field_aluno_id').value;
        if (alunoId) loadNaapiOcorrencias(alunoId);
    }
}

async function loadNaapiAnexos(alunoId) {
    const container = document.getElementById('naapiAnexosList');
    container.innerHTML = '<div style="padding:2rem; text-align:center; color:var(--text-muted);">Carregando anexos...</div>';

    try {
        const resp = await fetch(`/api/aluno_naapi.php?action=fetch_anexos&aluno_id=${alunoId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (data.success) {
            renderNaapiAnexos(data.anexos);
        } else {
            throw new Error(data.error || data.message || 'Erro desconhecido');
        }
    } catch (e) {
        container.innerHTML = `<div class="alert alert-danger">Erro ao carregar anexos: ${e.message}</div>`;
    }
}

function renderNaapiAnexos(anexos) {
    const container = document.getElementById('naapiAnexosList');
    if (anexos.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted); background: var(--bg-surface-2nd); border-radius: var(--radius-md); border: 2px dashed var(--border-color);">
                <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3;">📁</div>
                <p>Nenhum documento anexado ainda.</p>
            </div>
        `;
        return;
    }

    let h = '';
    anexos.forEach(a => {
        const dateStr = new Date(a.created_at).toLocaleDateString();
        const icon = a.extensao === 'pdf' ? '📄' : '🖼️';
        h += `
            <div class="anexo-item">
                <div class="anexo-icon">${icon}</div>
                <div class="anexo-info">
                    <span class="anexo-name" title="${a.descricao || ''}">${a.descricao || 'Arquivo .' + a.extensao}</span>
                    <div class="anexo-meta">${dateStr} • ${a.extensao.toUpperCase()} • Por ${a.author_name}</div>
                </div>
                <div class="anexo-actions">
                    <button class="btn btn-secondary btn-sm" onclick="viewAnexo('/${a.arquivo}', '${a.descricao || ''}', '${a.extensao}')">👁️</button>
                    <button class="btn btn-secondary btn-sm" style="color:#ef4444;" onclick="deleteNaapiAnexo(${a.id})">🗑️</button>
                </div>
            </div>
        `;
    });
    container.innerHTML = h;
}

function openAddNaapiAnexoModal() {
    const alunoId = document.getElementById('field_aluno_id').value;
    if (!alunoId) {
        Toast.error('ID do aluno não identificado.');
        return;
    }
    document.getElementById('naapiAnexoAlunoId').value = alunoId;
    document.getElementById('naapiAnexoDescricao').value = '';
    document.getElementById('naapiAnexoFile').value = '';
    openModal('modalAddNaapiAnexo');
}

async function submitNaapiAnexo() {
    const fileInput = document.getElementById('naapiAnexoFile');
    const alunoId = document.getElementById('naapiAnexoAlunoId').value;
    const descricao = document.getElementById('naapiAnexoDescricao').value;

    if (!fileInput.files[0]) {
        Toast.error('Selecione um arquivo.');
        return;
    }

    const formData = new FormData();
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    formData.append('action', 'upload_anexo');
    formData.append('csrf_token', csrfToken);
    formData.append('aluno_id', alunoId);
    formData.append('descricao', descricao);
    formData.append('arquivo', fileInput.files[0]);

    try {
        if (typeof Loading !== 'undefined') Loading.show('Enviando...');
        const resp = await fetch('/api/aluno_naapi.php', { 
            method: 'POST', 
            headers: { 
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData 
        });
        const res = await resp.json();
        if (typeof Loading !== 'undefined') Loading.hide();

        if (res.success) {
            Toast.success('Anexo enviado!');
            closeModal('modalAddNaapiAnexo');
            loadNaapiAnexos(alunoId);
        } else {
            throw new Error(res.error || res.message || 'Erro no processamento');
        }
    } catch (e) {
        if (typeof Loading !== 'undefined') Loading.hide();
        Toast.error('Erro no upload: ' + e.message);
    }
}

async function deleteNaapiAnexo(anexoId) {
    if (!confirm('Deseja excluir este anexo?')) return;
    
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    const formData = new FormData();
    formData.append('action', 'delete_anexo');
    formData.append('csrf_token', csrfToken);
    formData.append('anexo_id', anexoId);

    try {
        const resp = await fetch('/api/aluno_naapi.php', { 
            method: 'POST', 
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData 
        });
        const res = await resp.json();
        if (res.success) {
            Toast.success('Anexo removido.');
            loadNaapiAnexos(document.getElementById('field_aluno_id').value);
        } else {
            throw new Error(res.error || res.message);
        }
    } catch (e) { Toast.error('Erro ao excluir: ' + e.message); }
}

function viewAnexo(url, descricao, extensao) {
    const container = document.getElementById('anexoPreviewContainer');
    document.getElementById('viewAnexoTitle').innerText = descricao || 'Visualizar Anexo';
    document.getElementById('downloadAnexoBtn').href = url;
    container.innerHTML = '';
    if (extensao === 'pdf') {
        container.innerHTML = `<iframe src="${url}#toolbar=0" style="width:100%; height:100%; border:none;"></iframe>`;
    } else {
        container.innerHTML = `<img src="${url}" style="max-width:100%; max-height:100%; object-fit:contain;">`;
    }
    openModal('modalViewAnexo');
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
    
    // Habilitar aba de anexos se já existe no banco ou se for novo?
    // Para simplificar, habilitamos a aba de anexos se houver aluno_id selecionado
    // mas o ideal é salvar primeiro. Vamos manter o disabled até salvar se for novo.
    if (document.getElementById('field_id').value) {
        document.getElementById('btn-tab-anexos').disabled = false;
        document.getElementById('btn-tab-ocorrencias').disabled = false;
    }
}

function clearAlunoSelection() {
    document.getElementById('field_aluno_id').value = '';
    document.getElementById('aluno_search_group').style.display = 'block';
    document.getElementById('selected_aluno_info').style.display = 'none';
    document.getElementById('aluno_search_input').value = '';
    document.getElementById('aluno_search_input').focus();
    document.getElementById('btn-tab-anexos').disabled = true;
    document.getElementById('btn-tab-ocorrencias').disabled = true;
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
            document.getElementById('btn_change_aluno').style.display = 'none';
            
            // Habilitar anexos e ocorrencias na edição
            document.getElementById('btn-tab-anexos').disabled = false;
            document.getElementById('btn-tab-ocorrencias').disabled = false;
            switchModalTab('naapiModal', 'tab-ficha');
            
            openModal('naapiModal');
        } else {
            Toast.error(res.error);
        }
    } catch (e) {
        Toast.error('Erro ao buscar dados.');
    }
}

// Lógica de Ocorrências / Relatos
async function loadNaapiOcorrencias(alunoId) {
    const container = document.getElementById('naapiOcorrenciasList');
    container.innerHTML = '<div style="padding:2rem; text-align:center; color:var(--text-muted);">Carregando histórico...</div>';

    try {
        const resp = await fetch(`/api/aluno_naapi.php?action=fetch_ocorrencias&aluno_id=${alunoId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (data.success) {
            renderNaapiOcorrencias(data.ocorrencias);
        } else {
            throw new Error(data.error || 'Erro ao carregar');
        }
    } catch (e) {
        container.innerHTML = `<div class="alert alert-danger">Erro: ${e.message}</div>`;
    }
}

function renderNaapiOcorrencias(ocorrencias) {
    const container = document.getElementById('naapiOcorrenciasList');
    if (ocorrencias.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted); background: var(--bg-surface-2nd); border-radius: var(--radius-md); border: 2px dashed var(--border-color);">
                <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3;">📝</div>
                <p>Nenhum relato registrado neste prontuário.</p>
            </div>
        `;
        return;
    }

    let h = '';
    ocorrencias.forEach(o => {
        const dateStr = new Date(o.data_ocorrencia).toLocaleDateString('pt-BR');
        const photoHtml = o.usuario_photo ? `<img src="/${o.usuario_photo}">` : `<div class="no-photo">${o.usuario_nome.charAt(0)}</div>`;
        const privadoBadge = o.is_privado == 1 ? '<span class="badge-privado">🔒 Privado</span>' : '';
        
        h += `
            <div class="ocorrencia-card ${o.is_privado == 1 ? 'privada' : ''}">
                <div class="ocorrencia-header">
                    <div class="ocorrencia-user">
                        ${photoHtml}
                        <div>
                            <div style="font-weight:700; font-size:0.875rem;">${o.usuario_nome} ${privadoBadge}</div>
                            <div class="ocorrencia-meta">Lançado em ${dateStr}</div>
                        </div>
                    </div>
                    <div class="ocorrencia-actions">
                        <button class="btn btn-ghost btn-sm" onclick="editNaapiOcorrencia(${o.id}, '${o.data_ocorrencia}', ${o.is_privado}, \`${o.texto.replace(/`/g, '\\`')}\`)">✏️</button>
                        <button class="btn btn-ghost btn-sm" style="color:red;" onclick="deleteNaapiOcorrencia(${o.id})">🗑️</button>
                    </div>
                </div>
                <div class="ocorrencia-texto">${o.texto}</div>
            </div>
        `;
    });
    container.innerHTML = h;
}

function openAddNaapiOcorrenciaModal() {
    const alunoId = document.getElementById('field_aluno_id').value;
    document.getElementById('ocorrencia_id').value = '';
    document.getElementById('ocorrencia_aluno_id').value = alunoId;
    document.getElementById('ocorrencia_data').value = new Date().toISOString().split('T')[0];
    document.getElementById('ocorrencia_privado').checked = false;
    document.getElementById('ocorrencia_editor').innerHTML = '';
    openModal('modalAddNaapiOcorrencia');
}

function formatRichText(command) {
    document.execCommand(command, false, null);
    document.getElementById('ocorrencia_editor').focus();
}

async function saveNaapiOcorrencia() {
    const id = document.getElementById('ocorrencia_id').value;
    const alunoId = document.getElementById('ocorrencia_aluno_id').value;
    const dataOcorrencia = document.getElementById('ocorrencia_data').value;
    const isPrivado = document.getElementById('ocorrencia_privado').checked ? 1 : 0;
    const texto = document.getElementById('ocorrencia_editor').innerHTML;

    if (!texto.trim() || texto === '<br>') {
        Toast.error('O conteúdo do relato é obrigatório.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'save_ocorrencia');
    formData.append('id', id);
    formData.append('aluno_id', alunoId);
    formData.append('data_ocorrencia', dataOcorrencia);
    formData.append('is_privado', isPrivado);
    formData.append('texto', texto);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    try {
        const resp = await fetch('/api/aluno_naapi.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const res = await resp.json();
        if (res.success) {
            Toast.success('Relato salvo com sucesso!');
            closeModal('modalAddNaapiOcorrencia');
            loadNaapiOcorrencias(alunoId);
        } else {
            throw new Error(res.error);
        }
    } catch (e) {
        Toast.error('Erro ao salvar: ' + e.message);
    }
}

async function deleteNaapiOcorrencia(id) {
    if (!confirm('Deseja realmente excluir este relato?')) return;
    const formData = new FormData();
    formData.append('action', 'delete_ocorrencia');
    formData.append('id', id);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    try {
        const resp = await fetch('/api/aluno_naapi.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const res = await resp.json();
        if (res.success) {
            Toast.success('Relato removido.');
            loadNaapiOcorrencias(document.getElementById('field_aluno_id').value);
        }
    } catch (e) { Toast.error('Erro ao excluir.'); }
}

function editNaapiOcorrencia(id, data, privado, texto) {
    document.getElementById('ocorrencia_id').value = id;
    document.getElementById('ocorrencia_aluno_id').value = document.getElementById('field_aluno_id').value;
    document.getElementById('ocorrencia_data').value = data;
    document.getElementById('ocorrencia_privado').checked = privado == 1;
    document.getElementById('ocorrencia_editor').innerHTML = texto;
    openModal('modalAddNaapiOcorrencia');
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
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const res = await response.json();
        
        if (typeof Loading !== 'undefined') Loading.hide();

        if (res.success) {
            Toast.success(res.message);
            // Se for novo, poderíamos habilitar a aba aqui, mas vamos recarregar para atualizar a lista
            setTimeout(() => location.reload(), 800);
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

<!-- Modal: Adicionar/Editar Relato NAAPI -->
<div id="modalAddNaapiOcorrencia" class="modal-backdrop" style="z-index: 9000 !important;">
    <div class="modal" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title">📝 Registro de Relato / Ocorrência</h3>
            <button class="modal-close" onclick="closeModal('modalAddNaapiOcorrencia')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formAddNaapiOcorrencia" onsubmit="event.preventDefault(); saveNaapiOcorrencia();">
                <input type="hidden" id="ocorrencia_id">
                <input type="hidden" id="ocorrencia_aluno_id">
                
                <div style="display:grid; grid-template-columns: 1fr auto; gap:1.5rem; align-items: flex-end; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label">Data da Ocorrência <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">📅</span>
                            <input type="date" id="ocorrencia_data" class="form-control" required style="padding-left:2.75rem;">
                        </div>
                    </div>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; padding-bottom:0.75rem;">
                        <input type="checkbox" id="ocorrencia_privado" style="width:18px; height:18px;">
                        <span style="font-size:0.875rem; font-weight:600; color:var(--text-secondary);">Marcar como Privado 🔒</span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label">Relato Detalhado <span class="required">*</span></label>
                    <div class="editor-toolbar">
                        <button type="button" class="toolbar-btn" onclick="formatRichText('bold')" title="Negrito"><b>B</b></button>
                        <button type="button" class="toolbar-btn" onclick="formatRichText('italic')" title="Itálico"><i>I</i></button>
                        <button type="button" class="toolbar-btn" onclick="formatRichText('underline')" title="Sublinhado"><u>U</u></button>
                        <div style="width:1px; height:20px; background:var(--border-color); margin:0 0.25rem;"></div>
                        <button type="button" class="toolbar-btn" onclick="formatRichText('insertUnorderedList')" title="Lista">• List</button>
                    </div>
                    <div id="ocorrencia_editor" class="rich-editor" contenteditable="true" placeholder="Escreva aqui o relato do atendimento..."></div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalAddNaapiOcorrencia')">Cancelar</button>
            <button class="btn btn-primary" onclick="saveNaapiOcorrencia()">💾 Salvar Relato</button>
        </div>
    </div>
</div>

<!-- Modal: Adicionar Anexo NAAPI -->
<div id="modalAddNaapiAnexo" class="modal-backdrop" style="z-index: 9000 !important;">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h3 class="modal-title">📎 Adicionar Novo Anexo</h3>
            <button class="modal-close" onclick="closeModal('modalAddNaapiAnexo')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formAddNaapiAnexo" onsubmit="event.preventDefault(); submitNaapiAnexo();">
                <input type="hidden" id="naapiAnexoAlunoId">
                <div class="form-group">
                    <label class="form-label">Selecione o Arquivo (PDF ou Imagem)</label>
                    <div class="input-group">
                        <span class="input-icon">📂</span>
                        <input type="file" id="naapiAnexoFile" class="form-control" accept=".pdf,image/*" required style="padding-left:2.75rem;">
                    </div>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Descrição (Opcional)</label>
                    <input type="text" id="naapiAnexoDescricao" class="form-control" placeholder="Ex: Relatório médico, Laudo técnico...">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalAddNaapiAnexo')">Cancelar</button>
            <button class="btn btn-primary" onclick="submitNaapiAnexo()">Fazer Upload</button>
        </div>
    </div>
</div>

<!-- Modal: Visualizar Anexo -->
<div id="modalViewAnexo" class="modal-backdrop" style="z-index: 9100 !important;">
    <div class="modal" style="width: 90vw; height: 90vh; max-width: none; display: flex; flex-direction: column; overflow: hidden;">
        <div class="modal-header">
            <h3 class="modal-title" id="viewAnexoTitle">Visualizar Anexo</h3>
            <div style="display:flex; gap:0.75rem; align-items:center;">
                <a id="downloadAnexoBtn" href="#" download class="btn btn-secondary btn-sm">⬇️ Download</a>
                <button class="modal-close" onclick="closeModal('modalViewAnexo')">&times;</button>
            </div>
        </div>
        <div class="modal-body" style="flex: 1; padding: 0; overflow: hidden; background: #eee; display: flex; align-items: center; justify-content: center;">
            <div id="anexoPreviewContainer" style="width: 100%; height: 100%; overflow: auto; display: flex; align-items: center; justify-content: center;"></div>
        </div>
    </div>
</div>

<?php 
renderModalScripts();
renderToastScripts();
require_once __DIR__ . '/../includes/footer.php'; 
?>
