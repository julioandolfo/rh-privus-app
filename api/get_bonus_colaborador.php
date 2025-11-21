<?php
/**
 * API para buscar bônus de um colaborador
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$colaborador_id = $_GET['colaborador_id'] ?? 0;

if (empty($colaborador_id)) {
    echo json_encode(['success' => false, 'error' => 'ID do colaborador não informado']);
    exit;
}

try {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT cb.*, tb.nome as tipo_bonus_nome, tb.descricao as tipo_bonus_descricao
        FROM colaboradores_bonus cb
        INNER JOIN tipos_bonus tb ON cb.tipo_bonus_id = tb.id
        WHERE cb.colaborador_id = ?
        AND (
            cb.data_fim IS NULL 
            OR cb.data_fim >= CURDATE()
            OR (cb.data_inicio IS NULL AND cb.data_fim IS NULL)
        )
        ORDER BY tb.nome
    ");
    $stmt->execute([$colaborador_id]);
    $bonus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $bonus]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar bônus: ' . $e->getMessage()]);
}

