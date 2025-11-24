<?php
/**
 * Logout do Sistema
 */

require_once __DIR__ . '/includes/session_config.php';

// Inicia sessão para poder destruir
iniciar_sessao_30_dias();

// Limpa todos os dados da sessão
$_SESSION = [];

// Destrói o cookie de sessão
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destrói a sessão
session_destroy();

header('Location: login.php');
exit;

