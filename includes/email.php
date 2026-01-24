<?php
/**
 * Sistema de Envio de Email usando PHPMailer
 */

require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/functions.php';

/**
 * Registra log de email enviado
 * 
 * @param string $email_destinatario Email do destinatário
 * @param string $assunto Assunto do email
 * @param string $status 'sucesso' ou 'erro'
 * @param array $opcoes Opções adicionais
 * @return bool
 */
function registrar_log_email($email_destinatario, $assunto, $status, $opcoes = []) {
    try {
        $pdo = getDB();
        
        // Verifica se a tabela existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'email_logs'");
        if ($stmt->rowCount() == 0) {
            // Cria a tabela se não existir
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS email_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email_destinatario VARCHAR(255) NOT NULL,
                    nome_destinatario VARCHAR(255) NULL,
                    assunto VARCHAR(500) NOT NULL,
                    template_codigo VARCHAR(50) NULL,
                    template_nome VARCHAR(255) NULL,
                    status ENUM('sucesso', 'erro') NOT NULL DEFAULT 'sucesso',
                    erro_mensagem TEXT NULL,
                    origem VARCHAR(100) NULL,
                    usuario_id INT NULL,
                    colaborador_id INT NULL,
                    empresa_id INT NULL,
                    ip_origem VARCHAR(45) NULL,
                    user_agent TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_email_logs_status (status),
                    INDEX idx_email_logs_destinatario (email_destinatario),
                    INDEX idx_email_logs_template (template_codigo),
                    INDEX idx_email_logs_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        
        // Prepara dados do log
        $nome_destinatario = $opcoes['nome_destinatario'] ?? null;
        $template_codigo = $opcoes['template_codigo'] ?? null;
        $template_nome = $opcoes['template_nome'] ?? null;
        $erro_mensagem = $opcoes['erro_mensagem'] ?? null;
        $origem = $opcoes['origem'] ?? null;
        $usuario_id = $opcoes['usuario_id'] ?? (isset($_SESSION['usuario']['id']) ? $_SESSION['usuario']['id'] : null);
        $colaborador_id = $opcoes['colaborador_id'] ?? null;
        $empresa_id = $opcoes['empresa_id'] ?? (isset($_SESSION['usuario']['empresa_id']) ? $_SESSION['usuario']['empresa_id'] : null);
        $ip_origem = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO email_logs 
            (email_destinatario, nome_destinatario, assunto, template_codigo, template_nome, 
             status, erro_mensagem, origem, usuario_id, colaborador_id, empresa_id, ip_origem, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $email_destinatario,
            $nome_destinatario,
            mb_substr($assunto, 0, 500),
            $template_codigo,
            $template_nome,
            $status,
            $erro_mensagem,
            $origem,
            $usuario_id,
            $colaborador_id,
            $empresa_id,
            $ip_origem,
            $user_agent
        ]);
        
        return true;
    } catch (Exception $e) {
        // Silencia erros de log para não afetar o envio do email
        error_log("Erro ao registrar log de email: " . $e->getMessage());
        return false;
    }
}

/**
 * Envia um email usando SMTP
 * 
 * @param string $para Email do destinatário
 * @param string $assunto Assunto do email
 * @param string $mensagem Corpo do email (HTML ou texto)
 * @param array $opcoes Opções adicionais ['nome_destinatario', 'de_email', 'de_nome', 'anexos' => []]
 * @return array ['success' => bool, 'message' => string]
 */
