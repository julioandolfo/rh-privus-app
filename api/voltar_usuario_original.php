<?php
/**
 * API para voltar ao usuário original após impersonation
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session_config.php';

header('Content-Type: application/json');

iniciar_sessao_30_dias();

if (!isset($_SESSION['impersonating']) || !isset($_SESSION['usuario_original'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Não há impersonation ativa']);
    exit;
}

try {
    // Restaura sessão do usuário original
    $_SESSION['usuario'] = $_SESSION['usuario_original'];
    unset($_SESSION['usuario_original']);
    unset($_SESSION['impersonating']);
    
    // Atualiza timestamp
    $_SESSION['ultima_atividade'] = time();
    
    echo json_encode([
        'success' => true,
        'message' => 'Voltou ao seu usuário original',
        'redirect' => 'dashboard.php'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao voltar: ' . $e->getMessage()
    ]);
}
