<?php
/**
 * Configuração de Sessão - Duração de 30 dias
 * 
 * Este arquivo configura a sessão PHP para durar 30 dias
 */

/**
 * Configura a sessão para durar 30 dias
 * Deve ser chamado ANTES de session_start()
 */
function configurar_sessao_30_dias() {
    // 30 dias em segundos
    $dias = 30;
    $segundos = $dias * 24 * 60 * 60; // 2.592.000 segundos
    
    // Configura o tempo de vida do cookie de sessão para 30 dias
    ini_set('session.cookie_lifetime', $segundos);
    
    // Configura o tempo máximo de vida da sessão no servidor para 30 dias
    ini_set('session.gc_maxlifetime', $segundos);
    
    // Configura o cookie de sessão para ser persistente (não expira ao fechar navegador)
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Mude para 1 se usar HTTPS sempre
    ini_set('session.use_only_cookies', 1);
    
    // Configura parâmetros do cookie de sessão
    session_set_cookie_params([
        'lifetime' => $segundos,
        'path' => '/',
        'domain' => '', // Deixe vazio para usar o domínio atual
        'secure' => false, // Mude para true se usar HTTPS sempre
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Inicia sessão com configuração de 30 dias
 * Usa esta função ao invés de session_start() diretamente
 */
function iniciar_sessao_30_dias() {
    if (session_status() === PHP_SESSION_NONE) {
        configurar_sessao_30_dias();
        session_start();
        
        // Renova o cookie de sessão apenas se já houver usuário logado
        if (isset($_SESSION['usuario'])) {
            renovar_cookie_sessao();
        }
    }
}

/**
 * Renova o cookie de sessão para manter por mais 30 dias
 * Deve ser chamado após cada session_start() bem-sucedido
 */
function renovar_cookie_sessao() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $dias = 30;
        $segundos = $dias * 24 * 60 * 60;
        
        // Renova o cookie de sessão
        $params = session_get_cookie_params();
        
        // Usa setcookie com SameSite se disponível (PHP 7.3+)
        if (PHP_VERSION_ID >= 70300) {
            setcookie(
                session_name(),
                session_id(),
                [
                    'expires' => time() + $segundos,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax'
                ]
            );
        } else {
            setcookie(
                session_name(),
                session_id(),
                time() + $segundos,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
    }
}

/**
 * Verifica se a sessão está próxima de expirar e renova se necessário
 * Deve ser chamado em cada requisição após verificar login
 */
function verificar_e_renovar_sessao() {
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['usuario'])) {
        // Renova o cookie para mais 30 dias
        renovar_cookie_sessao();
        
        // Atualiza timestamp da última atividade na sessão
        $_SESSION['ultima_atividade'] = time();
    }
}

