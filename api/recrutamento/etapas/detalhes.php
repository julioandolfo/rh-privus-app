<?php
/**
 * API: Detalhes da Etapa
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if (!has_role(['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    
    $etapa_id = (int)($_GET['id'] ?? 0);
    
    if (empty($etapa_id)) {
        throw new Exception('Etapa não informada');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM processo_seletivo_etapas WHERE id = ? AND vaga_id IS NULL");
    $stmt->execute([$etapa_id]);
    $etapa = $stmt->fetch();
    
    if (!$etapa) {
        throw new Exception('Etapa não encontrada');
    }
    
    echo json_encode([
        'success' => true,
        'etapa' => $etapa
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

