<?php
/**
 * Vértice Acadêmico — Ação do Conselho de Classe
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$allowed = ['Administrador', 'Coordenador', 'Pedagogo', 'Assistente Social', 'Psicólogo'];
if (!$user || !in_array($user['profile'], $allowed)) {
    header('Location: /dashboard.php');
    exit;
}

$db = getDB();
$inst = getCurrentInstitution();
$instId = $inst['id'];

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/courses/conselho_acao.php'));
    exit;
}

$conselhoId = (int)($_GET['id'] ?? 0);

if (!$conselhoId) {
    header('Location: /courses/conselhos.php');
    exit;
}

$sql = "SELECT cc.*, t.description as turma_name, c.name as course_name 
        FROM conselhos_classe cc
        JOIN turmas t ON cc.turma_id = t.id
        JOIN courses c ON cc.course_id = c.id
        WHERE cc.id = ? AND cc.institution_id = ?";
$st = $db->prepare($sql);
$st->execute([$conselhoId, $instId]);
$conselho = $st->fetch();

if (!$conselho) {
    header('Location: /courses/conselhos.php');
    exit;
}

$conselhoConcluido = !$conselho['is_active'];

$turmaId = $conselho['turma_id'];

$success = '';
$error = '';
$action = $_POST['action'] ?? '';

if ($action === 'finalizar_conselho') {
    $db->prepare('UPDATE conselhos_classe SET is_active = 0 WHERE id = ?')->execute([$conselhoId]);
    header("Location: conselho_acao.php?id=$conselhoId&tab=avaliacao&success=" . urlencode('Conselho de Classe finalizado com sucesso!'));
    exit;
}

if ($action === 'salvar_presenca') {
    $presentes = $_POST['presentes'] ?? [];
    $db->prepare('DELETE FROM conselhos_presentes WHERE conselho_id = ?')->execute([$conselhoId]);
    foreach ($presentes as $userId) {
        $db->prepare('INSERT INTO conselhos_presentes (conselho_id, user_id) VALUES (?, ?)')->execute([$conselhoId, (int)$userId]);
    }
    header("Location: conselho_acao.php?id=$conselhoId&tab=presenca&success=" . urlencode('Lista de presença salva com sucesso!'));
    exit;
}

// Mensagens passadas via GET (após redirect)
if (isset($_GET['success'])) $success = $_GET['success'];
if (isset($_GET['error']))   $error = $_GET['error'];

$st = $db->prepare('SELECT user_id FROM conselhos_presentes WHERE conselho_id = ?');
$st->execute([$conselhoId]);
$presentesAtuais = array_column($st->fetchAll(), 'user_id');

$sql = "SELECT DISTINCT u.id, u.name, u.photo, u.profile
        FROM users u
        JOIN turma_disciplina_professores tdp ON tdp.professor_id = u.id
        JOIN turma_disciplinas td ON tdp.turma_disciplina_id = td.id
        WHERE td.turma_id = ?
        ORDER BY u.name";
$st = $db->prepare($sql);
$st->execute([$conselho['turma_id']]);
$professores = $st->fetchAll();

$stFb = $db->prepare('
    SELECT ra.comentario, ra.created_at, ra.id as resposta_id
    FROM respostas_avaliacao ra
    WHERE ra.conselho_id = ? AND ra.comentario IS NOT NULL AND ra.comentario != ""
    ORDER BY ra.created_at DESC
');
$stFb->execute([$conselhoId]);
$feedbacks = $stFb->fetchAll();

$profiles = PROFILES;
$selectedProfile = $_GET['profile'] ?? '';

$usuariosPorPerfil = [];
if ($selectedProfile) {
    $stUsers = $db->prepare("
        SELECT id, name, profile FROM users 
        WHERE is_active = 1 AND profile = ?
        AND id NOT IN (
            SELECT professor_id FROM turma_disciplina_professores tdp 
            JOIN turma_disciplinas td ON tdp.turma_disciplina_id = td.id 
            WHERE td.turma_id = ?
        )
        ORDER BY name
    ");
    $stUsers->execute([$selectedProfile, $conselho['turma_id']]);
    $usuariosPorPerfil = $stUsers->fetchAll();
}

$stEtapas = $db->prepare('
    SELECT e.id, e.description, e.media_nota
    FROM conselhos_etapas ce
    JOIN etapas e ON ce.etapa_id = e.id
    WHERE ce.conselho_id = ?
    ORDER BY e.id
');
$stEtapas->execute([$conselhoId]);
$etapasConselho = $stEtapas->fetchAll();

$etapasIds = array_column($etapasConselho, 'id');

$alunos = [];
if (!empty($etapasIds)) {
    $placeholders = implode(',', array_fill(0, count($etapasIds), '?'));
    
    $sql = "
        SELECT a.id, a.nome, a.photo,
               COALESCE(SUM(en.faltas), 0) as total_faltas,
               SUM(en.nota) as soma_notas,
               SUM(e.media_nota) as soma_media_etapas
        FROM alunos a
        JOIN turma_alunos ta ON a.id = ta.aluno_id
        LEFT JOIN etapa_notas en ON a.id = en.aluno_id AND en.etapa_id IN ($placeholders)
        LEFT JOIN etapas e ON en.etapa_id = e.id
        WHERE ta.turma_id = ?
        GROUP BY a.id, a.nome, a.photo
    ";
    
    $params = array_merge($etapasIds, [$conselho['turma_id']]);
    $st = $db->prepare($sql);
    $st->execute($params);
    $alunosRaw = $st->fetchAll();
    
    $sqlDisc = "
        SELECT en.aluno_id, d.descricao as disciplina,
               SUM(en.nota) as soma_nota,
               SUM(e.media_nota) as soma_media
        FROM etapa_notas en
        JOIN disciplinas d ON en.disciplina_codigo = d.codigo
        JOIN etapas e ON en.etapa_id = e.id
        WHERE en.aluno_id IN (SELECT aluno_id FROM turma_alunos WHERE turma_id = ?)
        AND en.etapa_id IN ($placeholders)
        GROUP BY en.aluno_id, d.codigo
        HAVING SUM(en.nota) < SUM(e.media_nota)
    ";
    $paramsDisc = array_merge([$conselho['turma_id']], $etapasIds);
    $stDisc = $db->prepare($sqlDisc);
    $stDisc->execute($paramsDisc);
    $discsPerdidas = $stDisc->fetchAll();
    
    $discsAgrupadas = [];
    foreach ($discsPerdidas as $d) {
        $discsAgrupadas[$d['aluno_id']][] = $d['disciplina'] . '||' . $d['soma_nota'] . '||' . $d['soma_media'];
    }
    
    $alunos = [];
    foreach ($alunosRaw as $aluno) {
        $somaNotas = (float)$aluno['soma_notas'];
        $somaMediaEtapas = (float)$aluno['soma_media_etapas'];
        $mediasPerdidas = ($somaNotas < $somaMediaEtapas) ? 1 : 0;
        
        $aluno['medias_perdidas'] = isset($discsAgrupadas[$aluno['id']]) ? count($discsAgrupadas[$aluno['id']]) : 0;
        $aluno['disciplinas_perdidas'] = isset($discsAgrupadas[$aluno['id']]) ? implode('||', $discsAgrupadas[$aluno['id']]) : '';
        $alunos[] = $aluno;
    }
    
    usort($alunos, function($a, $b) {
        if ($b['medias_perdidas'] !== $a['medias_perdidas']) {
            return $b['medias_perdidas'] - $a['medias_perdidas'];
        }
        return strcmp($a['nome'], $b['nome']);
    });
}

$pageTitle = 'Conselho de Classe - ' . htmlspecialchars($conselho['descricao']);
$extraJS = [
    '/assets/js/sentiment_system.js?v=1.2',
    '/assets/js/performance_system.js?v=2.2',
    'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'
];
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.tabs-nav { display:flex; gap:.25rem; border-bottom:1px solid var(--border-color); margin-bottom:1.5rem; }
.tab-btn { 
    padding:.75rem 1.25rem; border:none; background:none; cursor:pointer; 
    font-size:.875rem; font-weight:500; color:var(--text-muted); 
    border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .2s;
}
.tab-btn:hover { color:var(--text-primary); }
.tab-btn.active { color:var(--color-primary); border-bottom-color:var(--color-primary); }
.tab-btn.inactive { opacity:0.5; cursor:not-allowed; text-decoration:line-through; }
.tab-btn.inactive:hover { color:var(--text-muted); }
.tab-content { display:none; }
.tab-content.active { display:block; }

.presence-list { display:flex; flex-direction:column; gap:.5rem; }
.presence-item { 
    display:flex; align-items:center; gap:1rem; padding:.75rem 1rem; 
    background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-md);
    transition:all .2s;
}
.presence-item:hover { background:var(--bg-hover); }
.presence-item input[type="checkbox"] { width:18px; height:18px; cursor:pointer; }
.presence-item .avatar { 
    width:36px; height:36px; border-radius:50%; background:var(--bg-surface-2nd); 
    display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.875rem;
    overflow:hidden;
}
.presence-item .avatar img { width:100%; height:100%; object-fit:cover; }
.presence-item .info { flex:1; }
.presence-item .name { font-weight:600; color:var(--text-primary); }
.presence-item .profile { font-size:.75rem; color:var(--text-muted); }

.btn-add-user { 
    display:inline-flex; align-items:center; gap:.5rem; padding:.5rem 1rem; 
    background:var(--bg-surface); border:1px dashed var(--border-color); 
    border-radius:var(--radius-md); cursor:pointer; color:var(--text-muted);
    transition:all .2s; width:100%; justify-content:center;
}
.btn-add-user:hover { border-color:var(--color-primary); color:var(--color-primary); background:var(--color-primary-light); }

.detail-tabs-container { 
    display:flex; 
    gap:0; 
    margin-top:1.5rem;
    border:1px solid var(--border-color); 
    border-radius:var(--radius-lg); 
    background:var(--bg-surface);
    min-height:400px;
}
.detail-sidebar {
    width:200px;
    border-right:1px solid var(--border-color);
    background:var(--bg-surface-2nd);
    border-radius:var(--radius-lg) 0 0 var(--radius-lg);
    padding:.75rem;
    display:flex;
    flex-direction:column;
    gap:.5rem;
}
.detail-sidebar-tabs {
    flex:1;
    display:flex;
    flex-direction:column;
    padding:1rem .5rem;
    gap:.25rem;
    overflow-y:auto;
}

/* SUB TABS CSS */
.sub-tabs-nav { display:flex; gap:.5rem; margin-bottom:1rem; border-bottom:1px solid var(--border-color); padding-bottom:.5rem; }
.sub-tab-btn { 
    padding:.5rem 1rem; border:none; background:var(--bg-card); cursor:pointer; 
    font-size:.75rem; font-weight:600; color:var(--text-muted); border-radius:var(--radius-md);
    border:1px solid var(--border-color); transition:all .2s;
}
.sub-tab-btn.active { 
    background:var(--color-primary); color:white; border-color:var(--color-primary);
}
.sub-tab-btn:hover:not(.active) {
    background:var(--bg-hover);
    border-color:var(--border-color-hover);
}

