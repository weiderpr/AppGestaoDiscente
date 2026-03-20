<?php
/**
 * Vértice Acadêmico — Dashboard Principal
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user      = getCurrentUser();
$pageTitle = 'Dashboard';
$firstName = explode(' ', $user['name'])[0];

// Hora do dia para saudação
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12)      $greeting = 'Bom dia';
elseif ($hour >= 12 && $hour < 18) $greeting = 'Boa tarde';
else                                $greeting = 'Boa noite';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner fade-in">
    <div class="welcome-greeting"><?= $greeting ?>,</div>
    <div class="welcome-name"><?= htmlspecialchars($firstName) ?>! 👋</div>
    <div class="welcome-badge">
        🎓 <?= htmlspecialchars($user['profile']) ?>
    </div>
</div>

<!-- Stats Grid (placeholders para futuros indicadores) -->
<div class="stats-grid fade-in">

    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(79,70,229,.12);">📚</div>
        <div>
            <div class="stat-value">—</div>
            <div class="stat-label">Turmas</div>
        </div>
        <div class="stat-trend up">Em breve</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16,185,129,.12);">👥</div>
        <div>
            <div class="stat-value">—</div>
            <div class="stat-label">Discentes</div>
        </div>
        <div class="stat-trend up">Em breve</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(245,158,11,.12);">📊</div>
        <div>
            <div class="stat-value">—</div>
            <div class="stat-label">Indicadores</div>
        </div>
        <div class="stat-trend up">Em breve</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(239,68,68,.12);">🔔</div>
        <div>
            <div class="stat-value">—</div>
            <div class="stat-label">Alertas</div>
        </div>
        <div class="stat-trend up">Em breve</div>
    </div>

</div>

<!-- Dashboard Grid -->
<div class="dashboard-grid fade-in">

    <!-- Card Principal -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📈 Visão Geral dos Indicadores</span>
            <span class="badge-profile badge-<?= htmlspecialchars(explode(' ', $user['profile'])[0]) ?>">
                <?= htmlspecialchars($user['profile']) ?>
            </span>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:3rem;text-align:center;gap:1rem;">
            <div style="font-size:3.5rem;">🚀</div>
            <div style="font-size:1.125rem;font-weight:700;color:var(--text-primary);">Sistema em construção</div>
            <div style="color:var(--text-muted);max-width:360px;line-height:1.7;">
                Os indicadores discentes serão exibidos aqui conforme o sistema for desenvolvido.
                Esta estrutura base está pronta para receber os dados.
            </div>
        </div>
    </div>

    <!-- Card Lateral -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">ℹ️ Meu Perfil</span>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:1rem;">

            <!-- Avatar -->
            <div style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;padding:0.5rem 0 1rem;">
                <?php
                $initials = '';
                foreach (explode(' ', trim($user['name'])) as $part) {
                    $initials .= strtoupper(substr($part, 0, 1));
                    if (strlen($initials) >= 2) break;
                }
                ?>
                <?php if (!empty($user['photo']) && file_exists(__DIR__ . '/' . $user['photo'])): ?>
                <img src="/<?= htmlspecialchars($user['photo']) ?>"
                     alt="Foto de <?= htmlspecialchars($user['name']) ?>"
                     style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--color-primary);">
                <?php else: ?>
                <div style="width:72px;height:72px;border-radius:50%;background:var(--gradient-brand);display:flex;align-items:center;justify-content:center;color:white;font-size:1.5rem;font-weight:700;border:3px solid var(--color-primary);">
                    <?= $initials ?>
                </div>
                <?php endif; ?>
                <div style="text-align:center;">
                    <div style="font-weight:700;color:var(--text-primary);"><?= htmlspecialchars($user['name']) ?></div>
                    <div style="font-size:0.8125rem;color:var(--text-muted);"><?= htmlspecialchars($user['email']) ?></div>
                </div>
            </div>

            <!-- Info -->
            <?php $rows = [
                ['📱', 'Telefone', $user['phone'] ?: 'Não informado'],
                ['🎭', 'Perfil',   $user['profile']],
                ['🎨', 'Tema',     $user['theme'] === 'dark' ? 'Escuro 🌙' : 'Claro ☀️'],
            ]; ?>
            <?php foreach ($rows as [$icon, $label, $value]): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid var(--border-color);font-size:0.875rem;">
                <span style="color:var(--text-muted);"><?= $icon ?> <?= $label ?></span>
                <span style="font-weight:500;color:var(--text-primary);"><?= htmlspecialchars($value) ?></span>
            </div>
            <?php endforeach; ?>

            <a href="/profile.php" class="btn btn-secondary btn-sm" style="margin-top:0.5rem;width:100%;text-align:center;">
                ✏️ Editar Perfil
            </a>

        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
