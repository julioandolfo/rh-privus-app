<?php
/**
 * Script de Teste para API de Feedback
 * 
 * Uso:
 * 1. Faça login no sistema
 * 2. Acesse: http://localhost/rh-privus/test_api_feedback.php
 * 3. Substitua os valores de teste pelos seus dados
 */

session_start();

// Verifica se está logado
if (!isset($_SESSION['usuario'])) {
    die('❌ ERRO: Você precisa estar logado no sistema. <a href="login.php">Fazer login</a>');
}

echo '<h1>Teste da API de Feedback</h1>';
echo '<pre>';

// Mostra dados da sessão
echo "=== DADOS DA SESSÃO ===\n";
echo "Usuário ID: " . ($_SESSION['usuario']['id'] ?? 'NULL') . "\n";
echo "Colaborador ID: " . ($_SESSION['usuario']['colaborador_id'] ?? 'NULL') . "\n";
echo "Nome: " . ($_SESSION['usuario']['nome'] ?? 'NULL') . "\n";
echo "Role: " . ($_SESSION['usuario']['role'] ?? 'NULL') . "\n";
echo "\n";

// Busca colaboradores disponíveis
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/select_colaborador.php';

$pdo = getDB();
$colaboradores = get_colaboradores_disponiveis($pdo, $_SESSION['usuario']);

echo "=== COLABORADORES DISPONÍVEIS ===\n";
if (empty($colaboradores)) {
    echo "⚠️ Nenhum colaborador disponível para enviar feedback.\n";
    echo "Verifique se:\n";
    echo "- Existem colaboradores ativos no sistema\n";
    echo "- Você tem permissão para acessá-los\n";
    echo "- Existem usuários sem colaborador vinculado\n";
} else {
    echo "Total: " . count($colaboradores) . " pessoa(s)\n\n";
    foreach ($colaboradores as $colab) {
        echo "ID: " . $colab['id'] . " | Nome: " . $colab['nome_completo'] . "\n";
    }
}

echo "\n=== TESTE SIMULADO ===\n";
echo "Para testar manualmente, use este CURL:\n\n";

if (!empty($colaboradores)) {
    $primeiro = $colaboradores[0];
    $destinatario_id = $primeiro['id'];
    
    echo "curl -X POST 'http://localhost/rh-privus/api/feedback/enviar.php' \\\n";
    echo "  -H 'Content-Type: application/x-www-form-urlencoded' \\\n";
    echo "  -b 'PHPSESSID=" . session_id() . "' \\\n";
    echo "  -d 'destinatario_colaborador_id=" . $destinatario_id . "' \\\n";
    echo "  -d 'conteudo=Teste de feedback via API' \\\n";
    echo "  -d 'template_id=0' \\\n";
    echo "  -d 'anonimo=0' \\\n";
    echo "  -d 'presencial=0' \\\n";
    echo "  -d 'request_id=test-" . time() . "'\n";
}

echo "\n=== VERIFICAR ERROS DO PHP ===\n";
echo "Verifique os logs em:\n";
echo "- logs/feedback.log (logs da aplicação)\n";
echo "- C:/laragon/www/rh-privus/logs/php_errors.log (erros do PHP)\n";

echo "\n=== PRÓXIMOS PASSOS ===\n";
echo "1. Copie o comando CURL acima\n";
echo "2. Execute no terminal/PowerShell\n";
echo "3. Verifique a resposta\n";
echo "4. Se houver erro, me envie a mensagem completa\n";

echo '</pre>';

// Botão para voltar
echo '<br><a href="pages/feedback_enviar.php" style="padding: 10px 20px; background: #009ef7; color: white; text-decoration: none; border-radius: 4px;">Voltar para Enviar Feedback</a>';
?>
