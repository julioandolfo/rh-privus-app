<?php
/**
 * Teste simples para verificar se PHP está funcionando
 * Acesse: https://privus.com.br/rh/test_index_simples.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>✅ Teste de PHP</h1>";
echo "<p>PHP está funcionando!</p>";
echo "<p>Versão PHP: " . phpversion() . "</p>";
echo "<p>Servidor: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Name: " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";

echo "<h2>Teste de Includes</h2>";

try {
    echo "<p>Tentando carregar functions.php...</p>";
    require_once __DIR__ . '/includes/functions.php';
    echo "<p>✅ functions.php carregado!</p>";
    
    echo "<p>Tentando conectar ao banco...</p>";
    $pdo = getDB();
    echo "<p>✅ Conexão com banco OK!</p>";
    
    echo "<p>Tentando carregar auth.php...</p>";
    require_once __DIR__ . '/includes/auth.php';
    echo "<p>✅ auth.php carregado!</p>";
    
    echo "<p>Testando get_login_url()...</p>";
    $loginUrl = get_login_url();
    echo "<p>✅ Login URL: " . htmlspecialchars($loginUrl) . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Arquivo: " . $e->getFile() . "</p>";
    echo "<p>Linha: " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "<p style='color: red;'>❌ Erro Fatal: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Arquivo: " . $e->getFile() . "</p>";
    echo "<p>Linha: " . $e->getLine() . "</p>";
}

echo "<h2>Teste de Sessão</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "<p>Status da sessão: " . (session_status() === PHP_SESSION_ACTIVE ? 'Ativa' : 'Inativa') . "</p>";

echo "<h2>Links de Teste</h2>";
echo "<p><a href='index.php'>Tentar index.php</a></p>";
echo "<p><a href='login.php'>Tentar login.php</a></p>";
echo "<p><a href='manifest.php'>Tentar manifest.php</a></p>";
