<?php
/**
 * API para registrar subscription do OneSignal
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método não permitido';
    echo json_encode($response);
    exit;
}

// Valida sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    $response['message'] = 'Não autenticado. Faça login primeiro.';
    $response['debug'] = [
        'session_id' => session_id(),
        'has_session' => isset($_SESSION),
        'session_data' => $_SESSION ?? []
    ];
    echo json_encode($response);
    exit;
}

$usuario = $_SESSION['usuario'];
$input = json_decode(file_get_contents('php://input'), true);
$player_id = $input['player_id'] ?? '';

if (empty($player_id)) {
    $response['message'] = 'Player ID é obrigatório';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getDB();
    
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    // Se colaborador não tem usuário vinculado, busca
    if (!$usuario_id && $colaborador_id) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE colaborador_id = ? LIMIT 1");
        $stmt->execute([$colaborador_id]);
        $user_data = $stmt->fetch();
        $usuario_id = $user_data['id'] ?? null;
    }
    
    // Detecta tipo de dispositivo
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device_type = 'web';
    if (preg_match('/Mobile|Android|iPhone|iPad/', $user_agent)) {
        $device_type = 'mobile';
    }
    
    // Verifica se já existe subscription para este player_id
    $stmt = $pdo->prepare("SELECT id FROM onesignal_subscriptions WHERE player_id = ?");
    $stmt->execute([$player_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Atualiza subscription existente
        $stmt = $pdo->prepare("
            UPDATE onesignal_subscriptions 
            SET usuario_id = ?, colaborador_id = ?, device_type = ?, user_agent = ?, updated_at = NOW()
            WHERE player_id = ?
        ");
        $stmt->execute([
            $usuario_id,
            $colaborador_id,
            $device_type,
            $user_agent,
            $player_id
        ]);
    } else {
        // Cria nova subscription
        $stmt = $pdo->prepare("
            INSERT INTO onesignal_subscriptions (usuario_id, colaborador_id, player_id, device_type, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $usuario_id,
            $colaborador_id,
            $player_id,
            $device_type,
            $user_agent
        ]);
    }
    
    $response['success'] = true;
    $response['message'] = 'Subscription registrada com sucesso';
    $response['data'] = [
        'usuario_id' => $usuario_id,
        'colaborador_id' => $colaborador_id,
        'player_id' => $player_id,
        'device_type' => $device_type
    ];
    
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Erro ao registrar subscription: ' . $e->getMessage();
    $response['error'] = [
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ];
    error_log('Erro ao registrar subscription OneSignal: ' . $e->getMessage());
}

echo json_encode($response);

