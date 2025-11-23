<?php
/**
 * API: Excluir Campo do Formulário
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
    
    $campo_id = (int)($_GET['id'] ?? 0);
    
    if (empty($campo_id)) {
        throw new Exception('Campo não informado');
    }
    
    $stmt = $pdo->prepare("DELETE FROM formularios_cultura_campos WHERE id = ?");
    $stmt->execute([$campo_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Campo excluído com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

