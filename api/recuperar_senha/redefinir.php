<?php
/**
 * API para redefinir senha usando token
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $token = trim($_POST['token'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $confirmar_senha = trim($_POST['confirmar_senha'] ?? '');
    
    if (empty($token)) {
        throw new Exception('Token é obrigatório');
    }
    
    if (empty($senha) || empty($confirmar_senha)) {
        throw new Exception('Preencha todos os campos');
    }
    
    if (strlen($senha) < 6) {
        throw new Exception('A senha deve ter no mínimo 6 caracteres');
    }
    
    if ($senha !== $confirmar_senha) {
        throw new Exception('As senhas não coincidem');
    }
    
    // Busca token válido
    $stmt = $pdo->prepare("
        SELECT * FROM password_reset_tokens 
        WHERE token = ? 
        AND used_at IS NULL 
        AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $token_info = $stmt->fetch();
    
    if (!$token_info) {
        throw new Exception('Token inválido ou expirado. Solicite uma nova recuperação de senha.');
    }
    
    // Gera hash da nova senha
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    
    // Inicia transação
    $pdo->beginTransaction();
    
    try {
        if ($token_info['tipo'] === 'usuario') {
            // Atualiza senha do usuário
            $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
            $stmt->execute([$senha_hash, $token_info['usuario_id']]);
        } else {
            // Atualiza senha do colaborador
            $stmt = $pdo->prepare("UPDATE colaboradores SET senha_hash = ? WHERE id = ?");
            $stmt->execute([$senha_hash, $token_info['colaborador_id']]);
        }
        
        // Marca token como usado
        $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
        $stmt->execute([$token_info['id']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Senha redefinida com sucesso!'
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

