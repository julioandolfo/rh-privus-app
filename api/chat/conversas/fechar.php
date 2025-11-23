<?php
/**
 * API: Fechar Conversa
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/chat_functions.php';

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Não autenticado');
    }
    
    $usuario = $_SESSION['usuario'];
    
    // Apenas RH pode fechar
    if (!in_array($usuario['role'], ['ADMIN', 'RH'])) {
        throw new Exception('Apenas RH pode fechar conversas');
    }
    
    $conversa_id = (int)($_POST['conversa_id'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');
    
    if (empty($conversa_id)) {
        throw new Exception('ID da conversa é obrigatório');
    }
    
    // Fecha conversa
    $resultado = fechar_conversa($conversa_id, $usuario['id'], $motivo);
    
    if (!$resultado['success']) {
        throw new Exception($resultado['error'] ?? 'Erro ao fechar conversa');
    }
    
    // Envia notificação
    require_once __DIR__ . '/../../../includes/chat_notifications.php';
    enviar_notificacao_conversa_fechada($conversa_id);
    
    $response = [
        'success' => true,
        'message' => 'Conversa fechada com sucesso'
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

