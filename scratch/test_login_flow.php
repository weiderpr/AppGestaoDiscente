<?php
/**
 * Test script to verify the fix for audit logging (Login via select_institution)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/Traits/Auditable.php';
require_once __DIR__ . '/../src/App/Services/UserService.php';

use App\Services\UserService;

$userService = new UserService();
$db = getDB();

// Test ID e Inst ID
$testUserId = 888;
$testInstId = 1;

echo "--- Iniciando teste de Auditoria (Select Institution Fix) ---\n";

// Simular seleção de instituição na sessão
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = $testUserId;
$_SESSION['institution_id'] = $testInstId;

echo "Simulando LOGIN via select_institution para usuário $testUserId na unidade $testInstId...\n";
$userService->logLogin($testUserId);

// Verificar no Banco
echo "Verificando logs no banco de dados...\n";
$stmt = $db->prepare("SELECT * FROM audit_logs WHERE record_id = ? AND action = 'LOGIN' ORDER BY id DESC LIMIT 1");
$stmt->execute([$testUserId]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if ($log) {
    echo "✅ Registro de auditoria encontrado (ID: {$log['id']}).\n";
    echo "   Action: {$log['action']} | Institution ID: " . ($log['institution_id'] ?? 'NULL') . "\n";
    
    if ((int)$log['institution_id'] === $testInstId) {
        echo "   ✅ Institution ID gravado corretamente.\n";
    } else {
        echo "   ❌ Erro: Institution ID gravado foi " . ($log['institution_id'] ?? 'NULL') . ", esperado $testInstId\n";
    }
} else {
    echo "❌ Erro: Log de login não encontrado para o usuário $testUserId.\n";
}

echo "--- Fim do teste ---\n";
