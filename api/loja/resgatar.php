<?php
/**
 * API para resgatar produto na loja
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $usuario = $_SESSION['usuario'];
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    if (!$colaborador_id) {
        throw new Exception('Usuário não vinculado a um colaborador');
    }
    
    $produto_id = intval($_POST['produto_id'] ?? 0);
    $quantidade = intval($_POST['quantidade'] ?? 1);
    $observacao = trim($_POST['observacao'] ?? '');
    
    if ($produto_id <= 0) {
        throw new Exception('Produto inválido');
    }
    
    if ($quantidade <= 0) {
        throw new Exception('Quantidade inválida');
    }
    
    // Processa o resgate
    $resultado = loja_resgatar($colaborador_id, $produto_id, $quantidade, $observacao);
    
    echo json_encode($resultado);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
