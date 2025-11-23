<?php
/**
 * API: Atualizar Ordem dos Campos
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!has_role(['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    
    $campo_id = (int)($_POST['campo_id'] ?? 0);
    $ordem = (int)($_POST['ordem'] ?? 0);
    
    if (empty($campo_id) || $ordem < 0) {
        throw new Exception('Dados inválidos');
    }
    
    $stmt = $pdo->prepare("UPDATE formularios_cultura_campos SET ordem = ? WHERE id = ?");
    $stmt->execute([$ordem, $campo_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ordem atualizada'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

