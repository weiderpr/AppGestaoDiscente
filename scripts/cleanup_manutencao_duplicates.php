<?php
/**
 * Cleanup: Remover duplicados e adicionar restrição UNIQUE (Versão Corrigida)
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();

    // 1. Limpar tabela de problemas padrão
    // Mantém apenas o menor ID para cada descrição duplicada
    $db->exec("DELETE p1 FROM manutencao_problemas_padrao p1
               INNER JOIN manutencao_problemas_padrao p2 
               WHERE p1.id > p2.id AND p1.descricao = p2.descricao");

    // 2. Adicionar UNIQUE na descrição para evitar novos duplicados
    try {
        $db->exec("ALTER TABLE manutencao_problemas_padrao ADD UNIQUE (descricao)");
    } catch(Exception $e) {
        // Se já existir ou erro de constraint, ignora
    }

    // 3. Limpar a tabela de vínculos (Truncate é mais seguro aqui)
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE manutencao_ambiente_problemas");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    // 4. Re-vincular de forma limpa
    $stmt = $db->query("SELECT id FROM manutencao_problemas_padrao");
    $problemaIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $db->query("SELECT id FROM manutencao_ambientes");
    $ambienteIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($problemaIds) && !empty($ambienteIds)) {
        foreach ($ambienteIds as $aid) {
            foreach ($problemaIds as $pid) {
                $db->prepare("INSERT IGNORE INTO manutencao_ambiente_problemas (ambiente_id, problema_id) VALUES (?, ?)")
                   ->execute([$aid, $pid]);
            }
        }
    }

    echo "Duplicados removidos e banco de dados higienizado com sucesso!\n";

} catch (Exception $e) {
    die("Erro no cleanup: " . $e->getMessage() . "\n");
}
