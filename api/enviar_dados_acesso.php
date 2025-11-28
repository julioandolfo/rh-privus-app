<?php
/**
 * API para enviar dados de acesso ao colaborador por email
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/email_templates.php';

header('Content-Type: application/json');

// Verifica autenticação
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Verifica permissão
$usuario = $_SESSION['usuario'];
if ($usuario['role'] === 'COLABORADOR') {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

// Verifica método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$colaborador_id = $_POST['colaborador_id'] ?? 0;

if (empty($colaborador_id)) {
    echo json_encode(['success' => false, 'message' => 'ID do colaborador não informado']);
    exit;
}

try {
    $pdo = getDB();
    
    // Busca dados do colaborador
    $stmt = $pdo->prepare("
        SELECT c.*, 
               e.nome_fantasia as empresa_nome,
               u.id as usuario_id,
               u.email as usuario_email,
               u.senha_hash as usuario_senha_hash
        FROM colaboradores c
        LEFT JOIN empresas e ON c.empresa_id = e.id
        LEFT JOIN usuarios u ON u.colaborador_id = c.id
        WHERE c.id = ?
    ");
    $stmt->execute([$colaborador_id]);
    $colaborador = $stmt->fetch();
    
    if (!$colaborador) {
        echo json_encode(['success' => false, 'message' => 'Colaborador não encontrado']);
        exit;
    }
    
    // Verifica se tem email cadastrado
    $email_destino = $colaborador['email_pessoal'] ?? null;
    if (empty($email_destino)) {
        echo json_encode(['success' => false, 'message' => 'Colaborador não possui email cadastrado']);
        exit;
    }
    
    // Determina login (CPF ou email)
    $usuario_login = !empty($colaborador['cpf']) ? formatar_cpf($colaborador['cpf']) : $email_destino;
    
    // Verifica se já tem senha cadastrada
    $tem_senha = false;
    $senha_temporaria = null;
    
    // Verifica se tem usuário vinculado com senha
    if (!empty($colaborador['usuario_id']) && !empty($colaborador['usuario_senha_hash'])) {
        $tem_senha = true;
    }
    // Verifica se tem senha_hash direto no colaborador
    elseif (!empty($colaborador['senha_hash'])) {
        $tem_senha = true;
    }
    
    // Se não tem senha, cria uma temporária
    if (!$tem_senha) {
        // Gera senha temporária aleatória (8 caracteres: letras e números)
        $senha_temporaria = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        $senha_hash = password_hash($senha_temporaria, PASSWORD_DEFAULT);
        
        // Se tem usuário vinculado, atualiza senha do usuário
        if (!empty($colaborador['usuario_id'])) {
            $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
            $stmt->execute([$senha_hash, $colaborador['usuario_id']]);
        } else {
            // Atualiza senha_hash do colaborador
            $stmt = $pdo->prepare("UPDATE colaboradores SET senha_hash = ? WHERE id = ?");
            $stmt->execute([$senha_hash, $colaborador_id]);
        }
    }
    
    // Monta URL de acesso
    $base_url = get_base_url();
    $url_login = $base_url . '/login.php';
    
    // Prepara variáveis para o email
    $variaveis = [
        'nome_completo' => $colaborador['nome_completo'],
        'empresa_nome' => $colaborador['empresa_nome'] ?? '',
        'cargo_nome' => '', // Será preenchido se necessário
        'setor_nome' => '', // Será preenchido se necessário
        'data_inicio' => formatar_data($colaborador['data_inicio'] ?? date('Y-m-d')),
        'tipo_contrato' => $colaborador['tipo_contrato'] ?? 'CLT',
        'cpf' => formatar_cpf($colaborador['cpf'] ?? ''),
        'email_pessoal' => $email_destino,
        'telefone' => $colaborador['telefone'] ?? '',
        'usuario_login' => $usuario_login,
        'senha' => $senha_temporaria ?? '[Sua senha cadastrada]',
        'url_login' => $url_login,
        'ano_atual' => date('Y'),
        'dados_acesso_html' => ''
    ];
    
    // Busca cargo e setor se necessário
    if (!empty($colaborador['cargo_id'])) {
        $stmt_cargo = $pdo->prepare("SELECT nome_cargo FROM cargos WHERE id = ?");
        $stmt_cargo->execute([$colaborador['cargo_id']]);
        $cargo = $stmt_cargo->fetch();
        if ($cargo) {
            $variaveis['cargo_nome'] = $cargo['nome_cargo'];
        }
    }
    
    if (!empty($colaborador['setor_id'])) {
        $stmt_setor = $pdo->prepare("SELECT nome_setor FROM setores WHERE id = ?");
        $stmt_setor->execute([$colaborador['setor_id']]);
        $setor = $stmt_setor->fetch();
        if ($setor) {
            $variaveis['setor_nome'] = $setor['nome_setor'];
        }
    }
    
    // Se tem senha temporária, prepara HTML e texto com dados de acesso
    if (!empty($senha_temporaria)) {
        $variaveis['dados_acesso_html'] = '
            <div class="dados-acesso-box">
                <h3>Dados de Acesso ao Sistema</h3>
                <p><strong>Usuário/Login:</strong> <code>' . htmlspecialchars($usuario_login) . '</code></p>
                <p><strong>Senha Temporária:</strong> <code class="senha-code">' . htmlspecialchars($senha_temporaria) . '</code></p>
                <p><strong>Link de Acesso:</strong> <a href="' . htmlspecialchars($url_login) . '" style="color: #009ef7;">' . htmlspecialchars($url_login) . '</a></p>
            </div>
        ';
        $variaveis['dados_acesso_texto'] = "\n\nDADOS DE ACESSO AO SISTEMA:\nUsuário/Login: " . $usuario_login . "\nSenha Temporária: " . $senha_temporaria . "\nLink de Acesso: " . $url_login . "\n\n⚠️ Importante: Guarde estas informações com segurança. Você pode alterar sua senha após o primeiro acesso.";
    } else {
        // Se já tem senha, apenas informa login e link
        $variaveis['dados_acesso_html'] = '
            <div class="dados-acesso-box">
                <h3>Dados de Acesso ao Sistema</h3>
                <p><strong>Usuário/Login:</strong> <code>' . htmlspecialchars($usuario_login) . '</code></p>
                <p><strong>Link de Acesso:</strong> <a href="' . htmlspecialchars($url_login) . '" style="color: #009ef7;">' . htmlspecialchars($url_login) . '</a></p>
                <p style="margin-top: 15px; color: #6c757d; font-size: 13px;"><em>Use sua senha cadastrada para acessar o sistema. Se esqueceu sua senha, use a opção "Esqueci minha senha" na página de login.</em></p>
            </div>
        ';
        $variaveis['dados_acesso_texto'] = "\n\nDADOS DE ACESSO AO SISTEMA:\nUsuário/Login: " . $usuario_login . "\nLink de Acesso: " . $url_login . "\n\nUse sua senha cadastrada para acessar o sistema. Se esqueceu sua senha, use a opção 'Esqueci minha senha' na página de login.";
    }
    
    // Tenta usar template de email específico para dados de acesso
    $resultado = enviar_email_template('dados_acesso', $email_destino, $variaveis);
    
    // Se template não existe ou falhou, tenta usar template de novo colaborador
    if (!$resultado || (isset($resultado['success']) && !$resultado['success'])) {
        $resultado = enviar_email_template('novo_colaborador', $email_destino, $variaveis);
    }
    
    // Se ainda não funcionou, cria email customizado
    if (!$resultado || (isset($resultado['success']) && !$resultado['success'])) {
        $assunto = 'Dados de Acesso ao Sistema - RH Privus';
        
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
                    <p>Olá, <strong>{$variaveis['nome_completo']}</strong>!</p>
                    <p>Seus dados de acesso ao sistema foram enviados conforme solicitado.</p>
                    {$variaveis['dados_acesso_html']}
                    <p style='text-align: center; margin-top: 30px;'>
                        <a href='{$url_login}' class='button'>Acessar Sistema</a>
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
        
        require_once __DIR__ . '/../includes/email.php';
        $resultado = enviar_email($email_destino, $assunto, $mensagem, [
            'nome_destinatario' => $variaveis['nome_completo']
        ]);
    }
    
    if ($resultado && (is_bool($resultado) || (isset($resultado['success']) && $resultado['success']))) {
        $mensagem_sucesso = 'Dados de acesso enviados com sucesso para ' . $email_destino;
        if ($senha_temporaria) {
            $mensagem_sucesso .= '. Uma senha temporária foi gerada e enviada por email.';
        }
        echo json_encode([
            'success' => true,
            'message' => $mensagem_sucesso
        ]);
    } else {
        $mensagem_erro = isset($resultado['message']) ? $resultado['message'] : 'Erro ao enviar email';
        echo json_encode([
            'success' => false,
            'message' => $mensagem_erro
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}

