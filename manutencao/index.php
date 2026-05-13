<?php
/**
 * Vértice Acadêmico — Gestão de Manutenções (Kanban)
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

hasDbPermission('manutencao.index');

$canCreate   = hasDbPermission('manutencao.create', false);
$canMove     = hasDbPermission('manutencao.move', false);
$canMaterial = hasDbPermission('manutencao.materials', false);
$canComment  = hasDbPermission('manutencao.comments', false);
$canDelete   = hasDbPermission('manutencao.delete', false);

$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = $inst['id'];

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/manutencao/index.php'));
    exit;
}

require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/Manutencao/AmbienteService.php';
$ambienteService = new \App\Services\Manutencao\AmbienteService();
$ambientes = $ambienteService->getAll($instId);

$pageTitle = "Gestão de Manutenções";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ===== Kanban Page — Layout Override ===== */
html, body { overflow: hidden !important; height: 100% !important; }

.main-content {
    padding: 0 !important;
    max-width: 100% !important;
    overflow: hidden !important;
    height: calc(100vh - var(--navbar-height)) !important;
    display: flex !important;
    flex-direction: column !important;
}

.app-footer { display: none !important; }

.kanban-subheader {
    position: relative;
    z-index: 500;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1.25rem;
    height: 48px;
    background: var(--bg-surface);
    border-bottom: 1px solid var(--border-color);
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    flex-shrink: 0;
    gap: 1rem;
}

.kanban-subheader-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.625rem;
    white-space: nowrap;
}

.btn-kanban-new {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 1rem;
    border-radius: var(--radius-md);
    font-size: 0.8125rem;
    font-weight: 600;
    color: white;
    background: var(--gradient-brand);
    border: none;
    cursor: pointer;
    transition: all var(--transition-fast);
    box-shadow: 0 4px 12px rgba(79,70,229,.3);
}

.btn-kanban-new:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 15px rgba(79,70,229,.4);
}

/* Kanban Board */
.kanban-board {
    display: flex;
    overflow-x: auto;
    overflow-y: hidden;
    gap: 0.75rem;
    height: calc(100vh - var(--navbar-height) - 48px);
    align-items: flex-start;
    padding: 1rem 1.25rem;
    background: var(--bg-page);
}

.kanban-board::-webkit-scrollbar { height: 6px; }
.kanban-board::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }

.kanban-column {
    background: rgba(255,255,255,0.4);
    backdrop-filter: blur(10px);
    border-radius: var(--radius-lg);
    flex: 1;
    min-width: 280px;
    max-height: 100%;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--border-color);
    flex-shrink: 0;
}

[data-theme="dark"] .kanban-column {
    background: rgba(30, 41, 59, 0.4);
}

.kanban-column-header {
    padding: 1rem;
    border-bottom: 2px solid var(--border-color);
    background: var(--bg-surface);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}

.kanban-count {
    background: var(--bg-surface-2nd);
    padding: 2px 10px;
    border-radius: var(--radius-full);
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--color-primary);
    border: 1px solid var(--border-color);
}

.kanban-cards {
    padding: 0.75rem;
    flex-grow: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-height: 100px;
}

/* Cards */
.k-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 0.875rem;
    cursor: grab;
    box-shadow: var(--card-shadow);
    transition: all 0.2s ease;
    user-select: none;
}
.k-card:active { cursor: grabbing; }
.k-card:hover { 
    box-shadow: var(--card-shadow-hover); 
    transform: translateY(-2px);
    border-color: var(--color-primary);
}
.k-card.dragging { opacity: 0.4; transform: scale(0.95); }

.k-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.k-badge {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    letter-spacing: 0.04em;
}
.k-badge-turma { background: var(--color-primary-light); color: var(--color-primary); }

.k-card-title {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.4;
    margin-bottom: 0.4rem;
}

.k-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.75rem;
    border-top: 1px solid var(--border-color);
    padding-top: 0.6rem;
}
.k-card-date { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }

/* Modal Detalhes (80% largura) */
.modal-80 {
    width: 80% !important;
    max-width: 1400px !important;
    height: 85vh; /* Altura fixa para evitar saltos de layout */
}

