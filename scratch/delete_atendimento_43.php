<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/AtendimentoService.php';

$db = getDB();
$service = new \App\Services\AtendimentoService();

// Busca o institution_id para validar a exclusão pelo Service
$st = $db->prepare("SELECT institution_id FROM gestao_atendimentos WHERE id = 43");
$st->execute();
$instId = $st->fetchColumn();

if ($instId) {
    // A exclusão via Service garante que o deleted_at seja preenchido e que a auditoria seja disparada
    $success = $service->deleteAtendimento(43, (int)$instId);
    echo $success ? "Sucesso: O atendimento ID 43 foi excluído logicamente." : "Erro: Falha ao excluir o atendimento via Service.";
} else {
    echo "Erro: Atendimento ID 43 não encontrado ou já excluído.";
}
