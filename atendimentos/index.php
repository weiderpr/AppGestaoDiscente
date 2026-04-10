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
    '/assets/css/kanban.css',
    '/assets/css/sancao_popover.css'
];

require_once __DIR__ . '/../includes/header.php';
renderModalStyles();
renderToastStyles();
?>

<style>
/* ===== Kanban Page — Layout Override ===== */

/* Travar scroll vertical da página inteira — apenas no Kanban */
html, body { overflow: hidden !important; height: 100% !important; }

/* O main cresce pelo flex mas não deve criar scroll vertical */
.main-content {
    padding: 0 !important;
    max-width: 100% !important;
    overflow: hidden !important;
    height: calc(100vh - var(--navbar-height)) !important;
    display: flex !important;
    flex-direction: column !important;
}

/* Ocultar footer nessa página — Kanban usa 100vh */
.app-footer { display: none !important; }

/* Sub-header: faixa fina e discreta — sem sticky (page scroll está bloqueado) */
.kanban-subheader {
    position: relative;
    z-index: 500;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1.25rem;
    height: 42px;
    background: var(--bg-surface);
    border-bottom: 1px solid var(--border-color);
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    flex-shrink: 0;
    gap: 1rem;
}

.kanban-subheader-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.kanban-subheader-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Botão "ghost" minimalista para Novo Atendimento */
.btn-kanban-new {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.3rem 0.75rem;
    border-radius: var(--radius-md);
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--color-primary);
    border: 1px solid var(--color-primary-light);
    background: var(--color-primary-light);
    cursor: pointer;
    transition: all var(--transition-fast);
    white-space: nowrap;
    font-family: inherit;
}

.btn-kanban-new:hover {
    background: var(--color-primary);
    color: #fff;
    border-color: var(--color-primary);
    box-shadow: 0 2px 10px rgba(79,70,229,.25);
}

/* Controle de arquivados */
.kanban-archive-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-muted);
    cursor: pointer;
    white-space: nowrap;
}

/* Kanban Board — Full viewport height */
.kanban-board {
    display: flex;
    overflow-x: auto;
    overflow-y: hidden;
    gap: 0.625rem;
    /* 42px subheader + navbar height */
    height: calc(100vh - var(--navbar-height) - 42px);
    align-items: flex-start;
    padding: 1rem 1.25rem 1rem;
}

/* Scrollbar horizontal discreta */
.kanban-board::-webkit-scrollbar { height: 6px; }
.kanban-board::-webkit-scrollbar-track { background: transparent; }
.kanban-board::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
.kanban-board::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

.kanban-column {
    background: var(--bg-surface-2nd);
    border-radius: var(--radius-lg);
    flex: 1;
    min-width: 260px;
    max-height: 100%;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--border-color);
    flex-shrink: 1;
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
    gap: 0.375rem;
    min-height: 100px;
}

/* Cards */
.k-card {
    background: var(--bg-card, var(--bg-surface));
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 0.5rem 0.75rem;
    cursor: grab;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    transition: transform 0.15s, box-shadow 0.15s;
    user-select: none;
}
.k-card:active { cursor: grabbing; }
.k-card:hover { box-shadow: var(--shadow-md, 0 4px 12px rgba(0,0,0,.08)); transform: translateY(-1px); }
.k-card.dragging { opacity: 0.5; }

.k-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.3rem;
}

.k-badge {
    font-size: 0.5625rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 0.15rem 0.4rem;
    border-radius: 6px;
    letter-spacing: 0.04em;
}
.k-badge-aluno { background: #dbeafe; color: #1e40af; }
.k-badge-turma { background: #fef3c7; color: #92400e; }
.k-badge-encaminhamento { background: #f3e8ff; color: #6b21a8; }

.k-card-title {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.25;
    margin-bottom: 0.35rem;
}

.k-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.35rem;
    border-top: 1px dashed var(--border-color);
    padding-top: 0.35rem;
}
.k-card-date { font-size: 0.65rem; color: var(--text-muted); }
.k-card-users { display: flex; margin-left: 0.5rem; }
.k-card-user {
    width: 20px; height: 20px;
    border-radius: 50%;
    border: 2px solid var(--bg-surface);
    margin-left: -6px;
    background: var(--bg-surface-2nd);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.55rem; color: var(--text-muted); font-weight: 700;
    object-fit: cover; flex-shrink: 0;
}
.k-card-user:first-child { margin-left: 0; }
.k-card-student {
    width: 20px; height: 20px;
    border-radius: 50%;
    border: 1px solid var(--border-color);
    object-fit: cover; flex-shrink: 0;
    background: var(--bg-surface-2nd);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.65rem; font-weight: 700; color: var(--text-muted);
}

/* Modals */
.timeline-container {
    max-height: 400px; overflow-y: auto;
    padding-right: 0.5rem; margin-top: 1rem;
    display: flex; flex-direction: column; gap: 0.25rem;
}
.comment-item {
    background: var(--bg-surface-2nd);
    padding: 0.375rem 0.625rem;
    border-radius: var(--radius-sm);
    font-size: 0.875rem;
    border: 1px solid var(--border-color);
}
.comment-header {
    display: flex; justify-content: space-between;
    margin-bottom: 0.125rem;
    font-size: 0.75rem; color: var(--text-muted);
}
.c-private-badge {
    background: #fee2e2; color: #991b1b;
    padding: 2px 6px; border-radius: 4px;
    font-size: 0.65rem; font-weight: bold;
}

/* Switch Toggle */
.switch { position: relative; display: inline-block; width: 30px; height: 17px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider {
    position: absolute; cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: var(--border-color);
    transition: .3s; border-radius: 17px;
}
.slider:before {
    position: absolute; content: "";
    height: 11px; width: 11px;
    left: 3px; bottom: 3px;
    background-color: white; transition: .3s; border-radius: 50%;
}
input:checked + .slider { background-color: var(--color-primary); }
input:checked + .slider:before { transform: translateX(13px); }

/* Responsive */
@media (max-width: 768px) {
    .kanban-board { scroll-snap-type: x mandatory; padding: 0.75rem; }
    .kanban-column { scroll-snap-align: center; width: 85vw; min-width: 85vw; }
    .kanban-subheader-title { display: none; }
}
</style>

<!-- Sub-header Kanban (sticky, minimalista) -->
<div class="kanban-subheader">
    <span class="kanban-subheader-title">
        📝 Atendimentos
    </span>
    <div class="kanban-subheader-actions">
        <label class="kanban-archive-toggle" for="toggleShowArchived">
            <label class="switch" style="flex-shrink:0;">
                <input type="checkbox" id="toggleShowArchived" onchange="handleArchiveToggle()">
                <span class="slider"></span>
            </label>
            <span>Arquivados</span>
        </label>
        <button class="btn-kanban-new" onclick="openNewAtendimentoModal()">
            + Novo Atendimento
        </button>
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
<?php require_once __DIR__ . '/../includes/student_schedule_modal.php'; ?>

<!-- Import Shared Logic -->
<script src="/assets/js/atendimento_shared.js?v=<?= time() ?>"></script>

<!-- Import Kanban Logic -->
<script src="/assets/js/atendimentos_kanban.js?v=<?= time() ?>"></script>

<script>
    const currentUserId = <?= (int)$user['id'] ?>;
    const currentUserProfile = '<?= addslashes($user['profile']) ?>';
</script>

<script src="/assets/js/sancao_popover.js?v=1.0"></script>

<?php 
renderModalScripts();
renderToastScripts();
require_once __DIR__ . '/../includes/footer.php'; 
?>
