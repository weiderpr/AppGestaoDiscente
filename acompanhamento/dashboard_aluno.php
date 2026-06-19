<?php
/**
 * Vértice Acadêmico — Dashboard Unificado do Aluno
 * Visão consolidada para equipe de apoio (coordenação, pedagogia, assistência social, psicologia).
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

hasDbPermission('acompanhamento.index');

require_once __DIR__ . '/../includes/toast.php';
require_once __DIR__ . '/../includes/modal.php';
require_once __DIR__ . '/../includes/components/StudentAIAnalysis.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/AtendimentoService.php';

$user   = getCurrentUser();
$inst   = getCurrentInstitution();
$instId = (int)$inst['id'];
$userId = (int)$user['id'];

$pageTitle = 'Dashboard do Aluno';
$extraCSS  = [
    '/assets/css/dashboard_aluno.css',
];
$extraJS = [
    '/assets/js/student_comments.js',   // carrega vaAIAnalysis
    '/assets/js/dashboard_aluno.js',
];

require_once __DIR__ . '/../includes/header.php';
renderModalStyles();
renderToastStyles();

// Turmas disponíveis para o filtro de "Novo Atendimento" (inject no JS)
$service = new \App\Services\AtendimentoService();
?>

<style>
/* Remove o padding do wrapper global nesta página; cada seção cuida do próprio espaçamento. */
#main-content { padding: 0 !important; }
</style>

<!-- ── Subheader compacto: título + busca em linha ── -->
<div class="da-subheader">
        <span class="da-subheader-title">👤 Dashboard do Aluno</span>

        <div class="da-search-box" style="flex:1;max-width:520px">
            <span class="da-search-icon" aria-hidden="true">🔍</span>
            <input
                type="search"
                id="daSearchInput"
                class="da-search-input"
                placeholder="Buscar por nome ou matrícula…"
                autocomplete="off"
                aria-label="Buscar aluno"
                aria-autocomplete="list"
                aria-controls="daSearchDropdown"
            >
            <div class="da-search-spinner" aria-hidden="true"></div>
            <div id="daSearchDropdown" class="da-search-dropdown" role="listbox" aria-label="Resultados"></div>
        </div>
    </div>

<div style="max-width:1400px;margin:0 auto;padding:1.25rem 1.25rem 2rem">

    <!-- ── Estado vazio ── -->
    <div id="daEmptyState" class="da-empty-state">
        <div class="da-empty-state-icon">👤</div>
        <div class="da-empty-state-title">Nenhum aluno selecionado</div>
        <div class="da-empty-state-desc">Use a busca acima para localizar um aluno e carregar seu dashboard.</div>
    </div>

    <!-- ── Dashboard (oculto até selecionar um aluno) ── -->
    <div id="daDashboard" style="display:none">

        <!-- Hero -->
        <div id="daHero"></div>

        <!-- Grid de cards -->
        <div class="da-grid">

            <!-- Notas & Faltas -->
            <div class="da-area-perf da-card" id="daCard_performance">
                <div class="da-card-header">
                    <span class="da-card-title">📊 Notas &amp; Faltas</span>
                </div>
                <div class="da-card-body"></div>
            </div>

            <!-- Atendimentos -->
            <div class="da-area-atend da-card" id="daCard_atendimentos">
                <div class="da-card-header">
                    <span class="da-card-title">📝 Atendimentos</span>
                    <?php if (hasDbPermission('atendimentos.manage', false)): ?>
                    <button class="btn btn-primary btn-sm"
                            style="font-size:.75rem;padding:.25rem .75rem"
                            onclick="daOpenNewAtendimento()">
                        + Novo
                    </button>
                    <?php endif; ?>
                </div>
                <div class="da-card-body"></div>
            </div>

            <!-- Conselho de Classe -->
            <div class="da-area-conselho da-card" id="daCard_conselho">
                <div class="da-card-header">
                    <span class="da-card-title">🏛 Conselho de Classe</span>
                </div>
                <div class="da-card-body"></div>
            </div>

            <!-- Análise IA -->
            <div class="da-area-ai da-card" id="daCard_ai">
                <div class="da-card-header">
                    <span class="da-card-title">Análise Pedagógica</span>
                </div>
                <div class="da-card-body"></div>
            </div>

            <!-- Comentários -->
            <div class="da-area-coment da-card" id="daCard_comentarios">
                <div class="da-card-header">
                    <span class="da-card-title">💬 Comentários</span>
                </div>
                <div class="da-card-body"></div>
            </div>

            <!-- Sanções Disciplinares -->
            <div class="da-area-sancao da-card" id="daCard_sancoes">
                <div class="da-card-header">
                    <span class="da-card-title">⚖️ Sanções Disciplinares</span>
                </div>
                <div class="da-card-body"></div>
            </div>

            <!-- Segunda Chamada -->
            <div class="da-area-segch da-card" id="daCard_segunda_chamada">
                <div class="da-card-header">
                    <span class="da-card-title">📄 Segunda Chamada</span>
                </div>
                <div class="da-card-body"></div>
            </div>

        </div><!-- /da-grid -->
    </div><!-- /daDashboard -->

</div><!-- /content-wrap -->

<!-- Modal: Novo Atendimento (reutilizado do módulo de atendimentos) -->
<?php if (hasDbPermission('atendimentos.manage', false)): ?>
<?php require_once __DIR__ . '/../includes/atendimento_modal.php'; ?>
<?php endif; ?>

<script>
const daCurrentUserId      = <?= (int)$userId ?>;
const daCurrentUserProfile = '<?= addslashes($user['profile']) ?>';

<?php if (hasDbPermission('atendimentos.manage', false)): ?>
function daOpenNewAtendimento() {
    const st = vaDashboardAluno.getState();
    if (!st.alunoId) { Toast.show('Selecione um aluno primeiro.', 'warning'); return; }
    if (typeof openAtendimentoModal === 'function') {
        openAtendimentoModal({ aluno_id: st.alunoId });
    }
}
<?php endif; ?>
</script>

<?php
renderModalScripts();
renderToastScripts();
require_once __DIR__ . '/../includes/footer.php';
?>
