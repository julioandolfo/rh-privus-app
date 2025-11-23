<?php
/**
 * Script para adicionar campos horario_trabalho e dias_trabalho na tabela vagas
 */

require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = getDB();
    
    // Verifica se os campos já existem
    $stmt = $pdo->query("SHOW COLUMNS FROM vagas LIKE 'horario_trabalho'");
    if ($stmt->rowCount() > 0) {
        echo "Campo 'horario_trabalho' já existe.\n";
    } else {
        $pdo->exec("ALTER TABLE vagas ADD COLUMN horario_trabalho VARCHAR(100) NULL AFTER localizacao");
        echo "Campo 'horario_trabalho' adicionado com sucesso!\n";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM vagas LIKE 'dias_trabalho'");
    if ($stmt->rowCount() > 0) {
        echo "Campo 'dias_trabalho' já existe.\n";
    } else {
        $pdo->exec("ALTER TABLE vagas ADD COLUMN dias_trabalho VARCHAR(100) NULL AFTER horario_trabalho");
        echo "Campo 'dias_trabalho' adicionado com sucesso!\n";
    }
    
    echo "\nMigração concluída!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

