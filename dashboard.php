<?php
/**
 * Vértice Acadêmico — Dashboard Principal
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user      = getCurrentUser();

// --- REDIRECIONAMENTO MOBILE ---
// Perfis que usam a interface simplificada no mobile
$mobileProfiles = ['Professor', 'Pedagogo', 'Assistente Social', 'Psicólogo'];
if (isMobileDevice() && (in_array($user['profile'], $mobileProfiles) || ($user['is_teacher'] ?? 0) == 1)) {
    header('Location: /mobile/index.php');
    exit;
}

$pageTitle = 'Dashboard';
$firstName = explode(' ', $user['name'])[0];

// Hora do dia para saudação
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12)      $greeting = 'Bom dia';
elseif ($hour >= 12 && $hour < 18) $greeting = 'Boa tarde';
else                                $greeting = 'Boa noite';

// --- FALLBACK DE NOTIFICAÇÕES ---
// Se o usuário desativou a pilha flutuante, mostramos aqui no dashboard
$showNotifPanel = (($user['exibir_notificacoes'] ?? 1) == 0);
$notifications = [];
if ($showNotifPanel) {
    require_once __DIR__ . '/src/App/Services/Service.php';
    require_once __DIR__ . '/src/App/Services/NotificationService.php';
    $notifService = new \App\Services\NotificationService();
    $notifications = $notifService->getUnreadForUser($user);
    $extraCSS[] = '/assets/css/dashboard_notifications.css?v=1.3';
    $extraCSS[] = '/assets/css/dashboard_layout.css?v=1.0';
    $extraJS[]  = '/assets/js/dashboard_notifications.js?v=1.3';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-work-wrapper">
    <?php if (hasDbPermission('dashboard.online_users', false)): ?>
        <aside class="dashboard-left-sidebar" aria-label="Usuários Online">
            <?php include __DIR__ . '/componentes/usuarios_online/index.php'; ?>
        </aside>
    <?php endif; ?>

    <div class="dashboard-main-content-area">

        <?php 
        $showSocialFeed = hasDbPermission('social.feed_view', false);
        
        // Se o Feed Social estiver ativo, ocultamos os outros componentes da área principal (exceto notificações)
        if (!$showSocialFeed): 
        ?>
            <!-- Componentes Tradicionais do Dashboard -->
            <?php if (in_array($user['profile'], ['Pedagogo', 'Assistente Social', 'Psicólogo']) && !empty($curInst['id'])): 
                $db = getDB();
                $stCourses = $db->prepare("
                    SELECT c.id, c.name, c.location
                    FROM courses c
                    WHERE c.institution_id = ?
                    ORDER BY c.name
                ");
                $stCourses->execute([$curInst['id']]);
                $teacherCourses = $stCourses->fetchAll();
            ?>
                <?php if (!empty($teacherCourses)): ?>
                <div class="fade-in" style="margin-bottom:2.5rem;">
                    <h2 style="font-size:1.125rem;font-weight:700;color:var(--text-primary);margin-bottom:1.25rem;display:flex;align-items:center;gap:0.5rem;">
                        📚 Cursos Ativos
                    </h2>
                    <div class="stats-grid" style="grid-template-columns:repeat(auto-fill, minmax(240px, 1fr));">
                        <?php foreach ($teacherCourses as $tc): ?>
                        <a href="/courses/turmas.php?course_id=<?= $tc['id'] ?>" class="stat-card" style="text-decoration:none;transition:transform 0.2s;cursor:pointer;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
                            <div class="stat-icon" style="background:var(--color-primary-light);color:var(--color-primary);font-size:1.5rem;">
                                📚
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:1rem;font-weight:700;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($tc['name']) ?>">
                                    <?= htmlspecialchars($tc['name']) ?>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;display:flex;align-items:center;gap:0.25rem;">
                                    📍 <?= $tc['location'] ? htmlspecialchars($tc['location']) : 'Local não informado' ?>
                                </div>
                            </div>
                            <div style="color:var(--color-primary);font-size:1.25rem;margin-left:0.5rem;">
                                ➜
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php elseif ($user['profile'] === 'Professor' && !empty($curInst['id'])): 
                $db = getDB();
                $stCourses = $db->prepare("
                    SELECT DISTINCT c.id, c.name, c.location
                    FROM courses c
                    JOIN turmas t ON c.id = t.course_id
                    JOIN turma_disciplinas td ON t.id = td.turma_id
                    JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id
                    WHERE tdp.professor_id = ? AND c.institution_id = ?
                    ORDER BY c.name
                ");
                $stCourses->execute([$user['id'], $curInst['id']]);
                $teacherCourses = $stCourses->fetchAll();
            ?>
                <?php if (!empty($teacherCourses)): ?>
                <div class="fade-in" style="margin-bottom:2.5rem;">
                    <h2 style="font-size:1.125rem;font-weight:700;color:var(--text-primary);margin-bottom:1.25rem;display:flex;align-items:center;gap:0.5rem;">
                        📚 Meus Cursos
                    </h2>
                    <div class="stats-grid" style="grid-template-columns:repeat(auto-fill, minmax(240px, 1fr));">
                        <?php foreach ($teacherCourses as $tc): ?>
                        <a href="/courses/turmas.php?course_id=<?= $tc['id'] ?>" class="stat-card" style="text-decoration:none;transition:transform 0.2s;cursor:pointer;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
                            <div class="stat-icon" style="background:var(--color-primary-light);color:var(--color-primary);font-size:1.5rem;">
                                📚
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:1rem;font-weight:700;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($tc['name']) ?>">
                                    <?= htmlspecialchars($tc['name']) ?>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;display:flex;align-items:center;gap:0.25rem;">
                                    📍 <?= $tc['location'] ? htmlspecialchars($tc['location']) : 'Local não informado' ?>
                                </div>
                            </div>
                            <div style="color:var(--color-primary);font-size:1.25rem;margin-left:0.5rem;">
                                ➜
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    
        <!-- Social Feed Integration -->
        <?php if ($showSocialFeed): ?>
            <?php require_once __DIR__ . '/social/feed_component.php'; ?>
        <?php endif; ?>

    </div> <!-- /dashboard-main-content-area -->

    <?php if ($showNotifPanel): ?>
    <aside class="dashboard-notif-sidebar" aria-label="Notificações do Sistema">
        <div class="dash-notif-header">
            <span class="dash-notif-title">🔔 Notificações</span>
            <?php if (count($notifications) > 0): ?>
                <span class="dash-notif-count"><?= count($notifications) ?></span>
            <?php endif; ?>
        </div>

        <div class="dash-notif-list">
            <?php if (empty($notifications)): ?>
                <div class="dash-notif-empty">
                    <span>✨ Todas as notificações foram lidas!</span>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): 
                    $canSeeLink = empty($notif['required_permission']) || 
                                 (($userPermissions['IS_ADMIN'] ?? 0) === 1) || 
                                 (isset($userPermissions[$notif['required_permission']]) && $userPermissions[$notif['required_permission']] == 1);
                ?>
                <div class="dash-notif-card type-<?= htmlspecialchars($notif['tipo']) ?>" data-id="<?= $notif['id'] ?>">
                    <div class="dash-notif-card-header">
                        <span class="dash-notif-card-title"><?= htmlspecialchars($notif['titulo']) ?></span>
                        <button class="dash-notif-btn-read" data-id="<?= $notif['id'] ?>" title="Marcar como lida">✕</button>
                    </div>
                    <div class="dash-notif-card-body">
                        <?= htmlspecialchars(strip_tags($notif['mensagem'])) ?>
                    </div>
                    <div class="dash-notif-card-footer">
                        <span><?= date('d/m/y H:i', strtotime($notif['created_at'])) ?></span>
                        <?php if ($notif['link_acao'] && $canSeeLink): ?>
                            <a href="<?= $notif['link_acao'] ?>" class="dash-notif-link">Ver detalhes →</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
    <?php endif; ?>
</div> <!-- /dashboard-work-wrapper -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
