<?php
/**
 * Teste de caminho da API
 * Acesse: http://localhost/rh-privus/test_api_path.php
 */

echo "<h2>Teste de Caminhos da API</h2>";
echo "<p>Caminho atual: " . $_SERVER['PHP_SELF'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";

echo "<h3>Testando acesso à API:</h3>";

$paths = [
    '/rh-privus/api/onesignal/config.php',
    '../api/onesignal/config.php',
    'api/onesignal/config.php',
    __DIR__ . '/api/onesignal/config.php'
];

foreach ($paths as $path) {
    $fullPath = __DIR__ . '/' . ltrim($path, '/');
    $exists = file_exists($fullPath);
    echo "<p>";
    echo "<strong>$path</strong>: ";
    if ($exists) {
        echo "<span style='color: green;'>✅ Existe</span>";
    } else {
        echo "<span style='color: red;'>❌ Não existe</span> (Procurado em: $fullPath)";
    }
    echo "</p>";
}

echo "<h3>Testando acesso via HTTP:</h3>";
$baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
$testPaths = [
    $baseUrl . '/rh-privus/api/onesignal/config.php',
    $baseUrl . '/rh-privus/test_api_path.php'
];

foreach ($testPaths as $url) {
    echo "<p>Testando: <a href='$url' target='_blank'>$url</a></p>";
}

