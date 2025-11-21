<?php
/**
 * Teste de envio de notificação push
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/push_notifications.php';

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

header('Content-Type: application/json');

// Para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = getDB();
    
    echo "<h1>Teste de Envio de Notificação Push</h1>";
    
    // 1. Verifica configuração do OneSignal
    echo "<h2>1. Configuração OneSignal</h2>";
    $stmt = $pdo->query("SELECT app_id, rest_api_key FROM onesignal_config ORDER BY id DESC LIMIT 1");
    $config = $stmt->fetch();
    
    if (!$config) {
        echo "<p style='color: red;'>❌ OneSignal não configurado!</p>";
        exit;
    }
    
    echo "<p style='color: green;'>✅ OneSignal configurado</p>";
    echo "<pre>";
    echo "App ID: " . $config['app_id'] . "\n";
    echo "REST API Key: " . substr($config['rest_api_key'], 0, 10) . "..." . "\n";
    echo "</pre>";
    
    // 2. Lista subscriptions
    echo "<h2>2. Subscriptions Disponíveis</h2>";
    $stmt = $pdo->query("
        SELECT 
            os.*,
            u.nome as usuario_nome,
            u.email as usuario_email,
            c.nome_completo as colaborador_nome
        FROM onesignal_subscriptions os
        LEFT JOIN usuarios u ON os.usuario_id = u.id
        LEFT JOIN colaboradores c ON os.colaborador_id = c.id
        ORDER BY os.created_at DESC
        LIMIT 10
    ");
    $subscriptions = $stmt->fetchAll();
    
    if (empty($subscriptions)) {
        echo "<p style='color: red;'>❌ Nenhuma subscription encontrada!</p>";
        exit;
    }
    
    echo "<p style='color: green;'>✅ " . count($subscriptions) . " subscription(s) encontrada(s)</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Usuário</th><th>Email</th><th>Player ID</th><th>Device</th><th>Criado em</th></tr>";
    foreach ($subscriptions as $sub) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($sub['id']) . "</td>";
        echo "<td>" . htmlspecialchars($sub['usuario_nome'] ?: $sub['colaborador_nome'] ?: 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($sub['usuario_email'] ?: 'N/A') . "</td>";
        echo "<td style='font-size: 10px;'>" . htmlspecialchars($sub['player_id']) . "</td>";
        echo "<td>" . htmlspecialchars($sub['device_type']) . "</td>";
        echo "<td>" . htmlspecialchars($sub['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 3. Teste de envio
    if (isset($_POST['testar_envio'])) {
        echo "<hr><h2>3. Resultado do Teste</h2>";
        
        $usuario_id = $_POST['usuario_id'] ?? null;
        $titulo = $_POST['titulo'] ?? 'Teste de Notificação';
        $mensagem = $_POST['mensagem'] ?? 'Esta é uma notificação de teste';
        
        echo "<p><strong>Enviando para usuário ID:</strong> " . htmlspecialchars($usuario_id) . "</p>";
        echo "<p><strong>Título:</strong> " . htmlspecialchars($titulo) . "</p>";
        echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($mensagem) . "</p>";
        
        try {
            $resultado = enviar_push_usuario($usuario_id, $titulo, $mensagem);
            
            echo "<h3>Resultado:</h3>";
            echo "<pre>";
            print_r($resultado);
            echo "</pre>";
            
            if ($resultado['success']) {
                echo "<p style='color: green; font-weight: bold;'>✅ Notificação enviada com sucesso!</p>";
            } else {
                echo "<p style='color: red; font-weight: bold;'>❌ Erro ao enviar: " . htmlspecialchars($resultado['message']) . "</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red; font-weight: bold;'>❌ Exceção: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<pre>";
            echo $e->getTraceAsString();
            echo "</pre>";
        }
    }
    
    // Formulário de teste
    echo "<hr><h2>Testar Envio</h2>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='testar_envio' value='1'>";
    echo "<p><label>Usuário ID: <input type='number' name='usuario_id' value='" . ($_SESSION['usuario']['id'] ?? 1) . "' required></label></p>";
    echo "<p><label>Título: <input type='text' name='titulo' value='Teste' required style='width: 300px;'></label></p>";
    echo "<p><label>Mensagem: <textarea name='mensagem' required style='width: 300px; height: 80px;'>Esta é uma mensagem de teste</textarea></label></p>";
    echo "<p><button type='submit' style='padding: 10px 20px; background: #009ef7; color: white; border: none; border-radius: 5px; cursor: pointer;'>Enviar Notificação de Teste</button></p>";
    echo "</form>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Erro fatal: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>";
    echo $e->getTraceAsString();
    echo "</pre>";
}
?>

