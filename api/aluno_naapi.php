<?php
/**
 * Vértice Acadêmico — Aluno NAAPI API
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$inst = getCurrentInstitution();
$instId = (int)$inst['id'];
$user = getCurrentUser();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? ($method === 'POST' ? 'save' : 'get');

// Ações permitidas gerenciadas via Matriz de Controle de Acesso (RBAC)
// Administrador possui acesso total por padrão (hasDbPermission lida com isso)

switch ($action) {
    case 'get':
        hasDbPermission('naapi.index'); 
        $alunoId = (int)($_GET['aluno_id'] ?? 0);
        if (!$alunoId) {
            echo json_encode(['error' => 'ID do aluno não fornecido.']);
            exit;
        }

        try {
            $stmt = $db->prepare("SELECT * FROM alunos_naapi WHERE aluno_id = ? AND institution_id = ?");
            $stmt->execute([$alunoId, $instId]);
            $data = $stmt->fetch();
            
            echo json_encode($data ?: ['exists' => false]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao buscar dados do NAAPI.']);
        }
        break;

    case 'save':
        hasDbPermission('naapi.manage'); 

        $alunoId = (int)($_POST['aluno_id'] ?? 0);
        $dataInclusao = $_POST['data_inclusao'] ?? '';
        $neurodivergencia = $_POST['neurodivergencia'] ?? '';
        $campoTexto = $_POST['campo_texto'] ?? '';
        $obsPublicas = $_POST['observacoes_publicas'] ?? '';

        if (!$alunoId || !$dataInclusao) {
            echo json_encode(['error' => 'Preencha a data de inclusão.']);
            exit;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO alunos_naapi (aluno_id, institution_id, data_inclusao, neurodivergencia, campo_texto, observacoes_publicas)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    data_inclusao = VALUES(data_inclusao),
                    neurodivergencia = VALUES(neurodivergencia),
                    campo_texto = VALUES(campo_texto),
                    observacoes_publicas = VALUES(observacoes_publicas)
            ");
            $stmt->execute([$alunoId, $instId, $dataInclusao, $neurodivergencia, $campoTexto, $obsPublicas]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao salvar: ' . $e->getMessage()]);
        }
        break;

    case 'fetch_anexos':
        hasDbPermission('naapi.index'); 
        $alunoId = (int)($_GET['aluno_id'] ?? 0);
        if (!$alunoId) {
            echo json_encode(['error' => 'ID inválido.']);
            exit;
        }

        try {
            $st = $db->prepare("
                SELECT a.*, u.name as author_name 
                FROM alunos_naapi_anexos a
                JOIN users u ON a.usuario_id = u.id
                WHERE a.aluno_id = ?
                ORDER BY a.created_at DESC
            ");
            $st->execute([$alunoId]);
            $anexos = $st->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'anexos' => $anexos]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'upload_anexo':
        hasDbPermission('naapi.manage'); 

        $alunoId = (int)($_POST['aluno_id'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');
        
        if (!$alunoId) {
            echo json_encode(['error' => 'ID inválido.']);
            exit;
        }

        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'Erro no envio do arquivo.']);
            exit;
        }

        $file = $_FILES['arquivo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($ext, $allowed)) {
            echo json_encode(['error' => 'Extensão não permitida (PDF e Imagens apenas).']);
            exit;
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['error' => 'Arquivo muito grande (Máximo 10MB).']);
            exit;
        }

        $uploadDir = __DIR__ . '/../assets/uploads/naapi/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $newName = uniqid('naapi_' . $alunoId . '_', true) . '.' . $ext;
        $destPath = $uploadDir . $newName;
        $dbPath = 'assets/uploads/naapi/' . $newName;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            try {
                $st = $db->prepare("
                    INSERT INTO alunos_naapi_anexos (aluno_id, usuario_id, arquivo, descricao, extensao, tamanho)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $st->execute([$alunoId, $user['id'], $dbPath, $descricao, $ext, $file['size']]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                @unlink($destPath);
                echo json_encode(['error' => 'Erro no banco: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['error' => 'Erro ao mover o arquivo.']);
        }
        break;

    case 'delete_anexo':
        hasDbPermission('naapi.manage'); 

        $anexoId = (int)($_POST['anexo_id'] ?? 0);
        
        try {
            $st = $db->prepare("SELECT arquivo FROM alunos_naapi_anexos WHERE id = ?");
            $st->execute([$anexoId]);
            $anexo = $st->fetch(PDO::FETCH_ASSOC);

            if ($anexo) {
                $filePath = __DIR__ . '/../' . $anexo['arquivo'];
                if (file_exists($filePath)) @unlink($filePath);

                $db->prepare("DELETE FROM alunos_naapi_anexos WHERE id = ?")->execute([$anexoId]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Anexo não encontrado.']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'delete_naapi':
        hasDbPermission('naapi.manage'); 

        $alunoId = (int)($_POST['aluno_id'] ?? 0);
        if (!$alunoId) {
            echo json_encode(['error' => 'ID inválido.']);
            exit;
        }

        try {
            $db->beginTransaction();

            // Buscar e remover arquivos físicos dos anexos
            $st = $db->prepare("SELECT arquivo FROM alunos_naapi_anexos WHERE aluno_id = ?");
            $st->execute([$alunoId]);
            $anexos = $st->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($anexos as $anexo) {
                $filePath = __DIR__ . '/../' . $anexo['arquivo'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            // Remover registros dos anexos
            $stDelAnexos = $db->prepare("DELETE FROM alunos_naapi_anexos WHERE aluno_id = ?");
            $stDelAnexos->execute([$alunoId]);

            // Remover o registro principal do NAAPI
            $stDelMain = $db->prepare("DELETE FROM alunos_naapi WHERE aluno_id = ? AND institution_id = ?");
            $stDelMain->execute([$alunoId, $instId]);

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao excluir registro: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Ação inválida.']);
        break;
}
