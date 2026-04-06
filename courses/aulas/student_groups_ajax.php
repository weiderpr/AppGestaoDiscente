<?php
/**
 * Vértice Acadêmico — AJAX: Configuração de Grupos de Alunos
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
hasDbPermission('students.schedule.config');

header('Content-Type: application/json');

$db      = getDB();
$user    = getCurrentUser();
$userId  = $user['id'];
$action  = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            $alunoId = (int)($_POST['aluno_id'] ?? 0);
            $turmaId = (int)($_POST['turma_id'] ?? 0);
            $groups  = $_POST['groups'] ?? []; // Expected array: [aula_id => grupo_name]

            if (!$alunoId || !$turmaId) {
                echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
                exit;
            }

            $db->beginTransaction();

            // Prepare statements
            $stDelete = $db->prepare('DELETE FROM gestao_turma_aluno_grupo WHERE aluno_id = ? AND aula_id = ?');
            $stInsert = $db->prepare('INSERT INTO gestao_turma_aluno_grupo (aluno_id, turma_id, aula_id, grupo) VALUES (?, ?, ?, ?)');

            foreach ($groups as $aulaId => $grupo) {
                $aulaId = (int)$aulaId;
                if (empty($grupo)) {
                    // If group is empty, we just remove the assignment
                    $stDelete->execute([$alunoId, $aulaId]);
                    continue;
                }

                // Upsert logic: Delete first then Insert (since we have UNIQUE KEY)
                $stDelete->execute([$alunoId, $aulaId]);
                $stInsert->execute([$alunoId, $turmaId, $aulaId, $grupo]);
            }

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso!']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
            break;
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
}
