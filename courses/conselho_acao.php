<?php
/**
 * Vértice Acadêmico — Ação do Conselho de Classe
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$allowed = ['Administrador', 'Coordenador'];
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

$success = '';
$error = '';
$action = $_POST['action'] ?? '';

if ($action === 'salvar_presenca') {
    $presentes = $_POST['presentes'] ?? [];
    
    $db->prepare('DELETE FROM conselhos_presentes WHERE conselho_id = ?')->execute([$conselhoId]);
    
    foreach ($presentes as $userId) {
        $db->prepare('INSERT INTO conselhos_presentes (conselho_id, user_id) VALUES (?, ?)')->execute([$conselhoId, (int)$userId]);
    }
    
    $success = 'Lista de presença salva com sucesso!';
}

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

$profiles = ['Administrador', 'Coordenador', 'Diretor', 'Professor', 'Pedagogo', 'Assistente Social', 'Naapi', 'Outro'];
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
</style>

<div class="page-header fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <a href="/courses/conselhos.php" style="color:var(--text-muted);text-decoration:none;font-size:.875rem;">← Voltar para Conselhos</a>
        <h1 class="page-title">📋 <?= htmlspecialchars($conselho['descricao']) ?></h1>
        <p class="page-subtitle">
            <strong><?= htmlspecialchars($conselho['turma_name']) ?></strong> — <?= htmlspecialchars($conselho['course_name']) ?>
            <span style="color:var(--text-muted);"> | <?= date('d/m/Y H:i', strtotime($conselho['data_hora'])) ?></span>
        </p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success fade-in" style="margin-bottom:1.5rem;">
    ✅ <?= htmlspecialchars($success) ?>
    <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:1.1rem;">✕</button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger fade-in" style="margin-bottom:1.5rem;">
    ⚠️ <?= htmlspecialchars($error) ?>
    <button onclick="dismissAlert(this)" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:1.1rem;">✕</button>
</div>
<?php endif; ?>

<div class="tabs-nav fade-in">
    <button class="tab-btn active" onclick="showTab('presenca')">1. Lista de Presença</button>
    <button class="tab-btn" onclick="showTab('alunos')">2. Alunos</button>
    <button class="tab-btn" onclick="showTab('alunos_detalhes')">2.1 Detalhes dos Alunos</button>
    <button class="tab-btn" onclick="showTab('encaminhamentos')">3. Encaminhamentos</button>
    <button class="tab-btn" onclick="showTab('ata')">4. Ata do Conselho</button>
    <button class="tab-btn" onclick="showTab('avaliacao')">5. Avaliação do Processo</button>
</div>

<div id="presenca" class="tab-content active fade-in">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Lista de Presença</span>
            <span style="font-size:.875rem;color:var(--text-muted);"><?= count($professores) ?> professor(es) vinculado(s)</span>
        </div>
        <form method="POST">
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
                            <th style="padding:.75rem 1rem;text-align:center;font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-color); width:200px;">Tendência (Análise Quantitativa)</th>
                            <th style="padding:.75rem 1rem;text-align:center;font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-color); width:200px;">Tendência (Análise Qualitativa)</th>
                            <th style="padding:.75rem 1rem;text-align:center;font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-color);">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alunos as $aluno): ?>
                        <tr style="border-bottom:1px solid var(--border-color);">
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
                                <button type="button" class="action-btn" title="Ver Detalhes" onclick='openAlunoModal(<?= json_encode($aluno) ?>)'>👁️</button>
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
        <div class="card-body" style="text-align:center;padding:3rem;color:var(--text-muted);">
            <p style="font-size:3rem;margin-bottom:1rem;">📌</p>
            <p>Encaminhamentos em desenvolvimento.</p>
        </div>
    </div>
</div>

<div id="ata" class="tab-content fade-in">
    <div class="card">
        <div class="card-body" style="text-align:center;padding:3rem;color:var(--text-muted);">
            <p style="font-size:3rem;margin-bottom:1rem;">📝</p>
            <p>Ata do Conselho em desenvolvimento.</p>
        </div>
    </div>
</div>

<div id="avaliacao" class="tab-content fade-in">
    <div class="card">
        <div class="card-body" style="text-align:center;padding:3rem;color:var(--text-muted);">
            <p style="font-size:3rem;margin-bottom:1rem;">✅</p>
            <p>Avaliação do Processo em desenvolvimento.</p>
        </div>
    </div>
</div>

<script>
const presentesAtuais = [<?= implode(',', $presentesAtuais) ?>];
const usuariosPorPerfil = <?= json_encode($usuariosPorPerfil) ?>;
const conselhoId = <?= $conselhoId ?>;
const conselho = { turma_id: <?= $conselho['turma_id'] ?> };
let detailTabs = [];

function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    
    document.getElementById(tabId).classList.add('active');
    document.querySelector(`[onclick="showTab('${tabId}')"]`).classList.add('active');
}

function addDetailTab(aluno) {
    const tabId = 'aluno_' + aluno.id;
    const nomeParts = aluno.nome.split(' ');
    const tabName = nomeParts[0] + (nomeParts.length > 1 ? ' ' + nomeParts[1].substring(0, 3) + '...' : '');
    
    const exists = detailTabs.find(t => t.id === tabId);
    if (!exists) {
        detailTabs.push({ id: tabId, name: tabName, aluno: aluno });
    }
    
    showTab('alunos_detalhes');
    
    renderDetailTabs();
    showDetailTab(tabId);
}

function removeDetailTab(tabId) {
    detailTabs = detailTabs.filter(t => t.id !== tabId);
    renderDetailTabs();
    
    if (detailTabs.length > 0) {
        showDetailTab(detailTabs[detailTabs.length - 1].id);
    } else {
        document.getElementById('detail-content').innerHTML = '<div class="detail-content-empty">Clique no botão 👁️ de um aluno na aba "2. Alunos" para visualizar os detalhes.</div>';
    }
}

function showDetailTab(tabId) {
    const tab = detailTabs.find(t => t.id === tabId);
    if (!tab) return;
    
    document.querySelectorAll('.detail-tab-item').forEach(t => t.classList.remove('active'));
    document.querySelector(`[data-tab-id="${tabId}"]`)?.classList.add('active');
    
    const content = document.getElementById('detail-content');
    content.innerHTML = '<p style="text-align:center;color:var(--text-muted);">Carregando detalhes de ' + tab.aluno.nome + '...</p>';
    
    fetch('/courses/conselho_aluno_detalhes_ajax.php?aluno_id=' + tab.aluno.id + '&conselho_id=' + conselhoId)
        .then(res => res.json())
        .then(dados => {
            if (dados.error) {
                content.innerHTML = '<p style="text-align:center;color:var(--color-danger);">' + dados.error + '</p>';
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
            html += '<div style="display:flex;gap:.5rem;">';
            html += '<button type="button" class="action-btn" title="Comentários" onclick="openComentariosModal(' + tab.aluno.id + ', \'' + tab.aluno.nome.replace(/'/g, "\\'") + '\')">💬</button>';
            html += '<button type="button" class="action-btn" title="Encaminhamentos">📌</button>';
            html += '<button type="button" class="action-btn" title="Histórico">📜</button>';
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
                
                tbodyHtml += '<td style="text-align:center;padding:.5rem .75rem;font-size:.8125rem;' + mediaStyle + '">' + disc.soma_nota.toFixed(1) + '</td>';
                tbodyHtml += '<td style="text-align:center;padding:.5rem .75rem;font-size:.8125rem;">' + disc.soma_faltas + '</td>';
                tbodyHtml += '</tr>';
            });
            tbodyHtml += '</tbody>';
            
            html += '<div style="display:flex;gap:1.5rem;align-items:flex-start;">';
            
            // Tabela de notas - 2/3
            html += '<div style="flex:2;overflow-x:auto;border-radius:var(--radius-md);border:1px solid var(--border-color);">';
            html += '<table style="width:100%;border-collapse:collapse;font-size:.8125rem;">' + theadHtml + tbodyHtml + '</table>';
            html += '</div>';
            
            // Área de gráficos - 1/3
            html += '<div style="flex:1;min-width:280px;display:flex;flex-direction:column;gap:1rem;">';
            
            // Card 1: Tendência Geral
            html += '<div style="padding:1rem;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-md);">';
            html += '<div style="font-size:.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:1rem;">📈 Tendência de Evolução</div>';
            html += '<div id="perf-trend-' + a.id + '"></div>';
            html += '</div>';

            // Card 2: Evolução por Etapa
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
            
            // Renderiza as análises
            VASentiment.renderTrend('banner-trend-' + a.id, a.id, conselho.turma_id);
            VAPerformance.renderPerformanceTrend('perf-trend-' + a.id, dados.etapas, dados.disciplinas);
            VAPerformance.renderPerformanceChart('perf-chart-' + a.id, dados.etapas, dados.disciplinas);
            VAPerformance.renderCategoryChart('perf-categories-' + a.id, dados.disciplinas);
        })
        .catch(() => {
            content.innerHTML = '<p style="text-align:center;color:var(--color-danger);">Erro ao carregar detalhes.</p>';
        });
}

function renderDetailTabs() {
    const container = document.getElementById('detail-tabs-list');
    container.innerHTML = '';
    
    detailTabs.forEach(tab => {
        const isActive = document.querySelector('.detail-tab-item[data-tab-id="' + tab.id + '"]')?.classList.contains('active');
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

<script src="/assets/js/student_comments.js?v=1.1"></script>
<?php require_once __DIR__ . '/../includes/student_comment_modal.php'; ?>




<script>
document.addEventListener('DOMContentLoaded', () => {
    // Inicializa tendências na lista de conselho
    // Inicializa tendências qualitativas
    document.querySelectorAll('.sentiment-trend-container').forEach(container => {
        VASentiment.renderTrend(container, container.dataset.alunoId, container.dataset.turmaId);
    });

    // Inicializa tendências quantitativas
    document.querySelectorAll('.performance-trend-container').forEach(container => {
        VAPerformance.renderTrend(container, container.dataset.alunoId, container.dataset.turmaId);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
