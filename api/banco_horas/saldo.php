<?php
/**
 * API: Consultar Saldo do Banco de Horas
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/banco_horas_functions.php';

require_login();

$colaborador_id = $_GET['colaborador_id'] ?? null;

if (empty($colaborador_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'Colaborador não informado'
    ]);
    exit;
}

$colaborador_id = (int)$colaborador_id;

// Verifica permissão
if (!can_access_colaborador($colaborador_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'Sem permissão para acessar este colaborador'
    ]);
    exit;
}

try {
    $saldo = get_saldo_banco_horas($colaborador_id);
    
    echo json_encode([
        'success' => true,
        'data' => $saldo
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

