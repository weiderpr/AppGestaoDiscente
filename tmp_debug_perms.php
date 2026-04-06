<?php
require_once __DIR__ . '/includes/auth.php';

$user = getCurrentUser();
$inst = getCurrentInstitution();

echo "User Profile: " . ($user['profile'] ?? 'N/A') . "\n";
echo "Current Institution ID: " . ($inst['id'] ?? 'NULL') . "\n";

$resource = 'users.index';
require_once __DIR__ . '/src/App/Services/Service.php';
require_once __DIR__ . '/src/App/Services/PermissionService.php';
$ps = new \App\Services\PermissionService();
$has = $ps->canAccess($user['profile'], $resource);

echo "canAccess('$resource'): " . ($has ? 'TRUE' : 'FALSE') . "\n";

// Check DB directly
$db = getDB();
$st = $db->prepare("SELECT * FROM profile_permissions WHERE profile = ? AND resource = ? AND instituicao_id = ?");
$st->execute([$user['profile'], $resource, $inst['id']]);
$row = $st->fetch();
echo "Direct DB check: " . ($row ? "Found (can_access=" . $row['can_access'] . ")" : "Not found") . "\n";

// List all permissions for this profile and institution
echo "\nAll permissions for this profile and institution:\n";
$st = $db->prepare("SELECT resource, can_access FROM profile_permissions WHERE profile = ? AND instituicao_id = ?");
$st->execute([$user['profile'], $inst['id']]);
while ($r = $st->fetch()) {
    echo "- " . $r['resource'] . ": " . $r['can_access'] . "\n";
}
