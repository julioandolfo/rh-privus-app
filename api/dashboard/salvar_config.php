<?php
/**
 * API para salvar configuração do dashboard do usuário
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $configuracao = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($configuracao['cards']) || !is_array($configuracao['cards'])) {
        throw new Exception('Configuração inválida');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Remove configurações antigas do usuário
        $stmt_delete = $pdo->prepare("DELETE FROM dashboard_config WHERE usuario_id = ?");
        $stmt_delete->execute([$usuario['id']]);
        
        // Insere novas configurações
        $stmt_insert = $pdo->prepare("
            INSERT INTO dashboard_config (usuario_id, card_id, posicao_x, posicao_y, largura, altura, visivel, ordem)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $ordem = 0;
        foreach ($configuracao['cards'] as $card) {
            if (!isset($card['id'])) continue;
            
            $stmt_insert->execute([
                $usuario['id'],
                $card['id'],
                $card['x'] ?? 0,
                $card['y'] ?? 0,
                $card['w'] ?? 3,
                $card['h'] ?? 3,
                isset($card['visible']) ? (int)$card['visible'] : 1,
                $ordem++
            ]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuração salva com sucesso!'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

