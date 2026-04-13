<?php
/**
 * Vértice Acadêmico — AJAX: Gestão de Dispensas de Disciplinas
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

// Usando permissão similar à de atividades ou específica
$canManage = hasDbPermission('students.schedule.dispensas', false) || hasDbPermission('students.schedule.activities', false);
if (!$canManage) {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para gerenciar dispensas.']);
    exit;
}

// Previne poluição do JSON por alertas/erros do PHP
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

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
        case 'save':
            $turmaId = (int)($_POST['turma_id'] ?? 0);
            $discCodigo = trim($_POST['disciplina_codigo'] ?? '');

            if (!$turmaId || empty($discCodigo)) {
                echo json_encode(['success' => false, 'message' => 'Dados incompletos para registro de dispensa.']);
                exit;
            }

            // Verifica se já existe (soft delete ou ativo)
            $stCheck = $db->prepare('SELECT id FROM alunos_dispensa WHERE aluno_id = ? AND turma_id = ? AND disciplina_codigo = ?');
            $stCheck->execute([$alunoId, $turmaId, $discCodigo]);
            $existing = $stCheck->fetch();

            if ($existing) {
                // Se já existe, apenas ativa (caso estivesse inativo)
                $st = $db->prepare('UPDATE alunos_dispensa SET is_active = 1, updated_at = NOW(), created_by = ? WHERE id = ?');
                $st->execute([$userId, $existing['id']]);
            } else {
                // Cria novo registro
                $st = $db->prepare('INSERT INTO alunos_dispensa (aluno_id, turma_id, disciplina_codigo, created_by) VALUES (?, ?, ?, ?)');
                $st->execute([$alunoId, $turmaId, $discCodigo, $userId]);
            }

            echo json_encode(['success' => true, 'message' => 'Dispensa registrada com sucesso!']);
            break;

        case 'delete':
            $turmaId = (int)($_POST['turma_id'] ?? 0);
            $discCodigo = trim($_POST['disciplina_codigo'] ?? '');

            if (!$turmaId || empty($discCodigo)) {
                echo json_encode(['success' => false, 'message' => 'Dados incompletos para remoção de dispensa.']);
                exit;
            }

            // Soft delete
            $st = $db->prepare('UPDATE alunos_dispensa SET is_active = 0, updated_at = NOW() WHERE aluno_id = ? AND turma_id = ? AND disciplina_codigo = ?');
            $st->execute([$alunoId, $turmaId, $discCodigo]);

            echo json_encode(['success' => true, 'message' => 'Dispensa cancelada com sucesso!']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
            break;
    }
} catch (Throwable $e) {
    // Garante retorno JSON mesmo em erros fatais do PHP
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true, 500);
    }
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
}
