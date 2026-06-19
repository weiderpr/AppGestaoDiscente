<?php
/**
 * Vértice Acadêmico — API Dashboard do Aluno
 *
 * Carrega dados de um aluno de forma progressiva por seção.
 *
 * GET ?action=hero&aluno_id=X
 * GET ?action=performance&aluno_id=X&turma_id=Y
 * GET ?action=atendimentos&aluno_id=X
 * GET ?action=conselho&aluno_id=X&turma_id=Y
 * GET ?action=sancoes&aluno_id=X
 * GET ?action=segunda_chamada&aluno_id=X
 * GET ?action=comentarios&aluno_id=X
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$db     = getDB();
$user   = getCurrentUser();
$inst   = getCurrentInstitution();
$instId = (int)$inst['id'];
$userId = (int)$user['id'];
$isAdmin = in_array($user['profile'], ['Administrador', 'Coordenador']);

$action  = $_GET['action'] ?? '';
$alunoId = (int)($_GET['aluno_id'] ?? 0);

if (!$alunoId) {
    echo json_encode(['error' => 'Aluno não especificado']);
    exit;
}

// Garante que o aluno pertence a esta instituição
$stCheck = $db->prepare("
    SELECT a.id FROM alunos a
    JOIN turma_alunos ta ON ta.aluno_id = a.id
    JOIN turmas t ON ta.turma_id = t.id
    JOIN courses c ON t.course_id = c.id
    WHERE a.id = ? AND c.institution_id = ? AND a.deleted_at IS NULL
    LIMIT 1
");
$stCheck->execute([$alunoId, $instId]);
if (!$stCheck->fetch()) {
    echo json_encode(['error' => 'Aluno não encontrado ou sem acesso']);
    exit;
}

// ─── hero ────────────────────────────────────────────────────────────────────
if ($action === 'hero') {

    $stAluno = $db->prepare("
        SELECT a.id, a.nome, a.matricula, a.email, a.telefone, a.photo
        FROM alunos a
        WHERE a.id = ? AND a.deleted_at IS NULL
    ");
    $stAluno->execute([$alunoId]);
    $aluno = $stAluno->fetch(PDO::FETCH_ASSOC);

    if (!$aluno) {
        echo json_encode(['error' => 'Aluno não encontrado']);
        exit;
    }

    // Turmas em que o aluno está matriculado
    $stTurmas = $db->prepare("
        SELECT t.id, t.description as nome, c.name as curso_nome, c.id as curso_id
        FROM turma_alunos ta
        JOIN turmas t ON ta.turma_id = t.id
        JOIN courses c ON t.course_id = c.id
        WHERE ta.aluno_id = ?
        ORDER BY t.id DESC
    ");
    $stTurmas->execute([$alunoId]);
    $turmas = $stTurmas->fetchAll(PDO::FETCH_ASSOC);

    // Contadores para os badges de alerta
    $stSancoes = $db->prepare("
        SELECT COUNT(*) FROM sancao WHERE aluno_id = ? AND status NOT IN ('Cancelado','Finalizado')
    ");
    $stSancoes->execute([$alunoId]);
    $totalSancoesAtivas = (int)$stSancoes->fetchColumn();

    $stEncaminhamentos = $db->prepare("
        SELECT COUNT(*) FROM conselho_encaminhamentos e
        JOIN conselhos_classe c ON e.conselho_id = c.id
        JOIN turmas t ON c.turma_id = t.id
        JOIN courses co ON t.course_id = co.id
        WHERE e.aluno_id = ? AND e.status = 'Pendente' AND co.institution_id = ?
    ");
    $stEncaminhamentos->execute([$alunoId, $instId]);
    $totalEncaminhamentosPendentes = (int)$stEncaminhamentos->fetchColumn();

    $stAtendAtivos = $db->prepare("
        SELECT COUNT(*) FROM gestao_atendimentos
        WHERE aluno_id = ? AND institution_id = ? AND status IN ('Aberto','Em Atendimento') AND deleted_at IS NULL
    ");
    $stAtendAtivos->execute([$alunoId, $instId]);
    $totalAtendimentosAtivos = (int)$stAtendAtivos->fetchColumn();

    $stSegChamada = $db->prepare("
        SELECT COUNT(*) FROM segunda_chamada
        WHERE aluno_id = ? AND institution_id = ? AND status = 'Pendente'
    ");
    $stSegChamada->execute([$alunoId, $instId]);
    $totalSegundaChamadaPendente = (int)$stSegChamada->fetchColumn();

    echo json_encode([
        'aluno'   => $aluno,
        'turmas'  => $turmas,
        'badges'  => [
            'sancoes_ativas'             => $totalSancoesAtivas,
            'encaminhamentos_pendentes'  => $totalEncaminhamentosPendentes,
            'atendimentos_ativos'        => $totalAtendimentosAtivos,
            'segunda_chamada_pendente'   => $totalSegundaChamadaPendente,
        ],
    ]);
    exit;
}

// ─── performance ─────────────────────────────────────────────────────────────
if ($action === 'performance') {

    $turmaId = (int)($_GET['turma_id'] ?? 0);
    if (!$turmaId) {
        echo json_encode(['error' => 'turma_id obrigatório para performance']);
        exit;
    }

    $stEtapas = $db->prepare("
        SELECT e.id, e.description, e.media_nota, e.nota_maxima
        FROM etapas e
        WHERE e.turma_id = ?
        ORDER BY e.id ASC
    ");
    $stEtapas->execute([$turmaId]);
    $etapas = $stEtapas->fetchAll(PDO::FETCH_ASSOC);

    if (empty($etapas)) {
        echo json_encode(['etapas' => [], 'disciplinas' => []]);
        exit;
    }

    $etapasIds    = array_column($etapas, 'id');
    $placeholders = implode(',', array_fill(0, count($etapasIds), '?'));

    $stDisc = $db->prepare("
        SELECT d.codigo, d.descricao
        FROM turma_disciplinas td
        JOIN disciplinas d ON td.disciplina_codigo = d.codigo
        WHERE td.turma_id = ?
        ORDER BY d.descricao ASC
    ");
    $stDisc->execute([$turmaId]);
    $disciplinas = $stDisc->fetchAll(PDO::FETCH_ASSOC);

    $params  = array_merge([$alunoId], $etapasIds);
    $stNotas = $db->prepare("
        SELECT etapa_id, disciplina_codigo, nota, faltas
        FROM etapa_notas
        WHERE aluno_id = ? AND etapa_id IN ($placeholders)
    ");
    $stNotas->execute($params);
    $notasRaw = $stNotas->fetchAll(PDO::FETCH_ASSOC);

    $indexed = [];
    foreach ($notasRaw as $n) {
        $indexed[$n['disciplina_codigo']][$n['etapa_id']] = [
            'nota'   => $n['nota'] !== null ? (float)$n['nota'] : null,
            'faltas' => (int)$n['faltas'],
        ];
    }

    $result = [];
    foreach ($disciplinas as $d) {
        $etapasDisc = [];
        $somaNotas  = 0;
        $qtdNotas   = 0;
        $somaFaltas = 0;

        foreach ($etapas as $e) {
            $dado = $indexed[$d['codigo']][$e['id']] ?? null;
            $nota   = $dado['nota']   ?? null;
            $faltas = $dado['faltas'] ?? 0;

            $etapasDisc[] = [
                'etapa_id'    => $e['id'],
                'etapa_desc'  => $e['description'],
                'nota'        => $nota,
                'faltas'      => $faltas,
                'media_aprov' => (float)$e['media_nota'],
            ];

            if ($nota !== null) {
                $somaNotas += $nota;
                $qtdNotas++;
            }
            $somaFaltas += $faltas;
        }

        $result[] = [
            'codigo'     => $d['codigo'],
            'descricao'  => $d['descricao'],
            'media'      => $qtdNotas > 0 ? round($somaNotas / $qtdNotas, 1) : null,
            'total_faltas' => $somaFaltas,
            'etapas'     => $etapasDisc,
        ];
    }

    echo json_encode(['etapas' => $etapas, 'disciplinas' => $result]);
    exit;
}

// ─── atendimentos ─────────────────────────────────────────────────────────────
if ($action === 'atendimentos') {

    $st = $db->prepare("
        SELECT
            a.id, a.titulo, a.status, a.created_at, a.is_archived,
            a.descricao_publica    AS texto_publico,
            CASE WHEN a.author_id = ? OR ? = 1 THEN a.descricao_profissional ELSE NULL END AS texto_profissional,
            a.author_id,
            u.name  AS autor_nome,
            u.photo AS autor_photo,
            u.profile AS autor_perfil,
            (a.author_id = ?) AS is_mine
        FROM gestao_atendimentos a
        JOIN users u ON a.author_id = u.id
        WHERE a.aluno_id = ? AND a.institution_id = ? AND a.deleted_at IS NULL
        ORDER BY a.created_at DESC
    ");
    $st->execute([$userId, $isAdmin ? 1 : 0, $userId, $alunoId, $instId]);
    $atendimentos = $st->fetchAll(PDO::FETCH_ASSOC);

    // Participantes de cada atendimento
    foreach ($atendimentos as &$at) {
        $stPart = $db->prepare("
            SELECT u.id, u.name, u.photo, u.profile
            FROM gestao_atendimento_usuarios gau
            JOIN users u ON gau.usuario_id = u.id
            WHERE gau.atendimento_id = ?
        ");
        $stPart->execute([$at['id']]);
        $at['participantes'] = $stPart->fetchAll(PDO::FETCH_ASSOC);
        $at['is_mine'] = (bool)$at['is_mine'];
    }
    unset($at);

    echo json_encode(['atendimentos' => $atendimentos]);
    exit;
}

// ─── conselho ────────────────────────────────────────────────────────────────
if ($action === 'conselho') {

    $turmaId = (int)($_GET['turma_id'] ?? 0);
    if (!$turmaId) {
        echo json_encode(['error' => 'turma_id obrigatório para conselho']);
        exit;
    }

    // Conselho mais recente da turma
    $stCons = $db->prepare("
        SELECT id FROM conselhos_classe WHERE turma_id = ? ORDER BY id DESC LIMIT 1
    ");
    $stCons->execute([$turmaId]);
    $conselhoId = (int)$stCons->fetchColumn();

    if (!$conselhoId) {
        echo json_encode(['conselho_id' => null, 'encaminhamentos' => [], 'comentarios' => []]);
        exit;
    }

    // Encaminhamentos
    $stEnc = $db->prepare("
        SELECT e.id, e.setor_tipo, e.data_expectativa, e.status, e.texto,
               e.created_at,
               GROUP_CONCAT(u.name SEPARATOR ', ') AS responsaveis
        FROM conselho_encaminhamentos e
        LEFT JOIN conselho_encaminhamento_usuarios ceu ON ceu.encaminhamento_id = e.id
        LEFT JOIN users u ON ceu.user_id = u.id
        WHERE e.conselho_id = ? AND e.aluno_id = ?
        GROUP BY e.id
        ORDER BY e.created_at DESC
    ");
    $stEnc->execute([$conselhoId, $alunoId]);
    $encaminhamentos = $stEnc->fetchAll(PDO::FETCH_ASSOC);

    // Comentários do conselho (post-its) — sem filtro de aluno (são por conselho)
    $stCom = $db->prepare("
        SELECT cc.id, cc.comentario, cc.created_at, u.name AS autor_nome, u.photo AS autor_photo
        FROM conselhos_comentarios cc
        JOIN users u ON cc.user_id = u.id
        WHERE cc.conselho_id = ?
        ORDER BY cc.created_at DESC
        LIMIT 20
    ");
    $stCom->execute([$conselhoId]);
    $comentarios = $stCom->fetchAll(PDO::FETCH_ASSOC);

    // Anotações (registros) do conselho para o aluno
    $stReg = $db->prepare("
        SELECT cr.id, cr.texto, cr.created_at, u.name AS autor_nome, u.photo AS autor_photo
        FROM conselho_registros cr
        JOIN users u ON cr.user_id = u.id
        WHERE cr.conselho_id = ? AND cr.aluno_id = ?
        ORDER BY cr.created_at DESC
    ");
    $stReg->execute([$conselhoId, $alunoId]);
    $registros = $stReg->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'conselho_id'    => $conselhoId,
        'encaminhamentos'=> $encaminhamentos,
        'comentarios'    => $comentarios,
        'registros'      => $registros,
    ]);
    exit;
}

// ─── sancoes ─────────────────────────────────────────────────────────────────
if ($action === 'sancoes') {

    $st = $db->prepare("
        SELECT s.id, s.data_sancao, s.observacoes AS descricao, s.status,
               st.titulo AS tipo_titulo,
               u.name AS autor_nome
        FROM sancao s
        JOIN sancao_tipo st ON s.sancao_tipo_id = st.id
        LEFT JOIN users u ON s.author_id = u.id
        WHERE s.aluno_id = ? AND s.institution_id = ?
        ORDER BY s.data_sancao DESC
    ");
    $st->execute([$alunoId, $instId]);
    $sancoes = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['sancoes' => $sancoes]);
    exit;
}

// ─── segunda_chamada ─────────────────────────────────────────────────────────
if ($action === 'segunda_chamada') {

    $st = $db->prepare("
        SELECT sc.id, sc.data_solicitacao, sc.status, sc.justificativa,
               sc.disciplina_codigo, d.descricao AS disciplina_nome,
               sc.atividade_nome, sc.created_at
        FROM segunda_chamada sc
        LEFT JOIN disciplinas d ON sc.disciplina_codigo = d.codigo
        WHERE sc.aluno_id = ? AND sc.institution_id = ?
        ORDER BY sc.created_at DESC
        LIMIT 50
    ");
    $st->execute([$alunoId, $instId]);
    $solicitacoes = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['solicitacoes' => $solicitacoes]);
    exit;
}

// ─── comentarios ─────────────────────────────────────────────────────────────
if ($action === 'comentarios') {

    $st = $db->prepare("
        SELECT cp.id, cp.conteudo, cp.created_at,
               u.name AS autor_nome, u.photo AS autor_photo, u.profile AS autor_perfil,
               t.description AS turma_nome
        FROM comentarios_professores cp
        JOIN users u ON cp.professor_id = u.id
        JOIN turmas t ON cp.turma_id = t.id
        WHERE cp.aluno_id = ?
        ORDER BY cp.created_at DESC
        LIMIT 100
    ");
    $st->execute([$alunoId]);
    $comentarios = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['comentarios' => $comentarios]);
    exit;
}

echo json_encode(['error' => "Ação '{$action}' inválida"]);
