<?php
/**
 * Vértice Acadêmico — Sanção Disciplinar
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

hasDbPermission('sancoes.index');

$user = getCurrentUser();
$inst = getCurrentInstitution();

$db = getDB();
$instStmt = $db->prepare("SELECT cnpj, address FROM institutions WHERE id = ?");
$instStmt->execute([$inst['id']]);
if ($instRow = $instStmt->fetch(PDO::FETCH_ASSOC)) {
    $inst['cnpj'] = $instRow['cnpj'];
    $inst['address'] = $instRow['address'];
}

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

/* Ata Document Style (Reference: conselho_acao.php) */
.ata-document {
    background: #fff;
    border: 1px solid #ddd;
    padding: 4rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    color: #333;
    font-family: 'Times New Roman', serif;
    line-height: 1.8;
    text-align: justify;
}

@media print {
    /* Esconde elementos de interface que ocupam espaço */
    .sidebar, .navbar, .header, .page-header, .tabs-nav, .no-print, 
    .modal-header, .modal-tabs, .modal-footer, .btn, .modal-backdrop { 
        display: none !important; 
    }

    /* Reset completo de todas as camadas de modal para fluxo de página natural */
    .modal, .modal-content, .modal-dialog, .modal-container, .sancao-modal-body, .modal-body {
        position: static !important;
        transform: none !important;
        inset: auto !important;
        top: 0 !important;
        left: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        height: auto !important;
        overflow: visible !important;
        max-height: none !important;
    }

    body { 
        background: white !important; 
        margin: 0 !important; 
        padding: 0 !important; 
        overflow: visible !important;
    }

    /* Faz o container de impressão ocupar a tela toda em fluxo natural */
    #tab-impressao { 
        display: block !important; 
        width: 100% !important; 
        margin: 0 !important; 
        padding: 0 !important;
        position: static !important;
    }

    .ata-document { 
        margin: 0 !important; 
        padding: 1cm !important; 
        box-shadow: none !important; 
        border: none !important; 
        width: 100% !important;
        height: auto !important;
        page-break-inside: avoid;
    }

    @page { 
        margin: 1cm; 
    }
}

.sancao-modal-body {
    padding: 0;
}

