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
}

/* Modals Customizados para o Kanban */
.timeline-container {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 0.5rem;
    margin-top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.comment-item {
    background: var(--bg-surface-2nd);
    padding: 1rem;
    border-radius: var(--radius-md);
    font-size: 0.875rem;
}
.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
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
</style>

<div class="page-header">
    <div class="header-content">
        <h1 class="page-title">Gestão de Atendimentos</h1>
        <p class="page-subtitle">Quadro Kanban interativo para acompanhamento pedagógico.</p>
    </div>
    <div class="header-actions">
        <!-- Só exibe se tiver permissão de criação -->
        <button class="btn btn-primary" onclick="openNewAtendimentoModal()">
            <span class="btn-icon">+</span> Novo Atendimento
        </button>
    </div>
</div>

<div class="kanban-board" id="kanbanBoard">
    
    <!-- Coluna: Demandas -->
    <div class="kanban-column" data-status="Demandas">
        <div class="kanban-column-header">
            <span style="display:flex;align-items:center;gap:0.5rem;">📥 Demandas</span>
            <span class="kanban-count" id="count-Demandas">0</span>
        </div>
        <div class="kanban-cards" id="col-Demandas">
            <!-- Cards injetados via JS -->
            <!-- Placeholder para drag/drop empty -->
        </div>
    </div>

    <!-- Coluna: Aberto -->
    <div class="kanban-column" style="border-top: 3px solid #3b82f6;" data-status="Aberto">
        <div class="kanban-column-header">
            <span style="display:flex;align-items:center;gap:0.5rem;">📋 Em Aberto</span>
            <span class="kanban-count" id="count-Aberto">0</span>
        </div>
        <div class="kanban-cards" id="col-Aberto">
            <!-- Cards injetados via JS -->
        </div>
    </div>

    <!-- Coluna: Em Atendimento -->
    <div class="kanban-column" style="border-top: 3px solid #f59e0b;" data-status="Em Atendimento">
        <div class="kanban-column-header">
            <span style="display:flex;align-items:center;gap:0.5rem;">⚙️ Em Atendimento</span>
            <span class="kanban-count" id="count-Em Atendimento">0</span>
        </div>
        <div class="kanban-cards" id="col-Em Atendimento"></div>
    </div>

    <!-- Coluna: Finalizado -->
    <div class="kanban-column" style="border-top: 3px solid #10b981;" data-status="Finalizado">
        <div class="kanban-column-header">
            <span style="display:flex;align-items:center;gap:0.5rem;">✅ Finalizado</span>
            <span class="kanban-count" id="count-Finalizado">0</span>
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
                        <option value="turma">Turma Indireta</option>
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

<!-- Modal: Detalhes do Card -->
<div class="modal-backdrop" id="modalCardDetails" role="dialog">
    <div class="modal" style="max-width: 800px; width: 95vw;">
        <div class="modal-header">
            <h3 id="cdTitle">Detalhes do Atendimento</h3>
            <button class="modal-close" onclick="closeModal('modalCardDetails')">×</button>
        </div>
        <div class="modal-body" style="padding: 0;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0; min-height: 400px;">
                <!-- Main Area (Left) -->
                <div style="padding: 2rem; border-right: 1px solid var(--border-color); overflow-y: auto; max-height: 80vh;">
                    
                    <!-- Student Header -->
                    <div style="display: flex; align-items: flex-start; gap: 1.5rem; margin-bottom: 2rem;">
                        <img id="cdAlunoPhoto" src="" style="width: 84px; height: 84px; border-radius: var(--radius-lg); object-fit: cover; display: none; border: 2px solid var(--border-color); box-shadow: var(--shadow-sm);">
                        <div id="cdAlunoAvatar" class="m-aluno-avatar-text" style="width: 84px; height: 84px; font-size: 2rem; display: none; border-radius: var(--radius-lg);"></div>
                        
                        <div style="flex: 1;">
                            <div id="cdBadgeStatus" class="kanban-badge" style="margin-bottom: 0.5rem; display: inline-block;">Status</div>
                            <h2 id="cdMainTitle" style="margin: 0.25rem 0 0.5rem 0; line-height: 1.2; font-size: 1.5rem;">Título</h2>
                            <div id="cdAlunoSubtitle" style="font-size: 0.9375rem; color: var(--text-muted); font-weight: 600;"></div>
                        </div>
                    </div>

                    <!-- Informações Restritas a "Em Atendimento" para edição -->
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Informação Pública (Visível para o aluno em relatórios)</label>
                        <textarea class="form-control" id="cdDescPublica" rows="3" placeholder="Descreva o que será visível para o aluno..."></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label class="form-label">Anotação Profissional (Privado ao conselho/equipe)</label>
                        <textarea class="form-control" id="cdDescProfissional" rows="3" placeholder="Anotações internas para a equipe..."></textarea>
                    </div>

                    <div style="text-align: right; margin-bottom: 2rem; border-top: 1px solid var(--border-color-light); padding-top: 0.75rem;">
                        <button class="btn btn-primary" onclick="saveAtendimentoInfo()">
                            <span style="margin-right:0.5rem;">💾</span> Salvar Descrições
                        </button>
                    </div>

                    <!-- Timeline de Comentários -->
                    <h4 style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1.25rem; font-size:1rem;">
                        <span>💬</span> Timeline de Ações e Comentários
                    </h4>
                    <div style="background: var(--bg-surface-2nd); padding: 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                        <form id="formNewComment" style="display:flex; gap:0.75rem; flex-direction:column;">
                            <textarea class="form-control" id="ncTexto" rows="2" placeholder="Escreva uma atualização ou observação..."></textarea>
                            <div style="display:flex; justify-content: space-between; align-items: center;">
                                <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.8125rem; color:var(--text-muted); cursor:pointer;">
                                    <input type="checkbox" id="ncPrivate" style="width:14px; height:14px;"> 
                                    <span style="display:flex; align-items:center; gap:0.25rem;">🔒 Registro Privado</span>
                                </label>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="submitComment()">
                                    Postar Registro
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="timeline-container" id="cdTimeline">
                        <!-- Comentários via JS -->
                    </div>
                </div>

                <!-- Sidebar (Right) -->
                <div style="padding: 1.5rem; background: var(--bg-surface-2nd); overflow-y: auto; max-height: 80vh; display:flex; flex-direction:column; gap:1.5rem;">
                    <div>
                        <h4 style="margin-bottom: 1rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em; color: var(--text-muted); display:flex; align-items:center; gap:0.375rem;">
                            <span>👥</span> Equipe Responsável
                        </h4>
                        <div id="cdResponsaveisList" style="display:flex; flex-direction: column; gap: 0.5rem;">
                            <!-- JS -->
                        </div>
                    </div>

                    <div style="border-top: 1px dashed var(--border-color); padding-top: 1.25rem;">
                        <div style="position:relative;">
                            <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:0.8rem; opacity:0.5;">🔍</span>
                            <input type="text" id="userSearch" class="form-control form-control-sm" placeholder="Adicionar profissional..." oninput="searchUsers(this.value)" style="padding-left:30px;">
                        </div>
                        <div id="userSearchResults" style="margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.375rem;"></div>
                    </div>

                    <!-- Botão de Excluir (Final da Sidebar) -->
                    <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-color-light);">
                        <button class="btn btn-outline-danger btn-sm" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-color: transparent;" onclick="deleteAtendimento()">
                            <span>🗑️</span> Excluir Card
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Kanban Logic -->
<script src="/assets/js/atendimentos_kanban.js"></script>

<?php 
renderModalScripts();
renderToastScripts();
require_once __DIR__ . '/../includes/footer.php'; 
?>
