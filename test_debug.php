<?php
/**
 * Script de Teste de Debug
 * Acesse este arquivo no navegador para verificar se o debug está funcionando
 */

// Carrega configuração de debug
require_once __DIR__ . '/config/debug.php';

echo "<h1 style='font-family:Arial,sans-serif;color:#333;'>🔧 Teste de Debug - Sistema RH Privus</h1>";
echo "<hr>";

// Teste 1: Configurações PHP
echo "<div style='background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;margin:10px 0;border-radius:5px;font-family:monospace;'>";
echo "<h3>✓ Configurações PHP</h3>";
echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>";
echo "<strong>Display Errors:</strong> " . ini_get('display_errors') . "<br>";
echo "<strong>Error Reporting:</strong> " . error_reporting() . "<br>";
echo "<strong>Debug Mode:</strong> " . (defined('DEBUG_MODE') && DEBUG_MODE ? 'ATIVADO' : 'DESATIVADO') . "<br>";
echo "</div>";

// Teste 2: Conexão com Banco de Dados
echo "<div style='background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;margin:10px 0;border-radius:5px;font-family:monospace;'>";
echo "<h3>✓ Teste de Conexão com Banco de Dados</h3>";

try {
    $config = include __DIR__ . '/config/db.php';
    echo "<strong>Host:</strong> " . $config['host'] . "<br>";
    echo "<strong>Database:</strong> " . $config['dbname'] . "<br>";
    echo "<strong>Username:</strong> " . $config['username'] . "<br>";
    
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<strong style='color:#155724;'>✓ Conexão estabelecida com sucesso!</strong><br>";
    
    // Testa uma query simples
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $result = $stmt->fetch();
    echo "<strong>Total de usuários no sistema:</strong> " . $result['total'] . "<br>";
    
} catch (PDOException $e) {
    echo "<strong style='color:#721c24;'>✗ Erro na conexão:</strong> " . $e->getMessage() . "<br>";
}
echo "</div>";

// Teste 3: Arquivos Importantes
echo "<div style='background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;margin:10px 0;border-radius:5px;font-family:monospace;'>";
echo "<h3>✓ Verificação de Arquivos</h3>";

$arquivos_importantes = [
    'config/db.php' => 'Configuração do Banco',
    'config/debug.php' => 'Configuração de Debug',
    'includes/functions.php' => 'Funções do Sistema',
    'includes/auth.php' => 'Autenticação',
    'includes/session_config.php' => 'Configuração de Sessão',
];

foreach ($arquivos_importantes as $arquivo => $descricao) {
    $existe = file_exists(__DIR__ . '/' . $arquivo);
    $cor = $existe ? '#155724' : '#721c24';
    $status = $existe ? '✓' : '✗';
    echo "<strong style='color:$cor;'>$status $descricao:</strong> $arquivo ";
    echo $existe ? "(encontrado)" : "(NÃO ENCONTRADO)";
    echo "<br>";
}
echo "</div>";

// Teste 4: Extensões PHP
echo "<div style='background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;margin:10px 0;border-radius:5px;font-family:monospace;'>";
echo "<h3>✓ Extensões PHP Necessárias</h3>";

$extensoes = ['pdo', 'pdo_mysql', 'mysqli', 'mbstring', 'curl', 'gd', 'zip'];
foreach ($extensoes as $ext) {
    $carregada = extension_loaded($ext);
    $cor = $carregada ? '#155724' : '#856404';
    $status = $carregada ? '✓' : '⚠';
    echo "<strong style='color:$cor;'>$status $ext:</strong> " . ($carregada ? 'instalada' : 'NÃO instalada') . "<br>";
}
echo "</div>";

// Teste 5: Permissões de Escrita
echo "<div style='background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;margin:10px 0;border-radius:5px;font-family:monospace;'>";
echo "<h3>✓ Permissões de Escrita</h3>";

$pastas_escrita = ['logs', 'uploads', 'assets/media'];
foreach ($pastas_escrita as $pasta) {
    $caminho = __DIR__ . '/' . $pasta;
    if (!file_exists($caminho)) {
        echo "<strong style='color:#856404;'>⚠ $pasta:</strong> pasta não existe<br>";
        continue;
    }
    $gravavel = is_writable($caminho);
    $cor = $gravavel ? '#155724' : '#721c24';
    $status = $gravavel ? '✓' : '✗';
    echo "<strong style='color:$cor;'>$status $pasta:</strong> " . ($gravavel ? 'gravável' : 'SEM permissão de escrita') . "<br>";
}
echo "</div>";

// Teste 6: Variáveis de Servidor
echo "<div style='background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;margin:10px 0;border-radius:5px;font-family:monospace;'>";
echo "<h3>✓ Variáveis de Servidor</h3>";
echo "<strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'não definido') . "<br>";
echo "<strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'não definido') . "<br>";
echo "<strong>Server Name:</strong> " . ($_SERVER['SERVER_NAME'] ?? 'não definido') . "<br>";
echo "<strong>Server Port:</strong> " . ($_SERVER['SERVER_PORT'] ?? 'não definido') . "<br>";
echo "</div>";

echo "<hr>";
echo "<p style='font-family:Arial,sans-serif;color:#666;'>Para desativar o debug após resolver os problemas, edite <code>config/debug.php</code> e altere <code>DEBUG_MODE</code> para <code>false</code>.</p>";
