<?php
/**
 * Vértice Acadêmico — API de Notificações
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/NotificationService.php';

use App\Services\NotificationService;

requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user = getCurrentUser();
$service = new NotificationService();

try {
    switch ($action) {
        case 'fetch':
            $notifications = $service->getUnreadForUser($user);
            echo json_encode(['success' => true, 'data' => $notifications]);
            break;

        case 'mark_read':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID inválido');
            
            $success = $service->markAsRead($user['id'], $id);
            echo json_encode(['success' => $success]);
            break;

        case 'toggle_visibility':
            // 1 para exibir, 0 para ocultar
            $status = (int)($_POST['status'] ?? 1);
            
            $db = getDB();
            $st = $db->prepare("UPDATE users SET exibir_notificacoes = ? WHERE id = ?");
            $success = $st->execute([$status, $user['id']]);
            
            echo json_encode(['success' => $success]);
            break;

        case 'push':
            $data = [
                'titulo' => $_POST['titulo'] ?? '',
                'mensagem' => $_POST['mensagem'] ?? '',
                'tipo' => $_POST['tipo'] ?? 'Info',
                'aluno_id' => $_POST['aluno_id'] ? (int)$_POST['aluno_id'] : null,
                'turma_id' => $_POST['turma_id'] ? (int)$_POST['turma_id'] : null,
                'link_acao' => $_POST['link_acao'] ?? null
            ];
            
            if (!$data['titulo'] || !$data['mensagem']) {
                throw new Exception('Título e Mensagem são obrigatórios');
            }

            $id = $service->push($data);
            echo json_encode(['success' => $id > 0, 'id' => $id]);
            break;

        default:
            throw new Exception('Ação não reconhecida');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
