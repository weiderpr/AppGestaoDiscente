<?php
/**
 * Migration: Seed Dispensas Permission
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

$resource = 'students.schedule.dispensas';
$adminProfiles = ['Coordenador', 'Diretor', 'Pedagogo', 'Naapi'];

try {
    // 1. Buscar todas as instituições existentes
    $stInst = $db->query("SELECT id FROM institutions");
    $institutions = $stInst->fetchAll(PDO::FETCH_COLUMN);

    if (empty($institutions)) {
        echo "Nenhuma instituição encontrada para semear permissões.\n";
        exit;
    }

    $inserted = 0;
    foreach ($institutions as $instId) {
        foreach ($adminProfiles as $profile) {
            // Verifica se já existe
            $stCheck = $db->prepare("SELECT COUNT(*) FROM profile_permissions WHERE profile = ? AND resource = ? AND instituicao_id = ?");
            $stCheck->execute([$profile, $resource, $instId]);
            
            if ($stCheck->fetchColumn() == 0) {
                $stAdd = $db->prepare("INSERT INTO profile_permissions (profile, resource, instituicao_id, can_access) VALUES (?, ?, ?, 1)");
                $stAdd->execute([$profile, $resource, $instId]);
                $inserted++;
            }
        }
    }

    echo "Semeio concluído. $inserted novos registros de permissão criados.\n";
} catch (PDOException $e) {
    echo "Erro no semeio: " . $e->getMessage() . "\n";
    exit(1);
}
