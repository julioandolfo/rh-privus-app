<?php
/**
 * Script para executar migração de contratos
 */

require_once __DIR__ . '/includes/functions.php';

echo "<h1>Executando Migração - Sistema de Contratos</h1>";

try {
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Lê o arquivo SQL
    $sql_file = __DIR__ . '/migracao_contratos_autentique.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("Arquivo de migração não encontrado: $sql_file");
    }
    
    $sql = file_get_contents($sql_file);
    
    if (empty($sql)) {
        throw new Exception("Arquivo de migração está vazio!");
    }
    
    echo "<p>Lendo arquivo de migração...</p>";
    
    // Remove comentários
    $sql = preg_replace('/^--.*$/m', '', $sql);
    
    // Separa por statements (por ponto e vírgula seguido de quebra de linha)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt);
        }
    );
    
    echo "<p>Encontrados " . count($statements) . " comandos SQL</p>";
    echo "<ul>";
    
    $pdo->beginTransaction();
    
    foreach ($statements as $i => $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            
            // Identifica qual tabela foi criada
            if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "<li style='color:green;'>✓ Tabela '{$matches[1]}' criada/verificada com sucesso</li>";
            } else {
                echo "<li style='color:green;'>✓ Comando " . ($i + 1) . " executado com sucesso</li>";
            }
        } catch (Exception $e) {
            echo "<li style='color:orange;'>⚠ Aviso no comando " . ($i + 1) . ": " . $e->getMessage() . "</li>";
            // Continua mesmo com erro (pode ser tabela que já existe)
        }
    }
    
    $pdo->commit();
    
    echo "</ul>";
    echo "<h2 style='color:green;'>✓ Migração concluída com sucesso!</h2>";
    
    // Verifica se as tabelas foram criadas
    echo "<h3>Verificando tabelas criadas:</h3>";
    echo "<ul>";
    
    $tabelas = ['contratos_templates', 'contratos', 'contratos_signatarios', 'contratos_eventos', 'autentique_config'];
    
    foreach ($tabelas as $tabela) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->fetch()) {
            echo "<li style='color:green;'>✓ Tabela '$tabela' existe</li>";
        } else {
            echo "<li style='color:red;'>✗ Tabela '$tabela' NÃO existe</li>";
        }
    }
    
    echo "</ul>";
    
    echo "<p><a href='pages/contratos.php'>Ir para Contratos</a></p>";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h2 style='color:red;'>✗ Erro na migração:</h2>";
    echo "<p style='color:red;'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
