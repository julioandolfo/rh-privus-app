<?php
/**
 * API para marcar anotação como visualizada
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $anotacao_id = (int)($_POST['id'] ?? 0);
    
    if ($anotacao_id <= 0) {
        throw new Exception('ID da anotação inválido');
    }
    
    $usuario_id = $usuario['id'];
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    // Verifica se já foi visualizada
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as existe
        FROM anotacoes_visualizacoes
        WHERE anotacao_id = ? AND (usuario_id = ? OR colaborador_id = ?)
    ");
    $stmt->execute([$anotacao_id, $usuario_id, $colaborador_id]);
    $existe = $stmt->fetch()['existe'];
    
    if (!$existe) {
        // Registra visualização
        $stmt = $pdo->prepare("
            INSERT INTO anotacoes_visualizacoes (anotacao_id, usuario_id, colaborador_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$anotacao_id, $usuario_id, $colaborador_id]);
        
        // Atualiza contador de visualizações
        $stmt = $pdo->prepare("
            UPDATE anotacoes_sistema
            SET visualizacoes = visualizacoes + 1
            WHERE id = ?
        ");
        $stmt->execute([$anotacao_id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Anotação marcada como visualizada'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

