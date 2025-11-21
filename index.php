<?php
/**
 * Página Inicial - Redireciona para Dashboard
 */

// Configuração de erros para debug (desative em produção se necessário)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibe erros na tela em produção
ini_set('log_errors', 1);

// Tenta carregar os arquivos necessários
try {
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
    
    // Redireciona para dashboard
    header('Location: pages/dashboard.php');
    exit;
    
} catch (Exception $e) {
    // Em caso de erro, redireciona para login ao invés de mostrar erro
    // Isso evita ERR_FAILED no navegador
    if (function_exists('get_login_url')) {
        header('Location: ' . get_login_url());
    } else {
        header('Location: login.php');
    }
    exit;
} catch (Error $e) {
    // Captura erros fatais também
    if (function_exists('get_login_url')) {
        header('Location: ' . get_login_url());
    } else {
        header('Location: login.php');
    }
    exit;
}

