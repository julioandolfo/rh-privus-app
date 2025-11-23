<?php
/**
 * API: Criar Formulário de Cultura
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!has_role(['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $nome = trim($_POST['nome'] ?? '');
    
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO formularios_cultura 
        (nome, descricao, etapa_id, ativo, criado_por)
        VALUES (?, ?, ?, 1, ?)
    ");
    $stmt->execute([
        $nome,
        $_POST['descricao'] ?? null,
        !empty($_POST['etapa_id']) ? (int)$_POST['etapa_id'] : null,
        $usuario['id']
    ]);
    
    $formulario_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Formulário criado com sucesso',
        'formulario_id' => $formulario_id
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

