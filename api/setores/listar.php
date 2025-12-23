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
    
    // Verifica se existe coluna status na tabela setores
    $stmt_check = $pdo->query("SHOW COLUMNS FROM setores LIKE 'status'");
    $has_status_column = $stmt_check->fetch() !== false;
    
    if ($has_status_column) {
        $stmt = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id = ? AND (status = 'ativo' OR status IS NULL) ORDER BY nome_setor");
    } else {
        $stmt = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id = ? ORDER BY nome_setor");
    }
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

