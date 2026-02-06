<?php
/**
 * Configuração de Debug do Sistema
 * 
 * Para DESATIVAR o debug, altere DEBUG_MODE para false
 */

// Define se o modo debug está ativo
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    // Mostrar todos os erros
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
    
    // Configurações adicionais para debug
    ini_set('html_errors', 1);
    ini_set('ignore_repeated_errors', 0);
    
    // Handler de erros customizado para melhor visualização
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        $errorTypes = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED',
        ];
        
        // E_STRICT foi removido no PHP 8.4 - pular para evitar deprecation warning
        
        $type = $errorTypes[$errno] ?? 'UNKNOWN';
        
        echo "<div style='background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;margin:10px;border-radius:5px;font-family:monospace;'>";
        echo "<strong>[$type]</strong> $errstr<br>";
        echo "<small>Arquivo: $errfile | Linha: $errline</small>";
        echo "</div>";
        
        // Log no arquivo também
        error_log("[$type] $errstr in $errfile on line $errline");
        
        return false; // Permite que o handler padrão do PHP também seja executado
    });
    
    // Handler de exceções não capturadas
    set_exception_handler(function($exception) {
        echo "<div style='background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;margin:10px;border-radius:5px;font-family:monospace;'>";
        echo "<strong>[EXCEPTION]</strong> " . $exception->getMessage() . "<br>";
        echo "<small>Arquivo: " . $exception->getFile() . " | Linha: " . $exception->getLine() . "</small><br>";
        echo "<pre style='margin-top:10px;background:#fff;padding:10px;border-radius:3px;overflow-x:auto;'>" . $exception->getTraceAsString() . "</pre>";
        echo "</div>";
        
        error_log("EXCEPTION: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    });
    
} else {
    // Modo produção - oculta erros
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}
