<?php
/**
 * Vértice Acadêmico — Turmas do Curso (Mobile)
 * UI Refatorada para Excelência Visual
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = $inst['id'] ?? 0;

$courseId = (int)($_GET['course_id'] ?? 0);
if (!$courseId || !$instId) {
    header('Location: /mobile/courses.php');
    exit;
}

$db = getDB();

// Valida curso e acesso
$stCourse = $db->prepare("SELECT * FROM courses WHERE id = ? AND institution_id = ? AND is_active = 1");
$stCourse->execute([$courseId, $instId]);
$course = $stCourse->fetch();

if (!$course) {
    header('Location: /mobile/courses.php');
    exit;
}

// Permissões via Matriz + Vínculos
$isAdmin = ($user['profile'] === 'Administrador');
$canViewStudents = hasDbPermission('students.index', false);

$isCourseCoordinator = false;
if ($user['profile'] === 'Coordenador') {
    $stCheck = $db->prepare("SELECT 1 FROM course_coordinators WHERE course_id = ? AND user_id = ?");
    $stCheck->execute([$courseId, $user['id']]);
    $isCourseCoordinator = (bool)$stCheck->fetch();
}

$isTeacherInCourse = false;
if (($user['is_teacher'] ?? 0) == 1) {
    $stT = $db->prepare("
        SELECT 1 FROM turmas t 
        JOIN turma_disciplinas td ON t.id = td.turma_id 
        JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id 
        WHERE t.course_id = ? AND tdp.professor_id = ? LIMIT 1
    ");
    $stT->execute([$courseId, $user['id']]);
    $isTeacherInCourse = (bool)$stT->fetch();
}

// Se não for admin nem tiver permissão de visão de alunos, nem for coordenador/professor, barra.
if (!$isAdmin && !$canViewStudents && !$isCourseCoordinator && !$isTeacherInCourse) {
    header('Location: /mobile/courses.php');
    exit;
}

$search = trim($_GET['search'] ?? '');

// ---- LISTAR TURMAS ----
$sql = "
    SELECT t.*,
           (SELECT COUNT(*) FROM turma_alunos WHERE turma_id = t.id) as total_alunos
    FROM turmas t
    WHERE t.course_id = ? AND t.is_active = 1
";
$params = [$courseId];

// Filtro de professor (vê apenas onde leciona se não for coordenador/admin/pedagogo)
if (($user['is_teacher'] ?? 0) == 1 && !$isAdmin && !$canViewStudents && !$isCourseCoordinator) {
    $sql .= " AND t.id IN (
        SELECT DISTINCT t2.id
        FROM turmas t2
        JOIN turma_disciplinas td ON t2.id = td.turma_id
        JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id
        WHERE tdp.professor_id = ?
    )";
    $params[] = $user['id'];
}

if ($search) {
    $sql .= " AND (t.description LIKE ? OR t.ano LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY t.ano DESC, t.description ASC";
$st = $db->prepare($sql);
$st->execute($params);
$turmas = $st->fetchAll();

$pageTitle = $course['name'];
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

    .m-turma-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.375rem;
    }

    .m-turma-card-new {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        text-decoration: none;
    }

    .m-turma-visual {
        width: 60px;
        height: 60px;
        border-radius: 18px;
        background: var(--color-primary-light);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .m-v-ano {
        font-size: 0.625rem;
        font-weight: 800;
        color: var(--text-muted);
        line-height: 1;
    }
    .m-v-id {
        font-family: 'Outfit', sans-serif;
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--color-primary);
    }

    .m-turma-body { flex: 1; }

    .m-t-name {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 1.125rem;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .m-t-meta {
        font-size: 0.8125rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .m-t-arrow {
        font-size: 1.5rem;
        color: var(--text-muted);
        opacity: 0.3;
        font-weight: 300;
    }
</style>

<div class="m-content">
    
    <div class="m-header-details">
        <div class="m-breadcrumbs">
            <a href="/mobile/courses.php">Cursos</a>
            <span>/</span>
            <span>Turmas</span>
        </div>
        <h1 class="m-section-title" style="margin-bottom: 0.5rem;">Turmas Disponíveis</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted);">Selecione uma turma para carregar a lista de alunos.</p>
    </div>

    <!-- Busca Standardizada -->
    <form action="" method="GET">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">
        <div class="m-search-box">
            <span>🔍</span>
            <input type="text" name="search" class="m-search-input" placeholder="Buscar turma ou ano..." value="<?= htmlspecialchars($search) ?>">
            <?php if($search): ?>
                <a href="turmas.php?course_id=<?= $courseId ?>" style="text-decoration:none; color:var(--text-muted); padding-right:0.5rem;">✕</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="m-turma-grid">
        <?php if (empty($turmas)): ?>
            <div class="m-card" style="text-align:center; padding: 4rem 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🎓</div>
                <p style="color:var(--text-muted);">Nenhuma turma encontrada para este curso.</p>
                <?php if($search): ?>
                    <a href="turmas.php?course_id=<?= $courseId ?>" style="color:var(--color-primary); font-weight:600; margin-top:1rem; display:inline-block;">Limpar busca</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($turmas as $t): ?>
                <a href="/mobile/alunos.php?turma_id=<?= $t['id'] ?>" class="m-card m-turma-card-new">
                    <div class="m-turma-visual">
                        <span class="m-v-ano"><?= $t['ano'] ?></span>
                        <span class="m-v-id"><?= $t['id'] ?></span>
                    </div>
                    <div class="m-turma-body">
                        <div class="m-t-name"><?= htmlspecialchars($t['description']) ?></div>
                        <div class="m-t-meta">
                            <span title="Total de Alunos">👥 <?= $t['total_alunos'] ?></span>
                            <span>• Ativa</span>
                        </div>
                    </div>
                    <div class="m-t-arrow">›</div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
