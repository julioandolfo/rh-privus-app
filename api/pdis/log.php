<?php
/**
 * API para salvar logs de debug do PDI
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['tipo']) || !isset($input['dados'])) {
        throw new Exception('Dados inválidos');
    }
    
    $tipo = $input['tipo'];
    $dados = $input['dados'];
    
    // Cria diretório de logs se não existir
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/pdi.log';
    
    // Formata log
    $logEntry = sprintf(
        "[%s] %s: %s\n",
        date('Y-m-d H:i:s'),
        $tipo,
        json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    
    // Adiciona ao arquivo de log
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    echo json_encode([
        'success' => true,
        'message' => 'Log salvo com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

