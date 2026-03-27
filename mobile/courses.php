<?php
/**
 * Vértice Acadêmico — Gestão de Cursos (Mobile)
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

// ---- Lógica de Listagem (Reutilizada de courses/index.php) ----
$sql = "SELECT c.*, 
               COUNT(t.id) as total_turmas
        FROM courses c
        LEFT JOIN turmas t ON c.id = t.course_id
        LEFT JOIN course_coordinators cc ON c.id = cc.course_id";

$params = [$instId];
$where  = "WHERE c.institution_id=? AND c.is_active = 1";

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

if (!empty($restrictions) && $user['profile'] !== 'Administrador') {
    $where .= " AND (" . implode(" OR ", $restrictions) . ")";
}

if ($search) {
    $where .= " AND c.name LIKE ?";
    $params[] = "%$search%";
}

$sql .= " $where GROUP BY c.id ORDER BY c.name ASC";
$st = $db->prepare($sql);
$st->execute($params);
$courses = $st->fetchAll();

$pageTitle = 'Meus Cursos';
$currentPage = 'cursos';
require_once __DIR__ . '/header.php';
?>

<style>
    .m-course-list {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    .m-course-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 1.5rem;
        text-decoration: none;
        display: block;
        box-shadow: var(--shadow-md);
        position: relative;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .m-course-card:active {
        transform: scale(0.98);
        box-shadow: var(--shadow-sm);
    }
    
    .m-course-tag {
        font-size: 0.625rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--color-primary);
        background: var(--color-primary-light);
        padding: 0.25rem 0.625rem;
        border-radius: 8px;
        display: inline-block;
        margin-bottom: 0.75rem;
    }
    .m-course-name {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-primary);
        font-family: 'Outfit', sans-serif;
        line-height: 1.2;
        margin-bottom: 0.5rem;
    }
    .m-course-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
        color: var(--text-muted);
        font-size: 0.8125rem;
    }
    .m-course-stat {
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }
    .m-course-arrow {
        position: absolute;
        right: 1.5rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.5rem;
        color: var(--text-muted);
        opacity: 0.3;
    }
    .m-empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-muted);
    }
</style>

<div class="m-content">
    
    <header style="margin-bottom: 1.5rem;">
        <h1 class="m-section-title" style="margin-bottom: 0.25rem;">Gestão de Cursos</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted);">Visualize e gerencie suas turmas</p>
    </header>

    <!-- Busca -->
    <form action="" method="GET">
        <div class="m-search-box">
            <span>🔍</span>
            <input type="text" name="search" class="m-search-input" placeholder="Buscar curso..." value="<?= htmlspecialchars($search) ?>">
            <?php if($search): ?>
                <a href="courses.php" style="text-decoration:none; color:var(--text-muted);">✕</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="m-course-list">
        <?php if (empty($courses)): ?>
            <div class="m-empty-state">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                <p>Nenhum curso encontrado.</p>
                <?php if($search): ?>
                    <a href="courses.php" style="color:var(--color-primary); font-weight:600; margin-top:0.5rem; display:inline-block;">Limpar busca</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $c): ?>
                <a href="/mobile/turmas.php?course_id=<?= $c['id'] ?>" class="m-course-card">
                    <span class="m-course-tag"><?= htmlspecialchars($c['location'] ?? 'Eixo Principal') ?></span>
                    <div class="m-course-name"><?= htmlspecialchars($c['name']) ?></div>
                    <div class="m-course-meta">
                        <div class="m-course-stat">
                            <span>👥</span> <?= $c['total_turmas'] ?> Turmas
                        </div>
                        <?php if(($user['is_teacher'] ?? 0) == 1): ?>
                            <div class="m-course-stat">
                                <span>📖</span> Minhas Disciplinas
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="m-course-arrow">›</div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
