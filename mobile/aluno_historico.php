<?php
/**
 * Vértice Acadêmico — Histórico Multidisciplinar (Mobile)
 * UI Refatorada para Excelência Visual em Dispositivos Móveis
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/AlunoService.php';

requireLogin();

$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = $inst['id'];

// Validação via Matriz RBAC
hasDbPermission('students.history'); // Redireciona se não tiver acesso

$alunoId = (int)($_GET['aluno_id'] ?? 0);
$turmaId = (int)($_GET['turma_id'] ?? 0);

if (!$alunoId || !$turmaId) {
    header('Location: /mobile/courses.php');
    exit;
}

$db = getDB();
$alunoService = new \App\Services\AlunoService();

// Contexto da Turma
$stTurma = $db->prepare("
    SELECT t.*, c.name as course_name 
    FROM turmas t 
    JOIN courses c ON t.course_id = c.id 
    WHERE t.id = ? AND c.institution_id = ? AND t.is_active = 1
");
$stTurma->execute([$turmaId, $instId]);
$turma = $stTurma->fetch();

if (!$turma) {
    header('Location: /mobile/courses.php');
    exit;
}

$aluno = $alunoService->findById($alunoId);
if (!$aluno) {
    header('Location: /mobile/alunos.php?turma_id=' . $turmaId);
    exit;
}

// Histórico
$history = $alunoService->getMultidisciplinaryHistory($alunoId, $turmaId);

/**
 * Organiza o histórico em árvore para exibir aninhamento (Encaminhamento -> Atendimento -> Comentário)
 */
function buildHistoryTree(array $flatItems): array {
    $itemMap = [];
    $tree = [];

    // Primeiro mapeia todos por unique_id
    foreach ($flatItems as $item) {
        $item['children'] = [];
        $item['is_archived_inherited'] = isset($item['is_archived']) && $item['is_archived'] == 1;
        $itemMap[$item['unique_id']] = $item;
    }

    // Depois vincula aos pais e propagada arquivo para filhos
    foreach ($itemMap as $id => &$item) {
        if ($item['parent_unique_id'] && isset($itemMap[$item['parent_unique_id']])) {
            $parent = $itemMap[$item['parent_unique_id']];
            $itemMap[$item['parent_unique_id']]['children'][] = &$item;
            // Herda arquivo do pai
            if (isset($parent['is_archived_inherited']) && $parent['is_archived_inherited']) {
                $item['is_archived_inherited'] = true;
            }
            // Se pai tem is_archived, filho também herda
            if (isset($parent['is_archived']) && $parent['is_archived'] == 1) {
                $item['is_archived_inherited'] = true;
            }
        } else {
            $tree[] = &$item;
        }
    }
    return $tree;
}

$historyTree = buildHistoryTree($history);

function safeHtml($html) {
    $allowedTags = '<b><strong><i><em><u><s><p><br><br/><br /><ul><ol><li><a><code><blockquote><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($html, $allowedTags);
    $text = preg_replace('/([a-záàâãéèêíìîíòôõúùûç])([A-ZÁÀÂÃÉÈÊÍÌÎÍÒÔÕÚÙÛ])/', '$1 $2', $text);
    return nl2br($text);
}


$pageTitle = "Histórico: " . $aluno['nome'];
$currentPage = 'cursos';
require_once __DIR__ . '/header.php';
?>

