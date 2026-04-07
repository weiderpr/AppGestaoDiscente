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
        echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar histórico']);
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
    
    // File Upload handling (Anexo)
    $anexoPath = null;
    if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['anexo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExt)) {
            $dir = __DIR__ . '/../assets/uploads/sancoes';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $filename = uniqid('sancao_' . $aluno_id . '_') . '.' . $ext;
            if (move_uploaded_file($_FILES['anexo']['tmp_name'], $dir . '/' . $filename)) {
                $anexoPath = 'assets/uploads/sancoes/' . $filename;
                
                // Triggers status to Concluído if it was Open
                if ($status === 'Em aberto') {
                    $status = 'Concluído';
                }
            }
        }
    }

    try {
        $db->beginTransaction();
        
        if ($id > 0) {
            // Update
            $sql = "UPDATE sancao SET sancao_tipo_id=?, data_sancao=?, observacoes=?, status=?";
            $params = [$sancao_tipo_id, $data_sancao, $observacoes, $status];
            
            if ($status === 'Concluído') {
                $sql .= ", data_conclusao=CURRENT_DATE";
            }
            if ($anexoPath) {
                $sql .= ", anexo_path=?";
                $params[] = $anexoPath;
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
            if ($anexoPath) {
                $sql .= ", anexo_path";
            }
            $sql .= ") VALUES (?, ?, ?, ?, ?, ?, ?, ?";
            
            if ($status === 'Concluído') {
                $sql .= ", CURRENT_DATE";
            }
            if ($anexoPath) {
                $sql .= ", ?";
                $params[] = $anexoPath;
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
