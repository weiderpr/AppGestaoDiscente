<?php
/**
 * Vértice Acadêmico — Configurações do Sistema
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
if (!$user || $user['profile'] !== 'Administrador') {
    header('Location: /dashboard.php');
    exit;
}

$db = getDB();

// Processar backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
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
    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
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

// Aba ativa
$activeTab = $_GET['tab'] ?? 'backup';

$pageTitle = 'Configurações';
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

<!-- Abas de Configuração -->
<div class="settings-tabs fade-in">
    <button class="settings-tab <?= $activeTab === 'backup' ? 'active' : '' ?>" onclick="location.href='?tab=backup'">
        💾 Backup
    </button>
    <button class="settings-tab <?= $activeTab === 'restore' ? 'active' : '' ?>" onclick="location.href='?tab=restore'">
        📂 Restaurar
    </button>
    <button class="settings-tab <?= $activeTab === 'logs' ? 'active' : '' ?>" onclick="location.href='?tab=logs'">
        📜 Logs de Restauração
    </button>
    <button class="settings-tab <?= $activeTab === 'info' ? 'active' : '' ?>" onclick="location.href='?tab=info'">
        ℹ️ Informações
    </button>
</div>

<!-- Seção: Backup -->
<div class="settings-section <?= $activeTab === 'backup' ? 'active' : '' ?>">
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
                <input type="hidden" name="action" value="backup">
                <button type="submit" class="btn btn-primary">
                    💾 Gerar Backup SQL
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Seção: Restaurar -->
<div class="settings-section <?= $activeTab === 'restore' ? 'active' : '' ?>">
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
</div>

<!-- Seção: Logs de Restauração -->
<div class="settings-section <?= $activeTab === 'logs' ? 'active' : '' ?>">
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
</div>

<!-- Seção: Informações -->
<div class="settings-section <?= $activeTab === 'info' ? 'active' : '' ?>">
    <div class="card settings-card">
        <div class="settings-card-header">
            <div class="settings-card-icon">ℹ️</div>
            <div>
                <div class="settings-card-title">Informações do Sistema</div>
                <div class="settings-card-desc">Dados técnicos sobre a instalação.</div>
            </div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
                <?php
                $dbVersion = $db->query("SELECT VERSION() as v")->fetch()['v'];
                $tablesCount = $db->query("SELECT COUNT(*) as c FROM information_schema.tables WHERE table_schema = 'vertice_academico'")->fetch()['c'];
                $usersCount = $db->query("SELECT COUNT(*) as c FROM users")->fetch()['c'];
                $institutionsCount = $db->query("SELECT COUNT(*) as c FROM institutions")->fetch()['c'];
                ?>
                <div style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                    <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem;">Banco de Dados</div>
                    <div style="font-weight:600;">vertice_academico</div>
                </div>
                <div style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                    <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem;">Versão MySQL</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($dbVersion) ?></div>
                </div>
                <div style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                    <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem;">Tabelas</div>
                    <div style="font-weight:600;"><?= $tablesCount ?> tabelas</div>
                </div>
                <div style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                    <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem;">Versão PHP</div>
                    <div style="font-weight:600;"><?= PHP_VERSION ?></div>
                </div>
                <div style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                    <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem;">Usuários</div>
                    <div style="font-weight:600;"><?= $usersCount ?> usuários</div>
                </div>
                <div style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                    <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem;">Instituições</div>
                    <div style="font-weight:600;"><?= $institutionsCount ?> instituições</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card settings-card">
        <div class="settings-card-header">
            <div class="settings-card-icon">🛠️</div>
            <div>
                <div class="settings-card-title">Configurações do PHP</div>
                <div class="settings-card-desc">Limites e configurações do servidor.</div>
            </div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
                <div style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                    <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem;">Upload Máximo</div>
                    <div style="font-weight:600;"><?= ini_get('upload_max_filesize') ?></div>
                </div>
                <div style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                    <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem;">Post Máximo</div>
                    <div style="font-weight:600;"><?= ini_get('post_max_size') ?></div>
                </div>
                <div style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                    <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem;">Tempo Execução</div>
                    <div style="font-weight:600;"><?= ini_get('max_execution_time') ?>s</div>
                </div>
                <div style="background:var(--bg-surface-2nd);border-radius:var(--radius-md);padding:1rem;">
                    <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem;">Memória</div>
                    <div style="font-weight:600;"><?= ini_get('memory_limit') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

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
