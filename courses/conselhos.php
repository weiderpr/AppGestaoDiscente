<?php
/**
 * Vértice Acadêmico  — Conselhos de Classe
 */
require_once __DIR__ . '/../includes/auth.php';
hasDbPermission('conselhos.index');

$user = getCurrentUser();

$db      = getDB();
$inst    = getCurrentInstitution();
$instId  = $inst['id'];

// Se não há instituição selecionada, solicita seleção
if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/courses/conselhos.php'));
    exit;
}

$success = '';
$error   = '';
$action  = $_POST['action'] ?? '';

// ---- CRIAR / EDITAR ----
if ($action === 'save') {
    $id            = (int)($_POST['id'] ?? 0);
    $course_id     = (int)($_POST['course_id'] ?? 0);
    $turma_id      = (int)($_POST['turma_id'] ?? 0);
    $descricao     = trim($_POST['descricao'] ?? '');
    $data_hora     = trim($_POST['data_hora'] ?? '');
    $local_reuniao = trim($_POST['local_reuniao'] ?? '');
    $avaliacao_id  = (int)($_POST['avaliacao_id'] ?? 0);
    $etapas        = $_POST['etapas'] ?? [];

    if (empty($descricao) || empty($data_hora) || $course_id <= 0 || $turma_id <= 0) {
        $error = 'Curso, Turma, Descrição e Data/Hora são obrigatórios.';
    } else {
        if ($id > 0) {
            // Update
            $st = $db->prepare('UPDATE conselhos_classe SET course_id=?, turma_id=?, descricao=?, data_hora=?, local_reuniao=?, avaliacao_id=? WHERE id=? AND institution_id=?');
            $st->execute([$course_id, $turma_id, $descricao, $data_hora, $local_reuniao ?: null, $avaliacao_id > 0 ? $avaliacao_id : null, $id, $instId]);
            
            // Remove etapas antigas e insere novas
            $db->prepare('DELETE FROM conselhos_etapas WHERE conselho_id = ?')->execute([$id]);
            foreach ($etapas as $etapa_id) {
                $db->prepare('INSERT INTO conselhos_etapas (conselho_id, etapa_id) VALUES (?, ?)')->execute([$id, (int)$etapa_id]);
            }
            
            $success = 'Conselho de Classe atualizado com sucesso!';
        } else {
            // Insert
            $st = $db->prepare('INSERT INTO conselhos_classe (institution_id, course_id, turma_id, descricao, data_hora, local_reuniao, avaliacao_id) VALUES (?,?,?,?,?,?,?)');
            $st->execute([$instId, $course_id, $turma_id, $descricao, $data_hora, $local_reuniao ?: null, $avaliacao_id > 0 ? $avaliacao_id : null]);
            $newId = $db->lastInsertId();
            
            // Insere etapas
            foreach ($etapas as $etapa_id) {
                $db->prepare('INSERT INTO conselhos_etapas (conselho_id, etapa_id) VALUES (?, ?)')->execute([$newId, (int)$etapa_id]);
            }
            
            $success = 'Conselho de Classe agendado com sucesso!';
        }
    }
}

// ---- TOGGLE STATUS ----
if ($action === 'toggle' && !empty($_POST['id'])) {
    $id = (int)$_POST['id'];
    $db->prepare('UPDATE conselhos_classe SET is_active = !is_active WHERE id=? AND institution_id=?')
       ->execute([$id, $instId]);
    $success = 'Status do conselho atualizado.';
}

// ---- EXCLUIR ----
if ($action === 'delete' && !empty($_POST['id'])) {
    $id = (int)$_POST['id'];
    $db->prepare('DELETE FROM conselhos_classe WHERE id=? AND institution_id=?')
       ->execute([$id, $instId]);
    $success = 'Conselho de Classe removido permanentemente.';
}

