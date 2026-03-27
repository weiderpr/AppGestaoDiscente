<?php
/**
 * AJAX API for Referrals (Encaminhamentos)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/atendimentos_functions.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$user = getCurrentUser();
$db = getDB();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'save':
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

            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO conselho_encaminhamentos (conselho_id, aluno_id, author_id, setor_tipo, texto, data_expectativa)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$conselhoId, $alunoId > 0 ? $alunoId : null, $user['id'], $setorTipo, $texto, $dataExpectativa]);
            $encaminhamentoId = $db->lastInsertId();

            if (!empty($usuariosId) && is_array($usuariosId)) {
                $stmtUser = $db->prepare("INSERT INTO conselho_encaminhamento_usuarios (encaminhamento_id, user_id) VALUES (?, ?)");
                foreach ($usuariosId as $uId) {
                    $uId = (int)$uId;
                    if ($uId > 0) {
                        $stmtUser->execute([$encaminhamentoId, $uId]);
                    }
                }
            }

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Encaminhamento registrado com sucesso']);
            break;

        case 'list':
            $alunoId = (int)($_GET['aluno_id'] ?? 0);
            $conselhoId = (int)($_GET['conselho_id'] ?? 0);

            if (!$alunoId && !$conselhoId) throw new Exception('Parâmetros ausentes');

            $sql = "
                SELECT ce.*, u.name as author_name, u.profile as author_profile,
                       GROUP_CONCAT(target_u.name SEPARATOR ', ') as target_users,
                       (SELECT id FROM atendimentos WHERE encaminhamento_id = ce.id AND deleted_at IS NULL LIMIT 1) as atendimento_id,
                       cc.is_active as conselho_is_active
                FROM conselho_encaminhamentos ce
                JOIN conselhos_classe cc ON ce.conselho_id = cc.id
                JOIN users u ON ce.author_id = u.id
                LEFT JOIN conselho_encaminhamento_usuarios ceu ON ce.id = ceu.encaminhamento_id
                LEFT JOIN users target_u ON ceu.user_id = target_u.id
            ";

            if ($alunoId > 0) {
                $sql .= " WHERE ce.aluno_id = ? ";
                $params = [$alunoId];
            } else {
                $sql .= " WHERE ce.conselho_id = ? AND ce.aluno_id IS NULL ";
                $params = [$conselhoId];
            }

            $sql .= " GROUP BY ce.id ORDER BY ce.created_at DESC ";

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
                       GROUP_CONCAT(target_u.name SEPARATOR ', ') as target_users,
                       (SELECT id FROM atendimentos WHERE encaminhamento_id = ce.id AND deleted_at IS NULL LIMIT 1) as atendimento_id,
                       cc.is_active as conselho_is_active
                FROM conselho_encaminhamentos ce
                JOIN conselhos_classe cc ON ce.conselho_id = cc.id
                LEFT JOIN alunos a ON ce.aluno_id = a.id
                JOIN users u ON ce.author_id = u.id
                LEFT JOIN conselho_encaminhamento_usuarios ceu ON ce.id = ceu.encaminhamento_id
                LEFT JOIN users target_u ON ceu.user_id = target_u.id
                WHERE ce.conselho_id = ?
                GROUP BY ce.id
                ORDER BY ce.created_at DESC
            ");
            $stmt->execute([$conselhoId]);
            $list = $stmt->fetchAll();

            echo json_encode(['success' => true, 'list' => $list]);
            break;

        case 'delete':
            $referralId = (int)($_GET['id'] ?? 0);
            if (!$referralId) throw new Exception('ID do encaminhamento ausente');

            // Verifica se o encaminhamento existe, quem é o autor e se o conselho está ativo
            $stCheck = $db->prepare("
                SELECT ce.author_id, cc.is_active 
                FROM conselho_encaminhamentos ce
                JOIN conselhos_classe cc ON ce.conselho_id = cc.id
                WHERE ce.id = ?
            ");
            $stCheck->execute([$referralId]);
            $referral = $stCheck->fetch();

            if (!$referral) throw new Exception('Encaminhamento não encontrado');

            if ($referral['is_active'] == 0) {
                throw new Exception('Não é possível excluir encaminhamentos de um conselho finalizado');
            }

            // Permissões: Admin, Coordenador ou o Autor
            $allowed = ['Administrador', 'Coordenador'];
            if (!in_array($user['profile'], $allowed) && (int)$user['id'] !== (int)$referral['author_id']) {
                throw new Exception('Você não tem permissão para excluir este encaminhamento');
            }

            $db->beginTransaction();
            // Remove usuários vinculados primeiro
            $db->prepare("DELETE FROM conselho_encaminhamento_usuarios WHERE encaminhamento_id = ?")->execute([$referralId]);
            // Remove o encaminhamento
            $db->prepare("DELETE FROM conselho_encaminhamentos WHERE id = ?")->execute([$referralId]);
            $db->commit();

            echo json_encode(['success' => true, 'message' => 'Encaminhamento removido com sucesso']);
            break;

        case 'get_atendimento':
            $referralId = (int)($_GET['id'] ?? 0);
            if (!$referralId) throw new Exception('ID do encaminhamento ausente');

            $atendimento = getAtendimentoByReferral($referralId);
            if (!$atendimento) throw new Exception('Atendimento não encontrado');

            echo json_encode(['success' => true, 'atendimento' => $atendimento]);
            break;

        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
