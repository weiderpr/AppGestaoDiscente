<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SomativaScheduler.php';

// Monkey-patch: subclasse com debug para professor 33
class DebugScheduler extends \App\Services\SomativaScheduler
{
    public array $debugLog = [];

    // Redefine greedyAllocate via reflection trick
    // Na prática: vamos checar o resultado final e inferir
}

$scheduler = new \App\Services\SomativaScheduler();

// Usa reflection para acessar método privado greedyAllocate
$ref = new ReflectionClass($scheduler);

// Roda normalmente e analisa alocações
$plano = $scheduler->run(1, 1);

// Filtra alocações/conflitos do professor 33 e das turmas relacionadas
echo "=== DIAGNÓSTICO PROFESSOR 33 (Matemática) ===" . PHP_EOL . PHP_EOL;

$mat_allocs = array_filter($plano['alocacoes'], fn($a) =>
    str_contains($a['disc_professor_ids'] ?? '', '33') ||
    in_array($a['disciplina_codigo'], ['8FG.171', '8FG.172'])
);
$mat_conflicts = array_filter($plano['conflitos'], fn($c) =>
    in_array($c['disc_cod'] ?? $c['disciplina_codigo'] ?? '', ['8FG.171', '8FG.172']) ||
    str_contains($c['disc_nome'] ?? '', 'MATEMÁTICA') ||
    str_contains(implode('|', array_values($c)), 'professor 33') ||
    str_contains(implode('|', array_values($c)), '8FG.17')
);

echo "Alocações com prof 33 ou disc 8FG.17x:" . PHP_EOL;
foreach ($mat_allocs as $a) {
    printf("  [%s] %s (%s) -> %s/slot%d  just=%s%s",
        $a['disciplina_codigo'],
        $a['disc_nome'],
        $a['turma_desc'],
        $a['data_prova'],
        $a['slot_config_id'],
        $a['justificativa'] ?? '-',
        PHP_EOL
    );
}

echo PHP_EOL . "Conflitos com disc 8FG.17x ou Matemática ou prof33:" . PHP_EOL;
foreach ($mat_conflicts as $c) {
    printf("  %s (%s): %s%s",
        $c['disc_nome'],
        $c['turma_desc'],
        $c['motivo'],
        PHP_EOL
    );
}

// Mostra TODAS as alocações ordenadas por turma + data + slot
echo PHP_EOL . "=== TODAS AS ALOCAÇÕES (ordenadas por turma/data/slot) ===" . PHP_EOL;
$allocs = $plano['alocacoes'];
usort($allocs, fn($a, $b) => strcmp(
    $a['turma_desc'] . $a['data_prova'] . sprintf('%02d', $a['slot_config_id']),
    $b['turma_desc'] . $b['data_prova'] . sprintf('%02d', $b['slot_config_id'])
));
foreach ($allocs as $a) {
    printf("  [turma=%-15s] %s slot%d  %s  (prof=%s) just=%s%s",
        $a['turma_desc'],
        $a['data_prova'],
        $a['slot_config_id'],
        str_pad($a['disc_nome'] ?? '', 25),
        $a['disc_professor_ids'] ?? '-',
        $a['justificativa'] ?? '-',
        PHP_EOL
    );
}

echo PHP_EOL . "Total alocações: " . count($plano['alocacoes']) . PHP_EOL;
echo "Total conflitos: " . count($plano['conflitos']) . PHP_EOL;
