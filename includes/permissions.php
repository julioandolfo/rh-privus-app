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
        'solicitar_horas_extras.php' => ['COLABORADOR'],
        'aprovar_horas_extras.php' => ['ADMIN', 'RH'],
        'fechamento_pagamentos.php' => ['ADMIN', 'RH'],
        'tipos_bonus.php' => ['ADMIN', 'RH'],
        
        // Ocorrências - todos podem acessar (com restrições de dados)
        'ocorrencias_list.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'ocorrencias_add.php' => ['ADMIN', 'RH', 'GESTOR'],
        'ocorrencias_rapida.php' => ['ADMIN', 'RH', 'GESTOR'],
        'categorias_ocorrencias.php' => ['ADMIN', 'RH'],
        'relatorio_ocorrencias_avancado.php' => ['ADMIN', 'RH', 'GESTOR'],
        'tipos_ocorrencias.php' => ['ADMIN', 'RH'],
        'flags_view.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        
        // Pagamentos - colaborador
        'meus_pagamentos.php' => ['COLABORADOR'],
        
        // Usuários - apenas ADMIN
        'usuarios.php' => ['ADMIN'],
        
        // Notificações - ADMIN e RH
        'enviar_notificacao_push.php' => ['ADMIN', 'RH'],
        'notificacoes_enviadas.php' => ['ADMIN', 'RH'],
        
        // Chat - ADMIN e RH para gestão, COLABORADOR para visualização
        'chat_gestao.php' => ['ADMIN', 'RH'],
        'chat_colaborador.php' => ['COLABORADOR'],
        'chat_configuracoes.php' => ['ADMIN', 'RH'],
        
        // Perfil/Conta - todos podem acessar
        'minha_conta.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        
        // Configurações - apenas ADMIN
        'configuracoes_email.php' => ['ADMIN'],
        'configuracoes_onesignal.php' => ['ADMIN'],
        'templates_email.php' => ['ADMIN'],
        
        // Relatórios - ADMIN, RH e GESTOR
        'relatorio_ocorrencias.php' => ['ADMIN', 'RH', 'GESTOR'],
        'relatorios_fechamentos_extras.php' => ['ADMIN', 'RH'],
        
        // LMS - Escola Privus
        // LMS - Antigo (compatibilidade)
        'lms/cursos.php' => ['ADMIN', 'RH'],
        'lms/curso_add.php' => ['ADMIN', 'RH'],
        'lms/curso_edit.php' => ['ADMIN', 'RH'],
        'lms/curso_view.php' => ['ADMIN', 'RH'],
        'lms/aulas.php' => ['ADMIN', 'RH'],
        'lms/aula_add.php' => ['ADMIN', 'RH'],
        'lms/aula_edit.php' => ['ADMIN', 'RH'],
        'lms/categorias_cursos.php' => ['ADMIN', 'RH'],
        'lms/avaliacoes.php' => ['ADMIN', 'RH'],
        'lms/avaliacao_add.php' => ['ADMIN', 'RH'],
        'lms/relatorios_lms.php' => ['ADMIN', 'RH'],
        'lms/certificados_gerenciar.php' => ['ADMIN', 'RH'],
        'lms/badges.php' => ['ADMIN', 'RH'],
        'lms/cursos_obrigatorios.php' => ['ADMIN', 'RH'],
        'lms/portal/meus_cursos.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'lms/portal/curso_detalhes.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'lms/portal/player_aula.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'lms/portal/meu_progresso.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'lms/portal/meus_certificados.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'lms/portal/minhas_conquistas.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        
        // LMS - Novo (pages direto com prefixo lms_)
        'lms_cursos.php' => ['ADMIN', 'RH'],
        'lms_curso_add.php' => ['ADMIN', 'RH'],
        'lms_curso_edit.php' => ['ADMIN', 'RH'],
        'lms_curso_view.php' => ['ADMIN', 'RH'],
        'lms_aulas.php' => ['ADMIN', 'RH'],
        'lms_aula_add.php' => ['ADMIN', 'RH'],
        'lms_aula_edit.php' => ['ADMIN', 'RH'],
        'lms_categorias_cursos.php' => ['ADMIN', 'RH'],
        'lms_avaliacoes.php' => ['ADMIN', 'RH'],
        'lms_avaliacao_add.php' => ['ADMIN', 'RH'],
        'lms_relatorios.php' => ['ADMIN', 'RH'],
        'lms_certificados_gerenciar.php' => ['ADMIN', 'RH'],
        'lms_badges.php' => ['ADMIN', 'RH'],
        'lms_cursos_obrigatorios.php' => ['ADMIN', 'RH'],
        'lms_meus_cursos.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'lms_curso_detalhes.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'lms_player_aula.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'lms_meu_progresso.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'lms_meus_certificados.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'lms_minhas_conquistas.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'relatorio_adiantamentos_pendentes.php' => ['ADMIN', 'RH'],
        'templates_fechamento_extra.php' => ['ADMIN', 'RH'],
        
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
        
        // Feedbacks - todos podem enviar e ver seus próprios feedbacks
        'feedback_enviar.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'feedback_meus.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'feedback_gestao.php' => ['ADMIN', 'RH'],
        'ver_feedback.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        
        // Notificações - todos podem ver suas próprias notificações
        'notificacoes.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        
        // Gestão > Engajamento - ADMIN, RH e GESTOR
        'gestao_engajamento.php' => ['ADMIN', 'RH', 'GESTOR'],
        'reunioes_1on1.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'celebracoes.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'pesquisas_satisfacao.php' => ['ADMIN', 'RH', 'GESTOR'],
        'pesquisas_rapidas.php' => ['ADMIN', 'RH', 'GESTOR'],
        'pesquisas_colaborador.php' => ['COLABORADOR'],
        'pdis.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'responder_pesquisa.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'], // Página pública para responder pesquisas
        
        // Recrutamento e Seleção - ADMIN e RH
        'vagas.php' => ['ADMIN', 'RH'],
        'vaga_add.php' => ['ADMIN', 'RH'],
        'vaga_edit.php' => ['ADMIN', 'RH'],
        'vaga_view.php' => ['ADMIN', 'RH', 'GESTOR'],
        'vaga_landing_page.php' => ['ADMIN', 'RH'],
        'portal_vagas_config.php' => ['ADMIN', 'RH'],
        'candidaturas.php' => ['ADMIN', 'RH', 'GESTOR'],
        'candidatura_view.php' => ['ADMIN', 'RH', 'GESTOR'],
        'kanban_selecao.php' => ['ADMIN', 'RH', 'GESTOR'],
        'etapas_processo.php' => ['ADMIN', 'RH'],
        'automatizacoes_kanban.php' => ['ADMIN', 'RH'],
        'formularios_cultura.php' => ['ADMIN', 'RH'],
        'formulario_cultura_editar.php' => ['ADMIN', 'RH'],
        'formulario_cultura_analytics.php' => ['ADMIN', 'RH'],
        'entrevistas.php' => ['ADMIN', 'RH', 'GESTOR'],
        'onboarding.php' => ['ADMIN', 'RH'],
        'kanban_onboarding.php' => ['ADMIN', 'RH'],
        'analytics_recrutamento.php' => ['ADMIN', 'RH'],
        
        // Comunicados - ADMIN e RH podem criar/gerenciar, todos podem ver
        'comunicados.php' => ['ADMIN', 'RH'],
        'comunicado_add.php' => ['ADMIN', 'RH'],
        'comunicado_view.php' => ['ADMIN', 'RH'],
        
        // Contratos - ADMIN e RH
        'contratos.php' => ['ADMIN', 'RH'],
        'contrato_add.php' => ['ADMIN', 'RH'],
        'contrato_view.php' => ['ADMIN', 'RH'],
        'contrato_templates.php' => ['ADMIN', 'RH'],
        'contrato_template_add.php' => ['ADMIN', 'RH'],
        'contrato_template_edit.php' => ['ADMIN', 'RH'],
        
        // Configurações Autentique - apenas ADMIN
        'configuracoes_autentique.php' => ['ADMIN'],
        
        // Manual de Conduta - todos podem visualizar
        'manual_conduta_view.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'faq_view.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        
        // Edição Manual de Conduta - apenas ADMIN
        'manual_conduta_edit.php' => ['ADMIN'],
        'faq_edit.php' => ['ADMIN'],
        'manual_conduta_estatisticas.php' => ['ADMIN'],
        'manual_conduta_exportar_pdf.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        
        // Manuais Individuais - ADMIN, RH e GESTOR para gestão, COLABORADOR para visualização
        'manuais_individuais.php' => ['ADMIN', 'RH', 'GESTOR'],
        'manual_individuais_add.php' => ['ADMIN', 'RH', 'GESTOR'],
        'manual_individuais_edit.php' => ['ADMIN', 'RH', 'GESTOR'],
        'manual_individuais_view.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
        'meus_manuais_individuais.php' => ['COLABORADOR'],
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
 * Verifica se o usuário tem acesso a pelo menos uma página de um grupo de páginas
 * 
 * @param array $pages Array com nomes das páginas a verificar
 * @return bool True se tem acesso a pelo menos uma página
 */
