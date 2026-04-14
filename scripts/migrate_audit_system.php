// Mock server for CLI
if (php_sapi_name() === 'cli') {
    $_SERVER['REQUEST_METHOD'] = 'GET';
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();

try {
    // 1. Criar tabela audit_logs
    $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        institution_id INT NULL,
        action VARCHAR(50) NOT NULL,
        table_name VARCHAR(100) NOT NULL,
        record_id INT NOT NULL,
        old_values JSON DEFAULT NULL,
        new_values JSON DEFAULT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_inst (institution_id),
        INDEX idx_table (table_name),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sql);
    echo "Tabela audit_logs criada/verificada.\n";

    // 2. Inserir permissão base
    $institutions = $db->query("SELECT id FROM institutions")->fetchAll(PDO::FETCH_COLUMN);
    $profiles = ['Coordenador', 'Diretor', 'Professor', 'Pedagogo', 'Assistente Social', 'Naapi', 'Psicólogo', 'Outro'];
    $resources = ['audit.view_logs', 'settings.audit_logs'];

    $db->beginTransaction();
    foreach ($institutions as $instId) {
        foreach ($profiles as $profile) {
            foreach ($resources as $resource) {
                // Verifica se já existe
                $stCheck = $db->prepare("SELECT id FROM profile_permissions WHERE profile = ? AND resource = ? AND instituicao_id = ?");
                $stCheck->execute([$profile, $resource, $instId]);
                
                if (!$stCheck->fetch()) {
                    $canAccess = ($profile === 'Diretor') ? 1 : 0;
                    $stIns = $db->prepare("INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id) VALUES (?, ?, ?, ?)");
                    $stIns->execute([$profile, $resource, $canAccess, $instId]);
                }
            }
        }
    }
    $db->commit();
    echo "Permissões de auditoria semeadas com sucesso.\n";

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Erro na migração: " . $e->getMessage() . "\n");
}
