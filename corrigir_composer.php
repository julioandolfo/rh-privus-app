<?php
/**
 * Script para corrigir problemas do Composer
 * Execute este script APÓS rodar o comando: composer install
 */

echo "<h1>🔧 Correção de Dependências do Composer</h1>";
echo "<hr>";

$vendorPath = __DIR__ . '/vendor';
$composerJsonPath = __DIR__ . '/composer.json';

// Verifica se composer.json existe
if (!file_exists($composerJsonPath)) {
    echo "<div style='background:#f8d7da;color:#721c24;padding:15px;margin:10px;border-radius:5px;'>";
    echo "<strong>❌ ERRO:</strong> Arquivo composer.json não encontrado!<br>";
    echo "Você precisa de um arquivo composer.json na raiz do projeto.";
    echo "</div>";
    exit;
}

echo "<div style='background:#d1ecf1;color:#0c5460;padding:15px;margin:10px;border-radius:5px;'>";
echo "<strong>✓ composer.json encontrado</strong><br>";
echo "</div>";

// Verifica se pasta vendor existe
if (!file_exists($vendorPath)) {
    echo "<div style='background:#fff3cd;color:#856404;padding:15px;margin:10px;border-radius:5px;'>";
    echo "<strong>⚠ ATENÇÃO:</strong> Pasta vendor não existe!<br><br>";
    echo "<strong>Execute os seguintes comandos no terminal:</strong><br>";
    echo "<code style='background:#f8f9fa;padding:5px;border-radius:3px;display:block;margin-top:10px;'>";
    echo "cd " . __DIR__ . "<br>";
    echo "composer install<br>";
    echo "# OU se não funcionar:<br>";
    echo "composer update<br>";
    echo "</code>";
    echo "</div>";
    exit;
}

echo "<div style='background:#d1ecf1;color:#0c5460;padding:15px;margin:10px;border-radius:5px;'>";
echo "<strong>✓ Pasta vendor encontrada</strong><br>";
echo "</div>";

// Lista arquivos problemáticos
$arquivosNecessarios = [
    'vendor/autoload.php',
    'vendor/composer/autoload_real.php',
    'vendor/symfony/polyfill-mbstring/bootstrap.php',
    'vendor/phpmailer/phpmailer/src/PHPMailer.php',
    'vendor/mpdf/mpdf/src/Mpdf.php',
];

$arquivosFaltando = [];
foreach ($arquivosNecessarios as $arquivo) {
    $caminhoCompleto = __DIR__ . '/' . $arquivo;
    if (!file_exists($caminhoCompleto)) {
        $arquivosFaltando[] = $arquivo;
    }
}

if (empty($arquivosFaltando)) {
    echo "<div style='background:#d4edda;color:#155724;padding:15px;margin:10px;border-radius:5px;'>";
    echo "<strong>✅ SUCESSO!</strong> Todos os arquivos necessários estão presentes.<br>";
    echo "O sistema deve funcionar corretamente agora.";
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da;color:#721c24;padding:15px;margin:10px;border-radius:5px;'>";
    echo "<strong>❌ ERRO:</strong> Arquivos faltando:<br><br>";
    foreach ($arquivosFaltando as $arquivo) {
        echo "• $arquivo<br>";
    }
    echo "<br><strong>Solução:</strong><br>";
    echo "<code style='background:#f8f9fa;padding:5px;border-radius:3px;display:block;margin-top:10px;'>";
    echo "cd " . __DIR__ . "<br>";
    echo "rm -rf vendor<br>";
    echo "rm composer.lock<br>";
    echo "composer install<br>";
    echo "</code>";
    echo "</div>";
}

echo "<hr>";
echo "<h3>📋 Instruções Detalhadas</h3>";
echo "<div style='background:#e7f3ff;color:#004085;padding:15px;margin:10px;border-radius:5px;font-family:monospace;'>";
echo "<strong>No servidor de produção, execute:</strong><br><br>";
echo "<code style='background:#f8f9fa;padding:5px;border-radius:3px;display:block;'>";
echo "1. Acesse o diretório do projeto via SSH<br>";
echo "   cd /home/privus/public_html/rh<br><br>";
echo "2. Remova a pasta vendor (pode estar corrompida)<br>";
echo "   rm -rf vendor<br><br>";
echo "3. Remova o arquivo composer.lock<br>";
echo "   rm composer.lock<br><br>";
echo "4. Reinstale as dependências<br>";
echo "   composer install --no-dev --optimize-autoloader<br><br>";
echo "5. Se composer não estiver instalado, use:<br>";
echo "   php composer.phar install --no-dev --optimize-autoloader<br><br>";
echo "6. Verifique as permissões<br>";
echo "   chmod -R 755 vendor<br>";
echo "</code>";
echo "</div>";
?>
