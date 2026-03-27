<?php
/**
 * Vértice Acadêmico — Turmas do Curso (Mobile)
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

// Segurança extra por perfil
$isCourseCoordinator = false;
if ($user['profile'] === 'Coordenador') {
    $stCheck = $db->prepare("SELECT 1 FROM course_coordinators WHERE course_id = ? AND user_id = ?");
    $stCheck->execute([$courseId, $user['id']]);
    $isCourseCoordinator = (bool)$stCheck->fetch();
}

// Se for coordenador e não for desta turma, verificamos se ele é pelo menos professor nela
$isTeacherOfThisCourse = false;
if (($user['is_teacher'] ?? 0) == 1) {
    $stT = $db->prepare("
        SELECT 1 FROM turmas t 
        JOIN turma_disciplinas td ON t.id = td.turma_id 
        JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id 
        WHERE t.course_id = ? AND tdp.professor_id = ? LIMIT 1
    ");
    $stT->execute([$courseId, $user['id']]);
    $isTeacherOfThisCourse = (bool)$stT->fetch();
}

$isAdmin = ($user['profile'] === 'Administrador');

if (!$isCourseCoordinator && !$isTeacherOfThisCourse && !$isAdmin && !in_array($user['profile'], ['Pedagogo', 'Assistente Social', 'Psicólogo'])) {
    header('Location: /mobile/courses.php');
    exit;
}

$search = trim($_GET['search'] ?? '');

// ---- LISTAR TURMAS (Lógica reutilizada) ----
$sql = "
    SELECT t.*,
           (SELECT COUNT(*) FROM turma_alunos WHERE turma_id = t.id) as total_alunos
    FROM turmas t
    WHERE t.course_id = ? AND t.is_active = 1
";
$params = [$courseId];

// Se for apenas professor (ou coordenador acessando curso que não coordena), filtra turmas onde leciona
if (($user['is_teacher'] ?? 0) == 1 && !$isAdmin && !$isCourseCoordinator && !in_array($user['profile'], ['Pedagogo', 'Assistente Social', 'Psicólogo'])) {
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

$pageTitle = 'Turmas — ' . $course['name'];
$currentPage = 'cursos'; // Mantém o destaque em Cursos
require_once __DIR__ . '/header.php';
?>

<style>
    .m-back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-secondary);
        font-weight: 600;
        font-size: 0.875rem;
        text-decoration: none;
        margin-bottom: 1.5rem;
        background: var(--bg-surface);
        padding: 0.5rem 0.875rem;
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }
    .m-turma-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 1.25rem 1.5rem;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 1.25rem;
        box-shadow: var(--shadow-md);
        margin-bottom: 1rem;
        position: relative;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .m-turma-card:active {
        transform: scale(0.98);
        box-shadow: var(--shadow-sm);
    }
    
    .m-turma-icon {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        background: var(--bg-surface-2nd);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .m-turma-ano {
        font-size: 0.625rem;
        font-weight: 800;
        color: var(--text-muted);
        line-height: 1;
    }
    .m-turma-id {
        font-family: 'Outfit', sans-serif;
        font-size: 1.125rem;
        font-weight: 800;
        color: var(--color-primary);
    }
    
    .m-turma-info {
        flex: 1;
    }
    .m-turma-name {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 1.125rem;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }
    .m-turma-meta {
        font-size: 0.8125rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .m-turma-arrow {
        font-size: 1.25rem;
        color: var(--text-muted);
        opacity: 0.3;
    }
</style>

<div class="m-content">
    
    <a href="/mobile/courses.php" class="m-back-link">
        <span>←</span> Voltar para Cursos
    </a>

    <header style="margin-bottom: 2rem;">
        <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-primary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">
            <?= htmlspecialchars($course['name']) ?>
        </div>
        <h1 class="m-section-title" style="margin-bottom: 0.5rem;">Turmas Disponíveis</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted);">Selecione uma turma para ver os alunos</p>
    </header>

    <!-- Busca -->
    <form action="" method="GET" style="margin-bottom: 2rem;">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">
        <div class="m-search-box">
            <span>🔍</span>
            <input type="text" name="search" class="m-search-input" placeholder="Buscar turma ou ano..." value="<?= htmlspecialchars($search) ?>">
            <?php if($search): ?>
                <a href="turmas.php?course_id=<?= $courseId ?>" style="text-decoration:none; color:var(--text-muted); padding-right: 0.5rem;">✕</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="m-turma-list">
        <?php if (empty($turmas)): ?>
            <div class="m-empty-state">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🎓</div>
                <p>Nenhuma turma encontrada para este curso.</p>
                <?php if($search): ?>
                    <a href="turmas.php?course_id=<?= $courseId ?>" style="color:var(--color-primary); font-weight:600; margin-top:0.5rem; display:inline-block;">Limpar busca</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($turmas as $t): ?>
                <a href="/mobile/alunos.php?turma_id=<?= $t['id'] ?>" class="m-turma-card">
                    <div class="m-turma-icon">
                        <span class="m-turma-ano"><?= $t['ano'] ?></span>
                        <span class="m-turma-id">T<?= $t['id'] ?></span>
                    </div>
                    <div class="m-turma-info">
                        <div class="m-turma-name"><?= htmlspecialchars($t['description']) ?></div>
                        <div class="m-turma-meta">
                            <span>👥 <?= $t['total_alunos'] ?> Alunos</span>
                            <span>📅 Ativa</span>
                        </div>
                    </div>
                    <div class="m-turma-arrow">›</div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