<style>
    * { box-sizing: border-box; }
    .m-content-container {
        padding: 1rem;
        max-width: 600px;
        margin: 0 auto;
        animation: fadeIn 0.4s ease-out;
    }

    @media (max-width: 480px) {
        .m-content-container {
            padding: 0.75rem;
        }
        
        .m-history-group {
            margin-left: -0.5rem;
            margin-right: -0.5rem;
            padding: 1rem;
            border-radius: 16px;
        }
        
        .m-history-children {
            margin-left: 1.5rem;
            padding-left: 1rem;
        }
        
        .m-level-1::before, .m-level-2::before {
            left: -1.5rem;
            width: 1.5rem;
        }
        
        .m-level-1::after, .m-level-2::after {
            left: -1.5rem;
        }
        
        .m-history-item {
            padding: 1.25rem;
        }
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes slideInHierarchy {
        from { opacity: 0; transform: translateX(-10px) scale(0.95); }
        to { opacity: 1; transform: translateX(0) scale(1); }
    }

    .m-history-group {
        animation: fadeIn 0.5s ease-out;
    }

    .m-level-1 {
        animation: slideInHierarchy 0.4s ease-out 0.1s both;
    }

    .m-level-2 {
        animation: slideInHierarchy 0.4s ease-out 0.2s both;
    }

    /* Back Header */
    .m-back-header {
        margin-bottom: 1rem;
    }
    .m-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-secondary);
        text-decoration: none;
        font-weight: 600;
        font-size: 0.875rem;
        background: var(--bg-surface);
        padding: 0.625rem 1rem;
        border-radius: 14px;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm);
    }

    /* Student Hero */
    .m-history-hero {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-xl);
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-md);
    }

    .m-history-avatar {
        width: 64px;
        height: 64px;
        border-radius: 20px;
        object-fit: cover;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    }

    .m-history-avatar-placeholder {
        width: 64px;
        height: 64px;
        border-radius: 20px;
        background: var(--gradient-brand);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 1.25rem;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    }

    .m-history-student-info {
        flex: 1;
        min-width: 0;
    }

    .m-history-student-name {
        font-family: 'Outfit', sans-serif;
        font-size: 1.125rem;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 0.125rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .m-history-student-meta {
        font-size: 0.75rem;
        color: var(--text-muted);
        font-weight: 600;
    }

    /* Timeline Section */
    .m-timeline-container {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        position: relative;
    }

    /* Cada item de histórico como um card */
    .m-history-item {
        position: relative;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .m-history-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .m-history-author {
        display: flex;
        align-items: center;
        gap: 0.625rem;
    }

    .m-author-img {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        object-fit: cover;
    }

    .m-author-placeholder {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        background: var(--bg-surface-2nd);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-muted);
    }

    .m-author-details {
        display: flex;
        flex-direction: column;
    }

    .m-author-name {
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }

    .m-author-role {
        font-size: 0.6875rem;
        color: var(--text-muted);
        text-transform: uppercase;
        font-weight: 600;
    }

    /* Badges de Categoria Mobile */
    .m-category-badge {
        padding: 0.25rem 0.625rem;
        border-radius: 10px;
        font-size: 0.625rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .m-badge-aula { background: #dbeafe; color: #1e40af; }
    .m-badge-conselho { background: #f3e8ff; color: #6b21a8; }
    .m-badge-geral { background: #fef3c7; color: #92400e; }
    .m-badge-atendimento { background: #dcfce7; color: #14532d; }
    .m-badge-sancao { background: #fee2e2; color: #991b1b; }

    .m-badge-status-demandas { background: #fef3c7; color: #92400e; }
    .m-badge-status-aberto { background: #dbeafe; color: #1e40af; }
    .m-badge-status-em-atendimento { background: #f3e8ff; color: #6b21a8; }
    .m-badge-status-finalizado { background: #dcfce7; color: #14532d; }

    /* Destaque para o card de sanção — Informação Crítica */
    .m-history-item-sancao {
        background: linear-gradient(135deg, #fff5f5, #ffffff) !important;
        border: 2px solid #fca5a5 !important;
        border-left: 6px solid #ef4444 !important;
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.1) !important;
        position: relative;
        overflow: hidden;
    }

    .m-history-item-sancao::before {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, transparent 50%, rgba(239, 68, 68, 0.05) 50%);
        pointer-events: none;
    }

    .m-history-item-sancao .m-history-body {
        color: #7f1d1d;
        font-weight: 500;
    }

    .m-history-body {
        font-size: 0.9375rem;
        color: var(--text-secondary);
        line-height: 1.6;
        white-space: pre-wrap;
    }

    .m-history-footer {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-muted);
        font-size: 0.75rem;
        font-weight: 600;
        margin-top: 0.25rem;
        padding-top: 0.75rem;
        border-top: 1px dashed var(--border-color);
    }

    .m-empty-history {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-muted);
    }

    /* Comment Actions */
    .m-history-item-actions {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px dashed var(--border-color);
        display: flex;
        justify-content: flex-end;
    }

    .m-btn-delete-small {
        background: transparent;
        border: none;
        color: #ef4444;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .m-btn-delete-small:active {
        background: #fef2f2;
    }

    /* New Comment Form */
    .m-new-comment-container {
        display: block;
        background: var(--bg-card);
        border-radius: 20px;
        border: 2px solid rgba(79, 70, 229, 0.15);
        padding: 1rem;
        margin-bottom: 2rem;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .m-comment-input {
        width: 100%;
        min-height: 60px;
        border: 1px solid var(--border-color);
        background: #f8fafc;
        border-radius: 12px;
        padding: 0.875rem;
        font-family: inherit;
        font-size: 1rem;
        color: var(--text-primary);
        resize: none;
        outline: none;
        margin-bottom: 1rem;
        transition: border-color 0.2s;
    }

    .m-comment-input:focus {
        border-color: var(--color-primary);
    }

    .m-form-actions {
        display: flex;
        gap: 0.75rem;
    }

    .m-btn-primary { background: var(--color-primary); color: white; border: none; }
    .m-btn-secondary { background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-secondary); }

    /* Hierarquia e Conectores Refatorados - Layout Correto */
    .m-history-tree {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .m-history-branch {
        display: flex;
        flex-direction: column;
        position: relative;
    }

    /* Container dos filhos - conectado ao pai via borda */
    .m-history-children {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        margin-left: 1.5rem;
        padding-left: 1.25rem;
        position: relative;
        margin-top: 0.5rem;
        padding-top: 0.5rem;
    }

    /* Linha vertical principal da hierarquia */
    .m-history-children::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(to bottom, #6366f1 0%, #a5b4fc 50%, #c4b5fd 100%);
    }

    /* Connector horizontal do pai para os filhos */
    .m-history-children::after {
        content: "";
        position: absolute;
        left: 0;
        top: -0.75rem;
        width: 1.25rem;
        height: 2px;
        background: linear-gradient(to right, #6366f1, #a5b4fc);
    }

    /* Card do comentário */
    .m-history-item {
        position: relative;
        border-radius: 12px;
        padding: 1rem;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
    }

    /* Item raiz (nível 0) */
    .m-history-tree > .m-history-branch > .m-history-item:first-child {
        background: linear-gradient(135deg, #f8fafc, #ffffff);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-left: 4px solid #6366f1;
    }

    /* Todos os filhos (nível 1 e 2) - cor de atendimento discreta */
    .m-history-children > .m-history-branch > .m-history-item,
    .m-history-children .m-history-children > .m-history-branch > .m-history-item {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-left: 4px solid #64748b;
    }

    /* Nível 1 - primeiro filho */
    .m-history-children > .m-history-branch > .m-history-item {
        padding: 0.875rem;
    }

    /* Nível 2 - neto */
    .m-history-children .m-history-children > .m-history-branch > .m-history-item {
        padding: 0.75rem;
        margin-left: 0;
    }

    /* Container dos filhos nível 2 - mais próximo do nível 1 */
    .m-history-children .m-history-children {
        gap: 0.75rem;
        margin-left: 1rem;
        padding-left: 0.875rem;
    }

    /* Ajuste do autor para níveis inferiores */
    .m-level-1 .m-author-img,
    .m-level-1 .m-author-placeholder {
        width: 28px;
        height: 28px;
    }

    .m-level-2 .m-author-img,
    .m-level-2 .m-author-placeholder {
        width: 24px;
        height: 24px;
        font-size: 0.625rem;
    }

    .m-level-2 .m-author-name {
        font-size: 0.8125rem;
    }

    .m-level-2 .m-author-role {
        font-size: 0.625rem;
    }

    .m-level-2 .m-history-body {
        font-size: 0.8125rem;
    }

    .m-level-2 .m-category-badge {
        font-size: 0.5625rem;
        padding: 0.2rem 0.5rem;
    }

    /* Refinamentos: cards menores para filhos */
    .m-level-1 .m-history-header {
        gap: 0.375rem;
    }

    .m-level-1 .m-history-body {
        font-size: 0.875rem;
    }

    .m-level-1 .m-history-footer {
        font-size: 0.6875rem;
        padding-top: 0.5rem;
    }

    .m-level-2 .m-history-header {
        gap: 0.25rem;
        flex-wrap: wrap;
    }

    .m-level-2 .m-author-details {
        gap: 0;
    }

    .m-level-2 .m-history-body {
        font-size: 0.8125rem;
        line-height: 1.5;
    }

    .m-level-2 .m-history-footer {
        font-size: 0.625rem;
        padding-top: 0.375rem;
    }

    .m-level-2 .m-history-item-actions {
        padding-top: 0.5rem;
    }

    /* Reduzir badge de categoria nos níveis filhos */
    .m-level-1 .m-category-badge {
        font-size: 0.5rem;
        padding: 0.15rem 0.375rem;
    }

    .m-level-2 .m-category-badge {
        font-size: 0.5rem;
        padding: 0.15rem 0.375rem;
    }

    /* Badge de status do atendimento - tamanho reduzido */
    .m-badge-status-demandas,
    .m-badge-status-aberto,
    .m-badge-status-em-atendimento,
    .m-badge-status-finalizado {
        font-size: 0.5rem;
        padding: 0.15rem 0.375rem;
    }

    /* Nome do autor com truncate para níveis filhos */
    .m-author-name {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .m-level-1 .m-author-name {
        max-width: 120px;
    }

    /* Atendimento arquivado - visual mais apagado/esmaecido */
    .m-history-item[data-is-archived="1"] {
        opacity: 0.4;
        filter: grayscale(50%);
        background: linear-gradient(135deg, #f1f5f9, #e2e8f0) !important;
        border-color: rgba(148, 163, 184, 0.15) !important;
        border-left-color: #94a3b8 !important;
    }

    .m-history-item[data-is-archived="1"] .m-history-body {
        color: var(--text-muted);
    }

    .m-history-item[data-is-archived="1"] .m-author-name {
        color: var(--text-muted);
    }

    .m-history-item[data-is-archived="1"] .m-category-badge {
        opacity: 0.6;
        text-decoration: line-through;
    }

    .m-history-item[data-is-archived="1"] .m-history-footer {
        opacity: 0.5;
    }

    /* Tag de status para atendimentos nos níveis 1 e 2 */
    .m-atendimento-status-tag {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        padding: 0.2rem 0.5rem;
        border-radius: 8px;
        font-size: 0.625rem;
        font-weight: 700;
        text-transform: uppercase;
        max-width: 70px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .m-history-item {
        position: relative;
    }

    .m-history-body b, .m-history-body strong { font-weight: 700; }
    .m-history-body i, .m-history-body em { font-style: italic; }
    .m-history-body u { text-decoration: underline; }
    .m-history-body s { text-decoration: line-through; }
    .m-history-body ul, .m-history-body ol { padding-left: 1.25rem; margin: 0.5rem 0; }
    .m-history-body li { margin-bottom: 0.25rem; }
    .m-history-body p { margin: 0.5rem 0; }
    .m-history-body a { color: var(--color-primary); text-decoration: underline; }
    .m-history-body code { background: #f1f5f9; padding: 0.125rem 0.25rem; border-radius: 4px; font-family: monospace; font-size: 0.85em; }
    .m-history-body blockquote { border-left: 3px solid #cbd5e1; padding-left: 0.75rem; margin: 0.5rem 0; color: var(--text-muted); }
    .m-history-body br { display: block; content: ""; margin-top: 0.5rem; }
    .m-history-body .mention { background: #e0e7ff; color: #3730a3; padding: 0.125rem 0.375rem; border-radius: 4px; font-weight: 600; font-size: 0.9em; }
</style>

<div class="m-content-container">
    
    <!-- Back Header -->
    <div class="m-back-header">
        <a href="/mobile/alunos.php?turma_id=<?= $turmaId ?>" class="m-back-btn">
            <span>‹</span> Voltar para Turma
        </a>
    </div>

    <!-- Student Hero Card -->
    <div class="m-history-hero">
        <?php if (!empty($aluno['photo'])): ?>
            <img src="/<?= htmlspecialchars($aluno['photo']) ?>" class="m-history-avatar" alt="">
        <?php else: 
            $initials = '';
            foreach (explode(' ', trim($aluno['nome'])) as $part) {
                $initials .= strtoupper(substr($part, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        ?>
            <div class="m-history-avatar-placeholder"><?= $initials ?></div>
        <?php endif; ?>
        
        <div class="m-history-student-info">
            <h1 class="m-history-student-name"><?= htmlspecialchars($aluno['nome']) ?></h1>
            <div class="m-history-student-meta">
                <span>MATRÍCULA: #<?= htmlspecialchars($aluno['matricula']) ?></span>
            </div>
        </div>
    </div>

    <?php 
    // Permissões para comentar
    $canComment = hasDbPermission('students.comments', false);
    if ($canComment): ?>
        <div class="m-new-comment-container" id="commentFormContainer">
            <form id="commentForm">
                <?= csrf_field() ?>
                <input type="hidden" name="aluno_id" value="<?= $alunoId ?>">
                <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                <input type="hidden" name="action" value="save_comment">
                
                <textarea name="conteudo" class="m-comment-input" placeholder="O que aconteceu hoje com este aluno?"></textarea>
                
                <div class="m-form-actions" style="justify-content: flex-end;">
                    <button type="submit" class="m-btn m-btn-primary" style="width:auto; height:38px; padding:0 1.25rem; font-size:0.8125rem; border-radius:12px;">
                        Publicar Agora
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>


    <div class="m-timeline-container">
        <?php if (empty($historyTree)): ?>
            <div class="m-card m-empty-history">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                <p>Nenhum registro encontrado no histórico deste aluno.</p>
            </div>
        <?php else: ?>
            <div class="m-history-tree">
            <?php 
            function renderTimelineItem($item, $level = 0) {
                global $user;
                $badgeClass = 'm-badge-geral';
                $icon = '📢';
                $badgeText = $item['categoria'];
                
                if ($item['categoria'] === 'Aula') { $badgeClass = 'm-badge-aula'; $icon = '📝'; }
                if ($item['categoria'] === 'Conselho') { $badgeClass = 'm-badge-conselho'; $icon = '🤝'; }
                if ($item['categoria'] === 'Atendimento') { 
                    $atendStatus = $item['atendimento_status'] ?? 'Aberto';
                    $statusClass = strtolower(str_replace(' ', '-', $atendStatus));
                    $badgeClass = 'm-badge-status-' . $statusClass;
                    $icon = '📋';
                    $badgeText = $atendStatus;
                }
                if ($item['categoria'] === 'Sanção') { 
                    $badgeClass = 'm-badge-sancao'; 
                    $icon = '🛑'; 
                }
                
                $isAdmin = ($user['profile'] === 'Administrador');
                $isAuthor = ($item['autor_id'] == $user['id']);
                $canDelete = ($item['categoria'] === 'Aula' && ($isAdmin || $isAuthor));
                
                $levelClass = $level > 0 ? 'm-level-' . $level : '';
                $isArchived = (isset($item['is_archived']) && $item['is_archived'] == 1) || 
                              (isset($item['is_archived_inherited']) && $item['is_archived_inherited']) ? '1' : '0';
                
                $showStatusTag = ($level > 0 && $item['categoria'] === 'Atendimento' && !empty($item['atendimento_status']));
                if ($showStatusTag) {
                    $statusTagClass = 'm-badge-status-' . strtolower(str_replace(' ', '-', $item['atendimento_status']));
                    $statusTagText = mb_strlen($item['atendimento_status']) > 6 
                        ? mb_substr($item['atendimento_status'], 0, 6) . '...' 
                        : $item['atendimento_status'];
                }
                ?>
                <div class="m-history-branch">
                    <?php $sancaoClass = ($item['categoria'] === 'Sanção') ? 'm-history-item-sancao' : ''; ?>
                    <div class="m-history-item <?= $levelClass ?> <?= $sancaoClass ?>" data-history-id="<?= $item['id'] ?>" data-categoria="<?= $item['categoria'] ?>" data-is-archived="<?= $isArchived ?>">
                        <div class="m-history-header">
                            <div class="m-history-author">
                                <?php if (!empty($item['autor_foto'])): ?>
                                    <img src="/<?= htmlspecialchars($item['autor_foto']) ?>" class="m-author-img" alt="">
                                <?php else: ?>
                                    <div class="m-author-placeholder"><?= mb_substr($item['autor_nome'] ?? '?', 0, 1) ?></div>
                                <?php endif; ?>
                                <div class="m-author-details">
                                    <span class="m-author-name"><?= htmlspecialchars($item['autor_nome'] ?? 'Sistema') ?></span>
                                    <span class="m-author-role"><?= htmlspecialchars($item['autor_perfil'] ?? 'Automático') ?></span>
                                </div>
                            </div>
                            <?php if ($level === 0): ?>
                            <span class="m-category-badge <?= $badgeClass ?>">
                                <?= $icon ?> <?= htmlspecialchars($badgeText) ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($showStatusTag): ?>
                            <span class="m-atendimento-status-tag <?= $statusTagClass ?>">
                                <?= htmlspecialchars($statusTagText) ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="m-history-body"><?= safeHtml(trim($item['texto'] ?? '')) ?></div>

                        <div class="m-history-footer">
                            <span>📅 <?= date('d/m/Y \à\s H:i', strtotime($item['data_registro'])) ?></span>
                        </div>

                        <?php if ($canDelete): ?>
                            <div class="m-history-item-actions">
                                <button class="m-btn-delete-small" onclick="deleteComment(<?= $item['id'] ?>)">
                                    <span>🗑️</span> Excluir
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($item['children'])): ?>
                        <div class="m-history-children">
                            <?php foreach ($item['children'] as $child): ?>
                                <?php renderTimelineItem($child, $level + 1); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php 
            }

            foreach ($historyTree as $item) {
                renderTimelineItem($item);
            }
            ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>

<script>

document.getElementById('commentForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const txt = e.target.querySelector('textarea');
    
    if(!txt.value.trim()) return;

    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Publicando...';

    const formData = new FormData(e.target);
    
    try {
        const res = await fetch('/api/comments.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        const data = await res.json();
        if(data.success) {
            location.reload();
        } else {
            alert(data.error || 'Erro ao publicar');
        }
    } catch(err) {
        alert('Erro de conexão');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

async function deleteComment(id) {
    if(!confirm('Deseja excluir permanentemente este registro?')) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const formData = new FormData();
    formData.append('action', 'delete_comment');
    formData.append('comment_id', id);
    formData.append('csrf_token', csrfToken);

    try {
        const res = await fetch('/api/comments.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if(data.success) {
            const item = document.querySelector(`[data-history-id="${id}"][data-categoria="Aula"]`);
            if (item) {
                item.style.opacity = '0';
                item.style.transform = 'scale(0.9)';
                setTimeout(() => item.remove(), 300);
            } else {
                location.reload();
            }
        } else {
            alert(data.error || 'Erro ao excluir');
        }
    } catch(err) {
        alert('Erro de conexão');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.m-history-body').forEach(function(el) {
        var html = el.innerHTML;
        html = html.replace(/(@[a-zA-ZÀ-ÿ0-9_]+)/g, '<span class="mention">$1</span>');
        el.innerHTML = html;
    });
});
</script>
