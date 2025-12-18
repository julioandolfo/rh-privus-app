<?php
/**
 * Script para executar migração: Permitir entrevistas sem candidatura
 */

require_once __DIR__ . '/includes/functions.php';

echo "Executando migração: Entrevistas Manuais\n";
echo "==========================================\n\n";

try {
    $pdo = getDB();
    
    // Lê o arquivo SQL
    $sql = file_get_contents(__DIR__ . '/migracao_entrevistas_manual.sql');
    
    if (!$sql) {
        throw new Exception('Não foi possível ler o arquivo de migração');
    }
    
    // Divide em comandos (separados por ;)
    $comandos = array_filter(array_map('trim', explode(';', $sql)));
    
    $executados = 0;
    $erros = 0;
    
    foreach ($comandos as $comando) {
        if (empty($comando) || strpos($comando, '--') === 0) {
            continue; // Ignora comentários e linhas vazias
        }
        
        try {
            $pdo->exec($comando);
            $executados++;
            echo "✓ Comando executado com sucesso\n";
        } catch (PDOException $e) {
            // Se for erro de coluna já existe ou constraint já existe, ignora
            if (strpos($e->getMessage(), 'Duplicate column') !== false || 
                strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "⚠ Comando já executado anteriormente (ignorado)\n";
            } else {
                $erros++;
                echo "✗ Erro: " . $e->getMessage() . "\n";
                echo "  Comando: " . substr($comando, 0, 100) . "...\n";
            }
        }
    }
    
    echo "\n==========================================\n";
    echo "Migração concluída!\n";
    echo "Comandos executados: $executados\n";
    if ($erros > 0) {
        echo "Erros: $erros\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

