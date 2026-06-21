<?php
/**
 * Vértice Acadêmico — API Auto-Alocação de Somativas
 *
 * POST action=preview   → roda greedy + IA (se necessário), retorna plano sem salvar
 * POST action=confirm   → salva as alocações confirmadas em lote
 *
 * Body:
 *   action, somativa_id, csrf_token
 *   (confirm) alocacoes = JSON das alocações aprovadas na pré-visualização
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/App/Services/Service.php';
require_once __DIR__ . '/../../src/App/Services/Traits/Auditable.php';
require_once __DIR__ . '/../../src/App/Services/SomativaService.php';
require_once __DIR__ . '/../../src/App/Services/SomativaScheduler.php';
require_once __DIR__ . '/../../src/App/Services/SomativaAIScheduler.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if (!hasDbPermission('somativas.update', false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão para editar somativas']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$user   = getCurrentUser();
$inst   = getCurrentInstitution();
$instId = (int)($inst['id'] ?? 0);
$somId  = (int)($_POST['somativa_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$somId) {
    echo json_encode(['success' => false, 'message' => 'somativa_id obrigatório']);
    exit;
}

try {

    // ── PREVIEW: roda o engine e retorna o plano ──────────────
    if ($action === 'preview') {
        $scheduler = new \App\Services\SomativaScheduler();
        $plano     = $scheduler->run($somId, $instId);
        $provider  = null;
        $aiJust    = null;

        // Fallback de IA para conflitos não resolvidos
        if (!empty($plano['conflitos'])) {
            $aiScheduler = new \App\Services\SomativaAIScheduler();

            if ($aiScheduler->isAvailable()) {
                try {
                    $service     = new \App\Services\SomativaService();
                    $slotsLivres = $service->getSlotsLivres($somId, $plano['alocacoes']);
                    $restricoes  = $service->getRestricoes($somId);

                    $aiResult = $aiScheduler->resolve(
                        $plano['conflitos'],
                        $slotsLivres,
                        $restricoes,
                        $plano['alocacoes']
                    );

                    $provider = $aiResult['provider'];
                    $aiJust   = $aiResult['justificativa'];

                    // Enriquece resoluções com dados necessários para salvar
                    $db = getDB();
                    foreach ($aiResult['resolucoes'] as $res) {
                        $sdId = (int)$res['somativa_disciplina_id'];
                        $info = fetchDiscInfoForSave($db, $sdId, $instId);
                        if (!$info) continue;

                        // Encontra o conflito correspondente para obter nomes descritivos
                        $confMeta = [];
                        foreach ($plano['conflitos'] as $k => $conf) {
                            if ((int)$conf['somativa_disciplina_id'] === $sdId) {
                                $confMeta = $conf;
                                unset($plano['conflitos'][$k]);
                                break;
                            }
                        }

                        // Busca label do slot
                        $slotInfo = fetchSlotInfo($db, (int)$res['slot_config_id']);

                        $plano['alocacoes'][] = [
                            'somativa_id'            => $somId,
                            'somativa_turma_id'      => (int)$info['som_turma_id'],
                            'somativa_disciplina_id' => $sdId,
                            'disciplina_codigo'      => $confMeta['disciplina_codigo'] ?? '',
                            'disc_nome'              => $confMeta['disc_nome']         ?? '',
                            'turma_desc'             => $confMeta['turma_desc']        ?? '',
                            'data_prova'             => $res['data_prova'],
                            'slot_config_id'         => (int)$res['slot_config_id'],
                            'slot_label'             => $slotInfo['label']          ?? null,
                            'horario_inicio'         => $slotInfo['horario_inicio'] ?? null,
                            'horario_fim'            => $slotInfo['horario_fim']    ?? null,
                            'aplicador_id'           => null,
                            'volante_id'             => null,
                            'ambiente_id'            => $info['turma_ambiente_id'] ?: null,
                            'tipo'                   => 'Normal',
                            'observacoes'            => null,
                            'justificativa'          => $res['justificativa'] ?? 'Resolvido por IA (' . $provider . ')',
                            'ai_resolved'            => true,
                        ];
                    }

                    $plano['conflitos'] = array_values($plano['conflitos']);

                    if ($aiJust) {
                        $plano['avisos'][] = "IA ({$provider}): {$aiJust}";
                    }

                } catch (\Throwable $e) {
                    $plano['avisos'][] = 'IA indisponível ou falhou: ' . $e->getMessage();
                    error_log('[AutoAlocar] IA falhou: ' . $e->getMessage());
                }
            } else {
                $plano['avisos'][] = 'IA não configurada — ' . count($plano['conflitos']) . ' conflito(s) não resolvido(s) precisam de alocação manual.';
            }
        }

        echo json_encode([
            'success'   => true,
            'alocacoes' => $plano['alocacoes'],
            'conflitos' => $plano['conflitos'],
            'avisos'    => $plano['avisos'],
            'logs'      => $plano['logs'] ?? [],
            'provider'  => $provider,
            'total'     => count($plano['alocacoes']),
        ]);
        exit;
    }

    // ── CONFIRM: salva em lote ────────────────────────────────
    if ($action === 'confirm') {
        $alocacoesJson = $_POST['alocacoes'] ?? '[]';
        $alocacoes     = json_decode($alocacoesJson, true);

        if (!is_array($alocacoes) || empty($alocacoes)) {
            echo json_encode(['success' => false, 'message' => 'Nenhuma alocação para salvar']);
            exit;
        }

        $service = new \App\Services\SomativaService();
        $saved   = 0;
        $errors  = [];

        foreach ($alocacoes as $aloc) {
            // Campos extras de exibição não devem ir para saveGradeSlot
            $saveData = [
                'somativa_id'            => (int)($aloc['somativa_id'] ?? $somId),
                'somativa_turma_id'      => (int)$aloc['somativa_turma_id'],
                'somativa_disciplina_id' => (int)$aloc['somativa_disciplina_id'] ?: null,
                'data_prova'             => $aloc['data_prova'],
                'slot_config_id'         => (int)$aloc['slot_config_id'],
                'aplicador_id'           => !empty($aloc['aplicador_id'])       ? (int)$aloc['aplicador_id']       : null,
                'volante_id'             => !empty($aloc['volante_id'])         ? (int)$aloc['volante_id']         : null,
                'naapi_aplicador_id'     => !empty($aloc['naapi_aplicador_id']) ? (int)$aloc['naapi_aplicador_id'] : null,
                'ambiente_id'            => !empty($aloc['ambiente_id'])        ? (int)$aloc['ambiente_id']        : null,
                'tipo'                   => $aloc['tipo'] ?? 'Normal',
                'observacoes'            => $aloc['observacoes'] ?? null,
                'created_by'             => $user['id'],
            ];

            try {
                $service->saveGradeSlot($saveData);
                $saved++;
            } catch (\Throwable $e) {
                $errors[] = ($aloc['disc_nome'] ?? "ID {$aloc['somativa_disciplina_id']}") . ': ' . $e->getMessage();
                error_log('[AutoAlocar confirm] ' . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'saved'   => $saved,
            'errors'  => $errors,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ação inválida. Use: preview | confirm']);

} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[AutoAlocar] Erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}

// ── Helpers ───────────────────────────────────────────────────

function fetchDiscInfoForSave(\PDO $db, int $sdId, int $instId): ?array
{
    $stmt = $db->prepare(
        "SELECT sd.somativa_turma_id AS som_turma_id,
                t.ambiente_id        AS turma_ambiente_id
         FROM somativa_disciplinas sd
         JOIN somativa_turmas st ON st.id = sd.somativa_turma_id
         JOIN somativas s        ON s.id  = st.somativa_id AND s.institution_id = ?
         JOIN turmas t           ON t.id  = st.turma_id
         WHERE sd.id = ?"
    );
    $stmt->execute([$instId, $sdId]);
    return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
}

function fetchSlotInfo(\PDO $db, int $slotId): ?array
{
    $stmt = $db->prepare('SELECT id, label, horario_inicio, horario_fim FROM somativa_slots_config WHERE id = ?');
    $stmt->execute([$slotId]);
    return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
}
