<?php
/**
 * API para marcar todas as notificações como lidas
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
    $usuario = $_SESSION['usuario'];
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    $pdo = getDB();
    
    $where = [];
    $params = [];
    
    if ($usuario_id) {
        $where[] = "usuario_id = ?";
        $params[] = $usuario_id;
    } else if ($colaborador_id) {
        $where[] = "colaborador_id = ?";
        $params[] = $colaborador_id;
    } else {
        throw new Exception('Usuário ou colaborador não identificado');
    }
    
    $where_sql = implode(' AND ', $where);
    
    $stmt = $pdo->prepare("
        UPDATE notificacoes_sistema 
        SET lida = 1 
        WHERE $where_sql AND lida = 0
    ");
    $stmt->execute($params);
    
    $total_marcadas = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "$total_marcadas notificação(ões) marcada(s) como lida(s)",
        'total_marcadas' => $total_marcadas
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

