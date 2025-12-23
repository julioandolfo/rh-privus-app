<?php
/**
 * API: Listar Cargos
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $setor_id = !empty($_GET['setor_id']) ? (int)$_GET['setor_id'] : null;
    
    if (!$setor_id) {
        throw new Exception('Setor não informado');
    }
    
    // Verifica permissão através do setor
    $stmt = $pdo->prepare("SELECT empresa_id FROM setores WHERE id = ?");
    $stmt->execute([$setor_id]);
    $setor = $stmt->fetch();
    
    if (!$setor) {
        throw new Exception('Setor não encontrado');
    }
    
    if ($usuario['role'] === 'RH' && !can_access_empresa($setor['empresa_id'])) {
        throw new Exception('Sem permissão');
    }
    
    $stmt = $pdo->prepare("SELECT id, nome_cargo FROM cargos WHERE setor_id = ? ORDER BY nome_cargo");
    $stmt->execute([$setor_id]);
    $cargos = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'cargos' => $cargos
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