// ---- DADOS PARA O MODAL (Cursos com turmas ativas) ----
// Apenas cursos que possuem turmas ativas
if ($user['profile'] === 'Administrador') {
    $stCourses = $db->prepare("
        SELECT DISTINCT c.id, c.name 
        FROM courses c
        JOIN turmas t ON t.course_id = c.id
        WHERE c.institution_id = ? AND t.is_active = 1
        ORDER BY c.name
    ");
    $stCourses->execute([$instId]);
} else {
    // Coordenador: Apenas cursos que ele coordena e possuem turmas ativas
    $stCourses = $db->prepare("
        SELECT DISTINCT c.id, c.name 
        FROM courses c
        JOIN turmas t ON t.course_id = c.id
        WHERE c.institution_id = ? 
        AND c.id IN (SELECT course_id FROM course_coordinators WHERE user_id = ?)
        AND t.is_active = 1
        ORDER BY c.name
    ");
    $stCourses->execute([$instId, $user['id']]);
}
$availableCourses = $stCourses->fetchAll();

// ---- AVALIAÇÕES DISPONÍVEIS ----
$stAv = $db->prepare("SELECT id, nome FROM avaliacoes WHERE deleted_at IS NULL ORDER BY nome ASC");
$stAv->execute();
$availableAvaliacoes = $stAv->fetchAll();

// ---- LISTAR ----
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'data_hora';
$order = $_GET['order'] ?? 'DESC';

$allowedSorts = ['data_hora', 'local_reuniao', 'turma_name'];

$sortMap = [
    'data_hora' => 'cc.data_hora',
    'local_reuniao' => 'cc.local_reuniao',
    'turma_name' => 't.description'
];

$sql    = "SELECT cc.*, t.description as turma_name, c.name as course_name, c.id as course_id,
           GROUP_CONCAT(e.id ORDER BY e.id SEPARATOR ',') as etapas_ids,
           GROUP_CONCAT(e.description ORDER BY e.id SEPARATOR '||') as etapas_names,
           (SELECT AVG(rp.nota) FROM respostas_perguntas rp JOIN respostas_avaliacao ra ON rp.resposta_id = ra.id WHERE ra.conselho_id = cc.id) as media_avaliacao,
           (SELECT COUNT(ra.id) FROM respostas_avaliacao ra WHERE ra.conselho_id = cc.id) as total_respostas
           FROM conselhos_classe cc
           JOIN turmas t ON cc.turma_id = t.id
           JOIN courses c ON cc.course_id = c.id
           LEFT JOIN conselhos_etapas ce ON cc.id = ce.conselho_id
           LEFT JOIN etapas e ON ce.etapa_id = e.id
           WHERE cc.institution_id = ?";

$params = [$instId];

// Coordenador só vê os conselhos dos seus cursos
if ($user['profile'] === 'Coordenador') {
    $sql .= " AND c.id IN (SELECT course_id FROM course_coordinators WHERE user_id = ?)";
    $params[] = $user['id'];
}

$sql .= " GROUP BY cc.id";

if ($search) {
    $sql .= " AND (cc.descricao LIKE ? OR t.description LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY {$sortMap[$sort]} $order";
$st = $db->prepare($sql);
$st->execute($params);
$conselhos = $st->fetchAll();

$pageTitle = 'Conselhos de Classe';

/**
 * Renderiza estrelas baseadas na nota (0-5)
 */
function renderRatingStars($rating, $count) {
    if (!$count) return '<span style="color:var(--text-muted); font-size:.75rem;">Sem avaliações</span>';
    
    $stars = '';
    $rating = round($rating, 1);
    
    // Design Premium: Estrelas preenchidas e vazias
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= round($rating)) {
            $stars .= '<span style="color:#fbbf24;">★</span>';
        } else {
            $stars .= '<span style="color:var(--star-off, #334155); opacity:0.3;">★</span>';
        }
    }
    
    return "
        <div style='display:flex; flex-direction:column; gap:2px;' title='Média: $rating / 5 ($count respostas)'>
            <div style='display:flex; gap:1px; font-size:1.1rem; line-height:1;'>$stars</div>
            <div style='font-size:0.65rem; color:var(--text-muted); font-weight:600; text-transform:uppercase;'>$count RESPOSTAS</div>
        </div>
    ";
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.conselhos-table-wrap { overflow-x:auto; border-radius:var(--radius-lg); }
.conselhos-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.conselhos-table th {
    padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:600;
    text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted);
    background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color);
    white-space:nowrap;
}
.conselhos-table th.sortable { cursor:pointer; user-select:none; }
.conselhos-table th.sortable:hover { color:var(--text-primary); }
.conselhos-table th .sort-icon { margin-left:.375rem; opacity:.5; font-size:.75rem; }
.conselhos-table th.sorted .sort-icon { opacity:1; color:var(--color-primary); }
.conselhos-table td { padding:.875rem 1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; }
.conselhos-table tr:last-child td { border-bottom:none; }
.conselhos-table tr:hover td { background:var(--bg-hover); }

