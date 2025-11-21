<?php
/**
 * Página Inicial - Redireciona para Dashboard
 */

// IMPORTANTE: Garante que nenhum output seja enviado antes dos headers
ob_start();

// Configuração de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Inicia sessão ANTES de qualquer coisa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tenta carregar os arquivos necessários
try {
    // Limpa qualquer output que possa ter sido gerado
    ob_clean();
    
    if (!file_exists(__DIR__ . '/includes/functions.php')) {
        throw new Exception('Arquivo includes/functions.php não encontrado');
    }
    require_once __DIR__ . '/includes/functions.php';
    
    if (!file_exists(__DIR__ . '/includes/auth.php')) {
        throw new Exception('Arquivo includes/auth.php não encontrado');
    }
    require_once __DIR__ . '/includes/auth.php';
    
    // Verifica login e redireciona
    require_login();
    
    // Limpa buffer antes do redirect
    ob_end_clean();
    
    // Headers para evitar cache e garantir redirect correto
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Redireciona para dashboard com status code apropriado
    header('Location: pages/dashboard.php', true, 302);
    exit;
    
} catch (Exception $e) {
    // Limpa buffer em caso de erro
    ob_end_clean();
    
    // Headers para evitar cache
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Em caso de erro, redireciona para login
    // Usa URL absoluta para evitar problemas
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $loginUrl = $protocol . '://' . $host . '/rh/login.php';
    
    header('Location: ' . $loginUrl, true, 302);
    exit;
} catch (Error $e) {
    // Limpa buffer em caso de erro fatal
    ob_end_clean();
    
    // Headers para evitar cache
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Em caso de erro fatal, redireciona para login
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $loginUrl = $protocol . '://' . $host . '/rh/login.php';
    
    header('Location: ' . $loginUrl, true, 302);
    exit;
}

