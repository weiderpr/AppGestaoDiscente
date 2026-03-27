<?php
/**
 * Vértice Acadêmico — Alunos da Turma (Mobile)
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = $inst['id'] ?? 0;

$turmaId = (int)($_GET['turma_id'] ?? 0);
if (!$turmaId || !$instId) {
    header('Location: /mobile/courses.php');
    exit;
}

$db = getDB();

// Valida turma e curso
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

// Segurança extra: Verifica se o usuário tem vínculo com esta turma
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

$isAdmin = ($user['profile'] === 'Administrador');
$isSpecialProfile = in_array($user['profile'], ['Pedagogo', 'Assistente Social', 'Psicólogo']);

if (!$isAdmin && !$isCourseCoordinator && !$isTeacherOfThisTurma && !$isSpecialProfile) {
    header('Location: /mobile/courses.php');
    exit;
}

$search = trim($_GET['search'] ?? '');

// ---- LISTAR ALUNOS ----
$sql = "
    SELECT a.*
    FROM alunos a
    JOIN turma_alunos ta ON a.id = ta.aluno_id
    WHERE ta.turma_id = ? AND a.deleted_at IS NULL
";
$params = [$turmaId];

if ($search) {
    $sql .= " AND (a.nome LIKE ? OR a.matricula LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY a.nome ASC";
$st = $db->prepare($sql);
$st->execute($params);
$alunos = $st->fetchAll();

$pageTitle = 'Alunos — ' . $turma['description'];
$currentPage = 'cursos';
require_once __DIR__ . '/header.php';
?>

<style>
    .m-back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--color-primary);
        font-weight: 600;
        font-size: 0.875rem;
        text-decoration: none;
        margin-bottom: 1.5rem;
    }
    .m-aluno-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 1rem 1.25rem;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        margin-bottom: 0.75rem;
        transition: transform 0.2s;
    }
    .m-aluno-card:active { transform: scale(0.98); }
    
    .m-aluno-photo {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        border: 2px solid var(--border-color);
    }
    .m-aluno-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: var(--gradient-brand);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.125rem;
        flex-shrink: 0;
    }
    
    .m-aluno-info {
        flex: 1;
        min-width: 0;
    }
    .m-aluno-name {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 1rem;
        color: var(--text-primary);
        margin-bottom: 0.125rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .m-aluno-meta {
        font-size: 0.75rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .m-aluno-arrow {
        font-size: 1rem;
        color: var(--text-muted);
        opacity: 0.3;
    }
</style>

<div class="m-content">
    
    <a href="/mobile/turmas.php?course_id=<?= $turma['course_id'] ?>" class="m-back-link">
        <span>←</span> Voltar para Turmas
    </a>

    <header style="margin-bottom: 1.5rem;">
        <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-primary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">
            <?= htmlspecialchars($turma['course_name']) ?> • <?= htmlspecialchars($turma['description']) ?>
        </div>
        <h1 class="m-section-title" style="margin-bottom: 0.5rem;">Lista de Alunos</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted);"><?= count($alunos) ?> alunos matriculados nesta turma</p>
    </header>

    <!-- Busca -->
    <form action="" method="GET" style="margin-bottom: 1.5rem;">
        <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
        <div class="m-search-box" style="margin-bottom:0;">
            <span>🔍</span>
            <input type="text" name="search" class="m-search-input" placeholder="Nome ou matrícula..." value="<?= htmlspecialchars($search) ?>">
            <?php if($search): ?>
                <a href="alunos.php?turma_id=<?= $turmaId ?>" style="text-decoration:none; color:var(--text-muted);">✕</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="m-aluno-list">
        <?php if (empty($alunos)): ?>
            <div class="m-empty-state">
                <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                <p>Nenhum aluno encontrado nesta turma.</p>
                <?php if($search): ?>
                    <a href="alunos.php?turma_id=<?= $turmaId ?>" style="color:var(--color-primary); font-weight:600; margin-top:0.5rem; display:inline-block;">Limpar busca</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($alunos as $a): ?>
                <a href="/mobile/aluno_detalhe.php?aluno_id=<?= $a['id'] ?>&turma_id=<?= $turmaId ?>" class="m-aluno-card">
                    <?php if (!empty($a['photo'])): ?>
                        <img src="/<?= htmlspecialchars($a['photo'] ?? '') ?>" alt="" class="m-aluno-photo">
                    <?php else: 
                        $initials = '';
                        foreach (explode(' ', trim($a['nome'])) as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                            if (strlen($initials) >= 2) break;
                        }
                    ?>
                        <div class="m-aluno-avatar"><?= $initials ?></div>
                    <?php endif; ?>
                    
                    <div class="m-aluno-info">
                        <div class="m-aluno-name"><?= htmlspecialchars($a['nome']) ?></div>
                        <div class="m-aluno-meta">
                            <span>#<?= $a['matricula'] ?></span>
                            <?php if($a['telefone']): ?>
                                <span>• 📞 <?= htmlspecialchars($a['telefone']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="m-aluno-arrow">›</div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
