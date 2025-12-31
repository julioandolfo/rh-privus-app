<?php
/**
 * API para deletar manual individual
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];

// Apenas ADMIN, RH e GESTOR podem deletar
if (!in_array($usuario['role'], ['ADMIN', 'RH', 'GESTOR'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para deletar manuais']);
    exit;
}

$manual_id = $_POST['manual_id'] ?? 0;

if (empty($manual_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do manual não informado']);
    exit;
}

try {
    $pdo = getDB();
    
    // Verifica se o manual existe
    $stmt = $pdo->prepare("SELECT id FROM manuais_individuais WHERE id = ?");
    $stmt->execute([$manual_id]);
    $manual = $stmt->fetch();
    
    if (!$manual) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Manual não encontrado']);
        exit;
    }
    
    // Deleta relacionamentos primeiro (CASCADE já faz isso, mas é bom ser explícito)
    $stmt = $pdo->prepare("DELETE FROM manuais_individuais_colaboradores WHERE manual_id = ?");
    $stmt->execute([$manual_id]);
    
    // Deleta o manual
    $stmt = $pdo->prepare("DELETE FROM manuais_individuais WHERE id = ?");
    $stmt->execute([$manual_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Manual deletado com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao deletar manual: ' . $e->getMessage()
    ]);
}
