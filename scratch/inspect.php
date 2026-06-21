<?php
require_once __DIR__ . '/../config/database.php';

$output = "";

try {
    $db = getDB();
    
    // 1. Get the most recent somativa
    $somativa = $db->query("SELECT * FROM somativas ORDER BY id DESC LIMIT 1")->fetch();
    if (!$somativa) {
        $output .= "No somativa found in the database.\n";
        file_put_contents(__DIR__ . '/inspect_output.txt', $output);
        exit;
    }
    
    $output .= "=== ACTIVE SOMATIVA ===\n";
    $output .= "ID: " . $somativa['id'] . "\n";
    $output .= "Nome: " . $somativa['nome'] . "\n";
    $output .= "Data Inicio: " . $somativa['data_inicio'] . "\n";
    $output .= "Data Fim: " . $somativa['data_fim'] . "\n";
    $output .= "Max Provas/Dia: " . $somativa['max_provas_por_dia'] . "\n";
    $output .= "Segunda Chamada Data: " . ($somativa['segunda_chamada_data'] ?? 'None') . "\n";
    $output .= "Evitar Conflito Professor: " . $somativa['evitar_conflito_professor'] . "\n";
    $output .= "\n";
    
    // 2. Get constraints (restricoes) for this somativa
    $restricoes = $db->prepare("SELECT * FROM somativa_restricoes WHERE somativa_id = ?");
    $restricoes->execute([$somativa['id']]);
    $restricoesRows = $restricoes->fetchAll();
    
    $output .= "=== CONSTRAINTS (RESTRICOES) ===\n";
    foreach ($restricoesRows as $r) {
        $output .= "ID: {$r['id']} | Tipo: {$r['tipo']} | Categoria: {$r['categoria']} | Peso: {$r['peso']} | Ativo: {$r['is_active']} | Desc: {$r['descricao']}\n";
        $output .= "   Params: " . $r['params'] . "\n";
    }
    $output .= "\n";
    
    // 3. Get Turmas and Disciplinas
    $turmas = $db->prepare("
        SELECT st.id AS somativa_turma_id, st.turma_id, t.description AS turma_desc
        FROM somativa_turmas st
        JOIN turmas t ON t.id = st.turma_id
        WHERE st.somativa_id = ?
    ");
    $turmas->execute([$somativa['id']]);
    $turmasRows = $turmas->fetchAll();
    
    $output .= "=== TURMAS E DISCIPLINAS ===\n";
    foreach ($turmasRows as $t) {
        $output .= "Turma: {$t['turma_desc']} (som_turma_id: {$t['somativa_turma_id']})\n";
        
        $discs = $db->prepare("
            SELECT sd.id AS som_disc_id, sd.disciplina_codigo, sd.professor_aplicador, d.descricao AS disc_nome,
                   (SELECT GROUP_CONCAT(u.name SEPARATOR ', ')
                    FROM turma_disciplinas td
                    JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
                    JOIN users u ON u.id = tdp.professor_id
                    WHERE td.turma_id = ? AND td.disciplina_codigo = sd.disciplina_codigo) AS professores,
                   (SELECT GROUP_CONCAT(u.id SEPARATOR ',')
                    FROM turma_disciplinas td
                    JOIN turma_disciplina_professores tdp ON tdp.turma_disciplina_id = td.id
                    JOIN users u ON u.id = tdp.professor_id
                    WHERE td.turma_id = ? AND td.disciplina_codigo = sd.disciplina_codigo) AS professor_ids
            FROM somativa_disciplinas sd
            JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
            WHERE sd.somativa_turma_id = ?
        ");
        $discs->execute([$t['turma_id'], $t['turma_id'], $t['somativa_turma_id']]);
        $discsRows = $discs->fetchAll();
        
        foreach ($discsRows as $d) {
            $output .= "  - {$d['disc_nome']} ({$d['disciplina_codigo']}) | Profs: {$d['professores']} (IDs: {$d['professor_ids']}) | Aplicador Obrigatório: {$d['professor_aplicador']}\n";
        }
    }
    $output .= "\n";
    
    // 4. Get Current grade slots alocados
    $grade = $db->prepare("
        SELECT sg.*, d.descricao AS disc_nome, t.description AS turma_desc, sc.label AS slot_label
        FROM somativa_grade sg
        JOIN somativa_turmas st ON st.id = sg.somativa_turma_id
        JOIN turmas t ON t.id = st.turma_id
        LEFT JOIN somativa_disciplinas sd ON sd.id = sg.somativa_disciplina_id
        LEFT JOIN disciplinas d ON d.codigo = sd.disciplina_codigo
        JOIN somativa_slots_config sc ON sc.id = sg.slot_config_id
        WHERE sg.somativa_id = ?
        ORDER BY t.description, sg.data_prova, sc.ordem
    ");
    $grade->execute([$somativa['id']]);
    $gradeRows = $grade->fetchAll();
    
    $output .= "=== CURRENT GRADE ===\n";
    foreach ($gradeRows as $g) {
        $output .= "Turma: {$g['turma_desc']} | Data: {$g['data_prova']} | Slot: {$g['slot_label']} | Disciplina: {$g['disc_nome']} ({$g['tipo']})\n";
    }

} catch (Exception $e) {
    $output .= "Error: " . $e->getMessage() . "\n";
}

file_put_contents(__DIR__ . '/inspect_output.txt', $output);
echo "Output written to scratch/inspect_output.txt\n";
