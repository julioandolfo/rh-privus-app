<?php
/**
 * API para buscar bônus de um colaborador em um fechamento específico
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$fechamento_id = (int)($_GET['fechamento_id'] ?? 0);
$colaborador_id = (int)($_GET['colaborador_id'] ?? 0);

if (empty($fechamento_id) || empty($colaborador_id)) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

try {
    $pdo = getDB();
    
    // Busca bônus salvos no fechamento
    $stmt = $pdo->prepare("
        SELECT fb.*, tb.nome as tipo_bonus_nome
        FROM fechamentos_pagamento_bonus fb
        INNER JOIN tipos_bonus tb ON fb.tipo_bonus_id = tb.id
        WHERE fb.fechamento_pagamento_id = ? AND fb.colaborador_id = ?
    ");
    $stmt->execute([$fechamento_id, $colaborador_id]);
    $bonus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $bonus]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar bônus: ' . $e->getMessage()]);
}

