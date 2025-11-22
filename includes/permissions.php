<?php
/**
 * Sistema de Permissões Centralizado
 * 
 * Este arquivo centraliza todas as verificações de permissão do sistema,
 * facilitando manutenção e garantindo consistência.
 */

// Carrega funções necessárias (evita dependência circular)
if (!function_exists('require_login')) {
    require_once __DIR__ . '/functions.php';
    require_once __DIR__ . '/auth.php';
}

/**
 * Carrega permissões customizadas do arquivo JSON (se existir)
 * 
 * @return array Permissões customizadas ou array vazio
 */
function load_custom_permissions() {
    $permissions_file = __DIR__ . '/../config/permissions.json';
    
    if (file_exists($permissions_file)) {
        $content = file_get_contents($permissions_file);
        $permissions = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($permissions)) {
            return $permissions;
        }
    }
    
    return [];
}

/**
 * Mapeamento de permissões por página
 * Define quais roles podem acessar cada página
 * 
 * Se existir arquivo config/permissions.json, as permissões customizadas
 * terão prioridade sobre as padrões.
 */
function get_page_permissions() {
    $default_permissions = [
        // Dashboard - todos podem acessar
        'dashboard.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        
        // Análise de Emoções - ADMIN, RH e GESTOR
        'emocoes_analise.php' => ['ADMIN', 'RH', 'GESTOR'],
        
        // Estrutura - apenas ADMIN e RH
        'empresas.php' => ['ADMIN', 'RH'],
        'setores.php' => ['ADMIN', 'RH'],
        'cargos.php' => ['ADMIN', 'RH'],
        'hierarquia.php' => ['ADMIN', 'RH'],
        'niveis_hierarquicos.php' => ['ADMIN', 'RH'],
        
        // Colaboradores - ADMIN, RH e GESTOR
        'colaboradores.php' => ['ADMIN', 'RH', 'GESTOR'],
        'colaborador_add.php' => ['ADMIN', 'RH'],
        'colaborador_view.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'colaborador_edit.php' => ['ADMIN', 'RH'],
        'promocoes.php' => ['ADMIN', 'RH'],
        'horas_extras.php' => ['ADMIN', 'RH'],
        'fechamento_pagamentos.php' => ['ADMIN', 'RH'],
        'tipos_bonus.php' => ['ADMIN', 'RH'],
        
        // Ocorrências - todos podem acessar (com restrições de dados)
        'ocorrencias_list.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'ocorrencias_add.php' => ['ADMIN', 'RH', 'GESTOR'],
        'tipos_ocorrencias.php' => ['ADMIN', 'RH'],
        
        // Pagamentos - colaborador
        'meus_pagamentos.php' => ['COLABORADOR'],
        
        // Usuários - apenas ADMIN
        'usuarios.php' => ['ADMIN'],
        
        // Notificações - ADMIN e RH
        'enviar_notificacao_push.php' => ['ADMIN', 'RH'],
        'notificacoes_enviadas.php' => ['ADMIN', 'RH'],
        
        // Perfil/Conta - todos podem acessar
        'minha_conta.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        
        // Configurações - apenas ADMIN
        'configuracoes_email.php' => ['ADMIN'],
        'configuracoes_onesignal.php' => ['ADMIN'],
        'templates_email.php' => ['ADMIN'],
        
        // Relatórios - ADMIN, RH e GESTOR
        'relatorio_ocorrencias.php' => ['ADMIN', 'RH', 'GESTOR'],
        
        // Gerenciamento de Permissões - apenas ADMIN
        'permissoes.php' => ['ADMIN'],
        
        // Feed e Emoções - todos podem acessar
        'feed.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'emocoes.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        
        // Configuração de Pontos - apenas ADMIN
        'configuracoes_pontos.php' => ['ADMIN'],
        
        // Endomarketing - ADMIN, RH e GESTOR
        'endomarketing_datas_comemorativas.php' => ['ADMIN', 'RH', 'GESTOR'],
        'endomarketing_acoes.php' => ['ADMIN', 'RH', 'GESTOR'],
        'endomarketing_acao_view.php' => ['ADMIN', 'RH', 'GESTOR'],
    ];
    
    // Carrega permissões customizadas (se existir)
    $custom_permissions = load_custom_permissions();
    
    // Merge: customizadas têm prioridade sobre padrões
    return array_merge($default_permissions, $custom_permissions);
}

/**
 * Verifica se o usuário atual pode acessar uma página específica
 * 
 * @param string $page Nome do arquivo da página (ex: 'dashboard.php')
 * @return bool True se pode acessar, False caso contrário
 */
