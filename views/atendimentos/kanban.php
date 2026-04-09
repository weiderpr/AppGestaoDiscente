<!-- Kanban View -->
<style>
/* Kanban Specific Styles */
.kanban-board {
    display: flex;
    overflow-x: auto;
    overflow-y: hidden;
    gap: 1.25rem;
    padding: 0.5rem 0 1.5rem 0;
    height: calc(100vh - 160px);
    align-items: flex-start;
}

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
    min-height: 100px;
}

.switch {
  position: relative;
  display: inline-block;
  width: 34px;
  height: 20px;
}
.switch input { opacity: 0; width: 0; height: 0; }
.slider {
  position: absolute; cursor: pointer;
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: var(--border-color);
  transition: .3s; border-radius: 20px;
}
.slider:before {
  position: absolute; content: "";
  height: 14px; width: 14px; left: 3px; bottom: 3px;
  background-color: white; transition: .3s; border-radius: 50%;
}
input:checked + .slider { background-color: var(--color-primary); }
input:checked + .slider:before { transform: translateX(14px); }
</style>

<div class="page-header" style="margin-bottom: 1rem;">
    <div class="header-content">
        <h1 class="page-title">Gestão de Atendimentos</h1>
        <p class="page-subtitle">Gestão dos atendimentos</p>
    </div>
</div>

<div class="kanban-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
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
    
    <!-- Colunas fixas status -->
    <?php
    $columns = [
        'Demandas' => ['icon' => '📥', 'title' => 'Demandas', 'border' => ''],
        'Aberto' => ['icon' => '📋', 'title' => 'Em Aberto', 'border' => 'border-top: 3px solid #3b82f6;'],
        'Em Atendimento' => ['icon' => '⚙️', 'title' => 'Em Atendimento', 'border' => 'border-top: 3px solid #f59e0b;'],
        'Finalizado' => ['icon' => '✅', 'title' => 'Finalizado', 'border' => 'border-top: 3px solid #10b981;']
    ];

    foreach ($columns as $status => $c): ?>
    <div class="kanban-column" data-status="<?= $status ?>" style="<?= $c['border'] ?>">
        <div class="kanban-column-header" style="flex-direction: column; align-items: stretch; gap: 0.5rem; padding-bottom: 0.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="display:flex;align-items:center;gap:0.5rem;"><?= $c['icon'] ?> <?= $c['title'] ?></span>
                <span class="kanban-count" id="count-<?= $status ?>">0</span>
            </div>
            <div style="position: relative;">
                <input type="text" class="column-filter" placeholder="Filtrar..." oninput="filterColumn('<?= $status ?>', this.value)" style="width: 100%; border-radius: 6px; border: 1px solid var(--border-color); padding: 4px 8px 4px 24px; font-size: 0.75rem; background: var(--bg-surface-2nd);">
                <span style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; opacity: 0.4;">🔍</span>
            </div>
        </div>
        <div class="kanban-cards" id="col-<?= $status ?>"></div>
    </div>
    <?php endforeach; ?>

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

<?php require_once __DIR__ . '/../../includes/modals/atendimento_detalhes.php'; ?>
<?php require_once __DIR__ . '/../../includes/student_schedule_modal.php'; ?>

<!-- Scripts -->
<script src="/assets/js/atendimento_shared.js?v=<?= time() ?>"></script>
<script src="/assets/js/atendimentos_kanban.js?v=<?= time() ?>"></script>
<script src="/assets/js/sancao_popover.js?v=1.0"></script>

<script>
    const currentUserId = <?= (int)$user['id'] ?>;
    const currentUserProfile = '<?= addslashes($user['profile']) ?>';
</script>
