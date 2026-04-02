<?php
/**
 * Vértice Acadêmico — Gestão de Atendimentos (Kanban)
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Permissão (Ajuste conforme sua matriz RBAC)
hasDbPermission('atendimentos.index');

$user = getCurrentUser();
$inst = getCurrentInstitution();

require_once __DIR__ . '/../includes/toast.php';
require_once __DIR__ . '/../includes/modal.php';

$pageTitle = "Gestão de Atendimentos";
$extraCSS = [
    '/assets/css/kanban.css' // We will put Kanban specific styles here or embedded.
];

require_once __DIR__ . '/../includes/header.php';
renderModalStyles();
renderToastStyles();
?>

<style>
/* Kanban Specific Styles */
.kanban-board {
    display: flex;
    overflow-x: auto;
    overflow-y: hidden;
    gap: 1.25rem;
    padding: 0.5rem 0 1.5rem 0;
    height: calc(100vh - 160px); /* Ajuste dinâmico */
    align-items: flex-start;
}

/* Esconder scrollbar no Webkit */
.kanban-board::-webkit-scrollbar { height: 8px; }
.kanban-board::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }

.kanban-column {
    background: var(--bg-surface-2nd);
    border-radius: var(--radius-lg);
    width: 320px;
    min-width: 320px;
    max-height: 100%;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--border-color);
}

.kanban-column-header {
    padding: 1rem 1.25rem;
    font-weight: 700;
    font-size: 0.9375rem;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-surface);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}

.kanban-count {
    background: var(--bg-surface-2nd);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.kanban-cards {
    padding: 0.75rem;
    flex-grow: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-height: 100px; /* Para permitir drop quando vazio */
}

/* Estilo do Card */
.k-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 1rem;
    cursor: grab;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    transition: transform 0.2s, box-shadow 0.2s;
    user-select: none;
}

.k-card:active {
    cursor: grabbing;
}

.k-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.k-card.dragging {
    opacity: 0.5;
}

.k-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.k-badge {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 0.2rem 0.5rem;
    border-radius: 8px;
    letter-spacing: 0.05em;
}

.k-badge-aluno { background: #dbeafe; color: #1e40af; }
.k-badge-turma { background: #fef3c7; color: #92400e; }
.k-badge-encaminhamento { background: #f3e8ff; color: #6b21a8; }

.k-card-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.3;
    margin-bottom: 0.75rem;
}

.k-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.75rem;
    border-top: 1px dashed var(--border-color);
    padding-top: 0.75rem;
}

.k-card-date {
    font-size: 0.7rem;
    color: var(--text-muted);
}

.k-card-users {
    display: flex;
    margin-left: 0.5rem;
}

.k-card-user {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid var(--bg-card);
    margin-left: -8px;
    background: var(--bg-surface-2nd);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    color: var(--text-muted);
    font-weight: 700;
    object-fit: cover;
    flex-shrink: 0;
}

.k-card-user:first-child {
    margin-left: 0;
}

.k-card-student {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 1px solid var(--border-color);
    object-fit: cover;
    flex-shrink: 0;
    background: var(--bg-surface-2nd);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
}

/* Modals Customizados para o Kanban */
.timeline-container {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 0.5rem;
    margin-top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.comment-item {
    background: var(--bg-surface-2nd);
    padding: 0.375rem 0.625rem;
    border-radius: var(--radius-sm);
    font-size: 0.875rem;
    border: 1px solid var(--border-color);
}
.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.125rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}
.c-private-badge {
    background: #fee2e2;
    color: #991b1b;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: bold;
}

@media (max-width: 768px) {
    .kanban-board {
        scroll-snap-type: x mandatory;
    }
    .kanban-column {
        scroll-snap-align: center;
        width: 85vw;
        min-width: 85vw;
    }
}
/* Switch Toggle Style (liga/desliga) */
.switch {
  position: relative;
  display: inline-block;
  width: 34px;
  height: 20px;
}
.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}
.slider {
  position: absolute;
  cursor: pointer;
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: var(--border-color);
  transition: .3s;
  border-radius: 20px;
}
.slider:before {
  position: absolute;
  content: "";
  height: 14px;
  width: 14px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .3s;
  border-radius: 50%;
}
input:checked + .slider {
  background-color: var(--color-primary);
}
input:checked + .slider:before {
  transform: translateX(14px);
}
</style>

