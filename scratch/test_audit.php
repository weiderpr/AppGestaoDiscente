<?php
// Mock server for CLI
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Traits/Auditable.php';
require_once __DIR__ . '/../src/App/Services/Service.php';

// Mock a child service
class TestService extends \App\Services\Service {
    public function testAudit() {
        $this->audit('UPDATE', 'users', 1, 
            ['name' => 'Old Name', 'password' => 'secret123'], 
            ['name' => 'New Name', 'password' => 'newsecret']
        );
        return "Audit log inserted.";
    }
}

$service = new TestService();
echo $service->testAudit() . "\n";

$db = getDB();
$log = $db->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 1")->fetch();

echo "Log ID: " . $log['id'] . "\n";
echo "Action: " . $log['action'] . "\n";
echo "Old Values: " . $log['old_values'] . "\n";
echo "New Values: " . $log['new_values'] . "\n";

if (strpos($log['old_values'], '[PROTECTED]') !== false) {
    echo "SUCCESS: Sensitive data is protected.\n";
} else {
    echo "FAILURE: Sensitive data is visible!\n";
}