function can_access_any_page($pages) {
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
    
    $permissions = get_page_permissions();
    
    // Verifica se tem acesso a pelo menos uma página
    foreach ($pages as $page) {
        if (isset($permissions[$page]) && in_array($user_role, $permissions[$page])) {
            return true;
        }
    }
    
    return false;
}

/**
 * Verifica se o usuário tem acesso a pelo menos uma página de configuração
 * 
 * @return bool True se tem acesso a pelo menos uma página de configuração
 */
function can_access_configuracoes() {
    $config_pages = [
        'permissoes.php',
        'configuracoes_email.php',
        'configuracoes_onesignal.php',
        'configuracoes_pontos.php',
        'templates_email.php',
        'chat_configuracoes.php'
    ];
    
    return can_access_any_page($config_pages);
}

/**
 * Verifica se o usuário tem acesso a pelo menos uma página de recrutamento
 * 
 * @return bool True se tem acesso a pelo menos uma página de recrutamento
 */
function can_access_recrutamento() {
    $recrutamento_pages = [
        'vagas.php',
        'kanban_selecao.php',
        'candidaturas.php',
        'entrevistas.php',
        'etapas_processo.php',
        'formularios_cultura.php',
        'automatizacoes_kanban.php',
        'onboarding.php',
        'kanban_onboarding.php',
        'analytics_recrutamento.php',
        'portal_vagas_config.php'
    ];
    
    return can_access_any_page($recrutamento_pages);
}

