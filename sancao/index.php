<?php
/**
 * Vértice Acadêmico — Sanção Disciplinar
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

hasDbPermission('sancoes.index');

$user = getCurrentUser();
$inst = getCurrentInstitution();

require_once __DIR__ . '/../includes/toast.php';
require_once __DIR__ . '/../includes/modal.php';

$pageTitle = "Sanções Disciplinares";
require_once __DIR__ . '/../includes/header.php';
renderModalStyles();
renderToastStyles();
?>

<style>
/* Aba Styles */
.modal-tabs {
    display: flex;
    gap: 1rem;
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 1.5rem;
    padding: 0 1.5rem;
}

.modal-tab-btn {
    background: none;
    border: none;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    font-size: 0.9375rem;
}

.modal-tab-btn.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
}

.modal-tab-content {
    display: none;
    padding: 0 1.5rem 1.5rem;
}

.modal-tab-content.active {
    display: block;
}

.sancao-modal-body {
    padding: 0;
}

/* Tabela Padrão Sistema */
.sancao-table-wrap { overflow-x:auto; border-radius:var(--radius-lg); }
.sancao-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.sancao-table th {
    padding:.75rem 1.25rem; text-align:left; font-size:.75rem; font-weight:600;
    text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted);
    background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color);
}
.sancao-table td { padding:.875rem 1.25rem; border-bottom:1px solid var(--border-color); vertical-align:middle; transition: background 0.1s; }
.sancao-table tr:last-child td { border-bottom:none; }
.sancao-table tr:hover td { background:var(--bg-hover); }

/* Badges de Status (Padrão style.css) */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.625rem;
    border-radius: var(--radius-full);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}
