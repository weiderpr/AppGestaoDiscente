<?php
/**
 * Vértice Acadêmico — AJAX Handler para Ambientes
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/Manutencao/AmbienteService.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
    exit;
}

$action = $_GET['action'] ?? '';
$ambienteService = new \App\Services\Manutencao\AmbienteService();
$inst = getCurrentInstitution();
$instId = $inst['id'] ?? 0;

if (!$instId) {
    echo json_encode(['success' => false, 'message' => 'Instituição não selecionada.']);
    exit;
}

try {
    switch ($action) {
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $data = $ambienteService->findById($id);
            if ($data) {
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Ambiente não encontrado.']);
            }
            break;

        case 'search':
            $q = trim($_GET['q'] ?? '');
            $data = $ambienteService->getAll($instId, $q);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'create':
            if (!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
                throw new Exception('Token CSRF inválido.');
            }
            
            $payload = [
                'institution_id' => $instId,
                'descricao' => $_POST['descricao'] ?? '',
                'predio_campus' => $_POST['predio_campus'] ?? '',
                'status' => $_POST['status'] ?? 'Ativo',
                'problemas' => $_POST['problemas'] ?? []
            ];

            if (empty($payload['descricao']) || empty($payload['predio_campus'])) {
                throw new Exception('Descrição e Prédio/Campus são obrigatórios.');
            }

            $result = $ambienteService->create($payload);
            echo json_encode($result);
            break;

        case 'update':
            if (!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
                throw new Exception('Token CSRF inválido.');
            }

            $id = (int)($_POST['id'] ?? 0);
            $payload = [
                'descricao' => $_POST['descricao'] ?? '',
                'predio_campus' => $_POST['predio_campus'] ?? '',
                'status' => $_POST['status'] ?? 'Ativo',
                'problemas' => $_POST['problemas'] ?? []
            ];

            $result = $ambienteService->update($id, $payload);
            echo json_encode($result);
            break;

        case 'delete':
            if (!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
                throw new Exception('Token CSRF inválido.');
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($ambienteService->delete($id)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
