<?php
/**
 * Script para executar migração de templates de email de recrutamento
 */

require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();

echo "Executando migração de templates de email de recrutamento...\n\n";

try {
    // Lê o arquivo SQL
    $sql_file = __DIR__ . '/migracao_templates_email_recrutamento.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("Arquivo SQL não encontrado: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    
    // Divide em comandos SQL individuais (separados por ;)
    $commands = array_filter(
        array_map('trim', explode(';', $sql_content)),
        function($cmd) {
            return !empty($cmd) && !preg_match('/^\s*--/', $cmd);
        }
    );
    
    $pdo->beginTransaction();
    
    $sucesso = 0;
    $erros = 0;
    
    foreach ($commands as $command) {
        if (empty(trim($command))) {
            continue;
        }
        
        try {
            $pdo->exec($command);
            $sucesso++;
            echo "✓ Comando executado com sucesso\n";
        } catch (PDOException $e) {
            // Se for erro de duplicata, ignora (ON DUPLICATE KEY UPDATE)
            if (strpos($e->getMessage(), 'Duplicate entry') !== false || 
                strpos($e->getMessage(), 'ON DUPLICATE KEY') !== false) {
                echo "⚠ Template já existe (atualizado): " . $e->getMessage() . "\n";
                $sucesso++;
            } else {
                $erros++;
                echo "✗ Erro ao executar comando: " . $e->getMessage() . "\n";
                echo "  Comando: " . substr($command, 0, 100) . "...\n";
            }
        }
    }
    
    $pdo->commit();
    
    echo "\n========================================\n";
    echo "Migração concluída!\n";
    echo "Comandos executados com sucesso: $sucesso\n";
    echo "Erros: $erros\n";
    echo "========================================\n";
    
    // Lista templates criados
    echo "\nTemplates de recrutamento disponíveis:\n";
    $stmt = $pdo->query("
        SELECT codigo, nome, ativo 
        FROM email_templates 
        WHERE codigo IN ('confirmacao_candidatura', 'aprovacao', 'rejeicao', 'nova_candidatura_recrutador')
        ORDER BY codigo
    ");
    $templates = $stmt->fetchAll();
    
    foreach ($templates as $template) {
        $status = $template['ativo'] ? '✓ Ativo' : '✗ Inativo';
        echo "- {$template['codigo']}: {$template['nome']} ({$status})\n";
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

