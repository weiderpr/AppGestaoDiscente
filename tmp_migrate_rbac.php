<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

try {
    $db->exec('ALTER TABLE profile_permissions DROP INDEX unique_profile_resource');
} catch (Exception $e) {
    echo "Index unique_profile_resource already dropped or doesn't exist.\n";
}
try {
    $db->exec('ALTER TABLE profile_permissions DROP INDEX profile'); // Sometimes called profile or resource alone? Wait, I will just ignore errors.
} catch (Exception $e) {}

// Fetch existing rules (temporarily let's just use whatever lacks an institution but wait, we already added instituicao_id, so they have NULL now)
$st = $db->query('SELECT profile, resource, can_access FROM profile_permissions WHERE instituicao_id IS NULL');
$rules = $st->fetchAll();
echo "Found " . count($rules) . " template rules.\n";

if(count($rules) > 0) {
    // Fetch all institutions
    $stInst = $db->query('SELECT id FROM institutions');
    $institutions = $stInst->fetchAll(PDO::FETCH_COLUMN);

    if (count($institutions) == 0) {
        $institutions = [1];
    }

    $inserted = 0;
    foreach ($institutions as $instId) {
        foreach ($rules as $rule) {
            try {
                $stInsert = $db->prepare('INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id) VALUES (?, ?, ?, ?)');
                $stInsert->execute([$rule['profile'], $rule['resource'], $rule['can_access'], $instId]);
                $inserted++;
            } catch (Exception $e) {
                // Ignore duplicates if any
            }
        }
    }
    echo "Inserted $inserted rules across " . count($institutions) . " institutions.\n";

    // Delete old NULL rules
    $db->exec('DELETE FROM profile_permissions WHERE instituicao_id IS NULL');
    echo "Deleted old template rules.\n";
}

// Ensure uniqueness
$db->exec('
    DELETE a FROM profile_permissions a
    INNER JOIN profile_permissions b 
    WHERE a.id > b.id 
    AND a.profile = b.profile 
    AND a.resource = b.resource 
    AND a.instituicao_id = b.instituicao_id
');

$db->exec('ALTER TABLE profile_permissions MODIFY COLUMN instituicao_id INT UNSIGNED NOT NULL');

try {
    $db->exec('ALTER TABLE profile_permissions ADD CONSTRAINT UNIQUE idx_profile_resource_inst (profile, resource, instituicao_id)');
} catch (Exception $e) {
    echo "Unique index error: " . $e->getMessage() . "\n";
}

try {
    $db->exec('ALTER TABLE profile_permissions ADD CONSTRAINT fk_profile_perm_inst FOREIGN KEY (instituicao_id) REFERENCES institutions(id) ON DELETE CASCADE');
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate foreign key') === false) {
        echo "FK error: " . $e->getMessage() . "\n";
    }
}

echo "Database migration complete.\n";
