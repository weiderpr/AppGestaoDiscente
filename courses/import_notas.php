<?php
/**
 * Vértice Acadêmico — Importação de Notas via CSV
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$allowed = ['Administrador', 'Coordenador'];
if (!$user || !in_array($user['profile'], $allowed)) {
    header('Location: /dashboard.php');
    exit;
}

$db      = getDB();
$inst    = getCurrentInstitution();
$instId  = $inst['id'];

if (!$instId) {
    header('Location: /select_institution.php?redirect=' . urlencode('/courses/index.php'));
    exit;
}

$courseId = (int)($_REQUEST['course_id'] ?? 0);
if (!$courseId) {
    header('Location: /courses/index.php');
    exit;
}

// Verifica permissão no curso
$hasPerm = false;
if ($user['profile'] === 'Administrador') {
    $stCheck = $db->prepare('SELECT name FROM courses WHERE id=? AND institution_id=? LIMIT 1');
    $stCheck->execute([$courseId, $instId]);
    $course = $stCheck->fetch();
    if ($course) $hasPerm = true;
} else {
    $stCheck = $db->prepare('
        SELECT c.name 
        FROM courses c
        INNER JOIN course_coordinators cc ON cc.course_id = c.id
        WHERE c.id = ? AND c.institution_id = ? AND cc.user_id = ?
        LIMIT 1
    ');
    $stCheck->execute([$courseId, $instId, $user['id']]);
    $course = $stCheck->fetch();
    if ($course) $hasPerm = true;
}

if (!$hasPerm) {
    header('Location: /courses/index.php');
    exit;
}

$success = '';
$error   = '';
$importedCount = 0;
$skippedCount  = 0;
$errorDetails  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['import_file']['tmp_name'])) {
    $file = $_FILES['import_file']['tmp_name'];
    $handle = fopen($file, "r");
    
    // Detectar delimitador
    $headerLine = fgets($handle);
    rewind($handle);
    $delimiter = (strpos($headerLine, ';') !== false) ? ';' : ',';
    
    $db->beginTransaction();
    try {
        // Cache de etapas e disciplinas para performance simples
        $stEnv = $db->prepare('
            SELECT a.id as aluno_id, t.id as turma_id, e.id as etapa_id, e.nota_maxima
            FROM turmas t
            INNER JOIN etapas e ON e.turma_id = t.id
            INNER JOIN turma_alunos ta ON ta.turma_id = t.id
            INNER JOIN alunos a ON a.id = ta.aluno_id
            WHERE t.course_id = ? 
              AND a.matricula = ? 
              AND e.description = ?
            LIMIT 1
        ');
        
        // Busca disciplina por código OU por descrição/nome
        $stDisc = $db->prepare('
            SELECT d.codigo 
            FROM turma_disciplinas td
            INNER JOIN disciplinas d ON d.codigo = td.disciplina_codigo
            WHERE td.turma_id = ? 
              AND (d.codigo = ? OR d.descricao = ?)
            LIMIT 1
        ');
        
        $stUpsert = $db->prepare('
            INSERT INTO etapa_notas (etapa_id, aluno_id, disciplina_codigo, nota, faltas)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                nota = VALUES(nota), 
                faltas = VALUES(faltas),
                updated_at = CURRENT_TIMESTAMP
        ');

        $rowNum = 0;
        while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            $rowNum++;
            // Ignora cabeçalho
            if ($rowNum === 1 || empty($data[0])) continue;
            if (str_contains(strtolower($data[0]), 'etapa') || str_contains(strtolower($data[1]), 'matri')) continue;

            $etapaName = trim($data[0] ?? '');
            $matricula = trim($data[1] ?? '');
            $discInput = trim($data[2] ?? '');
            $notaVal   = trim($data[3] ?? '');
            $faltasVal = trim($data[4] ?? '');

            if (!$etapaName || !$matricula || !$discInput) {
                $skippedCount++;
                continue;
            }

            // 1. Localizar Contexto (Aluno + Turma + Etapa)
            $stEnv->execute([$courseId, $matricula, $etapaName]);
            $ctx = $stEnv->fetch();
            
            if (!$ctx) {
                $skippedCount++;
                $errorDetails[] = "Linha {$rowNum}: Aluno ({$matricula}) ou Etapa ({$etapaName}) n&atilde;o encontrados neste curso.";
                continue;
            }

            // 2. Localizar Disciplina na Turma
            $stDisc->execute([$ctx['turma_id'], $discInput, $discInput]);
            $disc = $stDisc->fetch();
            
            if (!$disc) {
                $skippedCount++;
                $errorDetails[] = "Linha {$rowNum}: Disciplina ({$discInput}) n&atilde;o vinculada &agrave; turma do aluno.";
                continue;
            }

            // 3. Sanitizar Valores
            $nota = null;
            if ($notaVal !== '') {
                $nota = (float)str_replace(',', '.', $notaVal);
                $notaMax = (float)$ctx['nota_maxima'];
                if ($nota < 0) $nota = 0;
                if ($nota > $notaMax) $nota = $notaMax;
            }
            $faltas = (int)$faltasVal;
            if ($faltas < 0) $faltas = 0;

            // 4. Upsert
            $stUpsert->execute([
                $ctx['etapa_id'], 
                $ctx['aluno_id'], 
                $disc['codigo'], 
                $nota, 
                $faltas
            ]);
            $importedCount++;
        }

        $db->commit();
        $success = "Processamento conclu&iacute;do! {$importedCount} registros importados/atualizados.";
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erro no processamento: " . $e->getMessage();
    }
    fclose($handle);
}

$pageTitle = 'Importar Notas — ' . $course['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div>
        <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.25rem;">
            <a href="/courses/index.php" style="color:var(--color-primary);">📚 Cursos</a>
            &nbsp;›&nbsp; <?= htmlspecialchars($course['name']) ?>
        </div>
        <h1 class="page-title">📊 Importação de Notas</h1>
        <p class="page-subtitle">Processamento de arquivo CSV para o curso selecionado.</p>
    </div>
    <a href="/courses/index.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success fade-in">✅ <?= $success ?></div>
    <?php if ($skippedCount > 0): ?>
        <div class="card fade-in" style="margin-top:1.5rem; border-color:var(--color-warning);">
            <div class="card-header" style="background:var(--color-warning-light); color:var(--color-warning-dark);">
                ⚠️ <?= $skippedCount ?> linha(s) foram ignoradas por inconsistência
            </div>
            <div class="card-body" style="max-height:300px; overflow-y:auto; font-size:.8125rem;">
                <ul style="padding-left:1.5rem; margin:0; line-height:1.6;">
                    <?php foreach (array_slice($errorDetails, 0, 50) as $err): ?>
                        <li><?= $err ?></li>
                    <?php endforeach; ?>
                    <?php if (count($errorDetails) > 50): ?><li>... e mais <?= count($errorDetails)-50 ?> erros.</li><?php endif; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger fade-in">⚠️ <?= $error ?></div>
<?php endif; ?>

<?php if (!$success): ?>
<div class="card fade-in" style="max-width:600px; margin:0 auto;">
    <div class="card-body" style="padding:2.5rem;">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            
            <div style="padding:1.25rem; border-radius:var(--radius-lg); background:var(--bg-surface-2nd); border:1px dashed var(--border-color); margin-bottom:2rem;">
                <p style="font-size:0.9375rem; font-weight:700; margin-bottom:0.75rem; color:var(--text-primary); display:flex; align-items:center; gap:0.5rem;">
                    <span>📝</span> Instruções do Arquivo CSV
                </p>
                <ul style="font-size:0.875rem; color:var(--text-muted); padding-left:1.25rem; display:grid; gap:0.5rem;">
                    <li>Separador: <strong>Vírgula (,)</strong> ou <strong>Ponto-e-vírgula (;)</strong>.</li>
                    <li>O cabeçalho é ignorado automaticamente.</li>
                    <li><strong>Ordem das Colunas:</strong>
                        <ol style="margin-top:0.5rem; color:var(--text-primary); font-weight:500;">
                            <li>Etapa (Ex: "1º Bimestre")</li>
                            <li>Matrícula do Aluno</li>
                            <li>Disciplina (Código ou Nome Exato)</li>
                            <li>Nota (Usar ponto ou vírgula)</li>
                            <li>Faltas (Número inteiro)</li>
                        </ol>
                    </li>
                </ul>
            </div>

            <div class="form-group">
                <label class="form-label">Selecione o arquivo CSV <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-icon">📄</span>
                    <input type="file" name="import_file" class="form-control" accept=".csv" required style="padding-left:2.75rem;">
                </div>
            </div>

            <div style="margin-top:2rem;">
                <button type="submit" class="btn btn-primary" style="width:100%; height:48px; font-size:1rem;">
                    🚀 Iniciar Processamento
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