/* Tabela Padrão Sistema */
.sancao-table-wrap { overflow-x:auto; border-radius:var(--radius-lg); }
.sancao-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.history-table { table-layout: fixed; width: 100%; margin-top: 0.5rem; }
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
.action-btn-danger:hover { background: #fef2f2; color: #ef4444; border-color: #fca5a5; }
[data-theme="dark"] .action-btn-danger:hover { background: #450a0a; color: #f87171; border-color: #991b1b; }

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
        <?php if(hasDbPermission('sancoes.config', false)): ?>
        <a href="/sancao/config.php" class="btn btn-secondary" title="Configurações">
            ⚙️
        </a>
        <?php endif; ?>
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
                        <div id="typeaheadResults" style="position:absolute; top: 100%; left: 0; background:var(--bg-card); width:100%; max-height:280px; overflow-y:auto; border:1px solid var(--border-color); border-radius:0 0 8px 8px; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1); display:none; z-index:100;"></div>
                    </div>
                    
                    <div id="alunoCard" style="display:none; margin-top: 1.5rem; padding: 1.25rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-surface-2nd); display: flex; gap: 1.5rem; align-items: center;">
                        <img id="alunoFoto" src="" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); display: none;">
                        <div id="alunoInitials" style="width: 80px; height: 80px; border-radius: 50%; background: var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; color: var(--text-muted); display: none;"></div>
                        
                        <div>
                            <h4 id="alunoNome" style="margin-bottom: 0.25rem;"></h4>
                            <div id="alunoCurso" style="font-weight: 600; font-size: 0.8125rem; color: var(--color-primary); margin-bottom: 0.15rem;"></div>
                            <p id="alunoMatriculaTurma" style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 0;"></p>
                        </div>
                    </div>

                    <!-- Histórico Disciplinar -->
                    <div id="alunoHistoricoContainer" style="display:none; margin-top: 2rem; border-top: 1px dashed var(--border-color); padding-top: 1.5rem;">
                        <h4 style="font-size: 0.875rem; font-weight: 600; color: var(--text-muted); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            📋 Histórico de Ocorrências Anteriores
                        </h4>
                        <div class="sancao-table-wrap" style="box-shadow: none; border: 1px solid var(--border-color); border-radius: 8px;">
                            <table class="sancao-table history-table">
                                <thead>
                                    <tr>
                                        <th style="padding: 0.6rem 1rem; width: 15%;">Data</th>
                                        <th style="padding: 0.6rem 1rem; width: 25%;">Tipo</th>
                                        <th style="padding: 0.6rem 1rem; width: 45%;">Relato / Observações</th>
                                        <th style="padding: 0.6rem 1rem; text-align: center; width: 15%;">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="alunoHistoricoBody">
                                    <tr><td colspan="4" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">Carregando histórico...</td></tr>
                                </tbody>
                            </table>
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
                        <label class="form-label" style="display: block; margin-bottom: 0.75rem;">Fatos Geradores da Sanção</label>
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
                <div id="tab-impressao" class="modal-tab-content" style="background: var(--bg-surface-2nd); padding: 2rem;">
                    <div class="ata-document" id="printArea">
                        
                        <!-- Cabeçalho Institucional Padronizado (Ref: conselho_ata_ajax.php) -->
                        <div style="display:flex; align-items:flex-start; gap:1.5rem; border-bottom:2px solid #333; padding-bottom:1rem; margin-bottom:2.5rem; text-align:left;">
                            <?php if (!empty($inst['photo'])): ?>
                                <div style="width:80px; height:80px; flex-shrink:0;">
                                    <img src="/<?= htmlspecialchars($inst['photo']) ?>" style="width:100%; height:100%; object-fit:contain;">
                                </div>
                            <?php endif; ?>
                            <div style="flex:1;">
                                <h1 style="margin:0; font-size:1.25rem; text-transform:uppercase; font-family:sans-serif; font-weight:800; line-height:1.2; color:#000;"><?= htmlspecialchars($inst['name']) ?></h1>
                                <div style="font-size:0.8125rem; font-weight:700; color:#000; margin-top:4px;">CNPJ: <?= htmlspecialchars($inst['cnpj'] ?? '—') ?></div>
                                <div style="font-size:0.75rem; color:#444; margin-top:2px; line-height:1.4;"><?= htmlspecialchars($inst['address'] ?? '—') ?></div>
                            </div>
                        </div>

                        <div style="text-align: center; margin-bottom: 3rem;">
                            <h2 style="margin: 0; text-transform: uppercase; letter-spacing: 2px; font-size: 1.3rem; font-family: sans-serif; font-weight: 800; color: #000;">REGISTRO DE OCORRENCIA DISCIPLINAR E/OU PEDAGOGICA</h2>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <p><strong>Discente:</strong> <span id="printAlunoNomeHeader"></span></p>
                            <p><strong>Matrícula:</strong> <span id="printAlunoMatricula"></span></p>
                            <p><strong>Curso:</strong> <span id="printCursoInfo"></span></p>
                            <p><strong>Turma:</strong> <span id="printTurmaInfo"></span></p>
                            <p><strong>Data da Aplicação:</strong> <span id="printDataSancao"></span></p>
                        </div>
                        
                        <div style="margin-bottom: 2.5rem;">
                            <p><strong>Sanção Aplicada:</strong> <span id="printSancaoTipo"></span></p>
                            <p style="margin-top: 1.5rem;"><strong>Relato da Ocorrência:</strong></p>
                            <div style="border: 1px solid #ddd; padding: 1rem; min-height: 120px; white-space: pre-wrap; background: #fafafa; border-radius: 4px;" id="printObservacoes"></div>
                        </div>
                        
                        <div style="margin-bottom: 2.5rem;">
                            <p><strong>Fatos Geradores Identificados:</strong></p>
                            <ul id="printAcoesList" style="margin-top: 0.5rem;"></ul>
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 4rem; margin-top: 5rem;">
                            <!-- Primeira Linha de Assinaturas -->
                            <div style="display: flex; justify-content: space-between; gap: 2rem;">
                                <div style="text-align: center; border-top: 1px solid black; padding-top: 0.5rem; width: 100%;">
                                    <div id="printAuthorName" style="font-size: 0.875rem; font-weight: bold; text-transform: uppercase;">PROFISSIONAL</div>
                                    <div style="font-size: 0.75rem; color: #666;">Assinatura do Profissional</div>
                                </div>
                                <div style="text-align: center; border-top: 1px solid black; padding-top: 0.5rem; width: 100%;">
                                    <div id="printAlunoNomeFooter" style="font-size: 0.875rem; font-weight: bold; text-transform: uppercase;">ALUNO</div>
                                    <div style="font-size: 0.75rem; color: #666;">Assinatura do Discente</div>
                                </div>
                            </div>

                            <!-- Segunda Linha de Assinatura (Responsável) -->
                            <div style="display: flex; justify-content: center;">
                                <div style="text-align: left; border-top: 1px solid black; padding-top: 0.5rem; width: 60%;">
                                    <div style="font-size: 0.875rem; font-weight: bold; margin-bottom: 0.25rem;">Responsável nome:</div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top:4rem; text-align:center; font-size:0.75rem; color:#999;">
                            Documento gerado eletronicamente via Sistema Vértice Acadêmico em <?= date('d/m/Y H:i') ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem; text-align: right;" class="no-print">
                        <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ Imprimir Termo</button>
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
                        <td style="text-align: center; white-space: nowrap;">
                            <button class="action-btn" onclick="editSancao(${s.id})" title="Detalhes/Editar">✏️</button>
                            <?php if(hasDbPermission('sancoes.manage', false)): ?>
                            <button class="action-btn action-btn-danger" onclick="deleteSancao(${s.id})" title="Excluir">🗑️</button>
                            <?php endif; ?>
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
    document.getElementById('alunoHistoricoContainer').style.display = 'none';
    document.getElementById('tabImpressaoBtn').style.display = 'none';
    document.getElementById('tabAnexoBtn').style.display = 'none';
    document.getElementById('anexoPreview').style.display = 'none';
    
    switchTab('tab-identificacao'); 
    
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
                curso_nome: data.curso_nome,
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
            document.getElementById('printAlunoNomeHeader').innerText = data.aluno_nome;
            document.getElementById('printAlunoMatricula').innerText = data.matricula;
            document.getElementById('printCursoInfo').innerText = data.curso_nome || '—';
            document.getElementById('printTurmaInfo').innerText = data.turma_desc;
            document.getElementById('printDataSancao').innerText = new Date(data.data_sancao + 'T00:00:00').toLocaleDateString('pt-BR');
            document.getElementById('printSancaoTipo').innerText = instTitle;
            document.getElementById('printObservacoes').innerText = data.observacoes || 'Sem relato específico.';
            document.getElementById('printAlunoNomeFooter').innerText = data.aluno_nome;
            document.getElementById('printAuthorName').innerText = data.author_name || 'Profissional';
            
            let printAcoes = '';
            document.querySelectorAll('input[name="acoes[]"]:checked').forEach(cb => {
                printAcoes += `<li>${cb.nextElementSibling.innerText}</li>`;
            });
            if (!printAcoes) printAcoes = '<li>Nenhum fato gerador específico selecionado.</li>';
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
                                <div style="font-size:0.75rem; color:var(--text-muted);">${a.curso_nome}</div>
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
    document.getElementById('alunoCurso').innerText = aluno.curso_nome || '—';
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
        const sancaoId = document.getElementById('sancao_id').value;
        if (!sancaoId) {
            switchTab('tab-configuracao');
        }
        // Load history independant of the current ID
        loadAlunoHistory(aluno.id, sancaoId);
    }, 500);
}

