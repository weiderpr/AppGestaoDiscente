<?php
require_once __DIR__ . '/includes/auth.php';

$db = getDB();

try {
    // 1. Adicionar a coluna target_user_id se não existir
    $check = $db->query("SHOW COLUMNS FROM `sys_notifications` LIKE 'target_user_id'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE `sys_notifications` ADD COLUMN `target_user_id` INT UNSIGNED DEFAULT NULL AFTER `turma_id`;");
        $db->exec("ALTER TABLE `sys_notifications` ADD CONSTRAINT `fk_notif_target_user` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;");
        $db->exec("CREATE INDEX `idx_notif_target_user` ON `sys_notifications` (`target_user_id`);");
        echo "Coluna target_user_id adicionada à tabela sys_notifications.\n";
    }

    echo "OK - Tabela sys_notifications atualizada com sucesso.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
