<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Validations
hasDbPermission('sancoes.index');

$action = $_GET['action'] ?? '';
$db = getDB();
$inst = getCurrentInstitution();
$instId = (int)$inst['id'];
$user = getCurrentUser();

header('Content-Type: application/json');

if ($action === 'search_aluno') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 3) {
        echo json_encode([]);
        exit;
    }

    $st = $db->prepare("
        SELECT a.id, a.nome, a.matricula, a.photo as foto,
               t.id as turma_id, t.description as turma_desc,
               c.name as curso_nome
        FROM alunos a
        JOIN turma_alunos ta ON ta.aluno_id = a.id
        JOIN turmas t ON ta.turma_id = t.id
        JOIN courses c ON t.course_id = c.id
        WHERE c.institution_id = ?
          AND (a.nome LIKE ? OR a.matricula LIKE ?)
        LIMIT 10
    ");
    $term = "%$q%";
    $st->execute([$instId, $term, $term]);
    
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'get_dependencies') {
    $tipos = $db->prepare("SELECT id, titulo, descricao FROM sancao_tipo WHERE institution_id = ? AND is_active = 1 ORDER BY titulo");
    $tipos->execute([$instId]);
    
    $acoes = $db->prepare("SELECT id, descricao FROM sancao_acao WHERE institution_id = ? AND is_active = 1 ORDER BY id");
    $acoes->execute([$instId]);
    
    echo json_encode([
        'tipos' => $tipos->fetchAll(PDO::FETCH_ASSOC),
        'acoes' => $acoes->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;
}

if ($action === 'list') {
    $alunoTerm = trim($_GET['aluno'] ?? '');
    $statusTerm = trim($_GET['status'] ?? '');
    
    $sql = "
        SELECT s.id, s.data_sancao, s.status, a.nome as aluno_nome, a.matricula, a.photo as aluno_foto, a.id as aluno_id,
               t.description as turma_desc, st.titulo as tipo_titulo
        FROM sancao s
        JOIN alunos a ON s.aluno_id = a.id
        JOIN turmas t ON s.turma_id = t.id
        JOIN sancao_tipo st ON s.sancao_tipo_id = st.id
        WHERE s.institution_id = ?
    ";
    $params = [$instId];
    
    if ($alunoTerm) {
        $sql .= " AND (a.nome LIKE ? OR a.matricula LIKE ?)";
        $params[] = "%$alunoTerm%";
        $params[] = "%$alunoTerm%";
    }
    if ($statusTerm) {
        $sql .= " AND s.status = ?";
        $params[] = $statusTerm;
    }
    
    $sql .= " ORDER BY s.data_sancao DESC, s.id DESC";
    
    try {
        $st = $db->prepare($sql);
        $st->execute($params);
        echo json_encode(['status' => 'success', 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro DB']);
    }
    exit;
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    $st = $db->prepare("
        SELECT s.*, 
               a.nome as aluno_nome, a.matricula, a.photo as aluno_foto,
               t.description as turma_desc,
               c.name as curso_nome,
               st.titulo as tipo_titulo,
               u.name as author_name
        FROM sancao s
        JOIN alunos a ON s.aluno_id = a.id
        JOIN turmas t ON s.turma_id = t.id
        JOIN courses c ON t.course_id = c.id
        JOIN sancao_tipo st ON s.sancao_tipo_id = st.id
        JOIN users u ON s.author_id = u.id
        WHERE s.id = ? AND s.institution_id = ?
    ");
    $st->execute([$id, $instId]);
    $sancao = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$sancao) {
        echo json_encode(['status' => 'error', 'message' => 'Sanção não encontrada']);
        exit;
    }
    
    $stAcoes = $db->prepare("SELECT sancao_acao_id FROM sancao_acoes_rel WHERE sancao_id = ?");
    $stAcoes->execute([$id]);
    $sancao['acoes_rel'] = $stAcoes->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode(['status' => 'success', 'data' => $sancao]);
    exit;
}

if ($action === 'get_history') {
    $alunoId = (int)($_GET['aluno_id'] ?? 0);
    $excludeId = (int)($_GET['exclude_id'] ?? 0);
    
    if (!$alunoId) {
        echo json_encode(['status' => 'error', 'message' => 'Aluno não especificado']);
        exit;
    }
    
    $sql = "
        SELECT s.id, s.data_sancao, s.status, s.observacoes, st.titulo as tipo_titulo
        FROM sancao s
        JOIN sancao_tipo st ON s.sancao_tipo_id = st.id
        WHERE s.aluno_id = ? AND s.institution_id = ?
    ";
    $params = [$alunoId, $instId];
    
    if ($excludeId > 0) {
        $sql .= " AND s.id != ?";
        $params[] = $excludeId;
    }
    
    $sql .= " ORDER BY s.data_sancao DESC, s.id DESC";
    
    try {
        $st = $db->prepare($sql);
        $st->execute($params);
        echo json_encode(['status' => 'success', 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao carregar histórico']);
    }
    exit;
}

if ($action === 'finish') {
    hasDbPermission('sancoes.manage');
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'ID não fornecido.']);
        exit;
    }
    
    try {
        $st = $db->prepare("UPDATE sancao SET status = 'Concluído', data_conclusao = CURRENT_DATE WHERE id = ? AND institution_id = ?");
        $st->execute([$id, $instId]);
        echo json_encode(['status' => 'success', 'message' => 'Sanção finalizada com sucesso!']);
    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao finalizar sanção.']);
    }
    exit;
}

if ($action === 'fetch_anexos') {
    $sancao_id = (int)($_GET['sancao_id'] ?? 0);
    $st = $db->prepare("
        SELECT sa.*, u.name as author_name 
        FROM sancao_anexos sa
        JOIN users u ON sa.usuario_id = u.id
        WHERE sa.sancao_id = ?
        ORDER BY sa.created_at DESC
    ");
    $st->execute([$sancao_id]);
    echo json_encode(['success' => true, 'anexos' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'upload_anexo') {
    hasDbPermission('sancoes.manage');
    $sancao_id = (int)($_POST['sancao_id'] ?? 0);
    $descricao = $_POST['descricao'] ?? '';
    
    if (!$sancao_id || !isset($_FILES['arquivo'])) {
        echo json_encode(['success' => false, 'error' => 'Dados incompletos.']);
        exit;
    }

    $file = $_FILES['arquivo'];
    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt)) {
        echo json_encode(['success' => false, 'error' => 'Extensão não permitida.']);
        exit;
    }

    $dir = __DIR__ . '/../assets/uploads/sancoes';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    $filename = uniqid('sancao_file_' . $sancao_id . '_') . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        $path = 'assets/uploads/sancoes/' . $filename;
        $st = $db->prepare("INSERT INTO sancao_anexos (sancao_id, usuario_id, arquivo, descricao, extensao, tamanho) VALUES (?, ?, ?, ?, ?, ?)");
        $st->execute([$sancao_id, $user['id'], $path, $descricao, $ext, $file['size']]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Falha ao mover arquivo.']);
    }
    exit;
}

if ($action === 'delete_anexo') {
    hasDbPermission('sancoes.manage');
    $anexo_id = (int)($_POST['anexo_id'] ?? 0);
    
    $st = $db->prepare("SELECT arquivo FROM sancao_anexos WHERE id = ?");
    $st->execute([$anexo_id]);
    $arquivo = $st->fetchColumn();
    
    if ($arquivo) {
        $fullPath = __DIR__ . '/../' . $arquivo;
        if (file_exists($fullPath)) unlink($fullPath);
        
        $db->prepare("DELETE FROM sancao_anexos WHERE id = ?")->execute([$anexo_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Anexo não encontrado.']);
    }
    exit;
}

if ($action === 'delete') {
    hasDbPermission('sancoes.manage');
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'ID não fornecido.']);
        exit;
    }
    
    try {
        $db->beginTransaction();
        
        // Remove related actions
        $db->prepare("DELETE FROM sancao_acoes_rel WHERE sancao_id = ?")->execute([$id]);
        
        // Remove sanction
        $st = $db->prepare("DELETE FROM sancao WHERE id = ? AND institution_id = ?");
        $st->execute([$id, $instId]);
        
        $db->commit();
        echo json_encode(['status' => 'success', 'message' => 'Sanção excluída com sucesso!']);
    } catch(Exception $e) {
        $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Erro ao excluir sanção.']);
    }
    exit;
}

if ($action === 'save') {
    hasDbPermission('sancoes.manage');
    
    $id = (int)($_POST['sancao_id'] ?? 0);
    $aluno_id = (int)($_POST['aluno_id'] ?? 0);
    $data_sancao = $_POST['data_sancao'] ?? '';
    $sancao_tipo_id = (int)($_POST['sancao_tipo_id'] ?? 0);
    $observacoes = $_POST['observacoes'] ?? null;
    $status = $_POST['status'] ?? 'Em aberto';
    $acoes = $_POST['acoes'] ?? [];
    
    if (!$aluno_id || !$data_sancao || !$sancao_tipo_id) {
        echo json_encode(['status' => 'error', 'message' => 'Preencha os campos obrigatórios.']);
        exit;
    }
    
    // Validate aluno and get turma_id latest
    $stT = $db->prepare("
        SELECT ta.turma_id 
        FROM turma_alunos ta 
        JOIN turmas t ON ta.turma_id=t.id 
        JOIN courses c ON t.course_id=c.id 
        WHERE ta.aluno_id=? AND c.institution_id=? 
        ORDER BY t.ano DESC LIMIT 1
    ");
    $stT->execute([$aluno_id, $instId]);
    $turmaRef = $stT->fetchColumn();
    if (!$turmaRef) {
        echo json_encode(['status' => 'error', 'message' => 'Aluno não possui turma vinculada.']);
        exit;
    }
    
    // Old single-file logic removed. Attachments are now handled separately.

    try {
        $db->beginTransaction();
        
        if ($id > 0) {
            // Update
            $sql = "UPDATE sancao SET sancao_tipo_id=?, data_sancao=?, observacoes=?, status=?";
            $params = [$sancao_tipo_id, $data_sancao, $observacoes, $status];
            
            if ($status === 'Concluído') {
                $sql .= ", data_conclusao=CURRENT_DATE";
            }
            $sql .= " WHERE id=? AND institution_id=?";
            $params[] = $id;
            $params[] = $instId;
            
            $st = $db->prepare($sql);
            $st->execute($params);
            
            // Delete old rels
            $db->prepare("DELETE FROM sancao_acoes_rel WHERE sancao_id=?")->execute([$id]);
        } else {
            // Insert
            $sql = "INSERT INTO sancao (institution_id, author_id, aluno_id, turma_id, sancao_tipo_id, data_sancao, observacoes, status";
            $params = [$instId, $user['id'], $aluno_id, $turmaRef, $sancao_tipo_id, $data_sancao, $observacoes, $status];
            
            if ($status === 'Concluído') {
                $sql .= ", data_conclusao";
            }
            $sql .= ") VALUES (?, ?, ?, ?, ?, ?, ?, ?";
            
            if ($status === 'Concluído') {
                $sql .= ", CURRENT_DATE";
            }
            $sql .= ")";
            
            $st = $db->prepare($sql);
            $st->execute($params);
            $id = $db->lastInsertId();
        }
        
        // Insert acoes
        if (!empty($acoes) && is_array($acoes)) {
            $stInsAcoes = $db->prepare("INSERT INTO sancao_acoes_rel (sancao_id, sancao_acao_id) VALUES (?, ?)");
            foreach ($acoes as $acao_id) {
                $stInsAcoes->execute([$id, (int)$acao_id]);
            }
        }
        
        $db->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Sanção registrada com sucesso!',
            'id' => $id
        ]);
        
    } catch(Exception $e) {
        $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Erro interno ao salvar.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Rota inválida.']);
