<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

echo "=== FINDING DUPLICATES ===\n";
$stmt = $db->query("
    SELECT somativa_turma_id, somativa_disciplina_id, count(*) as count
    FROM somativa_grade 
    WHERE somativa_disciplina_id IS NOT NULL
    GROUP BY somativa_turma_id, somativa_disciplina_id 
    HAVING count(*) > 1
");
$dups = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($dups)) {
    echo "No duplicates found.\n";
    exit;
}

foreach ($dups as $dup) {
    $tId = (int)$dup['somativa_turma_id'];
    $sdId = (int)$dup['somativa_disciplina_id'];
    
    // Find all IDs for this duplicate
    $stmt2 = $db->prepare("SELECT id FROM somativa_grade WHERE somativa_turma_id = ? AND somativa_disciplina_id = ? ORDER BY id DESC");
    $stmt2->execute([$tId, $sdId]);
    $ids = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    
    // Keep the first (highest/most recent ID), delete the rest
    $keepId = array_shift($ids);
    
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $deleteStmt = $db->prepare("DELETE FROM somativa_grade WHERE id IN ($placeholders)");
        $deleteStmt->execute($ids);
        echo "For Turma $tId and Subject ID $sdId: Kept ID $keepId, deleted ID(s) " . implode(', ', $ids) . "\n";
    }
}
echo "=== CLEANUP COMPLETED ===\n";
