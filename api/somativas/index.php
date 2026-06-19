<?php
/**
 * Vértice Acadêmico — API Somativas (CRUD)
 * Endpoint: /api/somativas/index.php
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/SomativaService.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$user    = getCurrentUser();
$inst    = getCurrentInstitution();
$instId  = $inst['id'] ?? 0;

if (!$instId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nenhuma instituição selecionada']);
    exit;
}

$service = new \App\Services\SomativaService();
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ── Listar ────────────────────────────────────────────
        case 'list':
            if (!hasDbPermission('somativas.index', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            $search = trim($_GET['q'] ?? '');
            echo json_encode(['success' => true, 'data' => $service->getAll($instId, $search)]);
            break;

        // ── Buscar por ID ─────────────────────────────────────
        case 'get':
            if (!hasDbPermission('somativas.index', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            $id  = (int)($_GET['id'] ?? 0);
            $som = $service->findById($id, $instId);
            if (!$som) {
                http_response_code(404); echo json_encode(['success' => false, 'message' => 'Não encontrado']); exit;
            }
            echo json_encode(['success' => true, 'data' => $som]);
            break;

        // ── Criar ─────────────────────────────────────────────
        case 'create':
            if (!hasDbPermission('somativas.create', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido']); exit;
            }

            $nome = trim($_POST['nome'] ?? '');
            if (strlen($nome) < 3) {
                echo json_encode(['success' => false, 'message' => 'Nome deve ter pelo menos 3 caracteres']); exit;
            }
            if (empty($_POST['data_inicio']) || empty($_POST['data_fim'])) {
                echo json_encode(['success' => false, 'message' => 'Datas de início e fim são obrigatórias']); exit;
            }
            if ($_POST['data_fim'] < $_POST['data_inicio']) {
                echo json_encode(['success' => false, 'message' => 'Data final deve ser posterior à inicial']); exit;
            }

            $result = $service->create($instId, $user['id'], [
                'nome'               => $nome,
                'descricao'          => trim($_POST['descricao'] ?? ''),
                'data_inicio'        => $_POST['data_inicio'],
                'data_fim'           => $_POST['data_fim'],
                'max_provas_por_dia' => (int)($_POST['max_provas_por_dia'] ?? 2),
            ]);

            // Salva slots se enviados
            if (!empty($_POST['slots']) && $result['success']) {
                $slots = json_decode($_POST['slots'], true) ?? [];
                if (!empty($slots)) {
                    $service->saveSlotsConfig($result['id'], $slots);
                }
            }

            echo json_encode($result);
            break;

        // ── Atualizar ─────────────────────────────────────────
        case 'update':
            if (!hasDbPermission('somativas.update', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido']); exit;
            }

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'ID inválido']); exit; }

            $fields = [];
            foreach (['nome','descricao','data_inicio','data_fim','max_provas_por_dia','status','segunda_chamada_data','naapi_ambiente_id','naapi_tempo_extra_min'] as $f) {
                if (array_key_exists($f, $_POST)) $fields[$f] = $_POST[$f] !== '' ? $_POST[$f] : null;
            }
            $result = $service->update($id, $fields);

            if (!empty($_POST['slots'])) {
                $slots = json_decode($_POST['slots'], true) ?? [];
                $service->saveSlotsConfig($id, $slots);
            }

            echo json_encode($result);
            break;

        // ── Salvar apenas slots de horário ────────────────────
        case 'save_slots':
            if (!hasDbPermission('somativas.update', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido']); exit;
            }

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'ID inválido']); exit; }

            $slots = json_decode($_POST['slots'] ?? '[]', true) ?? [];
            $service->saveSlotsConfig($id, $slots);
            echo json_encode(['success' => true]);
            break;

        // ── Excluir ───────────────────────────────────────────
        case 'delete':
            if (!hasDbPermission('somativas.delete', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido']); exit;
            }

            $id = (int)($_POST['id'] ?? 0);
            echo json_encode(['success' => $service->delete($id)]);
            break;

        // ── Adicionar Turma ───────────────────────────────────
        case 'add_turma':
            if (!hasDbPermission('somativas.update', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido']); exit;
            }

            $somId   = (int)($_POST['somativa_id'] ?? 0);
            $turmaId = (int)($_POST['turma_id'] ?? 0);
            $stId    = $service->addTurma($somId, $turmaId);
            echo json_encode(['success' => true, 'somativa_turma_id' => $stId]);
            break;

        // ── Remover Turma ─────────────────────────────────────
        case 'remove_turma':
            if (!hasDbPermission('somativas.update', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido']); exit;
            }

            $stId = (int)($_POST['somativa_turma_id'] ?? 0);
            echo json_encode(['success' => $service->removeTurma($stId)]);
            break;

        // ── Adicionar Disciplina ──────────────────────────────
        case 'add_disciplina':
            if (!hasDbPermission('somativas.update', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido']); exit;
            }

            $stId   = (int)($_POST['somativa_turma_id'] ?? 0);
            $codigo = trim($_POST['disciplina_codigo'] ?? '');
            $sdId   = $service->addDisciplina($stId, $codigo);
            echo json_encode(['success' => true, 'somativa_disciplina_id' => $sdId]);
            break;

        // ── Remover Disciplina ────────────────────────────────
        case 'remove_disciplina':
            if (!hasDbPermission('somativas.update', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido']); exit;
            }

            $sdId = (int)($_POST['somativa_disciplina_id'] ?? 0);
            echo json_encode(['success' => $service->removeDisciplina($sdId)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