.detail-sidebar-header {
    font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:.5rem;
}
.detail-tab-item {
    display:flex;align-items:center;justify-content:space-between;
    padding:.5rem .75rem;
    border-radius:var(--radius-md);
    cursor:pointer;
    font-size:.8125rem;
    color:var(--text-secondary);
    background:none;
    border:none;
    text-align:left;
    transition:all .2s;
}
.detail-tab-item:hover { background:var(--bg-hover); }
.detail-tab-item.active { background:var(--color-primary-light);color:var(--color-primary);font-weight:600; }
.detail-tab-item .close-btn {
    background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;font-size:.875rem;opacity:.7;
}
.detail-tab-item .close-btn:hover { opacity:1;color:var(--color-danger); }
.detail-content { flex:1;padding:1.5rem;overflow-y:auto; }
.detail-content-empty {
    display:flex;align-items:center;justify-content:center;height:100%;
    color:var(--text-muted);font-size:.875rem;
}


/* Modal styles moved to shared component student_comment_modal.php */

/* Privacy Blur Effect */
.privacy-blur {
    filter: blur(8px);
    transition: filter 0.3s ease;
    cursor: pointer;
    user-select: none;
    position: relative;
    border-radius: var(--radius-md);
    overflow: hidden;
}
.privacy-blur:hover {
    filter: blur(4px);
}
.privacy-blur.revealed {
    filter: blur(0);
    cursor: default;
    user-select: text;
}
.privacy-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.05);
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.8125rem;
    z-index: 10;
    pointer-events: none;
    transition: opacity 0.3s ease;
    text-align: center;
    padding: 1rem;
}
.privacy-blur.revealed .privacy-overlay {
    opacity: 0;
    visibility: hidden;
}
</style>

