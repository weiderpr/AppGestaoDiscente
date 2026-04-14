<?php
/**
 * AJAX API for Referrals (Encaminhamentos)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/ConselhoService.php';
require_once __DIR__ . '/../includes/atendimentos_functions.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$user = getCurrentUser();
$db = getDB();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            if (!hasDbPermission('conselhos.index', false)) throw new Exception('Acesso negado: Perfil insuficiente para salvar encaminhamentos.');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método inválido');
            
            $alunoId = (int)($_POST['aluno_id'] ?? 0);
            $conselhoId = (int)($_POST['conselho_id'] ?? 0);
            $setorTipo = $_POST['setor_tipo'] ?? '';
            $texto = $_POST['texto'] ?? ''; // This can contain HTML from contenteditable
            $dataExpectativa = $_POST['data_expectativa'] ?: null;
            $usuariosId = $_POST['usuarios_id'] ?? []; // Array of user IDs

            if (!$conselhoId || !$setorTipo || !$texto) {
                throw new Exception('Campos obrigatórios ausentes');
            }

            $conselhoService = new \App\Services\ConselhoService();
            $result = $conselhoService->addEncaminhamento($conselhoId, $user['id'], $alunoId, [
                'setor_tipo' => $setorTipo,
                'texto' => $texto,
                'data_expectativa' => $dataExpectativa,
                'usuarios_id' => $usuariosId
            ]);

            echo json_encode(['success' => true, 'message' => 'Encaminhamento registrado com sucesso']);
            break;

        case 'list':
            $alunoId = (int)($_GET['aluno_id'] ?? 0);
            $conselhoId = (int)($_GET['conselho_id'] ?? 0);

            if (!$alunoId && !$conselhoId) throw new Exception('Parâmetros ausentes');

            $sql = "
                SELECT ce.*, u.name as author_name, u.profile as author_profile,
                       ga.id as atendimento_id, ga.status as kanban_status,
                       cc.is_active as conselho_is_active,
                       (SELECT GROUP_CONCAT(un.name SEPARATOR ', ') 
                        FROM conselho_encaminhamento_usuarios ceu 
                        JOIN users un ON ceu.user_id = un.id 
                        WHERE ceu.encaminhamento_id = ce.id) as target_users
                FROM conselho_encaminhamentos ce
                JOIN conselhos_classe cc ON ce.conselho_id = cc.id
                JOIN users u ON ce.author_id = u.id
                LEFT JOIN gestao_atendimentos ga ON ce.id = ga.encaminhamento_id AND ga.deleted_at IS NULL
            ";

            if ($alunoId > 0) {
                $sql .= " WHERE ce.aluno_id = ? ";
                $params = [$alunoId];
            } else {
                $sql .= " WHERE ce.conselho_id = ? AND ce.aluno_id IS NULL ";
                $params = [$conselhoId];
            }

            $sql .= " ORDER BY ce.created_at DESC ";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $list = $stmt->fetchAll();

            echo json_encode(['success' => true, 'list' => $list]);
            break;

        case 'get_users':
            $setor = $_GET['setor'] ?? '';
            if (!$setor) throw new Exception('Setor ausente');

            $stmt = $db->prepare("SELECT id, name FROM users WHERE profile = ? AND is_active = 1 ORDER BY name ASC");
            $stmt->execute([$setor]);
            $users = $stmt->fetchAll();

            echo json_encode(['success' => true, 'users' => $users]);
            break;

        case 'list_by_council':
            $conselhoId = (int)($_GET['conselho_id'] ?? 0);
            if (!$conselhoId) throw new Exception('Conselho ID ausente');

            $stmt = $db->prepare("
                SELECT ce.*, a.nome as aluno_name, u.name as author_name, u.profile as author_profile,
                       ga.id as atendimento_id, ga.status as kanban_status,
                       cc.is_active as conselho_is_active,
                       (SELECT GROUP_CONCAT(un.name SEPARATOR ', ') 
                        FROM conselho_encaminhamento_usuarios ceu 
                        JOIN users un ON ceu.user_id = un.id 
                        WHERE ceu.encaminhamento_id = ce.id) as target_users
                FROM conselho_encaminhamentos ce
                JOIN conselhos_classe cc ON ce.conselho_id = cc.id
                LEFT JOIN alunos a ON ce.aluno_id = a.id
                JOIN users u ON ce.author_id = u.id
                LEFT JOIN gestao_atendimentos ga ON ce.id = ga.encaminhamento_id AND ga.deleted_at IS NULL
                WHERE ce.conselho_id = ?
                ORDER BY ce.created_at DESC
            ");
            $stmt->execute([$conselhoId]);
            $list = $stmt->fetchAll();

            echo json_encode(['success' => true, 'list' => $list]);
            break;

        case 'delete':
            if (!hasDbPermission('conselhos.index', false)) throw new Exception('Acesso negado: Perfil insuficiente para excluir encaminhamentos.');
            $referralId = (int)($_GET['id'] ?? 0);
            if (!$referralId) throw new Exception('ID do encaminhamento ausente');
            $conselhoService = new \App\Services\ConselhoService();
            $conselhoService->deleteEncaminhamento($referralId, $user['id'], $user['profile']);

            echo json_encode(['success' => true, 'message' => 'Encaminhamento removido com sucesso']);
            break;

        case 'get_atendimento':
            $referralId = (int)($_GET['id'] ?? 0);
            if (!$referralId) throw new Exception('ID do encaminhamento ausente');

            $atendimento = getAtendimentoByReferral($referralId);
            if (!$atendimento) throw new Exception('Atendimento não encontrado');

            // Busca responsáveis
            $stR = $db->prepare("
                SELECT u.id, u.name, u.photo, u.profile
                FROM gestao_atendimento_usuarios au
                JOIN users u ON au.usuario_id = u.id
                WHERE au.atendimento_id = ?
            ");
            $stR->execute([$atendimento['id']]);
            $responsaveis = $stR->fetchAll();

            // Busca comentários
            $stC = $db->prepare("
                SELECT ac.id, ac.texto, ac.is_private, ac.created_at,
                       u.name as author_name, u.photo as author_photo, u.profile as author_profile, u.id as author_id
                FROM gestao_atendimento_comentarios ac
                JOIN users u ON ac.usuario_id = u.id
                WHERE ac.atendimento_id = ?
                ORDER BY ac.created_at DESC
            ");
            $stC->execute([$atendimento['id']]);
            $comentarios = $stC->fetchAll();

            echo json_encode([
                'success' => true, 
                'atendimento' => $atendimento,
                'responsaveis' => $responsaveis,
                'comentarios' => $comentarios
            ]);
            break;

        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
