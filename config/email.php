<?php
/**
 * Configurações de Email/SMTP
 * 
 * Configure as credenciais do seu servidor SMTP aqui
 */

return [
    // Configurações SMTP
    'smtp' => [
        'host' => env('SMTP_HOST', 'smtp.gmail.com'),           // Servidor SMTP
        'port' => env('SMTP_PORT', 587),                        // Porta SMTP (587 para TLS, 465 para SSL)
        'secure' => env('SMTP_SECURE', 'tls'),                  // 'tls' ou 'ssl'
        'auth' => env('SMTP_AUTH', true),                        // Requer autenticação
        'username' => env('SMTP_USERNAME', ''),                  // Usuário SMTP
        'password' => env('SMTP_PASSWORD', ''),                 // Senha SMTP
        'from_email' => env('SMTP_FROM_EMAIL', 'noreply@privus.com.br'),  // Email remetente padrão
        'from_name' => env('SMTP_FROM_NAME', 'RH Privus'),      // Nome remetente padrão
        'charset' => 'UTF-8',
    ],
    
    // Configurações gerais
    'debug' => env('SMTP_DEBUG', false),                        // Modo debug (0 = off, 2 = verbose)
];

/**
 * Função helper para ler variáveis de ambiente
 * Se não existir, usa valor padrão
 */
if (!function_exists('env')) {
    function env($key, $default = null) {
        // Tenta ler de variável de ambiente
        $value = getenv($key);
        
        if ($value === false) {
            // Tenta ler de arquivo .env se existir
            $envFile = __DIR__ . '/../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    list($name, $val) = explode('=', $line, 2);
                    if (trim($name) === $key) {
                        $value = trim($val);
                        break;
                    }
                }
            }
        }
        
        return $value !== false ? $value : $default;
    }
}