.modal-80 .modal-content {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.modal-80 .modal-body {
    flex: 1;
    overflow-y: auto;
}

/* Estrutura de Abas */
.tabs-container {
    display: flex;
    flex-direction: column;
    flex: 1; /* Ocupa o restante do corpo do modal */
}

.tabs-header {
    display: flex;
    gap: 1.5rem;
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 1.5rem;
}

.tab-btn {
    background: none;
    border: none;
    padding: 0.75rem 0.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    position: relative;
    transition: all var(--transition-fast);
}

.tab-btn:hover {
    color: var(--color-primary);
}

.tab-btn.active {
    color: var(--color-primary);
}

.tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--color-primary);
    border-radius: 2px;
}

.tab-content {
    display: none;
    animation: fadeIn 0.3s ease;
}

.tab-content.active {
    display: flex;
    flex-direction: column;
    flex: 1;
}

/* Header de Detalhes no Modal */
.maintenance-info-header {
    background: var(--bg-surface-2nd);
    padding: 1.25rem;
    border-radius: var(--radius-lg);
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.02em;
}

.info-value {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--text-primary);
}

@media (max-width: 1024px) {
    .modal-80 { width: 95% !important; }
}

/* Photo Thumbnail and Preview */
.detail-photo-container {
    margin-top: 1.5rem;
}
.detail-photo-wrapper {
    width: 100%;
    max-width: 300px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    background: var(--bg-surface-2nd);
}
.detail-photo-wrapper:hover {
    transform: scale(1.02);
    box-shadow: var(--card-shadow-hover);
}
.detail-photo-thumb {
    width: 100%;
    height: 180px;
    object-fit: cover;
    display: block;
}
.photo-preview-img {
    width: 100%;
    max-height: 70vh;
    object-fit: contain;
    border-radius: var(--radius-md);
    background: #000;
}

/* Comments Feed Styles */
.comment-feed {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
    max-height: 400px;
    overflow-y: auto;
    padding-right: 0.5rem;
}
.comment-item {
    background: var(--bg-surface-2nd);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 0.875rem;
}
.comment-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    margin-bottom: 0.5rem;
    color: var(--text-muted);
}
.comment-user { font-weight: 700; color: var(--color-primary); }
.comment-text {
    font-size: 0.875rem;
    line-height: 1.5;
    color: var(--text-primary);
    white-space: pre-wrap;
}

