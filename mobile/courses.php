<?php
/**
 * Vértice Acadêmico — Gestão de Cursos (Mobile)
 * UI Refatorada para Excelência Visual
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = $inst['id'] ?? 0;

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/mobile/courses.php'));
    exit;
}

$db = getDB();
$search = trim($_GET['search'] ?? '');

// ---- Lógica de Listagem ----
// Usando subquery para COUNT para evitar problemas de GROUP BY com c.*
$sql = "SELECT c.*, 
               (SELECT COUNT(*) FROM turmas t WHERE t.course_id = c.id AND t.is_active = 1) as total_turmas
        FROM courses c
        WHERE c.institution_id = ? AND c.is_active = 1";

$params = [$instId];
$restrictions = [];

if ($user['profile'] === 'Coordenador') {
    $restrictions[] = "c.id IN (SELECT course_id FROM course_coordinators WHERE user_id = ?)";
    $params[] = $user['id'];
}

if (($user['is_teacher'] ?? 0) == 1) {
    $restrictions[] = "c.id IN (
        SELECT DISTINCT t_inner.course_id 
        FROM turmas t_inner
        JOIN turma_disciplinas td ON t_inner.id = td.turma_id
        JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id
        WHERE tdp.professor_id = ?
    )";
    $params[] = $user['id'];
}

$isSpecial = in_array($user['profile'], ['Administrador', 'Pedagogo', 'Assistente Social', 'Psicólogo']);

if (!empty($restrictions) && !$isSpecial) {
    $sql .= " AND (" . implode(" OR ", $restrictions) . ")";
}

if ($search) {
    $sql .= " AND c.name LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY c.name ASC";

try {
    $st = $db->prepare($sql);
    $st->execute($params);
    $courses = $st->fetchAll();
} catch (Exception $e) {
    // Para depuração se houver erro SQL (apenas se display_errors estiver OFF e quisermos ver)
    // die($e->getMessage()); 
    $courses = [];
}

$pageTitle = 'Meus Cursos';
$currentPage = 'cursos';
require_once __DIR__ . '/header.php';
?>

<style>
    .m-header-details { margin-bottom: 2rem; }
    .m-course-grid { display: flex; flex-direction: column; gap: 1.25rem; }
    .m-course-card-new { display: block; text-decoration: none; position: relative; }
    .m-course-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; }
    .m-course-tag-new {
        font-size: 0.625rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--color-primary);
        background: var(--color-primary-light);
        padding: 0.25rem 0.625rem;
        border-radius: 8px;
    }
    .m-course-name-text {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-primary);
        font-family: 'Outfit', sans-serif;
        line-height: 1.2;
        margin-bottom: 0.5rem;
    }
    .m-course-footer {
        display: flex;
        align-items: center;
        gap: 1rem;
        color: var(--text-muted);
        font-size: 0.8125rem;
        border-top: 1px solid var(--border-color);
        padding-top: 1rem;
        margin-top: 0.5rem;
    }
    .m-course-stat-item { display: flex; align-items: center; gap: 0.375rem; }
    .m-course-chevron { position: absolute; right: 1.25rem; top: 1.5rem; font-size: 1.5rem; color: var(--text-muted); opacity: 0.2; }
</style>

<div class="m-content">
    <header style="margin-bottom: 0.75rem;">
        <h1 class="m-section-title" style="margin-bottom: 0.25rem;">Gestão de Cursos</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted);">Visualize e gerencie suas turmas e disciplinas.</p>
    </header>

    <!-- Busca Standardizada -->
    <form action="" method="GET">
        <div class="m-search-box">
            <span>🔍</span>
            <input type="text" name="search" class="m-search-input" placeholder="Buscar curso ou campus..." value="<?= htmlspecialchars($search) ?>">
            <?php if($search): ?>
                <a href="courses.php" style="text-decoration:none; color:var(--text-muted); padding-right:0.5rem;">✕</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="m-course-grid">
        <?php if (empty($courses)): ?>
            <div class="m-card" style="text-align:center; padding: 4rem 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                <p style="color:var(--text-muted);">Nenhum curso encontrado.</p>
                <?php if($search): ?>
                    <a href="courses.php" style="color:var(--color-primary); font-weight:600; margin-top:1rem; display:inline-block;">Limpar busca</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $c): ?>
                <a href="/mobile/turmas.php?course_id=<?= $c['id'] ?>" class="m-card m-course-card-new">
                    <div class="m-course-header">
                        <span class="m-course-tag-new"><?= htmlspecialchars($c['location'] ?? 'Eixo Principal') ?></span>
                    </div>
                    <div class="m-course-name-text"><?= htmlspecialchars($c['name']) ?></div>
                    
                    <div class="m-course-footer">
                        <div class="m-course-stat-item">
                            <span>👥</span> <?= $c['total_turmas'] ?> Turmas
                        </div>
                        <?php if(($user['is_teacher'] ?? 0) == 1): ?>
                            <div class="m-course-stat-item">
                                <span>📖</span> Minhas Disciplinas
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="m-course-chevron">›</div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
