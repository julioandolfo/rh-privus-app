<?php
/**
 * API: Listar Candidaturas
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/recrutamento_functions.php';

require_login();

try {
    $filtros = [];
    
    if (!empty($_GET['status'])) {
        $statuses = explode(',', $_GET['status']);
        $filtros['status'] = $statuses;
    }
    
    if (!empty($_GET['vaga_id'])) {
        $filtros['vaga_id'] = (int)$_GET['vaga_id'];
    }
    
    $candidaturas = buscar_candidaturas_kanban($filtros);
    
    echo json_encode([
        'success' => true,
        'candidaturas' => $candidaturas
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

