<?php
/**
 * API: Detalhes do Campo do Formulário
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

try {
    $pdo = getDB();
    
    $campo_id = (int)($_GET['id'] ?? 0);
    
    if (empty($campo_id)) {
        throw new Exception('Campo não informado');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM formularios_cultura_campos WHERE id = ?");
    $stmt->execute([$campo_id]);
    $campo = $stmt->fetch();
    
    if (!$campo) {
        throw new Exception('Campo não encontrado');
    }
    
    echo json_encode([
        'success' => true,
        'campo' => $campo
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

