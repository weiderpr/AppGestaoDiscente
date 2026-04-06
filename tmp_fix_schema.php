<?php
$file = 'sql/schema.sql';
$content = file_get_contents($file);

$startMarker = 'INSERT INTO profile_permissions';
$startPos = strpos($content, $startMarker);
$endPos = strpos($content, ';', $startPos);

if ($startPos === false || $endPos === false) {
    echo "Block not found.\n";
    exit;
}

// Fixed rows (manual to be safe)
$profiles = ['Administrador', 'Coordenador', 'Diretor', 'Professor', 'Pedagogo', 'Assistente Social', 'Naapi', 'Psicólogo', 'Outro'];

// New resource rows
$viewAllRows = [
    ['Administrador', 1], ['Pedagogo', 1], ['Assistente Social', 1], ['Psicólogo', 1],
    ['Coordenador', 0], ['Diretor', 0], ['Professor', 0], ['Naapi', 0], ['Outro', 0]
];

// Read existing values to preserve them if possible
$block = substr($content, $startPos, $endPos - $startPos + 1);
preg_match_all("/\('(.*?)', '(.*?)', (\d+), (\d+)\)/", $block, $matches, PREG_SET_ORDER);

$newRows = [];
$seen = [];
foreach ($matches as $m) {
    $rowKey = $m[1] . '|' . $m[2] . '|' . $m[4];
    if (!isset($seen[$rowKey])) {
        $newRows[] = "('{$m[1]}', '{$m[2]}', {$m[3]}, {$m[4]})";
        $seen[$rowKey] = true;
    }
}

// Add view_all specifically if not present
foreach ($viewAllRows as $p) {
    if (!isset($seen["{$p[0]}|courses.view_all|1"])) {
        $newRows[] = "('{$p[0]}', 'courses.view_all', {$p[1]}, 1)";
    }
}

$newBlock = "INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id) VALUES\n" . implode(",\n", $newRows) . "\nON DUPLICATE KEY UPDATE can_access = VALUES(can_access);";

$newContent = substr_replace($content, $newBlock, $startPos, $endPos - $startPos + 1);
file_put_contents($file, $newContent);
echo "Schema fixed!\n";