/* Materials Table/List Styles */
.materials-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.material-item {
    background: var(--bg-surface-2nd);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 1rem;
    display: grid;
    grid-template-columns: 1fr 1fr 120px 40px;
    align-items: center;
    gap: 1rem;
}
.material-info { display: flex; flex-direction: column; gap: 2px; }
.material-label { font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
.material-value-text { font-weight: 700; color: var(--color-primary); }

.material-total-bar {
    background: var(--color-primary);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1.5rem;
    box-shadow: 0 4px 12px rgba(79,70,229,0.3);
}
.material-total-label { font-weight: 600; font-size: 0.9rem; }
.material-total-value { font-size: 1.25rem; font-weight: 800; }

@media (max-width: 768px) {
    .material-item { grid-template-columns: 1fr; gap: 0.75rem; }
    .material-item button { justify-self: flex-end; }
}
</style>

<div class="kanban-subheader">
    <span class="kanban-subheader-title">
        🛠️ Quadro de Manutenções
    </span>
    <div class="kanban-subheader-actions">
        <?php if ($canCreate): ?>
        <button class="btn-kanban-new" onclick="openNewManutencaoModal()">
            <span>➕</span> Nova Manutenção
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="kanban-board" id="kanbanBoard">
    <?= csrf_field() ?>
    
    <!-- Coluna: Demandas -->
    <div class="kanban-column" data-status="Demandas" style="border-top: 4px solid var(--text-muted);">
        <div class="kanban-column-header">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom:0.75rem;">
                <span style="display:flex;align-items:center;gap:0.5rem;font-weight:700;">📥 Demandas</span>
                <span class="kanban-count" id="count-Demandas">0</span>
            </div>
            <div style="position: relative;">
                <input type="text" class="column-filter" placeholder="Filtrar nesta coluna..." oninput="filterColumn('Demandas', this.value)" style="width: 100%; border-radius: 8px; border: 1px solid var(--border-color); padding: 6px 10px 6px 28px; font-size: 0.8125rem; background: var(--bg-input);">
                <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; opacity: 0.5;">🔍</span>
            </div>
        </div>
        <div class="kanban-cards" id="col-Demandas"></div>
    </div>

    <!-- Coluna: Em Aberto -->
    <div class="kanban-column" data-status="Em Aberto" style="border-top: 4px solid #3b82f6;">
        <div class="kanban-column-header">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom:0.75rem;">
                <span style="display:flex;align-items:center;gap:0.5rem;font-weight:700;">📋 Em Aberto</span>
                <span class="kanban-count" id="count-Em Aberto">0</span>
            </div>
            <div style="position: relative;">
                <input type="text" class="column-filter" placeholder="Filtrar..." oninput="filterColumn('Em Aberto', this.value)" style="width: 100%; border-radius: 8px; border: 1px solid var(--border-color); padding: 6px 10px 6px 28px; font-size: 0.8125rem; background: var(--bg-input);">
                <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; opacity: 0.5;">🔍</span>
            </div>
        </div>
        <div class="kanban-cards" id="col-Em Aberto"></div>
    </div>

    <!-- Coluna: Em Execução -->
    <div class="kanban-column" data-status="Em Execução" style="border-top: 4px solid #f59e0b;">
        <div class="kanban-column-header">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom:0.75rem;">
                <span style="display:flex;align-items:center;gap:0.5rem;font-weight:700;">⚙️ Em Execução</span>
                <span class="kanban-count" id="count-Em Execução">0</span>
            </div>
            <div style="position: relative;">
                <input type="text" class="column-filter" placeholder="Filtrar..." oninput="filterColumn('Em Execução', this.value)" style="width: 100%; border-radius: 8px; border: 1px solid var(--border-color); padding: 6px 10px 6px 28px; font-size: 0.8125rem; background: var(--bg-input);">
                <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; opacity: 0.5;">🔍</span>
            </div>
        </div>
        <div class="kanban-cards" id="col-Em Execução"></div>
    </div>

    <!-- Coluna: Finalizado -->
    <div class="kanban-column" data-status="Finalizado" style="border-top: 4px solid #10b981;">
        <div class="kanban-column-header">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom:0.75rem;">
                <span style="display:flex;align-items:center;gap:0.5rem;font-weight:700;">✅ Finalizado</span>
                <span class="kanban-count" id="count-Finalizado">0</span>
            </div>
            <div style="position: relative;">
                <input type="text" class="column-filter" placeholder="Filtrar..." oninput="filterColumn('Finalizado', this.value)" style="width: 100%; border-radius: 8px; border: 1px solid var(--border-color); padding: 6px 10px 6px 28px; font-size: 0.8125rem; background: var(--bg-input);">
                <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; opacity: 0.5;">🔍</span>
            </div>
        </div>
        <div class="kanban-cards" id="col-Finalizado"></div>
    </div>
</div>

<!-- Modal: Detalhes da Manutenção -->
<div id="modalMaintenanceDetails" class="modal-wrapper modal-hide" role="dialog" aria-modal="true">
    <div class="modal-overlay" onclick="closeModal('modalMaintenanceDetails')">
        <div class="modal-dialog modal-80" onclick="event.stopPropagation()">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Detalhes da Manutenção</h3>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span id="detailStatusBadge" class="k-badge">STATUS</span>
                        <button type="button" class="modal-close" onclick="closeModal('modalMaintenanceDetails')">✕</button>
                    </div>
                </div>
                <div class="modal-body" style="padding-top: 1rem;">
                    
                    <!-- Cabeçalho de Dados Rápidos -->
                    <div class="maintenance-info-header">
                        <div class="info-item">
                            <span class="info-label">Ambiente</span>
                            <span class="info-value" id="detailAmbiente">---</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Localização</span>
                            <span class="info-value" id="detailLocal">---</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">ID da Manutenção</span>
                            <span class="info-value" id="detailId">---</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Data de Abertura</span>
                            <span class="info-value" id="detailData">---</span>
                        </div>
                    </div>

                    <!-- Navegação por Abas -->
                    <div class="tabs-container">
                        <div class="tabs-header">
                            <button class="tab-btn active" onclick="switchMaintenanceTab('detalhes', this)">📋 Detalhes</button>
                            <button class="tab-btn" onclick="switchMaintenanceTab('comentarios', this)">💬 Comentários</button>
                            <button class="tab-btn" onclick="switchMaintenanceTab('materiais', this)">📦 Materiais</button>
                        </div>

                        <!-- Conteúdo: Detalhes -->
                        <div id="tab-detalhes" class="tab-content active">
                            <div class="form-group">
                                <label class="form-label">Descrição Original</label>
                                <div id="detailDescricao" style="background: var(--bg-surface-2nd); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); min-height: 80px;">
                                    ---
                                </div>
                            </div>

                            <div class="mt-md">
                                <label class="form-label">Problemas Identificados</label>
                                <div id="detailProblemas" class="problemas-grid" style="margin-top: 0.5rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.5rem;">
                                    <!-- Listagem de problemas -->
                                </div>
                            </div>

                            <div id="detailPhotoContainer" class="detail-photo-container" style="display:none;">
                                <label class="form-label">Evidência Fotográfica</label>
                                <div class="detail-photo-wrapper" onclick="openPhotoPreview()">
                                    <img id="detailPhotoImg" src="" class="detail-photo-thumb" alt="Evidência">
                                </div>
                            </div>
                        </div>

                        <!-- Conteúdo: Comentários -->
                        <div id="tab-comentarios" class="tab-content">
                            <div id="commentsFeed" class="comment-feed">
                                <p class="text-muted" style="text-align: center; padding: 2rem;">Carregando comentários...</p>
                            </div>
                            
                            <hr class="mt-md mb-md">
                            
                            <?php if ($canComment): ?>
                            <div class="comment-form">
                                <label class="form-label">Adicionar Comentário</label>
                                <textarea id="newCommentText" class="form-control" rows="3" placeholder="Escreva aqui as observações ou etapas realizadas..."></textarea>
                                <div style="display: flex; justify-content: flex-end; margin-top: 0.75rem;">
                                    <button type="button" class="btn btn-primary" onclick="submitComment()">
                                        <span>💬</span> Enviar Comentário
                                    </button>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info" style="font-size: 0.8125rem;">
                                ℹ️ Você tem permissão apenas para visualizar os comentários.
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Conteúdo: Materiais -->
                        <div id="tab-materiais" class="tab-content">
                            <?php if ($canMaterial): ?>
                            <div class="material-form bg-surface-2nd p-sm rounded-lg mb-md" style="border: 1px solid var(--border-color);">
                                <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; padding: 0.5rem;">
                                    <div style="flex: 2; min-width: 200px;">
                                        <label class="form-label" style="font-size: 0.7rem; margin-bottom: 4px; display: block; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Descrição do Material</label>
                                        <input type="text" id="matDescricao" class="form-control form-control-sm">
                                    </div>
                                    <div style="flex: 1.5; min-width: 150px;">
                                        <label class="form-label" style="font-size: 0.7rem; margin-bottom: 4px; display: block; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Local de Compra</label>
                                        <input type="text" id="matLocal" class="form-control form-control-sm">
                                    </div>
                                    <div style="width: 130px;">
                                        <label class="form-label" style="font-size: 0.7rem; margin-bottom: 4px; display: block; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Valor (R$)</label>
                                        <input type="text" id="matValor" class="form-control form-control-sm money-mask">
                                    </div>
                                    <div style="flex-shrink: 0;">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="submitMaterial()" style="height: 32px; padding: 0 1.25rem; font-weight: 700;">
                                            ➕ Adicionar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info" style="font-size: 0.8125rem;">
                                ℹ️ Você tem permissão apenas para visualizar a lista de materiais.
                            </div>
                            <?php endif; ?>

                            <div id="materialsList" class="materials-container">
                                <p class="text-muted" style="text-align: center; padding: 2rem;">Carregando materiais...</p>
                            </div>

                            <div class="material-total-bar">
                                <span class="material-total-label">Custo Total da Manutenção</span>
                                <span class="material-total-value" id="matTotalValue">R$ 0,00</span>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer" style="justify-content: space-between;">
                    <div>
                        <?php if ($canDelete): ?>
                        <button type="button" class="btn btn-danger" onclick="deleteMaintenance()">
                            <span>🗑️</span> Excluir Manutenção
                        </button>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalMaintenanceDetails')">Fechar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Nova Manutenção -->
<div id="modalNewManutencao" class="modal-wrapper modal-hide" role="dialog" aria-modal="true">
    <div class="modal-overlay" onclick="closeModal('modalNewManutencao')">
        <div class="modal-dialog modal-md" onclick="event.stopPropagation()">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Nova Solicitação de Manutenção</h3>
                    <button type="button" class="modal-close" onclick="closeModal('modalNewManutencao')">✕</button>
                </div>
                <form id="formNewManutencao">
                    <?= csrf_field() ?>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Ambiente <span class="required">*</span></label>
                            <select name="ambiente_id" class="form-control" required onchange="loadAmbienteProblemas(this.value)">
                                <option value="">Selecione o Ambiente...</option>
                                <?php foreach ($ambientes as $amb): ?>
                                <option value="<?= $amb['id'] ?>"><?= htmlspecialchars($amb['descricao']) ?> (<?= htmlspecialchars($amb['predio_campus']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Descrição do Problema <span class="required">*</span></label>
                            <textarea name="descricao" class="form-control" required rows="3" placeholder="Descreva detalhadamente o que precisa ser feito..."></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Data de Abertura</label>
                            <input type="datetime-local" name="data_manutencao" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Problemas Padrão para Verificar</label>
                            <div id="problemasChecklist">
                                <p style="font-size:0.8rem;color:var(--text-muted);text-align:center;padding:1rem;">Selecione um ambiente primeiro.</p>
                            </div>
                        </div>

                        <div class="form-group" id="groupOutrosDetalhes" style="display:none;">
                            <label class="form-label">Especifique o Problema (Outros)</label>
                            <textarea name="outros_detalhes" class="form-control" rows="2" placeholder="Descreva o problema não listado acima..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modalNewManutencao')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Criar Solicitação</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Pré-visualização de Foto -->
<div id="modalPhotoPreview" class="modal-wrapper modal-hide" role="dialog" aria-modal="true" style="z-index: 10000;">
    <div class="modal-overlay" onclick="closeModal('modalPhotoPreview')">
        <div class="modal-dialog modal-lg" onclick="event.stopPropagation()">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Visualizar Evidência</h3>
                    <button type="button" class="modal-close" onclick="closeModal('modalPhotoPreview')">✕</button>
                </div>
                <div class="modal-body" style="padding: 1rem; background: var(--bg-page); display: flex; align-items: center; justify-content: center; min-height: 400px;">
                    <img id="photoPreviewFull" src="" class="photo-preview-img" style="box-shadow: var(--shadow-lg);">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalPhotoPreview')">Fechar</button>
                    <a id="btnDownloadPhoto" href="" download="evidencia_manutencao.jpg" class="btn btn-primary" style="text-decoration: none;">
                        <span>📥</span> Download
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Variáveis de Permissão para o JS -->
<script>
    const CAN_CREATE_MANUTENCAO = <?= $canCreate ? 'true' : 'false' ?>;
    const CAN_MOVE_MANUTENCAO   = <?= $canMove ? 'true' : 'false' ?>;
    const CAN_MATERIAL_MANUTENCAO = <?= $canMaterial ? 'true' : 'false' ?>;
    const CAN_COMMENT_MANUTENCAO  = <?= $canComment ? 'true' : 'false' ?>;
    const CAN_DELETE_MANUTENCAO   = <?= $canDelete ? 'true' : 'false' ?>;
</script>

<script src="/assets/js/manutencao_kanban.js?v=<?= time() ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
