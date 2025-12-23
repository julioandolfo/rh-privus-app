<?php
/**
 * API: Listar histórico do onboarding
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $onboarding_id = (int)($_GET['onboarding_id'] ?? 0);
    
    if (!$onboarding_id) {
        throw new Exception('Onboarding não informado');
    }
    
    $stmt = $pdo->prepare("
        SELECT h.*, u.nome as usuario_nome
        FROM onboarding_historico h
        INNER JOIN usuarios u ON h.usuario_id = u.id
        WHERE h.onboarding_id = ?
        ORDER BY h.data_registro DESC
    ");
    $stmt->execute([$onboarding_id]);
    $historicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'historicos' => $historicos
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

