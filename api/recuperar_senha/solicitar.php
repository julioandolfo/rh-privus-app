<?php
/**
 * API para solicitar recuperação de senha
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/email.php';

// Verifica se a tabela existe, se não, cria
try {
    $pdo = getDB();
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(64) NOT NULL,
                tipo ENUM('usuario', 'colaborador') NOT NULL,
                usuario_id INT NULL,
                colaborador_id INT NULL,
                expires_at TIMESTAMP NOT NULL,
                used_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token),
                INDEX idx_email (email),
                INDEX idx_expires_at (expires_at),
                INDEX idx_usuario (usuario_id),
                INDEX idx_colaborador (colaborador_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
} catch (PDOException $e) {
    // Ignora se já existir
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $email_cpf = trim($_POST['email_cpf'] ?? '');
    
    if (empty($email_cpf)) {
        throw new Exception('Email ou CPF é obrigatório');
    }
    
    // Tenta encontrar como usuário primeiro
    $stmt = $pdo->prepare("SELECT id, nome, email FROM usuarios WHERE email = ? AND status = 'ativo'");
    $stmt->execute([$email_cpf]);
    $usuario = $stmt->fetch();
    
    $tipo = null;
    $usuario_id = null;
    $colaborador_id = null;
    $nome = null;
    $email_destino = null;
    
    if ($usuario) {
        $tipo = 'usuario';
        $usuario_id = $usuario['id'];
        $nome = $usuario['nome'];
        $email_destino = $usuario['email'];
    } else {
        // Tenta como colaborador (CPF ou email_pessoal)
        $cpf_limpo = preg_replace('/[^0-9]/', '', $email_cpf);
        $stmt = $pdo->prepare("
            SELECT id, nome_completo, email_pessoal, cpf 
            FROM colaboradores 
            WHERE (cpf = ? OR email_pessoal = ?) 
            AND status = 'ativo'
            AND senha_hash IS NOT NULL
        ");
        $stmt->execute([$cpf_limpo, $email_cpf]);
        $colaborador = $stmt->fetch();
        
        if ($colaborador) {
            $tipo = 'colaborador';
            $colaborador_id = $colaborador['id'];
            $nome = $colaborador['nome_completo'];
            $email_destino = $colaborador['email_pessoal'];
            
            // Se não tem email_pessoal, não pode recuperar
            if (empty($email_destino)) {
                throw new Exception('Este colaborador não possui email cadastrado. Entre em contato com o RH.');
            }
        }
    }
    
    // Se não encontrou nenhum, não revela isso por segurança
    if (!$usuario && !$colaborador) {
        // Retorna sucesso mesmo assim para não revelar se o email existe
        echo json_encode([
            'success' => true,
            'message' => 'Se o email informado estiver cadastrado, você receberá um link para redefinir sua senha.'
        ]);
        exit;
    }
    
    // Gera token único
    $token = bin2hex(random_bytes(32));
    
    // Expira em 1 hora
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Invalida tokens anteriores não usados
    if ($tipo === 'usuario') {
        $stmt = $pdo->prepare("
            UPDATE password_reset_tokens 
            SET used_at = NOW() 
            WHERE usuario_id = ? AND used_at IS NULL
        ");
        $stmt->execute([$usuario_id]);
        
        // Insere novo token
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_tokens (email, token, tipo, usuario_id, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$email_destino, $token, $tipo, $usuario_id, $expires_at]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE password_reset_tokens 
            SET used_at = NOW() 
            WHERE colaborador_id = ? AND used_at IS NULL
        ");
        $stmt->execute([$colaborador_id]);
        
        // Insere novo token
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_tokens (email, token, tipo, colaborador_id, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$email_destino, $token, $tipo, $colaborador_id, $expires_at]);
    }
    
    // Monta URL de recuperação
    $baseUrl = get_base_url();
    $resetUrl = $baseUrl . '/pages/redefinir_senha.php?token=' . $token;
    
    // Envia email
    $assunto = 'Recuperação de Senha - RH Privus';
    $mensagem = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #009ef7; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 30px; }
                .button { display: inline-block; background-color: #009ef7; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>RH Privus</h2>
                </div>
                <div class='content'>
                    <p>Olá, <strong>{$nome}</strong>!</p>
                    <p>Recebemos uma solicitação para redefinir sua senha.</p>
                    <p>Clique no botão abaixo para criar uma nova senha:</p>
                    <p style='text-align: center;'>
                        <a href='{$resetUrl}' class='button'>Redefinir Senha</a>
                    </p>
                    <p>Ou copie e cole o link abaixo no seu navegador:</p>
                    <p style='word-break: break-all; color: #009ef7;'>{$resetUrl}</p>
                    <p><strong>Este link expira em 1 hora.</strong></p>
                    <p>Se você não solicitou esta recuperação de senha, ignore este email.</p>
                </div>
                <div class='footer'>
                    <p>Este é um email automático, por favor não responda.</p>
                    <p>&copy; " . date('Y') . " RH Privus - Todos os direitos reservados</p>
                </div>
            </div>
        </body>
        </html>
    ";
    
    $result = enviar_email($email_destino, $assunto, $mensagem, [
        'nome_destinatario' => $nome
    ]);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Se o email informado estiver cadastrado, você receberá um link para redefinir sua senha. Verifique sua caixa de entrada e spam.'
        ]);
    } else {
        throw new Exception($result['message']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

