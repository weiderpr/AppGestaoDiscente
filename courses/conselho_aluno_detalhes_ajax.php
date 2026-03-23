<?php
/**
 * AJAX - Detalhes do aluno para conselho de classe
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$db = getDB();
$alunoId = (int)($_GET['aluno_id'] ?? 0);
$conselhoId = (int)($_GET['conselho_id'] ?? 0);

if (!$alunoId || !$conselhoId) {
    echo json_encode(['error' => 'Parâmetros inválidos']);
    exit;
}

$stAluno = $db->prepare("SELECT id, nome, email, telefone, photo FROM alunos WHERE id = ?");
$stAluno->execute([$alunoId]);
$aluno = $stAluno->fetch();

if (!$aluno) {
    echo json_encode(['error' => 'Aluno não encontrado']);
    exit;
}

$stEtapas = $db->prepare('
    SELECT e.id, e.description, e.media_nota
    FROM conselhos_etapas ce
    JOIN etapas e ON ce.etapa_id = e.id
    WHERE ce.conselho_id = ?
    ORDER BY e.id
');
$stEtapas->execute([$conselhoId]);
$etapasConselho = $stEtapas->fetchAll();

if (empty($etapasConselho)) {
    echo json_encode(['error' => 'Nenhuma etapa vinculada ao conselho', 'aluno' => $aluno]);
    exit;
}

$etapasIds = array_column($etapasConselho, 'id');
$placeholders = implode(',', array_fill(0, count($etapasIds), '?'));

$sql = "SELECT en.etapa_id, e.description as etapa_desc, e.media_nota,
               en.disciplina_codigo, d.descricao,
               en.nota, en.faltas
        FROM etapa_notas en
        JOIN etapas e ON en.etapa_id = e.id
        JOIN disciplinas d ON en.disciplina_codigo = d.codigo
        WHERE en.aluno_id = ? AND en.etapa_id IN ($placeholders)
        ORDER BY d.descricao, e.id";

$params = array_merge([$alunoId], $etapasIds);
$st = $db->prepare($sql);
$st->execute($params);
$notas = $st->fetchAll();

$disciplinasAgrupadas = [];
foreach ($notas as $nota) {
    $discCodigo = $nota['disciplina_codigo'];
    if (!isset($disciplinasAgrupadas[$discCodigo])) {
        $disciplinasAgrupadas[$discCodigo] = [
            'codigo' => $discCodigo,
            'descricao' => $nota['descricao'],
            'soma_nota' => 0,
            'soma_faltas' => 0,
            'etapas' => []
        ];
    }
    $disciplinasAgrupadas[$discCodigo]['etapas'][$nota['etapa_id']] = [
        'descricao' => $nota['etapa_desc'],
        'nota' => $nota['nota'] !== null ? (float)$nota['nota'] : null,
        'faltas' => (int)$nota['faltas'],
        'media' => $nota['media_nota']
    ];
    if ($nota['nota'] !== null) {
        $disciplinasAgrupadas[$discCodigo]['soma_nota'] += (float)$nota['nota'];
    }
    $disciplinasAgrupadas[$discCodigo]['soma_faltas'] += (int)$nota['faltas'];
}

echo json_encode([
    'aluno' => $aluno,
    'etapas' => $etapasConselho,
    'disciplinas' => array_values($disciplinasAgrupadas)
]);
