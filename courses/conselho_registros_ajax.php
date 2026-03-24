<?php
/**
 * Vértice Acadêmico — API de Registros do Conselho (Post-its)
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$user = getCurrentUser();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            $conselhoId = (int)($_POST['conselho_id'] ?? 0);
            $alunoId = $_POST['aluno_id'] ? (int)$_POST['aluno_id'] : null;
            $texto = $_POST['texto'] ?? '';
            
            if (!$conselhoId || !$texto) {
                throw new Exception('Dados incompletos para salvar o registro.');
            }

            $stmt = $db->prepare("INSERT INTO conselho_registros (conselho_id, aluno_id, user_id, texto) VALUES (?, ?, ?, ?)");
            $stmt->execute([$conselhoId, $alunoId, $user['id'], $texto]);

            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
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
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID ausente');

            // Verificar se o usuário pode excluir (autor ou admin)
            $stmt = $db->prepare("SELECT user_id FROM conselho_registros WHERE id = ?");
            $stmt->execute([$id]);
            $reg = $stmt->fetch();
            
            if (!$reg) throw new Exception('Registro não encontrado');
            
            if ($reg['user_id'] != $user['id'] && !in_array($user['profile'], ['Administrador', 'Coordenador'])) {
                throw new Exception('Sem permissão para excluir este registro.');
            }

            $stmt = $db->prepare("DELETE FROM conselho_registros WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
