<?php
/**
 * Vértice Acadêmico — Lançamento de Notas e Frequência
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user    = getCurrentUser();
$allowed = ['Administrador', 'Coordenador', 'Professor'];
if (!$user || !in_array($user['profile'], $allowed)) {
    header('Location: /dashboard.php');
    exit;
}

$db     = getDB();
$inst   = getCurrentInstitution();
$instId = $inst['id'];

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/courses/index.php'));
    exit;
}

$etapaId = (int)($_GET['etapa_id'] ?? 0);
if (!$etapaId) { header('Location: /courses/index.php'); exit; }

// Buscar a etapa, turma e curso
$stmt = $db->prepare('
    SELECT e.*, t.description as turma_desc, t.course_id, c.name as course_name 
    FROM etapas e
    INNER JOIN turmas t ON t.id = e.turma_id
    INNER JOIN courses c ON c.id = t.course_id
    WHERE e.id = ? AND c.institution_id = ? 
    LIMIT 1
');
$stmt->execute([$etapaId, $instId]);
$etapa = $stmt->fetch();
if (!$etapa) { header('Location: /courses/index.php'); exit; }

// Segurança: Coordenador só no seu curso
if ($user['profile'] === 'Coordenador') {
    $stCheck = $db->prepare('SELECT 1 FROM course_coordinators WHERE course_id=? AND user_id=? LIMIT 1');
    $stCheck->execute([$etapa['course_id'], $user['id']]);
    if (!$stCheck->fetch()) {
        header('Location: /courses/index.php');
        exit;
    }
}

// Segurança: Professor só nas turmas/disciplinas que leciona
if ($user['profile'] === 'Professor') {
    $stCheck = $db->prepare('
        SELECT 1 FROM turma_disciplinas td
        JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id
        WHERE td.turma_id = ? AND tdp.professor_id = ? LIMIT 1
    ');
    $stCheck->execute([$etapa['turma_id'], $user['id']]);
    if (!$stCheck->fetch()) {
        header('Location: /courses/index.php');
        exit;
    }
}

// Buscar disciplinas da turma
$sqlDisc = '
    SELECT d.codigo as disciplina_codigo, d.descricao 
    FROM turma_disciplinas td
    INNER JOIN disciplinas d ON d.codigo = td.disciplina_codigo
    WHERE td.turma_id = ?
';
$paramsDisc = [$etapa['turma_id']];

// Se for professor, filtrar apenas suas disciplinas
if ($user['profile'] === 'Professor') {
    $sqlDisc .= ' AND td.id IN (
        SELECT tdp.turma_disciplina_id FROM turma_disciplina_professores tdp 
        WHERE tdp.professor_id = ?
    )';
    $paramsDisc[] = $user['id'];
}
$sqlDisc .= ' ORDER BY d.descricao ASC';

$stDisc = $db->prepare($sqlDisc);
$stDisc->execute($paramsDisc);
$disciplinas = $stDisc->fetchAll();

$disciplinaCodigo = trim($_GET['disciplina_codigo'] ?? ($_POST['disciplina_codigo'] ?? ''));
// Se não tiver chegado, pega a primeira disponível
if ($disciplinaCodigo === '' && count($disciplinas) > 0) {
    $disciplinaCodigo = $disciplinas[0]['disciplina_codigo'];
}

$success = '';
$error   = '';

// Processar formulário de salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $disciplinaCodigo !== '') {
    $notas = $_POST['notas'] ?? [];
    $faltas = $_POST['faltas'] ?? [];
    $hasError = false;

    $db->beginTransaction();
    try {
        $stUpsert = $db->prepare('
            INSERT INTO etapa_notas (etapa_id, aluno_id, disciplina_codigo, nota, faltas) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE nota = VALUES(nota), faltas = VALUES(faltas)
        ');

        foreach ($notas as $alunoId => $notaVal) {
            $faltasVal = $faltas[$alunoId] ?? 0;
            
            // Validar nota
            if ($notaVal === '') {
                $notaVal = null;
            } else {
                $notaVal = (float) str_replace(',', '.', $notaVal);
                if ($notaVal < 0 || $notaVal > $etapa['nota_maxima']) {
                    $hasError = true;
                    $error = "Uma ou mais notas informadas são inválidas ou excedem a nota máxima da etapa (" . number_format($etapa['nota_maxima'], 2, ',', '.') . ").";
                    break;
                }
            }
            
            // Validar faltas
            $faltasVal = (int) $faltasVal;
            if ($faltasVal < 0) {
                $hasError = true;
                $error = 'O número de faltas não pode ser negativo.';
                break;
            }

            $stUpsert->execute([$etapaId, $alunoId, $disciplinaCodigo, $notaVal, $faltasVal]);
        }
        
        if (!$hasError) {
            $db->commit();
            $success = 'Notas e faltas salvas com sucesso!';
        } else {
            $db->rollBack();
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Erro ao salvar os dados: ' . $e->getMessage();
    }
}

// Buscar alunos da turma com as respectivas notas
$stAlunos = $db->prepare('
    SELECT a.id, a.matricula, a.nome, a.photo, en.nota, en.faltas
    FROM turma_alunos ta
    INNER JOIN alunos a ON a.id = ta.aluno_id
    LEFT JOIN etapa_notas en ON en.aluno_id = a.id AND en.etapa_id = ? AND en.disciplina_codigo = ?
    WHERE ta.turma_id = ?
    ORDER BY a.nome ASC
');
if ($disciplinaCodigo !== '') {
    $stAlunos->execute([$etapaId, $disciplinaCodigo, $etapa['turma_id']]);
    $alunos = $stAlunos->fetchAll();
} else {
    $alunos = [];
}

$pageTitle = 'Lançamento: ' . $etapa['description'];
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.notas-table-wrap { overflow-x:auto; border-radius:var(--radius-lg); margin-top: 1rem; }
.notas-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.notas-table th {
    padding:.75rem 1rem; text-align:left; font-size:.75rem; font-weight:600;
    text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted);
    background:var(--bg-surface-2nd); border-bottom:1px solid var(--border-color);
    white-space:nowrap;
}
.notas-table td { padding:.875rem 1rem; border-bottom:1px solid var(--border-color); vertical-align:middle; }
.notas-table tr:hover td { background:var(--bg-hover); }
.input-nota { width: 90px; text-align: right; }
.input-freq { width: 80px; text-align: right; }
.input-nota.nota-abaixo { border-color: var(--color-danger); color: var(--color-danger); font-weight: 600; }
.input-nota.nota-acima { border-color: var(--color-primary); color: var(--color-primary); font-weight: 600; }

/* Subject Selector Style */
.subject-selector-card { margin-top: 1.5rem; overflow: hidden; }
.subject-selector-header { padding: 0.75rem 1.25rem; background: var(--bg-surface-2nd); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 0.5rem; }
.subject-selector-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
.subject-tabs-container { display: flex; overflow-x: auto; background: var(--bg-surface); scrollbar-width: none; -ms-overflow-style: none; }
.subject-tabs-container::-webkit-scrollbar { display: none; }
.subject-tab {
    display: flex; align-items: center; gap: 0.625rem; padding: 1rem 1.5rem;
    color: var(--text-secondary); font-weight: 600; text-decoration: none;
    border-bottom: 3px solid transparent; transition: all var(--transition-fast);
    white-space: nowrap; font-size: 0.875rem;
}
.subject-tab:hover { background: var(--bg-hover); color: var(--color-primary); }
.subject-tab.active { color: var(--color-primary); border-bottom-color: var(--color-primary); background: var(--color-primary-light); }
.subject-icon { font-size: 1.1rem; opacity: 0.7; }
.subject-tab.active .subject-icon { opacity: 1; }
</style>

