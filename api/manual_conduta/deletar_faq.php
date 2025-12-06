<?php
/**
 * API: Deletar FAQ
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

// Verifica método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Lê dados JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['faq_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da FAQ não informado']);
    exit;
}

$faq_id = (int)$data['faq_id'];
$pdo = getDB();

try {
    // Verifica se FAQ existe
    $stmt = $pdo->prepare("SELECT id FROM faq_manual_conduta WHERE id = ?");
    $stmt->execute([$faq_id]);
    if (!$stmt->fetch()) {
        throw new Exception('FAQ não encontrada');
    }
    
    // Deleta FAQ (cascade deleta histórico)
    $stmt = $pdo->prepare("DELETE FROM faq_manual_conduta WHERE id = ?");
    $stmt->execute([$faq_id]);
    
    echo json_encode(['success' => true, 'message' => 'FAQ deletada com sucesso']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar FAQ: ' . $e->getMessage()]);
}

