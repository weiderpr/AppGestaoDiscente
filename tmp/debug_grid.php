<?php
/**
 * Advanced Debugger for student_grid.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock server vars
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Mock session
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = 1;

$_GET['aluno_id'] = 1; // Test with ID 1

try {
    echo "--- INICIANDO INCLUSÃO ---\n";
    ob_start();
    include __DIR__ . '/../courses/aulas/student_grid.php';
    echo ob_get_clean();
    echo "\n--- FIM DA INCLUSÃO ---\n";
} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_end_clean();
    echo "\nFATAL ERROR CAUGHT: [" . get_class($e) . "]\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
