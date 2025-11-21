<?php
/**
 * API para registrar subscription de push notification
 * Suporta tanto usuario_id quanto colaborador_id
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

// Valida token JWT ou sessão
$usuario = null;
$colaborador_id = null;

// Tenta obter usuário da sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['usuario'])) {
    $usuario = $_SESSION['usuario'];
    $colaborador_id = $usuario['colaborador_id'] ?? null;
}

if (!$usuario) {
    http_response_code(401);
    $response['message'] = 'Não autenticado';
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$endpoint = $input['endpoint'] ?? '';
$p256dh = $input['keys']['p256dh'] ?? '';
$auth = $input['keys']['auth'] ?? '';

if (empty($endpoint) || empty($p256dh) || empty($auth)) {
    $response['message'] = 'Dados de subscription inválidos';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getDB();
    
    $usuario_id = $usuario['id'] ?? null;
    
    // Se colaborador não tem usuário vinculado, busca ou cria
    if (!$usuario_id && $colaborador_id) {
        // Tenta encontrar usuário vinculado ao colaborador
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE colaborador_id = ? LIMIT 1");
        $stmt->execute([$colaborador_id]);
        $user_data = $stmt->fetch();
        $usuario_id = $user_data['id'] ?? null;
    }
    
    // Verifica se já existe subscription para este endpoint
    $stmt = $pdo->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
    $stmt->execute([$endpoint]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Atualiza subscription existente
        $stmt = $pdo->prepare("
            UPDATE push_subscriptions 
            SET usuario_id = ?, colaborador_id = ?, p256dh = ?, auth = ?, user_agent = ?, updated_at = NOW()
            WHERE endpoint = ?
        ");
        $stmt->execute([
            $usuario_id,
            $colaborador_id,
            $p256dh,
            $auth,
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $endpoint
        ]);
    } else {
        // Cria nova subscription
        $stmt = $pdo->prepare("
            INSERT INTO push_subscriptions (usuario_id, colaborador_id, endpoint, p256dh, auth, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $usuario_id,
            $colaborador_id,
            $endpoint,
            $p256dh,
            $auth,
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    $response['success'] = true;
    $response['message'] = 'Subscription registrada com sucesso';
    $response['data'] = [
        'usuario_id' => $usuario_id,
        'colaborador_id' => $colaborador_id
    ];
    
} catch (Exception $e) {
    $response['message'] = 'Erro ao registrar subscription: ' . $e->getMessage();
}

echo json_encode($response);

