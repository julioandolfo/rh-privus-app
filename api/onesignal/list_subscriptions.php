<?php
/**
 * API para listar subscriptions do usuário logado
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

try {
    $pdo = getDB();
    
    // Verifica e cria a tabela onesignal_subscriptions se não existir
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'onesignal_subscriptions'");
        if ($stmt->rowCount() == 0) {
            // Cria a tabela automaticamente
            $pdo->exec("
                CREATE TABLE onesignal_subscriptions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NULL,
                    colaborador_id INT NULL,
                    player_id VARCHAR(255) NOT NULL UNIQUE,
                    device_type VARCHAR(50),
                    user_agent VARCHAR(500),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_usuario (usuario_id),
                    INDEX idx_colaborador (colaborador_id),
                    INDEX idx_player_id (player_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (PDOException $e) {
        // Ignora se já existe
        if (strpos($e->getMessage(), 'already exists') === false && 
            strpos($e->getMessage(), '1050') === false) {
            throw $e;
        }
    }
    
    $usuario = $_SESSION['usuario'];
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    // Busca subscriptions do usuário
    $stmt = $pdo->prepare("
        SELECT id, usuario_id, colaborador_id, player_id, device_type, user_agent, created_at, updated_at
        FROM onesignal_subscriptions
        WHERE usuario_id = ? OR colaborador_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$usuario_id, $colaborador_id]);
    $subscriptions = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'subscriptions' => $subscriptions,
        'count' => count($subscriptions)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

