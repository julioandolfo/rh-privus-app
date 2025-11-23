<?php
/**
 * API: Detalhes da Automação
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!has_role(['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    
    $automacao_id = (int)($_GET['id'] ?? 0);
    
    if (empty($automacao_id)) {
        throw new Exception('Automação não informada');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM kanban_automatizacoes WHERE id = ?");
    $stmt->execute([$automacao_id]);
    $automacao = $stmt->fetch();
    
    if (!$automacao) {
        throw new Exception('Automação não encontrada');
    }
    
    // Decodifica JSONs se existirem
    if (!empty($automacao['condicoes'])) {
        $automacao['condicoes'] = json_decode($automacao['condicoes'], true);
    }
    if (!empty($automacao['configuracao'])) {
        $automacao['configuracao'] = json_decode($automacao['configuracao'], true);
    }
    
    echo json_encode([
        'success' => true,
        'automacao' => $automacao
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