.badge-warning { background: #fffbeb; color: #92400e; }
.badge-success { background: #f0fdf4; color: #14532d; }
.badge-danger  { background: #fef2f2; color: #991b1b; }

[data-theme="dark"] .badge-warning { background: #451a03; color: #fbbf24; }
[data-theme="dark"] .badge-success { background: #064e3b; color: #34d399; }
[data-theme="dark"] .badge-danger  { background: #450a0a; color: #f87171; }

/* Botões de Ação */
.action-btn {
    display:inline-flex; align-items:center; justify-content:center;
    width:32px; height:32px; border-radius:var(--radius-md);
    border:1px solid var(--border-color); background:var(--bg-surface);
    color:var(--text-muted); cursor:pointer; font-size:.875rem;
    transition:all var(--transition-fast); text-decoration:none;
    padding:0; line-height:1;
}
.action-btn:hover { background:var(--bg-hover); color:var(--text-primary); border-color: var(--color-primary); }

/* Thumbnails de Aluno */
.aluno-thumb {
    display: flex;
    align-items: center;
    gap: 0.875rem;
}
.aluno-photo-sm { width:36px; height:36px; border-radius:50%; object-fit:cover; background:var(--bg-surface-2nd); border: 1px solid var(--border-color); }
.aluno-initials-sm { width:36px; height:36px; border-radius:50%; background:var(--gradient-brand); color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.75rem; border: 1px solid rgba(255,255,255,0.2); }

/* Checklist Styles */
.checklist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}

.checklist-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--bg-surface-2nd);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    cursor: pointer;
}

.checklist-item input {
    width: 1.25rem;
    height: 1.25rem;
    cursor: pointer;
}

/* Print View */
@media print {
    body * {
        visibility: hidden;
    }
    #printArea, #printArea * {
        visibility: visible;
    }
    #printArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 2cm;
    }
    .modal-backdrop, .modal-close, .modal-tabs, .modal-footer, .navbar, .page-header {
        display: none !important;
    }
}
</style>

<div class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-content">
        <h1 class="page-title">Controle de Sanções Disciplinares</h1>
        <p class="page-subtitle">Gerencie as sanções emitidas para os discentes</p>
    </div>
</div>

<div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
    <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <label class="form-label" style="font-size: 0.8125rem;">Buscar Aluno</label>
            <input type="text" id="filterAluno" class="form-control" placeholder="Nome ou matrícula...">
        </div>
        <div style="width: 180px;">
            <label class="form-label" style="font-size: 0.8125rem;">Status</label>
            <select id="filterStatus" class="form-control">
                <option value="">Todos</option>
                <option value="Em aberto">Em aberto</option>
                <option value="Concluído">Concluído</option>
                <option value="Cancelado">Cancelado</option>
            </select>
        </div>
        <button class="btn btn-secondary" onclick="loadSancoes()">Filtrar</button>
        <?php if(hasDbPermission('sancoes.manage', false)): ?>
        <button class="btn btn-primary" onclick="openSancaoModal()">
            <span class="btn-icon">+</span> Nova Sanção
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="sancao-table-wrap">
        <table class="sancao-table" id="sancoesTable">
            <thead>
                <tr>
                    <th style="width: 120px;">Data</th>
                    <th>Discente / Matrícula</th>
                    <th>Tipo de Sanção</th>
                    <th style="width: 150px; text-align: center;">Status</th>
                    <th style="width: 80px; text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody id="sancoesTableBody">
                <tr><td colspan="5" style="text-align: center; padding: 3rem; color: var(--text-muted);">Carregando registros...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Sanção (80vw x 80vh) -->
<div class="modal-backdrop" id="modalSancao" role="dialog">
    <div class="modal" style="width: 80vw; height: 80vh; max-width: 1400px; display: flex; flex-direction: column; overflow: hidden;">
        <div class="modal-header">
            <h3 id="sancaoModalTitle">Nova Sanção Disciplinar</h3>
            <button class="modal-close" onclick="closeModal('modalSancao')">×</button>
        </div>
        
        <div class="modal-body sancao-modal-body" style="flex: 1; display: flex; flex-direction: column; padding: 1rem 0 0 0; overflow: hidden;">
            
            <div class="modal-tabs">
                <button class="modal-tab-btn active" onclick="switchTab('tab-identificacao')">Identificação</button>
                <button class="modal-tab-btn" onclick="switchTab('tab-configuracao')">Configuração</button>
                <button class="modal-tab-btn" onclick="switchTab('tab-impressao')" id="tabImpressaoBtn" style="display:none;">Impressão</button>
                <button class="modal-tab-btn" onclick="switchTab('tab-anexo')" id="tabAnexoBtn" style="display:none;">Arquivos / Conclusão</button>
            </div>

            <form id="formSancao" style="flex: 1; overflow-y: auto; padding: 0;">
                <input type="hidden" id="sancao_id" name="sancao_id" value="">
                
                <!-- ABA 1: Identificação -->
                <div id="tab-identificacao" class="modal-tab-content active">
                    <div class="form-group" style="position:relative;">
                        <label class="form-label">Buscar Aluno (Nome ou Matrícula)</label>
                        <input type="text" id="typeaheadAluno" class="form-control" placeholder="Digite para buscar..." autocomplete="off">
                        <input type="hidden" id="aluno_id" name="aluno_id" required>
                        <div id="typeaheadResults" style="position:absolute; background:var(--bg-card); width:100%; max-height:280px; overflow-y:auto; border:1px solid var(--border-color); border-radius:0 0 8px 8px; box-shadow:0 4px 6px rgba(0,0,0,0.1); display:none; z-index:100;"></div>
                    </div>
                    
                    <div id="alunoCard" style="display:none; margin-top: 1.5rem; padding: 1.25rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-surface-2nd); display: flex; gap: 1.5rem; align-items: center;">
                        <img id="alunoFoto" src="" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); display: none;">
                        <div id="alunoInitials" style="width: 80px; height: 80px; border-radius: 50%; background: var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; color: var(--text-muted); display: none;"></div>
                        
                        <div>
                            <h4 id="alunoNome" style="margin-bottom: 0.25rem;"></h4>
                            <p id="alunoMatriculaTurma" style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 0;"></p>
                        </div>
                    </div>
                </div>

                <!-- ABA 2: Configuração -->
                <div id="tab-configuracao" class="modal-tab-content">
                    <div class="form-group">
                        <label class="form-label">Data da Sanção</label>
                        <input type="date" id="data_sancao" name="data_sancao" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Sanção</label>
                        <select id="sancao_tipo_id" name="sancao_tipo_id" class="form-control" required>
                            <option value="">Selecione...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: block; margin-bottom: 0.75rem;">Ações Adicionais Exigidas</label>
                        <div class="checklist-grid" id="acoesContainer">
                            Carregando...
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label class="form-label">Observações / Relato</label>
                        <textarea id="observacoes" name="observacoes" class="form-control" rows="4" placeholder="Descreva os detalhes da ocorrência e deliberações..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status Inicial</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="Em aberto">Em aberto (Aguardando Retorno/Assinatura)</option>
                            <option value="Concluído">Concluído</option>
                            <option value="Cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>

                <!-- ABA 3: Impressão -->
                <div id="tab-impressao" class="modal-tab-content">
                    <div style="background: white; color: black; border: 1px solid #ccc; padding: 2cm; min-height: 400px;" id="printArea">
                        <div style="text-align: center; margin-bottom: 2rem;">
                            <h2 style="margin: 0; text-transform: uppercase;">Termo de Sanção Disciplinar</h2>
                            <p style="margin: 0.5rem 0 0; color: #555;"><?= htmlspecialchars($inst['name']) ?></p>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <p><strong>Discente:</strong> <span id="printAlunoNome"></span></p>
                            <p><strong>Matrícula:</strong> <span id="printAlunoMatricula"></span></p>
                            <p><strong>Turma:</strong> <span id="printTurmaInfo"></span></p>
                            <p><strong>Data da Aplicação:</strong> <span id="printDataSancao"></span></p>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <p><strong>Sanção Aplicada:</strong> <span id="printSancaoTipo"></span></p>
                            <p><strong>Relato:</strong></p>
                            <div style="border: 1px solid #ddd; padding: 1rem; min-height: 100px; white-space: pre-wrap;" id="printObservacoes"></div>
                        </div>
                        
                        <div style="margin-bottom: 2rem;">
                            <p><strong>Ações Requeridas:</strong></p>
                            <ul id="printAcoesList"></ul>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; margin-top: 4rem;">
                            <div style="text-align: center; width: 45%;">
                                <div style="border-top: 1px solid black; margin-bottom: 0.5rem;"></div>
                                Assinatura Instituição
                            </div>
                            <div style="text-align: center; width: 45%;">
                                <div style="border-top: 1px solid black; margin-bottom: 0.5rem;"></div>
                                Assinatura Discente / Responsável
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem; text-align: right;">
                        <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ Imprimir Documento</button>
                    </div>
                </div>

                <!-- ABA 4: Anexo / Conclusão -->
                <div id="tab-anexo" class="modal-tab-content">
                    <div class="form-group">
                        <label class="form-label">Comprovante / Documento Assinado (PDF ou Imagem)</label>
                        <input type="file" id="anexoFile" name="anexo" class="form-control" accept=".pdf,image/*">
                        <p style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.5rem;">
                            O envio do documento assinado marca automaticamente a sanção como "Concluída" se ainda estiver em aberto.
                        </p>
                    </div>
                    
                    <div id="anexoPreview" style="margin-top: 1rem; display: none;">
                        <a href="#" id="anexoDownloadLink" target="_blank" class="btn btn-secondary">⬇️ Baixar Arquivo Anexado</a>
                    </div>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalSancao')">Cancelar</button>
            <button class="btn btn-primary" id="btnSalvarSancao" onclick="salvarSancao()">Salvar Registro</button>
        </div>
    </div>
