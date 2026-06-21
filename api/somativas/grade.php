<?php
/**
 * Vértice Acadêmico — API Somativas Grade
 * Endpoint: /api/somativas/grade.php
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

$user   = getCurrentUser();
$inst   = getCurrentInstitution();
$instId = $inst['id'] ?? 0;
$service = new \App\Services\SomativaService();
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ── Dados completos da grade (turma) ──────────────────
        case 'load':
            $somId      = (int)($_GET['somativa_id'] ?? 0);
            $stId       = (int)($_GET['somativa_turma_id'] ?? 0);

            $som = $service->findById($somId, $instId);
            if (!$som) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Somativa não encontrada']); exit; }

            // Busca a turma desta somativa
            $turmaRow = null;
            foreach ($som['turmas'] as $t) {
                if ((int)$t['somativa_turma_id'] === $stId) { $turmaRow = $t; break; }
            }
            if (!$turmaRow) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Turma não encontrada na somativa']); exit; }

            $dates   = $service->getDatesInRange($som['data_inicio'], $som['data_fim']);
            $slots   = $service->getSlotsConfig($somId);
            $grade   = $service->getGrade($somId, $stId);
            $naoAloc = $service->getDisciplinasNaoAlocadas($somId, $stId);
            $avail   = $service->getTeacherAvailability((int)$turmaRow['turma_id'], $dates, $slots);

            // Indexa grade por data_slot_key para acesso rápido
            $gradeIndex = [];
            foreach ($grade as $g) {
                $gradeIndex[$g['data_prova'] . '_' . $g['slot_config_id']] = $g;
            }

            echo json_encode([
                'success'           => true,
                'somativa'          => $som,
                'turma'             => $turmaRow,
                'dates'             => $dates,
                'slots'             => $slots,
                'grade'             => $gradeIndex,
                'nao_alocadas'      => $naoAloc,
                'disponibilidade'   => $avail,
            ]);
            break;

        // ── Sugestões ─────────────────────────────────────────
        case 'suggestions':
            $somId = (int)($_GET['somativa_id'] ?? 0);
            $som   = $service->findById($somId, $instId);
            if (!$som) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Não encontrado']); exit; }
            echo json_encode(['success' => true, 'suggestions' => $service->getSuggestions($somId)]);
            break;

        // ── Sugerir Alocação ──────────────────────────────────
        case 'suggest_allocation':
            $somId  = (int)($_GET['somativa_id']             ?? 0);
            $stId   = (int)($_GET['somativa_turma_id']       ?? 0);
            $sdId   = (int)($_GET['somativa_disciplina_id']  ?? 0) ?: null;
            $data   = $_GET['data_prova']                    ?? '';
            $slotId = (int)($_GET['slot_config_id']          ?? 0);

            if (!$somId || !$stId || !$slotId || !$data) {
                echo json_encode(['success' => false, 'message' => 'Parâmetros insuficientes']); exit;
            }

            $suggested = $service->getSuggestedSlotDetails($somId, $stId, $sdId, $data, $slotId);
            echo json_encode(['success' => true, 'data' => $suggested]);
            break;

        // ── Salvar slot ───────────────────────────────────────
        case 'save_slot':
            if (!hasDbPermission('somativas.update', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido']); exit;
            }

            $somId  = (int)($_POST['somativa_id']            ?? 0);
            $stId   = (int)($_POST['somativa_turma_id']      ?? 0);
            $sdId   = (int)($_POST['somativa_disciplina_id'] ?? 0) ?: null;
            $slotId = (int)($_POST['slot_config_id']         ?? 0);
            $data   = $_POST['data_prova'] ?? '';

            if (!$somId || !$stId || !$slotId || !$data) {
                echo json_encode(['success' => false, 'message' => 'Parâmetros insuficientes']); exit;
            }

            // Valida limite de provas por dia para esta turma
            $db  = getDB();
            $somStmt = $db->prepare('SELECT max_provas_por_dia FROM somativas WHERE id = ?');
            $somStmt->execute([$somId]);
            $som = $somStmt->fetch();
            if ($som) {
                $count = (int)$db->prepare(
                    'SELECT COUNT(*) FROM somativa_grade
                     WHERE somativa_turma_id = ? AND data_prova = ? AND tipo = "Normal"'
                )->execute([$stId, $data]) ? $db->query(
                    "SELECT COUNT(*) FROM somativa_grade WHERE somativa_turma_id={$stId} AND data_prova='{$data}' AND tipo='Normal'"
                )->fetchColumn() : 0;

                // Permite salvar se é atualização de slot já existente
                $existing = $db->prepare(
                    'SELECT id FROM somativa_grade WHERE somativa_turma_id = ? AND data_prova = ? AND slot_config_id = ?'
                );
                $existing->execute([$stId, $data, $slotId]);
                $isUpdate = (bool)$existing->fetch();

                if (!$isUpdate && $count >= $som['max_provas_por_dia']) {
                    echo json_encode([
                        'success' => false,
                        'message' => "Limite de {$som['max_provas_por_dia']} prova(s) por dia atingido para esta turma."
                    ]);
                    exit;
                }
            }

            $result = $service->saveGradeSlot([
                'somativa_id'            => $somId,
                'somativa_turma_id'      => $stId,
                'somativa_disciplina_id' => $sdId,
                'data_prova'             => $data,
                'slot_config_id'         => $slotId,
                'aplicador_id'           => (int)($_POST['aplicador_id']       ?? 0) ?: null,
                'volante_id'             => (int)($_POST['volante_id']         ?? 0) ?: null,
                'naapi_aplicador_id'     => (int)($_POST['naapi_aplicador_id'] ?? 0) ?: null,
                'ambiente_id'            => (int)($_POST['ambiente_id']        ?? 0) ?: null,
                'tipo'                   => $_POST['tipo'] ?? 'Normal',
                'observacoes'            => trim($_POST['observacoes'] ?? '') ?: null,
                'created_by'             => $user['id'],
                'propagate_naapi'        => (int)($_POST['propagate_naapi']    ?? 0),
            ]);

            if ($result['success']) {
                $clearGradeId = (int)($_POST['clear_grade_id'] ?? 0);
                if ($clearGradeId) {
                    $service->clearGradeSlot($clearGradeId);
                }
            }

            echo json_encode($result);
            break;

        // ── Limpar slot ───────────────────────────────────────
        case 'clear_slot':
            if (!hasDbPermission('somativas.update', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido']); exit;
            }

            $gradeId = (int)($_POST['grade_id'] ?? 0);
            echo json_encode(['success' => $service->clearGradeSlot($gradeId)]);
            break;

        // ── Limpar todos os cards da turma ────────────────────
        case 'clear_all_slots':
            if (!hasDbPermission('somativas.update', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido']); exit;
            }

            $stId = (int)($_POST['somativa_turma_id'] ?? 0);
            if (!$stId) {
                echo json_encode(['success' => false, 'message' => 'Turma não informada']); exit;
            }

            $removed = $service->clearAllGradeSlotsByTurma($stId);
            echo json_encode(['success' => true, 'removed' => $removed]);
            break;

        // ── Limpar todos os cards de todas as turmas ──────────
        case 'clear_all_somativa_slots':
            if (!hasDbPermission('somativas.update', false)) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido']); exit;
            }

            $somId = (int)($_POST['somativa_id'] ?? 0);
            if (!$somId) {
                echo json_encode(['success' => false, 'message' => 'Somativa não informada']); exit;
            }

            $removed = $service->clearAllGradeSlotsBySomativa($somId);
            echo json_encode(['success' => true, 'removed' => $removed]);
            break;

        // ── Disciplinas não alocadas ──────────────────────────
        case 'nao_alocadas':
            $somId = (int)($_GET['somativa_id']       ?? 0);
            $stId  = (int)($_GET['somativa_turma_id'] ?? 0);
            echo json_encode([
                'success' => true,
                'data'    => $service->getDisciplinasNaoAlocadas($somId, $stId),
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
