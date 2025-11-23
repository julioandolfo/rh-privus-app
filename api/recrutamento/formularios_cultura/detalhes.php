<?php
/**
 * API: Detalhes do Formulário de Cultura
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    
    $formulario_id = (int)($_GET['id'] ?? 0);
    
    if (!$formulario_id) {
        throw new Exception('Formulário não informado');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM formularios_cultura WHERE id = ?");
    $stmt->execute([$formulario_id]);
    $formulario = $stmt->fetch();
    
    if (!$formulario) {
        throw new Exception('Formulário não encontrado');
    }
    
    echo json_encode([
        'success' => true,
        'formulario' => $formulario
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

