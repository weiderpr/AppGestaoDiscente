<?php
/**
 * Vértice Acadêmico — Gestão de Aulas (Schedules)
 * AJAX Handler dentro de pasta organizada
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/AuditHelper.php';

use App\Services\AuditHelper;

requireLogin();

$db     = getDB();
$inst   = getCurrentInstitution();
$instId = $inst['id'];
$audit  = new AuditHelper();

$turmaId          = (int)($_REQUEST['turma_id'] ?? 0);
$disciplinaCodigo = $_REQUEST['disciplina_codigo'] ?? '';
$disciplinaNome   = $_REQUEST['disciplina_nome'] ?? '';

if (!$turmaId || !$disciplinaCodigo) {
    die('<div class="alert alert-danger">Parâmetros inválidos.</div>');
}

// Verificar permissão/vínculo (mesma lógica do disciplinas_turma_ajax.php)
$stCheck = $db->prepare('
    SELECT 1 FROM turma_disciplinas td
    JOIN turmas t ON t.id = td.turma_id
    JOIN courses c ON c.id = t.course_id
    WHERE t.id = ? AND td.disciplina_codigo = ? AND c.institution_id = ?
');
$stCheck->execute([$turmaId, $disciplinaCodigo, $instId]);
if (!$stCheck->fetch()) {
    die('<div class="alert alert-danger">Relação turma-disciplina não encontrada ou sem permissão.</div>');
}

$success = '';
$error   = '';

// ---- AÇÕES (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança expirado.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_aula') {
            $dia      = (int)($_POST['dia_semana'] ?? 0);
            $inicio   = $_POST['horario_inicio'] ?? '';
            $fim      = $_POST['horario_fim'] ?? '';
            $user     = getCurrentUser();

            if (!$dia || !$inicio || !$fim) {
                $error = 'Todos os campos são obrigatórios.';
            } elseif ($inicio >= $fim) {
                $error = 'O horário de fim deve ser após o horário de início.';
            } else {
                $local = trim($_POST['local'] ?? '');
                $ocupacao = $_POST['ocupacao'] ?? 'Turma inteira';
                try {
                    $st = $db->prepare('
                        INSERT INTO gestao_turma_aulas (turma_id, disciplina_codigo, dia_semana, horario_inicio, horario_fim, local, ocupacao, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $st->execute([$turmaId, $disciplinaCodigo, $dia, $inicio, $fim, $local, $ocupacao, $user['id']]);
                    $audit->log('CREATE', 'gestao_turma_aulas', (int)$db->lastInsertId(), null, [
                        'turma_id' => $turmaId,
                        'disciplina_codigo' => $disciplinaCodigo,
                        'dia_semana' => $dia,
                        'horario_inicio' => $inicio,
                        'horario_fim' => $fim,
                        'local' => $local,
                        'ocupacao' => $ocupacao
                    ]);
                    $success = 'Aula cadastrada com sucesso!';
                } catch (\PDOException $e) {
                    $error = 'Erro ao salvar: ' . $e->getMessage();
                }
            }
        }

        if ($action === 'delete_aula') {
            $aulaId = (int)($_POST['aula_id'] ?? 0);
            if ($aulaId) {
                $old = $db->query("SELECT * FROM gestao_turma_aulas WHERE id = $aulaId AND turma_id = $turmaId")->fetch(PDO::FETCH_ASSOC);
                $db->prepare('DELETE FROM gestao_turma_aulas WHERE id = ? AND turma_id = ?')->execute([$aulaId, $turmaId]);
                if ($old) {
                    $audit->log('DELETE', 'gestao_turma_aulas', $aulaId, $old, null);
                }
                $success = 'Aula removida.';
            }
        }
    }
}

// ---- LISTAR AULAS ATUAIS ----
$stAulas = $db->prepare('
    SELECT * FROM gestao_turma_aulas 
    WHERE turma_id = ? AND disciplina_codigo = ? AND is_active = 1
    ORDER BY dia_semana, horario_inicio
');
$stAulas->execute([$turmaId, $disciplinaCodigo]);
$aulas = $stAulas->fetchAll();

$diasSemana = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo'
];
?>

<!-- Interface do Modal -->
<div class="aulas-modal-container">
    
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">⚠️ <?= $error ?></div>
    <?php endif; ?>

    <!-- Layout principal -->
    <div class="aulas-layout">
        
        <!-- Coluna Esquerda: Listagem com Scroll -->
        <div class="aulas-list-container">
            <div class="aulas-list-body">
                <?php if (empty($aulas)): ?>
                    <div class="aulas-empty">
                        <span class="aulas-empty-icon">📅</span>
                        <p>Nenhum horário cadastrado para esta disciplina.</p>
                        <small>Use o formulário ao lado para adicionar</small>
                    </div>
                <?php else: ?>
                    <table class="aulas-table">
                        <thead>
                            <tr>
                                <th>Dia da Semana</th>
                                <th>Início</th>
                                <th>Fim</th>
                                <th>Grupo</th>
                                <th>Local</th>
                                <th style="text-align:center;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aulas as $a): ?>
                                <tr>
                                    <td><span class="dia-badge"><?= $diasSemana[$a['dia_semana']] ?></span></td>
                                    <td><span class="time-badge"><?= substr($a['horario_inicio'], 0, 5) ?></span></td>
                                    <td><span class="time-badge"><?= substr($a['horario_fim'], 0, 5) ?></span></td>
                                    <td>
                                        <span class="badge-profile <?= $a['ocupacao'] === 'Turma inteira' ? 'badge-Administrador' : 'badge-Outro' ?>" style="font-size: 11px;">
                                            <?= htmlspecialchars($a['ocupacao']) ?>
                                        </span>
                                    </td>
                                    <td><span class="location-badge"><?= htmlspecialchars($a['local'] ?: 'Não definido') ?></span></td>
                                    <td style="text-align:center;">
                                        <button class="action-btn danger" onclick="deleteAula(<?= $a['id'] ?>)" title="Excluir">🗑️</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Coluna Direita: Formulário de Inclusão (Fixo) -->
        <div class="aulas-form-container">
            <div class="aulas-form-card">
                <div class="aulas-form-header">
                    <span class="card-title">➕ Nova Aula</span>
                </div>
                <div class="aulas-form-body">
                    <form id="formAula" onsubmit="saveAula(event)">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_aula">
                        <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                        <input type="hidden" name="disciplina_codigo" value="<?= $disciplinaCodigo ?>">
                        <input type="hidden" name="disciplina_nome" value="<?= htmlspecialchars($disciplinaNome) ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Dia da Semana</label>
                                <select name="dia_semana" class="form-control" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($diasSemana as $val => $label): ?>
                                        <option value="<?= $val ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Grupo</label>
                                <select name="ocupacao" class="form-control" required>
                                    <option value="Turma inteira">Turma inteira</option>
                                    <option value="Grupo 1">Grupo 1</option>
                                    <option value="Grupo 2">Grupo 2</option>
                                    <option value="Grupo 3">Grupo 3</option>
                                    <option value="Grupo 4">Grupo 4</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Início</label>
                                <input type="time" name="horario_inicio" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fim</label>
                                <input type="time" name="horario_fim" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Local (Sala/Bloco)</label>
                            <input type="text" name="local" class="form-control" placeholder="Ex: Sala 102, Lab A...">
                        </div>

                        <button type="submit" class="btn btn-primary btn-full" style="margin-top: 0.5rem;">
                            ➕ Incluir Aula
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
.aulas-modal-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
    box-sizing: border-box;
    overflow: auto;
}

.alert {
    flex-shrink: 0;
    margin: 0.75rem 1.5rem;
}

.aulas-layout {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 1rem;
    flex: 1;
    min-height: 0;
    padding: 0 1rem 1rem;
}

.aulas-list-container {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--bg-surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.aulas-list-body {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}

.aulas-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1.5rem;
    text-align: center;
    color: var(--text-muted);
    height: 100%;
}

.aulas-empty-icon {
    font-size: 3.5rem;
    opacity: 0.25;
    margin-bottom: 1rem;
}

.aulas-empty p {
    font-size: 0.9375rem;
    margin-bottom: 0.5rem;
    color: var(--text-secondary);
}

.aulas-empty small {
    font-size: 0.8125rem;
    opacity: 0.7;
}

.aulas-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.aulas-table thead {
    background: var(--bg-surface-2nd);
    position: sticky;
    top: 0;
    z-index: 10;
}

.aulas-table th {
    padding: 0.875rem 1rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--border-color);
}

.aulas-table td {
    padding: 0.875rem 1rem;
    border-bottom: 1px solid var(--border-light);
    vertical-align: middle;
}

.aulas-table tbody tr:hover {
    background: var(--bg-surface-2nd);
}

.dia-badge {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.8125rem;
}

.time-badge {
    background: var(--bg-surface-2nd);
    color: var(--text-primary);
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Mono', monospace;
    font-size: 0.8125rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 500;
}

.location-badge {
    font-size: 0.8125rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.location-badge::before {
    content: '📍';
    font-size: 0.875rem;
}

.aulas-form-container {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow-y: auto;
    padding-right: 8px;
    padding-bottom: 2rem;
    min-height: 0;
}

.aulas-form-container::-webkit-scrollbar { width: 6px; }
.aulas-form-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
[data-theme="dark"] .aulas-form-container::-webkit-scrollbar-thumb { background: #475569; }

.aulas-form-card {
    background: var(--bg-surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.aulas-form-header {
    padding: 0.75rem 1rem;
    background: linear-gradient(135deg, var(--color-primary) 0%, #4f46e5 100%);
    border-bottom: none;
}

.aulas-form-header .card-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: white;
}

.aulas-form-body {
    padding: 1rem;
}

.aulas-form-body .form-group {
    margin-bottom: 0.75rem;
}

.aulas-form-body .form-label {
    display: block;
    font-weight: 600;
    font-size: 0.8125rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.aulas-form-body .form-control {
    width: 100%;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    background: var(--bg-main);
    color: var(--text-primary);
    transition: all 0.2s ease;
}

.aulas-form-body .form-control:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.875rem;
}

.aulas-form-body .btn-full {
    width: 100%;
    margin-top: 0.25rem;
    padding: 0.625rem;
    font-weight: 700;
    font-size: 0.875rem;
    background: linear-gradient(135deg, var(--color-primary) 0%, #4f46e5 100%);
    border: none;
    color: white;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s ease;
}

.aulas-form-body .btn-full:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
}


@media (max-width: 900px) {
    .aulas-layout {
        grid-template-columns: 1fr;
        grid-template-rows: auto auto;
        overflow-y: auto;
        height: auto;
    }
    
    .aulas-form-container {
        height: auto;
        overflow: visible;
    }

    .aulas-list-container {
        height: 350px; /* Altura fixa no mobile antes do form */
        flex-shrink: 0;
    }
}
</style>
