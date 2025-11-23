<?php
/**
 * Script para adicionar campo Instagram na tabela candidatos
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = getDB();
    
    // Verifica se o campo já existe
    $stmt = $pdo->query("SHOW COLUMNS FROM candidatos LIKE 'instagram'");
    if ($stmt->rowCount() > 0) {
        echo "Campo 'instagram' já existe na tabela candidatos.\n";
    } else {
        // Adiciona o campo
        $pdo->exec("ALTER TABLE candidatos ADD COLUMN instagram VARCHAR(255) NULL AFTER portfolio");
        echo "Campo 'instagram' adicionado com sucesso na tabela candidatos!\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

