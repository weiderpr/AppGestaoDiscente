<?php
/**
 * Vértice Acadêmico — Turmas do Curso (Mobile)
 * UI Refatorada para Excelência Visual
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$inst = getCurrentInstitution();
$instId = $inst['id'];

$courseId = (int)($_GET['course_id'] ?? 0);
if (!$courseId) {
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
// 1. Definição de Permissão: Apenas Administradores veem todas as turmas do curso.
// Outros perfis veem apenas onde possuem disciplinas vinculadas.
$isFullAccess = ($user['profile'] === 'Administrador');

$canViewStudents = hasDbPermission('students.index', false);

$isCourseCoordinator = false;
if ($user['profile'] === 'Coordenador') {
    $stCheck = $db->prepare("SELECT 1 FROM course_coordinators WHERE course_id = ? AND user_id = ?");
    $stCheck->execute([$courseId, $user['id']]);
    $isCourseCoordinator = (bool)$stCheck->fetch();
}

$isTeacherInCourse = false;
$stT = $db->prepare("
    SELECT 1 FROM turmas t 
    JOIN turma_disciplinas td ON t.id = td.turma_id 
    JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id 
    WHERE t.course_id = ? AND tdp.professor_id = ? AND t.is_active = 1 LIMIT 1
");
$stT->execute([$courseId, $user['id']]);
$isTeacherInCourse = (bool)$stT->fetch();

if (!$isFullAccess && !$canViewStudents && !$isCourseCoordinator && !$isTeacherInCourse) {
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

// 2. Filtro de Visibilidade Reais
if (!$isFullAccess) {
    $sql .= " AND t.id IN (
        SELECT DISTINCT t2.id
        FROM turmas t2
        JOIN turma_disciplinas td ON t2.id = td.turma_id
        JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id
        WHERE tdp.professor_id = ? AND t2.is_active = 1
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

    /* Bottom Sheet Styles */
    .m-bs-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 3998;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .m-bs-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .m-bottom-sheet {
        position: fixed;
        left: 0;
        right: 0;
        bottom: -100%;
        background: var(--bg-surface, #ffffff);
        border-top-left-radius: 28px;
        border-top-right-radius: 28px;
        box-shadow: 0 -10px 40px rgba(0,0,0,0.15);
        z-index: 3999;
        transition: bottom 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        padding: 1.25rem 1.5rem calc(1.5rem + var(--safe-area-bottom, 0px));
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        max-width: 600px;
        margin: 0 auto;
    }
    .m-bottom-sheet.active {
        bottom: 0;
    }

    [data-theme="dark"] .m-bottom-sheet {
        background: var(--bg-card);
        border-top: 1px solid var(--border-color);
    }

    .m-bs-drag-handle {
        width: 40px;
        height: 4px;
        background: var(--border-color);
        border-radius: 2px;
        margin: 0 auto;
        opacity: 0.6;
    }

    .m-bs-header {
        text-align: center;
    }

    .m-bs-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.125rem;
        font-weight: 800;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .m-bs-subtitle {
        font-size: 0.8125rem;
        color: var(--text-muted);
        margin: 0;
    }

    .m-bs-body {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .m-bs-action-btn {
        background: var(--bg-body, #f1f5f9);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 1.125rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 1.125rem;
        text-decoration: none;
        transition: transform 0.15s, background-color 0.2s;
    }

    .m-bs-action-btn:active {
        transform: scale(0.98);
        background: var(--border-color);
    }

    [data-theme="dark"] .m-bs-action-btn {
        background: rgba(30, 41, 59, 0.5);
    }

    .m-bs-action-icon {
        font-size: 1.625rem;
        width: 44px;
        height: 44px;
        background: var(--bg-card);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: var(--shadow-sm);
        flex-shrink: 0;
    }

    .m-bs-action-info {
        flex: 1;
        text-align: left;
    }

    .m-bs-action-name {
        font-weight: 700;
        font-size: 0.9375rem;
        color: var(--text-primary);
        margin-bottom: 0.125rem;
    }

    .m-bs-action-desc {
        font-size: 0.75rem;
        color: var(--text-muted);
        line-height: 1.3;
    }

    .m-bs-action-chevron {
        font-size: 1.25rem;
        color: var(--text-muted);
        opacity: 0.4;
    }

    .m-bs-footer {
        padding-top: 0.25rem;
    }

    .m-bs-close-btn {
        width: 100%;
        height: 48px;
        border-radius: 16px;
        background: var(--bg-body);
        color: var(--text-secondary);
        font-weight: 700;
        border: 1px solid var(--border-color);
        font-size: 0.9375rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .m-bs-close-btn:active {
        background: var(--border-color);
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
                <a href="#" class="m-card m-turma-card-new js-turma-card" data-id="<?= $t['id'] ?>" data-name="<?= htmlspecialchars($t['description']) ?>">
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

    <!-- Bottom Sheet Overlay -->
    <div class="m-bs-overlay" id="bsOverlay"></div>

    <!-- Bottom Sheet (Ações Rápidas) -->
    <div class="m-bottom-sheet" id="turmaBottomSheet">
        <div class="m-bs-drag-handle"></div>
        <div class="m-bs-header">
            <h3 class="m-bs-title" id="bsTurmaTitle">Nome da Turma</h3>
            <p class="m-bs-subtitle" id="bsCourseName"><?= htmlspecialchars($course['name']) ?></p>
        </div>
        <div class="m-bs-body">
            <a href="#" class="m-bs-action-btn" id="btnGoToStudents">
                <span class="m-bs-action-icon">👥</span>
                <div class="m-bs-action-info">
                    <div class="m-bs-action-name">Alunos da Turma</div>
                    <div class="m-bs-action-desc">Ver listagem completa, contatos e histórico</div>
                </div>
                <span class="m-bs-action-chevron">›</span>
            </a>
            <a href="#" class="m-bs-action-btn" id="btnGoToGrades">
                <span class="m-bs-action-icon">📊</span>
                <div class="m-bs-action-info">
                    <div class="m-bs-action-name">Notas da Turma</div>
                    <div class="m-bs-action-desc">Acompanhar rendimento escolar e médias</div>
                </div>
                <span class="m-bs-action-chevron">›</span>
            </a>
        </div>
        <div class="m-bs-footer">
            <button class="m-bs-close-btn" id="btnCloseBottomSheet">Fechar</button>
        </div>
    </div>

</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const cards = document.querySelectorAll(".js-turma-card");
        const overlay = document.getElementById("bsOverlay");
        const sheet = document.getElementById("turmaBottomSheet");
        const closeBtn = document.getElementById("btnCloseBottomSheet");
        
        const titleEl = document.getElementById("bsTurmaTitle");
        const btnAlunos = document.getElementById("btnGoToStudents");
        const btnNotas = document.getElementById("btnGoToGrades");

        function openSheet(id, name) {
            titleEl.textContent = name;
            btnAlunos.href = `/mobile/alunos.php?turma_id=${id}`;
            btnNotas.href = `/mobile/notas_turma.php?turma_id=${id}`;
            
            overlay.classList.add("active");
            sheet.classList.add("active");
            document.body.style.overflow = "hidden";
        }

        function closeSheet() {
            overlay.classList.remove("active");
            sheet.classList.remove("active");
            document.body.style.overflow = "";
        }

        cards.forEach(card => {
            card.addEventListener("click", (e) => {
                e.preventDefault();
                const id = card.getAttribute("data-id");
                const name = card.getAttribute("data-name");
                openSheet(id, name);
            });
        });

        overlay.addEventListener("click", closeSheet);
        closeBtn.addEventListener("click", closeSheet);
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
