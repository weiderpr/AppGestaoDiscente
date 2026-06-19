<?php
/**
 * Vértice Acadêmico — API CRUD de Restrições de Somativa
 *
 * GET  ?somativa_id=X             → lista restrições
 * POST action=save                → cria ou atualiza restrição
 * POST action=delete  id=X        → remove restrição
 * POST action=toggle  id=X        → ativa / desativa restrição
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/Traits/Auditable.php';
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

$inst    = getCurrentInstitution();
$instId  = (int)($inst['id'] ?? 0);
$user    = getCurrentUser();
$service = new \App\Services\SomativaService();
$method  = $_SERVER['REQUEST_METHOD'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

try {

    // ── Listagem ──────────────────────────────────────────────
    if ($method === 'GET') {
        $somId = (int)($_GET['somativa_id'] ?? 0);
        if (!$somId) { echo json_encode(['success' => false, 'message' => 'somativa_id obrigatório']); exit; }

        // Verifica acesso à somativa
        $som = $service->findById($somId, $instId);
        if (!$som) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Somativa não encontrada']); exit; }

        echo json_encode([
            'success'    => true,
            'restricoes' => $service->getRestricoes($somId),
        ]);
        exit;
    }

    // ── Mutações ──────────────────────────────────────────────
    if (!hasDbPermission('somativas.update', false)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissão para editar somativas']);
        exit;
    }

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }

    $somId = (int)($_POST['somativa_id'] ?? 0);
    if (!$somId) { echo json_encode(['success' => false, 'message' => 'somativa_id obrigatório']); exit; }

    $som = $service->findById($somId, $instId);
    if (!$som) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Somativa não encontrada']); exit; }

    switch ($action) {

        // ── Salvar (criar ou atualizar) ───────────────────────
        case 'save':
            $categoria = trim($_POST['categoria'] ?? '');
            $tipo      = in_array($_POST['tipo'] ?? '', ['hard', 'soft']) ? $_POST['tipo'] : 'soft';

            if (!$categoria) {
                echo json_encode(['success' => false, 'message' => 'Categoria obrigatória']);
                exit;
            }

            // Valida e monta params conforme a categoria
            $params = buildParams($categoria, $_POST);
            if ($params === null) {
                echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos para a categoria ' . $categoria]);
                exit;
            }

            $result = $service->saveRestricao($somId, $user['id'], [
                'id'        => (int)($_POST['id'] ?? 0) ?: null,
                'tipo'      => $tipo,
                'categoria' => $categoria,
                'params'    => $params,
                'peso'      => max(1, min(10, (int)($_POST['peso'] ?? 5))),
                'descricao' => trim($_POST['descricao'] ?? ''),
            ]);

            echo json_encode($result);
            break;

        // ── Excluir ───────────────────────────────────────────
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'id obrigatório']); exit; }
            echo json_encode(['success' => $service->deleteRestricao($id, $somId)]);
            break;

        // ── Ativar / Desativar ────────────────────────────────
        case 'toggle':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'id obrigatório']); exit; }
            echo json_encode(['success' => $service->toggleRestricao($id, $somId)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }

} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[restricoes.php] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}

// ── Monta params JSON conforme categoria ──────────────────────

function buildParams(string $categoria, array $post): ?array
{
    switch ($categoria) {

        case 'bloquear_data':
            $data = trim($post['param_data'] ?? '');
            if (!$data || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) return null;
            return ['data' => $data, 'motivo' => trim($post['param_motivo'] ?? '') ?: 'Bloqueio'];

        case 'mesmo_dia_horario_diferente':
            $codA   = trim($post['param_disciplina_codigo_a'] ?? '');
            $codB   = trim($post['param_disciplina_codigo_b'] ?? '');
            $scope  = trim($post['param_scope_relacao'] ?? '');
            $regra  = trim($post['param_regra_relacao'] ?? '');

            if (!$codA || !$codB) return null;
            if (!in_array($scope, ['mesma_turma', 'turmas_diferentes'])) return null;
            if (!in_array($regra, ['deve', 'nao_deve'])) return null;

            return [
                'disciplina_codigo_a' => $codA,
                'disciplina_codigo_b' => $codB,
                'scope'               => $scope,
                'regra'               => $regra
            ];

        case 'evitar_mesmo_dia':
            $raw = $post['param_disciplinas'] ?? '';
            $discs = is_array($raw)
                ? array_values(array_filter(array_map('trim', $raw)))
                : array_values(array_filter(array_map('trim', explode(',', $raw))));
            if (count($discs) < 2) return null;
            return ['disciplinas' => $discs];

        case 'mesmo_dia_turmas':
            $cod = trim($post['param_disciplina_codigo'] ?? '');
            if (!$cod) return null;
            return ['disciplina_codigo' => $cod];

        case 'mesmo_horario_turmas':
            $scope = trim($post['param_scope'] ?? 'disciplina');
            if ($scope === 'turma') {
                $turmaId = (int)($post['param_turma_id'] ?? 0);
                if (!$turmaId) return null;
                return ['scope' => 'turma', 'turma_id' => $turmaId];
            } else {
                $cod = trim($post['param_disciplina_codigo'] ?? '');
                if (!$cod) return null;
                return ['scope' => 'disciplina', 'disciplina_codigo' => $cod];
            }

        case 'professor_indisponivel':
            $profId = (int)($post['param_professor_id'] ?? 0);
            $datas  = array_values(array_filter(
                array_map('trim', explode(',', $post['param_datas'] ?? ''))
            ));
            if (!$profId || empty($datas)) return null;
            // Valida cada data
            foreach ($datas as $d) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return null;
            }
            return ['professor_id' => $profId, 'datas' => $datas];

        case 'professor_mesmo_dia_horario':
            $scope = trim($post['param_pdh_scope'] ?? 'especifico');
            if ($scope === 'todos') {
                return ['todos' => true];
            }
            $profId = (int)($post['param_professor_id'] ?? 0);
            if (!$profId) return null;
            return ['professor_id' => $profId];

        case 'mesmo_dia_horario_grupo':
            $raw   = $post['param_pares'] ?? [];
            $sdIds = is_array($raw)
                ? array_values(array_unique(array_filter(array_map('intval', $raw))))
                : [];
            if (count($sdIds) < 2) return null;
            return ['pares' => array_map(fn($id) => ['somativa_disciplina_id' => $id], $sdIds)];

        case 'preferir_primeiros_horarios':
            $scope = trim($post['param_scope'] ?? 'todas');
            if (!in_array($scope, ['todas', 'turma'])) return null;
            if ($scope === 'turma') {
                $stId = (int)($post['param_somativa_turma_id'] ?? 0);
                if (!$stId) return null;
                return ['scope' => 'turma', 'somativa_turma_id' => $stId];
            }
            return ['scope' => 'todas'];

        case 'min_provas_por_dia':
            $min   = max(1, (int)($post['param_min'] ?? 2));
            $scope = trim($post['param_scope'] ?? 'todas');
            if (!in_array($scope, ['todas', 'turma'])) return null;
            if ($scope === 'turma') {
                $stId = (int)($post['param_somativa_turma_id'] ?? 0);
                if (!$stId) return null;
                return ['min' => $min, 'scope' => 'turma', 'somativa_turma_id' => $stId];
            }
            return ['min' => $min, 'scope' => 'todas'];

        default:
            // Categoria desconhecida: aceita params livres em JSON
            $jsonRaw = trim($post['param_json'] ?? '{}');
            $decoded = json_decode($jsonRaw, true);
            return is_array($decoded) ? $decoded : null;
    }
}
