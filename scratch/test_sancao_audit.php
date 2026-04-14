<?php
/**
 * Test script to verify SancaoService audit integration
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SancaoService.php';

use App\Services\SancaoService;

if (session_status() === PHP_SESSION_NONE) session_start();

$db = getDB();
$service = new SancaoService();

// Setup: use real IDs from DB
$instIdRow = $db->query("SELECT id FROM institutions LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$userIdRow = $db->query("SELECT id FROM users WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$alunoRow = $db->query("
    SELECT a.id, ta.turma_id 
    FROM alunos a 
    JOIN turma_alunos ta ON ta.aluno_id = a.id 
    JOIN turmas t ON ta.turma_id = t.id 
    JOIN courses c ON t.course_id = c.id 
    WHERE c.institution_id = {$instIdRow['id']} 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
$tipoRow = $db->query("SELECT id FROM sancao_tipo WHERE institution_id = {$instIdRow['id']} AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$instId = (int)$instIdRow['id'];
$userId = (int)$userIdRow['id'];

// Set session context for audit
$_SESSION['user_id'] = $userId;
$_SESSION['institution_id'] = $instId;

echo "--- Teste de Auditoria: SancaoService ---\n";
echo "Instituição: $instId | Usuário: $userId\n\n";

if (!$alunoRow || !$tipoRow) {
    die("❌ Dados de teste insuficientes no banco (aluno ou tipo_sancao não encontrado).\n");
}

// 1. CREATE
echo "1. Testando CREATE...";
$dataCreate = [
    'id'             => 0,
    'aluno_id'       => (int)$alunoRow['id'],
    'data_sancao'    => date('Y-m-d'),
    'sancao_tipo_id' => (int)$tipoRow['id'],
    'observacoes'    => 'Sanção de TESTE - script de auditoria',
    'status'         => 'Em aberto',
    'acoes'          => [],
];
$newId = $service->save($dataCreate, $instId, $userId);
$logCreate = $db->query("SELECT * FROM audit_logs WHERE action='CREATE' AND table_name='sancao' AND record_id=$newId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($logCreate) {
    echo " ✅ Log registrado (audit_log ID: {$logCreate['id']})\n";
} else {
    echo " ❌ Log NÃO encontrado!\n";
}

// 2. UPDATE
echo "2. Testando UPDATE...";
$dataUpdate = array_merge($dataCreate, ['id' => $newId, 'observacoes' => 'Sanção ATUALIZADA']);
$service->save($dataUpdate, $instId, $userId);
$logUpdate = $db->query("SELECT * FROM audit_logs WHERE action='UPDATE' AND table_name='sancao' AND record_id=$newId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($logUpdate) {
    echo " ✅ Log registrado (audit_log ID: {$logUpdate['id']})\n";
} else {
    echo " ❌ Log NÃO encontrado!\n";
}

// 3. FINISH
echo "3. Testando FINISH...";
$service->finish($newId, $instId);
$logFinish = $db->query("SELECT * FROM audit_logs WHERE action='UPDATE' AND table_name='sancao' AND record_id=$newId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($logFinish && json_decode($logFinish['new_values'], true)['status'] === 'Concluído') {
    echo " ✅ Log registrado (audit_log ID: {$logFinish['id']})\n";
} else {
    echo " ❌ Log NÃO encontrado com status Concluído!\n";
}

// 4. DELETE
echo "4. Testando DELETE...";
$service->delete($newId, $instId, $userId);
$logDelete = $db->query("SELECT * FROM audit_logs WHERE action='DELETE' AND table_name='sancao' AND record_id=$newId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($logDelete) {
    echo " ✅ Log registrado (audit_log ID: {$logDelete['id']})\n";
} else {
    echo " ❌ Log NÃO encontrado!\n";
}

echo "\n--- Fim do Teste ---\n";
