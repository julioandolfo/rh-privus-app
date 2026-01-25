<?php
/**
 * API de wishlist (lista de desejos)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/loja_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];
$colaborador_id = $usuario['colaborador_id'] ?? null;

if (!$colaborador_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Usuário não vinculado a um colaborador']);
    exit;
}

try {
    $action = $_REQUEST['action'] ?? 'listar';
    
    switch ($action) {
        case 'listar':
            $wishlist = loja_get_wishlist($colaborador_id);
            echo json_encode(['success' => true, 'wishlist' => $wishlist]);
            break;
            
        case 'toggle':
            $produto_id = intval($_POST['produto_id'] ?? 0);
            if ($produto_id <= 0) {
                throw new Exception('Produto inválido');
            }
            
            $resultado = loja_toggle_wishlist($colaborador_id, $produto_id);
            echo json_encode($resultado);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