async function loadAlunoHistory(alunoId, excludeId = null) {
    const container = document.getElementById('alunoHistoricoContainer');
    const body = document.getElementById('alunoHistoricoBody');
    
    if (!alunoId) return;
    
    container.style.display = 'block';
    body.innerHTML = '<tr><td colspan="3" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">Carregando histórico...</td></tr>';
    
    try {
        const resp = await fetch(`/sancao/ajax.php?action=get_history&aluno_id=${alunoId}&exclude_id=${excludeId || ''}`);
        const result = await resp.json();
        
        if (result.status === 'success' && result.data.length > 0) {
            let html = '';
            result.data.forEach(s => {
                const badgeClass = s.status === 'Concluído' ? 'badge-success' : (s.status === 'Cancelado' ? 'badge-danger' : 'badge-warning');
                const relatoResumo = s.observacoes || '—';
                html += `
                    <tr>
                        <td style="padding: 0.6rem 1rem; white-space: nowrap; font-size: 0.75rem;">${new Date(s.data_sancao + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                        <td style="padding: 0.6rem 1rem; font-weight: 600; font-size: 0.75rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${s.tipo_titulo}</td>
                        <td style="padding: 0.6rem 1rem; font-size: 0.75rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${relatoResumo.replace(/"/g, '&quot;')}">
                            ${relatoResumo}
                        </td>
                        <td style="padding: 0.6rem 1rem; text-align: center;">
                            <span class="badge ${badgeClass}" style="transform: scale(0.9);">${s.status}</span>
                        </td>
                    </tr>
                `;
            });
            body.innerHTML = html;
        } else {
            body.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-muted);">O aluno não possui ocorrências anteriores.</td></tr>';
        }
    } catch (e) {
        body.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem; color: #b91c1c;">Erro ao carregar histórico.</td></tr>';
    }
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
            loadSancoes();
            
            // Transform to Edit Mode
            const newId = result.id;
            document.getElementById('sancao_id').value = newId;
            document.getElementById('sancaoModalTitle').innerText = 'Editar Sanção Disciplinar';
            
            // Show additional tabs
            document.getElementById('tabImpressaoBtn').style.display = 'block';
            document.getElementById('tabAnexoBtn').style.display = 'block';
            
            // Popula dados da impressão e outros detalhes
            await editSancao(newId);
            
            // Vai para a aba de impressão
            setTimeout(() => {
                switchTab('tab-impressao');
            }, 500);
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

