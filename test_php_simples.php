<?php
/**
 * Teste simples para verificar se PHP está funcionando
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Teste PHP</title></head><body>";
echo "<h1>✅ PHP está funcionando!</h1>";
echo "<p><strong>Versão PHP:</strong> " . phpversion() . "</p>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Script Name:</strong> " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p><strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p><strong>HTTPS:</strong> " . (isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'Não') . "</p>";

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
echo "<p><a href='index.php'>Tentar acessar index.php</a></p>";
echo "<p><a href='login.php'>Tentar acessar login.php</a></p>";
echo "<p><a href='test_enviar_push.php'>Tentar acessar test_enviar_push.php</a></p>";

echo "</body></html>";
?>