<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <a href="/courses/conselhos.php" style="color:var(--text-muted);text-decoration:none;font-size:.875rem;">← Voltar para Conselhos</a>
        <h1 class="page-title">📋 <?= htmlspecialchars($conselho['descricao']) ?></h1>
        <p class="page-subtitle">
            <strong><?= htmlspecialchars($conselho['turma_name']) ?></strong> — <?= htmlspecialchars($conselho['course_name']) ?> (<?= htmlspecialchars($conselho['descricao']) ?>)
            <span style="color:var(--text-muted);"> | <?= date('d/m/Y H:i', strtotime($conselho['data_hora'])) ?></span>
        </p>
    </div>
    <div style="display:flex; gap:0.75rem;">
        <button type="button" class="btn btn-primary" onclick="<?= $conselhoConcluido ? '' : "openReferralModal(0, 'Encaminhamento para a Turma', conselhoId)" ?>" <?= $conselhoConcluido ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?> style="display:inline-flex; align-items:center; gap:0.5rem;">
            <span>📌 Encaminhamento Turma</span>
        </button>
        <button type="button" class="btn btn-secondary" onclick="<?= $conselhoConcluido ? '' : "openCouncilRecordModal(conselhoId, null)" ?>" <?= $conselhoConcluido ? 'disabled style="opacity:0.5; cursor:not-allowed; background:var(--bg-surface); border:1px solid var(--border-color); color:var(--text-primary);"' : 'style="display:inline-flex; align-items:center; gap:0.5rem; background:var(--bg-surface); border:1px solid var(--border-color); color:var(--text-primary);"' ?>>
            <span>📝 Registros Gerais</span>
        </button>
    </div>
</div>

<!-- Notifications handled via Toast.js -->
<?php if ($success || $error): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    <?php if ($success): ?> Toast.success(<?= json_encode($success) ?>); <?php endif; ?>
    <?php if ($error): ?> Toast.error(<?= json_encode($error) ?>); <?php endif; ?>
});
</script>
<?php endif; ?>

<div class="tabs-nav fade-in" id="mainTabsNav">
    <button class="tab-btn <?= $conselhoConcluido ? 'inactive' : 'active' ?>" data-tab="presenca" onclick="showTab('presenca')">1. Lista de Presença</button>
    <button class="tab-btn <?= $conselhoConcluido ? 'inactive' : '' ?>" data-tab="alunos" onclick="showTab('alunos')">2. Alunos</button>
    <button class="tab-btn <?= $conselhoConcluido ? 'inactive' : '' ?>" data-tab="alunos_detalhes" onclick="showTab('alunos_detalhes')">2.1 Detalhes dos Alunos</button>
    <button class="tab-btn" data-tab="encaminhamentos" onclick="showTab('encaminhamentos')">3. Encaminhamentos</button>
    <button class="tab-btn <?= $conselhoConcluido ? 'active' : '' ?>" data-tab="ata" onclick="showTab('ata')">4. Ata do Conselho</button>
    <button class="tab-btn <?= $conselhoConcluido ? 'active' : '' ?>" data-tab="avaliacao" onclick="showTab('avaliacao')">5. Finalização</button>
</div>