.action-btn {
    display:inline-flex; align-items:center; justify-content:center;
    width:32px; height:32px; border-radius:var(--radius-md);
    border:1px solid var(--border-color); background:var(--bg-surface);
    color:var(--text-muted); cursor:pointer; font-size:.875rem;
    transition:all var(--transition-fast); text-decoration:none;
}
.action-btn:hover { background:var(--bg-hover); color:var(--text-primary); }
.action-btn.danger:hover { background:#fef2f2; color:var(--color-danger); border-color:var(--color-danger); }
[data-theme="dark"] .action-btn.danger:hover { background:#450a0a; }

/* Modal styles are now handled by core CSS */
</style>

<!-- Page Header -->
<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 class="page-title">🏠 Conselhos de Classe</h1>
        <p class="page-subtitle">
            Gestão de reuniões e deliberações pedagógicas por turma.
        </p>
    </div>
    <button class="btn btn-primary" onclick="openModal()">➕ Novo Conselho</button>
</div>

<?php if ($success): ?>
<div class="alert alert-success fade-in" style="margin-bottom:1.5rem;">
    ✅ <?= htmlspecialchars($success) ?>
    <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:1.1rem;">✕</button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger fade-in" style="margin-bottom:1.5rem;">
    ⚠️ <?= htmlspecialchars($error) ?>
    <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:1.1rem;">✕</button>
</div>
<?php endif; ?>

<!-- Filtro -->
<div class="card fade-in" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.5rem;">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:220px;margin:0;">
                <div class="input-group">
                    <span class="input-icon">🔍</span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Buscar por descrição, turma ou curso..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <?php if ($search): ?>
            <a href="/courses/conselhos.php" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabela -->
<div class="card fade-in">
    <div class="card-header">
        <span class="card-title">Histórico de Conselhos</span>
        <span style="font-size:.875rem;color:var(--text-muted);"><?= count($conselhos) ?> registro(s)</span>
    </div>
    <div class="conselhos-table-wrap">
        <table class="conselhos-table">
            <thead>
                <tr>
                    <th>#</th>
                    <?php 
                    $cols = [
                        'turma_name' => 'Turma',
                        'data_hora' => 'Data e Hora',
                        'local_reuniao' => 'Local'
                    ];
                    foreach ($cols as $col => $label):
                        $newOrder = ($sort === $col && $order === 'ASC') ? 'DESC' : 'ASC';
                        $queryParams = 'sort=' . $col . '&order=' . $newOrder;
                        if ($search) $queryParams .= '&search=' . urlencode($search);
                        $icon = '';
                        if ($sort === $col) {
                            $icon = $order === 'ASC' ? '▲' : '▼';
                        }
                    ?>
                    <th class="sortable <?= $sort === $col ? 'sorted' : '' ?>" 
                        onclick="window.location='?<?= $queryParams ?>'">
                        <?= $label ?> <span class="sort-icon"><?= $icon ?></span>
                    </th>
                    <?php endforeach; ?>
                    <th>Etapas</th>
                    <th>Avaliação</th>
                    <th>Status</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($conselhos)): ?>
                <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
                    Nenhum conselho de classe encontrado.
                </td></tr>
                <?php endif; ?>
                <?php foreach ($conselhos as $c): ?>
                <tr style="<?= !$c['is_active'] ? 'opacity:.55' : '' ?>">
                    <td style="color:var(--text-muted);font-size:.8125rem;"><?= $c['id'] ?></td>
                    <td>
                        <div style="font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($c['turma_name']) ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($c['course_name']) ?></div>
                    </td>
                    <td style="color:var(--text-secondary);white-space:nowrap;">
                        📅 <?= date('d/m/Y H:i', strtotime($c['data_hora'])) ?>
                    </td>
                    <td style="color:var(--text-secondary);">
                        <?= $c['local_reuniao'] ? '📍 ' . htmlspecialchars($c['local_reuniao']) : '—' ?>
                    </td>
                    <td style="color:var(--text-secondary);font-size:.8125rem;">
                        <?= !empty($c['etapas_names']) ? htmlspecialchars(str_replace('||', ', ', $c['etapas_names'])) : '—' ?>
                    </td>
                    <td>
                        <?= renderRatingStars($c['media_avaliacao'], $c['total_respostas']) ?>
                    </td>
                    <td>
                        <span style="font-size:.8125rem;font-weight:600;color:<?= $c['is_active'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                            <?= $c['is_active'] ? '● Ativo' : '○ Concluído' ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;justify-content:center;gap:.375rem;">
                            <a href="/courses/conselho_acao.php?id=<?= $c['id'] ?>" class="action-btn" title="Acessar Conselho">📋</a>
                            <?php if ($c['is_active']): ?>
                            <button type="button" class="action-btn" title="Editar"
                                    onclick='openModal(<?= json_encode($c) ?>)'>✏️</button>
                            
                            <button type="button" class="action-btn" title="Desativar"
                                    onclick="confirmToggleStatus(<?= $c['id'] ?>, true)">⏸</button>

                            <button type="button" class="action-btn danger" title="Excluir"
                                    onclick="confirmDeleteConselho(<?= $c['id'] ?>)">🗑</button>
                            <?php else: ?>
                            <button type="button" class="action-btn" title="Editar" disabled
                                    style="opacity:0.4;cursor:not-allowed;">✏️</button>
                            
                            <button type="button" class="action-btn" title="Ativar"
                                    onclick="confirmToggleStatus(<?= $c['id'] ?>, false)">▶</button>

                            <button type="button" class="action-btn danger" title="Excluir" disabled
                                    style="opacity:0.4;cursor:not-allowed;">🗑</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Forms Ocultos para Ações -->
<form id="formAction" method="POST" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="actionField">
    <input type="hidden" name="id" id="idField">
</form>

<!-- Modal: Novo/Editar Conselho -->
<div id="conselhoModal" class="modal-wrapper" role="dialog" aria-modal="true">
    <div class="modal-overlay" onclick="closeModal()">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title" id="modalTitle">🏠 Novo Conselho de Classe</span>
                    <button type="button" class="modal-close" onclick="closeModal()">✕</button>
                </div>
            <form method="POST" onsubmit="return prepareForm()">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="conselho_id" value="0">
                <div class="modal-body">
                    
                    <!-- Seleção de Curso e Turma -->
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Curso <span class="required">*</span></label>
                            <select name="course_id" id="conselho_course_id" class="form-control" required onchange="loadTurmas()">
                                <option value="">Selecione o curso...</option>
                                <?php foreach ($availableCourses as $ac): ?>
                                    <option value="<?= $ac['id'] ?>"><?= htmlspecialchars($ac['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Turma <span class="required">*</span></label>
                            <select name="turma_id" id="conselho_turma_id" class="form-control" required disabled onchange="loadEtapas()">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Etapas Consideredas</label>
                        <select name="etapas[]" id="conselho_etapas" class="form-control" multiple disabled style="min-height:100px;">
                            <option value="">Selecione uma turma...</option>
                        </select>
                        <small style="color:var(--text-muted);font-size:.75rem;">Segure Ctrl (ou Cmd) para selecionar múltiplas etapas</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descrição do Conselho <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon">📝</span>
                            <input type="text" name="descricao" id="conselho_descricao" class="form-control"
                                placeholder="Ex: Conselho Final - 3º Ano A" required>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Data e Hora <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon">📅</span>
                                <input type="text" name="data_hora" id="conselho_data_hora" class="form-control" 
                                    placeholder="dd/mm/aaaa hh:mm" autocomplete="off" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Local da Reunião</label>
                            <div class="input-group">
                                <span class="input-icon">📍</span>
                                <input type="text" name="local_reuniao" id="conselho_local" class="form-control"
                                    placeholder="Ex: Sala II">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pesquisa / Avaliação Associada</label>
                        <div class="input-group">
                            <span class="input-icon">🔍</span>
                            <select name="avaliacao_id" id="conselho_avaliacao_id" class="form-control">
                                <option value="0">Nenhuma pesquisa associada</option>
                                <?php foreach ($availableAvaliacoes as $av): ?>
                                    <option value="<?= $av['id'] ?>"><?= htmlspecialchars($av['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small style="color:var(--text-muted);font-size:.75rem;">Selecione uma pesquisa para que os participantes respondam durante o conselho.</small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">💾 Salvar Conselho</button>
                </div>
            </form>
        </div> <!-- modal-content -->
        </div> <!-- modal-dialog -->
    </div> <!-- modal-overlay -->
</div> <!-- modal-wrapper -->

<script>
function prepareForm() {
    const dateField = document.getElementById('conselho_data_hora');
    let value = dateField.value.trim();
    
    if (value && value.includes('/')) {
        const parts = value.split(' ');
        const dateParts = parts[0].split('/');
        if (dateParts.length === 3) {
            const timeParts = parts[1] ? parts[1].split(':') : ['00', '00'];
            const dt = new Date(dateParts[2], dateParts[1] - 1, dateParts[0], timeParts[0], timeParts[1]);
            if (dt && !isNaN(dt)) {
                dateField.value = dt.getFullYear() + '-' + 
                    String(dt.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(dt.getDate()).padStart(2, '0') + ' ' + 
                    String(dt.getHours()).padStart(2, '0') + ':' + 
                    String(dt.getMinutes()).padStart(2, '0');
            }
        }
    }
    
    dateField.removeAttribute('required');
    return true;
}

function loadTurmas() {
    const courseId = document.getElementById('conselho_course_id').value;
    const turmaSelect = document.getElementById('conselho_turma_id');
    const etapasSelect = document.getElementById('conselho_etapas');
    
    if (!courseId) {
        turmaSelect.innerHTML = '<option value="">Selecione...</option>';
        turmaSelect.disabled = true;
        etapasSelect.innerHTML = '<option value="">Selecione uma turma...</option>';
        etapasSelect.disabled = true;
        return;
    }
    
    turmaSelect.innerHTML = '<option value="">Carregando...</option>';
    turmaSelect.disabled = true;
    
    if (typeof Loading !== 'undefined') Loading.show();
    
    fetch('/courses/conselhos_turmas_ajax.php?course_id=' + courseId)
        .then(res => res.json())
        .then(turmas => {
            let html = '<option value="">Selecione...</option>';
            turmas.forEach(t => {
                html += `<option value="${t.id}">${t.description}</option>`;
            });
            turmaSelect.innerHTML = html;
            turmaSelect.disabled = false;
        })
        .catch(() => {
            turmaSelect.innerHTML = '<option value="">Erro ao carregar</option>';
        })
        .finally(() => {
            if (typeof Loading !== 'undefined') Loading.hide();
        });
}

function loadEtapas() {
    const turmaId = document.getElementById('conselho_turma_id').value;
    const etapasSelect = document.getElementById('conselho_etapas');
    
    if (!turmaId) {
        etapasSelect.innerHTML = '<option value="">Selecione uma turma...</option>';
        etapasSelect.disabled = true;
        return;
    }
    
    etapasSelect.innerHTML = '<option value="">Carregando...</option>';
    etapasSelect.disabled = true;
    
    if (typeof Loading !== 'undefined') Loading.show();
    
    fetch('/courses/conselhos_etapas_ajax.php?turma_id=' + turmaId)
        .then(res => res.json())
        .then(etapas => {
            let html = '';
            etapas.forEach(e => {
                html += `<option value="${e.id}">${e.description}</option>`;
            });
            if (!html) {
                html = '<option value="">Nenhuma etapa encontrada</option>';
            }
            etapasSelect.innerHTML = html;
            etapasSelect.disabled = false;
        })
        .catch(() => {
            etapasSelect.innerHTML = '<option value="">Erro ao carregar</option>';
        })
        .finally(() => {
            if (typeof Loading !== 'undefined') Loading.hide();
        });
}

function openModal(data = null) {
    const modal = document.getElementById('conselhoModal');
    const title = document.getElementById('modalTitle');
    const idField = document.getElementById('conselho_id');
    const courseField = document.getElementById('conselho_course_id');
    const turmaField = document.getElementById('conselho_turma_id');
    const etapasField = document.getElementById('conselho_etapas');
    const descField = document.getElementById('conselho_descricao');
    const dateField = document.getElementById('conselho_data_hora');
    const localField = document.getElementById('conselho_local');
    const avaliacaoField = document.getElementById('conselho_avaliacao_id');

    courseField.value = '';
    turmaField.innerHTML = '<option value="">Selecione...</option>';
    turmaField.disabled = true;
    etapasField.innerHTML = '<option value="">Selecione uma turma...</option>';
    etapasField.disabled = true;

    if (data) {
        title.innerText = '🏠 Editar Conselho de Classe';
        idField.value = data.id;
        descField.value = data.descricao;
        
        // Formata data para display
        if (data.data_hora) {
            const dt = new Date(data.data_hora.replace(' ', 'T'));
            if (!isNaN(dt)) {
                dateField.value = String(dt.getDate()).padStart(2, '0') + '/' + 
                    String(dt.getMonth() + 1).padStart(2, '0') + '/' + 
                    dt.getFullYear() + ' ' + 
                    String(dt.getHours()).padStart(2, '0') + ':' + 
                    String(dt.getMinutes()).padStart(2, '0');
            }
        }
        localField.value = data.local_reuniao || '';
        avaliacaoField.value = data.avaliacao_id || '0';
        
        // Impede que o navegador auto-preencha com data
        dateField.readOnly = true;
        
        setTimeout(() => { dateField.readOnly = false; }, 100);
        
        // Carrega turmas do curso primeiro
        fetch('/courses/conselhos_turmas_ajax.php?course_id=' + data.course_id)
            .then(res => res.json())
            .then(turmas => {
                let html = '<option value="">Selecione...</option>';
                turmas.forEach(t => {
                    html += `<option value="${t.id}">${t.description}</option>`;
                });
                turmaField.innerHTML = html;
                turmaField.disabled = false;
                turmaField.value = data.turma_id;
                courseField.value = data.course_id;
                
                // Carrega as etapas da turma selecionada
                return fetch('/courses/conselhos_etapas_ajax.php?turma_id=' + data.turma_id);
            })
            .then(res => res.json())
            .then(etapas => {
                let html = '';
                etapas.forEach(e => {
                    const selected = data.etapas_ids && data.etapas_ids.split(',').includes(String(e.id)) ? ' selected' : '';
                    html += `<option value="${e.id}"${selected}>${e.description}</option>`;
                });
                if (!html) {
                    html = '<option value="">Nenhuma etapa encontrada</option>';
                }
                etapasField.innerHTML = html;
                etapasField.disabled = false;
            })
            .catch(() => {
                turmaField.innerHTML = '<option value="">Erro ao carregar</option>';
            });
    } else {
        title.innerText = '🏠 Novo Conselho de Classe';
        idField.value = '0';
        descField.value = '';
        dateField.value = '';
        localField.value = '';
        avaliacaoField.value = '0';
        turmaField.value = '';
    }

    modal.classList.add('modal-show');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('conselhoModal').classList.remove('modal-show');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { 
    if(e.key === 'Escape') closeModal(); 
});

function confirmToggleStatus(id, active) {
    const action = active ? 'desativar' : 'ativar';
    Modal.confirm({
        title: '⏹ ' + (active ? 'Desativar' : 'Ativar') + ' Conselho',
        message: `Tem certeza que deseja ${action} este conselho de classe?`,
        confirmText: active ? 'Sim, Desativar' : 'Sim, Ativar',
        confirmClass: active ? 'btn-danger' : 'btn-success',
        onConfirm: () => {
            document.getElementById('actionField').value = 'toggle';
            document.getElementById('idField').value = id;
            document.getElementById('formAction').submit();
        }
    });
}

function confirmDeleteConselho(id) {
    Modal.confirm({
        title: '🗑 Excluir Conselho',
        message: 'Tem certeza que deseja excluir permanentemente este conselho de classe? Todos os registros e encaminhamentos vinculados serão removidos.',
        confirmText: 'Sim, Excluir Para Sempre',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            document.getElementById('actionField').value = 'delete';
            document.getElementById('idField').value = id;
            document.getElementById('formAction').submit();
        }
    });
}

// Notificações via Toast
document.addEventListener('DOMContentLoaded', () => {
    <?php if ($success): ?> Toast.success(<?= json_encode($success) ?>); <?php endif; ?>
    <?php if ($error): ?> Toast.error(<?= json_encode($error) ?>); <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