<div class="page-header fade-in">
    <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.25rem;">
        <a href="/courses/index.php" style="color:var(--color-primary);text-decoration:none;">📚 Cursos</a>
        &nbsp;›&nbsp;
        <a href="/courses/turmas.php?course_id=<?= $etapa['course_id'] ?>" style="color:var(--color-primary);text-decoration:none;"><?= htmlspecialchars($etapa['course_name']) ?></a>
        &nbsp;›&nbsp;
        <a href="/courses/etapas.php?turma_id=<?= $etapa['turma_id'] ?>" style="color:var(--color-primary);text-decoration:none;">Turma: <?= htmlspecialchars($etapa['turma_desc']) ?></a>
        &nbsp;›&nbsp; <?= htmlspecialchars($etapa['description']) ?>
    </div>
    <h1 class="page-title">📊 Lançamento de Notas e Faltas</h1>
    <p class="page-subtitle">
        Etapa: <strong><?= htmlspecialchars($etapa['description']) ?></strong>
        &nbsp;·&nbsp; Nota Máxima: <strong><?= number_format($etapa['nota_maxima'], 2, ',', '.') ?></strong>
    </p>

    <!-- Seletor de Disciplina -->
    <?php if (count($disciplinas) > 0): ?>
    <div class="card subject-selector-card fade-in">
        <div class="subject-selector-header">
            <span class="subject-icon">📖</span>
            <span class="subject-selector-label">Disciplinas da Turma</span>
        </div>
        <div class="subject-tabs-container">
            <?php foreach ($disciplinas as $d): 
                $isActive = ($d['disciplina_codigo'] === $disciplinaCodigo);
            ?>
                <a href="?etapa_id=<?= $etapaId ?>&disciplina_codigo=<?= urlencode($d['disciplina_codigo']) ?>" 
                   class="subject-tab <?= $isActive ? 'active' : '' ?>">
                    <span><?= htmlspecialchars($d['descricao']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-danger" style="margin-top: 1.5rem; margin-bottom: 0;">
        ⚠️ Esta turma não possui disciplinas vinculadas. Por favor, acesse o painel da turma e vincule as disciplinas para lançar notas.
    </div>
    <?php endif; ?>
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

<?php if ($disciplinaCodigo !== '' && count($disciplinas) > 0): ?>
<form method="POST" class="fade-in">
    <input type="hidden" name="disciplina_codigo" value="<?= htmlspecialchars($disciplinaCodigo) ?>">
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <span><strong style="color:var(--color-primary);"><?= count($alunos) ?></strong> aluno(s) enturmado(s)</span>
            <div style="display:flex;gap:.75rem;">
                <a href="/courses/etapas.php?turma_id=<?= $etapa['turma_id'] ?>" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">💾 Salvar Lançamentos</button>
            </div>
        </div>
        
        <div class="notas-table-wrap">
            <table class="notas-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">Foto</th>
                        <th style="width: 120px;">Matrícula</th>
                        <th>Nome do Aluno</th>
                        <th style="width: 150px; text-align: right;">Nota</th>
                        <th style="width: 150px; text-align: right;">Faltas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alunos)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
                        Não há alunos enturmados nesta turma. Registre alunos primeiro.
                    </td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($alunos as $a): 
                        $notaVal = $a['nota'] !== null ? number_format((float)$a['nota'], 2, '.', '') : '';
                        $faltasVal = $a['faltas'] !== null ? (int)$a['faltas'] : 0;
                        $colorClass = '';
                        if ($a['nota'] !== null) {
                            $colorClass = ((float)$a['nota'] < (float)$etapa['media_nota']) ? 'nota-abaixo' : 'nota-acima';
                        }
                    ?>
                    <tr>
                        <td style="text-align:center;">
                            <?php if (!empty($a['photo']) && file_exists(__DIR__ . '/../' . $a['photo'])): ?>
                                <img src="/<?= htmlspecialchars($a['photo']) ?>" style="width:36px; height:36px; border-radius:50%; object-fit:cover; background:var(--bg-surface-2nd);">
                            <?php else: ?>
                                <div style="margin: 0 auto; width:36px; height:36px; border-radius:50%; background:var(--bg-surface-2nd); display:flex; align-items:center; justify-content:center; font-weight:600; font-size:.75rem; color:var(--text-muted); border: 1px solid var(--border-color);">
                                    <?= htmlspecialchars(mb_strtoupper(mb_substr(trim($a['nome']), 0, 2))) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-family:monospace; color:var(--text-muted);"><?= htmlspecialchars($a['matricula']) ?></td>
                        <td><strong><?= htmlspecialchars($a['nome']) ?></strong></td>
                        <td style="text-align: right;">
                            <input type="number" name="notas[<?= $a['id'] ?>]" class="form-control input-nota <?= $colorClass ?>" 
                                   step="0.01" min="0" max="<?= $etapa['nota_maxima'] ?>" 
                                   value="<?= $notaVal ?>" placeholder="—">
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: .5rem;">
                                <input type="number" name="faltas[<?= $a['id'] ?>]" class="form-control input-freq" 
                                       step="1" min="0" value="<?= $faltasVal ?>" required>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (!empty($alunos)): ?>
        <div class="card-body" style="background:var(--bg-surface-2nd); border-top:1px solid var(--border-color); display:flex; justify-content:flex-end; gap:.75rem; padding: 1rem 1.5rem;">
            <a href="/courses/etapas.php?turma_id=<?= $etapa['turma_id'] ?>" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">💾 Salvar Lançamentos</button>
        </div>
        <?php endif; ?>
    </div>
</form>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mediaEtapa = <?= (float)$etapa['media_nota'] ?>;
    const inputs = document.querySelectorAll('.input-nota');
    
    function updateColor(input) {
        const val = parseFloat(input.value);
        input.classList.remove('nota-abaixo', 'nota-acima');
        if (!isNaN(val)) {
            if (val < mediaEtapa) {
                input.classList.add('nota-abaixo');
            } else {
                input.classList.add('nota-acima');
            }
        }
    }
    
    inputs.forEach(input => {
        input.addEventListener('input', function() { updateColor(this); });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
