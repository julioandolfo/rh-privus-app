<?php
/**
 * API: Listar Empresas
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $where = ["1=1"];
    $params = [];
    
    // Filtro por empresas permitidas para RH
    if ($usuario['role'] === 'RH') {
        if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
            $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
            $where[] = "id IN ($placeholders)";
            $params = array_merge($params, $usuario['empresas_ids']);
        }
    }
    
    $sql = "SELECT id, nome_fantasia FROM empresas WHERE " . implode(' AND ', $where) . " ORDER BY nome_fantasia";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $empresas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'empresas' => $empresas
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

