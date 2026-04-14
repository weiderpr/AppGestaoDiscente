<?php
/**
 * Vértice Acadêmico — API de Salvamento (Avaliações)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/Traits/Auditable.php';
requireLogin();

$user = getCurrentUser();
if (!$user || !in_array($user['profile'], ['Administrador', 'Coordenador'])) {
    echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

// Verificar CSRF
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Token de segurança inválido. Recarregue a página.']);
    exit;
}

$db = getDB();
// Audit helper
if (!class_exists('_AvaliacaoAudit')) {
    class _AvaliacaoAudit extends \App\Services\Service { public function log(string $a, string $t, int $id, $old, $new): void { $this->audit($a,$t,$id,$old,$new); } }
}
$auditHelper = new _AvaliacaoAudit();
$id        = (int)($_POST['id'] ?? 0);
$nome      = trim($_POST['nome'] ?? '');
$tipo_id   = (int)($_POST['tipo_id'] ?? 0);
$perguntas = $_POST['perguntas'] ?? []; // Array de strings (textos das perguntas)

// Validações básicas
if (!$nome) {
    echo json_encode(['success' => false, 'error' => 'O nome da avaliação é obrigatório.']);
    exit;
}
if (!$tipo_id) {
    echo json_encode(['success' => false, 'error' => 'O tipo de avaliação é obrigatório.']);
    exit;
}
if (empty($perguntas)) {
    echo json_encode(['success' => false, 'error' => 'Adicione pelo menos uma pergunta.']);
    exit;
}

try {
    $db->beginTransaction();

    if ($id > 0) {
        // --- ATUALIZAÇÃO ---
        $old = $db->query("SELECT * FROM avaliacoes WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        
        // 1. Atualizar avaliação
        $st = $db->prepare("UPDATE avaliacoes SET nome = ?, tipo_id = ? WHERE id = ?");
        $st->execute([$nome, $tipo_id, $id]);
        $auditHelper->log('UPDATE', 'avaliacoes', $id, $old, ['nome' => $nome, 'tipo_id' => $tipo_id]);

        $db->prepare("UPDATE perguntas SET deleted_at = CURRENT_TIMESTAMP WHERE avaliacao_id = ? AND deleted_at IS NULL")
           ->execute([$id]);

        $avaliacao_id = $id;
        $message = "Avaliação atualizada com sucesso!";
    } else {
        // --- CRIAÇÃO ---
        $st = $db->prepare("INSERT INTO avaliacoes (nome, tipo_id) VALUES (?, ?)");
        $st->execute([$nome, $tipo_id]);
        $avaliacao_id = $db->lastInsertId();
        $auditHelper->log('CREATE', 'avaliacoes', (int)$avaliacao_id, null, ['nome' => $nome, 'tipo_id' => $tipo_id]);
        $message = "Avaliação criada com sucesso!";
    }

    // Inserir perguntas (com a ordem baseada no índice do formulário)
    $stMsg = $db->prepare("INSERT INTO perguntas (avaliacao_id, texto_pergunta, ordem) VALUES (?, ?, ?)");
    foreach ($perguntas as $index => $texto) {
        $texto = trim($texto);
        if ($texto === '') continue; // Ignorar perguntas vazias
        
        $ordem = $index + 1;
        $stMsg->execute([$avaliacao_id, $texto, $ordem]);
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => $message, 'id' => $avaliacao_id]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'error' => 'Erro no servidor: ' . $e->getMessage()]);
}
