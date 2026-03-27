<?php
/**
 * Vértice Acadêmico — Alunos da Turma (Mobile)
 * UI Refatorada para Excelência Visual
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = $inst['id'];

$turmaId = (int)($_GET['turma_id'] ?? 0);
if (!$turmaId) {
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

// Permissões via Matriz + Vínculos
$isAdmin = ($user['profile'] === 'Administrador');
$canViewStudents = hasDbPermission('students.index', false);

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

if (!$isAdmin && !$canViewStudents && !$isCourseCoordinator && !$isTeacherOfThisTurma) {
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

$pageTitle = $turma['description'];
$currentPage = 'cursos';
require_once __DIR__ . '/header.php';
?>

<style>
    .m-header-details {
        margin-bottom: 0.75rem;
    }
    
    .m-breadcrumbs {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
    }

    .m-breadcrumbs a { color: var(--color-primary); }

    .m-aluno-list-new {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .m-aluno-card-new {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        text-decoration: none;
        padding: 1.25rem;
    }

    .m-aluno-avatar-box {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        border: 2px solid var(--border-color);
        box-shadow: var(--shadow-sm);
    }

    .m-aluno-avatar-text {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: var(--gradient-brand);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.125rem;
        flex-shrink: 0;
        box-shadow: var(--shadow-sm);
    }
    
    .m-aluno-body {
        flex: 1;
        min-width: 0;
    }

    .m-aluno-name-text {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 1.0625rem;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .m-aluno-meta-text {
        font-size: 0.8125rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .m-aluno-arrow-text {
        font-size: 1.5rem;
        color: var(--text-muted);
        opacity: 0.3;
    }
</style>

<div class="m-content">
    
    <div class="m-header-details">
        <div class="m-breadcrumbs">
            <a href="/mobile/courses.php">Cursos</a>
            <span>/</span>
            <a href="/mobile/turmas.php?course_id=<?= $turma['course_id'] ?>">Turmas</a>
            <span>/</span>
            <span>Alunos</span>
        </div>
        <h1 class="m-section-title" style="margin-bottom: 0.5rem;">Lista de Alunos</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted);"><?= count($alunos) ?> alunos encontrados.</p>
    </div>

    <!-- Busca Standardizada -->
    <form action="" method="GET">
        <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
        <div class="m-search-box">
            <span>🔍</span>
            <input type="text" name="search" class="m-search-input" placeholder="Nome ou matrícula..." value="<?= htmlspecialchars($search) ?>">
            <?php if($search): ?>
                <a href="alunos.php?turma_id=<?= $turmaId ?>" style="text-decoration:none; color:var(--text-muted); padding-right:0.5rem;">✕</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="m-aluno-list-new">
        <?php if (empty($alunos)): ?>
            <div class="m-card" style="text-align:center; padding: 4rem 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                <p style="color:var(--text-muted);">Nenhum aluno encontrado nesta turma.</p>
                <?php if($search): ?>
                    <a href="alunos.php?turma_id=<?= $turmaId ?>" style="color:var(--color-primary); font-weight:600; margin-top:1rem; display:inline-block;">Limpar busca</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($alunos as $a): ?>
                <a href="/mobile/aluno_detalhe.php?aluno_id=<?= $a['id'] ?>&turma_id=<?= $turmaId ?>" class="m-card m-aluno-card-new">
                    <?php if (!empty($a['photo'])): ?>
                        <img src="/<?= htmlspecialchars($a['photo']) ?>" alt="" class="m-aluno-avatar-box">
                    <?php else: 
                        $initials = '';
                        foreach (explode(' ', trim($a['nome'])) as $part) {
                            if(!empty($part)) $initials .= strtoupper(substr($part, 0, 1));
                            if (strlen($initials) >= 2) break;
                        }
                    ?>
                        <div class="m-aluno-avatar-text"><?= $initials ?></div>
                    <?php endif; ?>
                    
                    <div class="m-aluno-body">
                        <div class="m-aluno-name-text"><?= htmlspecialchars($a['nome']) ?></div>
                        <div class="m-aluno-meta-text">
                            <span>#<?= $a['matricula'] ?></span>
                            <?php if($a['telefone']): ?>
                                <span>• 📞 <?= htmlspecialchars($a['telefone']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="m-aluno-arrow-text">›</div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
