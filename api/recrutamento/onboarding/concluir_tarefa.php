<?php
/**
 * API: Concluir Tarefa de Onboarding
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    
    $tarefa_id = (int)($_GET['id'] ?? 0);
    
    if (empty($tarefa_id)) {
        throw new Exception('Tarefa não informada');
    }
    
    // Atualiza tarefa
    $stmt = $pdo->prepare("
        UPDATE onboarding_tarefas 
        SET status = 'concluida', data_conclusao = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$tarefa_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tarefa concluída com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

