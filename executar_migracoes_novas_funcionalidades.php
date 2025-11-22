<?php
/**
 * Script para executar migrações das novas funcionalidades
 * Acesse: http://localhost/rh-privus/executar_migracoes_novas_funcionalidades.php
 */

require_once __DIR__ . '/includes/functions.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Executar Migrações - Novas Funcionalidades</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #009ef7; }
        .success { color: green; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; padding: 10px; background: #d1ecf1; border-radius: 4px; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #009ef7; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        .btn:hover { background: #0085d1; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Executar Migrações - Novas Funcionalidades</h1>
        
        <?php
        try {
            $pdo = getDB();
            
            echo "<h2>Executando migrações...</h2><pre>";
            
            // 1. Demissões
            echo "1. Criando tabela demissoes...\n";
            $sql = file_get_contents(__DIR__ . '/migracao_demissoes.sql');
            $pdo->exec($sql);
            echo "<span class='success'>✅ Tabela demissoes criada!</span>\n\n";
            
            // 2. Emoções
            echo "2. Criando tabela emocoes...\n";
            $sql = file_get_contents(__DIR__ . '/migracao_emocoes.sql');
            $pdo->exec($sql);
            echo "<span class='success'>✅ Tabela emocoes criada!</span>\n\n";
            
            // 3. Pontuação
            echo "3. Criando tabelas de pontuação...\n";
            $sql = file_get_contents(__DIR__ . '/migracao_pontuacao.sql');
            $pdo->exec($sql);
            echo "<span class='success'>✅ Tabelas de pontuação criadas!</span>\n\n";
            
            // 4. Feed
            echo "4. Criando tabelas do feed...\n";
            $sql = file_get_contents(__DIR__ . '/migracao_feed.sql');
            $pdo->exec($sql);
            echo "<span class='success'>✅ Tabelas do feed criadas!</span>\n\n";
            
            // 5. Notificações do Sistema
            echo "5. Criando tabela notificacoes_sistema...\n";
            $sql = file_get_contents(__DIR__ . '/migracao_notificacoes_sistema.sql');
            $pdo->exec($sql);
            echo "<span class='success'>✅ Tabela notificacoes_sistema criada!</span>\n\n";
            
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