/**
 * Verifica se o usuário tem acesso a pelo menos uma página de estrutura
 * 
 * @return bool True se tem acesso a pelo menos uma página de estrutura
 */
function can_access_estrutura() {
    $estrutura_pages = [
        'empresas.php',
        'setores.php',
        'cargos.php',
        'hierarquia.php',
        'niveis_hierarquicos.php'
    ];
    
    return can_access_any_page($estrutura_pages);
}

/**
 * Verifica se o usuário tem acesso ao LMS (Escola Privus)
 * 
 * @return bool True se tem acesso a pelo menos uma página do LMS
 */
function can_access_lms_menu() {
    $lms_pages = [
        // LMS - Antigo (compatibilidade)
        'lms/cursos.php',
        'lms/curso_add.php',
        'lms/curso_edit.php',
        'lms/curso_view.php',
        'lms/aulas.php',
        'lms/aula_add.php',
        'lms/aula_edit.php',
        'lms/categorias_cursos.php',
        'lms/avaliacoes.php',
        'lms/avaliacao_add.php',
        'lms/relatorios_lms.php',
        'lms/certificados_gerenciar.php',
        'lms/badges.php',
        'lms/cursos_obrigatorios.php',
        'lms/portal/meus_cursos.php',
        'lms/portal/curso_detalhes.php',
        'lms/portal/player_aula.php',
        'lms/portal/meu_progresso.php',
        'lms/portal/meus_certificados.php',
        'lms/portal/minhas_conquistas.php',
        
        // LMS - Novo (pages direto com prefixo lms_)
        'lms_cursos.php',
        'lms_curso_add.php',
        'lms_curso_edit.php',
        'lms_curso_view.php',
        'lms_aulas.php',
        'lms_aula_add.php',
        'lms_aula_edit.php',
        'lms_categorias_cursos.php',
        'lms_avaliacoes.php',
        'lms_avaliacao_add.php',
        'lms_relatorios.php',
        'lms_certificados_gerenciar.php',
        'lms_badges.php',
        'lms_cursos_obrigatorios.php',
        'lms_meus_cursos.php',
        'lms_curso_detalhes.php',
        'lms_player_aula.php',
        'lms_meu_progresso.php',
        'lms_meus_certificados.php',
        'lms_minhas_conquistas.php'
    ];
    
    return can_access_any_page($lms_pages);
}

