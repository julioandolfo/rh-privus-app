<?php
/**
 * API: Listar Vagas
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $where = ["1=1"];
    $params = [];
    
    // Filtro por status
    if (!empty($_GET['status'])) {
        $where[] = "v.status = ?";
        $params[] = $_GET['status'];
    }
    
    // Permissões por empresa
    if ($usuario['role'] === 'RH') {
        if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
            $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
            $where[] = "v.empresa_id IN ($placeholders)";
            $params = array_merge($params, $usuario['empresas_ids']);
        }
    }
    
    $sql = "
        SELECT v.id, v.titulo, v.status, v.empresa_id
        FROM vagas v
        WHERE " . implode(' AND ', $where) . "
        ORDER BY v.titulo ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vagas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'vagas' => $vagas
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

