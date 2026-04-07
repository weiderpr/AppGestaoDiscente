<?php
/**
 * API — Desempenho do aluno por turma (para mini-trend no grid)
 * Retorna etapas e notas médias por etapa, sem depender de conselho.
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$db      = getDB();
$alunoId = (int)($_GET['aluno_id'] ?? 0);
$turmaId = (int)($_GET['turma_id'] ?? 0);

if (!$alunoId || !$turmaId) {
    echo json_encode(['error' => 'Parâmetros inválidos']);
    exit;
}

// Busca as etapas da turma
$stEtapas = $db->prepare("
    SELECT e.id, e.description, e.media_nota, e.nota_maxima
    FROM etapas e
    WHERE e.turma_id = ?
    ORDER BY e.id ASC
");
$stEtapas->execute([$turmaId]);
$etapas = $stEtapas->fetchAll();

if (empty($etapas)) {
    echo json_encode(['etapas' => [], 'disciplinas' => []]);
    exit;
}

$etapasIds   = array_column($etapas, 'id');
$placeholders = implode(',', array_fill(0, count($etapasIds), '?'));

// Busca as disciplinas da turma
$stDisc = $db->prepare("
    SELECT d.codigo, d.descricao
    FROM turma_disciplinas td
    JOIN disciplinas d ON td.disciplina_codigo = d.codigo
    WHERE td.turma_id = ?
    ORDER BY d.descricao
");
$stDisc->execute([$turmaId]);
$disciplinas = $stDisc->fetchAll();

// Busca as notas do aluno nessas etapas
$params  = array_merge([$alunoId], $etapasIds);
$stNotas = $db->prepare("
    SELECT etapa_id, disciplina_codigo, nota
    FROM etapa_notas
    WHERE aluno_id = ? AND etapa_id IN ($placeholders)
");
$stNotas->execute($params);
$notasRaw = $stNotas->fetchAll();

// Indexa notas
$indexed = [];
foreach ($notasRaw as $n) {
    $indexed[$n['disciplina_codigo']][$n['etapa_id']] = (float)$n['nota'];
}

// Monta disciplinas com suas etapas
$result = [];
foreach ($disciplinas as $d) {
    $etapasDisc = [];
    foreach ($etapas as $e) {
        $nota = $indexed[$d['codigo']][$e['id']] ?? null;
        $etapasDisc[$e['id']] = ['nota' => $nota];
    }
    $result[] = [
        'codigo'   => $d['codigo'],
        'descricao'=> $d['descricao'],
        'etapas'   => $etapasDisc
    ];
}

echo json_encode([
    'etapas'      => $etapas,
    'disciplinas' => $result
]);
