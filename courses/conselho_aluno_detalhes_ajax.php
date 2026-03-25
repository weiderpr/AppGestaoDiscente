<?php
ini_set('display_errors', 0);
error_reporting(0);
/**
 * AJAX - Detalhes do aluno para conselho de classe
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$db = getDB();
$alunoId = (int)($_GET['aluno_id'] ?? 0);
$conselhoId = (int)($_GET['conselho_id'] ?? 0);

if (!$alunoId) {
    echo json_encode(['error' => 'Aluno ID inválido']);
    exit;
}

if (!$conselhoId && isset($_GET['turma_id'])) {
    $turmaId = (int)$_GET['turma_id'];
    $stc = $db->prepare("SELECT id FROM conselhos_classe WHERE turma_id = ? ORDER BY id DESC LIMIT 1");
    $stc->execute([$turmaId]);
    $conselhoId = $stc->fetchColumn();
}

if (!$conselhoId) {
    echo json_encode(['error' => 'Conselho não encontrado para esta turma']);
    exit;
}

// Busca a turma_id se não fornecida
$targetTurmaId = (int)($_GET['turma_id'] ?? 0);
if (!$targetTurmaId) {
    $stTurma = $db->prepare("SELECT turma_id FROM conselhos_classe WHERE id = ?");
    $stTurma->execute([$conselhoId]);
    $targetTurmaId = (int)$stTurma->fetchColumn();
}

$stAluno = $db->prepare("SELECT id, nome, email, telefone, photo FROM alunos WHERE id = ?");
$stAluno->execute([$alunoId]);
$aluno = $stAluno->fetch();

if (!$aluno) {
    echo json_encode(['error' => 'Aluno não encontrado']);
    exit;
}

$stEtapas = $db->prepare('
    SELECT e.id, e.description, e.media_nota, e.nota_maxima
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

// 1. Buscar TODAS as disciplinas vinculadas à turma
$stDisciplinas = $db->prepare("
    SELECT d.codigo, d.descricao, dc.nome as categoria_nome
    FROM turma_disciplinas td
    JOIN disciplinas d ON td.disciplina_codigo = d.codigo
    LEFT JOIN disciplina_categorias dc ON d.categoria_id = dc.id
    WHERE td.turma_id = ?
    ORDER BY d.descricao
");
$stDisciplinas->execute([$targetTurmaId]);
$todasDisciplinas = $stDisciplinas->fetchAll();

// 2. Buscar as notas lançadas para as etapas deste conselho
$etapasIds = array_column($etapasConselho, 'id');
$placeholders = implode(',', array_fill(0, count($etapasIds), '?'));
$params = array_merge([$alunoId], $etapasIds);

$sqlNotas = "SELECT etapa_id, disciplina_codigo, nota, faltas FROM etapa_notas WHERE aluno_id = ? AND etapa_id IN ($placeholders)";
$stNotas = $db->prepare($sqlNotas);
$stNotas->execute($params);
$notasRaw = $stNotas->fetchAll();

// Indexar notas para facilitar o agrupamento
$indexedNotas = [];
foreach ($notasRaw as $n) {
    $indexedNotas[$n['disciplina_codigo']][$n['etapa_id']] = $n;
}

// 3. Montar o retorno garantindo que TODAS as disciplinas apareçam
$disciplinasAgrupadas = [];
foreach ($todasDisciplinas as $d) {
    $discCodigo = $d['codigo'];
    $agrupada = [
        'codigo' => $discCodigo,
        'descricao' => $d['descricao'],
        'categoria' => $d['categoria_nome'] ?? 'Sem Categoria',
        'soma_nota' => 0,
        'soma_faltas' => 0,
        'etapas' => []
    ];
    
    foreach ($etapasConselho as $e) {
        $etapaId = $e['id'];
        $detalheNota = $indexedNotas[$discCodigo][$etapaId] ?? null;
        
        $agrupada['etapas'][$etapaId] = [
            'descricao' => $e['description'],
            'nota' => $detalheNota ? ($detalheNota['nota'] !== null ? (float)$detalheNota['nota'] : null) : null,
            'faltas' => $detalheNota ? (int)$detalheNota['faltas'] : 0,
            'media' => $e['media_nota']
        ];
        
        if ($detalheNota && $detalheNota['nota'] !== null) {
            $agrupada['soma_nota'] += (float)$detalheNota['nota'];
        }
        if ($detalheNota) {
            $agrupada['soma_faltas'] += (int)$detalheNota['faltas'];
        }
    }
    $disciplinasAgrupadas[] = $agrupada;
}

// 4. Buscar médias da turma para comparação
$mediasTurmaRaw = [];
try {
    // Busca contagem de alunos separadamente para evitar subqueries complexas no SELECT
    $stCount = $db->prepare("SELECT COUNT(*) FROM turma_alunos WHERE turma_id = ?");
    $stCount->execute([$targetTurmaId]);
    $totalAlunosTurma = (int)$stCount->fetchColumn() ?: 1;

    $sqlMediaTurma = "
        SELECT en.disciplina_codigo, SUM(en.nota) as soma_total
        FROM etapa_notas en
        WHERE en.etapa_id IN ($placeholders) 
          AND en.aluno_id IN (SELECT aluno_id FROM turma_alunos WHERE turma_id = ?)
        GROUP BY en.disciplina_codigo
    ";
    $stMedia = $db->prepare($sqlMediaTurma);
    $stMedia->execute(array_merge($etapasIds, [$targetTurmaId]));
    $somasTurmaRaw = $stMedia->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    
    foreach ($somasTurmaRaw as $codigo => $soma) {
        $mediasTurmaRaw[$codigo] = $soma / $totalAlunosTurma;
    }
} catch (Throwable $e) {
    // Silently fail for averages if query fails
    $mediasTurmaRaw = [];
}

// Adicionar a média da turma ao array de disciplinas
foreach ($disciplinasAgrupadas as &$disc) {
    $disc['media_turma'] = isset($mediasTurmaRaw[$disc['codigo']]) ? (float)$mediasTurmaRaw[$disc['codigo']] : 0;
}
unset($disc);

// 5. Soma de médias de aprovação (Target)
$somaMediaAprovacao = 0;
foreach ($etapasConselho as $e) {
    if (isset($e['media_nota'])) {
        $somaMediaAprovacao += (float)$e['media_nota'];
    }
}

// 6. Distribuição das notas da turma (para Boxplot)
$distribuicaoTurma = [];
try {
    $sqlDist = "
        SELECT en.disciplina_codigo, en.aluno_id, SUM(en.nota) as soma_aluno
        FROM etapa_notas en
        WHERE en.etapa_id IN ($placeholders)
          AND en.aluno_id IN (SELECT aluno_id FROM turma_alunos WHERE turma_id = ?)
        GROUP BY en.disciplina_codigo, en.aluno_id
    ";
    $stDist = $db->prepare($sqlDist);
    $stDist->execute(array_merge($etapasIds, [$targetTurmaId]));
    $resDist = $stDist->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($resDist as $row) {
        $distribuicaoTurma[$row['disciplina_codigo']][] = (float)$row['soma_aluno'];
    }
} catch (Throwable $e) {
    // Silently fail if query fails
}

echo json_encode([
    'aluno' => $aluno,
    'etapas' => $etapasConselho,
    'soma_media_aprovacao' => $somaMediaAprovacao,
    'disciplinas' => array_values($disciplinasAgrupadas),
    'distribuicao_turma' => $distribuicaoTurma
]);
