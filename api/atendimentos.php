<?php
/**
 * Vértice Acadêmico — API Gestão de Atendimentos
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = $inst['id'];
$db = getDB();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'fetch_board':
        try {
            $showArchived = ($_POST['show_archived'] ?? $_GET['show_archived'] ?? 'false') === 'true';

            // 1. Demandas (Encaminhamentos Pendentes)
            // Filtro: encaminhamentos do conselho pendentes
            $stDemandas = $db->prepare("
                SELECT e.id as encaminhamento_id, e.setor_tipo, e.data_expectativa, e.status as enc_status,
                       a.id as aluno_id, a.nome as aluno_nome, a.photo as aluno_photo,
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

            // Filtragem aplicada diretamente na query baseada no usuário logado e seu setor (perfil)


            $cardsDemandas = array_map(function($d) {
                return [
                    'id' => 'enc_' . $d['encaminhamento_id'], // Prefix to differentiate
                    'encaminhamento_id' => $d['encaminhamento_id'],
                    'aluno_id' => $d['aluno_id'],
                    'aluno_nome' => $d['aluno_nome'],
                    'aluno_photo' => $d['aluno_photo'],
                    'turma_id' => $d['turma_id'],
                    'turma_nome' => $d['turma_nome'],
                    'titulo' => 'Encaminhamento: ' . $d['setor_tipo'],
                    'status' => 'Demandas',
                    'data' => $d['data_expectativa'],
                    'is_encaminhamento' => true,
                    'responsaveis' => []
                ];
            }, $demandas);

            // 2. Atendimentos Em Andamento (Aberto, Em Atendimento, Finalizado)
            $stAtend = $db->prepare("
                SELECT at.*, 
                       a.nome as aluno_nome, a.photo as aluno_photo,
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

            // Get responsáveis parra cada atendimento
            $atendIds = array_column($atendimentos, 'id');
            $responsaveisMap = [];
            if (!empty($atendIds)) {
                $inQuery = implode(',', array_fill(0, count($atendIds), '?'));
                $stResp = $db->prepare("
                    SELECT au.atendimento_id, u.id, u.name, u.photo
                    FROM gestao_atendimento_usuarios au
                    JOIN users u ON au.usuario_id = u.id
                    WHERE au.atendimento_id IN ($inQuery)
                ");
                $stResp->execute($atendIds);
                foreach ($stResp->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $responsaveisMap[$r['atendimento_id']][] = [
                        'id' => $r['id'],
                        'name' => $r['name'],
                        'photo' => $r['photo']
                    ];
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
                    'is_archived' => (bool)$a['is_archived'],
                    'data' => $a['created_at'],
                    'is_encaminhamento' => false,
                    'responsaveis' => $responsaveisMap[$a['id']] ?? []
                ];
            }, $atendimentos);

            // Combinar e agrupar
            $allCards = array_merge($cardsDemandas, $cardsAtend);
            
            $board = [
                'Demandas' => [],
                'Aberto' => [],
                'Em Atendimento' => [],
                'Finalizado' => []
            ];

            foreach ($allCards as $card) {
                $s = $card['status'];
                if (isset($board[$s])) {
                    $board[$s][] = $card;
                }
            }

            echo json_encode(['success' => true, 'board' => $board]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'update_status':
        $cardId = $_POST['card_id'] ?? '';
        $newStatus = $_POST['new_status'] ?? '';
        
        $validStatuses = ['Aberto', 'Em Atendimento', 'Finalizado']; // Demandas is a source column

        try {
            $db->beginTransaction();

            if (strpos($cardId, 'enc_') === 0) {
                // Moving from Demandas (encaminhamento) to Atendimento
                if (!in_array($newStatus, $validStatuses)) {
                    throw new Exception('Status inválido para conversão.');
                }

                $encId = (int)str_replace('enc_', '', $cardId);
                
                // Get encaminhamento details
                $stE = $db->prepare("
                    SELECT e.*, c.turma_id 
                    FROM conselho_encaminhamentos e
                    JOIN conselhos_classe c ON e.conselho_id = c.id
                    WHERE e.id = ?
                ");
                $stE->execute([$encId]);
                $enc = $stE->fetch(PDO::FETCH_ASSOC);

                if (!$enc) throw new Exception('Encaminhamento não encontrado.');

                // Insert into gestao_atendimentos
                $stIns = $db->prepare("
                    INSERT INTO gestao_atendimentos (aluno_id, turma_id, encaminhamento_id, institution_id, status, titulo, author_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $titulo = 'Demanda: ' . $enc['setor_tipo'];
                $stIns->execute([
                    $enc['aluno_id'], 
                    $enc['turma_id'], 
                    $encId, 
                    $instId, 
                    $newStatus, 
                    $titulo, 
                    $user['id']
                ]);
                $newAtendId = $db->lastInsertId();

                // Add current user as responsible automatically
                $db->prepare("INSERT INTO gestao_atendimento_usuarios (atendimento_id, usuario_id) VALUES (?, ?)")
                   ->execute([$newAtendId, $user['id']]);

                // Update encaminhamento status
                $db->prepare("UPDATE conselho_encaminhamentos SET status = 'Em Andamento' WHERE id = ?")
                   ->execute([$encId]);

                $cardId = $newAtendId; // Return new ID

            } else {
                // Updating existing atendimento
                $atendId = (int)$cardId;
                if (!in_array($newStatus, $validStatuses)) {
                    throw new Exception('Status inválido.');
                }
                
                $db->prepare("UPDATE gestao_atendimentos SET status = ? WHERE id = ? AND institution_id = ?")
                   ->execute([$newStatus, $atendId, $instId]);

                // Handle completion of linked encaminhamento
                if ($newStatus === 'Finalizado') {
                    $db->prepare("
                        UPDATE conselho_encaminhamentos 
                        SET status = 'Concluído' 
                        WHERE id = (SELECT encaminhamento_id FROM gestao_atendimentos WHERE id = ?)
                    ")->execute([$atendId]);
                }
            }

            $db->commit();
            echo json_encode(['success' => true, 'new_id' => $cardId]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'create_atendimento':
        $alunoId = !empty($_POST['aluno_id']) ? (int)$_POST['aluno_id'] : null;
        $turmaId = !empty($_POST['turma_id']) ? (int)$_POST['turma_id'] : null;
        $titulo = trim($_POST['titulo'] ?? '');
        
        if (empty($titulo)) {
            echo json_encode(['success' => false, 'error' => 'Título é obrigatório.']);
            exit;
        }

        try {
            $db->beginTransaction();
            $st = $db->prepare("
                INSERT INTO gestao_atendimentos (aluno_id, turma_id, institution_id, status, titulo, author_id)
                VALUES (?, ?, ?, 'Aberto', ?, ?)
            ");
            $st->execute([$alunoId, $turmaId, $instId, $titulo, $user['id']]);
            $newId = $db->lastInsertId();

            // Default responsibility to creator
            $db->prepare("INSERT INTO gestao_atendimento_usuarios (atendimento_id, usuario_id) VALUES (?, ?)")
               ->execute([$newId, $user['id']]);

            $db->commit();
            echo json_encode(['success' => true, 'new_id' => $newId]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_details':
        $rawId = $_GET['id'] ?? '';
        if (!$rawId) {
            echo json_encode(['success' => false, 'error' => 'ID inválido.']);
            exit;
        }

        try {
            if (strpos($rawId, 'enc_') === 0) {
                $encId = (int)str_replace('enc_', '', $rawId);
                // Get Encaminhamento Details
                $st = $db->prepare("
                    SELECT e.*, 
                           a.nome as aluno_nome, a.matricula, a.photo as aluno_photo,
                           t.description as turma_nome,
                           co.name as curso_nome,
                           c.descricao as conselho_nome, c.data_hora as conselho_data
                    FROM conselho_encaminhamentos e
                    JOIN alunos a ON e.aluno_id = a.id
                    JOIN conselhos_classe c ON e.conselho_id = c.id
                    JOIN turmas t ON c.turma_id = t.id
                    JOIN courses co ON t.course_id = co.id
                    WHERE e.id = ? AND co.institution_id = ?
                ");
                $st->execute([$encId, $instId]);
                $enc = $st->fetch(PDO::FETCH_ASSOC);

                if (!$enc) throw new Exception('Demanda não encontrada.');

                // Mapping for frontend
                $enc['titulo'] = 'Demanda: ' . $enc['setor_tipo'];
                $enc['descricao_profissional'] = $enc['texto'];
                $enc['is_encaminhamento_pure'] = true;
                $enc['status'] = 'Pendente';

                echo json_encode([
                    'success' => true, 
                    'atendimento' => $enc, 
                    'responsaveis' => [], 
                    'comentarios' => []
                ]);
                exit;
            }

            $atendId = (int)$rawId;
            $st = $db->prepare("
                SELECT at.*, 
                       a.nome as aluno_nome, a.matricula, a.photo as aluno_photo,
                       t.description as turma_nome,
                       co.name as curso_nome
                FROM gestao_atendimentos at
                LEFT JOIN alunos a ON at.aluno_id = a.id
                LEFT JOIN turmas t ON at.turma_id = t.id
                LEFT JOIN courses co ON t.course_id = co.id
                WHERE at.id = ? AND at.institution_id = ? AND at.deleted_at IS NULL
            ");
            $st->execute([$atendId, $instId]);
            $atend = $st->fetch(PDO::FETCH_ASSOC);

            if (!$atend) throw new Exception('Atendimento não encontrado.');

            // Limpa as tags HTML do Quill Editor para que não apareçam nos textareas
            $atend['descricao_publica'] = strip_tags(html_entity_decode(str_replace(['<br>', '<br/>', '</p>'], "\n", (string)$atend['descricao_publica'])));
            $atend['descricao_profissional'] = strip_tags(html_entity_decode(str_replace(['<br>', '<br/>', '</p>'], "\n", (string)$atend['descricao_profissional'])));

            // Get Responsibles
            $stR = $db->prepare("
                SELECT u.id, u.name, u.photo, u.profile
                FROM gestao_atendimento_usuarios au
                JOIN users u ON au.usuario_id = u.id
                WHERE au.atendimento_id = ?
            ");
            $stR->execute([$atendId]);
            $responsaveis = $stR->fetchAll(PDO::FETCH_ASSOC);

            // Get Comments
            $stC = $db->prepare("
                SELECT ac.id, ac.texto, ac.is_private, ac.created_at,
                       u.name as author_name, u.photo as author_photo, u.profile as author_profile, u.id as author_id
                FROM gestao_atendimento_comentarios ac
                JOIN users u ON ac.usuario_id = u.id
                WHERE ac.atendimento_id = ?
                ORDER BY ac.created_at DESC
            ");
            $stC->execute([$atendId]);
            $comentarios = $stC->fetchAll(PDO::FETCH_ASSOC);

            // Filter out private comments if user is not a teacher/admin/etc? Let's assume professionals see them all.
            // But perhaps restrict slightly if needed. For now, all logged in professionals see private.

            echo json_encode(['success' => true, 'atendimento' => $atend, 'responsaveis' => $responsaveis, 'comentarios' => $comentarios]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'archive_atendimento':
        $atendId = (int)($_POST['atendimento_id'] ?? 0);
        $archive = (int)($_POST['archive'] ?? 1); // 1 para arquivar, 0 para desarquivar

        if (!$atendId) {
            echo json_encode(['success' => false, 'error' => 'ID inválido.']);
            exit;
        }

        try {
            // Verifica permissão (Autor, Admin, Coordenador, Pedagogo)
            $stCheck = $db->prepare("SELECT author_id FROM gestao_atendimentos WHERE id = ? AND institution_id = ?");
            $stCheck->execute([$atendId, $instId]);
            $owner = $stCheck->fetch();

            if (!$owner) {
                echo json_encode(['success' => false, 'error' => 'Atendimento não encontrado.']);
                exit;
            }

            $allowed = ['Administrador', 'Coordenador', 'Pedagogo'];
            if ($owner['author_id'] != $user['id'] && !in_array($user['profile'], $allowed)) {
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para arquivar este atendimento.']);
                exit;
            }

            $db->prepare("UPDATE gestao_atendimentos SET is_archived = ? WHERE id = ?")
               ->execute([$archive, $atendId]);

            echo json_encode(['success' => true, 'message' => $archive ? 'Atendimento arquivado.' : 'Atendimento desarquivado.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_info':
        $atendId = (int)($_POST['atendimento_id'] ?? 0);
        $descPublica = trim($_POST['descricao_publica'] ?? '');
        $descProfissional = trim($_POST['descricao_profissional'] ?? '');

        try {
            $db->prepare("
                UPDATE gestao_atendimentos 
                SET descricao_publica = ?, descricao_profissional = ? 
                WHERE id = ? AND institution_id = ?
            ")->execute([$descPublica, $descProfissional, $atendId, $instId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_comment':
        $atendId = (int)($_POST['atendimento_id'] ?? 0);
        $texto = trim($_POST['texto'] ?? '');
        $isPrivate = (int)($_POST['is_private'] ?? 0);

        if (empty($texto)) {
            echo json_encode(['success' => false, 'error' => 'O comentário não pode estar vazio.']);
            exit;
        }

        try {
            $db->prepare("
                INSERT INTO gestao_atendimento_comentarios (atendimento_id, usuario_id, texto, is_private)
                VALUES (?, ?, ?, ?)
            ")->execute([$atendId, $user['id'], $texto, $isPrivate]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'add_responsible':
        $atendId = (int)($_POST['atendimento_id'] ?? 0);
        $usuarioId = (int)($_POST['usuario_id'] ?? 0);

        try {
            $db->prepare("INSERT IGNORE INTO gestao_atendimento_usuarios (atendimento_id, usuario_id) VALUES (?, ?)")
               ->execute([$atendId, $usuarioId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_atendimento':
        $rawId = $_POST['atendimento_id'] ?? '';
        if (!$rawId) {
            echo json_encode(['success' => false, 'error' => 'ID inválido.']);
            exit;
        }

        try {
            if (strpos($rawId, 'enc_') === 0) {
                // Deleting a "pure" demand (encaminhamento)
                $encId = (int)str_replace('enc_', '', $rawId);
                // We could soft-delete if it had a column, but since it doesn't, we'll mark as 'Cancelado' or just delete?
                // Let's check if there's a 'Cancelado' status. 
                // Actually, I'll just hard-delete it if the user wants to "remove the card".
                // Or better: update status to 'Excluido' if possible.
                // Looking at the schema, it has a 'status' VARCHAR.
                $db->prepare("DELETE FROM conselho_encaminhamentos WHERE id = ?")
                   ->execute([$encId]);
            } else {
                // Deleting an active Atendimento
                $atendId = (int)$rawId;
                // Check if it has a linked encaminhamento to revert its status
                $stCheck = $db->prepare("SELECT encaminhamento_id FROM gestao_atendimentos WHERE id = ? AND institution_id = ?");
                $stCheck->execute([$atendId, $instId]);
                $at = $stCheck->fetch(PDO::FETCH_ASSOC);

                if ($at && $at['encaminhamento_id']) {
                    $db->prepare("UPDATE conselho_encaminhamentos SET status = 'Pendente' WHERE id = ?")
                       ->execute([$at['encaminhamento_id']]);
                }

                $db->prepare("UPDATE gestao_atendimentos SET deleted_at = NOW() WHERE id = ? AND institution_id = ?")
                   ->execute([$atendId, $instId]);
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'remove_responsible':
        $atendId = (int)($_POST['atendimento_id'] ?? 0);
        $usuarioId = (int)($_POST['usuario_id'] ?? 0);

        try {
            $db->prepare("DELETE FROM gestao_atendimento_usuarios WHERE atendimento_id = ? AND usuario_id = ?")
               ->execute([$atendId, $usuarioId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'search_users':
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 3) {
            echo json_encode(['success' => true, 'users' => []]);
            exit;
        }
        $st = $db->prepare("
            SELECT u.id, u.name, u.photo, u.profile 
            FROM users u
            JOIN user_institutions ui ON u.id = ui.user_id
            WHERE ui.institution_id = ? AND u.is_active = 1 AND u.name LIKE ?
            LIMIT 10
        ");
        $st->execute([$instId, "%$q%"]);
        echo json_encode(['success' => true, 'users' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'search_alunos':
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 3) {
            echo json_encode(['success' => true, 'alunos' => []]);
            exit;
        }
        $st = $db->prepare("
            SELECT DISTINCT a.id, a.nome, a.matricula
            FROM alunos a
            JOIN turma_alunos ta ON a.id = ta.aluno_id
            JOIN turmas t ON ta.turma_id = t.id
            JOIN courses c ON t.course_id = c.id
            WHERE c.institution_id = ? 
              AND a.deleted_at IS NULL
              AND (a.nome LIKE ? OR a.matricula LIKE ?)
            LIMIT 10
        ");
        $like = "%$q%";
        $st->execute([$instId, $like, $like]);
        echo json_encode(['success' => true, 'alunos' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'search_turmas':
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 3) {
            echo json_encode(['success' => true, 'turmas' => []]);
            exit;
        }
        $st = $db->prepare("
            SELECT t.id, t.description as nome, c.name as course_name
            FROM turmas t
            JOIN courses c ON t.course_id = c.id
            WHERE c.institution_id = ? AND t.is_active = 1 AND c.is_active = 1 AND (t.description LIKE ? OR c.name LIKE ?)
            LIMIT 10
        ");
        $like = "%$q%";
        $st->execute([$instId, $like, $like]);
        echo json_encode(['success' => true, 'turmas' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'fetch_anexos':
        $atendId = (int)($_GET['atendimento_id'] ?? 0);
        if (!$atendId) {
            echo json_encode(['success' => false, 'error' => 'ID de atendimento inválido.']);
            exit;
        }

        try {
            $st = $db->prepare("
                SELECT a.*, u.name as author_name 
                FROM gestao_atendimentos_anexos a
                JOIN users u ON a.usuario_id = u.id
                WHERE a.atendimento_id = ?
                ORDER BY a.created_at DESC
            ");
            $st->execute([$atendId]);
            $anexos = $st->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'anexos' => $anexos]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload_anexo':
        $atendId = (int)($_POST['atendimento_id'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');
        
        if (!$atendId) {
            echo json_encode(['success' => false, 'error' => 'ID de atendimento inválido.']);
            exit;
        }

        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Erro no envio do arquivo.']);
            exit;
        }

        $file = $_FILES['arquivo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Extensão não permitida (Apenas PDF e Imagens).']);
            exit;
        }

        // Limite de 10MB
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Arquivo muito grande (Máximo 10MB).']);
            exit;
        }

        $uploadDir = __DIR__ . '/../assets/uploads/atendimentos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $newName = uniqid('atend_' . $atendId . '_', true) . '.' . $ext;
        $destPath = $uploadDir . $newName;
        $dbPath = 'assets/uploads/atendimentos/' . $newName;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            try {
                $st = $db->prepare("
                    INSERT INTO gestao_atendimentos_anexos (atendimento_id, usuario_id, arquivo, descricao, extensao, tamanho)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $st->execute([$atendId, $user['id'], $dbPath, $descricao, $ext, $file['size']]);
                echo json_encode(['success' => true, 'message' => 'Arquivo enviado com sucesso.']);
            } catch (Exception $e) {
                @unlink($destPath); // Remove arquivo se falhar o banco
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar no banco: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao mover o arquivo para o servidor.']);
        }
        break;

    case 'delete_anexo':
        $anexoId = (int)($_POST['anexo_id'] ?? 0);
        
        try {
            // Pegar caminho do arquivo antes de deletar
            $st = $db->prepare("SELECT arquivo FROM gestao_atendimentos_anexos WHERE id = ?");
            $st->execute([$anexoId]);
            $anexo = $st->fetch(PDO::FETCH_ASSOC);

            if ($anexo) {
                $filePath = __DIR__ . '/../' . $anexo['arquivo'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }

                $db->prepare("DELETE FROM gestao_atendimentos_anexos WHERE id = ?")->execute([$anexoId]);
                echo json_encode(['success' => true, 'message' => 'Anexo removido.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Anexo não encontrado.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Ação inválida.']);
        break;
}
