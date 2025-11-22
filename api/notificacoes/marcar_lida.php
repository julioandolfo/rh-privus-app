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
    // Log de entrada
    error_log("=== MARCAR NOTIFICAÇÃO COMO LIDA ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("Method: " . $_SERVER['REQUEST_METHOD']);
    
    $notificacao_id = $_POST['notificacao_id'] ?? null;
    
    if (empty($notificacao_id)) {
        error_log("ERRO: ID da notificação vazio");
        throw new Exception('ID da notificação é obrigatório');
    }
    
    $usuario = $_SESSION['usuario'];
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    error_log("Notificação ID: $notificacao_id");
    error_log("Usuario ID: " . ($usuario_id ?? 'NULL'));
    error_log("Colaborador ID: " . ($colaborador_id ?? 'NULL'));
    
    $sucesso = marcar_notificacao_lida($notificacao_id, $usuario_id, $colaborador_id);
    
    error_log("Resultado: " . ($sucesso ? 'SUCESSO' : 'FALHOU'));
    
    if ($sucesso) {
        echo json_encode([
            'success' => true,
            'message' => 'Notificação marcada como lida'
        ]);
    } else {
        // Verifica se a notificação existe
        $pdo = getDB();
        $stmt_check = $pdo->prepare("SELECT id, lida, usuario_id, colaborador_id FROM notificacoes_sistema WHERE id = ?");
        $stmt_check->execute([$notificacao_id]);
        $notif = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$notif) {
            throw new Exception('Notificação não encontrada.');
        } else if ($notif['lida'] == 1) {
            throw new Exception('Notificação já está marcada como lida.');
        } else {
            // Verifica se pertence ao usuário
            $where_conditions = [];
            $check_params = [];
            
            if ($usuario_id && $notif['usuario_id'] == $usuario_id) {
                // OK
            } else if ($colaborador_id && $notif['colaborador_id'] == $colaborador_id) {
                // OK
            } else {
                throw new Exception('Esta notificação não pertence a você.');
            }
            
            throw new Exception('Erro ao atualizar notificação. Tente novamente.');
        }
    }
    
} catch (Exception $e) {
    error_log("Erro ao marcar notificação como lida: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

