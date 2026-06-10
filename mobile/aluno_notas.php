<?php
/**
 * Vértice Acadêmico — Notas do Aluno por Etapa (Mobile)
 * UI de Excelência Visual e Foco na Experiência do Usuário
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/AlunoService.php';
require_once __DIR__ . '/../src/App/Services/TurmaService.php';

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
$turmaService = new \App\Services\TurmaService();

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

// Permissões via Matriz + Vínculos
$isFullAccess = ($user['profile'] === 'Administrador');

$isTeacherOfThisTurma = false;
$stCheckT = $db->prepare("
    SELECT 1 FROM turma_disciplinas td 
    JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id 
    WHERE td.turma_id = ? AND tdp.professor_id = ? LIMIT 1
");
$stCheckT->execute([$turmaId, $user['id']]);
$isTeacherOfThisTurma = (bool)$stCheckT->fetch();

$isCourseCoordinator = false;
if ($user['profile'] === 'Coordenador') {
    $stCheck = $db->prepare("SELECT 1 FROM course_coordinators WHERE course_id = ? AND user_id = ?");
    $stCheck->execute([$turma['course_id'], $user['id']]);
    $isCourseCoordinator = (bool)$stCheck->fetch();
}

if (!$isFullAccess && !$isTeacherOfThisTurma && !$isCourseCoordinator) {
    header('Location: /mobile/courses.php');
    exit;
}

// Busca as disciplinas do professor logado para destacar
$teacherDiscs = $turmaService->getTeacherDisciplinesInTurma($turmaId, (int)$user['id']);
$teacherDiscCodes = array_column($teacherDiscs, 'codigo');

$pageTitle = "Notas: " . $aluno['nome'];
$currentPage = 'cursos';
require_once __DIR__ . '/header.php';
?>

<style>
    .m-header-details {
        margin-bottom: 1rem;
    }
    
    .m-breadcrumbs {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
    }

    .m-breadcrumbs a { color: var(--color-primary); text-decoration: none; }

    /* Student Mini Hero */
    .m-student-mini-hero {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-xl);
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.25rem;
        box-shadow: var(--shadow-sm);
    }

    .m-student-mini-avatar {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        object-fit: cover;
    }

    .m-student-mini-avatar-placeholder {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: var(--gradient-brand);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1rem;
    }

    .m-student-mini-name {
        font-family: 'Outfit', sans-serif;
        font-size: 1rem;
        font-weight: 800;
        color: var(--text-primary);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .m-student-mini-sub {
        font-size: 0.75rem;
        color: var(--text-muted);
        font-weight: 600;
    }

    /* Seletor de Etapas */
    .m-stages-scroll {
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
        padding-bottom: 0.75rem;
        margin: 0 -1.5rem 1rem;
        padding-left: 1.5rem;
        padding-right: 1.5rem;
        scrollbar-width: none;
    }
    .m-stages-scroll::-webkit-scrollbar { display: none; }

    .m-stage-pill {
        flex: 0 0 auto;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        padding: 0.625rem 1.125rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .m-stage-pill.active {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
    }

    /* Lista de Notas */
    .m-grades-list-card {
        padding: 1.25rem;
        margin-bottom: 2rem;
    }

    .m-grades-list-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
        font-family: 'Outfit', sans-serif;
        margin-top: 0;
        margin-bottom: 1rem;
    }

    .m-grade-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.875rem 1rem;
        border-radius: 16px;
        margin-bottom: 0.5rem;
        transition: all 0.2s;
    }

    .m-grade-row-default {
        border: 1px solid var(--border-color);
        background: var(--bg-card);
    }

    .m-grade-row-highlight {
        border: 2px solid var(--color-primary);
        background: var(--color-primary-light);
    }
    [data-theme="dark"] .m-grade-row-highlight {
        background: rgba(79, 70, 229, 0.15);
    }

    .m-grade-info {
        flex: 1;
        min-width: 0;
    }

    .m-grade-disc-name {
        font-weight: 700;
        font-size: 0.875rem;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 0.125rem;
    }

    .m-grade-teacher-tag {
        font-size: 0.5625rem;
        font-weight: 800;
        background: var(--color-primary);
        color: white;
        padding: 2px 6px;
        border-radius: 6px;
        margin-left: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .m-grade-details {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .m-grade-badge-container {
        text-align: right;
    }

    .m-grade-badge {
        font-size: 0.875rem;
        font-weight: 800;
        padding: 0.375rem 0.625rem;
        border-radius: 10px;
    }

    .grade-pass {
        color: #10b981;
        background: #ecfdf5;
    }
    [data-theme="dark"] .grade-pass {
        color: #34d399;
        background: rgba(16, 185, 129, 0.15);
    }

    .grade-fail {
        color: #ef4444;
        background: #fef2f2;
    }
    [data-theme="dark"] .grade-fail {
        color: #f87171;
        background: rgba(239, 68, 68, 0.15);
    }

    .grade-null {
        color: var(--text-muted);
        background: var(--border-color);
    }

    /* Loading Skeletons */
    .skeleton-bar {
        background: linear-gradient(90deg, var(--border-color) 25%, var(--bg-body) 50%, var(--border-color) 75%);
        background-size: 200% 100%;
        animation: loadingSkeleton 1.5s infinite;
        border-radius: 8px;
    }

    @keyframes loadingSkeleton {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>

<div class="m-content">
    
    <div class="m-header-details">
        <div class="m-breadcrumbs">
            <a href="/mobile/courses.php">Cursos</a>
            <span>/</span>
            <a href="/mobile/turmas.php?course_id=<?= $turma['course_id'] ?>">Turmas</a>
            <span>/</span>
            <a href="/mobile/aluno_historico.php?aluno_id=<?= $alunoId ?>&turma_id=<?= $turmaId ?>">Histórico</a>
            <span>/</span>
            <span>Notas</span>
        </div>
        <h1 class="m-section-title" style="margin-bottom: 0.25rem;">Notas do Aluno</h1>
        <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 0;"><?= htmlspecialchars($turma['description']) ?> • <?= htmlspecialchars($turma['course_name']) ?></p>
    </div>

    <!-- Student Mini Hero -->
    <div class="m-student-mini-hero">
        <?php if (!empty($aluno['photo'])): ?>
            <img src="/<?= htmlspecialchars($aluno['photo']) ?>" class="m-student-mini-avatar" alt="">
        <?php else: 
            $initials = '';
            foreach (explode(' ', trim($aluno['nome'])) as $part) {
                $initials .= strtoupper(substr($part, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        ?>
            <div class="m-student-mini-avatar-placeholder"><?= $initials ?></div>
        <?php endif; ?>
        
        <div class="m-student-mini-info">
            <h2 class="m-student-mini-name"><?= htmlspecialchars($aluno['nome']) ?></h2>
            <div class="m-student-mini-sub">MATRÍCULA: #<?= htmlspecialchars($aluno['matricula']) ?></div>
        </div>
    </div>

    <!-- Seletor de Etapas (Pills) -->
    <div class="m-stages-scroll" id="stagesContainer">
        <!-- Preenchido via JS -->
    </div>

    <!-- Lista de Notas das Disciplinas -->
    <div class="m-card m-grades-list-card">
        <h3 class="m-grades-list-title" id="stageTitle">Boletim do Aluno</h3>
        <div id="gradesContainer">
            <!-- Skeletons -->
            <div class="m-grade-row m-grade-row-default">
                <div style="flex:1;">
                    <div class="skeleton-bar" style="width: 60%; height: 16px; margin-bottom: 6px;"></div>
                    <div class="skeleton-bar" style="width: 30%; height: 12px;"></div>
                </div>
                <div class="skeleton-bar" style="width: 40px; height: 24px; border-radius: 10px;"></div>
            </div>
            <div class="m-grade-row m-grade-row-default">
                <div style="flex:1;">
                    <div class="skeleton-bar" style="width: 50%; height: 16px; margin-bottom: 6px;"></div>
                    <div class="skeleton-bar" style="width: 25%; height: 12px;"></div>
                </div>
                <div class="skeleton-bar" style="width: 40px; height: 24px; border-radius: 10px;"></div>
            </div>
        </div>
    </div>

</div>

<script>
    const alunoId = <?= $alunoId ?>;
    const turmaId = <?= $turmaId ?>;
    const teacherDiscCodes = <?= json_encode($teacherDiscCodes) ?>;
    
    let activeEtapaId = null;
    let cachePerformanceData = null;

    document.addEventListener("DOMContentLoaded", () => {
        loadPerformanceData();
    });

    async function loadPerformanceData() {
        try {
            const res = await fetch(`/api/student_performance.php?aluno_id=${alunoId}&turma_id=${turmaId}`);
            const data = await res.json();

            if (data.error || !data.etapas || data.etapas.length === 0) {
                document.getElementById("gradesContainer").innerHTML = `<div style="text-align:center; color:var(--text-muted); font-size:0.875rem; padding: 2rem 0;">Nenhum dado lançado para este aluno.</div>`;
                document.getElementById("stageTitle").textContent = "Boletim do Aluno";
                return;
            }

            cachePerformanceData = data;
            
            // Assume a primeira etapa como ativa inicialmente
            activeEtapaId = data.etapas[0].id;

            renderEtapas(data.etapas, activeEtapaId);
            renderGrades(activeEtapaId);

        } catch (e) {
            console.error("Erro ao carregar boletim do aluno:", e);
            document.getElementById("gradesContainer").innerHTML = `<div style="text-align:center; color:var(--text-muted); font-size:0.875rem; padding: 2rem 0;">Erro ao obter boletim acadêmico.</div>`;
        }
    }

    function renderEtapas(etapas, activeId) {
        const container = document.getElementById("stagesContainer");
        if (!container) return;

        let html = "";
        etapas.forEach(et => {
            const activeClass = parseInt(et.id) === parseInt(activeId) ? "active" : "";
            html += `<button class="m-stage-pill ${activeClass}" onclick="switchEtapa(${et.id})">${et.description}</button>`;
        });
        container.innerHTML = html;
    }

    function switchEtapa(etapaId) {
        activeEtapaId = etapaId;
        
        // Atualiza Pills ativa
        const pills = document.querySelectorAll(".m-stage-pill");
        pills.forEach(p => p.classList.remove("active"));
        
        // Encontra o botão clicado
        const activeBtn = Array.from(pills).find(p => p.getAttribute("onclick").includes(etapaId));
        if (activeBtn) activeBtn.classList.add("active");

        // Loader na lista
        document.getElementById("gradesContainer").innerHTML = `
            <div class="m-grade-row m-grade-row-default">
                <div style="flex:1;"><div class="skeleton-bar" style="width:60%; height:16px; margin-bottom:6px;"></div><div class="skeleton-bar" style="width:30%; height:12px;"></div></div>
                <div class="skeleton-bar" style="width:40px; height:24px; border-radius:10px;"></div>
            </div>`;

        renderGrades(etapaId);
    }

    function renderGrades(etapaId) {
        if (!cachePerformanceData) return;

        const container = document.getElementById("gradesContainer");
        const titleEl = document.getElementById("stageTitle");
        if (!container) return;

        const etapa = cachePerformanceData.etapas.find(et => parseInt(et.id) === parseInt(etapaId));
        if (!etapa) return;

        titleEl.textContent = `Boletim — ${etapa.description}`;

        const passGrade = parseFloat(etapa.media_nota) || 6;
        const maxGrade = parseFloat(etapa.nota_maxima) || 10;

        let html = "";
        cachePerformanceData.disciplinas.forEach(disc => {
            const notaInfo = disc.etapas ? disc.etapas[etapa.id] : null;
            const notaVal = (notaInfo && notaInfo.nota !== null) ? parseFloat(notaInfo.nota) : null;
            
            const isTeacherDisc = teacherDiscCodes.includes(disc.codigo);
            const rowClass = isTeacherDisc ? "m-grade-row-highlight" : "m-grade-row-default";

            let badgeHtml = "";
            if (notaVal !== null) {
                const passed = notaVal >= passGrade;
                const badgeClass = passed ? "grade-pass" : "grade-fail";
                badgeHtml = `<span class="m-grade-badge ${badgeClass}">${notaVal.toFixed(1)}</span>`;
            } else {
                badgeHtml = `<span class="m-grade-badge grade-null">—</span>`;
            }

            const highlightTag = isTeacherDisc ? `<span class="m-grade-teacher-tag">Sua Disciplina</span>` : "";
            const stageMetaText = `Nota Máxima: ${maxGrade.toFixed(0)} • Média Mínima: ${passGrade.toFixed(0)}`;

            html += `
                <div class="m-grade-row ${rowClass}">
                    <div class="m-grade-info">
                        <div class="m-grade-disc-name">${disc.descricao} ${highlightTag}</div>
                        <div class="m-grade-details">${stageMetaText}</div>
                    </div>
                    <div class="m-grade-badge-container">
                        ${badgeHtml}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
