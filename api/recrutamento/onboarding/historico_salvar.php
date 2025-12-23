<?php
/**
 * API: Salvar histórico do onboarding (criar ou editar)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $id = (int)($_POST['id'] ?? 0);
    $onboarding_id = (int)($_POST['onboarding_id'] ?? 0);
    $tipo = trim($_POST['tipo'] ?? 'anotacao');
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $status_andamento = trim($_POST['status_andamento'] ?? 'em_andamento');
    
    if (!$onboarding_id) {
        throw new Exception('Onboarding não informado');
    }
    
    if (empty($titulo)) {
        throw new Exception('Título é obrigatório');
    }
    
    // Valida tipo
    $tipos_validos = ['anotacao', 'andamento', 'documento', 'contato', 'problema', 'outro'];
    if (!in_array($tipo, $tipos_validos)) {
        $tipo = 'anotacao';
    }
    
    // Valida status
    $status_validos = ['pendente', 'em_andamento', 'concluido', 'cancelado'];
    if (!in_array($status_andamento, $status_validos)) {
        $status_andamento = 'em_andamento';
    }
    
    if ($id > 0) {
        // Editar
        $stmt = $pdo->prepare("
            UPDATE onboarding_historico SET
                tipo = ?,
                titulo = ?,
                descricao = ?,
                status_andamento = ?,
                data_atualizacao = NOW()
            WHERE id = ? AND onboarding_id = ?
        ");
        $stmt->execute([$tipo, $titulo, $descricao, $status_andamento, $id, $onboarding_id]);
        
        $message = 'Registro atualizado com sucesso';
    } else {
        // Criar
        $stmt = $pdo->prepare("
            INSERT INTO onboarding_historico 
            (onboarding_id, usuario_id, tipo, titulo, descricao, status_andamento, data_registro)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$onboarding_id, $usuario['id'], $tipo, $titulo, $descricao, $status_andamento]);
        
        $id = $pdo->lastInsertId();
        $message = 'Registro criado com sucesso';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'id' => $id
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