</div>

<script>
let debounceTimer;
let currentAcoes = [];
let currentSancaoId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadSancoes();
    loadDependencyData();
});

function switchTab(tabId) {
    document.querySelectorAll('.modal-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.modal-tab-content').forEach(content => content.classList.remove('active'));
    
    // Fallback instead of using event.currentTarget
    const btns = document.querySelectorAll('.modal-tab-btn');
    for(let i=0; i<btns.length; i++){
        if(btns[i].getAttribute('onclick').includes(tabId)){
            btns[i].classList.add('active');
            break;
        }
    }
    
    document.getElementById(tabId).classList.add('active');
    
    if (tabId === 'tab-impressao' || tabId === 'tab-anexo') {
        document.getElementById('btnSalvarSancao').style.display = 'none';
    } else {
        document.getElementById('btnSalvarSancao').style.display = 'inline-block';
    }
}

async function loadDependencyData() {
    try {
        const resp = await fetch('/sancao/ajax.php?action=get_dependencies');
        const data = await resp.json();
        
        let tipoHtml = '<option value="">Selecione...</option>';
        data.tipos.forEach(t => {
            tipoHtml += `<option value="${t.id}">${t.titulo}</option>`;
        });
        document.getElementById('sancao_tipo_id').innerHTML = tipoHtml;
        
        let acoesHtml = '';
        data.acoes.forEach(a => {
            acoesHtml += `
            <label class="checklist-item">
                <input type="checkbox" name="acoes[]" value="${a.id}">
                <span>${a.descricao}</span>
            </label>`;
        });
        document.getElementById('acoesContainer').innerHTML = acoesHtml;
        
    } catch (e) {
        console.error("Failed to load dependencies", e);
    }
}

