<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

// Subclasse para debug
class DebugSomativaScheduler extends \App\Services\SomativaScheduler
{
    protected function preAllocateHardProfAll(
        array  $data,
        array  $restricoes,
        array  $hardProfAll,
        array  $profAllStIds,
        array  $datesNormais,
        array  &$alocacoes,
        array  &$ocupados,
        array  &$countDia,
        array  &$discNoDia,
        array  &$codEmData,
        array  &$avisos = []
    ): array {
        $result = parent::preAllocateHardProfAll(
            $data, $restricoes, $hardProfAll, $profAllStIds, $datesNormais,
            $alocacoes, $ocupados, $countDia, $discNoDia, $codEmData, $avisos
        );
        echo "=== preAllocateHardProfAll result ===" . PHP_EOL;
        echo "preAllocated count: " . count($result) . PHP_EOL;
        foreach ($alocacoes as $a) {
            if (str_contains($a['justificativa'] ?? '', 'Todos professor 33')) {
                echo "  PROF33 allocated: " . $a['disc_nome'] . " turma=" . $a['turma_desc'] . " " . $a['data_prova'] . "/slot" . $a['slot_config_id'] . PHP_EOL;
            }
        }
        echo PHP_EOL;
        return $result;
    }
}

// Hack: make preAllocateHardProfAll accessible (it's private)
// We use a Reflection-based subclass trick instead: let's just patch the PHP file temporarily
// Actually, let's use a different approach: check via output the state

// Patch: temporarily make the method protected in scheduler for subclassing
// Since we can't easily do that, let's use eval-based inspection

$db = getDB();

// Load the somativa data to trace manually
$stmt = $db->prepare("SELECT * FROM somativas WHERE id = 1");
$stmt->execute();
$som = $stmt->fetch(PDO::FETCH_ASSOC);

// Check restrictions for professor 33
echo "=== Restrições professor 33 ===" . PHP_EOL;
$stmt = $db->prepare("
    SELECT r.*, rt.tipo_nome
    FROM somativa_restricoes r
    LEFT JOIN somativa_restricao_tipos rt ON rt.id = r.restricao_tipo_id
    WHERE r.somativa_id = 1
    ORDER BY rt.tipo_nome, r.id
");
$stmt->execute();
$restricoes_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($restricoes_all as $r) {
    $params = json_decode($r['parametros'] ?? '{}', true);
    $profId = $params['professor_id'] ?? $params['professor_ids'] ?? null;
    if (is_array($profId)) { $profId = implode(',', $profId); }
    if ($profId && str_contains((string)$profId, '33')) {
        echo "  tipo={$r['tipo_nome']} params=" . json_encode($params) . PHP_EOL;
    }
}

// Check disciplines of professor 33
echo PHP_EOL . "=== Disciplinas do professor 33 ===" . PHP_EOL;
$stmt = $db->prepare("
    SELECT sd.id as som_disc_id, d.codigo, d.nome, st.turma_desc, st.id as turma_id,
           sd.professor_ids
    FROM somativa_disciplinas sd
    JOIN disciplinas d ON d.id = sd.disciplina_id
    JOIN somativa_turmas st ON st.id = sd.somativa_turma_id
    WHERE sd.somativa_id = 1
    AND FIND_IN_SET('33', sd.professor_ids)
    ORDER BY st.turma_desc, d.nome
");
$stmt->execute();
$discs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($discs as $d) {
    echo "  som_disc_id={$d['som_disc_id']} [{$d['codigo']}] {$d['nome']} turma={$d['turma_desc']} (turma_id={$d['turma_id']})" . PHP_EOL;
}

// Check professor 33 availability
echo PHP_EOL . "=== Disponibilidade professor 33 ===" . PHP_EOL;
$stmt = $db->prepare("
    SELECT * FROM professor_disponibilidades WHERE professor_id = 33
");
$stmt->execute();
$avails = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($avails)) {
    echo "  (nenhuma restrição de disponibilidade)" . PHP_EOL;
} else {
    foreach ($avails as $av) {
        echo "  " . json_encode($av) . PHP_EOL;
    }
}

echo PHP_EOL . "=== Slots da somativa 1 ===" . PHP_EOL;
$stmt = $db->prepare("SELECT * FROM somativa_slots_config WHERE somativa_id = 1 ORDER BY ordem");
$stmt->execute();
$slots_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($slots_all as $s) {
    echo "  id={$s['id']} label={$s['label']} ordem={$s['ordem']}" . PHP_EOL;
}
