<?php
/**
 * API para listar notificações
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

try {
    $usuario = $_SESSION['usuario'];
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    $limite = intval($_GET['limite'] ?? 20);
    
    // Log para debug
    error_log("API Notificações - Usuario ID: " . ($usuario_id ?? 'NULL') . ", Colaborador ID: " . ($colaborador_id ?? 'NULL') . ", Limite: $limite");
    
    $notificacoes = obter_notificacoes_nao_lidas($usuario_id, $colaborador_id, $limite);
    $total_nao_lidas = contar_notificacoes_nao_lidas($usuario_id, $colaborador_id);
    
    error_log("API Notificações - Retornando: " . count($notificacoes) . " notificações, Total não lidas: $total_nao_lidas");
    
    echo json_encode([
        'success' => true,
        'notificacoes' => $notificacoes,
        'total_nao_lidas' => $total_nao_lidas
    ]);
    
} catch (Exception $e) {
    error_log("API Notificações - Erro: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

