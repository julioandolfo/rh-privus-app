<?php
/**
 * API: Listar Setores
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $empresa_id = !empty($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : null;
    
    if (!$empresa_id) {
        throw new Exception('Empresa não informada');
    }
    
    // Verifica permissão
    if ($usuario['role'] === 'RH' && !can_access_empresa($empresa_id)) {
        throw new Exception('Sem permissão');
    }
    
    $stmt = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id = ? ORDER BY nome_setor");
    $stmt->execute([$empresa_id]);
    $setores = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'setores' => $setores
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