<div id="presenca" class="tab-content <?= $conselhoConcluido ? '' : 'active' ?> fade-in">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Lista de Presença</span>
            <span style="font-size:.875rem;color:var(--text-muted);"><?= count($professores) ?> professor(es) vinculado(s)</span>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="salvar_presenca">
            <div class="card-body" style="padding-bottom:.5rem;">
                <p style="margin-bottom:1rem;color:var(--text-muted);font-size:.875rem;">
                    Marque os presentes na reunião deste conselho de classe.
                </p>
                
                <?php if (empty($professores)): ?>
                <p style="text-align:center;padding:2rem;color:var(--text-muted);">
                    Nenhum professor vinculado a esta turma.
                </p>
                <?php else: ?>
                <div class="presence-list">
                    <?php foreach ($professores as $p): ?>
                    <label class="presence-item">
                        <input type="checkbox" name="presentes[]" value="<?= $p['id'] ?>" <?= in_array($p['id'], $presentesAtuais) ? 'checked' : '' ?>>
                        <div class="avatar">
                            <?php if ($p['photo'] && file_exists(__DIR__ . '/../' . $p['photo'])): ?>
                                <img src="/<?= htmlspecialchars($p['photo']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                            <?php else: ?>
                                <?= mb_strtoupper(mb_substr($p['name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="info">
                            <div class="name"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="profile"><?= htmlspecialchars($p['profile']) ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border-color);">
                    <p style="margin-bottom:1rem;font-weight:600;">Adicionar outros participantes:</p>
                    
                    <div class="form-group" style="margin-bottom:1rem;">
                        <select id="filter_profile" class="form-control" onchange="window.location.href = '?id=<?= $conselhoId ?>&profile=' + this.value">
                            <option value="">Selecione o tipo de participante...</option>
                            <?php foreach ($profiles as $p): ?>
                            <option value="<?= $p ?>" <?= $selectedProfile === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="outros_participantes" class="presence-list">
                        <?php if ($selectedProfile): ?>
                            <p style="text-align:center;color:var(--text-muted);font-size:.875rem;">Carregando...</p>
                        <?php else: ?>
                        <p style="text-align:center;color:var(--text-muted);font-size:.875rem;">
                            Selecione um tipo de participante acima para adicionar.
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="display:flex;justify-content:flex-end;padding:1rem 1.5rem;background:var(--bg-surface-2nd);border-top:1px solid var(--border-color);margin-top:auto;">
                <button type="submit" class="btn btn-primary">💾 Salvar Presença</button>
            </div>
        </form>
    </div>
</div>

<div id="alunos" class="tab-content fade-in">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Lista de Alunos</span>
            <span style="font-size:.875rem;color:var(--text-muted);"><?= count($alunos) ?> aluno(s) | Etapas: <?= implode(', ', array_column($etapasConselho, 'description')) ?></span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($etapasConselho)): ?>
            <div style="text-align:center;padding:3rem;color:var(--text-muted);">
                <p style="font-size:3rem;margin-bottom:1rem;">⚠️</p>
                <p>Nenhuma etapa vinculada a este conselho.</p>
                <p style="font-size:.875rem;">Vincule as etapas no cadastro do conselho para visualizar a análise dos alunos.</p>
            </div>
            <?php elseif (empty($alunos)): ?>
            <div style="text-align:center;padding:3rem;color:var(--text-muted);">
                <p style="font-size:3rem;margin-bottom:1rem;">🎓</p>
                <p>Nenhum aluno encontrado nesta turma.</p>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                    <thead>
                        <tr style="background:var(--bg-surface-2nd);">
                            <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-color);">Foto</th>
                            <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-color);">Aluno</th>
                            <th style="padding:.75rem 1rem;text-align:center;font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-color);">Médias Perdidas</th>
                            <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-color);">Disciplinas</th>
                            <th style="padding:.75rem 1rem;text-align:center;font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-color);">Total Faltas</th>
                            <th style="padding:.75rem .5rem;text-align:center;font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-color);white-space:nowrap;width:1%;">Quant</th>
                            <th style="padding:.75rem .5rem;text-align:center;font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-color);white-space:nowrap;width:1%;">Quali</th>
                            <th style="padding:.75rem 1rem;text-align:center;font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-color);">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alunos as $aluno): ?>
                        <tr data-aluno-id="<?= $aluno['id'] ?>" style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:.75rem 1rem;vertical-align:middle;">
                                <div style="width:40px;height:40px;border-radius:50%;background:var(--bg-surface-2nd);display:flex;align-items:center;justify-content:center;overflow:hidden;">
                                    <?php if ($aluno['photo'] && file_exists(__DIR__ . '/../' . $aluno['photo'])): ?>
                                        <img src="/<?= htmlspecialchars($aluno['photo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                    <?php else: ?>
                                        <span style="font-weight:700;color:var(--text-muted);"><?= mb_strtoupper(mb_substr($aluno['nome'], 0, 1)) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="padding:.75rem 1rem;vertical-align:middle;">
                                <span style="font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($aluno['nome']) ?></span>
                            </td>
                            <td style="padding:.75rem 1rem;vertical-align:middle;text-align:center;">
                                <?php if ($aluno['medias_perdidas'] > 0): ?>
                                    <span style="display:inline-block;padding:.25rem .75rem;background:#fef2f2;color:#dc2626;border-radius:var(--radius-md);font-weight:600;">
                                        <?= $aluno['medias_perdidas'] ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:var(--color-success);font-weight:600;">0</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:.75rem 1rem;vertical-align:middle;">
                                <?php if (!empty($aluno['disciplinas_perdidas'])): ?>
                                    <div style="display:flex;flex-wrap:wrap;gap:.375rem;">
                                        <?php 
                                        $discs = explode('||', $aluno['disciplinas_perdidas']);
                                        $disciplinasFormatadas = [];
                                        for ($i = 0; $i < count($discs); $i += 3) {
                                            if (isset($discs[$i])) {
                                                $disciplinasFormatadas[] = [
                                                    'nome' => $discs[$i],
                                                    'nota' => $discs[$i + 1] ?? '',
                                                    'media' => $discs[$i + 2] ?? ''
                                                ];
                                            }
                                        }
                                        foreach ($disciplinasFormatadas as $disc): ?>
                                            <span style="display:inline-block;padding:.125rem .5rem;background:#fef2f2;color:#dc2626;border-radius:var(--radius-sm);font-size:.75rem;font-weight:500;cursor:help;" title="Nota: <?= htmlspecialchars($disc['nota']) ?> | Média: <?= htmlspecialchars($disc['media']) ?>">
                                                <?= htmlspecialchars($disc['nome']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:.8125rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:.75rem 1rem;vertical-align:middle;text-align:center;">
                                <?php if ($aluno['total_faltas'] > 0): ?>
                                    <span style="font-weight:600;color:var(--text-secondary);"><?= $aluno['total_faltas'] ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">0</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:.75rem 1rem;text-align:center;vertical-align:middle;">
                                <div id="perf-trend-<?= $aluno['id'] ?>" class="performance-trend-container" data-aluno-id="<?= $aluno['id'] ?>" data-turma-id="<?= $turmaId ?>"></div>
                            </td>
                            <td style="padding:.75rem 1rem;text-align:center;vertical-align:middle;">
                                <div id="trend-<?= $aluno['id'] ?>" class="sentiment-trend-container" data-aluno-id="<?= $aluno['id'] ?>" data-turma-id="<?= $turmaId ?>"></div>
                            </td>
                            <td style="padding:.75rem 1rem;text-align:center;vertical-align:middle;">
                                <button type="button" title="Ver Detalhes" 
                                        style="background:var(--color-primary-light); color:var(--color-primary); border:none; width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:var(--radius-md); transition:all .2s; cursor:pointer;"
                                        onmouseover="this.style.background='var(--color-primary)'; this.style.color='white'; this.style.transform='scale(1.05)';" 
                                        onmouseout="this.style.background='var(--color-primary-light)'; this.style.color='var(--color-primary)'; this.style.transform='scale(1)';"
                                        onclick='openAlunoModal(<?= json_encode($aluno) ?>)'>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .radar-chart-container { width: 100%; max-width: 450px; margin: 0 auto; min-height: 350px; }
    
    @media print {
        body * { visibility: hidden; }
        #ata, #ata *, .ata-document, .ata-document * { visibility: visible; }
        .ata-document { 
            position: absolute; left: 0; top: 0; width: 100%; 
            margin: 0 !important; padding: 0 !important; 
            box-shadow: none !important; border: none !important; 
        }
        .sidebar, .header, .tabs-container, .card-header, .btn, .modal-backdrop { display: none !important; }
        #ata { display: block !important; width: 100%; position: absolute; left: 0; top: 0; }
        .tab-content:not(.active) { display: none !important; }
        @page { margin: 1cm; }
    }
</style>

<div id="alunos_detalhes" class="tab-content fade-in">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Detalhes dos Alunos</span>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="detail-tabs-container">
                <div class="detail-sidebar">
                    <div class="detail-sidebar-header">Alunos Detalhados</div>
                    <div id="detail-tabs-list" style="display:flex;flex-direction:column;gap:.25rem;">
                    </div>
                </div>
                <div class="detail-content" id="detail-content">
                    <div class="detail-content-empty">
                        Clique no botão 👁️ de um aluno na aba "2. Alunos" para visualizar os detalhes.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="encaminhamentos" class="tab-content fade-in">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Conferência de Encaminhamentos</span>
            <span style="font-size:.875rem;color:var(--text-muted);">Todas as providências registradas nesta sessão</span>
        </div>
        <div class="card-body" id="council_referrals_list" style="padding:0;">
            <!-- Renderizado via AJAX no referrals_system.js -->
            <div style="text-align:center;padding:3rem;color:var(--text-muted);">
                <p style="font-size:3rem;margin-bottom:1rem;">📌</p>
                <p>Nenhum encaminhamento carregado.</p>
            </div>
        </div>
    </div>
</div>

<div id="ata" class="tab-content fade-in">
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <span class="card-title">Ata do Conselho</span>
                <span style="font-size:.875rem;color:var(--text-muted);">Documento consolidado da sessão</span>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="window.print()" style="display:inline-flex; align-items:center; gap:0.4rem;">
                <span>🖨️ Imprimir Ata</span>
            </button>
        </div>
        <div class="card-body" id="ata_content_area" style="background:var(--bg-surface-2nd); padding:2rem;">
            <!-- Renderizado via AJAX no conselho_ata_system.js -->
            <div style="text-align:center;padding:3rem;color:var(--text-muted);">
                <p style="font-size:3rem;margin-bottom:1rem;">📝</p>
                <p>Nenhuma informação carregada para a Ata.</p>
            </div>
        </div>
    </div>
</div>

<div id="avaliacao" class="tab-content fade-in">
    <div class="card">
        <div class="card-body" style="text-align:center;padding:3rem;">
            <?php if ($conselho['is_active']): ?>
                <div style="max-width:400px; margin:0 auto;">
                    <p style="font-size:3rem;margin-bottom:1rem;">🏁</p>
                    <h3 style="margin-bottom:1rem;color:var(--text-primary);">Finalizar Sessão</h3>
                    <p style="color:var(--text-muted);margin-bottom:2rem;font-size:.875rem;">
                        Ao finalizar, este conselho será marcado como concluído e não poderá mais receber novos encaminhamentos ou registros.
                    </p>
                    <form method="POST" id="formFinalizar">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="finalizar_conselho">
                        <button type="button" class="btn btn-primary btn-lg" style="width:100%;" onclick="confirmFinalizar()">✨ Finalizar Conselho de Classe</button>
                    </form>
                </div>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; align-items:center; gap:2rem;">
                    <div>
                        <p style="font-size:3rem;margin-bottom:1rem;">✅</p>
                        <h3 style="color:var(--text-primary);">Conselho Concluído</h3>
                        <p style="color:var(--text-muted);font-size:.875rem;">Sessão encerrada em <?= date('d/m/Y H:i', strtotime($conselho['updated_at'])) ?></p>
                    </div>

                    <?php if ($conselho['avaliacao_id']): ?>
                    <div style="background:var(--bg-surface-2nd); padding:2rem; border-radius:var(--radius-xl); border:1px solid var(--border-color); display:flex; flex-direction:column; align-items:center; gap:1.5rem; max-width:350px;">
                        <div style="text-align:center;">
                            <h4 style="color:var(--text-primary); margin-bottom:0.5rem;">Pesquisa de Satisfação</h4>
                            <p style="color:var(--text-muted); font-size:0.75rem;">Aponte a câmera do celular para o QR Code abaixo para avaliar este conselho.</p>
                        </div>
                        
                        <div id="qrcode" style="background:white; padding:15px; border-radius:12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);"></div>
                        
                        <a href="<?= (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]/survey.php?c=$conselhoId&a={$conselho['avaliacao_id']}" ?>" 
                           target="_blank" style="font-size:0.875rem; color:var(--color-primary); font-weight:600; text-decoration:none;">
                            Abrir Link Manualmente ↗
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<script>
window.conselhoIsConcluido = <?= $conselhoConcluido ? 'true' : 'false' ?>;
const conselhoConcluido = window.conselhoIsConcluido;
const presentesAtuais = [<?= implode(',', $presentesAtuais) ?>];
const usuariosPorPerfil = <?= json_encode($usuariosPorPerfil) ?>;
const conselhoId = <?= $conselhoId ?>;
const currentUserId = <?= getCurrentUser()['id'] ?>;
const conselho = { turma_id: <?= $conselho['turma_id'] ?> };
let detailTabs = [];
let studentsInDetail = [];

document.addEventListener('DOMContentLoaded', function() {
    if (conselhoConcluido) {
        showTab('ata');
    }
});

function showTab(tabId) {
    const btn = document.querySelector(`#mainTabsNav [data-tab="${tabId}"]`);
    if (btn && btn.classList.contains('inactive')) {
        return;
    }
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('#mainTabsNav .tab-btn').forEach(b => b.classList.remove('active'));
    
    const contentEl = document.getElementById(tabId);
    const btnEl = document.querySelector(`#mainTabsNav [data-tab="${tabId}"]`);
    
    if (contentEl) contentEl.classList.add('active');
    if (btnEl) btnEl.classList.add('active');

    // Se for a aba de encaminhamentos, carrega a lista geral
    if (tabId === 'encaminhamentos' && typeof loadCouncilReferrals === 'function') {
        loadCouncilReferrals(conselhoId, conselhoConcluido);
    }

    // Se for a aba de Ata, carrega consolidado
    if (tabId === 'ata' && typeof loadCouncilAta === 'function') {
        loadCouncilAta(conselhoId);
    }
}

function addDetailTab(aluno) {
    const tabId = 'aluno_' + aluno.id;
    const nomeParts = aluno.nome.split(' ');
    const tabName = nomeParts[0] + (nomeParts.length > 1 ? ' ' + nomeParts[1].substring(0, 3) + '...' : '');
    
    const exists = detailTabs.find(t => t.id === tabId);
    if (!exists) {
        detailTabs.push({ id: tabId, name: tabName, aluno: aluno });
    }
    
    if (!studentsInDetail.includes(aluno.id)) {
        studentsInDetail.push(aluno.id);
        setStudentRowInactive(aluno.id, true);
    }
    
    showTab('alunos_detalhes');
    
    renderDetailTabs();
    showDetailTab(tabId);
}

function removeDetailTab(tabId) {
    const tab = detailTabs.find(t => t.id === tabId);
    const alunoId = tab ? tab.aluno.id : null;
    
    if (alunoId) {
        studentsInDetail = studentsInDetail.filter(id => id !== alunoId);
        setStudentRowInactive(alunoId, false);
    }
    
    detailTabs = detailTabs.filter(t => t.id !== tabId);
    renderDetailTabs();
    
    if (detailTabs.length > 0) {
        showDetailTab(detailTabs[detailTabs.length - 1].id);
    } else {
        document.getElementById('detail-content').innerHTML = '<div class="detail-content-empty">Clique no botão 👁️ de um aluno na aba "2. Alunos" para visualizar os detalhes.</div>';
    }
}

function setStudentRowInactive(alunoId, inactive) {
    const row = document.querySelector('tr[data-aluno-id="' + alunoId + '"]');
    if (row) {
        if (inactive) {
            row.style.opacity = '0.4';
            row.style.background = 'var(--bg-surface-2nd)';
        } else {
            row.style.opacity = '';
            row.style.background = '';
        }
    }
}

function showDetailTab(tabId) {
    const tab = detailTabs.find(t => t.id === tabId);
    if (!tab) return;
    
    document.querySelectorAll('.detail-tab-item').forEach(t => t.classList.remove('active'));
    const tabEl = document.querySelector('[data-tab-id="' + tabId + '"]');
    if (tabEl) tabEl.classList.add('active');

    const content = document.getElementById('detail-content');
    content.innerHTML = '<p style="text-align:center;color:var(--text-muted);">Carregando detalhes de ' + tab.aluno.nome + '...</p>';
    
    if (typeof Loading !== 'undefined') Loading.show();
    
    fetch('conselho_aluno_detalhes_ajax.php?aluno_id=' + tab.aluno.id + '&conselho_id=' + conselhoId)
        .then(res => res.json())
        .then(dados => {
            if (dados.error) {
                content.innerHTML = `<div style="padding:2rem;text-align:center;color:var(--color-danger);font-weight:600;">⚠️ ${dados.error}</div>`;
                return;
            }
            
            const a = dados.aluno;
            
            let html = '<div style="display:flex;align-items:center;gap:1rem;padding:1rem;background:var(--bg-surface-2nd);border-radius:var(--radius-md);margin-bottom:1.5rem;">';
            html += '<div style="width:60px;height:60px;border-radius:50%;background:var(--bg-surface);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">';
            if (a.photo) {
                html += '<img src="/' + a.photo + '" style="width:100%;height:100%;object-fit:cover;">';
            } else {
                html += '<span style="font-weight:700;font-size:1.5rem;color:var(--text-muted);">' + a.nome.charAt(0).toUpperCase() + '</span>';
            }
            html += '</div>';
            html += '<div style="flex:1;">';
            html += '<div style="font-weight:700;font-size:1.125rem;color:var(--text-primary);">' + a.nome + '</div>';
            html += '<div style="font-size:.8125rem;color:var(--text-muted);">' + (a.email || 'Sem email') + '</div>';
            html += '<div style="font-size:.8125rem;color:var(--text-muted);">' + (a.telefone || 'Sem telefone') + '</div>';
            html += '</div>';
            html += '<div style="display:flex;align-items:center;gap:1.5rem;">';
            html += '<div id="banner-trend-' + a.id + '" style="min-width:140px; transform: scale(0.9); transform-origin: right center;"></div>';
            html += '<div id="banner-perf-' + a.id + '" style="min-width:140px; transform: scale(0.85); transform-origin: right center;"></div>';
            html += '<div style="display:flex;gap:.75rem;align-items:center;">';
            
            // Botão Comentários (Azul)
            html += '<button type="button" title="Comentários" style="background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe;padding:.625rem;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;transition:all .2s ease;cursor:pointer;" onmouseover="this.style.background=\'#dbeafe\'" onmouseout="this.style.background=\'#eff6ff\'" onclick="openComentariosModal(' + a.id + ', \'' + a.nome.replace(/'/g, "\\'") + '\')">' + 
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></button>';
            
            // Botão Registros (Amarelo/Laranja)
            html += '<button type="button" title="Registros (Post-it)" style="background:#fffbeb;color:#d97706;border:1px solid #fef3c7;padding:.625rem;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;transition:all .2s ease;cursor:pointer;" onmouseover="this.style.background=\'#fef3c7\'" onmouseout="this.style.background=\'#fffbeb\'" onclick="openCouncilRecordModal(' + conselhoId + ', {id:' + a.id + ', nome:\'' + a.nome.replace(/'/g, "\\'") + '\', photo:\'' + (a.photo || '') + '\'})">' + 
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.5 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.5L15.5 3z"></path><path d="M15 3v6h6"></path><line x1="13" y1="13" x2="7" y2="13"></line><line x1="13" y1="17" x2="7" y2="17"></line><line x1="9" y1="9" x2="7" y2="9"></line></svg></button>';
            
            // Botão Encaminhamentos (Roxo/Indigo)
            html += '<button type="button" title="Encaminhamentos" style="background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe;padding:.625rem;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;transition:all .2s ease;cursor:pointer;" onmouseover="this.style.background=\'#ddd6fe\'" onmouseout="this.style.background=\'#f5f3ff\'" onclick="openReferralModal(' + a.id + ', \'' + a.nome.replace(/'/g, "\\'") + '\', ' + conselhoId + ')">' + 
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path></svg></button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            // Cabeçalho da tabela
            let theadHtml = '<thead><tr style="background:var(--bg-surface-2nd);"><th style="padding:.5rem .75rem;text-align:left;font-size:.75rem;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border-color);">Disciplina</th>';
            dados.etapas.forEach(etapa => {
                theadHtml += '<th style="padding:.5rem .75rem;text-align:center;font-size:.75rem;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border-color);">' + etapa.description + '</th>';
            });
            theadHtml += '<th style="padding:.5rem .75rem;text-align:center;font-size:.75rem;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border-color);">Soma Notas</th>';
            theadHtml += '<th style="padding:.5rem .75rem;text-align:center;font-size:.75rem;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border-color);">Soma Faltas</th>';
            theadHtml += '<th style="padding:.5rem .75rem;text-align:center;font-size:.75rem;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border-color);">Tendência</th>';
            theadHtml += '</tr></thead>';
            
            // Corpo da tabela
            let tbodyHtml = '<tbody>';
            // Calcula a soma das médias das etapas do conselho
            const somaMediaEtapas = dados.etapas.reduce((acc, e) => acc + (parseFloat(e.media_nota) || 0), 0);
            dados.disciplinas.forEach(disc => {
                let mediaStyle = disc.soma_nota < somaMediaEtapas ? 'color:#dc2626;font-weight:600;' : 'color:var(--color-success);';
                tbodyHtml += '<tr style="border-bottom:1px solid var(--border-color);">';
                tbodyHtml += '<td style="padding:.5rem .75rem;font-size:.8125rem;">' + disc.descricao + '</td>';
                
                dados.etapas.forEach(etapa => {
                    const etapaData = disc.etapas[etapa.id];
                    if (etapaData) {
                        const notaOk = etapaData.nota >= etapaData.media;
                        const cellStyle = etapaData.nota !== null 
                            ? (notaOk ? 'color:var(--color-success);' : 'color:#dc2626;font-weight:600;')
                            : 'color:var(--text-muted);';
                        tbodyHtml += '<td style="text-align:center;padding:.5rem .75rem;font-size:.8125rem;' + cellStyle + '">' + (etapaData.nota !== null ? etapaData.nota : '-') + '</td>';
                    } else {
                        tbodyHtml += '<td style="text-align:center;padding:.5rem .75rem;font-size:.8125rem;color:var(--text-muted);">-</td>';
                    }
                });
                
                tbodyHtml += '<td style="text-align:center;padding:.5rem .375rem;font-size:.8125rem;white-space:nowrap;' + mediaStyle + '">' + disc.soma_nota.toFixed(1) + '</td>';
                tbodyHtml += '<td style="text-align:center;padding:.5rem .375rem;font-size:.8125rem;white-space:nowrap;">' + disc.soma_faltas + '</td>';
                
                let trendIcon = '';
                let trendStyle = '';
                const relNotas = dados.etapas.map(e => {
                    const etapaData = disc.etapas[e.id];
                    if (etapaData && etapaData.nota !== null) {
                        return (parseFloat(etapaData.nota) / (parseFloat(e.nota_maxima) || 10)) * 10;
                    }
                    return null;
                }).filter(n => n !== null);

                if (relNotas.length >= 2) {
                    let diff = relNotas[relNotas.length - 1] - relNotas[0];
                    if (diff > 0.5) {
                        trendIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>';
                        trendStyle = 'color:#3b82f6;'; // Azul conforme solicitação
                    } else if (diff < -0.5) {
                        trendIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>';
                        trendStyle = 'color:var(--color-danger);';
                    } else {
                        trendIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>';
                        trendStyle = 'color:var(--text-muted);';
                    }
                } else if (relNotas.length === 1) {
                    trendIcon = '⏳';
                    trendStyle = 'color:var(--text-muted);';
                } else {
                    trendIcon = '—';
                    trendStyle = 'color:var(--text-muted);';
                }
                tbodyHtml += '<td style="text-align:center;padding:.5rem .375rem;font-size:.8125rem;white-space:nowrap;' + trendStyle + '">' + trendIcon + '</td>';
                tbodyHtml += '</tr>';
            });
            tbodyHtml += '</tbody>';
            
            html += '<div style="display:flex;gap:1.5rem;align-items:flex-start;">';
            
            // Container principal tabulado - 2/3
            html += '<div style="flex:2;background:var(--bg-card);border:1px solid var(--border-color);padding:1rem;border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);">';
            
            // Navegação de Sub-abas
            html += '<div class="sub-tabs-nav">';
            html += '<button class="sub-tab-btn active" onclick="switchSubTab(this, \'pane-chart-' + a.id + '\', \'' + a.id + '\')">📊 Comparativo</button>';
            html += '<button class="sub-tab-btn" onclick="switchSubTab(this, \'pane-table-' + a.id + '\', \'' + a.id + '\')">📋 Planilha de Notas</button>';
            html += '<button class="sub-tab-btn" onclick="switchSubTab(this, \'pane-boxplot-' + a.id + '\', \'' + a.id + '\')">📦 Boxplot</button>';
            html += '</div>';

            // Pane 1: Gráfico de Comparação (Ativo por padrão)
            html += '<div id="pane-chart-' + a.id + '" class="sub-tab-pane">';
            html += '<div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;">';
            html += '<span style="font-size:1.25rem;">⚖️</span>';
            html += '<div style="font-size:.875rem;font-weight:700;color:var(--text-primary);text-transform:uppercase;">Desempenho: Aluno vs Média da Turma</div>';
            html += '</div>';
            html += '<div id="perf-comparison-' + a.id + '" style="min-height:360px;width:100%;"></div>';
            html += '</div>';

            // Pane 2: Tabela de Notas (Escondido por padrão)
            html += '<div id="pane-table-' + a.id + '" class="sub-tab-pane" style="display:none;">';
            html += '<div style="overflow-x:auto;border-radius:var(--radius-md);border:1px solid var(--border-color);">';
            html += '<table style="width:100%;border-collapse:collapse;font-size:.8125rem;">' + theadHtml + tbodyHtml + '</table>';
            html += '</div>'; // Fecha overflow-x
            html += '</div>'; // Fecha pane-table
            
            // Pane 3: Boxplot (Escondido por padrão)
            html += '<div id="pane-boxplot-' + a.id + '" class="sub-tab-pane" style="display:none;">';
            html += '<div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;">';
            html += '<span style="font-size:1.25rem;">📦</span>';
            html += '<div style="font-size:.875rem;font-weight:700;color:var(--text-primary);text-transform:uppercase;">Distribuição: Dispersão de Notas (Boxplot)</div>';
            html += '</div>';
            html += '<div id="perf-boxplot-' + a.id + '" style="min-height:360px;width:100%;"></div>';
            html += '<div style="margin-top:1rem;font-size:0.75rem;color:var(--text-muted);display:flex;gap:1.5rem;justify-content:center;">';
            html += '<span><b style="color:#3b82f6">—</b> Caixa: 50% central (Q1-Q3)</span>';
            html += '<span><b>—</b> Mediana: Centro da Distribuição</span>';
            html += '<span><b style="color:var(--color-danger)">●</b> Outliers: Notas Discrepantes</span>';
            html += '</div>';
            html += '</div>';
            
            html += '</div>'; // Fecha container flex:2 (principal)
            
            // Área de gráficos - 1/3
            html += '<div style="flex:1;min-width:280px;display:flex;flex-direction:column;gap:1rem;">';
            
            // Card: Evolução por Etapa (Médias)
            html += '<div style="padding:1rem;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-md);">';
            html += '<div style="font-size:.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:1.5rem;">📊 Médias por Etapa</div>';
            html += '<div id="perf-chart-' + a.id + '" style="height:150px;width:100%;"></div>';
            html += '</div>';

            // Card 3: Desempenho por Categoria
            html += '<div style="padding:1rem;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-md);">';
            html += '<div style="font-size:.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:1.5rem;">🎯 Por Área de Conhecimento</div>';
            html += '<div id="perf-categories-' + a.id + '" style="min-height:100px;width:100%;"></div>';
            html += '</div>';
            
            html += '</div>'; // Fecha área de gráficos
            html += '</div>'; // Fecha container flex principal
            
            content.innerHTML = html;
            
            // Armazena dados no cache global para troca de abas
            VAPerformance.cache[a.id] = dados;
            
            // Renderiza as análises no banner lateral e nos cards de detalhes
            VASentiment.renderTrend('banner-trend-' + a.id, a.id, conselho.turma_id);
            VAPerformance.renderPerformanceTrend('banner-perf-' + a.id, dados.etapas, dados.disciplinas);
            VAPerformance.renderPerformanceChart('perf-chart-' + a.id, dados.etapas, dados.disciplinas);
            VAPerformance.renderCategoryChart('perf-categories-' + a.id, dados.disciplinas);
            VAPerformance.renderComparisonChart('perf-comparison-' + a.id, dados.disciplinas, dados.soma_media_aprovacao);
        })
        .catch((err) => {
            console.error('Erro ao carregar detalhes:', err);
            content.innerHTML = '<p style="text-align:center;color:var(--color-danger);padding:2rem;"><b>Erro ao carregar detalhes.</b><br><small>' + (err.message || 'Erro desconhecido') + '</small></p>';
        })
        .finally(() => {
            if (typeof Loading !== 'undefined') Loading.hide();
        });
}

// Função para alternar entre sub-abas (Gráfico/Tabela)
function switchSubTab(btn, paneId, alunoId) {
    const container = btn.closest('div').parentElement;
    const dados = VAPerformance.cache[alunoId];
    
    // Toggle botões
    container.querySelectorAll('.sub-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    // Toggle panes
    container.querySelectorAll('.sub-tab-pane').forEach(p => p.style.display = 'none');
    const pane = document.getElementById(paneId);
    if (pane) pane.style.display = 'block';

    // Se for a aba do gráfico, forçar re-render para garantir largura correta
    if (paneId.startsWith('pane-chart')) {
        setTimeout(() => {
            VAPerformance.renderComparisonChart('perf-comparison-' + alunoId, dados.disciplinas, dados.soma_media_aprovacao);
        }, 50);
    } else if (paneId.startsWith('pane-boxplot')) {
        setTimeout(() => {
            VAPerformance.renderBoxPlot('perf-boxplot-' + alunoId, dados.disciplinas, dados.distribuicao_turma);
        }, 50);
    }
}

function renderDetailTabs() {
    const container = document.getElementById('detail-tabs-list');
    container.innerHTML = '';
    
    detailTabs.forEach(tab => {
            const tabItem = document.querySelector('.detail-tab-item[data-tab-id="' + tab.id + '"]');
            const isActive = tabItem ? tabItem.classList.contains('active') : false;
        const div = document.createElement('div');
        div.className = 'detail-tab-item' + (isActive ? ' active' : '');
        div.setAttribute('data-tab-id', tab.id);
        div.onclick = () => showDetailTab(tab.id);
        div.innerHTML = '<span>' + tab.name + '</span><button class="close-btn" onclick="event.stopPropagation();removeDetailTab(\'' + tab.id + '\')">✕</button>';
        container.appendChild(div);
    });
}

function renderOutrosParticipantes() {
    const container = document.getElementById('outros_participantes');
    
    if (usuariosPorPerfil.length === 0) {
        container.innerHTML = '<p style="text-align:center;color:var(--text-muted);font-size:.875rem;">Nenhum usuário encontrado para este perfil.</p>';
        return;
    }
    
    let html = '';
    usuariosPorPerfil.forEach(u => {
        const checked = presentesAtuais.includes(u.id) ? ' checked' : '';
        const initial = u.name.charAt(0).toUpperCase();
        html += `
            <label class="presence-item">
                <input type="checkbox" name="presentes[]" value="${u.id}"${checked}>
                <div class="avatar">${initial}</div>
                <div class="info">
                    <div class="name">${u.name}</div>
                    <div class="profile">${u.profile}</div>
                </div>
            </label>
        `;
    });
    container.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($selectedProfile): ?>
    renderOutrosParticipantes();
    <?php endif; ?>
});

/**
 * Integração com o Modal de Comentários Compartilhado
 */
function openComentariosModal(alunoId, alunoNome) {
    const aluno = {
        id: alunoId,
        nome: alunoNome,
        photo: null // Opcional: buscar foto se necessário
    };
    // conselho.turma_id está disponível no escopo global através do PHP/JS parse
    openCommentModal(aluno, conselho.turma_id);
}

function closeComentariosModal() {
    closeModal('commentModal');
}


function closeComentariosModal() {
    document.getElementById('comentariosModal').classList.remove('show');
    document.body.style.overflow = '';
}

function openAlunoModal(aluno) {
    addDetailTab(aluno);
}
</script>

<script src="/assets/js/student_comments.js?v=2.0"></script>
<?php require_once __DIR__ . '/../includes/student_comment_modal.php'; ?>

<!-- Componente de Encaminhamentos -->
<?php require_once __DIR__ . '/../includes/encaminhamento_modal.php'; ?>

<!-- Componente de Registros do Conselho -->
<script src="/assets/js/conselho_registros_system.js?v=2.0"></script>
<?php require_once __DIR__ . '/../includes/conselho_registro_modal.php'; ?>

<!-- Componente de Ata do Conselho -->
<script src="/assets/js/conselho_ata_system.js?v=2.0"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Inicializa tendências qualitativas (Mini)
    document.querySelectorAll('.sentiment-trend-container').forEach(container => {
        VASentiment.renderTrend(container, container.dataset.alunoId, container.dataset.turmaId, true);
    });

    // Inicializa tendências quantitativas (Mini)
    document.querySelectorAll('.performance-trend-container').forEach(container => {
        VAPerformance.renderTrend(container, container.dataset.alunoId, container.dataset.turmaId, true);
    });

    // Restaurar aba ativa se houver na URL
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab) {
        showTab(activeTab);
    }

    // Gera QR Code se necessário
    const qrContainer = document.getElementById('qrcode');
    if (qrContainer) {
        const url = "<?= (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]/survey.php?c=$conselhoId&a={$conselho['avaliacao_id']}" ?>";
        new QRCode(qrContainer, {
            text: url,
            width: 180,
            height: 180,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    }
});

function confirmFinalizar() {
    Modal.confirm({
        title: '🏁 Finalizar Conselho',
        message: 'Tem certeza que deseja encerrar este conselho de classe? Esta ação é irreversível.',
        confirmText: 'Sim, Finalizar',
        onConfirm: () => {
            document.getElementById('formFinalizar').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
