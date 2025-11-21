<?php
/**
 * Retorna chave VAPID pública para o frontend
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT public_key FROM vapid_keys ORDER BY id DESC LIMIT 1");
    $vapid = $stmt->fetch();
    
    if (!$vapid) {
        throw new Exception('Chaves VAPID não configuradas. Execute scripts/gerar_vapid_keys.php');
    }
    
    echo json_encode(['publicKey' => $vapid['public_key']]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

