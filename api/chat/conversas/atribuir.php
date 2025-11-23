<?php
/**
 * API: Atribuir Conversa para RH
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
    
    // Apenas RH pode atribuir
    if (!in_array($usuario['role'], ['ADMIN', 'RH'])) {
        throw new Exception('Apenas RH pode atribuir conversas');
    }
    
    $conversa_id = (int)($_POST['conversa_id'] ?? 0);
    $usuario_id = (int)($_POST['usuario_id'] ?? 0);
    
    if (empty($conversa_id)) {
        throw new Exception('ID da conversa é obrigatório');
    }
    
    if (empty($usuario_id)) {
        throw new Exception('ID do usuário é obrigatório');
    }
    
    // Verifica se usuário existe e é RH
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, role FROM usuarios WHERE id = ? AND role IN ('ADMIN', 'RH')");
    $stmt->execute([$usuario_id]);
    $usuario_destino = $stmt->fetch();
    
    if (!$usuario_destino) {
        throw new Exception('Usuário não encontrado ou não é RH');
    }
    
    // Atribui conversa
    $resultado = atribuir_conversa($conversa_id, $usuario_id, $usuario['id']);
    
    if (!$resultado['success']) {
        throw new Exception($resultado['error'] ?? 'Erro ao atribuir conversa');
    }
    
    // Envia notificação
    require_once __DIR__ . '/../../../includes/chat_notifications.php';
    enviar_notificacao_conversa_atribuida($conversa_id, $usuario_id);
    
    $response = [
        'success' => true,
        'message' => 'Conversa atribuída com sucesso'
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

