<?php
/**
 * Teste de PWA - Verifica se tudo está configurado corretamente
 * Acesse: http://localhost/rh-privus/testar_pwa.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste PWA - RH Privus</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 2px solid #009ef7; padding-bottom: 10px; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #009ef7;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover { background: #0088d1; }
    </style>
</head>
<body>
    <h1>🔍 Teste de PWA - RH Privus</h1>
    
    <div class="card">
        <h2>1. Verificação de Arquivos</h2>
        <?php
        $files = [
            'manifest.json' => 'Manifest do PWA',
            'sw.js' => 'Service Worker',
            'OneSignalSDKWorker.js' => 'OneSignal Service Worker',
            'assets/js/onesignal-init.js' => 'Script de inicialização OneSignal',
            'api/onesignal/config.php' => 'API de configuração OneSignal',
            'api/onesignal/subscribe.php' => 'API de subscription OneSignal',
            'api/onesignal/send.php' => 'API de envio OneSignal'
        ];
        
        $allOk = true;
        foreach ($files as $file => $desc) {
            $exists = file_exists(__DIR__ . '/' . $file);
            if ($exists) {
                echo "<p class='success'>✅ $desc (<code>$file</code>)</p>";
            } else {
                echo "<p class='error'>❌ $desc (<code>$file</code>) - NÃO ENCONTRADO</p>";
                $allOk = false;
            }
        }
        ?>
    </div>
    
    <div class="card">
        <h2>2. Verificação de Manifest.json</h2>
        <?php
        $manifestPath = __DIR__ . '/manifest.json';
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            if ($manifest) {
                echo "<p class='success'>✅ Manifest válido</p>";
                echo "<p><strong>Nome:</strong> {$manifest['name']}</p>";
                echo "<p><strong>Start URL:</strong> <code>{$manifest['start_url']}</code></p>";
                echo "<p><strong>Display:</strong> {$manifest['display']}</p>";
                echo "<p><strong>Ícones:</strong> " . count($manifest['icons']) . " configurados</p>";
                
                // Verifica se ícones existem
                $iconsOk = true;
                foreach ($manifest['icons'] as $icon) {
                    $iconPath = $_SERVER['DOCUMENT_ROOT'] . $icon['src'];
                    if (!file_exists($iconPath)) {
                        echo "<p class='warning'>⚠️ Ícone não encontrado: <code>{$icon['src']}</code></p>";
                        $iconsOk = false;
                    }
                }
                if ($iconsOk) {
                    echo "<p class='success'>✅ Todos os ícones existem</p>";
                }
            } else {
                echo "<p class='error'>❌ Manifest inválido (erro de JSON)</p>";
                $allOk = false;
            }
        } else {
            echo "<p class='error'>❌ Manifest não encontrado</p>";
            $allOk = false;
        }
        ?>
    </div>
    
    <div class="card">
        <h2>3. Verificação de Banco de Dados</h2>
        <?php
        try {
            require_once __DIR__ . '/includes/functions.php';
            $pdo = getDB();
            
            // Verifica tabela onesignal_config
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM onesignal_config");
                $result = $stmt->fetch();
                echo "<p class='success'>✅ Tabela <code>onesignal_config</code> existe</p>";
                
                // Verifica se tem configuração
                $stmt = $pdo->query("SELECT app_id FROM onesignal_config ORDER BY id DESC LIMIT 1");
                $config = $stmt->fetch();
                if ($config && !empty($config['app_id'])) {
                    echo "<p class='success'>✅ OneSignal configurado (App ID: " . substr($config['app_id'], 0, 20) . "...)</p>";
                } else {
                    echo "<p class='warning'>⚠️ OneSignal não configurado. <a href='pages/configuracoes_onesignal.php'>Configurar agora</a></p>";
                }
            } catch (PDOException $e) {
                echo "<p class='error'>❌ Tabela <code>onesignal_config</code> não existe</p>";
                echo "<p class='info'>💡 Execute: <a href='criar_tabelas_onesignal.php'>criar_tabelas_onesignal.php</a></p>";
            }
            
            // Verifica tabela onesignal_subscriptions
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM onesignal_subscriptions");
                $result = $stmt->fetch();
                echo "<p class='success'>✅ Tabela <code>onesignal_subscriptions</code> existe ({$result['total']} subscriptions)</p>";
            } catch (PDOException $e) {
                echo "<p class='error'>❌ Tabela <code>onesignal_subscriptions</code> não existe</p>";
                echo "<p class='info'>💡 Execute: <a href='criar_tabelas_onesignal.php'>criar_tabelas_onesignal.php</a></p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Erro ao conectar ao banco: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    
    <div class="card">
        <h2>4. Teste de URLs</h2>
        <?php
        $baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
        $testUrls = [
            '/rh-privus/manifest.json' => 'Manifest',
            '/rh-privus/sw.js' => 'Service Worker',
            '/rh-privus/api/onesignal/config.php' => 'API Config OneSignal'
        ];
        
        foreach ($testUrls as $url => $desc) {
            $fullUrl = $baseUrl . $url;
            echo "<p><strong>$desc:</strong> <a href='$fullUrl' target='_blank'>$fullUrl</a></p>";
        }
        ?>
    </div>
    
    <div class="card">
        <h2>5. Instruções de Instalação</h2>
        <h3>📱 Android (Chrome/Edge)</h3>
        <ol>
            <li>Acesse o sistema no Chrome do Android</li>
            <li>Toque no menu (3 pontos) → "Adicionar à tela inicial"</li>
            <li>Ou aguarde o banner automático aparecer</li>
        </ol>
        
        <h3>🍎 iOS (Safari)</h3>
        <ol>
            <li>Abra o Safari no iPhone/iPad</li>
            <li>Acesse o sistema</li>
            <li>Toque no botão de compartilhar (quadrado com seta)</li>
            <li>Role e toque em "Adicionar à Tela de Início"</li>
        </ol>
        
        <p class="info">
            <strong>💡 Dica:</strong> Para testar em dispositivo físico, use o IP do seu computador:<br>
            <code>http://SEU_IP_AQUI/rh-privus/</code><br>
            (Celular e computador devem estar na mesma rede WiFi)
        </p>
    </div>
    
    <div class="card">
        <h2>6. Próximos Passos</h2>
        <?php if ($allOk): ?>
            <p class="success">✅ Tudo parece estar configurado! Você pode instalar o PWA agora.</p>
        <?php else: ?>
            <p class="warning">⚠️ Alguns itens precisam ser corrigidos antes de instalar.</p>
        <?php endif; ?>
        
        <a href="login.php" class="btn">Ir para Login</a>
        <a href="pages/configuracoes_onesignal.php" class="btn">Configurar OneSignal</a>
        <a href="test_onesignal.php" class="btn">Testar OneSignal</a>
    </div>
    
    <div class="card">
        <h2>7. Verificação no Browser</h2>
        <p>Abra o Console do Browser (F12) e verifique:</p>
        <ul>
            <li>Service Worker registrado</li>
            <li>OneSignal inicializado</li>
            <li>Sem erros 404</li>
        </ul>
        <p class="info">
            <strong>No Chrome:</strong> DevTools → Application → Manifest<br>
            <strong>No Safari iOS:</strong> Não há DevTools, mas o menu de compartilhar deve aparecer
        </p>
    </div>
</body>
</html>

