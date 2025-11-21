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
    
    // Verifica e cria a tabela se não existir
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'onesignal_config'");
        if ($stmt->rowCount() == 0) {
            // Cria a tabela automaticamente
            $pdo->exec("
                CREATE TABLE onesignal_config (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    app_id VARCHAR(255) NOT NULL,
                    rest_api_key VARCHAR(255) NOT NULL,
                    safari_web_id VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (PDOException $e) {
        // Ignora se já existe
        if (strpos($e->getMessage(), 'already exists') === false && 
            strpos($e->getMessage(), '1050') === false) {
            throw $e;
        }
    }
    
    // Busca configurações
    try {
        $stmt = $pdo->query("SELECT app_id, safari_web_id FROM onesignal_config ORDER BY id DESC LIMIT 1");
        $config = $stmt->fetch();
    } catch (PDOException $e) {
        // Se ainda der erro, retorna sem configuração
        echo json_encode([
            'appId' => null,
            'safariWebId' => null,
            'message' => 'Erro ao buscar configurações do OneSignal. Verifique o banco de dados.',
            'error' => $e->getMessage()
        ]);
        exit;
    }
    
    if (!$config || empty($config['app_id'])) {
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

