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
$history = $alunoService->getMultidisciplinaryHistory($alunoId);

/**
 * Organiza o histórico em árvore para exibir aninhamento (Encaminhamento -> Atendimento -> Comentário)
 */
function buildHistoryTree(array $flatItems): array {
    $itemMap = [];
    $tree = [];

    // Primeiro mapeia todos por unique_id
    foreach ($flatItems as $item) {
        $item['children'] = [];
        $itemMap[$item['unique_id']] = $item;
    }

    // Depois vincula aos pais
    foreach ($itemMap as $id => &$item) {
        if ($item['parent_unique_id'] && isset($itemMap[$item['parent_unique_id']])) {
            $itemMap[$item['parent_unique_id']]['children'][] = &$item;
        } else {
            $tree[] = &$item;
        }
    }
    return $tree;
}

$historyTree = buildHistoryTree($history);


$pageTitle = "Histórico: " . $aluno['nome'];
$currentPage = 'cursos';
require_once __DIR__ . '/header.php';
?>

<style>
    * { box-sizing: border-box; }
    .m-content-container {
        padding: 0.75rem;
        max-width: 600px;
        margin: 0 auto;
        animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
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

    /* Hierarquia e Conectores Refatorados (V3) */
    .m-history-group {
        display: flex;
        flex-direction: column;
        margin-bottom: 1.5rem;
        position: relative;
    }

    .m-history-children {
        margin-left: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-top: 0.5rem;
        position: relative;
        border-left: 2px solid var(--border-color);
        padding-left: 1rem;
    }

    /* Linha que sai do pai para os filhos */
    .m-history-children::before {
        content: "";
        position: absolute;
        left: -2px;
        top: -1rem;
        height: 1rem;
        width: 2px;
        background: var(--border-color);
    }

    .m-history-item {
        position: relative;
        margin-bottom: 0 !important; /* Remove margem padrão para evitar conflitos */
    }

    /* Conector Horizontal em cada item filho */
    .m-level-1::before, .m-level-2::before {
        content: "";
        position: absolute;
        left: -1rem;
        top: 1.5rem;
        width: 1rem;
        height: 2px;
        background: var(--border-color);
    }

    /* Cards de Nível Inferior - Estilização */
    .m-level-1 {
        background: var(--bg-surface);
        border-color: rgba(79, 70, 229, 0.2);
    }

    .m-level-2 {
        background: var(--bg-surface-2nd);
        border-color: var(--border-color);
        box-shadow: none; /* Simplifica para evitar bugs de overlap */
        transform: scale(0.98);
        transform-origin: left;
    }

    /* Texto e Autor em Comentários */
    .m-level-2 .m-history-body {
        font-size: 0.8125rem;
    }

    .m-level-2 .m-author-img, .m-level-2 .m-author-placeholder {
        width: 24px;
        height: 24px;
        font-size: 0.6rem;
    }
    
    .m-level-2 .m-author-name {
        font-size: 0.75rem;
    }
    
    .m-level-2 .m-author-role {
        font-size: 0.6rem;
    }
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
            <?php 
            function renderTimelineItem($item, $level = 0) {
                global $user;
                $badgeClass = 'm-badge-geral';
                $icon = '📢';
                if ($item['categoria'] === 'Aula') { $badgeClass = 'm-badge-aula'; $icon = '📝'; }
                if ($item['categoria'] === 'Conselho') { $badgeClass = 'm-badge-conselho'; $icon = '🤝'; }
                if ($item['categoria'] === 'Atendimento') { $badgeClass = 'm-badge-atendimento'; $icon = '🔒'; }
                
                $isAdmin = ($user['profile'] === 'Administrador');
                $isAuthor = ($item['autor_id'] == $user['id']);
                $canDelete = ($item['categoria'] === 'Aula' && ($isAdmin || $isAuthor));
                
                $levelClass = $level > 0 ? 'm-level-' . $level : '';
                ?>
                <div class="m-history-group">
                    <div class="m-card m-history-item <?= $levelClass ?>" data-history-id="<?= $item['id'] ?>" data-categoria="<?= $item['categoria'] ?>">
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
                            <span class="m-category-badge <?= $badgeClass ?>">
                                <?= $icon ?> <?= htmlspecialchars($item['categoria']) ?>
                            </span>
                        </div>

                        <div class="m-history-body"><?= nl2br(htmlspecialchars(trim(html_entity_decode(strip_tags($item['texto'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')))) ?></div>

                        <div class="m-history-footer">
                            <span>📅 <?= date('d/m/Y \à\s H:i', strtotime($item['data_registro'])) ?></span>
                        </div>

                        <?php if ($canDelete): ?>
                            <div class="m-history-item-actions">
                                <button class="m-btn-delete-small" onclick="deleteComment(<?= $item['id'] ?>)">
                                    <span>🗑️</span> Excluir Registro
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
</script>