async function loadSancoes() {
    const tableBody = document.getElementById('sancoesTableBody');
    tableBody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 3rem; color: var(--text-muted);">Carregando registros...</td></tr>';
    
    const filterAluno = document.getElementById('filterAluno').value;
    const filterStatus = document.getElementById('filterStatus').value;
    
    try {
        const resp = await fetch(`/sancao/ajax.php?action=list&aluno=${encodeURIComponent(filterAluno)}&status=${encodeURIComponent(filterStatus)}`);
        const result = await resp.json();
        
        if (result.status === 'success') {
            if (result.data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 3rem; color:var(--text-muted);">Nenhuma sanção encontrada com estes filtros.</td></tr>';
                return;
            }
            
            let html = '';
            result.data.forEach(s => {
                let badgeClass = s.status === 'Em aberto' ? 'warning' : (s.status === 'Concluído' ? 'success' : 'danger');
                
                // Formatação Aluno Thumb
                const initials = s.aluno_nome.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
                const thumbHtml = s.aluno_foto 
                    ? `<img src="/${s.aluno_foto}" class="aluno-photo-sm">`
                    : `<div class="aluno-initials-sm">${initials}</div>`;

                html += `
                    <tr>
                        <td style="font-weight: 500; color: var(--text-secondary);">
                            ${new Date(s.data_sancao + 'T00:00:00').toLocaleDateString('pt-BR')}
                        </td>
                        <td>
                            <div class="aluno-thumb">
                                ${thumbHtml}
                                <div>
                                    <div style="font-weight: 600; color: var(--text-primary);">${s.aluno_nome}</div>
                                    <div style="font-size: 0.75rem; color: var(--color-primary); font-weight: 500;">
                                        ${s.matricula} <span style="color: var(--text-muted); font-weight: 400; margin-left: 4px;">• ${s.turma_desc}</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="font-weight: 500;">${s.tipo_titulo}</td>
                        <td style="text-align: center;"><span class="badge badge-${badgeClass}">${s.status}</span></td>
                        <td style="text-align: center;">
                            <button class="action-btn" onclick="editSancao(${s.id})" title="Detalhes/Editar">✏️</button>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 3rem; color: var(--color-danger);">${result.message}</td></tr>`;
        }
    } catch (e) {
        tableBody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 3rem; color: var(--color-danger);">Erro ao conectar com o servidor.</td></tr>';
    }
}

function openSancaoModal() {
    document.getElementById('formSancao').reset();
    document.getElementById('sancao_id').value = '';
    document.getElementById('aluno_id').value = '';
    
    document.getElementById('alunoCard').style.display = 'none';
    document.getElementById('tabImpressaoBtn').style.display = 'none';
    document.getElementById('tabAnexoBtn').style.display = 'none';
    document.getElementById('anexoPreview').style.display = 'none';
    
    switchTab('tab-identificacao'); // direct call instead of .click()
    
    document.getElementById('sancaoModalTitle').innerText = 'Nova Sanção Disciplinar';
    
    openModal('modalSancao');
}

async function editSancao(id) {
    openSancaoModal();
    document.getElementById('sancaoModalTitle').innerText = 'Editar Sanção Disciplinar';
    document.getElementById('tabImpressaoBtn').style.display = 'block';
    document.getElementById('tabAnexoBtn').style.display = 'block';
    
    try {
        const resp = await fetch(`/sancao/ajax.php?action=get&id=${id}`);
        const result = await resp.json();
        if (result.status === 'success') {
            const data = result.data;
            document.getElementById('sancao_id').value = data.id;
            
            // Populate Aluno Tab
            document.getElementById('aluno_id').value = data.aluno_id;
            document.getElementById('typeaheadAluno').value = data.aluno_nome;
            selectAluno({
                id: data.aluno_id,
                nome: data.aluno_nome,
                matricula: data.matricula,
                turma_id: data.turma_id,
                turma_desc: data.turma_desc,
                foto: data.aluno_foto
            });
            
            // Populate Config Tab
            document.getElementById('data_sancao').value = data.data_sancao;
            document.getElementById('sancao_tipo_id').value = data.sancao_tipo_id;
            document.getElementById('observacoes').value = data.observacoes;
            document.getElementById('status').value = data.status;
            
            // Checkboxes
            document.querySelectorAll('input[name="acoes[]"]').forEach(cb => {
                cb.checked = data.acoes_rel.includes(parseInt(cb.value));
            });
            
            // Populate Print Area
            const instTitle = document.querySelector('#sancao_tipo_id option:checked')?.text;
            document.getElementById('printAlunoNome').innerText = data.aluno_nome;
            document.getElementById('printAlunoMatricula').innerText = data.matricula;
            document.getElementById('printTurmaInfo').innerText = data.turma_desc;
            document.getElementById('printDataSancao').innerText = new Date(data.data_sancao + 'T00:00:00').toLocaleDateString('pt-BR');
            document.getElementById('printSancaoTipo').innerText = instTitle;
            document.getElementById('printObservacoes').innerText = data.observacoes || 'Sem relato específico.';
            
            let printAcoes = '';
            document.querySelectorAll('input[name="acoes[]"]:checked').forEach(cb => {
                printAcoes += `<li>${cb.nextElementSibling.innerText}</li>`;
            });
            if (!printAcoes) printAcoes = '<li>Nenhuma ação adicional vinculada.</li>';
            document.getElementById('printAcoesList').innerHTML = printAcoes;
            
            // Populate Anexos
            if (data.anexo_path) {
                document.getElementById('anexoPreview').style.display = 'block';
                document.getElementById('anexoDownloadLink').href = '/' + data.anexo_path;
            }
            
        }
    } catch (e) {
        Toast.show('Erro ao carregar dados da sanção', 'error');
    }
}

// Typeahead Logic
const searchAlunoInput = document.getElementById('typeaheadAluno');
const resultsDiv = document.getElementById('typeaheadResults');

searchAlunoInput.addEventListener('input', (e) => {
    clearTimeout(debounceTimer);
    const q = e.target.value.trim();
    if(q.length < 3) {
        resultsDiv.style.display = 'none';
        return;
    }
    debounceTimer = setTimeout(async () => {
        try {
            const resp = await fetch(`/sancao/ajax.php?action=search_aluno&q=${encodeURIComponent(q)}`);
            const data = await resp.json();
            
            if(data.length === 0) {
                resultsDiv.innerHTML = '<div style="padding:1rem;color:var(--text-muted);text-align:center;">Nenhum aluno encontrado</div>';
            } else {
                let html = '';
                data.forEach(a => {
                    const initials = a.nome.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
                    const photoHtml = a.foto 
                        ? `<img src="/${a.foto}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">`
                        : `<div style="width:32px;height:32px;border-radius:50%;background:var(--bg-surface-2nd);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:600;border:1px solid var(--border-color);">${initials}</div>`;
                        
                    html += `
                        <div style="padding:0.6rem 1rem; border-bottom:1px solid var(--border-color); cursor:pointer; display:flex; align-items:center; gap:0.75rem;" class="typeahead-item" onclick='selectAluno(${JSON.stringify(a)})'>
                            ${photoHtml}
                            <div>
                                <div style="font-weight:600; font-size:0.875rem;">${a.nome}</div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">${a.matricula} • ${a.turma_desc}</div>
                            </div>
                        </div>
                    `;
                });
                resultsDiv.innerHTML = html;
            }
            resultsDiv.style.display = 'block';
        } catch(e) {
            console.error(e);
        }
    }, 300);
});

document.addEventListener('click', (e) => {
    if(!searchAlunoInput.contains(e.target) && !resultsDiv.contains(e.target)) {
        resultsDiv.style.display = 'none';
    }
});

function selectAluno(aluno) {
    document.getElementById('aluno_id').value = aluno.id;
    searchAlunoInput.value = aluno.nome;
    resultsDiv.style.display = 'none';
    
    // Fill Card
    const card = document.getElementById('alunoCard');
    card.style.display = 'flex';
    document.getElementById('alunoNome').innerText = aluno.nome;
    document.getElementById('alunoMatriculaTurma').innerText = `${aluno.matricula} —  ${aluno.turma_desc}`;
    
    const img = document.getElementById('alunoFoto');
    const ini = document.getElementById('alunoInitials');
    if (aluno.foto) {
        img.src = '/' + aluno.foto;
        img.style.display = 'block';
        ini.style.display = 'none';
    } else {
        img.style.display = 'none';
        ini.style.display = 'flex';
        ini.innerText = aluno.nome.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
    }
    
    // Switch to step 2 visually or keep user there
    setTimeout(() => {
        if (!document.getElementById('sancao_id').value) {
            switchTab('tab-configuracao');
        }
    }, 500);
}

async function salvarSancao() {
    const form = document.getElementById('formSancao');
    if (!form.reportValidity()) return;
    
    if (!document.getElementById('aluno_id').value) {
        Toast.show('Selecione um aluno na aba Identificação.', 'error');
        switchTab('tab-identificacao');
        return;
    }

    const formData = new FormData(form);
    
    // CSRF Token from meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }
    
    const btn = document.getElementById('btnSalvarSancao');
    const oldText = btn.innerHTML;
    btn.innerHTML = 'Salvando...';
    btn.disabled = true;
    
    try {
        const resp = await fetch('/sancao/ajax.php?action=save', {
            method: 'POST',
            body: formData
        });
        const result = await resp.json();
        
        if (result.status === 'success') {
            Toast.show(result.message, 'success');
            closeModal('modalSancao');
            loadSancoes();
        } else {
            Toast.show(result.message, 'error');
        }
    } catch (e) {
        Toast.show('Erro na requisição. Tente novamente.', 'error');
    } finally {
        btn.innerHTML = oldText;
        btn.disabled = false;
    }
}
</script>

<?php 
renderModalScripts();
renderToastScripts();
require_once __DIR__ . '/../includes/footer.php'; 
?>
