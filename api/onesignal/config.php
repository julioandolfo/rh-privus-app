<?php
/**
 * Retorna configurações do OneSignal para o frontend
 */

// Tratamento de erros melhorado
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = getDB();
    
    // Verifica se tabela existe
    try {
        $stmt = $pdo->query("SELECT app_id, safari_web_id FROM onesignal_config ORDER BY id DESC LIMIT 1");
        $config = $stmt->fetch();
    } catch (PDOException $e) {
        // Tabela não existe ainda
        echo json_encode([
            'appId' => null,
            'safariWebId' => null,
            'message' => 'Tabela onesignal_config não existe. Execute a migração primeiro.',
            'error' => 'Table not found'
        ]);
        exit;
    }
    
    if (!$config) {
        echo json_encode([
            'appId' => null,
            'safariWebId' => null,
            'message' => 'OneSignal não configurado. Configure em pages/configuracoes_onesignal.php'
        ]);
        exit;
    }
    
    echo json_encode([
        'appId' => $config['app_id'],
        'safariWebId' => $config['safari_web_id']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

