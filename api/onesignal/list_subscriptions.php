<?php
/**
 * API para listar subscriptions do usuário logado
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    // Busca subscriptions do usuário
    $stmt = $pdo->prepare("
        SELECT id, usuario_id, colaborador_id, player_id, device_type, user_agent, created_at, updated_at
        FROM onesignal_subscriptions
        WHERE usuario_id = ? OR colaborador_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$usuario_id, $colaborador_id]);
    $subscriptions = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'subscriptions' => $subscriptions,
        'count' => count($subscriptions)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

