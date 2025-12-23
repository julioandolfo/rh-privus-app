<?php
/**
 * API: Excluir histórico do onboarding
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        throw new Exception('ID não informado');
    }
    
    // Verifica se existe e se o usuário pode excluir (criador ou ADMIN)
    $stmt = $pdo->prepare("SELECT * FROM onboarding_historico WHERE id = ?");
    $stmt->execute([$id]);
    $historico = $stmt->fetch();
    
    if (!$historico) {
        throw new Exception('Registro não encontrado');
    }
    
    // Apenas o criador ou ADMIN pode excluir
    if ($historico['usuario_id'] != $usuario['id'] && $usuario['role'] !== 'ADMIN') {
        throw new Exception('Você não tem permissão para excluir este registro');
    }
    
    $stmt = $pdo->prepare("DELETE FROM onboarding_historico WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Registro excluído com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

