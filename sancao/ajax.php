<?php
/**
 * Vértice Acadêmico — Ajax Handler do Módulo de Sanções
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SancaoService.php';
require_once __DIR__ . '/../src/App/Services/NotificationService.php';

use App\Services\SancaoService;
use App\Services\NotificationService;

requireLogin();

$action = $_GET['action'] ?? '';
$db = getDB();
$inst = getCurrentInstitution();
$instId = (int)$inst['id'];
$user = getCurrentUser();

header('Content-Type: application/json');

$sancaoService = new SancaoService();

// ─── Busca de Alunos (autocomplete) ────────────────────────────────────────
if ($action === 'search_aluno') {
    hasDbPermission('sancoes.index');
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

// ─── Dependências do formulário ─────────────────────────────────────────────
if ($action === 'get_dependencies') {
    hasDbPermission('sancoes.index');
    echo json_encode($sancaoService->getDependencies($instId));
    exit;
}

// ─── Listagem ───────────────────────────────────────────────────────────────
if ($action === 'list') {
    hasDbPermission('sancoes.index');
    $filters = [
        'aluno'  => trim($_GET['aluno'] ?? ''),
        'status' => trim($_GET['status'] ?? ''),
    ];
    try {
        echo json_encode(['status' => 'success', 'data' => $sancaoService->list($instId, $filters)]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro DB']);
    }
    exit;
}

// ─── Busca Única ─────────────────────────────────────────────────────────────
if ($action === 'get') {
    hasDbPermission('sancoes.index');
    $id = (int)($_GET['id'] ?? 0);
    $sancao = $sancaoService->get($id, $instId);

    if (!$sancao) {
        echo json_encode(['status' => 'error', 'message' => 'Sanção não encontrada']);
        exit;
    }

    echo json_encode(['status' => 'success', 'data' => $sancao]);
    exit;
}

// ─── Histórico de Aluno ──────────────────────────────────────────────────────
if ($action === 'get_history') {
    if (!hasDbPermission('sancoes.index', false) && !hasDbPermission('students.index', false)) {
        hasDbPermission('sancoes.index');
    }
    $alunoId  = (int)($_GET['aluno_id'] ?? 0);
    $excludeId = (int)($_GET['exclude_id'] ?? 0);

    if (!$alunoId) {
        echo json_encode(['status' => 'error', 'message' => 'Aluno não especificado']);
        exit;
    }

    try {
        echo json_encode(['status' => 'success', 'data' => $sancaoService->getHistory($alunoId, $instId, $excludeId)]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao carregar histórico']);
    }
    exit;
}

// ─── Salvar (Create/Update) ──────────────────────────────────────────────────
if ($action === 'save') {
    hasDbPermission('sancoes.manage');

    $data = [
        'id'             => (int)($_POST['sancao_id'] ?? 0),
        'aluno_id'       => (int)($_POST['aluno_id'] ?? 0),
        'data_sancao'    => $_POST['data_sancao'] ?? '',
        'sancao_tipo_id' => (int)($_POST['sancao_tipo_id'] ?? 0),
        'observacoes'    => $_POST['observacoes'] ?? null,
        'status'         => $_POST['status'] ?? 'Em aberto',
        'acoes'          => $_POST['acoes'] ?? [],
    ];

    if (!$data['aluno_id'] || !$data['data_sancao'] || !$data['sancao_tipo_id']) {
        echo json_encode(['status' => 'error', 'message' => 'Preencha os campos obrigatórios.']);
        exit;
    }

    try {
        $savedId = $sancaoService->save($data, $instId, (int)$user['id']);

        // Disparar Notificação do Sistema (apenas no cadastro)
        if (!(int)($_POST['sancao_id'] ?? 0)) {
            try {
                $notifService = new NotificationService();
                $stInfo = $db->prepare("
                    SELECT a.nome as aluno_nome, st.titulo as tipo_titulo 
                    FROM alunos a, sancao_tipo st 
                    WHERE a.id = ? AND st.id = ?
                ");
                $stInfo->execute([$data['aluno_id'], $data['sancao_tipo_id']]);
                $info = $stInfo->fetch();

                $notifService->push([
                    'titulo'               => 'Nova Sanção Cadastrada',
                    'mensagem'             => "O aluno <strong>" . ($info['aluno_nome'] ?? 'Desconhecido') . "</strong> recebeu a sanção: " . ($info['tipo_titulo'] ?? 'N/A'),
                    'tipo'                 => 'Alerta',
                    'aluno_id'             => $data['aluno_id'],
                    'turma_id'             => null,
                    'link_acao'            => "/sancao/index.php",
                    'required_permission'  => 'sancoes.index'
                ]);
            } catch (Exception $e) {
                // Falha silenciosa na notificação
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Sanção registrada com sucesso!', 'id' => $savedId]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage() ?: 'Erro interno ao salvar.']);
    }
    exit;
}

// ─── Finalizar ───────────────────────────────────────────────────────────────
if ($action === 'finish') {
    hasDbPermission('sancoes.manage');
    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'ID não fornecido.']);
        exit;
    }

    try {
        $sancaoService->finish($id, $instId);
        echo json_encode(['status' => 'success', 'message' => 'Sanção finalizada com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao finalizar sanção.']);
    }
    exit;
}

// ─── Excluir ─────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    hasDbPermission('sancoes.manage');
    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'ID não fornecido.']);
        exit;
    }

    try {
        $sancaoService->delete($id, $instId, (int)$user['id']);
        echo json_encode(['status' => 'success', 'message' => 'Sanção excluída com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage() ?: 'Erro ao excluir sanção.']);
    }
    exit;
}

// ─── Anexos ───────────────────────────────────────────────────────────────────
if ($action === 'fetch_anexos') {
    $sancaoId = (int)($_GET['sancao_id'] ?? 0);
    echo json_encode(['success' => true, 'anexos' => $sancaoService->getAnexos($sancaoId)]);
    exit;
}

if ($action === 'upload_anexo') {
    hasDbPermission('sancoes.manage');
    $sancaoId  = (int)($_POST['sancao_id'] ?? 0);
    $descricao = $_POST['descricao'] ?? '';

    if (!$sancaoId || !isset($_FILES['arquivo'])) {
        echo json_encode(['success' => false, 'error' => 'Dados incompletos.']);
        exit;
    }

    $file       = $_FILES['arquivo'];
    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt)) {
        echo json_encode(['success' => false, 'error' => 'Extensão não permitida.']);
        exit;
    }

    $dir = __DIR__ . '/../assets/uploads/sancoes';
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $filename = uniqid('sancao_file_' . $sancaoId . '_') . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        $fileData = [
            'arquivo'   => 'assets/uploads/sancoes/' . $filename,
            'descricao' => $descricao,
            'extensao'  => $ext,
            'tamanho'   => $file['size'],
        ];
        $sancaoService->addAnexo($sancaoId, (int)$user['id'], $fileData);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Falha ao mover arquivo.']);
    }
    exit;
}

if ($action === 'delete_anexo') {
    hasDbPermission('sancoes.manage');
    $anexoId  = (int)($_POST['anexo_id'] ?? 0);
    $sancaoId = (int)($_POST['sancao_id'] ?? 0);

    if ($sancaoService->deleteAnexo($anexoId, $sancaoId)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Anexo não encontrado.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Rota inválida.']);
