<?php
/**
 * API: Obter FAQ
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

header('Content-Type: application/json');

// Verifica login
require_login();

// Verifica permissão
if (!has_role(['ADMIN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

$faq_id = (int)($_GET['id'] ?? 0);

if (!$faq_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

$pdo = getDB();

try {
    $stmt = $pdo->prepare("SELECT * FROM faq_manual_conduta WHERE id = ?");
    $stmt->execute([$faq_id]);
    $faq = $stmt->fetch();
    
    if (!$faq) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'FAQ não encontrada']);
        exit;
    }
    
    echo json_encode(['success' => true, 'faq' => $faq]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar FAQ: ' . $e->getMessage()]);
}

