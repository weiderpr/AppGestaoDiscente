<?php
/**
 * Vértice Acadêmico — API de Notas da Turma (Mobile)
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$user = getCurrentUser();
$turmaId = (int)($_GET['turma_id'] ?? 0);
$etapaId = (int)($_GET['etapa_id'] ?? 0);
$disciplinaCodigo = trim($_GET['disciplina_codigo'] ?? '');

if (!$turmaId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID da turma é obrigatório.']);
    exit;
}

// Inclusão manual dos serviços para o ambiente procedural
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/TurmaService.php';

$turmaService = new \App\Services\TurmaService();

// 1. Validar se a turma existe e obter informações
$turma = $turmaService->findById($turmaId);
if (!$turma) {
    http_response_code(404);
    echo json_encode(['error' => 'Turma não encontrada.']);
    exit;
}

// 2. Obter Etapas da Turma
$etapas = $turmaService->getEtapas($turmaId);
if (empty($etapas)) {
    echo json_encode([
        'etapas' => [],
        'etapa_ativa' => null,
        'disciplina_destaque' => null,
        'disciplinas_media' => [],
        'alunos' => []
    ]);
    exit;
}

// Determinar a etapa ativa
$etapaAtiva = null;
if ($etapaId) {
    foreach ($etapas as $et) {
        if ((int)$et['id'] === $etapaId) {
            $etapaAtiva = $et;
            break;
        }
    }
}
if (!$etapaAtiva) {
    $etapaAtiva = $etapas[0]; // Assume a primeira
}
$etapaAtivaId = (int)$etapaAtiva['id'];

// 3. Determinar a disciplina em destaque
// Se o usuário é professor, busca as disciplinas dele nesta turma
$teacherDisciplines = $turmaService->getTeacherDisciplinesInTurma($turmaId, (int)$user['id']);
$disciplinaDestaque = null;

if (!empty($teacherDisciplines)) {
    // Se foi passado um código por GET e o professor leciona ela, usa ela. Caso contrário, usa a primeira.
    if ($disciplinaCodigo) {
        foreach ($teacherDisciplines as $td) {
            if ($td['codigo'] === $disciplinaCodigo) {
                $disciplinaDestaque = $td;
                break;
            }
        }
    }
    if (!$disciplinaDestaque) {
        $disciplinaDestaque = $teacherDisciplines[0];
    }
} else {
    // Se o usuário não leciona disciplinas nesta turma (ex: Administrador/Coordenador),
    // pegamos a primeira disciplina da turma como destaque.
    $allDisciplinas = $turmaService->getDisciplinas($turmaId);
    if (!empty($allDisciplinas)) {
        if ($disciplinaCodigo) {
            foreach ($allDisciplinas as $ad) {
                if ($ad['codigo'] === $disciplinaCodigo) {
                    $disciplinaDestaque = [
                        'codigo' => $ad['codigo'],
                        'descricao' => $ad['descricao']
                    ];
                    break;
                }
            }
        }
        if (!$disciplinaDestaque) {
            $disciplinaDestaque = [
                'codigo' => $allDisciplinas[0]['codigo'],
                'descricao' => $allDisciplinas[0]['descricao']
            ];
        }
    }
}

// 4. Obter as médias de todas as disciplinas da turma para a etapa ativa (gráfico de barras)
$mediasRaw = $turmaService->getTurmaMediaPorDisciplina($turmaId, $etapaAtivaId);
$disciplinasMedia = [];
foreach ($mediasRaw as $mr) {
    $disciplinasMedia[] = [
        'codigo' => $mr['codigo'],
        'descricao' => $mr['descricao'],
        'media_nota' => $mr['media_nota'] !== null ? (float)$mr['media_nota'] : null
    ];
}

// 5. Obter a listagem de alunos e notas para a disciplina em destaque na etapa ativa
$alunos = [];
if ($disciplinaDestaque) {
    $alunosRaw = $turmaService->getAlunosNotasPorDisciplinaEtapa($turmaId, $disciplinaDestaque['codigo'], $etapaAtivaId);
    foreach ($alunosRaw as $ar) {
        $alunos[] = [
            'id' => (int)$ar['id'],
            'nome' => $ar['nome'],
            'matricula' => $ar['matricula'],
            'photo' => $ar['photo'],
            'nota' => $ar['nota'] !== null ? (float)$ar['nota'] : null,
            'faltas' => $ar['faltas'] !== null ? (int)$ar['faltas'] : 0
        ];
    }
}

echo json_encode([
    'etapas' => $etapas,
    'etapa_ativa' => $etapaAtiva,
    'disciplina_destaque' => $disciplinaDestaque,
    'disciplinas_media' => $disciplinasMedia,
    'alunos' => $alunos,
    'media_aprovacao' => (float)($etapaAtiva['media_nota'] ?? 6.00),
    'nota_maxima_turma' => (float)($etapaAtiva['nota_maxima'] ?? 10.00)
]);
exit;
