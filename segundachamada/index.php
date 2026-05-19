<?php
/**
 * Vértice Acadêmico — Gestão de Segunda Chamada
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SegundaChamadaService.php';

requireLogin();
hasDbPermission('segundachamada.index');

$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = $inst['id'] ?? 0;

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/segundachamada/index.php'));
    exit;
}

require_once __DIR__ . '/../includes/modal.php';
require_once __DIR__ . '/../includes/toast.php';

$service = new \App\Services\SegundaChamadaService();
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$isCoordinator = ($user['profile'] === 'Coordenador' && !hasDbPermission('segundachamada.view_all', false));
$coordinatorUserId = $isCoordinator ? (int)$user['id'] : null;

$requests = $service->getAll($instId, $search, $coordinatorUserId, $statusFilter);
$disciplinas = $service->getDisciplinas($instId);

$pageTitle = "Segunda Chamada";
require_once __DIR__ . '/../includes/header.php';
renderModalStyles(); 
?>

<style>
.sc-table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
.sc-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.sc-table th {
    padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted);
    background: var(--bg-surface-2nd); border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.sc-table td { padding: .875rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.sc-table tr:hover td { background: var(--bg-hover); }

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

/* Autocomplete de Alunos */
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
    max-height: 250px;
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
.search-item img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
.search-item .no-photo {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--color-primary-light); color: var(--color-primary);
    display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700;
}
.search-item-info { display: flex; flex-direction: column; gap: 2px; }
.search-item-name { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
.search-item-meta { font-size: 0.75rem; color: var(--text-muted); }

/* Modal 80% Fixo (Padrão do Sistema) */
.modal-80 { 
    width: 80vw !important; height: 80vh !important; max-width: none !important; 
    display: flex !important; flex-direction: column !important; overflow: hidden !important;
}
.modal-80 .modal-body { flex: 1; overflow-y: auto; padding: 1.5rem 2rem; }

/* Status Badges */
.status-badge {
    display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px;
    font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
}
.status-badge.status-Pendente { background-color: rgba(245, 158, 11, 0.15); color: #d97706; }
.status-badge.status-Deferido { background-color: rgba(16, 185, 129, 0.15); color: #059669; }
.status-badge.status-Indeferido { background-color: rgba(239, 68, 68, 0.15); color: #dc2626; }

.section-title {
    font-size: 0.95rem; font-weight: 700; color: var(--text-secondary);
    border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-top: 1.5rem; margin-bottom: 1rem;
}

.file-preview-box {
    display: flex; align-items: center; justify-content: space-between;
    background: var(--bg-surface-2nd); border: 1px dashed var(--border-color);
    padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-top: 0.5rem;
}
</style>

<div class="page-header fade-in" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom: 1.5rem;">
    <div>
        <h1 class="page-title">📄 Segunda Chamada</h1>
        <p class="page-subtitle">Solicitações de Segunda Chamada de Atividades e Provas</p>
    </div>
    <div style="display:flex; gap:.75rem; align-items:center;">
        <select id="filter_status" class="form-control" style="width: auto; min-width: 160px; margin: 0; height: 38px; border-radius: var(--radius-md);" onchange="applyStatusFilter(this.value)">
            <option value="">Status: Todos</option>
            <option value="Pendente" <?= ($statusFilter === 'Pendente') ? 'selected' : '' ?>>Pendente</option>
            <option value="Deferido" <?= ($statusFilter === 'Deferido') ? 'selected' : '' ?>>Deferido</option>
            <option value="Indeferido" <?= ($statusFilter === 'Indeferido') ? 'selected' : '' ?>>Indeferido</option>
        </select>
        <?php if (hasDbPermission('segundachamada.manage', false)): ?>
        <button class="btn btn-primary" onclick="openScModal()">➕ Nova Solicitação</button>
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
                    <input type="text" name="search" class="form-control" placeholder="Buscar por aluno, matrícula, disciplina ou justificativa..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($search): ?>
            <a href="/segundachamada/index.php" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Listagem -->
<div class="card fade-in">
    <div class="sc-table-wrap">
        <table class="sc-table">
            <thead>
                <tr>
                    <th>Aluno</th>
                    <th>Disciplina</th>
                    <th>Data Atividade</th>
                    <th>Justificativa</th>
                    <th>Anexo</th>
                    <th>Status</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">
                        Nenhuma solicitação de segunda chamada registrada nesta instituição.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <td>
                            <div class="student-info">
                                <?php if (!empty($r['aluno_photo'])): ?>
                                    <img src="/<?= htmlspecialchars($r['aluno_photo']) ?>" class="student-avatar" alt="">
                                <?php else: ?>
                                    <div class="student-avatar"><?= strtoupper(substr($r['aluno_nome'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight:600;"><?= htmlspecialchars($r['aluno_nome']) ?></div>
                                    <div style="font-size:0.75rem; color:var(--text-muted);">Matrícula: <?= htmlspecialchars($r['aluno_matricula']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:500;"><?= htmlspecialchars($r['disciplina_nome']) ?></div>
                            <div style="font-size:0.75rem; color:var(--text-secondary);">Atividade: <strong style="color:var(--text-primary);"><?= htmlspecialchars($r['atividade_nome'] ?? 'Segunda Chamada') ?></strong></div>
                        </td>
                        <td style="color:var(--text-muted); font-size:0.8125rem;">
                            <?= date('d/m/Y', strtotime($r['data_atividade_perdida'])) ?>
                        </td>
                        <td>
                            <span style="font-size:0.8125rem; display:block; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($r['justificativa']) ?>">
                                <?= htmlspecialchars($r['justificativa']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($r['anexo_caminho'])): ?>
                                <?php 
                                    $isPdf = strtolower(pathinfo($r['anexo_nome'], PATHINFO_EXTENSION)) === 'pdf';
                                    $icon = $isPdf ? '📄' : '🖼️';
                                ?>
                                <button class="btn btn-ghost btn-sm" onclick="viewAnexo('/<?= htmlspecialchars($r['anexo_caminho']) ?>', '<?= htmlspecialchars(addslashes($r['anexo_nome'])) ?>', '<?= $isPdf ? 'pdf' : 'image' ?>')" title="Visualizar anexo">
                                    <?= $icon ?> Ver Anexo
                                </button>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-style:italic; font-size:0.8rem;">Sem anexo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $r['status'] ?>">
                                <?= $r['status'] ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex; justify-content:center; gap:.5rem;">
                                <?php if ($r['status'] === 'Pendente'): ?>
                                    <?php if (hasDbPermission('segundachamada.andamento', false)): ?>
                                    <button class="action-btn" onclick="openProgressModal(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['aluno_nome'])) ?>')" title="Dar Andamento">⚖️</button>
                                    <?php endif; ?>
                                    <button class="action-btn" onclick="resendEmail(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['aluno_nome'])) ?>')" title="Reenviar E-mail de Notificação">✉️</button>
                                    <button class="action-btn" onclick="editSc(<?= $r['id'] ?>)" title="Editar">✏️</button>
                                    <?php if (hasDbPermission('segundachamada.manage', false)): ?>
                                    <button class="action-btn danger" onclick="deleteSc(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['aluno_nome'])) ?>')" title="Excluir">🗑️</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if (hasDbPermission('segundachamada.andamento', false)): ?>
                                    <button class="action-btn" onclick="openReopenModal(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['aluno_nome'])) ?>', '<?= $r['status'] ?>', '<?= htmlspecialchars(addslashes($r['observacoes_status'] ?? '')) ?>')" title="Ver Parecer / Reabrir">⚖️</button>
                                    <?php endif; ?>
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

<!-- Modal Principal CRUD: Inclusão e Edição de Segunda Chamada -->
<div id="scModal" class="modal-backdrop">
    <div class="modal modal-80">
        <div class="modal-header">
            <h3 class="modal-title" id="scModalTitle">Nova Solicitação de Segunda Chamada</h3>
            <button class="modal-close" onclick="closeModal('scModal')">&times;</button>
        </div>
        
        <form id="scForm" onsubmit="saveSecondCall(event)" enctype="multipart/form-data" style="display:flex; flex-direction:column; height:100%; overflow:hidden;">
            <input type="hidden" name="id" id="field_id">
            <input type="hidden" name="aluno_id" id="field_aluno_id">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="modal-body">
                <!-- Busca de Aluno (Apenas para novos registros) -->
                <div class="form-group" id="aluno_search_group" style="position:relative; margin-bottom: 1.25rem;">
                    <label class="form-label">Buscar Aluno <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon">🔍</span>
                        <input type="text" id="aluno_search_input" class="form-control" placeholder="Nome ou Matrícula..." oninput="searchAlunos(this.value)" autocomplete="off">
                    </div>
                    <div id="searchAlunoResults" class="search-results" style="display:none;"></div>
                </div>

                <!-- Info do Aluno Selecionado -->
                <div id="selected_aluno_info" style="display:none; background:var(--bg-surface-2nd); padding:1rem; border-radius:8px; margin-bottom:1.25rem; border:1px solid var(--border-color);">
                    <div style="display:flex; justify-content:between; align-items:center; flex-wrap:wrap; gap:1rem;">
                        <div style="flex:1;">
                            <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Aluno Selecionado</div>
                            <div id="display_aluno_name" style="font-weight:600; font-size:1.05rem; color:var(--text-primary);"></div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" id="btn_change_aluno" onclick="clearAlunoSelection()" style="color:var(--color-danger);">Trocar Aluno</button>
                    </div>
                </div>

                <!-- Contatos do Aluno -->
                <div class="section-title">📞 Informações de Contato</div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Telefone do Aluno <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">📱</span>
                            <input type="tel" name="telefone_aluno" id="field_telefone_aluno" class="form-control mask-phone" required placeholder="Ex: (31) 99999-9999">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">E-mail do Aluno <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">✉️</span>
                            <input type="email" name="email_aluno" id="field_email_aluno" class="form-control" required placeholder="Ex: email@escola.com">
                        </div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-top: 0.5rem;">
                    <div class="form-group">
                        <label class="form-label">Nome do Responsável</label>
                        <input type="text" name="nome_responsavel" id="field_nome_responsavel" class="form-control" placeholder="Obrigatório para menores de idade">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telefone do Responsável</label>
                        <input type="tel" name="telefone_responsavel" id="field_telefone_responsavel" class="form-control mask-phone" placeholder="Ex: (31) 98888-8888">
                    </div>
                </div>

                <!-- Detalhes do Segunda Chamada -->
                <div class="section-title">📝 Detalhes da Atividade e Justificativa</div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Disciplina <span class="required">*</span></label>
                        <select name="disciplina_codigo" id="field_disciplina_codigo" class="form-control" required>
                            <option value="">Selecione o aluno primeiro...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data da Atividade Perdida <span class="required">*</span></label>
                        <input type="date" name="data_atividade_perdida" id="field_data_atividade_perdida" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-top:0.5rem;">
                    <label class="form-label">Nome/Descrição da Atividade Perdida <span class="required">*</span></label>
                    <input type="text" name="atividade_nome" id="field_atividade_nome" class="form-control" required placeholder="Ex: Prova Bimestral, Trabalho Prático, Teste Integrado, etc.">
                </div>

                <div class="form-group" style="margin-top:0.5rem;">
                    <label class="form-label">Justificativa da Solicitação <span class="required">*</span></label>
                    <textarea name="justificativa" id="field_justificativa" class="form-control" rows="3" required placeholder="Descreva os motivos da perda da atividade (doença, trabalho, força maior, etc.)"></textarea>
                </div>

                <!-- Anexo -->
                <div class="form-group">
                    <label class="form-label">Documento Comprobatório (Anexo) <span class="required" id="anexo_req" style="display:none;">*</span></label>
                    <input type="file" name="anexo" id="field_anexo" class="form-control" accept=".pdf,image/*">
                    <p style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">
                        Formatos aceitos: PDF (até 10MB) ou Imagens (JPG, PNG, WEBP que serão otimizadas para até 2MB).
                    </p>
                    <div id="current_anexo_info" style="display:none;" class="file-preview-box">
                        <div>
                            <span style="font-size: 1.1rem; margin-right: 0.5rem;">📎</span>
                            <strong id="display_anexo_name" style="font-size:0.85rem; color:var(--text-primary);"></strong>
                            <span id="display_anexo_meta" style="font-size:0.75rem; color:var(--text-muted); margin-left: 0.5rem;"></span>
                        </div>
                        <a id="btn_view_current_anexo" href="" target="_blank" class="btn btn-secondary btn-sm">Visualizar atual</a>
                    </div>
                </div>

                <!-- Controle Interno / Status -->
                <div class="section-title">⚙️ Controle de Status e Deferimento</div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Status da Solicitação <span class="required">*</span></label>
                        <select name="status_display" id="field_status" class="form-control" disabled required>
                            <option value="Pendente">Pendente</option>
                            <option value="Deferido">Deferido</option>
                            <option value="Indeferido">Indeferido</option>
                        </select>
                        <input type="hidden" name="status" id="field_status_hidden" value="Pendente">
                    </div>
                </div>
                <div class="form-group" style="margin-top:0.5rem;">
                    <label class="form-label">Observações sobre o Status</label>
                    <textarea name="observacoes_status" id="field_observacoes_status" class="form-control" rows="2" disabled placeholder="Status travado no momento do envio."></textarea>
                </div>
            </div>

            <div class="modal-footer" style="flex-shrink:0;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('scModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Solicitação</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Preview do Anexo -->
<div id="modalViewAnexo" class="modal-backdrop">
    <div class="modal modal-80">
        <div class="modal-header">
            <h3 class="modal-title" id="viewAnexoTitle">Visualizar Anexo</h3>
            <button class="modal-close" onclick="closeModal('modalViewAnexo')">&times;</button>
        </div>
        <div class="modal-body" id="anexoPreviewContainer" style="display:flex; align-items:center; justify-content:center; background:#1e1e2e; height:100%; min-height: 400px; padding: 0; overflow: hidden;">
            <!-- Renderizado via JS -->
        </div>
        <div class="modal-footer" style="flex-shrink:0;">
            <a id="downloadAnexoBtn" class="btn btn-primary" href="" download>Baixar Arquivo</a>
            <button class="btn btn-secondary" onclick="closeModal('modalViewAnexo')">Fechar</button>
        </div>
    </div>
</div>

<!-- Modal: Dar Andamento -->
<div id="progressModal" class="modal-backdrop">
    <div class="modal modal-md">
        <div class="modal-header">
            <h3 class="modal-title">⚖️ Dar Andamento à Solicitação</h3>
            <button class="modal-close" onclick="closeModal('progressModal')">&times;</button>
        </div>
        <form id="progressForm" onsubmit="saveProgress(event)">
            <input type="hidden" name="id" id="progress_id">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-body">
                <div style="background:var(--bg-surface-2nd); padding:0.875rem 1rem; border-radius:8px; margin-bottom:1.25rem; border:1px solid var(--border-color);">
                    <span style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; display:block;">Aluno</span>
                    <strong id="progress_aluno_name" style="font-size:1.05rem; color:var(--text-primary);"></strong>
                </div>

                <div class="form-group">
                    <label class="form-label">Encaminhamento da Solicitação <span class="required">*</span></label>
                    <select name="encaminhamento" id="field_encaminhamento" class="form-control" required onchange="toggleJustificativaRequired(this.value)">
                        <option value="">Selecione um encaminhamento...</option>
                        <option value="Deferido Ad Referendum">Deferido Ad Referendum</option>
                        <option value="Deferido pelo Colegiado">Deferido pelo Colegiado</option>
                        <option value="Indeferido">Indeferido</option>
                    </select>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Justificativa / Observações <span class="required" id="justificativa_andamento_req" style="display:none;">*</span></label>
                    <textarea name="justificativa" id="field_justificativa_andamento" class="form-control" rows="4" placeholder="Informe a justificativa ou observações do encaminhamento..."></textarea>
                </div>

                <!-- Chaves Liga/Desliga para E-mail -->
                <div class="section-title" style="margin-top:1.5rem;">✉️ Notificações por E-mail</div>
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.9rem; color:var(--text-primary);">
                        <input type="checkbox" name="notify_aluno" value="1" checked style="width:16px; height:16px;">
                        <span>Enviar e-mail de notificação para o <strong>Aluno</strong></span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.9rem; color:var(--text-primary);">
                        <input type="checkbox" name="notify_professor" value="1" checked style="width:16px; height:16px;">
                        <span>Enviar e-mail de notificação para o(s) <strong>Professor(es)</strong></span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.9rem; color:var(--text-primary);">
                        <input type="checkbox" name="notify_custom" id="field_notify_custom" value="1" checked style="width:16px; height:16px;" onchange="toggleCustomEmailInput(this.checked)">
                        <span>Enviar e-mail de notificação para outro endereço</span>
                    </label>
                    <div id="custom_email_group" class="form-group" style="margin-top:-0.25rem; padding-left:1.5rem; display: block;">
                        <input type="email" name="custom_email" id="field_custom_email" class="form-control" placeholder="Ex: coordenador.exemplo@escola.com" value="<?= htmlspecialchars($user['segundachamada_custom_email'] ?? '') ?>" style="max-width:360px;" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('progressModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Confirmar Encaminhamento</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Solicitação com Encaminhamento (Reabrir) -->
<div id="reopenModal" class="modal-backdrop">
    <div class="modal modal-md">
        <div class="modal-header">
            <h3 class="modal-title">⚖️ Solicitação com Encaminhamento</h3>
            <button class="modal-close" onclick="closeModal('reopenModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="background:var(--bg-surface-2nd); padding:0.875rem 1rem; border-radius:8px; margin-bottom:1.25rem; border:1px solid var(--border-color);">
                <span style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; display:block;">Aluno</span>
                <strong id="reopen_aluno_name" style="font-size:1.05rem; color:var(--text-primary);"></strong>
            </div>

            <div style="background-color:var(--bg-surface-2nd); border:1px solid var(--border-color); border-radius:8px; padding:1.25rem; margin-bottom:1.5rem;">
                <div style="margin-bottom:0.75rem;">
                    <span style="font-size:0.8rem; font-weight:bold; color:var(--text-muted); display:block;">Status Atual:</span>
                    <span id="reopen_status_badge" class="status-badge" style="margin-top:0.25rem;"></span>
                </div>
                <div>
                    <span style="font-size:0.8rem; font-weight:bold; color:var(--text-muted); display:block;">Parecer / Encaminhamento Registrado:</span>
                    <div id="reopen_observacoes" style="font-size:0.9rem; color:var(--text-primary); margin-top:0.25rem; white-space:pre-line; font-style:italic;"></div>
                </div>
            </div>

            <p style="font-size:0.925rem; color:var(--text-secondary); line-height:1.5; margin-bottom:1rem;">
                Esta solicitação já possui um encaminhamento final registrado e encontra-se encerrada.
            </p>
            <p style="font-size:0.925rem; font-weight:600; color:var(--color-primary); line-height:1.5;">
                Deseja reabrir esta solicitação para realizar um novo encaminhamento? Ao reabrir, o status voltará a ser <strong>Pendente</strong>.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('reopenModal')">Fechar</button>
            <?php if (hasDbPermission('segundachamada.andamento', false)): ?>
            <button type="button" class="btn btn-primary" id="btn_reopen_confirm" onclick="reopenSolicitation()">Reabrir Solicitação</button>
            <?php endif; ?>
        </div>
    </div>
</div>


<script>
let searchTimeout = null;

function openScModal() {
    document.getElementById('scForm').reset();
    document.getElementById('field_id').value = '';
    document.getElementById('field_aluno_id').value = '';
    document.getElementById('scModalTitle').innerText = 'Nova Solicitação de Segunda Chamada';
    
    document.getElementById('aluno_search_group').style.display = 'block';
    document.getElementById('selected_aluno_info').style.display = 'none';
    document.getElementById('current_anexo_info').style.display = 'none';
    document.getElementById('field_anexo').required = false;
    document.getElementById('anexo_req').style.display = 'none';
    
    document.getElementById('field_data_atividade_perdida').value = '<?= date('Y-m-d') ?>';
    document.getElementById('field_status').value = 'Pendente';
    document.getElementById('field_status_hidden').value = 'Pendente';
    document.getElementById('field_atividade_nome').value = '';
    
    openModal('scModal');
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
            const response = await fetch('/api/segundachamada.php?action=search_alunos&q=' + encodeURIComponent(query));
            const data = await response.json();
            
            if (data.success && data.alunos.length > 0) {
                let h = '';
                data.alunos.forEach(a => {
                    const photoHtml = a.photo 
                        ? `<img src="/${a.photo}" alt="${a.nome}">` 
                        : `<div class="no-photo">${a.nome.charAt(0)}</div>`;
                    
                    const serieTxt = a.serie || 'Sem Série';
                    const cursoTxt = a.curso || 'Sem Curso';
                    
                    h += `
                    <div class="search-item" onclick="selectAluno(${a.id}, '${a.nome.replace(/'/g, "\\'")}', '${a.matricula}', '${a.telefone || ''}', '${a.email || ''}', ${a.turma_id || 0})">
                        ${photoHtml}
                        <div class="search-item-info">
                            <span class="search-item-name">${a.nome}</span>
                            <span class="search-item-meta">Matrícula: ${a.matricula} &bull; Série: ${serieTxt} &bull; Curso: ${cursoTxt}</span>
                        </div>
                    </div>`;
                });
                resultsDiv.innerHTML = h;
                resultsDiv.style.display = 'block';
            } else {
                resultsDiv.innerHTML = '<div style="padding:.75rem; color:var(--text-muted); font-size:.875rem;">Nenhum aluno encontrado.</div>';
                resultsDiv.style.display = 'block';
            }
        } catch (e) {
            console.error(e);
        }
    }, 400);
}

function selectAluno(id, name, matricula, phone, email, turmaId) {
    document.getElementById('field_aluno_id').value = id;
    document.getElementById('display_aluno_name').innerText = name + ' (#' + matricula + ')';
    document.getElementById('aluno_search_group').style.display = 'none';
    document.getElementById('selected_aluno_info').style.display = 'block';
    document.getElementById('searchAlunoResults').style.display = 'none';
    
    if (phone) document.getElementById('field_telefone_aluno').value = phone;
    if (email) document.getElementById('field_email_aluno').value = email;
    
    // Carrega as disciplinas da turma, filtrando por dispensas
    loadStudentDisciplines(id, turmaId);
}

async function loadStudentDisciplines(alunoId, turmaId, selectedDisciplinaCodigo = '') {
    const selectDisc = document.getElementById('field_disciplina_codigo');
    selectDisc.innerHTML = '<option value="">Carregando disciplinas...</option>';
    selectDisc.disabled = true;
    
    try {
        const response = await fetch(`/api/segundachamada.php?action=get_student_disciplines&aluno_id=${alunoId}&turma_id=${turmaId}`);
        const res = await response.json();
        
        if (res.success) {
            let h = '<option value="">Selecione uma disciplina...</option>';
            if (res.disciplinas && res.disciplinas.length > 0) {
                res.disciplinas.forEach(d => {
                    h += `<option value="${d.codigo}">${d.descricao} (${d.codigo})</option>`;
                });
            } else {
                h = '<option value="">Nenhuma disciplina ativa para esta turma</option>';
            }
            selectDisc.innerHTML = h;
            
            if (selectedDisciplinaCodigo) {
                const exists = res.disciplinas ? res.disciplinas.some(d => d.codigo === selectedDisciplinaCodigo) : false;
                if (!exists) {
                    try {
                        const nameRes = await fetch(`/api/segundachamada.php?action=get_disciplina_name&codigo=${selectedDisciplinaCodigo}`);
                        const nameData = await nameRes.json();
                        const desc = nameData.success ? nameData.name : selectedDisciplinaCodigo;
                        selectDisc.innerHTML += `<option value="${selectedDisciplinaCodigo}">${desc} (${selectedDisciplinaCodigo})</option>`;
                    } catch(e) {
                        selectDisc.innerHTML += `<option value="${selectedDisciplinaCodigo}">${selectedDisciplinaCodigo}</option>`;
                    }
                }
                selectDisc.value = selectedDisciplinaCodigo;
            }
        } else {
            selectDisc.innerHTML = '<option value="">Erro ao carregar disciplinas</option>';
        }
    } catch(e) {
        console.error(e);
        selectDisc.innerHTML = '<option value="">Erro de conexão</option>';
    } finally {
        selectDisc.disabled = false;
    }
}

function clearAlunoSelection() {
    document.getElementById('field_aluno_id').value = '';
    document.getElementById('aluno_search_group').style.display = 'block';
    document.getElementById('selected_aluno_info').style.display = 'none';
    document.getElementById('aluno_search_input').value = '';
    document.getElementById('aluno_search_input').focus();
    
    document.getElementById('field_disciplina_codigo').innerHTML = '<option value="">Selecione o aluno primeiro...</option>';
}

async function editSc(id) {
    try {
        if (typeof showLoading === 'function') showLoading('Carregando dados...');
        const response = await fetch('/api/segundachamada.php?action=get&id=' + id);
        const res = await response.json();
        if (typeof hideLoading === 'function') hideLoading();
        
        if (res.success) {
            const data = res.data;
            document.getElementById('field_id').value = data.id;
            document.getElementById('field_aluno_id').value = data.aluno_id;
            document.getElementById('display_aluno_name').innerText = data.aluno_nome + ' (#' + data.aluno_matricula + ')';
            
            document.getElementById('field_telefone_aluno').value = data.telefone_aluno;
            document.getElementById('field_email_aluno').value = data.email_aluno;
            document.getElementById('field_nome_responsavel').value = data.nome_responsavel || '';
            document.getElementById('field_telefone_responsavel').value = data.telefone_responsavel || '';
            document.getElementById('field_data_atividade_perdida').value = data.data_atividade_perdida;
            document.getElementById('field_atividade_nome').value = data.atividade_nome || '';
            document.getElementById('field_justificativa').value = data.justificativa;
            document.getElementById('field_status').value = data.status;
            document.getElementById('field_status_hidden').value = data.status;
            document.getElementById('field_observacoes_status').value = data.observacoes_status || '';
            
            // Carrega dinamicamente as disciplinas da turma do aluno, selecionando a salva e filtrando dispensadas
            loadStudentDisciplines(data.aluno_id, data.turma_id || 0, data.disciplina_codigo);
            
            document.getElementById('scModalTitle').innerText = 'Editar Solicitação de Segunda Chamada';
            document.getElementById('aluno_search_group').style.display = 'none';
            document.getElementById('selected_aluno_info').style.display = 'block';
            document.getElementById('btn_change_aluno').style.display = 'none';
            
            // Tratamento do Anexo na Edição
            const currentAnexoBox = document.getElementById('current_anexo_info');
            document.getElementById('field_anexo').required = false;
            document.getElementById('anexo_req').style.display = 'none';
            
            if (data.anexo_caminho) {
                document.getElementById('display_anexo_name').innerText = data.anexo_nome;
                const sizeKb = (data.anexo_tamanho / 1024).toFixed(1);
                document.getElementById('display_anexo_meta').innerText = `(${sizeKb} KB)`;
                
                const isPdf = data.anexo_nome.toLowerCase().endsWith('.pdf');
                document.getElementById('btn_view_current_anexo').onclick = (e) => {
                    e.preventDefault();
                    viewAnexo('/' + data.anexo_caminho, data.anexo_nome, isPdf ? 'pdf' : 'image');
                };
                currentAnexoBox.style.display = 'flex';
            } else {
                currentAnexoBox.style.display = 'none';
            }
            
            openModal('scModal');
        } else {
            Toast.error(res.error || 'Erro ao carregar dados.');
        }
    } catch (e) {
        if (typeof hideLoading === 'function') hideLoading();
        Toast.error('Erro ao conectar ao servidor.');
    }
}

async function saveSecondCall(e) {
    e.preventDefault();
    
    const alunoId = document.getElementById('field_aluno_id').value;
    if (!alunoId) {
        Toast.error('Por favor, selecione um aluno válido.');
        return;
    }
    
    const emailAluno = document.getElementById('field_email_aluno').value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(emailAluno)) {
        Toast.error('Por favor, informe um e-mail de aluno válido.');
        return;
    }

    const atividadeNome = document.getElementById('field_atividade_nome').value.trim();
    if (!atividadeNome) {
        Toast.error('Por favor, informe o nome da atividade perdida.');
        return;
    }
    
    const formData = new FormData(document.getElementById('scForm'));
    formData.append('action', 'save');
    formData.append('aluno_id', alunoId);
    
    try {
        if (typeof showLoading === 'function') showLoading('Processando e salvando solicitação...');
        const response = await fetch('/api/segundachamada.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const res = await response.json();
        if (typeof hideLoading === 'function') hideLoading();
        
        if (res.success) {
            Toast.success(res.message);
            closeModal('scModal');
            setTimeout(() => location.reload(), 800);
        } else {
            Toast.error(res.error || 'Falha ao salvar a solicitação.');
        }
    } catch (err) {
        if (typeof hideLoading === 'function') hideLoading();
        Toast.error('Erro de conexão ao salvar.');
    }
}

async function deleteSc(id, studentName) {
    if (!confirm(`Tem certeza de que deseja excluir permanentemente a solicitação de segunda chamada do aluno "${studentName}"?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    
    try {
        if (typeof showLoading === 'function') showLoading('Excluindo solicitação...');
        const response = await fetch('/api/segundachamada.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const res = await response.json();
        if (typeof hideLoading === 'function') hideLoading();
        
        if (res.success) {
            Toast.success(res.message);
            setTimeout(() => location.reload(), 800);
        } else {
            Toast.error(res.error || 'Erro ao excluir solicitação.');
        }
    } catch (err) {
        if (typeof hideLoading === 'function') hideLoading();
        Toast.error('Erro de conexão ao excluir.');
    }
}

function viewAnexo(url, name, type) {
    const container = document.getElementById('anexoPreviewContainer');
    document.getElementById('viewAnexoTitle').innerText = name;
    document.getElementById('downloadAnexoBtn').href = url;
    container.innerHTML = '';
    
    if (type === 'pdf') {
        container.innerHTML = `<iframe src="${url}#toolbar=0" style="width:100%; height:100%; border:none; background:#ffffff;"></iframe>`;
    } else {
        container.innerHTML = `<img src="${url}" style="max-width:95%; max-height:95%; object-fit:contain; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.5);">`;
    }
    openModal('modalViewAnexo');
}

async function resendEmail(id, studentName) {
    if (!confirm(`Deseja realmente reenviar o e-mail de notificação para o professor sobre a solicitação de segunda chamada do aluno "${studentName}"?`)) {
        return;
    }
    
    try {
        if (typeof showLoading === 'function') showLoading('Reenviando e-mail...');
        
        const formData = new FormData();
        formData.append('action', 'resend_email');
        formData.append('id', id);
        formData.append('csrf_token', '<?= csrf_token() ?>');
        
        const response = await fetch('/api/segundachamada.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const res = await response.json();
        if (typeof hideLoading === 'function') hideLoading();
        
        if (res.success) {
            Toast.success(res.message);
        } else {
            Toast.error(res.error || 'Falha ao reenviar o e-mail.');
        }
    } catch (e) {
        if (typeof hideLoading === 'function') hideLoading();
        Toast.error('Erro de conexão ao reenviar o e-mail.');
    }
}

function openProgressModal(id, studentName) {
    document.getElementById('progressForm').reset();
    document.getElementById('progress_id').value = id;
    document.getElementById('progress_aluno_name').innerText = studentName;
    toggleJustificativaRequired('');
    
    const checkbox = document.getElementById('field_notify_custom');
    if (checkbox) {
        checkbox.checked = true;
        toggleCustomEmailInput(true);
    }
    
    openModal('progressModal');
}

function toggleCustomEmailInput(checked) {
    const group = document.getElementById('custom_email_group');
    const input = document.getElementById('field_custom_email');
    if (group && input) {
        if (checked) {
            group.style.display = 'block';
            input.required = true;
        } else {
            group.style.display = 'none';
            input.required = false;
        }
    }
}

function toggleJustificativaRequired(value) {
    const textarea = document.getElementById('field_justificativa_andamento');
    const reqAsterisk = document.getElementById('justificativa_andamento_req');
    if (value === 'Indeferido') {
        textarea.required = true;
        if (reqAsterisk) reqAsterisk.style.display = 'inline';
    } else {
        textarea.required = false;
        if (reqAsterisk) reqAsterisk.style.display = 'none';
    }
}

async function saveProgress(e) {
    e.preventDefault();
    const id = document.getElementById('progress_id').value;
    const encaminhamento = document.getElementById('field_encaminhamento').value;
    const justificativa = document.getElementById('field_justificativa_andamento').value;
    
    if (encaminhamento === 'Indeferido' && !justificativa.trim()) {
        Toast.error('A justificativa é obrigatória em caso de indeferimento.');
        return;
    }
    
    const formData = new FormData(document.getElementById('progressForm'));
    formData.append('action', 'progress');
    
    try {
        if (typeof showLoading === 'function') showLoading('Salvando encaminhamento e enviando e-mails...');
        const response = await fetch('/api/segundachamada.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const res = await response.json();
        if (typeof hideLoading === 'function') hideLoading();
        
        if (res.success) {
            Toast.success(res.message);
            closeModal('progressModal');
            setTimeout(() => location.reload(), 800);
        } else {
            Toast.error(res.error || 'Falha ao salvar o encaminhamento.');
        }
    } catch (err) {
        if (typeof hideLoading === 'function') hideLoading();
        Toast.error('Erro de conexão ao salvar encaminhamento.');
    }
}

let currentReopenId = null;

function openReopenModal(id, studentName, status, observacoes) {
    currentReopenId = id;
    document.getElementById('reopen_aluno_name').innerText = studentName;
    document.getElementById('reopen_observacoes').innerText = observacoes ? observacoes : 'Nenhum detalhe registrado.';
    
    const badge = document.getElementById('reopen_status_badge');
    badge.className = 'status-badge status-' + status;
    badge.innerText = status;
    
    openModal('reopenModal');
}

async function reopenSolicitation() {
    if (!currentReopenId) return;
    
    if (!confirm('Tem certeza que deseja reabrir esta solicitação? O status dela voltará a ser "Pendente" e o encaminhamento anterior será limpo.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reopen');
    formData.append('id', currentReopenId);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    
    try {
        if (typeof showLoading === 'function') showLoading('Reabrindo solicitação...');
        const response = await fetch('/api/segundachamada.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const res = await response.json();
        if (typeof hideLoading === 'function') hideLoading();
        
        if (res.success) {
            Toast.success(res.message);
            closeModal('reopenModal');
            setTimeout(() => location.reload(), 800);
        } else {
            Toast.error(res.error || 'Falha ao reabrir a solicitação.');
        }
    } catch (err) {
        if (typeof hideLoading === 'function') hideLoading();
        Toast.error('Erro de conexão ao reabrir solicitação.');
    }
}

function applyStatusFilter(status) {
    const url = new URL(window.location.href);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    window.location.href = url.toString();
}
</script>
<?php renderModalScripts(); ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
