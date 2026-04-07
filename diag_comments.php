<?php
/**
 * Diagnóstico da API de Comentários
 * Simula as mesmas condições da API e retorna o que ela deveria retornar.
 */
require_once __DIR__ . '/config/database.php';
$db = getDB();

$alunoId = 59; // Aluno de teste AIZAC
$turmaId = 1;  // Turma de teste

echo "--- Diagnóstico para Aluno $alunoId na Turma $turmaId ---\n\n";

// 1. Simular Busca de Outros Comentários
$professorId = 2; // Simular ID do Weider (Admin)
$st = $db->prepare('
    SELECT cp.id, cp.conteudo, cp.created_at, cp.updated_at,
           u.name as professor_nome, u.photo as professor_photo
    FROM comentarios_professores cp
    JOIN users u ON u.id = cp.professor_id
    WHERE cp.aluno_id = ? AND cp.turma_id = ? AND cp.professor_id != ?
    ORDER BY cp.created_at DESC
');
$st->execute([$alunoId, $turmaId, $professorId]);
$outros = $st->fetchAll(PDO::FETCH_ASSOC);
echo "1. Outros Comentários (excluindo prof $professorId): " . count($outros) . "\n";

// 2. Simular Busca de Meus Comentários
$stMeu = $db->prepare('
    SELECT cp.id, cp.conteudo, cp.created_at, cp.updated_at,
           u.name as professor_nome, u.photo as professor_photo
    FROM comentarios_professores cp
    JOIN users u ON u.id = cp.professor_id
    WHERE cp.aluno_id = ? AND cp.turma_id = ? AND cp.professor_id = ?
    ORDER BY cp.created_at DESC
');
$stMeu->execute([$alunoId, $turmaId, $professorId]);
$meus = $stMeu->fetchAll(PDO::FETCH_ASSOC);
echo "2. Meus Comentários (prof $professorId): " . count($meus) . "\n";

// 3. Simular Busca de Todos os Comentários (Análise)
$stAll = $db->prepare('
    SELECT cp.conteudo, cp.created_at
    FROM comentarios_professores cp
    WHERE cp.aluno_id = ?
    ORDER BY cp.created_at DESC
');
$stAll->execute([$alunoId]);
$todos = $stAll->fetchAll(PDO::FETCH_ASSOC);
echo "3. Todos os Comentários do aluno: " . count($todos) . "\n";

echo "\n--- Conteúdo de 'todos_comentarios' ---\n";
foreach ($todos as $c) {
    echo "  [" . $c['created_at'] . "] " . substr(strip_tags($c['conteudo']), 0, 50) . "...\n";
}

// 4. Testar a Análise de Sentimento (Simulando JS em PHP com lógica equivalente)
echo "\n--- Simulação de Análise de Sentimento ---\n";
if (count($todos) < 2) {
    echo "RESULTADO: Menos de 2 comentários. Tendência seria '—'.\n";
} else {
    echo "RESULTADO: " . count($todos) . " comentários. Tendência deveria ser exibida.\n";
}

echo "\n--- Verificação de Permissão Manual ---\n";
$stP = $db->prepare("SELECT can_access FROM profile_permissions WHERE profile = 'Administrador' AND resource = 'students.comments' AND instituicao_id = 1");
$stP->execute();
$can = $stP->fetchColumn();
echo "Permissão 'students.comments' para Admin na inst 1: " . ($can ? "SIM" : "NÃO") . "\n";
