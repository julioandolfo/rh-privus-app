<?php
/**
 * Exemplo de Configuração de Email/SMTP
 * 
 * Copie este arquivo para config/email.php e configure suas credenciais
 */

return [
    // Configurações SMTP
    'smtp' => [
        'host' => 'smtp.gmail.com',                    // Servidor SMTP
        'port' => 587,                                  // Porta SMTP (587 para TLS, 465 para SSL)
        'secure' => 'tls',                             // 'tls' ou 'ssl'
        'auth' => true,                                 // Requer autenticação
        'username' => 'seu_email@gmail.com',            // Usuário SMTP
        'password' => 'sua_senha_app',                  // Senha SMTP (use senha de app para Gmail)
        'from_email' => 'noreply@privus.com.br',       // Email remetente padrão
        'from_name' => 'RH Privus',                     // Nome remetente padrão
        'charset' => 'UTF-8',
    ],
    
    // Configurações gerais
    'debug' => false,                                  // Modo debug (false = off, true = verbose)
];

