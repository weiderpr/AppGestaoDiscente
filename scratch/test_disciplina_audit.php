<?php
/**
 * Test script to verify DisciplinaService audit integration
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/DisciplinaService.php';

use App\Services\DisciplinaService;

if (session_status() === PHP_SESSION_NONE) session_start();

$db = getDB();
$service = new DisciplinaService();

$instIdRow = $db->query("SELECT id FROM institutions LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$catRow = $db->query("SELECT id FROM disciplina_categorias WHERE institution_id = {$instIdRow['id']} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$instId = (int)$instIdRow['id'];
$userId = 2;

$_SESSION['user_id'] = $userId;
$_SESSION['institution_id'] = $instId;

echo "--- Teste de Auditoria: DisciplinaService ---\n";

if (!$catRow) {
    die("❌ Nenhuma categoria de disciplina encontrada para teste.\n");
}

$testCodigo = 'TST_AUDIT_999';

// Limpar dados antigos de testes anteriores
$db->prepare("DELETE FROM disciplinas WHERE codigo = ? AND institution_id = ?")->execute([$testCodigo, $instId]);

// 1. CREATE
echo "1. Testando CREATE...";
$service->create(['codigo' => $testCodigo, 'descricao' => 'Disciplina de Teste Auditoria', 'categoria_id' => $catRow['id'], 'observacoes' => ''], $instId);
$logCreate = $db->query("SELECT * FROM audit_logs WHERE action='CREATE' AND table_name='disciplinas' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$newValues = json_decode($logCreate['new_values'] ?? '{}', true);
echo ($newValues['codigo'] ?? '') === $testCodigo ? " ✅ Log registrado (ID: {$logCreate['id']})\n" : " ❌ Log NÃO encontrado!\n";

// 2. UPDATE
echo "2. Testando UPDATE...";
$service->update($testCodigo, ['codigo' => $testCodigo, 'descricao' => 'Disciplina ATUALIZADA', 'categoria_id' => $catRow['id'], 'observacoes' => ''], $instId);
$logUpdate = $db->query("SELECT * FROM audit_logs WHERE action='UPDATE' AND table_name='disciplinas' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$newValues = json_decode($logUpdate['new_values'] ?? '{}', true);
echo ($newValues['descricao'] ?? '') === 'Disciplina ATUALIZADA' ? " ✅ Log registrado (ID: {$logUpdate['id']})\n" : " ❌ Log NÃO encontrado!\n";

// 3. DELETE
echo "3. Testando DELETE...";
$service->delete($testCodigo, $instId);
$logDelete = $db->query("SELECT * FROM audit_logs WHERE action='DELETE' AND table_name='disciplinas' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$oldValues = json_decode($logDelete['old_values'] ?? '{}', true);
echo ($oldValues['codigo'] ?? '') === $testCodigo ? " ✅ Log registrado (ID: {$logDelete['id']})\n" : " ❌ Log NÃO encontrado!\n";

echo "\n--- Fim do Teste ---\n";
