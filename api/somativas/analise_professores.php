<?php
/**
 * Vértice Acadêmico — API Análise de Alocação de Professores
 * Endpoint: /api/somativas/analise_professores.php
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

if (!hasDbPermission('somativas.index', false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

$user    = getCurrentUser();
$inst    = getCurrentInstitution();
$instId  = $inst['id'] ?? 0;
$service = new \App\Services\SomativaService();
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'load_analysis':
            $somativaId = (int)($_GET['somativa_id'] ?? 0);
            if (!$somativaId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Somativa não informada.']);
                exit;
            }

            // Busca detalhes da somativa para verificar se ela existe e pertence à instituição
            $som = $service->findById($somativaId, $instId);
            if (!$som) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Somativa não encontrada.']);
                exit;
            }

            // Carrega os dados das 4 abas
            $conflitos    = $service->getTeacherAnalysisConventionalConflict($somativaId, $instId);
            $agendas      = $service->getTeacherSchedules($somativaId, $instId);
            $cargas       = $service->getTeacherWorkload($somativaId, $instId);
            $sugestoes    = $service->getEqualizationSuggestions($somativaId, $instId);

            echo json_encode([
                'success'    => true,
                'conflitos'  => $conflitos,
                'agendas'    => $agendas,
                'cargas'     => $cargas,
                'sugestoes'  => $sugestoes
            ]);
            break;

        case 'apply_equalization':
            if (!hasDbPermission('somativas.update', false)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sem permissão para atualizar dados.']);
                exit;
            }

            // Verifica Token CSRF
            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!csrf_verify($csrfToken)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
                exit;
            }

            $somativaId = (int)($_POST['somativa_id'] ?? 0);
            if (!$somativaId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Somativa não informada.']);
                exit;
            }

            // O payload de swaps deve vir como string JSON
            $swapsJson = $_POST['swaps'] ?? '';
            $swaps = json_decode($swapsJson, true);

            if (!is_array($swaps)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Trocas inválidas ou não informadas.']);
                exit;
            }

            $res = $service->applyEqualization($somativaId, $swaps, $user['id']);
            echo json_encode($res);
            break;

        case 'preview_intelligent':
            $somativaId = (int)($_GET['somativa_id'] ?? 0);
            if (!$somativaId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Somativa não informada.']);
                exit;
            }

            $som = $service->findById($somativaId, $instId);
            if (!$som) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Somativa não encontrada.']);
                exit;
            }

            $result = $service->previewIntelligentAllocation($somativaId, $instId);
            echo json_encode(array_merge(['success' => true], $result));
            break;

        case 'apply_intelligent':
            if (!hasDbPermission('somativas.update', false)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sem permissão para atualizar dados.']);
                exit;
            }

            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!csrf_verify($csrfToken)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
                exit;
            }

            $somativaId = (int)($_POST['somativa_id'] ?? 0);
            if (!$somativaId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Somativa não informada.']);
                exit;
            }

            $proposalsJson = $_POST['proposals'] ?? '';
            $proposals = json_decode($proposalsJson, true);

            if (!is_array($proposals)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Propostas inválidas.']);
                exit;
            }

            $res = $service->applyIntelligentAllocation($somativaId, $proposals, $user['id']);
            echo json_encode($res);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
            break;
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
