<?php
/**
 * Vértice Acadêmico — API Gestão de Atendimentos (Service-Oriented)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/AtendimentoService.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = (int)$inst['id'];
$db = getDB();

$service = new \App\Services\AtendimentoService();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'fetch_board':
            $showArchived = ($_POST['show_archived'] ?? $_GET['show_archived'] ?? 'false') === 'true';

            // 1. Demandas (Encaminhamentos Pendentes)
            $stDemandas = $db->prepare("
                SELECT e.id as encaminhamento_id, e.setor_tipo, e.data_expectativa, e.status as enc_status,
                       a.id as aluno_id, a.nome as aluno_nome, a.photo as aluno_photo,
                       (SELECT COUNT(*) FROM sancao WHERE aluno_id = a.id AND status != 'Cancelado') as total_sancoes,
                       c.id as conselho_id, t.id as turma_id, t.description as turma_nome
                FROM conselho_encaminhamentos e
                JOIN alunos a ON e.aluno_id = a.id
                JOIN conselhos_classe c ON e.conselho_id = c.id
                JOIN turmas t ON c.turma_id = t.id
                JOIN courses co ON t.course_id = co.id
                WHERE co.institution_id = ? 
                  AND e.status = 'Pendente'
                  AND (
                      EXISTS (SELECT 1 FROM conselho_encaminhamento_usuarios ceu WHERE ceu.encaminhamento_id = e.id AND ceu.user_id = ?)
                      OR 
                      (NOT EXISTS (SELECT 1 FROM conselho_encaminhamento_usuarios ceu2 WHERE ceu2.encaminhamento_id = e.id) AND e.setor_tipo = ?)
                  )
            ");
            $stDemandas->execute([$instId, $user['id'], $user['profile']]);
            $demandas = $stDemandas->fetchAll(PDO::FETCH_ASSOC);

            $cardsDemandas = array_map(function($d) {
                return [
                    'id' => 'enc_' . $d['encaminhamento_id'],
                    'encaminhamento_id' => $d['encaminhamento_id'],
                    'aluno_id' => $d['aluno_id'],
                    'aluno_nome' => $d['aluno_nome'],
                    'aluno_photo' => $d['aluno_photo'],
                    'turma_id' => $d['turma_id'],
                    'turma_nome' => $d['turma_nome'],
                    'titulo' => 'Encaminhamento: ' . $d['setor_tipo'],
                    'status' => 'Demandas',
                    'total_sancoes' => (int)$d['total_sancoes'],
                    'data' => $d['data_expectativa'],
                    'is_encaminhamento' => true,
                    'responsaveis' => []
                ];
            }, $demandas);

            // 2. Atendimentos Em Andamento
            $stAtend = $db->prepare("
                SELECT at.*, 
                       a.nome as aluno_nome, a.photo as aluno_photo,
                       (SELECT COUNT(*) FROM sancao WHERE aluno_id = a.id AND status != 'Cancelado') as total_sancoes,
                       t.description as turma_nome,
                       u.name as author_name, u.photo as author_photo
                FROM gestao_atendimentos at
                LEFT JOIN alunos a ON at.aluno_id = a.id
                LEFT JOIN turmas t ON at.turma_id = t.id
                LEFT JOIN users u ON at.author_id = u.id
                JOIN gestao_atendimento_usuarios gau ON at.id = gau.atendimento_id
                WHERE at.institution_id = ? AND at.deleted_at IS NULL AND gau.usuario_id = ?" . ($showArchived ? "" : " AND at.is_archived = 0") . "
            ");
            $stAtend->execute([$instId, $user['id']]);
            $atendimentos = $stAtend->fetchAll(PDO::FETCH_ASSOC);

            $atendIds = array_column($atendimentos, 'id');
            $responsaveisMap = [];
            if (!empty($atendIds)) {
                $inQuery = implode(',', array_fill(0, count($atendIds), '?'));
                $stResp = $db->prepare("
                    SELECT au.atendimento_id, u.name, u.photo
                    FROM gestao_atendimento_usuarios au
                    JOIN users u ON au.usuario_id = u.id
                    WHERE au.atendimento_id IN ($inQuery)
                ");
                $stResp->execute($atendIds);
                foreach ($stResp->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $responsaveisMap[$r['atendimento_id']][] = ['name' => $r['name'], 'photo' => $r['photo']];
                }
            }

            $cardsAtend = array_map(function($a) use ($responsaveisMap) {
                return [
                    'id' => $a['id'],
                    'aluno_id' => $a['aluno_id'],
                    'aluno_nome' => $a['aluno_nome'],
                    'aluno_photo' => $a['aluno_photo'],
                    'turma_id' => $a['turma_id'],
                    'turma_nome' => $a['turma_nome'],
                    'titulo' => strip_tags(html_entity_decode((string)$a['titulo'])),
                    'status' => $a['status'],
                    'total_sancoes' => (int)$a['total_sancoes'],
                    'is_archived' => (bool)$a['is_archived'],
                    'data' => $a['created_at'],
                    'is_encaminhamento' => false,
                    'responsaveis' => $responsaveisMap[$a['id']] ?? []
                ];
            }, $atendimentos);

            $board = ['Demandas' => $cardsDemandas, 'Aberto' => [], 'Em Atendimento' => [], 'Finalizado' => []];
            foreach ($cardsAtend as $card) {
                if (isset($board[$card['status']])) $board[$card['status']][] = $card;
            }

            echo json_encode(['success' => true, 'board' => $board]);
            break;

        case 'update_status':
            $cardId = $_POST['card_id'] ?? '';
            $newStatus = $_POST['new_status'] ?? '';
            
            if (strpos($cardId, 'enc_') === 0) {
                $encId = (int)str_replace('enc_', '', $cardId);
                $st = $db->prepare("SELECT e.*, c.turma_id FROM conselho_encaminhamentos e JOIN conselhos_classe c ON e.conselho_id = c.id WHERE e.id = ?");
                $st->execute([$encId]);
                $enc = $st->fetch(PDO::FETCH_ASSOC);
                
                $newId = $service->save([
                    'institution_id' => $instId,
                    'user_id' => $user['id'],
                    'aluno_id' => $enc['aluno_id'],
                    'turma_id' => $enc['turma_id'],
                    'encaminhamento_id' => $encId,
                    'status' => $newStatus,
                    'titulo' => 'Demanda: ' . $enc['setor_tipo']
                ]);
                echo json_encode(['success' => true, 'new_id' => $newId]);
            } else {
                $service->updateStatus((int)$cardId, $instId, $newStatus);
                echo json_encode(['success' => true, 'new_id' => $cardId]);
            }
            break;

        case 'create_atendimento':
            $newId = $service->save([
                'institution_id' => $instId,
                'user_id' => $user['id'],
                'aluno_id' => $_POST['aluno_id'] ?? null,
                'turma_id' => $_POST['turma_id'] ?? null,
                'titulo' => trim($_POST['titulo'] ?? 'Atendimento Profissional'),
                'status' => 'Aberto'
            ]);
            echo json_encode(['success' => true, 'new_id' => $newId]);
            break;

        case 'get_details':
            $rawId = $_GET['id'] ?? '';
            if (strpos($rawId, 'enc_') === 0) {
                $encId = (int)str_replace('enc_', '', $rawId);
                $st = $db->prepare("SELECT e.*, a.nome as aluno_nome, a.matricula, a.photo as aluno_photo, t.description as turma_nome FROM conselho_encaminhamentos e JOIN alunos a ON e.aluno_id = a.id JOIN conselhos_classe c ON e.conselho_id = c.id JOIN turmas t ON c.turma_id = t.id WHERE e.id = ?");
                $st->execute([$encId]);
                $atend = $st->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'atendimento' => $atend, 'responsaveis' => [], 'comentarios' => []]);
            } else {
                $atendId = (int)$rawId;
                $st = $db->prepare("SELECT at.*, a.nome as aluno_nome, a.photo as aluno_photo, t.description as turma_nome FROM gestao_atendimentos at LEFT JOIN alunos a ON at.aluno_id = a.id LEFT JOIN turmas t ON at.turma_id = t.id WHERE at.id = ? AND at.institution_id = ?");
                $st->execute([$atendId, $instId]);
                $atend = $st->fetch(PDO::FETCH_ASSOC);
                
                $stR = $db->prepare("SELECT u.id, u.name, u.photo FROM gestao_atendimento_usuarios au JOIN users u ON au.usuario_id = u.id WHERE au.atendimento_id = ?");
                $stR->execute([$atendId]);
                
                $stC = $db->prepare("SELECT ac.*, u.name as author_name FROM gestao_atendimento_comentarios ac JOIN users u ON ac.usuario_id = u.id WHERE ac.atendimento_id = ? ORDER BY ac.created_at DESC");
                $stC->execute([$atendId]);

                echo json_encode([
                    'success' => true, 
                    'atendimento' => $atend, 
                    'responsaveis' => $stR->fetchAll(PDO::FETCH_ASSOC), 
                    'comentarios' => $stC->fetchAll(PDO::FETCH_ASSOC)
                ]);
            }
            break;

        case 'archive_atendimento':
            $service->archive((int)$_POST['atendimento_id'], (bool)$_POST['archive']);
            echo json_encode(['success' => true]);
            break;

        case 'save_info':
            $service->update((int)$_POST['atendimento_id'], $instId, [
                'public_text' => $_POST['descricao_publica'] ?? '',
                'professional_text' => $_POST['descricao_profissional'] ?? ''
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'save_comment':
            $service->addComment([
                'atendimento_id' => (int)$_POST['atendimento_id'],
                'usuario_id' => $user['id'],
                'texto' => trim($_POST['texto'] ?? ''),
                'is_private' => (int)($_POST['is_private'] ?? 0)
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_comment':
            $service->deleteComment((int)($_POST['comentario_id'] ?? $_POST['comment_id']));
            echo json_encode(['success' => true]);
            break;

        case 'add_responsible':
            $service->addResponsible((int)$_POST['atendimento_id'], (int)$_POST['usuario_id']);
            echo json_encode(['success' => true]);
            break;

        case 'remove_responsible':
            $service->removeResponsible((int)$_POST['atendimento_id'], (int)$_POST['usuario_id']);
            echo json_encode(['success' => true]);
            break;

        case 'delete_atendimento':
            $service->deleteAtendimento((int)$_POST['atendimento_id'], $instId);
            echo json_encode(['success' => true]);
            break;
            
        case 'fetch_anexos':
            $atendimentoId = (int)$_GET['atendimento_id'];
            $anexos = $service->getAnexos($atendimentoId);
            echo json_encode(['success' => true, 'anexos' => $anexos]);
            break;

        case 'upload_anexo':
            if (empty($_FILES['arquivo']['tmp_name'])) {
                throw new Exception('Arquivo não enviado.');
            }
            
            $atendimentoId = (int)$_POST['atendimento_id'];
            $descricao = trim($_POST['descricao'] ?? '');
            $file = $_FILES['arquivo'];
            
            // Segurança: Extensões permitidas
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($ext, $allowed)) {
                throw new Exception('Formato de arquivo não permitido. Use PDF ou Imagem.');
            }
            
            // Diretório
            $destDir = __DIR__ . '/../assets/uploads/atendimentos/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            
            $fileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = $destDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $service->addAnexo([
                    'atendimento_id' => $atendimentoId,
                    'usuario_id' => $user['id'],
                    'arquivo' => 'assets/uploads/atendimentos/' . $fileName,
                    'descricao' => $descricao ?: $file['name'],
                    'extensao' => $ext,
                    'tamanho' => $file['size']
                ]);
                echo json_encode(['success' => true]);
            } else {
                throw new Exception('Erro ao mover arquivo para o servidor.');
            }
            break;

        case 'delete_anexo':
            $anexoId = (int)$_POST['anexo_id'];
            $st = $db->prepare("SELECT arquivo FROM gestao_atendimentos_anexos WHERE id = ?");
            $st->execute([$anexoId]);
            $anexo = $st->fetch();
            
            if ($anexo) {
                $filePath = __DIR__ . '/../' . $anexo['arquivo'];
                if (file_exists($filePath)) @unlink($filePath);
                $service->deleteAnexo($anexoId);
            }
            echo json_encode(['success' => true]);
            break;

        case 'search_users':
            $st = $db->prepare("SELECT u.id, u.name, u.photo, u.profile FROM users u JOIN user_institutions ui ON u.id = ui.user_id WHERE ui.institution_id = ? AND u.is_active = 1 AND u.name LIKE ? LIMIT 10");
            $st->execute([$instId, "%{$_GET['q']}%"]);
            echo json_encode(['success' => true, 'users' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'search_alunos':
            $q = trim($_GET['q'] ?? '');
            $term = "%$q%";
            $st = $db->prepare("
                SELECT DISTINCT a.id, a.nome, a.matricula
                FROM alunos a
                JOIN turma_alunos ta ON ta.aluno_id = a.id
                JOIN turmas t ON ta.turma_id = t.id
                JOIN courses c ON t.course_id = c.id
                WHERE c.institution_id = ? 
                  AND a.deleted_at IS NULL
                  AND (a.nome LIKE ? OR a.matricula LIKE ?)
                ORDER BY a.nome ASC
                LIMIT 15
            ");
            $st->execute([$instId, $term, $term]);
            echo json_encode(['success' => true, 'alunos' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'search_turmas':
            $q = trim($_GET['q'] ?? '');
            $term = "%$q%";
            $st = $db->prepare("
                SELECT t.id, t.description as nome, c.name as course_name
                FROM turmas t
                JOIN courses c ON t.course_id = c.id
                WHERE c.institution_id = ? 
                  AND t.is_active = 1
                  AND t.description LIKE ?
                ORDER BY t.description ASC
                LIMIT 10
            ");
            $st->execute([$instId, $term]);
            echo json_encode(['success' => true, 'turmas' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Ação inválida.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
