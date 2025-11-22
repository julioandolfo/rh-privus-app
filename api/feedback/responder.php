<?php
/**
 * API para Responder Feedback
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
    
    $feedback_id = $_POST['feedback_id'] ?? null;
    $resposta = trim($_POST['resposta'] ?? '');
    $resposta_pai_id = $_POST['resposta_pai_id'] ?? null;
    
    // Validações
    if (empty($feedback_id)) {
        throw new Exception('ID do feedback é obrigatório.');
    }
    
    if (empty($resposta)) {
        throw new Exception('A resposta não pode estar vazia.');
    }
    
    // Verifica se feedback existe e se usuário pode responder
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            destinatario_usuario_id, 
            destinatario_colaborador_id,
            remetente_usuario_id,
            remetente_colaborador_id,
            status
        FROM feedbacks 
        WHERE id = ?
    ");
    $stmt->execute([$feedback_id]);
    $feedback = $stmt->fetch();
    
    if (!$feedback) {
        throw new Exception('Feedback não encontrado.');
    }
    
    if ($feedback['status'] !== 'ativo') {
        throw new Exception('Não é possível responder a um feedback inativo.');
    }
    
    // Verifica se usuário é destinatário ou remetente do feedback
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    $pode_responder = false;
    
    if ($usuario_id) {
        $pode_responder = ($feedback['destinatario_usuario_id'] == $usuario_id) || 
                         ($feedback['remetente_usuario_id'] == $usuario_id);
    } elseif ($colaborador_id) {
        $pode_responder = ($feedback['destinatario_colaborador_id'] == $colaborador_id) || 
                         ($feedback['remetente_colaborador_id'] == $colaborador_id);
    }
    
    if (!$pode_responder) {
        throw new Exception('Você não tem permissão para responder este feedback.');
    }
    
    // Se houver resposta_pai_id, verifica se existe
    if ($resposta_pai_id) {
        $stmt = $pdo->prepare("SELECT id FROM feedback_respostas WHERE id = ? AND feedback_id = ? AND status = 'ativo'");
        $stmt->execute([$resposta_pai_id, $feedback_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Resposta pai não encontrada.');
        }
    }
    
    // Insere resposta
    $stmt = $pdo->prepare("
        INSERT INTO feedback_respostas (
            feedback_id, usuario_id, colaborador_id, 
            resposta, resposta_pai_id, status
        ) VALUES (?, ?, ?, ?, ?, 'ativo')
    ");
    
    $stmt->execute([
        $feedback_id,
        $usuario_id,
        $colaborador_id,
        $resposta,
        $resposta_pai_id ?: null
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Resposta enviada com sucesso!'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

