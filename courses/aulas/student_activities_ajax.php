<?php
/**
 * Vértice Acadêmico — AJAX: Gestão de Atividades Extracurriculares
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
hasDbPermission('students.schedule.activities');

header('Content-Type: application/json');

$db      = getDB();
$user    = getCurrentUser();
$userId  = $user['id'];
$action  = $_GET['action'] ?? '';
$alunoId = (int)($_REQUEST['aluno_id'] ?? 0);

if (!$alunoId) {
    echo json_encode(['success' => false, 'message' => 'Aluno ID não fornecido.']);
    exit;
}

try {
    switch ($action) {
        case 'list':
            $st = $db->prepare('
                SELECT * FROM gestao_alunos_atividadesextra 
                WHERE aluno_id = ? AND is_active = 1 
                ORDER BY dia_semana, horario_inicio
            ');
            $st->execute([$alunoId]);
            $activities = $st->fetchAll();
            echo json_encode(['success' => true, 'data' => $activities]);
            break;

        case 'save':
            $id            = (int)($_POST['id'] ?? 0);
            $titulo        = trim($_POST['titulo'] ?? '');
            $diaSemana     = (int)($_POST['dia_semana'] ?? 0);
            $horarioInicio = $_POST['horario_inicio'] ?? '';
            $horarioFim    = $_POST['horario_fim'] ?? '';
            $dataInicio    = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
            $dataFim       = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
            $local         = trim($_POST['local'] ?? '');
            $descricao     = trim($_POST['descricao'] ?? '');

            // Business Logic: If end date provided but no start date, default to today
            if (!empty($dataFim) && empty($dataInicio)) {
                $dataInicio = date('Y-m-d');
            }

            if (empty($titulo) || !$diaSemana || empty($horarioInicio) || empty($horarioFim)) {
                echo json_encode(['success' => false, 'message' => 'Por favor, preencha todos os campos obrigatórios.']);
                exit;
            }

            if ($id > 0) {
                // UPDATE
                $st = $db->prepare('
                    UPDATE gestao_alunos_atividadesextra 
                    SET titulo = ?, dia_semana = ?, horario_inicio = ?, horario_fim = ?, data_inicio = ?, data_fim = ?, local = ?, descricao = ?
                    WHERE id = ? AND aluno_id = ?
                ');
                $st->execute([$titulo, $diaSemana, $horarioInicio, $horarioFim, $dataInicio, $dataFim, $local, $descricao, $id, $alunoId]);
            } else {
                // CREATE
                $st = $db->prepare('
                    INSERT INTO gestao_alunos_atividadesextra (aluno_id, titulo, dia_semana, horario_inicio, horario_fim, data_inicio, data_fim, local, descricao, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $st->execute([$alunoId, $titulo, $diaSemana, $horarioInicio, $horarioFim, $dataInicio, $dataFim, $local, $descricao, $userId]);
            }

            echo json_encode(['success' => true, 'message' => 'Atividade salva com sucesso!']);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID da atividade não fornecido.']);
                exit;
            }

            // Soft delete
            $st = $db->prepare('UPDATE gestao_alunos_atividadesextra SET is_active = 0 WHERE id = ? AND aluno_id = ?');
            $st->execute([$id, $alunoId]);
            echo json_encode(['success' => true, 'message' => 'Atividade removida com sucesso!']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
}