async function deleteSancao(id) {
    if (!confirm('Deseja realmente excluir este registro de sanção? Esta ação não pode ser desfeita.')) return;
    
    const formData = new FormData();
    formData.append('id', id);
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) formData.append('csrf_token', csrfToken);
    
    try {
        const resp = await fetch('/sancao/ajax.php?action=delete', {
            method: 'POST',
            body: formData
        });
        const result = await resp.json();
        
        if (result.status === 'success') {
            Toast.show(result.message, 'success');
            loadSancoes();
        } else {
            Toast.show(result.message, 'error');
        }
    } catch (e) {
        Toast.show('Erro na comunicação com o servidor.', 'error');
    }
}

// Initial Loads
async function loadDependencies() {
    try {
        const resp = await fetch('/sancao/ajax.php?action=get_dependencies');
        const data = await resp.json();
        
        // Load Tipos
        const tipoSelect = document.getElementById('sancao_tipo_id');
        tipoSelect.innerHTML = '<option value="">Selecione...</option>';
        data.tipos.forEach(t => {
            tipoSelect.innerHTML += `<option value="${t.id}">${t.titulo}</option>`;
        });
        
        // Load Acoes
        const acoesDiv = document.getElementById('acoesContainer');
        if (data.acoes.length === 0) {
            acoesDiv.innerHTML = '<p style="color:var(--text-muted); grid-column: 1/-1;">Nenhuma ação cadastrada.</p>';
        } else {
            let html = '';
            data.acoes.forEach(a => {
                html += `
                    <label class="checklist-item">
                        <input type="checkbox" name="acoes[]" value="${a.id}">
                        <span>${a.descricao}</span>
                    </label>
                `;
            });
            acoesDiv.innerHTML = html;
        }
    } catch (e) {
        console.error('Erro ao carregar dependências:', e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadSancoes();
    loadDependencies();
});
</script>

<?php 
renderModalScripts();
renderToastScripts();
require_once __DIR__ . '/../includes/footer.php'; 
?>
