<?php
/**
 * Script para executar migrações de colaboradores complementares e endomarketing
 * Acesse: http://localhost/rh-privus/executar_migracoes_colaboradores_endomarketing.php
 */

require_once __DIR__ . '/includes/functions.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Executar Migrações - Colaboradores e Endomarketing</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #009ef7; }
        .success { color: green; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; padding: 10px; background: #d1ecf1; border-radius: 4px; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Executar Migrações - Colaboradores e Endomarketing</h1>
        
        <?php
        try {
            $pdo = getDB();
            
            echo "<h2>Executando migrações...</h2><pre>";
            
            // 1. Colaboradores complementares
            echo "1. Adicionando campos complementares em colaboradores...\n";
            $sql = file_get_contents(__DIR__ . '/migracao_colaboradores_complementar.sql');
            $pdo->exec($sql);
            echo "<span class='success'>✅ Campos complementares adicionados!</span>\n\n";
            
            // 2. Endomarketing
            echo "2. Criando sistema de endomarketing...\n";
            $sql = file_get_contents(__DIR__ . '/migracao_endomarketing.sql');
            $pdo->exec($sql);
            echo "<span class='success'>✅ Sistema de endomarketing criado!</span>\n\n";
            
            echo "</pre>";
            echo "<div class='success'><strong>✅ Todas as migrações foram executadas com sucesso!</strong></div>";
            echo "<div class='info'><strong>ℹ️</strong> Você pode fechar esta página agora.</div>";
            
        } catch (PDOException $e) {
            echo "</pre>";
            echo "<div class='error'><strong>❌ Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
        ?>
    </div>
</body>
</html>

