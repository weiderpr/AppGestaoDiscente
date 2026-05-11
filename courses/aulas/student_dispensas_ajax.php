<?php
/**
 * Vértice Acadêmico — AJAX: Gestão de Dispensas de Disciplinas
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

// Usando permissão similar à de atividades ou específica
$canManage = hasDbPermission('students.schedule.dispensas', false) || hasDbPermission('students.schedule.activities', false);
if (!$canManage) {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para gerenciar dispensas.']);
    exit;
}

// Previne poluição do JSON por alertas/erros do PHP
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$db      = getDB();
require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/DispensaService.php';

$dispensaService = new \App\Services\DispensaService();
$user    = getCurrentUser();
$userId  = $user['id'];
$action  = $_GET['action'] ?? '';
$alunoId = (int)($_REQUEST['aluno_id'] ?? 0);

if (!$alunoId) {
    echo json_encode(['success' => false, 'message' => 'Aluno ID não fornecido.']);
    exit;
}

try {
    switch ($action) {
        case 'save':
            $turmaId = (int)($_POST['turma_id'] ?? 0);
            $discCodigo = trim($_POST['disciplina_codigo'] ?? '');

            $result = $dispensaService->saveDispensa($alunoId, $turmaId, $discCodigo, $userId);
            if ($result['success']) {
                echo json_encode(['success' => true, 'message' => $result['message']]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['error']]);
            }
            break;

        case 'delete':
            $turmaId = (int)($_POST['turma_id'] ?? 0);
            $discCodigo = trim($_POST['disciplina_codigo'] ?? '');

            $result = $dispensaService->removeDispensa($alunoId, $turmaId, $discCodigo, $userId);
            if ($result['success']) {
                echo json_encode(['success' => true, 'message' => $result['message']]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['error']]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
            break;
    }
} catch (Throwable $e) {
    // Garante retorno JSON mesmo em erros fatais do PHP
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true, 500);
    }
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
}