<div class="page-header" style="margin-bottom: 1rem;">
    <div class="header-content">
        <h1 class="page-title">Gestão de Atendimentos</h1>
        <p class="page-subtitle">Gestão dos atendimentos</p>
    </div>
</div>

<div class="kanban-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <!-- Só exibe se tiver permissão de criação -->
    <button class="btn btn-primary" onclick="openNewAtendimentoModal()">
        <span class="btn-icon">+</span> Novo Atendimento
    </button>

    <div style="display:flex; align-items:center; gap:0.75rem; background:var(--bg-surface-2nd); padding:0.4rem 0.75rem; border-radius:var(--radius-md); border:1px solid var(--border-color); box-shadow:var(--shadow-sm);">
        <span style="font-size:0.8125rem; font-weight:600; color:var(--text-secondary);">Exibir Arquivados</span>
        <label class="switch">
            <input type="checkbox" id="toggleShowArchived" onchange="handleArchiveToggle()">
            <span class="slider"></span>
        </label>
    </div>
</div>

<div class="kanban-board" id="kanbanBoard">
    
    <!-- Coluna: Demandas -->
    <div class="kanban-column" data-status="Demandas">
        <div class="kanban-column-header" style="flex-direction: column; align-items: stretch; gap: 0.5rem; padding-bottom: 0.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="display:flex;align-items:center;gap:0.5rem;">📥 Demandas</span>
                <span class="kanban-count" id="count-Demandas">0</span>
            </div>
            <div style="position: relative;">
                <input type="text" class="column-filter" placeholder="Filtrar..." oninput="filterColumn('Demandas', this.value)" style="width: 100%; border-radius: 6px; border: 1px solid var(--border-color); padding: 4px 8px 4px 24px; font-size: 0.75rem; background: var(--bg-surface-2nd);">
                <span style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; opacity: 0.4;">🔍</span>
            </div>
        </div>
        <div class="kanban-cards" id="col-Demandas">
            <!-- Cards injetados via JS -->
            <!-- Placeholder para drag/drop empty -->
        </div>
    </div>

    <!-- Coluna: Aberto -->
    <div class="kanban-column" style="border-top: 3px solid #3b82f6;" data-status="Aberto">
        <div class="kanban-column-header" style="flex-direction: column; align-items: stretch; gap: 0.5rem; padding-bottom: 0.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="display:flex;align-items:center;gap:0.5rem;">📋 Em Aberto</span>
                <span class="kanban-count" id="count-Aberto">0</span>
            </div>
            <div style="position: relative;">
                <input type="text" class="column-filter" placeholder="Filtrar..." oninput="filterColumn('Aberto', this.value)" style="width: 100%; border-radius: 6px; border: 1px solid var(--border-color); padding: 4px 8px 4px 24px; font-size: 0.75rem; background: var(--bg-surface-2nd);">
                <span style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; opacity: 0.4;">🔍</span>
            </div>
        </div>
        <div class="kanban-cards" id="col-Aberto">
            <!-- Cards injetados via JS -->
        </div>
    </div>

    <!-- Coluna: Em Atendimento -->
    <div class="kanban-column" style="border-top: 3px solid #f59e0b;" data-status="Em Atendimento">
        <div class="kanban-column-header" style="flex-direction: column; align-items: stretch; gap: 0.5rem; padding-bottom: 0.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="display:flex;align-items:center;gap:0.5rem;">⚙️ Em Atendimento</span>
                <span class="kanban-count" id="count-Em Atendimento">0</span>
            </div>
            <div style="position: relative;">
                <input type="text" class="column-filter" placeholder="Filtrar..." oninput="filterColumn('Em Atendimento', this.value)" style="width: 100%; border-radius: 6px; border: 1px solid var(--border-color); padding: 4px 8px 4px 24px; font-size: 0.75rem; background: var(--bg-surface-2nd);">
                <span style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; opacity: 0.4;">🔍</span>
            </div>
        </div>
        <div class="kanban-cards" id="col-Em Atendimento"></div>
    </div>

    <!-- Coluna: Finalizado -->
    <div class="kanban-column" style="border-top: 3px solid #10b981;" data-status="Finalizado">
        <div class="kanban-column-header" style="flex-direction: column; align-items: stretch; gap: 0.5rem; padding-bottom: 0.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="display:flex;align-items:center;gap:0.5rem;">✅ Finalizado</span>
                <span class="kanban-count" id="count-Finalizado">0</span>
            </div>
            <div style="position: relative;">
                <input type="text" class="column-filter" placeholder="Filtrar..." oninput="filterColumn('Finalizado', this.value)" style="width: 100%; border-radius: 6px; border: 1px solid var(--border-color); padding: 4px 8px 4px 24px; font-size: 0.75rem; background: var(--bg-surface-2nd);">
                <span style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; opacity: 0.4;">🔍</span>
            </div>
        </div>
        <div class="kanban-cards" id="col-Finalizado"></div>
    </div>

