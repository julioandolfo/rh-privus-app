<?php
/**
 * API para registrar visualização de comunicado (sem marcar como lido)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$comunicado_id = intval($input['comunicado_id'] ?? 0);

if ($comunicado_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do comunicado inválido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    // Verifica se já existe registro de leitura
    $stmt = $pdo->prepare("
        SELECT id FROM comunicados_leitura 
        WHERE comunicado_id = ? 
        AND (usuario_id = ? OR colaborador_id = ?)
    ");
    $stmt->execute([$comunicado_id, $usuario_id, $colaborador_id]);
    $leitura = $stmt->fetch();
    
    if ($leitura) {
        // Atualiza registro existente (incrementa visualizações)
        $stmt = $pdo->prepare("
            UPDATE comunicados_leitura 
            SET data_visualizacao = NOW(),
                vezes_visualizado = vezes_visualizado + 1
            WHERE id = ?
        ");
        $stmt->execute([$leitura['id']]);
    } else {
        // Cria novo registro
        $stmt = $pdo->prepare("
            INSERT INTO comunicados_leitura (comunicado_id, usuario_id, colaborador_id, lido, data_visualizacao, vezes_visualizado)
            VALUES (?, ?, ?, 0, NOW(), 1)
        ");
        $stmt->execute([$comunicado_id, $usuario_id, $colaborador_id]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar visualização: ' . $e->getMessage()]);
}