/**
 * Verifica se o usuário tem acesso a pelo menos uma página de colaboradores
 * 
 * @return bool True se tem acesso a pelo menos uma página de colaboradores
 */
function can_access_colaboradores_menu() {
    // Colaboradores não devem ver o menu "Colaboradores" (apenas "Meus Pagamentos")
    if (is_colaborador()) {
        return false;
    }
    
    $colaboradores_pages = [
        'colaboradores.php',
        'colaborador_add.php',
        'colaborador_view.php',
        'colaborador_edit.php',
        'emocoes_analise.php',
        'promocoes.php',
        'horas_extras.php',
        'aprovar_horas_extras.php',
        'fechamento_pagamentos.php',
        'tipos_bonus.php'
    ];
    
    return can_access_any_page($colaboradores_pages);
}

/**
 * Verifica se o usuário tem acesso a pelo menos uma página de ocorrências
 * 
 * @return bool True se tem acesso a pelo menos uma página de ocorrências
 */
function can_access_ocorrencias_menu() {
    $ocorrencias_pages = [
        'ocorrencias_list.php',
        'ocorrencias_add.php',
        'ocorrencias_rapida.php',
        'tipos_ocorrencias.php',
        'categorias_ocorrencias.php',
        'relatorio_ocorrencias_avancado.php',
        'relatorio_ocorrencias.php'
    ];
    
    return can_access_any_page($ocorrencias_pages);
}

/**
 * Verifica se o usuário tem acesso a pelo menos uma página de engajamento
 * 
 * @return bool True se tem acesso a pelo menos uma página de engajamento
 */
function can_access_engajamento_menu() {
    $engajamento_pages = [
        'gestao_engajamento.php',
        'reunioes_1on1.php',
        'celebracoes.php',
        'pesquisas_satisfacao.php',
        'pesquisas_rapidas.php',
        'pdis.php'
    ];
    
    return can_access_any_page($engajamento_pages);
}

/**
 * Verifica se o usuário tem acesso a pelo menos uma página de notificações push
 * 
 * @return bool True se tem acesso a pelo menos uma página de notificações push
 */
function can_access_notificacoes_push_menu() {
    $notificacoes_pages = [
        'enviar_notificacao_push.php',
        'notificacoes_enviadas.php'
    ];
    
    return can_access_any_page($notificacoes_pages);
}

/**
 * Verifica se o usuário tem acesso a pelo menos uma página de feedbacks
 * 
 * @return bool True se tem acesso a pelo menos uma página de feedbacks
 */
function can_access_feedbacks_menu() {
    $feedbacks_pages = [
        'feedback_enviar.php',
        'feedback_meus.php',
        'feedback_gestao.php',
        'ver_feedback.php'
    ];
    
    return can_access_any_page($feedbacks_pages);
}

/**
 * Verifica se o usuário tem acesso a pelo menos uma página de endomarketing
 * 
 * @return bool True se tem acesso a pelo menos uma página de endomarketing
 */
function can_access_endomarketing_menu() {
    $endomarketing_pages = [
        'endomarketing_datas_comemorativas.php',
        'endomarketing_acoes.php',
        'endomarketing_acao_view.php'
    ];
    
    return can_access_any_page($endomarketing_pages);
}

/**
 * Verifica se o usuário tem acesso a pelo menos uma página de feed
 * 
 * @return bool True se tem acesso a pelo menos uma página de feed
 */
function can_access_feed_menu() {
    $feed_pages = [
        'feed.php',
        'emocoes.php'
    ];
    
    return can_access_any_page($feed_pages);
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

