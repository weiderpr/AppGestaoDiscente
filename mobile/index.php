<?php
/**
 * Vértice Acadêmico — Mobile Dashboard (Simplificado)
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$curInst = getCurrentInstitution();
$pageTitle = 'Dashboard';
$currentPage = 'home';

require_once __DIR__ . '/header.php';
?>

<style>
    .m-stats-scroller {
        display: flex;
        gap: 1rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
        margin: 0 -1.5rem 2rem;
        padding: 0 1.5rem 0.5rem;
        scrollbar-width: none;
    }

    .m-stats-scroller::-webkit-scrollbar { display: none; }

    .m-stat-pill {
        flex: 0 0 auto;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        padding: 1rem 1.25rem;
        border-radius: 20px;
        min-width: 140px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }

    .m-stat-val {
        font-family: 'Outfit', sans-serif;
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-primary);
        display: block;
    }

    .m-stat-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    .m-feature-grid {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .m-feature-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1.25rem;
        text-decoration: none;
        box-shadow: 0 8px 24px rgba(0,0,0,0.04);
        position: relative;
        overflow: hidden;
    }

    .m-feature-card::after {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0;
        width: 6px;
        background: var(--accent-color, var(--color-primary));
        opacity: 0.8;
    }

    .m-feature-icon {
        width: 56px;
        height: 56px;
        border-radius: 18px;
        background: var(--accent-bg, var(--color-primary-light));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        flex-shrink: 0;
    }

    .m-feature-info {
        flex: 1;
    }

    .m-feature-name {
        font-weight: 700;
        font-size: 1.125rem;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .m-feature-desc {
        font-size: 0.8125rem;
        color: var(--text-muted);
        line-height: 1.4;
    }

    .m-feature-arrow {
        color: var(--text-muted);
        font-size: 1.25rem;
        opacity: 0.5;
    }
</style>

<div class="m-content" style="padding-top: 1rem;">
    
    <!-- Stats Scroller -->
    <div class="m-stats-scroller">
        <div class="m-stat-pill">
            <span class="m-stat-val">320</span>
            <span class="m-stat-label">Alunos</span>
        </div>
    </div>

    <h2 class="m-section-title">Ações Rápidas</h2>

    <div class="m-feature-grid">
        
        <a href="/courses/conselhos.php" class="m-feature-card" style="--accent-color:#4f46e5; --accent-bg:#eef2ff;">
            <div class="m-feature-icon">⚖️</div>
            <div class="m-feature-info">
                <div class="m-feature-name">Conselhos de Classe</div>
                <div class="m-feature-desc">Atas, notas e avaliações de desempenho.</div>
            </div>
            <div class="m-feature-arrow">›</div>
        </a>

        <a href="/mobile/courses.php" class="m-feature-card" style="--accent-color:#10b981; --accent-bg:#ecfdf5;">
            <div class="m-feature-icon">📚</div>
            <div class="m-feature-info">
                <div class="m-feature-name">Gestão de Cursos</div>
                <div class="m-feature-desc">Visualize suas turmas e disciplinas ativas.</div>
            </div>
            <div class="m-feature-arrow">›</div>
        </a>

        <a href="/profile.php" class="m-feature-card" style="--accent-color:#7c3aed; --accent-bg:#f5f3ff;">
            <div class="m-feature-icon">👤</div>
            <div class="m-feature-info">
                <div class="m-feature-name">Meu Perfil</div>
                <div class="m-feature-desc">Dados cadastrais e preferências de tema.</div>
            </div>
            <div class="m-feature-arrow">›</div>
        </a>

    </div>

    <div class="m-card" style="background:var(--gradient-brand); color:white; border:none; padding:1.5rem; text-align:center; border-radius:24px;">
        <div style="font-size:2rem; margin-bottom:1rem;">🚀</div>
        <h3 style="font-weight:800; font-size:1.125rem; margin-bottom:0.5rem; font-family:'Outfit';">Tudo em um só lugar</h3>
        <p style="font-size:0.875rem; opacity:0.9; line-height:1.5; margin-bottom:1.5rem;">Gerencie o sucesso acadêmico de seus alunos com agilidade e precisão.</p>
        <button class="m-btn" style="background:white; color:var(--color-primary); border:none; width:100%;" onclick="window.location.href='/courses/turmas.php'">
            Ver Minhas Turmas
        </button>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
