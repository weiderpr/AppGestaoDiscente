<?php
/**
 * Test script to verify the fix for audit logging (Login/Logout)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/UserService.php';

use App\Services\UserService;

$userService = new UserService();
$db = getDB();

// Test ID
$testUserId = 999; // Using a mock ID for testing logic

echo "--- Iniciando teste de Auditoria (Fix) ---\n";

// 1. Simular Login
echo "Simulando LOGIN para usuário $testUserId...\n";
$userService->logLogin($testUserId);

// 2. Simular Logout
echo "Simulando LOGOUT para usuário $testUserId...\n";
$userService->logLogout($testUserId);

// 3. Verificar no Banco
echo "Verificando logs no banco de dados...\n";
$stmt = $db->prepare("SELECT * FROM audit_logs WHERE record_id = ? AND action IN ('LOGIN', 'LOGOUT') ORDER BY id DESC LIMIT 2");
$stmt->execute([$testUserId]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($logs) === 2) {
    echo "✅ Encontrados 2 registros de auditoria.\n";
    foreach ($logs as $log) {
        echo "   Action: {$log['action']} | User ID (Actor): {$log['user_id']} | Record ID: {$log['record_id']}\n";
        if ((int)$log['user_id'] === $testUserId) {
            echo "   ✅ User ID gravado corretamente.\n";
        } else {
            echo "   ❌ Erro: User ID gravado foi {$log['user_id']}, esperado $testUserId\n";
        }
    }
} else {
    echo "❌ Erro: Não foram encontrados os logs esperados. Total encontrado: " . count($logs) . "\n";
}

echo "--- Fim do teste ---\n";
