<?php
/**
 * Vértice Acadêmico — AJAX Handler para Manutenções (Kanban)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/Manutencao/ManutencaoService.php';
require_once __DIR__ . '/../../src/App/Services/Manutencao/AmbienteService.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
    exit;
}

$action = $_GET['action'] ?? '';
$manutencaoService = new \App\Services\Manutencao\ManutencaoService();
$ambienteService = new \App\Services\Manutencao\AmbienteService();
$inst = getCurrentInstitution();
$instId = $inst['id'] ?? 0;

try {
    switch ($action) {
        case 'list_kanban':
            $data = $manutencaoService->getKanbanData($instId);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'create':
            if (!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
                throw new Exception('Token CSRF inválido.');
            }
            
            $payload = [
                'institution_id' => $instId,
                'ambiente_id' => (int)($_POST['ambiente_id'] ?? 0),
                'descricao' => $_POST['descricao'] ?? '',
                'outros_detalhes' => $_POST['outros_detalhes'] ?? '',
                'status' => $_POST['status'] ?? 'Demandas',
                'data_manutencao' => $_POST['data_manutencao'] ?? date('Y-m-d H:i:s'),
                'problemas' => $_POST['problemas'] ?? []
            ];

            if (!$payload['ambiente_id'] || empty($payload['descricao'])) {
                throw new Exception('Ambiente e Descrição são obrigatórios.');
            }

            $result = $manutencaoService->create($payload);
            echo json_encode($result);
            break;

        case 'update_status':
            if (!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
                throw new Exception('Token CSRF inválido.');
            }

            $id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if ($manutencaoService->updateStatus($id, $status)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status.']);
            }
            break;

        case 'get_details':
            $id = (int)($_GET['id'] ?? 0);
            $data = $manutencaoService->findById($id);
            if ($data) {
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Manutenção não encontrada.']);
            }
            break;

        case 'get_ambiente_problemas':
            $ambienteId = (int)($_GET['ambiente_id'] ?? 0);
            $data = $ambienteService->findById($ambienteId);
            if ($data) {
                echo json_encode(['success' => true, 'problemas' => $data['problemas']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Ambiente não encontrado.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
