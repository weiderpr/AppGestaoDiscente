<?php
require_once __DIR__ . '/../config/database.php';

$output = "";

try {
    $db = getDB();
    
    // 1. Get the most recent somativa
    $somativa = $db->query("SELECT * FROM somativas ORDER BY id DESC LIMIT 1")->fetch();
    if (!$somativa) {
        $output .= "No somativa found.\n";
        file_put_contents(__DIR__ . '/inspect_constraints.txt', $output);
        exit;
    }
    
    $output .= "=== CONSTRAINTS DETAILS FOR SOMATIVA {$somativa['id']} ===\n\n";
    
    // Fetch all active constraints
    $restricoes = $db->prepare("SELECT * FROM somativa_restricoes WHERE somativa_id = ? AND is_active = 1");
    $restricoes->execute([$somativa['id']]);
    $restricoesRows = $restricoes->fetchAll();
    
    foreach ($restricoesRows as $r) {
        $output .= "--- Constraint ID: {$r['id']} | Categoria: {$r['categoria']} | Tipo: {$r['tipo']} ---\n";
        $params = json_decode($r['params'], true);
        
        if ($r['categoria'] === 'mesmo_dia_horario_grupo') {
            $pares = $params['pares'] ?? [];
            $output .= "Grupo (Mesmo Dia & Horário) contains:\n";
            foreach ($pares as $p) {
                $sdId = (int)($p['somativa_disciplina_id'] ?? 0);
                
                $discInfo = $db->prepare("
                    SELECT sd.id AS sd_id, sd.disciplina_codigo, d.descricao AS disc_nome, t.description AS turma_desc
                    FROM somativa_disciplinas sd
                    JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
                    JOIN somativa_turmas st ON st.id = sd.somativa_turma_id
                    JOIN turmas t ON t.id = st.turma_id
                    WHERE sd.id = ?
                ");
                $discInfo->execute([$sdId]);
                $d = $discInfo->fetch();
                if ($d) {
                    $output .= "  - ID {$sdId}: {$d['disc_nome']} ({$d['disciplina_codigo']}) in Turma \"{$d['turma_desc']}\"\n";
                } else {
                    $output .= "  - ID {$sdId}: NOT FOUND\n";
                }
            }
        } elseif ($r['categoria'] === 'mesmo_dia_horario_diferente') {
            $codA = $params['disciplina_codigo_a'] ?? '';
            $codB = $params['disciplina_codigo_b'] ?? '';
            $regra = $params['regra'] ?? '';
            $scope = $params['scope'] ?? '';
            
            // Name of A and B
            $nameA = $db->query("SELECT descricao FROM disciplinas WHERE codigo = '$codA'")->fetchColumn() ?: $codA;
            $nameB = $db->query("SELECT descricao FROM disciplinas WHERE codigo = '$codB'")->fetchColumn() ?: $codB;
            
            $output .= "Regra: {$regra} | Scope: {$scope}\n";
            $output .= "  - Disciplina A: {$nameA} ({$codA})\n";
            $output .= "  - Disciplina B: {$nameB} ({$codB})\n";
        } else {
            $output .= "Params: " . json_encode($params) . "\n";
        }
        $output .= "\n";
    }
    
} catch (Exception $e) {
    $output .= "Error: " . $e->getMessage() . "\n";
}

file_put_contents(__DIR__ . '/inspect_constraints.txt', $output);
echo "Output written to scratch/inspect_constraints.txt\n";
