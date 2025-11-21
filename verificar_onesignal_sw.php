<?php
/**
 * Verifica se o OneSignalSDKWorker.js está acessível
 * Acesse: http://localhost/rh-privus/verificar_onesignal_sw.php
 */

header('Content-Type: text/html; charset=utf-8');

$basePath = '/rh'; // Ajuste conforme necessário
if (strpos($_SERVER['REQUEST_URI'], '/rh-privus') !== false) {
    $basePath = '/rh-privus';
}

$swPath = __DIR__ . '/OneSignalSDKWorker.js';
$swUrl = $basePath . '/OneSignalSDKWorker.js';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verificar OneSignal Service Worker</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Verificação OneSignal Service Worker</h1>
    
    <div class="info">
        <strong>Base Path detectado:</strong> <?= $basePath ?><br>
        <strong>Caminho do arquivo:</strong> <?= $swPath ?><br>
        <strong>URL esperada:</strong> <?= $swUrl ?>
    </div>
    
    <h2>Status do Arquivo</h2>
    <?php if (file_exists($swPath)): ?>
        <p class="success">✅ Arquivo existe: <?= $swPath ?></p>
        <p><strong>Tamanho:</strong> <?= filesize($swPath) ?> bytes</p>
        <p><strong>Conteúdo:</strong></p>
        <pre><?= htmlspecialchars(file_get_contents($swPath)) ?></pre>
    <?php else: ?>
        <p class="error">❌ Arquivo NÃO existe: <?= $swPath ?></p>
    <?php endif; ?>
    
    <h2>Teste de Acesso HTTP</h2>
    <p><a href="<?= $swUrl ?>" target="_blank">Testar: <?= $swUrl ?></a></p>
    
    <h2>Instruções</h2>
    <ol>
        <li>O arquivo <code>OneSignalSDKWorker.js</code> deve estar na raiz do projeto</li>
        <li>A URL deve ser acessível via HTTP: <code><?= $swUrl ?></code></li>
        <li>Se retornar 404, verifique:
            <ul>
                <li>Se o arquivo existe na raiz</li>
                <li>Se o caminho base está correto</li>
                <li>Se há regras de .htaccess bloqueando</li>
            </ul>
        </li>
    </ol>
</body>
</html>

