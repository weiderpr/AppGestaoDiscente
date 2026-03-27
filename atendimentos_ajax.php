<?php
/**
 * Vértice Acadêmico — AJAX Atendimentos
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/atendimentos_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Sessão expirada.']);
    exit;
}

$user   = getCurrentUser();
$inst   = getCurrentInstitution();
$instId = $inst['id'];

if (!$instId) {
    echo json_encode(['error' => 'Instituição não selecionada.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $alunoId          = (int)($_POST['aluno_id'] ?? 0);
    $turmaId          = (int)($_POST['turma_id'] ?? 0);
    $encaminhamentoId = (int)($_POST['encaminhamento_id'] ?? 0);
    $professionalText = trim($_POST['professional_text'] ?? '');
    $publicText       = trim($_POST['public_text'] ?? '');
    $dataAtendimento  = $_POST['data_atendimento'] ?? date('Y-m-d');

    if (empty($professionalText) && empty($publicText)) {
        echo json_encode(['error' => 'O conteúdo do atendimento não pode estar vazio.']);
        exit;
    }

    try {
        $data = [
            'institution_id'    => $instId,
            'user_id'           => $user['id'],
            'aluno_id'          => $alunoId,
            'turma_id'          => $turmaId,
            'encaminhamento_id' => $encaminhamentoId,
            'professional_text' => $professionalText,
            'public_text'       => $publicText,
            'data_atendimento'  => $dataAtendimento
        ];
        
        $id = saveAtendimento($data);
        
        echo json_encode([
            'success' => true,
            'message' => 'Atendimento registrado com sucesso!',
            'id' => $id
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro ao salvar atendimento: ' . $e->getMessage()]);
    }
    exit;
} else if ($action === 'update') {
    $id               = (int)($_POST['atend_id'] ?? 0);
    $professionalText = trim($_POST['professional_text'] ?? '');
    $publicText       = trim($_POST['public_text'] ?? '');
    $dataAtendimento  = $_POST['data_atendimento'] ?? date('Y-m-d');

    if (!$id) {
        echo json_encode(['error' => 'ID do atendimento ausente.']);
        exit;
    }

    try {
        $db = getDB();
        $st = $db->prepare("SELECT user_id FROM atendimentos WHERE id = ? AND institution_id = ?");
        $st->execute([$id, $instId]);
        $atendimento = $st->fetch();

        if (!$atendimento) {
            echo json_encode(['error' => 'Atendimento não encontrado.']);
            exit;
        }

        // Permissão: Autor ou Administrador/Coordenador
        $allowed = ['Administrador', 'Coordenador'];
        if ($atendimento['user_id'] != $user['id'] && !in_array($user['profile'], $allowed)) {
            echo json_encode(['error' => 'Você não tem permissão para editar este atendimento.']);
            exit;
        }

        $data = [
            'institution_id'    => $instId,
            'professional_text' => $professionalText,
            'public_text'       => $publicText,
            'data_atendimento'  => $dataAtendimento
        ];
        
        updateAtendimento($id, $data);
        
        echo json_encode([
            'success' => true,
            'message' => 'Atendimento atualizado com sucesso!'
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro ao atualizar atendimento: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Ação inválida.']);
