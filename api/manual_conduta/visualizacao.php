<?php
/**
 * API: Registrar visualização do manual/FAQ
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/manual_conduta_functions.php';

header('Content-Type: application/json');

// Verifica login
require_login();

// Verifica método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Lê dados JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['tipo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$tipo = $data['tipo']; // 'manual' ou 'faq'
$faq_id = isset($data['faq_id']) ? (int)$data['faq_id'] : null;

try {
    registrar_visualizacao_manual($tipo, $faq_id);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar visualização: ' . $e->getMessage()]);
}

