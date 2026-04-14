<?php
/**
 * Vértice Acadêmico — Aluno NAAPI API (Service-Oriented)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/NaapiService.php';

requireLogin();

header('Content-Type: application/json');

$inst = getCurrentInstitution();
$instId = (int)$inst['id'];
$user = getCurrentUser();

$service = new \App\Services\NaapiService();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? ($method === 'POST' ? 'save' : 'get');

try {
    switch ($action) {
        case 'get':
            hasDbPermission('naapi.index'); 
            $alunoId = (int)($_GET['aluno_id'] ?? 0);
            if (!$alunoId) throw new Exception('ID do aluno não fornecido.');
            
            $data = $service->getByAlunoId($alunoId);
            echo json_encode($data ?: ['exists' => false]);
            break;

        case 'save':
            hasDbPermission('naapi.manage'); 
            $alunoId = (int)($_POST['aluno_id'] ?? 0);
            if (!$alunoId) throw new Exception('ID do aluno inválido.');

            $data = [
                'aluno_id' => $alunoId,
                'institution_id' => $instId,
                'data_inclusao' => $_POST['data_inclusao'] ?? date('Y-m-d'),
                'neurodivergencia' => $_POST['neurodivergencia'] ?? '',
                'campo_texto' => $_POST['campo_texto'] ?? '',
                'observacoes_publicas' => $_POST['observacoes_publicas'] ?? ''
            ];

            if ($service->exists($alunoId)) {
                $existing = $service->getByAlunoId($alunoId);
                $service->update((int)$existing['id'], $data);
            } else {
                $service->add($data);
            }
            
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            hasDbPermission('naapi.manage');
            $alunoId = (int)($_POST['aluno_id'] ?? 0);
            if (!$alunoId) throw new Exception('ID inválido.');
            
            $existing = $service->getByAlunoId($alunoId);
            if ($existing) {
                $service->delete((int)$existing['id']);
            }
            echo json_encode(['success' => true]);
            break;

        case 'fetch_anexos':
            hasDbPermission('naapi.index'); 
            $alunoId = (int)($_GET['aluno_id'] ?? 0);
            $anexos = $service->getAnexos($alunoId);
            echo json_encode(['success' => true, 'anexos' => $anexos]);
            break;

        case 'upload_anexo':
            hasDbPermission('naapi.manage');
            $alunoId = (int)($_POST['aluno_id'] ?? 0);
            if (empty($_FILES['file']['tmp_name'])) throw new Exception("Nenhum arquivo enviado.");

            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'txt'];
            
            if (!in_array($ext, $allowed)) throw new Exception("Tipo de arquivo não permitido.");

            $uploadDir = __DIR__ . '/../assets/uploads/naapi/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileName = uniqid('naapi_', true) . '.' . $ext;
            $filePath = 'assets/uploads/naapi/' . $fileName;

            if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                $service->addAnexo([
                    'aluno_id' => $alunoId,
                    'usuario_id' => $user['id'],
                    'nome_arquivo' => $file['name'],
                    'caminho_arquivo' => $filePath,
                    'tipo_arquivo' => $file['type'],
                    'tamanho_bytes' => $file['size']
                ]);
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Erro ao mover o arquivo para o servidor.");
            }
            break;

        case 'delete_anexo':
            hasDbPermission('naapi.manage');
            $id = (int)($_POST['id'] ?? 0);
            $service->deleteAnexo($id);
            echo json_encode(['success' => true]);
            break;

        case 'fetch_ocorrencias':
            hasDbPermission('naapi.index'); 
            $alunoId = (int)($_GET['aluno_id'] ?? 0);
            $ocorrencias = $service->getOcorrencias($alunoId, $instId, $user['id'], $user['profile']);
            echo json_encode(['success' => true, 'ocorrencias' => $ocorrencias]);
            break;

        case 'save_ocorrencia':
            hasDbPermission('naapi.manage');
            $id = (int)($_POST['id'] ?? 0);
            $alunoId = (int)($_POST['aluno_id'] ?? 0);
            
            $service->saveOcorrencia([
                'id' => $id > 0 ? $id : null,
                'aluno_id' => $alunoId,
                'institution_id' => $instId,
                'usuario_id' => $user['id'],
                'texto' => $_POST['texto'] ?? '',
                'is_privado' => (int)($_POST['is_privado'] ?? 0),
                'data_ocorrencia' => $_POST['data_ocorrencia'] ?? date('Y-m-d')
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_ocorrencia':
            hasDbPermission('naapi.manage');
            $id = (int)($_POST['id'] ?? 0);
            $service->deleteOcorrencia($id);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception("Ação '$action' não reconhecida.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
