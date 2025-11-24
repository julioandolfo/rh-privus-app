<?php
/**
 * API para testar conexão com Autentique
 */

// Desabilita exibição de erros para não quebrar o JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/autentique_service.php';

// Define header JSON antes de qualquer output
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

try {
    // Verifica se há configuração
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM autentique_config WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
    $config = $stmt->fetch();
    
    if (!$config) {
        throw new Exception('Autentique não configurado. Configure a API Key em Configurações > Autentique.');
    }
    
    if (empty($config['api_key'])) {
        throw new Exception('API Key não configurada. Configure em Configurações > Autentique.');
    }
    
    $service = new AutentiqueService();
    $usuario = $service->buscarUsuarioAtual();
    
    if ($usuario) {
        echo json_encode([
            'success' => true,
            'message' => 'Conexão estabelecida com sucesso! Usuário: ' . ($usuario['name'] ?? $usuario['email'] ?? 'N/A'),
            'ambiente' => $config['sandbox'] ? 'Sandbox' : 'Produção'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível obter informações do usuário. Verifique se a API Key está correta.'
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getFile() . ':' . $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro fatal: ' . $e->getMessage(),
        'error' => $e->getFile() . ':' . $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

