<?php
/**
 * Vértice Acadêmico — API Somativas Search (autocomplete inteligente)
 * Endpoint: /api/somativas/search.php
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'results' => []]);
    exit;
}

$inst   = getCurrentInstitution();
$instId = $inst['id'] ?? 0;
$db     = getDB();

$type   = $_GET['type']   ?? '';
$q      = trim($_GET['q'] ?? '');
$limit  = 15;

$results = [];

switch ($type) {

    // ── Buscar professores ────────────────────────────────────
    case 'professor':
        $stmt = $db->prepare(
            "SELECT u.id, u.name, u.photo,
                    GROUP_CONCAT(DISTINCT d.descricao ORDER BY d.descricao SEPARATOR ', ') AS disciplinas
             FROM users u
             LEFT JOIN turma_disciplina_professores tdp ON tdp.professor_id = u.id
             LEFT JOIN turma_disciplinas td ON td.id = tdp.turma_disciplina_id
             LEFT JOIN disciplinas d ON d.codigo = td.disciplina_codigo AND d.institution_id = ?
             JOIN user_institutions ui ON ui.user_id = u.id AND ui.institution_id = ?
             WHERE u.is_active = 1
               AND (u.is_teacher = 1 OR u.profile = 'Professor')
               AND (u.name LIKE ? OR u.email LIKE ?)
             GROUP BY u.id
             ORDER BY u.name
             LIMIT ?;"
        );
        $like = "%{$q}%";
        $stmt->execute([$instId, $instId, $like, $like, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $results[] = [
                'id'    => $r['id'],
                'label' => $r['name'],
                'sub'   => $r['disciplinas'] ? 'Disciplinas: ' . $r['disciplinas'] : 'Professor',
                'photo' => $r['photo'],
            ];
        }
        break;

    // ── Buscar ambientes/salas ────────────────────────────────
    case 'ambiente':
        $stmt = $db->prepare(
            "SELECT id, descricao, predio_campus
             FROM manutencao_ambientes
             WHERE institution_id = ? AND status = 'Ativo'
               AND (descricao LIKE ? OR predio_campus LIKE ?)
             ORDER BY predio_campus, descricao
             LIMIT ?;"
        );
        $like = "%{$q}%";
        $stmt->execute([$instId, $like, $like, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $results[] = [
                'id'    => $r['id'],
                'label' => $r['descricao'],
                'sub'   => $r['predio_campus'],
            ];
        }
        break;

    // ── Buscar turmas ─────────────────────────────────────────
    case 'turma':
        $stmt = $db->prepare(
            "SELECT t.id, t.description, t.ano, c.name AS course_name
             FROM turmas t
             JOIN courses c ON c.id = t.course_id AND c.institution_id = ?
             WHERE t.is_active = 1
               AND (t.description LIKE ? OR c.name LIKE ?)
             ORDER BY c.name, t.ano, t.description
             LIMIT ?;"
        );
        $like = "%{$q}%";
        $stmt->execute([$instId, $like, $like, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $results[] = [
                'id'    => $r['id'],
                'label' => "{$r['course_name']} — {$r['description']}",
                'sub'   => "Ano: {$r['ano']}",
            ];
        }
        break;

    // ── Buscar disciplinas de uma turma ───────────────────────
    case 'disciplina_turma':
        $turmaId = (int)($_GET['turma_id'] ?? 0);
        if (!$turmaId) { echo json_encode(['success' => true, 'results' => []]); exit; }

        $stmt = $db->prepare(
            "SELECT d.codigo, d.descricao,
                    GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ', ') AS professores
             FROM turma_disciplinas td
             JOIN disciplinas d ON d.codigo = td.disciplina_codigo
             LEFT JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
             LEFT JOIN users u ON u.id = tdp.professor_id
             WHERE td.turma_id = ?
               AND (d.descricao LIKE ? OR d.codigo LIKE ?)
             GROUP BY d.codigo
             ORDER BY d.descricao
             LIMIT ?;"
        );
        $like = "%{$q}%";
        $stmt->execute([$turmaId, $like, $like, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $results[] = [
                'id'    => $r['codigo'],
                'label' => $r['descricao'],
                'sub'   => $r['codigo'] . ($r['professores'] ? ' · ' . $r['professores'] : ''),
            ];
        }
        break;

    // ── Buscar todos os professores de uma somativa_turma (hint volante) ──
    case 'professor_turma':
        $turmaId = (int)($_GET['turma_id'] ?? 0);
        if (!$turmaId) { echo json_encode(['success' => true, 'results' => []]); exit; }

        $stmt = $db->prepare(
            "SELECT DISTINCT u.id, u.name, u.photo,
                    d.descricao AS disc_nome
             FROM turma_disciplinas td
             JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
             JOIN users u  ON u.id = tdp.professor_id
             JOIN disciplinas d ON d.codigo = td.disciplina_codigo
             WHERE td.turma_id = ? AND u.name LIKE ?
             ORDER BY u.name
             LIMIT ?;"
        );
        $like = "%{$q}%";
        $stmt->execute([$turmaId, $like, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $results[] = [
                'id'    => $r['id'],
                'label' => $r['name'],
                'sub'   => 'Prof. de: ' . $r['disc_nome'],
                'photo' => $r['photo'],
            ];
        }
        break;
}

echo json_encode(['success' => true, 'results' => $results]);