</div>

<!-- Modal: Novo Atendimento -->
<div class="modal-backdrop" id="modalNewAtendimento" role="dialog">
    <div class="modal" style="max-width: 600px; min-height: 550px; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h3>Criar Novo Atendimento</h3>
            <button class="modal-close" onclick="closeModal('modalNewAtendimento')">×</button>
        </div>
        <div class="modal-body" style="flex: 1;">
            <form id="formNewAtendimento">
                <div class="form-group">
                    <label>Título do Atendimento</label>
                    <input type="text" name="titulo" class="form-control" required placeholder="Ex: Acompanhamento de Faltas">
                </div>
                
                <div class="form-group">
                    <label>Tipo de Vínculo</label>
                    <select id="tipoVinculo" class="form-control" onchange="toggleVinculoType()">
                        <option value="aluno">Aluno Específico</option>
                        <option value="turma">Turma Inteira</option>
                    </select>
                </div>

                <div class="form-group" style="position:relative;" id="vinculoAlunoGroup">
                    <label>Buscar Aluno (Nome ou Matrícula)</label>
                    <input type="text" id="searchAlunoInput" class="form-control" placeholder="Digite para buscar..." oninput="debounceSearchAluno(this.value)" autocomplete="off">
                    <input type="hidden" name="aluno_id" id="inputAlunoId">
                    <div id="searchAlunoResults" style="position:absolute; background:var(--bg-card); width:100%; max-height:280px; overflow-y:auto; border:1px solid var(--border-color); border-radius:0 0 8px 8px; box-shadow:0 4px 6px rgba(0,0,0,0.1); display:none; z-index:100;"></div>
                    <div id="selectedAlunoInfo" style="margin-top:0.5rem; font-size:0.875rem; color:var(--text-muted); display:none;">
                        Selecionado: <strong id="selectedAlunoName" style="color:var(--text-primary);"></strong>
                        <button type="button" onclick="clearAlunoSelection()" style="border:none; background:transparent; color:#ef4444; cursor:pointer;" title="Remover seleção">✖</button>
                    </div>
                </div>

                <div class="form-group" style="position:relative; display:none;" id="vinculoTurmaGroup">
                    <label>Buscar Turma</label>
                    <input type="text" id="searchTurmaInput" class="form-control" placeholder="Digite para buscar..." oninput="debounceSearchTurma(this.value)" autocomplete="off">
                    <input type="hidden" name="turma_id" id="inputTurmaId">
                    <div id="searchTurmaResults" style="position:absolute; background:var(--bg-card); width:100%; max-height:280px; overflow-y:auto; border:1px solid var(--border-color); border-radius:0 0 8px 8px; box-shadow:0 4px 6px rgba(0,0,0,0.1); display:none; z-index:100;"></div>
                    <div id="selectedTurmaInfo" style="margin-top:0.5rem; font-size:0.875rem; color:var(--text-muted); display:none;">
                        Selecionado: <strong id="selectedTurmaName" style="color:var(--text-primary);"></strong>
                        <button type="button" onclick="clearTurmaSelection()" style="border:none; background:transparent; color:#ef4444; cursor:pointer;" title="Remover seleção">✖</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNewAtendimento')">Cancelar</button>
            <button class="btn btn-primary" onclick="submitNewAtendimento()">Criar Atendimento</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/atendimento_detalhes_modal.php'; ?>

<!-- Import Shared Logic -->
<script src="/assets/js/atendimento_shared.js"></script>

<!-- Import Kanban Logic -->
<script src="/assets/js/atendimentos_kanban.js?v=1.2"></script>

<script>
    const currentUserId = <?= (int)$user['id'] ?>;
    const currentUserProfile = '<?= addslashes($user['profile']) ?>';
</script>

<?php 
renderModalScripts();
renderToastScripts();
require_once __DIR__ . '/../includes/footer.php'; 
?>
