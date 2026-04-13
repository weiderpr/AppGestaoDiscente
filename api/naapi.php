<?php
/**
 * Vértice Acadêmico — API NAAPI
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/NaapiService.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sessão expirada.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$service = new \App\Services\NaapiService();
$inst = getCurrentInstitution();
$instId = $inst['id'] ?? 0;

if (!$instId) {
    echo json_encode(['success' => false, 'error' => 'Instituição não selecionada.']);
    exit;
}

try {
    switch ($action) {
        case 'search_alunos':
            hasDbPermission('naapi.index');
            $q = $_GET['q'] ?? '';
            $alunos = $service->searchAlunosNotInNaapi($instId, $q);
            echo json_encode(['success' => true, 'alunos' => $alunos]);
            break;

        case 'save':
            hasDbPermission('naapi.manage');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método inválido.');
            if (!csrf_verify($_POST['csrf_token'] ?? '')) throw new Exception('Token CSRF inválido.');

            $id = (int)($_POST['id'] ?? 0);
            $data = [
                'aluno_id' => (int)($_POST['aluno_id'] ?? 0),
                'institution_id' => $instId,
                'data_inclusao' => $_POST['data_inclusao'] ?? date('Y-m-d'),
                'neurodivergencia' => trim($_POST['neurodivergencia'] ?? ''),
                'campo_texto' => trim($_POST['campo_texto'] ?? ''),
                'observacoes_publicas' => trim($_POST['observacoes_publicas'] ?? '')
            ];

            if ($id > 0) {
                $service->update($id, $data);
                $message = 'Registro atualizado com sucesso.';
            } else {
                if (!$data['aluno_id']) throw new Exception('Aluno é obrigatório.');
                $service->add($data);
                $message = 'Aluno adicionado ao NAAPI com sucesso.';
            }

            echo json_encode(['success' => true, 'message' => $message]);
            break;

        case 'delete':
            hasDbPermission('naapi.manage');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método inválido.');
            if (!csrf_verify($_POST['csrf_token'] ?? '')) throw new Exception('Token CSRF inválido.');

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID inválido.');

            $service->delete($id);
            echo json_encode(['success' => true, 'message' => 'Registro removido com sucesso.']);
            break;

        case 'get':
            hasDbPermission('naapi.index');
            $id = (int)($_GET['id'] ?? 0);
            $registro = $service->getById($id);
            if (!$registro) throw new Exception('Registro não encontrado.');
            echo json_encode(['success' => true, 'data' => $registro]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Ação desconhecida.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
