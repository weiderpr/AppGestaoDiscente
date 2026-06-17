<?php
/**
 * Vértice Acadêmico — API: Análise Pedagógica via IA
 *
 * GET  ?aluno_id=X&turma_id=Y          — Retorna análise (usa cache quando disponível)
 * POST aluno_id=X&turma_id=Y&csrf_token — Força recálculo via IA
 *
 * Nota: auth.php valida CSRF globalmente para todas as requisições POST.
 */

// Garante que apenas JSON seja enviado (sem warnings/notices poluindo a resposta)
ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/bootstrap.php';

requireLogin();

$user = getCurrentUser();

// Descarta qualquer output prévio (warnings, notices) e define o tipo de resposta
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!$user || !hasDbPermission('students.comments', false)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado: sem permissão para acessar dados de alunos.']);
    exit;
}

$method  = $_SERVER['REQUEST_METHOD'];
$alunoId = (int) ($_REQUEST['aluno_id'] ?? 0);
$turmaId = (int) ($_REQUEST['turma_id'] ?? 0);

if ($alunoId <= 0 || $turmaId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros obrigatórios ausentes: aluno_id, turma_id']);
    exit;
}

try {
    $service = new \App\Services\AIAnalysisService();

    if (!$service->isConfigured()) {
        http_response_code(503);
        echo json_encode([
            'error' => 'Nenhum provedor de IA configurado. '
                     . 'Adicione a constante AI_PROVIDERS em config/config.local.php.',
        ]);
        exit;
    }

    $analysis = ($method === 'POST')
        ? $service->forceRefresh($alunoId, $turmaId)
        : $service->getAnalysis($alunoId, $turmaId);

    if ($analysis === null) {
        echo json_encode([
            'status'  => 'sem_dados',
            'message' => 'Não há comentários ou notas suficientes para gerar uma análise.',
        ]);
        exit;
    }

    echo json_encode(['status' => 'ok', 'analysis' => $analysis]);

} catch (\Throwable $e) {
    error_log('[ai_analysis.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao processar análise: ' . $e->getMessage()]);
}
