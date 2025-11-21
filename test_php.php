<?php
/**
 * Teste simples para verificar se PHP está funcionando
 * Versão alternativa com nome mais simples
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Teste PHP</title></head><body>";
echo "<h1>✅ PHP está funcionando!</h1>";
echo "<p><strong>Versão PHP:</strong> " . phpversion() . "</p>";
echo "<p><strong>Servidor:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "</p>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Script Name:</strong> " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p><strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p><strong>HTTPS:</strong> " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'Sim' : 'Não') . "</p>";

// Testa se consegue incluir arquivos
echo "<h2>Teste de Includes:</h2>";
if (file_exists(__DIR__ . '/includes/functions.php')) {
    echo "<p style='color: green;'>✅ Arquivo includes/functions.php existe</p>";
    try {
        require_once __DIR__ . '/includes/functions.php';
        echo "<p style='color: green;'>✅ includes/functions.php carregado com sucesso</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro ao carregar: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Arquivo includes/functions.php não encontrado</p>";
}

if (file_exists(__DIR__ . '/config/db.php')) {
    echo "<p style='color: green;'>✅ Arquivo config/db.php existe</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Arquivo config/db.php não encontrado (pode ser normal se não configurado)</p>";
}

echo "<hr>";
echo "<h2>Arquivos de Teste Disponíveis:</h2>";
echo "<ul>";
echo "<li><a href='test_php.php'>test_php.php (este arquivo)</a></li>";
echo "<li><a href='test_php_simples.php'>test_php_simples.php</a></li>";
echo "<li><a href='test_subscription.php'>test_subscription.php</a></li>";
echo "<li><a href='test_enviar_push.php'>test_enviar_push.php</a></li>";
echo "<li><a href='test_index_simples.php'>test_index_simples.php</a></li>";
echo "<li><a href='index.php'>index.php</a></li>";
echo "<li><a href='login.php'>login.php</a></li>";
echo "</ul>";

echo "<hr>";
echo "<h2>Informações do Sistema:</h2>";
echo "<p><strong>Diretório atual:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Arquivos PHP na raiz:</strong></p>";
echo "<ul>";
$files = glob(__DIR__ . '/*.php');
foreach ($files as $file) {
    $filename = basename($file);
    echo "<li>$filename</li>";
}
echo "</ul>";

echo "</body></html>";
?>

