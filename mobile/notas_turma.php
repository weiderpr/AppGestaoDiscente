<?php
/**
 * Vértice Acadêmico — Notas da Turma (Mobile)
 * UI de Excelência Visual com Gráficos SVG Nativos
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
// Apenas Administradores ou profissionais que possuem disciplinas/coordenação na turma podem acessar.
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

$pageTitle = 'Notas da Turma';
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

    /* Info Destaque Disciplina */
    .m-discipline-banner {
        background: var(--color-primary-light);
        border: 1px solid rgba(79, 70, 229, 0.15);
        color: var(--color-primary);
        padding: 0.875rem 1.25rem;
        border-radius: 18px;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.875rem;
        font-weight: 600;
    }

    [data-theme="dark"] .m-discipline-banner {
        background: rgba(79, 70, 229, 0.1);
        color: #818cf8;
        border-color: rgba(79, 70, 229, 0.2);
    }

    .m-discipline-banner span {
        font-size: 1.25rem;
    }

    /* Gráfico Card */
    .m-chart-card {
        padding: 1.25rem;
        margin-bottom: 1.25rem;
    }

    .m-chart-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
        font-family: 'Outfit', sans-serif;
    }

    .m-chart-subtitle {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-bottom: 1rem;
    }

    .m-chart-container {
        width: 100%;
        min-height: 180px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Lista de Alunos */
    .m-alunos-list-card {
        padding: 1.25rem;
        margin-bottom: 2rem;
    }

    .m-list-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .m-list-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
        font-family: 'Outfit', sans-serif;
        margin: 0;
    }

    .m-list-count {
        font-size: 0.75rem;
        background: var(--border-color);
        color: var(--text-secondary);
        padding: 0.25rem 0.5rem;
        border-radius: 8px;
        font-weight: 700;
    }

    .m-aluno-row {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.875rem 0;
        border-bottom: 1px solid var(--border-color);
    }
    .m-aluno-row:last-child {
        border-bottom: none;
    }

    .m-aluno-photo-box {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--border-color);
    }

    .m-aluno-initials-box {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: var(--gradient-brand);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 0.9375rem;
        box-shadow: var(--shadow-sm);
    }

    .m-aluno-info {
        flex: 1;
        min-width: 0;
    }

    .m-aluno-name {
        font-weight: 700;
        font-size: 0.9375rem;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 0.125rem;
    }

    .m-aluno-sub {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .m-aluno-grade-container {
        text-align: right;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 2px;
    }

    .m-grade-badge {
        font-size: 0.9375rem;
        font-weight: 800;
        padding: 0.25rem 0.625rem;
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

    .m-aluno-faltas {
        font-size: 0.6875rem;
        color: var(--text-muted);
        font-weight: 600;
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
            <span>Notas</span>
        </div>
        <h1 class="m-section-title" style="margin-bottom: 0.25rem;"><?= htmlspecialchars($turma['description']) ?></h1>
        <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 0;"><?= htmlspecialchars($turma['course_name']) ?></p>
    </div>

    <!-- Seletor de Etapas (Pills) -->
    <div class="m-stages-scroll" id="stagesContainer">
        <!-- Preenchido via JS -->
    </div>

    <!-- Banner da Disciplina do Professor -->
    <div class="m-discipline-banner" id="disciplineBanner" style="display: none;">
        <span>📖</span>
        <div id="disciplineBannerText">Carregando disciplina...</div>
    </div>

    <!-- Card de Gráfico de Médias por Disciplina -->
    <div class="m-card m-chart-card">
        <div class="m-chart-title">Desempenho Geral da Turma</div>
        <div class="m-chart-subtitle">Comparativo de média geral por disciplina nesta etapa</div>
        <div class="m-chart-container" id="chartContainer">
            <div class="skeleton-bar" style="width: 100%; height: 150px;"></div>
        </div>
    </div>

    <!-- Card da Lista de Alunos e Notas -->
    <div class="m-card m-alunos-list-card">
        <div class="m-list-header">
            <h2 class="m-list-title">Notas dos Alunos</h2>
            <span class="m-list-count" id="alunosCount">0</span>
        </div>
        <div id="alunosListContainer">
            <!-- Skeletons -->
            <div class="m-aluno-row">
                <div class="skeleton-bar" style="width: 44px; height: 44px; border-radius: 50%;"></div>
                <div style="flex: 1;">
                    <div class="skeleton-bar" style="width: 60%; height: 16px; margin-bottom: 6px;"></div>
                    <div class="skeleton-bar" style="width: 40%; height: 12px;"></div>
                </div>
                <div class="skeleton-bar" style="width: 40px; height: 24px; border-radius: 10px;"></div>
            </div>
            <div class="m-aluno-row">
                <div class="skeleton-bar" style="width: 44px; height: 44px; border-radius: 50%;"></div>
                <div style="flex: 1;">
                    <div class="skeleton-bar" style="width: 70%; height: 16px; margin-bottom: 6px;"></div>
                    <div class="skeleton-bar" style="width: 30%; height: 12px;"></div>
                </div>
                <div class="skeleton-bar" style="width: 40px; height: 24px; border-radius: 10px;"></div>
            </div>
        </div>
    </div>

</div>

<script>
    const turmaId = <?= $turmaId ?>;
    let selectedEtapaId = null;

    document.addEventListener("DOMContentLoaded", () => {
        loadData();
    });

    async function loadData(etapaId = null) {
        let url = `/api/notas_turma.php?turma_id=${turmaId}`;
        if (etapaId) {
            url += `&etapa_id=${etapaId}`;
        }

        try {
            const response = await fetch(url);
            const data = await response.json();

            if (data.error) {
                console.error("Erro na API:", data.error);
                return;
            }

            selectedEtapaId = parseInt(data.etapa_ativa?.id);

            renderEtapas(data.etapas, selectedEtapaId);
            renderDisciplineBanner(data.disciplina_destaque);
            renderChart(data.disciplinas_media, data.disciplina_destaque?.codigo, data.media_aprovacao, data.nota_maxima_turma);
            renderAlunosList(data.alunos, data.media_aprovacao);

        } catch (error) {
            console.error("Erro ao carregar dados de notas:", error);
        }
    }

    function renderEtapas(etapas, activeId) {
        const container = document.getElementById("stagesContainer");
        if (!container) return;

        let html = "";
        etapas.forEach(et => {
            const activeClass = parseInt(et.id) === activeId ? "active" : "";
            html += `<button class="m-stage-pill ${activeClass}" onclick="switchEtapa(${et.id})">${et.description}</button>`;
        });
        container.innerHTML = html;
    }

    function renderDisciplineBanner(discipline) {
        const banner = document.getElementById("disciplineBanner");
        const bannerText = document.getElementById("disciplineBannerText");
        if (!banner || !bannerText) return;

        if (discipline) {
            bannerText.innerHTML = `Mostrando notas de: <strong>${discipline.descricao}</strong>`;
            banner.style.display = "flex";
        } else {
            banner.style.display = "none";
        }
    }

    function switchEtapa(etapaId) {
        // Mostra loaders parciais
        document.getElementById("chartContainer").innerHTML = `<div class="skeleton-bar" style="width: 100%; height: 150px;"></div>`;
        document.getElementById("alunosListContainer").innerHTML = `
            <div class="m-aluno-row">
                <div class="skeleton-bar" style="width: 44px; height: 44px; border-radius: 50%;"></div>
                <div style="flex: 1;"><div class="skeleton-bar" style="width: 60%; height: 16px; margin-bottom: 6px;"></div><div class="skeleton-bar" style="width: 40%; height: 12px;"></div></div>
                <div class="skeleton-bar" style="width: 40px; height: 24px; border-radius: 10px;"></div>
            </div>`;
        
        loadData(etapaId);
    }

    function renderChart(disciplinas, destaqueCodigo, mediaAprovacao, notaMaxima) {
        const container = document.getElementById("chartContainer");
        if (!container) return;

        if (!disciplinas || disciplinas.length === 0) {
            container.innerHTML = `<div style="text-align:center; color:var(--text-muted); font-size:0.875rem; padding: 2rem 0;">Nenhum dado lançado para esta etapa.</div>`;
            return;
        }

        const width = container.offsetWidth || 320;
        const height = 180;
        const paddingLeft = 45;
        const paddingRight = 15;
        const paddingTop = 20;
        const paddingBottom = 45;
        const chartWidth = width - paddingLeft - paddingRight;
        const chartHeight = height - paddingTop - paddingBottom;

        // Máximo valor do eixo Y é o valor máximo da turma ou arredondado
        const yMax = Math.ceil(notaMaxima || 10);

        let svg = `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" style="overflow:visible;">`;

        // 1. Linhas de grade e Labels do eixo Y
        const gridSteps = 3;
        for (let i = 0; i <= gridSteps; i++) {
            const val = (yMax / gridSteps) * i;
            const y = height - paddingBottom - (val / yMax * chartHeight);
            svg += `<line x1="${paddingLeft}" y1="${y}" x2="${width - paddingRight}" y2="${y}" stroke="var(--border-color)" stroke-width="1" stroke-dasharray="2,2" opacity="0.3" />`;
            svg += `<text x="${paddingLeft - 8}" y="${y + 4}" font-size="9" text-anchor="end" fill="var(--text-muted)" font-weight="600">${val.toFixed(0)}</text>`;
        }

        // 2. Linha de aprovação (tracejada laranja)
        if (mediaAprovacao > 0) {
            const passY = height - paddingBottom - (mediaAprovacao / yMax * chartHeight);
            svg += `
                <line x1="${paddingLeft}" y1="${passY}" x2="${width - paddingRight}" y2="${passY}" stroke="#f59e0b" stroke-width="1.5" stroke-dasharray="4,2" opacity="0.8" />
                <text x="${width - paddingRight - 5}" y="${passY - 4}" font-size="8" font-weight="700" text-anchor="end" fill="#d97706">Média: ${mediaAprovacao.toFixed(1)}</text>
            `;
        }

        // 3. Renderizar Barras das disciplinas
        const barWidth = Math.min(24, (chartWidth / disciplinas.length) * 0.45);
        const gap = (chartWidth - (barWidth * disciplinas.length)) / (disciplinas.length + 1 || 1);

        disciplinas.forEach((d, i) => {
            const x = paddingLeft + gap + i * (barWidth + gap);
            const mediaNota = d.media_nota || 0;
            const h = (mediaNota / yMax) * chartHeight;
            const y = height - paddingBottom - h;

            // Se for a disciplina do professor logado (destaqueCodigo), usa a cor da marca.
            // Senão, usa cinza.
            const isDestaque = d.codigo === destaqueCodigo;
            const color = isDestaque ? "var(--color-primary, #4f46e5)" : "#94a3b8";
            const opacity = isDestaque ? "1" : "0.5";

            svg += `
                <rect x="${x}" y="${y}" width="${barWidth}" height="${h}" fill="${color}" rx="3" opacity="${opacity}">
                    <animate attributeName="height" from="0" to="${h}" dur="0.4s" fill="freeze" />
                    <animate attributeName="y" from="${height - paddingBottom}" to="${y}" dur="0.4s" fill="freeze" />
                </rect>
            `;

            if (mediaNota > 0) {
                svg += `<text x="${x + barWidth / 2}" y="${y - 4}" font-size="8.5" font-weight="700" text-anchor="middle" fill="${color}">${mediaNota.toFixed(1)}</text>`;
            }

            // Label da disciplina (iniciais ou cortado)
            const label = d.descricao.length > 5 ? d.descricao.substring(0, 4) + '.' : d.descricao;
            svg += `
                <text x="${x + barWidth / 2}" y="${height - paddingBottom + 16}" font-size="8.5" font-weight="700" text-anchor="middle" fill="var(--text-secondary)">${label}</text>
            `;
        });

        svg += `</svg>`;
        container.innerHTML = svg;
    }

    function renderAlunosList(alunos, mediaAprovacao) {
        const container = document.getElementById("alunosListContainer");
        const countBadge = document.getElementById("alunosCount");
        if (!container || !countBadge) return;

        countBadge.textContent = alunos.length;

        if (!alunos || alunos.length === 0) {
            container.innerHTML = `<div style="text-align:center; color:var(--text-muted); font-size:0.875rem; padding: 2rem 0;">Nenhum discente vinculado à turma.</div>`;
            return;
        }

        let html = "";
        alunos.forEach(a => {
            // Nota formatada
            let gradeHtml = "";
            if (a.nota !== null) {
                const passed = a.nota >= mediaAprovacao;
                const badgeClass = passed ? "grade-pass" : "grade-fail";
                gradeHtml = `<span class="m-grade-badge ${badgeClass}">${a.nota.toFixed(1)}</span>`;
            } else {
                gradeHtml = `<span class="m-grade-badge grade-null">—</span>`;
            }

            // Foto ou iniciais
            let avatarHtml = "";
            if (a.photo) {
                avatarHtml = `<img src="/${a.photo}" alt="" class="m-aluno-photo-box">`;
            } else {
                let initials = "";
                const parts = a.nome.trim().split(" ");
                if (parts[0]) initials += parts[0][0].toUpperCase();
                if (parts[1]) initials += parts[1][0].toUpperCase();
                avatarHtml = `<div class="m-aluno-initials-box">${initials}</div>`;
            }

            // Faltas
            const faltasText = a.faltas > 0 ? `• ⚠️ ${a.faltas} Faltas` : "";

            html += `
                <div class="m-aluno-row">
                    ${avatarHtml}
                    <div class="m-aluno-info">
                        <div class="m-aluno-name">${a.nome}</div>
                        <div class="m-aluno-sub">Matrícula: #${a.matricula} ${faltasText}</div>
                    </div>
                    <div class="m-aluno-grade-container">
                        ${gradeHtml}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