function can_access_page($page) {
    if (!isset($_SESSION['usuario'])) {
        return false;
    }
    
    $user_role = $_SESSION['usuario']['role'] ?? null;
    
    if (!$user_role) {
        return false;
    }
    
    $permissions = get_page_permissions();
    
    // Se página não está no mapeamento, permite acesso (compatibilidade)
    if (!isset($permissions[$page])) {
        return true;
    }
    
    // Verifica se o role do usuário está na lista de permissões
    return in_array($user_role, $permissions[$page]);
}

/**
 * Verifica permissão e redireciona se não tiver acesso
 * 
 * @param string $page Nome do arquivo da página
 * @param string $redirect_page Página para redirecionar em caso de negação (padrão: dashboard.php)
 * @param string $message Mensagem de erro (padrão: 'Você não tem permissão para acessar esta página.')
 */
function require_page_permission($page, $redirect_page = 'dashboard.php', $message = 'Você não tem permissão para acessar esta página.') {
    require_login();
    
    if (!can_access_page($page)) {
        redirect($redirect_page, $message, 'error');
    }
}

/**
 * Verifica se o usuário tem um dos roles especificados
 * 
 * @param string|array $roles Role único ou array de roles permitidos
 * @return bool True se o usuário tem um dos roles
 */
function has_role($roles) {
    if (!isset($_SESSION['usuario'])) {
        return false;
    }
    
    $user_role = $_SESSION['usuario']['role'] ?? null;
    
    if (!$user_role) {
        return false;
    }
    
    // ADMIN sempre tem acesso
    if ($user_role === 'ADMIN') {
        return true;
    }
    
    // Converte para array se necessário
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($user_role, $roles);
}

/**
 * Verifica se o usuário é colaborador (com ou sem usuário vinculado)
 * 
 * @return bool True se é colaborador
 */
function is_colaborador() {
    if (!isset($_SESSION['usuario'])) {
        return false;
    }
    
    $usuario = $_SESSION['usuario'];
    
    return $usuario['role'] === 'COLABORADOR' || !empty($usuario['colaborador_id']);
}

/**
 * Verifica se o usuário é colaborador sem usuário vinculado
 * 
 * @return bool True se é colaborador sem usuário vinculado
 */
function is_colaborador_sem_usuario() {
    if (!isset($_SESSION['usuario'])) {
        return false;
    }
    
    $usuario = $_SESSION['usuario'];
    
    return $usuario['role'] === 'COLABORADOR' 
        && empty($usuario['id']) 
        && !empty($usuario['colaborador_id']);
}

/**
 * Verifica se o menu item deve ser exibido para o usuário atual
 * 
 * @param string|array $roles Role único ou array de roles permitidos
 * @return bool True se deve exibir o menu
 */
function can_show_menu($roles) {
    return has_role($roles);
}

/**
 * Obtém o nome do arquivo atual
 * 
 * @return string Nome do arquivo (ex: 'dashboard.php')
 */
function get_current_page() {
    return basename($_SERVER['PHP_SELF']);
}

/**
 * Carrega permissões de cards do dashboard
 * 
 * @return array Array com permissões de cards do dashboard
 */
function get_dashboard_cards_permissions() {
    $dashboard_cards_file = __DIR__ . '/../config/dashboard_cards.json';
    
    if (file_exists($dashboard_cards_file)) {
        $permissions = json_decode(file_get_contents($dashboard_cards_file), true);
        if (is_array($permissions)) {
            return $permissions;
        }
    }
    
    // Retorna array vazio se não há permissões customizadas (comportamento padrão: todos podem ver)
    return [];
}

/**
 * Verifica se o usuário atual pode ver um card específico do dashboard
 * 
 * @param string $card_key Chave do card (ex: 'card_emocao_diaria')
 * @return bool True se pode ver, False caso contrário
 */
function can_see_dashboard_card($card_key) {
    if (!isset($_SESSION['usuario'])) {
        return false;
    }
    
    $user_role = $_SESSION['usuario']['role'] ?? null;
    
    if (!$user_role) {
        return false;
    }
    
    // ADMIN sempre pode ver tudo
    if ($user_role === 'ADMIN') {
        return true;
    }
    
    $card_permissions = get_dashboard_cards_permissions();
    
    // Se não há permissões customizadas, permite acesso (comportamento padrão)
    if (empty($card_permissions) || !isset($card_permissions[$card_key])) {
        return true;
    }
    
    // Verifica se o role do usuário está na lista de permissões do card
    return in_array($user_role, $card_permissions[$card_key]);
}

