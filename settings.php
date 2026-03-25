<?php
/**
 * Vértice Acadêmico — Configurações do Sistema
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
requireLogin();

$user = getCurrentUser();
if (!$user || !in_array($user['profile'], ['Administrador', 'Coordenador'])) {
    header('Location: /dashboard.php');
    exit;
}

// Proteção: Somente Administrador acessa Backup
$requestedSection = $_GET['section'] ?? 'backup';
if ($requestedSection === 'backup' && $user['profile'] !== 'Administrador') {
    header('Location: /settings.php?section=avaliacoes');
    exit;
}

$db = getDB();

// Mensagens de feedback
$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';

// Processar backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token de segurança expirado. Tente novamente.';
        header('Location: /settings.php');
        exit;
    }
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="backup_vertice_academico_' . date('Y-m-d_H-i-s') . '.sql"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = "-- =======================================================\n";
    $output .= "-- Vértice Acadêmico — Backup Completo do Banco de Dados\n";
    $output .= "-- Gerado em: " . date('d/m/Y H:i:s') . "\n";
    $output .= "-- Gerado por: " . $user['name'] . " (" . $user['email'] . ")\n";
    $output .= "-- =======================================================\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $output .= "SET AUTOCOMMIT = 0;\n";
    $output .= "START TRANSACTION;\n\n";
    
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $createTable = $db->query("SHOW CREATE TABLE `$table`")->fetch();
        $output .= "-- --------------------------------------------------------\n";
        $output .= "-- Estrutura da tabela `$table`\n";
        $output .= "-- --------------------------------------------------------\n\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $output .= $createTable['Create Table'] . ";\n\n";
    }
    
    $output .= "-- =======================================================\n";
    $output .= "-- Dados das Tabelas\n";
    $output .= "-- =======================================================\n\n";
    
    $totalRecords = 0;
    foreach ($tables as $table) {
        $output .= "-- --------------------------------------------------------\n";
        $output .= "-- Dados da tabela `$table`\n";
        $output .= "-- --------------------------------------------------------\n\n";
        
        $rows = $db->query("SELECT * FROM `$table`");
        
        if ($rows->rowCount() > 0) {
            $columns = array_keys($rows->fetch(PDO::FETCH_ASSOC));
            $rows = $db->query("SELECT * FROM `$table`");
            
            foreach ($rows as $row) {
                $totalRecords++;
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $value = str_replace(["\\", "'", "\r", "\n"], ["\\\\", "\\'", "\\r", "\\n"], $value);
                        $values[] = "'" . $value . "'";
                    }
                }
                $cols = '`' . implode('`, `', $columns) . '`';
                $vals = implode(', ', $values);
                $output .= "INSERT INTO `$table` ($cols) VALUES ($vals);\n";
            }
            $output .= "\n";
        }
    }
    
    $output .= "-- =======================================================\n";
    $output .= "-- Total de registros: $totalRecords\n";
    $output .= "-- Fim do Backup\n";
    $output .= "-- =======================================================\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $output .= "COMMIT;\n";
    
    echo $output;
    exit;
}

// Processar restore
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança expirado. Tente novamente.';
    } elseif (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Erro ao carregar o arquivo. Tente novamente.';
    } else {
        $reason = trim($_POST['restore_reason'] ?? '');
        
        if (empty($reason)) {
            $error = 'Informe o motivo da restauração.';
        } else {
            $file = $_FILES['backup_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if ($ext !== 'sql') {
                $error = 'Arquivo inválido. Selecione um arquivo .sql';
            } elseif ($file['size'] > 50 * 1024 * 1024) {
                $error = 'Arquivo muito grande. O limite é 50MB.';
            } else {
                $sql = file_get_contents($file['tmp_name']);
                if ($sql === false) {
                    $error = 'Não foi possível ler o arquivo.';
                } else {
                    try {
                        // Contar INSERTs no arquivo para registro
                        preg_match_all('/INSERT INTO `/i', $sql, $matches);
                        $recordsCount = count($matches[0]);
                        
                        $db->exec($sql);
                        
                        // Garantir que a transacao do arquivo foi comitada e restaurar o autocommit
                        $db->exec('COMMIT; SET AUTOCOMMIT = 1;');
                        
                        // Registrar no log
                        $stLog = $db->prepare('
                            INSERT INTO restore_logs (user_id, reason, file_name, file_size, records_count, status)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ');
                        $stLog->execute([
                            $user['id'],
                            $reason,
                            $file['name'],
                            $file['size'],
                            $recordsCount,
                            'success'
                        ]);
                        
                        $success = 'Backup restaurado com sucesso! O banco de dados foi atualizado.';
                    } catch (PDOException $e) {
                        try {
                            $db->exec('ROLLBACK; SET AUTOCOMMIT = 1;');
                        } catch (Exception $ex) {
                            // Ignora erro caso rollback falhe
                        }
                        
                        // Registrar erro no log
                        $stLog = $db->prepare('
                            INSERT INTO restore_logs (user_id, reason, file_name, file_size, status, error_message)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ');
                        $stLog->execute([
                            $user['id'],
                            $reason,
                            $file['name'],
                            $file['size'],
                            'error',
                            substr($e->getMessage(), 0, 500)
                        ]);
                        
                        $error = 'Erro ao restaurar backup: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Listar logs de restauração
$restoreLogs = $db->query('
    SELECT rl.*, u.name as user_name, u.email as user_email
    FROM restore_logs rl
    JOIN users u ON u.id = rl.user_id
    ORDER BY rl.restore_date DESC
    LIMIT 20
')->fetchAll();

// --- AÇÕES: AVALIAÇÕES (TIPOS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add_tipo', 'edit_tipo', 'delete_tipo'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança expirado. Tente novamente.';
    } else {
        $action = $_POST['action'];
        $id     = (int)($_POST['id'] ?? 0);
        $nome   = trim($_POST['nome'] ?? '');
        $desc   = trim($_POST['descricao'] ?? '');

        try {
            if ($action === 'add_tipo' && $nome) {
                $db->prepare("INSERT INTO tipos_avaliacao (nome, descricao) VALUES (?, ?)")->execute([$nome, $desc]);
                $success = 'Tipo de avaliação cadastrado!';
            } elseif ($action === 'edit_tipo' && $id && $nome) {
                $db->prepare("UPDATE tipos_avaliacao SET nome=?, descricao=? WHERE id=?")->execute([$nome, $desc, $id]);
                $success = 'Tipo de avaliação atualizado!';
            } elseif ($action === 'delete_tipo' && $id) {
                $db->prepare("UPDATE tipos_avaliacao SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
                $success = 'Tipo de avaliação removido!';
            }
        } catch (PDOException $e) {
            $error = 'Erro no banco: ' . $e->getMessage();
        }
    }
}

// --- AÇÕES: AVALIAÇÕES (LISTA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_avaliacao') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança expirado.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                $db->prepare("UPDATE avaliacoes SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
                $success = 'Avaliação removida com sucesso!';
            } catch (PDOException $e) {
                $error = 'Erro ao remover: ' . $e->getMessage();
            }
        }
    }
}

// Seção e sub-seção ativas
$activeSection = $_GET['section'] ?? 'backup';
if (!in_array($activeSection, ['backup', 'avaliacoes'])) $activeSection = 'backup';

$activeSub = $_GET['sub'] ?? 'backup';
$allowedSubs = [
    'backup'     => ['backup', 'restore', 'logs'],
    'avaliacoes' => ['dashboard', 'tipos', 'lista', 'create']
];
if (!in_array($activeSub, $allowedSubs[$activeSection] ?? [])) {
    $activeSub = $allowedSubs[$activeSection][0];
}

$pageTitle = 'Configurações';
$extraCSS  = ['/assets/css/components/sidebar.css'];
$extraJS   = ['/assets/js/components/Sidebar.js'];
require_once __DIR__ . '/includes/header.php';
?>

<style>
.settings-tabs {
    display: flex;
    gap: .25rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 0;
}
.settings-tab {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .75rem 1.25rem;
    font-size: .875rem;
    font-weight: 500;
    color: var(--text-secondary);
    text-decoration: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all var(--transition-fast);
    cursor: pointer;
    background: none;
    border-left: none;
    border-right: none;
    border-top: none;
}
.settings-tab:hover {
    color: var(--text-primary);
    background: var(--bg-hover);
}
.settings-tab.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
}
.settings-section { display: none; }
.settings-section.active { display: block; }
.settings-card { margin-bottom: 1.5rem; }
.settings-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: .75rem;
}
.settings-card-icon {
    width: 40px; height: 40px; border-radius: var(--radius-md);
    background: var(--color-primary-light); color: var(--color-primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem;
}
.settings-card-icon.warning {
    background: rgba(245,158,11,.12); color: var(--color-warning);
}
.settings-card-icon.danger {
    background: rgba(239,68,68,.12); color: var(--color-danger);
}
.settings-card-icon.success {
    background: rgba(16,185,129,.12); color: var(--color-success);
}
.settings-card-title {
    font-size: 1rem; font-weight: 600; color: var(--text-primary);
}
.settings-card-desc {
    font-size: .8125rem; color: var(--text-muted); margin-top: .125rem;
}
.restore-dropzone {
    border: 2px dashed var(--border-color);
    border-radius: var(--radius-lg);
    padding: 2.5rem 2rem;
    text-align: center;
    transition: all var(--transition-fast);
    cursor: pointer;
    background: var(--bg-surface-2nd);
}
.restore-dropzone:hover, .restore-dropzone.dragover {
    border-color: var(--color-primary);
    background: var(--color-primary-light);
}
.restore-dropzone input[type="file"] { display: none; }
.restore-dropzone-icon { font-size: 2.5rem; margin-bottom: .75rem; }
.restore-dropzone-text { font-weight: 500; color: var(--text-primary); margin-bottom: .25rem; }
.restore-dropzone-hint { font-size: .8125rem; color: var(--text-muted); }

.logs-table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
.logs-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.logs-table th {
    padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted);
    background: var(--bg-surface-2nd); border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.logs-table td { padding: .875rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: top; }
.logs-table tr:last-child td { border-bottom: none; }
.logs-table tr:hover td { background: var(--bg-hover); }
</style>

<div class="page-header fade-in">
    <div>
        <h1 class="page-title">⚙️ Configurações do Sistema</h1>
        <p class="page-subtitle">Gerencie as configurações e ferramentas do sistema.</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success): ?>
    showSuccess(<?= json_encode($success) ?>);
    <?php endif; ?>
    <?php if ($error): ?>
    showError(<?= json_encode($error) ?>);
    <?php endif; ?>
});
</script>

<!-- Shell: sidebar + conteúdo -->
<div class="settings-shell fade-in">

<?php require_once __DIR__ . '/includes/settings_sidebar.php'; ?>

<!-- Área de conteúdo -->
<div class="settings-content">

<!-- ===== SEÇÃO: BACKUP ===== -->
<div class="settings-section <?= $activeSection === 'backup' ? 'active' : '' ?>">

    <!-- SUB: Gerar Backup -->
    <?php if ($activeSub === 'backup'): ?>
    <div class="card settings-card">
        <div class="settings-card-header">
            <div class="settings-card-icon">💾</div>
            <div>
                <div class="settings-card-title">Backup do Banco de Dados</div>
                <div class="settings-card-desc">Exportar um arquivo SQL com toda a estrutura e dados do banco.</div>
            </div>
        </div>
        <div class="card-body">
            <p style="color:var(--text-secondary);margin:0 0 1.5rem;font-size:.9375rem;line-height:1.6;">
                Esta ferramenta gera um arquivo SQL completo contendo todas as tabelas, estrutura e dados do banco de dados
                <strong>vertice_academico</strong>. O arquivo pode ser usado para restaurar o banco de dados ou transferi-lo
                para outro servidor.
            </p>

            <div style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;margin-bottom:1.5rem;">
                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
                    <span style="font-size:1rem;">📋</span>
                    <span style="font-weight:600;font-size:.875rem;">O backup inclui:</span>
                </div>
                <ul style="margin:0;padding-left:1.75rem;font-size:.8125rem;color:var(--text-secondary);line-height:1.8;list-style:disc;">
                    <li>Estrutura de todas as tabelas (CREATE TABLE)</li>
                    <li>Dados de todas as tabelas (INSERT INTO)</li>
                    <li>Índices e chaves estrangeiras</li>
                    <li>Usuários e instituições cadastrados</li>
                    <li>Cursos, turmas e disciplinas</li>
                    <li>Discentes e representantes</li>
                </ul>
            </div>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="backup">
                <button type="submit" class="btn btn-primary">
                    💾 Gerar Backup SQL
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- SUB: Restaurar -->
    <?php if ($activeSub === 'restore'): ?>
    <div class="card settings-card">
        <div class="settings-card-header">
            <div class="settings-card-icon warning">⚠️</div>
            <div>
                <div class="settings-card-title">Restaurar Backup</div>
                <div class="settings-card-desc">Restaurar o banco de dados a partir de um arquivo SQL.</div>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-warning" style="margin-bottom:1.5rem;">
                ⚠️ <strong>Atenção:</strong> A restauração substituirá todos os dados atuais.
                Certifique-se de ter feito um backup antes de continuar.
            </div>

            <form method="POST" enctype="multipart/form-data" id="restoreForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="restore">

                <div class="restore-dropzone" id="dropzone" onclick="document.getElementById('backup_file').click()">
                    <input type="file" id="backup_file" name="backup_file" accept=".sql" required>
                    <div class="restore-dropzone-icon">📁</div>
                    <div class="restore-dropzone-text">Clique para selecionar ou arraste o arquivo aqui</div>
                    <div class="restore-dropzone-hint">Arquivo .sql (máximo 50MB)</div>
                    <div id="fileName" style="margin-top:.75rem;font-weight:500;color:var(--color-primary);display:none;"></div>
                </div>

                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label">Motivo da Restauração <span class="required">*</span></label>
                    <textarea name="restore_reason" id="restore_reason" class="form-control" rows="3"
                              placeholder="Descreva o motivo desta restauração (ex: Correção de dados, migração de servidor, etc.)"
                              required style="resize:vertical;"></textarea>
                    <small style="color:var(--text-muted);">Este motivo será registrado no log de auditoria.</small>
                </div>

                <div style="margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary" id="btnRestore" disabled>
                        🔄 Restaurar Backup
                    </button>
                    <span style="font-size:.8125rem;color:var(--text-muted);margin-left:.5rem;" id="restoreHint">
                        Selecione um arquivo e informe o motivo
                    </span>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- SUB: Logs de Restauração -->
    <?php if ($activeSub === 'logs'): ?>
    <div class="card settings-card">
        <div class="settings-card-header">
            <div class="settings-card-icon">📜</div>
            <div>
                <div class="settings-card-title">Histórico de Restaurações</div>
                <div class="settings-card-desc">Registro de todas as restaurações realizadas no sistema.</div>
            </div>
        </div>
        <?php if (empty($restoreLogs)): ?>
        <div class="card-body" style="text-align:center;padding:3rem;color:var(--text-muted);">
            <div style="font-size:2.5rem;margin-bottom:1rem;">📜</div>
            <div style="font-size:1rem;font-weight:600;">Nenhuma restauração registrada</div>
        </div>
        <?php else: ?>
        <div class="logs-table-wrap">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Motivo</th>
                        <th>Arquivo</th>
                        <th>Registros</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($restoreLogs as $log): ?>
                    <tr>
                        <td style="white-space:nowrap;color:var(--text-muted);">
                            <?= date('d/m/Y H:i', strtotime($log['restore_date'])) ?>
                        </td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($log['user_name']) ?></div>
                            <div style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($log['user_email']) ?></div>
                        </td>
                        <td style="max-width:250px;">
                            <div style="overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;" title="<?= htmlspecialchars($log['reason']) ?>">
                                <?= htmlspecialchars($log['reason']) ?>
                            </div>
                        </td>
                        <td style="font-size:.8125rem;">
                            <?= htmlspecialchars($log['file_name'] ?? '—') ?>
                            <?php if ($log['file_size']): ?>
                            <div style="color:var(--text-muted);font-size:.75rem;">
                                <?= number_format($log['file_size'] / 1024, 1) ?> KB
                            </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;font-size:.875rem;">
                            <?= $log['records_count'] > 0 ? number_format($log['records_count']) : '—' ?>
                        </td>
                        <td>
                            <?php if ($log['status'] === 'success'): ?>
                            <span style="display:inline-flex;align-items:center;gap:.25rem;font-size:.8125rem;font-weight:600;color:var(--color-success);">
                                ✅ Sucesso
                            </span>
                            <?php else: ?>
                            <span style="display:inline-flex;align-items:center;gap:.25rem;font-size:.8125rem;font-weight:600;color:var(--color-danger);"
                                  title="<?= htmlspecialchars($log['error_message'] ?? '') ?>">
                                ❌ Erro
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /settings-section backup -->

<!-- ===== SEÇÃO: AVALIAÇÕES ===== -->
<div class="settings-section <?= $activeSection === 'avaliacoes' ? 'active' : '' ?>">
    <?php 
    switch($activeSub) {
        case 'tipos':
            include __DIR__ . '/includes/settings/av_tipos.php';
            break;
        case 'lista':
            include __DIR__ . '/includes/settings/av_lista.php';
            break;
        case 'create':
            include __DIR__ . '/includes/settings/av_form.php';
            break;
        default:
            include __DIR__ . '/includes/settings/av_dashboard.php';
            break;
    }
    ?>
</div><!-- /settings-section avaliacoes -->

</div><!-- /settings-content -->
</div><!-- /settings-shell -->


<script>
// Dropzone para restore
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('backup_file');
const fileName = document.getElementById('fileName');
const btnRestore = document.getElementById('btnRestore');
const restoreHint = document.getElementById('restoreHint');
const restoreReason = document.getElementById('restore_reason');

function checkRestoreReady() {
    if (fileInput.files.length > 0 && restoreReason.value.trim().length > 0) {
        btnRestore.disabled = false;
        restoreHint.textContent = 'Pronto para restaurar';
    } else {
        btnRestore.disabled = true;
        restoreHint.textContent = fileInput.files.length > 0 ? 'Informe o motivo da restauração' : 'Selecione um arquivo e informe o motivo';
    }
}

fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
        fileName.textContent = '📄 ' + this.files[0].name;
        fileName.style.display = 'block';
        checkRestoreReady();
    }
});

restoreReason.addEventListener('input', checkRestoreReady);

dropzone.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('dragover');
});
dropzone.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
});
dropzone.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
    if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        fileInput.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
