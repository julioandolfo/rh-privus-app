<?php
/**
 * API para registrar emoção diária
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $nivel_emocao = $_POST['nivel_emocao'] ?? null;
    $descricao = $_POST['descricao'] ?? null;
    
    if (empty($nivel_emocao) || !in_array($nivel_emocao, [1, 2, 3, 4, 5])) {
        throw new Exception('Nível de emoção inválido');
    }
    
    $data_registro = date('Y-m-d');
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    // Verifica se já registrou emoção hoje
    if ($usuario_id) {
        $stmt = $pdo->prepare("SELECT id FROM emocoes WHERE usuario_id = ? AND data_registro = ? AND usuario_id IS NOT NULL");
        $stmt->execute([$usuario_id, $data_registro]);
    } else if ($colaborador_id) {
        $stmt = $pdo->prepare("SELECT id FROM emocoes WHERE colaborador_id = ? AND data_registro = ? AND colaborador_id IS NOT NULL");
        $stmt->execute([$colaborador_id, $data_registro]);
    } else {
        throw new Exception('Usuário ou colaborador não identificado');
    }
    
    if ($stmt->fetch()) {
        throw new Exception('Você já registrou sua emoção hoje!');
    }
    
    // Insere emoção
    $stmt = $pdo->prepare("
        INSERT INTO emocoes (usuario_id, colaborador_id, nivel_emocao, descricao, data_registro)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$usuario_id, $colaborador_id, $nivel_emocao, $descricao, $data_registro]);
    $emocao_id = $pdo->lastInsertId();
    
    // Adiciona pontos
    require_once __DIR__ . '/../includes/pontuacao.php';
    adicionar_pontos('registrar_emocao', $usuario_id, $colaborador_id, $emocao_id, 'emocao');
    
    echo json_encode([
        'success' => true,
        'message' => 'Emoção registrada com sucesso!',
        'emocao_id' => $emocao_id
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

