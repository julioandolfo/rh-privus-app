<?php
/**
 * API para carregar configuração do dashboard do usuário
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $stmt = $pdo->prepare("
        SELECT card_id, posicao_x as x, posicao_y as y, largura as w, altura as h, visivel as visible, ordem
        FROM dashboard_config
        WHERE usuario_id = ?
        ORDER BY ordem ASC
    ");
    $stmt->execute([$usuario['id']]);
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Converte para formato esperado pelo GridStack
    $cards = [];
    foreach ($configs as $config) {
        $cards[] = [
            'id' => $config['card_id'],
            'x' => (int)$config['x'],
            'y' => (int)$config['y'],
            'w' => (int)$config['w'],
            'h' => (int)$config['h'],
            'visible' => (bool)$config['visible']
        ];
    }
    
    // Busca configurações do dashboard (margin, cellHeight, etc)
    $stmt_config = $pdo->prepare("
        SELECT configuracao_valor
        FROM dashboard_preferences
        WHERE usuario_id = ? AND configuracao_chave = 'dashboard_settings'
        LIMIT 1
    ");
    $stmt_config->execute([$usuario['id']]);
    $dashboard_settings = $stmt_config->fetchColumn();
    
    $config = null;
    if ($dashboard_settings) {
        $config = json_decode($dashboard_settings, true);
    }
    
    // Busca configurações dos carrosséis
    $stmt_carousel = $pdo->prepare("
        SELECT configuracao_valor
        FROM dashboard_preferences
        WHERE usuario_id = ? AND configuracao_chave = 'carousel_settings'
        LIMIT 1
    ");
    $stmt_carousel->execute([$usuario['id']]);
    $carousel_settings = $stmt_carousel->fetchColumn();
    
    $carrosselConfigs = null;
    if ($carousel_settings) {
        $carrosselConfigs = json_decode($carousel_settings, true);
    }
    
    echo json_encode([
        'success' => true,
        'cards' => $cards,
        'config' => $config,
        'carrosselConfigs' => $carrosselConfigs
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'cards' => [],
        'config' => null
    ]);
}

