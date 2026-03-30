<?php
/**
 * Vértice Acadêmico — Detalhes do Aluno (Mobile)
 * UI Refatorada para Excelência Visual
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/AlunoService.php';

requireLogin();

$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = $inst['id'];

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

// Security Check via Matriz
$isAdmin = ($user['profile'] === 'Administrador');
$canComment = hasDbPermission('students.comments', false);

// Vínculos específicos para Professores e Coordenadores
$isCourseCoordinator = false;
if ($user['profile'] === 'Coordenador') {
    $stCheck = $db->prepare("SELECT 1 FROM course_coordinators WHERE course_id = ? AND user_id = ?");
    $stCheck->execute([$turma['course_id'], $user['id']]);
    $isCourseCoordinator = (bool)$stCheck->fetch();
}

$isTeacherOfThisTurma = false;
if (($user['is_teacher'] ?? 0) == 1) {
    $stCheckT = $db->prepare("
        SELECT 1 FROM turma_disciplinas td 
        JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id 
        WHERE td.turma_id = ? AND tdp.professor_id = ? LIMIT 1
    ");
    $stCheckT->execute([$turmaId, $user['id']]);
    $isTeacherOfThisTurma = (bool)$stCheckT->fetch();
}

if (!$isAdmin && !$isCourseCoordinator && !$isTeacherOfThisTurma && !$canComment) {
    header('Location: /mobile/courses.php');
    exit;
}

$comments = $alunoService->getComentarios($alunoId, $turmaId);

$pageTitle = $aluno['nome'];
$currentPage = 'cursos';
require_once __DIR__ . '/header.php';
?>

<style>
    /* Design System Tokens */
    :root {
        --card-radius: 28px;
        --bubble-radius: 20px;
        --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        --shadow-soft: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
    }

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

    /* Back Button */
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
    }

    /* Student Profile Hero */
    .m-profile-hero {
        background: var(--bg-surface);
        border-radius: var(--card-radius);
        padding: 1rem 0.75rem;
        text-align: center;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-soft);
        margin-bottom: 0.75rem;
        position: relative;
        overflow: hidden;
    }

    .m-profile-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 80px;
        background: var(--primary-gradient);
        opacity: 0.08;
    }

    .m-avatar-wrapper {
        position: relative;
        width: 95px;
        height: 95px;
        margin: 0 auto 0.75rem;
        z-index: 1;
    }

    .m-avatar-lg {
        width: 95px;
        height: 95px;
        border-radius: 32px;
        object-fit: cover;
        border: 4px solid var(--bg-surface);
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.2);
    }

    .m-avatar-placeholder {
        width: 95px;
        height: 95px;
        border-radius: 32px;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
        font-weight: 800;
        border: 4px solid var(--bg-surface);
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.2);
    }

    .m-student-name {
        font-family: 'Outfit', sans-serif;
        font-size: 1.625rem;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        letter-spacing: -0.02em;
    }

    .m-student-meta-badges {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .m-badge-outline {
        padding: 0.375rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 700;
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        background: var(--bg-body);
    }

    .m-badge-primary {
        background: var(--color-primary-light);
        color: var(--color-primary);
        border: 1px solid transparent;
    }

    /* Info Grid */
    .m-info-section {
        margin-bottom: 0.75rem;
    }

    .m-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding: 0 0.5rem;
    }

    .m-section-title-alt {
        font-family: 'Outfit', sans-serif;
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 0.625rem;
    }

    .m-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .m-info-card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        padding: 0.875rem 1rem;
        border-radius: 20px;
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .m-info-label {
        font-size: 0.6875rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .m-info-value {
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    /* Comentários */
    .m-comment-feed {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .m-comment-item {
        background: var(--bg-surface);
        border-radius: var(--bubble-radius);
        padding: 1rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        animation: slideUp 0.3s ease-out;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .m-comment-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.375rem;
    }

    .m-comment-user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        background: var(--color-primary-light);
        color: var(--color-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.875rem;
    }

    .m-comment-user-info {
        flex: 1;
    }

    .m-comment-author-name {
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .m-comment-timestamp {
        font-size: 0.6875rem;
        color: var(--text-muted);
    }

    .m-comment-body {
        font-size: 0.9375rem;
        color: var(--text-secondary);
        line-height: 1.6;
        white-space: pre-wrap;
    }

    .m-comment-actions {
        margin-top: 0.875rem;
        padding-top: 0.75rem;
        border-top: 1px dashed var(--border-color);
        display: flex;
        justify-content: flex-end;
    }

    .m-btn-delete {
        background: transparent;
        border: none;
        color: #ef4444;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.25rem 0.5rem;
        border-radius: 8px;
    }

    /* Comment Form Container */
    .m-new-comment-container {
        display: none;
        background: var(--bg-surface);
        border-radius: 24px;
        border: 1px solid var(--color-primary);
        padding: 1.25rem;
        margin-bottom: 2rem;
        box-shadow: 0 12px 30px rgba(99, 102, 241, 0.15);
        animation: slideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .m-comment-input {
        width: 100%;
        min-height: 120px;
        border: none;
        background: transparent;
        font-family: inherit;
        font-size: 1rem;
        color: var(--text-primary);
        resize: none;
        outline: none;
        margin-bottom: 1rem;
    }

    .m-form-actions {
        display: flex;
        gap: 0.75rem;
    }

    /* Placeholder Empty State */
    .m-empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--bg-surface);
        border-radius: var(--card-radius);
        border: 1px dashed var(--border-color);
        color: var(--text-muted);
    }

    .m-empty-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        display: block;
    }
</style>

<div class="m-content-container">
    
    <!-- Back Header -->
    <div class="m-back-header">
        <a href="/mobile/alunos.php?turma_id=<?= $turmaId ?>" class="m-back-btn">
            <span>‹</span> Voltar
        </a>
    </div>

    <!-- Profile Hero Card -->
    <div class="m-profile-hero">
        <div class="m-avatar-wrapper">
            <?php if (!empty($aluno['photo'])): ?>
                <img src="/<?= htmlspecialchars($aluno['photo']) ?>" class="m-avatar-lg" alt="<?= htmlspecialchars($aluno['nome']) ?>">
            <?php else: 
                $initials = '';
                foreach (explode(' ', trim($aluno['nome'])) as $part) {
                    $initials .= strtoupper(substr($part, 0, 1));
                    if (strlen($initials) >= 2) break;
                }
            ?>
                <div class="m-avatar-placeholder"><?= $initials ?></div>
            <?php endif; ?>
        </div>

        <h1 class="m-student-name"><?= htmlspecialchars($aluno['nome']) ?></h1>
        
        <div class="m-student-meta-badges">
            <span class="m-badge-outline m-badge-primary">ID: <?= htmlspecialchars($aluno['matricula']) ?></span>
            <span class="m-badge-outline">Ativo</span>
        </div>
    </div>

    <!-- Details Section -->
    <div class="m-info-section">
        <div class="m-section-header">
            <h2 class="m-section-title-alt">
                <span>📋</span> Informações Gerais
            </h2>
        </div>
        <div class="m-info-grid">
            <div class="m-info-card">
                <span class="m-info-label">Turma</span>
                <span class="m-info-value"><?= htmlspecialchars($turma['description']) ?></span>
            </div>
            <div class="m-info-card">
                <span class="m-info-label">Contato</span>
                <span class="m-info-value"><?= $aluno['telefone'] ? htmlspecialchars($aluno['telefone']) : '—' ?></span>
            </div>
        </div>
    </div>

    <!-- Comments Section -->
    <div class="m-info-section">
        <div class="m-section-header">
            <h2 class="m-section-title-alt">
                <span>💬</span> Observações
            </h2>
            <?php if ($canComment): ?>
                <button onclick="toggleCommentForm(true)" class="m-btn" style="height:36px; padding:0 1rem; border-radius:12px; background:var(--color-primary); color:white; border:none; font-size:0.8125rem;">
                    + Novo
                </button>
            <?php endif; ?>
        </div>

        <!-- New Comment Form -->
        <div class="m-new-comment-container" id="commentFormContainer">
            <form id="commentForm">
                <?= csrf_field() ?>
                <input type="hidden" name="aluno_id" value="<?= $alunoId ?>">
                <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                <input type="hidden" name="action" value="save_comment">
                
                <textarea name="conteudo" class="m-comment-input" placeholder="Escreva sua observação pedagógica aqui..."></textarea>
                
                <div class="m-form-actions">
                    <button type="button" onclick="toggleCommentForm(false)" class="m-btn" style="flex:1; background:var(--bg-body); border:1px solid var(--border-color); color:var(--text-secondary); height:48px;">
                        Cancelar
                    </button>
                    <button type="submit" class="m-btn" style="flex:2; background:var(--color-primary); color:white; border:none; height:48px;">
                        Salvar Registro
                    </button>
                </div>
            </form>
        </div>

        <!-- Comment List -->
        <div class="m-comment-feed" id="commentList">
            <?php if (empty($comments)): ?>
                <div class="m-empty-state">
                    <span class="m-empty-icon">📝</span>
                    <p>Nenhuma observação registrada para este aluno nesta turma.</p>
                </div>
            <?php else: ?>
                <?php foreach ($comments as $c): ?>
                    <div class="m-comment-item" data-comment-id="<?= $c['id'] ?>">
                        <div class="m-comment-header">
                            <div class="m-comment-user-avatar">
                                <?= substr($c['professor_name'], 0, 1) ?>
                            </div>
                            <div class="m-comment-user-info">
                                <div class="m-comment-author-name"><?= htmlspecialchars($c['professor_name']) ?></div>
                                <div class="m-comment-timestamp"><?= date('d M Y • H:i', strtotime($c['created_at'])) ?></div>
                            </div>
                        </div>
                        <div class="m-comment-body"><?= nl2br(htmlspecialchars(html_entity_decode(strip_tags($c['conteudo']), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?></div>
                        
                        <?php if ($isAdmin || $c['professor_id'] == $user['id']): ?>
                            <div class="m-comment-actions">
                                <button class="m-btn-delete" onclick="deleteComment(<?= $c['id'] ?>)">Excluir Registro</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function toggleCommentForm(show) {
    const container = document.getElementById('commentFormContainer');
    container.style.display = show ? 'block' : 'none';
    if (show) {
        container.querySelector('textarea').focus();
    }
}

document.getElementById('commentForm').onsubmit = async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const txt = e.target.querySelector('textarea');
    
    if(!txt.value.trim()) return;

    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Salvando...';

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
            alert(data.error || 'Erro ao publicar comentário');
        }
    } catch(err) {
        alert('Erro de conexão');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
};

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
            const item = document.querySelector(`[data-comment-id="${id}"]`);
            item.style.opacity = '0';
            item.style.transform = 'scale(0.9)';
            setTimeout(() => item.remove(), 300);
        } else {
            alert(data.error || 'Erro ao excluir');
        }
    } catch(err) {
        alert('Erro de conexão');
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
