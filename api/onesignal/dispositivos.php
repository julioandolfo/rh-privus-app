<?php
/**
 * API para listar dispositivos registrados do usuário
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false, 'message' => '', 'dispositivos' => []];

// Valida sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    $response['message'] = 'Não autenticado';
    echo json_encode($response);
    exit;
}

$usuario = $_SESSION['usuario'];
$usuario_id = $usuario['id'] ?? null;
$colaborador_id = $usuario['colaborador_id'] ?? null;

try {
    $pdo = getDB();
    
    // Busca dispositivos do usuário
    $query = "
        SELECT 
            id,
            player_id,
            device_type,
            user_agent,
            created_at,
            updated_at
        FROM onesignal_subscriptions
        WHERE (usuario_id = ? OR colaborador_id = ?)
        ORDER BY created_at DESC
    ";
    
    // Usa usuario_id ou colaborador_id (ou ambos se existirem)
    $param1 = $usuario_id ?? $colaborador_id;
    $param2 = $colaborador_id ?? $usuario_id;
    
    // Se ambos existem, usa ambos; senão usa o mesmo valor duas vezes
    if ($usuario_id && $colaborador_id) {
        $params = [$usuario_id, $colaborador_id];
    } else {
        $params = [$param1, $param1];
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    $dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Limpa dados sensíveis e formata
    foreach ($dispositivos as &$disp) {
        unset($disp['player_id']); // Remove player_id por segurança
        // Limita tamanho do user_agent para exibição
        if (!empty($disp['user_agent']) && strlen($disp['user_agent']) > 100) {
            $disp['user_agent'] = substr($disp['user_agent'], 0, 100) . '...';
        }
    }
    
    $response['success'] = true;
    $response['dispositivos'] = $dispositivos;
    $response['total'] = count($dispositivos);
    
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Erro ao buscar dispositivos: ' . $e->getMessage();
    error_log('Erro ao buscar dispositivos OneSignal: ' . $e->getMessage());
}

echo json_encode($response);

