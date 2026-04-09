<?php
/**
 * Vértice Acadêmico — API de Geração da Ata do Conselho
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$conselhoId = (int)($_GET['conselho_id'] ?? 0);

if (!$conselhoId) {
    echo json_encode(['success' => false, 'message' => 'Conselho ID ausente']);
    exit;
}

try {
    // 0. Metadados do Conselho e Instituição
    $stmt = $db->prepare("
        SELECT cc.descricao, cc.data_hora, 
               t.description as turma_name, 
               c.name as course_name,
               i.name as institution_name, i.cnpj as institution_cnpj, i.address as institution_address, i.photo as institution_logo
        FROM conselhos_classe cc
        JOIN turmas t ON cc.turma_id = t.id
        JOIN courses c ON t.course_id = c.id
        JOIN institutions i ON c.institution_id = i.id
        WHERE cc.id = ?
    ");
    $stmt->execute([$conselhoId]);
    $conselhoInfo = $stmt->fetch();

    // 1. Presentes
    $stmt = $db->prepare("
        SELECT u.name, u.profile, u.photo, u.is_teacher
        FROM conselhos_presentes cp
        JOIN users u ON cp.user_id = u.id
        WHERE cp.conselho_id = ?
        ORDER BY u.name
    ");
    $stmt->execute([$conselhoId]);
    $presentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($presentes as &$p) {
        if (!in_array($p['profile'], ['Coordenador', 'Professor', 'Diretor']) && !empty($p['is_teacher'])) {
            $p['profile'] = 'Professor';
        }
    }
    unset($p);

    // 1.1 Ausentes (Professores da turma que não estão presentes)
    $stmt = $db->prepare("
        SELECT DISTINCT u.name, u.profile, u.is_teacher
        FROM users u
        JOIN turma_disciplina_professores tdp ON tdp.professor_id = u.id
        JOIN turma_disciplinas td ON tdp.turma_disciplina_id = td.id
        JOIN conselhos_classe cc ON cc.turma_id = td.turma_id
        WHERE cc.id = ? 
        AND u.id NOT IN (
            SELECT user_id FROM conselhos_presentes WHERE conselho_id = ?
        )
        ORDER BY u.name
    ");
    $stmt->execute([$conselhoId, $conselhoId]);
    $ausentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ausentes as &$p) {
        if (!in_array($p['profile'], ['Coordenador', 'Professor', 'Diretor']) && !empty($p['is_teacher'])) {
            $p['profile'] = 'Professor';
        }
    }
    unset($p);

    // 2. Registros (Comentários do Conselho)
    $stmt = $db->prepare("
        SELECT cr.*, u.name as author_name, a.nome as aluno_nome
        FROM conselho_registros cr
        LEFT JOIN users u ON cr.user_id = u.id
        LEFT JOIN alunos a ON cr.aluno_id = a.id
        WHERE cr.conselho_id = ?
        ORDER BY cr.created_at ASC
    ");
    $stmt->execute([$conselhoId]);
    $registros = $stmt->fetchAll();

    // 3. Encaminhamentos
    $stmt = $db->prepare("
        SELECT ce.*, a.nome as aluno_nome, u.name as author_name,
               GROUP_CONCAT(target_u.name SEPARATOR ', ') as target_users
        FROM conselho_encaminhamentos ce
        LEFT JOIN alunos a ON ce.aluno_id = a.id
        JOIN users u ON ce.author_id = u.id
        LEFT JOIN conselho_encaminhamento_usuarios ceu ON ce.id = ceu.encaminhamento_id
        LEFT JOIN users target_u ON ceu.user_id = target_u.id
        WHERE ce.conselho_id = ?
        GROUP BY ce.id
        ORDER BY ce.created_at ASC
    ");
    $stmt->execute([$conselhoId]);
    $encaminhamentos = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'info' => $conselhoInfo,
            'presentes' => $presentes,
            'ausentes' => $ausentes,
            'registros' => $registros,
            'encaminhamentos' => $encaminhamentos
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
