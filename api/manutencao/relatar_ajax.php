<?php
/**
 * Vértice Acadêmico — API: Relato via QR Code (público, sem autenticação obrigatória)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/Manutencao/ManutencaoService.php';
require_once __DIR__ . '/../../src/App/Services/Manutencao/ManutencaoRelatoService.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$relatoService = new \App\Services\Manutencao\ManutencaoRelatoService();

try {
    switch ($action) {

        case 'get_ambiente':
            $ambienteId = (int)($_GET['ambiente_id'] ?? 0);
            if (!$ambienteId) throw new Exception('ID de ambiente inválido.');

            $data = $relatoService->getAmbienteParaRelato($ambienteId);
            if (!$data) throw new Exception('Ambiente não encontrado ou inativo.');

            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'submit':
            $ambienteId = (int)($_POST['ambiente_id'] ?? 0);
            $descricao  = trim($_POST['descricao'] ?? '');
            $problemas  = $_POST['problemas'] ?? [];
            $outros     = trim($_POST['outros_detalhes'] ?? '');
            $comentario = trim($_POST['comentario'] ?? '');

            if (!$ambienteId) throw new Exception('Ambiente não identificado.');
            if (empty($descricao) && empty($problemas) && empty($outros)) {
                throw new Exception('Selecione ao menos um problema ou descreva o ocorrido.');
            }

            // Busca dados do ambiente para pegar institution_id
            $ambiente = $relatoService->getAmbienteParaRelato($ambienteId);
            if (!$ambiente) throw new Exception('Ambiente inválido.');

            // Se descrição estiver vazia, monta a partir dos problemas
            if (empty($descricao)) {
                $descricao = 'Relato via QR Code';
            }

            $payload = [
                'ambiente_id'    => $ambienteId,
                'institution_id' => $ambiente['institution_id'],
                'descricao'      => $descricao,
                'problemas'      => is_array($problemas) ? $problemas : [],
                'outros_detalhes'=> $outros ?: null,
                'comentario'     => $comentario ?: null,
                'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_id'        => null,
                'nome_relator'   => null,
                'email_relator'  => null,
            ];

            // Se o usuário está logado, registra; senão usa campos opcionais
            if (isLoggedIn()) {
                $user = getCurrentUser();
                $payload['user_id'] = $user['id'];
            } else {
                $payload['nome_relator']  = trim($_POST['nome_relator'] ?? '') ?: null;
                $payload['email_relator'] = trim($_POST['email_relator'] ?? '') ?: null;
            }

            // Tratamento de Foto
            $payload['foto'] = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../assets/uploads/manutencao/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $fileTmpPath = $_FILES['foto']['tmp_name'];
                $fileName = $_FILES['foto']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                // Nome único para o arquivo
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $destPath = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $payload['foto'] = 'assets/uploads/manutencao/' . $newFileName;
                }
            }

            $result = $relatoService->create($payload);
            echo json_encode($result);
            break;

        default:
            throw new Exception('Ação inválida.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
