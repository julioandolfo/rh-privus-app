<?php
/**
 * API: Marcar FAQ como útil/não útil
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

if (!$data || !isset($data['faq_id']) || !isset($data['util'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$faq_id = (int)$data['faq_id'];
$util = (bool)$data['util'];

try {
    marcar_faq_util($faq_id, $util);
    echo json_encode(['success' => true, 'message' => 'Feedback registrado']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar feedback: ' . $e->getMessage()]);
}

