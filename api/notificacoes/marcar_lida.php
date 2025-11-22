<?php
/**
 * API para marcar notificação como lida
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notificacoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $notificacao_id = $_POST['notificacao_id'] ?? null;
    
    if (empty($notificacao_id)) {
        throw new Exception('ID da notificação é obrigatório');
    }
    
    $sucesso = marcar_notificacao_lida($notificacao_id);
    
    if ($sucesso) {
        echo json_encode([
            'success' => true,
            'message' => 'Notificação marcada como lida'
        ]);
    } else {
        throw new Exception('Erro ao marcar notificação como lida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

