<?php
/**
 * Vértice Acadêmico — API de Registros do Conselho (Post-its)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/ConselhoService.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$user = getCurrentUser();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            if (!hasDbPermission('conselhos.index', false)) throw new Exception('Acesso negado: Perfil insuficiente para salvar registros.');
            $conselhoId = (int)($_POST['conselho_id'] ?? 0);
            $alunoId = $_POST['aluno_id'] ? (int)$_POST['aluno_id'] : null;
            $texto = $_POST['texto'] ?? '';
            
            if (!$conselhoId || !$texto) {
                throw new Exception('Dados incompletos para salvar o registro.');
            }

            $conselhoService = new \App\Services\ConselhoService();
            $result = $conselhoService->addRegistro($conselhoId, $user['id'], $alunoId, $texto);

            echo json_encode($result);
            break;

        case 'list':
            $conselhoId = (int)($_GET['conselho_id'] ?? 0);
            $alunoId = isset($_GET['aluno_id']) && $_GET['aluno_id'] !== '' ? (int)$_GET['aluno_id'] : null;
            
            if (!$conselhoId) throw new Exception('Conselho ID ausente');

            $sql = "
                SELECT cr.*, u.name as author_name, u.profile as author_profile, a.nome as aluno_nome
                FROM conselho_registros cr
                LEFT JOIN users u ON cr.user_id = u.id
                LEFT JOIN alunos a ON cr.aluno_id = a.id
                WHERE cr.conselho_id = ?
            ";
            
            $params = [$conselhoId];
            if ($alunoId !== null) {
                $sql .= " AND cr.aluno_id = ?";
                $params[] = $alunoId;
            } else if (isset($_GET['general_only'])) {
                $sql .= " AND cr.aluno_id IS NULL";
            }

            $sql .= " ORDER BY cr.created_at DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $list = $stmt->fetchAll();

            echo json_encode(['success' => true, 'list' => $list]);
            break;

        case 'delete':
            if (!hasDbPermission('conselhos.index', false)) throw new Exception('Acesso negado: Perfil insuficiente para excluir registros.');
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID ausente');

            $conselhoService = new \App\Services\ConselhoService();
            $success = $conselhoService->deleteRegistro($id, $user['id'], $user['profile']);

            echo json_encode(['success' => $success]);
            break;

        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