function enviar_email($para, $assunto, $mensagem, $opcoes = []) {
    try {
        // Tenta buscar configurações do banco de dados primeiro
        $config_db = null;
        try {
            require_once __DIR__ . '/functions.php';
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM configuracoes_email WHERE id = 1");
            $stmt->execute();
            $config_db = $stmt->fetch();
        } catch (Exception $e) {
            // Se não conseguir buscar do banco, usa arquivo de config
        }
        
        // Usa configurações do banco se disponível, senão usa arquivo
        if ($config_db) {
            $smtp = [
                'host' => $config_db['smtp_host'],
                'port' => $config_db['smtp_port'],
                'secure' => $config_db['smtp_secure'],
                'auth' => (bool)$config_db['smtp_auth'],
                'username' => $config_db['smtp_username'],
                'password' => $config_db['smtp_password'],
                'from_email' => $config_db['from_email'],
                'from_name' => $config_db['from_name'],
                'charset' => 'UTF-8',
            ];
            $debug = (bool)$config_db['smtp_debug'];
        } else {
            // Fallback para arquivo de configuração
            $config = include __DIR__ . '/../config/email.php';
            $smtp = $config['smtp'];
            $debug = $config['debug'];
        }
        
        // Verifica se as configurações estão preenchidas (apenas se auth estiver ativado)
        if ($smtp['auth'] && (empty($smtp['username']) || empty($smtp['password']))) {
            return [
                'success' => false,
                'message' => 'Configurações de email não definidas. Configure através do menu Configurações > Configurações de Email'
            ];
        }
        
        if (empty($smtp['host'])) {
            return [
                'success' => false,
                'message' => 'Servidor SMTP não configurado. Configure através do menu Configurações > Configurações de Email'
            ];
        }
        
        $mail = new PHPMailer(true);
        
        // Configurações do servidor SMTP
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->SMTPAuth = $smtp['auth'];
        $mail->Username = $smtp['username'];
        $mail->Password = $smtp['password'];
        $mail->SMTPSecure = $smtp['secure'];
        $mail->Port = $smtp['port'];
        $mail->CharSet = $smtp['charset'];
        
        // Debug
        if ($debug) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }
        
        // Remetente
        $de_email = $opcoes['de_email'] ?? $smtp['from_email'];
        $de_nome = $opcoes['de_nome'] ?? $smtp['from_name'];
        $mail->setFrom($de_email, $de_nome);
        
        // Destinatário
        $nome_destinatario = $opcoes['nome_destinatario'] ?? '';
        $mail->addAddress($para, $nome_destinatario);
        
        // Reply-To (opcional)
        if (isset($opcoes['reply_to'])) {
            $mail->addReplyTo($opcoes['reply_to'], $opcoes['reply_to_nome'] ?? '');
        }
        
        // CC e BCC (opcional)
        if (isset($opcoes['cc'])) {
            foreach ((array)$opcoes['cc'] as $cc) {
                $mail->addCC($cc);
            }
        }
        if (isset($opcoes['bcc'])) {
            foreach ((array)$opcoes['bcc'] as $bcc) {
                $mail->addBCC($bcc);
            }
        }
        
        // Anexos (opcional)
        if (isset($opcoes['anexos']) && is_array($opcoes['anexos'])) {
            foreach ($opcoes['anexos'] as $anexo) {
                if (is_string($anexo) && file_exists($anexo)) {
                    $mail->addAttachment($anexo);
                } elseif (is_array($anexo) && isset($anexo['path']) && file_exists($anexo['path'])) {
                    $nome_anexo = $anexo['nome'] ?? basename($anexo['path']);
                    $mail->addAttachment($anexo['path'], $nome_anexo);
                }
            }
        }
        
        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body = $mensagem;
        
        // Versão texto alternativo (opcional)
        if (isset($opcoes['texto_alternativo'])) {
            $mail->AltBody = $opcoes['texto_alternativo'];
        } else {
            // Remove tags HTML para criar versão texto
            $mail->AltBody = strip_tags($mensagem);
        }
        
        $mail->send();
        
        // Registra log de sucesso
        registrar_log_email($para, $assunto, 'sucesso', [
            'nome_destinatario' => $nome_destinatario,
            'template_codigo' => $opcoes['template_codigo'] ?? null,
            'template_nome' => $opcoes['template_nome'] ?? null,
            'origem' => $opcoes['origem'] ?? 'envio_direto',
            'colaborador_id' => $opcoes['colaborador_id'] ?? null,
            'empresa_id' => $opcoes['empresa_id'] ?? null,
            'usuario_id' => $opcoes['usuario_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'message' => 'Email enviado com sucesso!'
        ];
        
    } catch (Exception $e) {
        $erro_info = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
        
        // Registra log de erro
        registrar_log_email($para, $assunto, 'erro', [
            'nome_destinatario' => $opcoes['nome_destinatario'] ?? '',
            'template_codigo' => $opcoes['template_codigo'] ?? null,
            'template_nome' => $opcoes['template_nome'] ?? null,
            'origem' => $opcoes['origem'] ?? 'envio_direto',
            'erro_mensagem' => $erro_info,
            'colaborador_id' => $opcoes['colaborador_id'] ?? null,
            'empresa_id' => $opcoes['empresa_id'] ?? null,
            'usuario_id' => $opcoes['usuario_id'] ?? null
        ]);
        
        return [
            'success' => false,
            'message' => 'Erro ao enviar email: ' . $erro_info
        ];
    }
}

/**
 * Envia email de boas-vindas para novo usuário
 */
function enviar_email_boas_vindas($usuario_email, $usuario_nome, $senha_temporaria = null) {
    $assunto = 'Bem-vindo ao Sistema RH Privus';
    
    $mensagem = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #009ef7; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .button { display: inline-block; padding: 10px 20px; background-color: #009ef7; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Bem-vindo ao RH Privus!</h1>
            </div>
            <div class='content'>
                <p>Olá <strong>{$usuario_nome}</strong>,</p>
                <p>Seu acesso ao sistema RH Privus foi criado com sucesso!</p>
                " . ($senha_temporaria ? "<p><strong>Senha temporária:</strong> {$senha_temporaria}</p><p>Por favor, altere sua senha no primeiro acesso.</p>" : "") . "
                <p>Você pode acessar o sistema através do link abaixo:</p>
                <p style='text-align: center;'>
                    <a href='" . get_base_url() . "/login.php' class='button'>Acessar Sistema</a>
                </p>
                <p>Se você não solicitou este acesso, por favor ignore este email.</p>
            </div>
            <div class='footer'>
                <p>Este é um email automático, por favor não responda.</p>
                <p>&copy; " . date('Y') . " RH Privus - Todos os direitos reservados</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return enviar_email($usuario_email, $assunto, $mensagem, [
        'nome_destinatario' => $usuario_nome
    ]);
}

/**
 * Envia email de recuperação de senha
 */
function enviar_email_recuperacao_senha($usuario_email, $usuario_nome, $token) {
    $assunto = 'Recuperação de Senha - RH Privus';
    
    $link_recuperacao = get_base_url() . '/recuperar_senha.php?token=' . urlencode($token);
    
    $mensagem = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #009ef7; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .button { display: inline-block; padding: 10px 20px; background-color: #009ef7; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .warning { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Recuperação de Senha</h1>
            </div>
            <div class='content'>
                <p>Olá <strong>{$usuario_nome}</strong>,</p>
                <p>Recebemos uma solicitação para redefinir sua senha.</p>
                <p>Clique no botão abaixo para criar uma nova senha:</p>
                <p style='text-align: center;'>
                    <a href='{$link_recuperacao}' class='button'>Redefinir Senha</a>
                </p>
                <p>Ou copie e cole este link no seu navegador:</p>
                <p style='word-break: break-all;'>{$link_recuperacao}</p>
                <div class='warning'>
                    <strong>Atenção:</strong> Este link expira em 1 hora. Se você não solicitou esta recuperação, ignore este email.
                </div>
            </div>
            <div class='footer'>
                <p>Este é um email automático, por favor não responda.</p>
                <p>&copy; " . date('Y') . " RH Privus - Todos os direitos reservados</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return enviar_email($usuario_email, $assunto, $mensagem, [
        'nome_destinatario' => $usuario_nome
    ]);
}

