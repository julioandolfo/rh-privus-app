<?php
/**
 * Menu Lateral - Metronic Theme
 */

if (!isset($_SESSION['usuario'])) {
    return;
}

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/chat_functions.php';

$usuario = $_SESSION['usuario'];
$current_page = get_current_page();

// Busca total de mensagens não lidas para o badge do menu
$total_nao_lidas_chat = 0;
try {
    $pdo = getDB();
    if (is_colaborador() && !empty($usuario['colaborador_id'])) {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT m.id) as total
            FROM chat_mensagens m
            INNER JOIN chat_conversas c ON m.conversa_id = c.id
            WHERE c.colaborador_id = ?
            AND m.enviado_por_usuario_id IS NOT NULL
            AND m.lida_por_colaborador = FALSE
            AND m.deletada = FALSE
        ");
        $stmt->execute([$usuario['colaborador_id']]);
        $result = $stmt->fetch();
        $total_nao_lidas_chat = (int)($result['total'] ?? 0);
    } elseif (can_show_menu(['ADMIN', 'RH'])) {
        // ADMIN: conta todas as mensagens não lidas
        // RH: conta apenas mensagens de conversas atribuídas a ele + não atribuídas
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->query("
                SELECT COUNT(DISTINCT m.id) as total
                FROM chat_mensagens m
                INNER JOIN chat_conversas c ON m.conversa_id = c.id
                WHERE m.enviado_por_colaborador_id IS NOT NULL
                AND m.lida_por_rh = FALSE
                AND m.deletada = FALSE
                AND c.status NOT IN ('fechada', 'arquivada', 'resolvida')
            ");
        } else {
            // RH: apenas conversas atribuídas a ele + não atribuídas
            $usuario_id = (int)($usuario['id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT m.id) as total
                FROM chat_mensagens m
                INNER JOIN chat_conversas c ON m.conversa_id = c.id
                WHERE m.enviado_por_colaborador_id IS NOT NULL
                AND m.lida_por_rh = FALSE
                AND m.deletada = FALSE
                AND c.status NOT IN ('fechada', 'arquivada', 'resolvida')
                AND (c.atribuido_para_usuario_id = ? OR c.atribuido_para_usuario_id IS NULL)
            ");
            $stmt->execute([$usuario_id]);
        }
        $result = $stmt->fetch();
        $total_nao_lidas_chat = (int)($result['total'] ?? 0);
    }
} catch (Exception $e) {
    // Ignora erros para não quebrar o menu
    $total_nao_lidas_chat = 0;
}

function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}

function getIcon($name) {
    $icons = [
        'dashboard' => 'element-11',
        'building' => 'bank',
        'diagram' => 'chart-simple',
        'briefcase' => 'briefcase',
        'people' => 'profile-circle',
        'clipboard' => 'notepad',
        'file' => 'file-up',
        'person' => 'profile-user',
        'gear' => 'setting-2',
        'email' => 'sms',
        'notification' => 'notification-status',
        'shield' => 'shield-check',
        'wallet' => 'wallet',
    ];
    return $icons[$name] ?? 'element-11';
}
?>
<!--begin::Aside-->
<div id="kt_aside" class="aside py-9" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '265px'}" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_toggle">
    <!--begin::Aside menu-->
    <div class="aside-menu flex-column-fluid ps-5 pe-3 mb-7" id="kt_aside_menu">
        <!--begin::Aside Menu-->
        <div class="w-100 hover-scroll-y d-flex pe-2" id="kt_aside_menu_wrapper" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_aside_footer, #kt_header" data-kt-scroll-wrappers="#kt_aside, #kt_aside_menu, #kt_aside_menu_wrapper" data-kt-scroll-offset="102">
            <!--begin::Menu-->
            <div class="menu menu-column menu-rounded menu-sub-indention menu-active-bg fw-semibold my-auto" id="#kt_aside_menu" data-kt-menu="true">
                <div class="menu-content px-3 pb-2">
                    <span class="text-muted text-uppercase fw-bold fs-7">Menu</span>
                </div>
                <!--begin:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link <?= isActive('dashboard.php') ?>" href="dashboard.php">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-<?= getIcon('dashboard') ?> fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </span>
                        <span class="menu-title">Dashboard</span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                
                <!--begin:Menu item - Chat-->
                <?php if (can_show_menu(['ADMIN', 'RH']) || is_colaborador()): ?>
                <div class="menu-item">
                    <a class="menu-link <?= isActive('chat_gestao.php') || isActive('chat_colaborador.php') ?>" href="<?= is_colaborador() ? 'chat_colaborador.php' : 'chat_gestao.php' ?>">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-message-text-2 fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </span>
                        <span class="menu-title">Chat</span>
                        <?php if ($total_nao_lidas_chat > 0): ?>
                        <span class="badge badge-circle badge-danger ms-auto"><?= $total_nao_lidas_chat > 99 ? '99+' : $total_nao_lidas_chat ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <!--end:Menu item-->
                <?php endif; ?>
                
                <!--begin:Menu item-->
                <?php if (can_access_feed_menu()): ?>
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link <?= isActive('feed.php') ?>" href="feed.php">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-message-text-2 fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </span>
                        <span class="menu-title">Feed Privus</span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <?php endif; ?>
                
                <!--begin:Menu item-->
                <?php if (can_access_feedbacks_menu()): ?>
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('feedback_enviar.php') || isActive('feedback_meus.php') || isActive('feedback_gestao.php')) ? 'here show' : '' ?>">
                    <!--begin:Menu link-->
                    <span class="menu-link">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-message-text fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </span>
                        <span class="menu-title">Feedbacks</span>
                        <span class="menu-arrow"></span>
                    </span>
                    <!--end:Menu link-->
                    <!--begin:Menu sub-->
                    <div class="menu-sub menu-sub-accordion">
                        <?php if (can_access_page('feedback_enviar.php')): ?>
                        <!--begin:Menu item-->
                        <div class="menu-item">
                            <a class="menu-link <?= isActive('feedback_enviar.php') ?>" href="feedback_enviar.php">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Enviar Feedback</span>
                            </a>
                        </div>
                        <!--end:Menu item-->
                        <?php endif; ?>
                        <?php if (can_access_page('feedback_meus.php')): ?>
                        <!--begin:Menu item-->
                        <div class="menu-item">
                            <a class="menu-link <?= isActive('feedback_meus.php') ?>" href="feedback_meus.php">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Meus Feedbacks</span>
                            </a>
                        </div>
                        <!--end:Menu item-->
                        <?php endif; ?>
                        <?php if (can_access_page('feedback_gestao.php')): ?>
                        <!--begin:Menu item-->
                        <div class="menu-item">
                            <a class="menu-link <?= isActive('feedback_gestao.php') ?>" href="feedback_gestao.php">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Gestão de Feedbacks</span>
                            </a>
                        </div>
                        <!--end:Menu item-->
                        <?php endif; ?>
                    </div>
                    <!--end:Menu sub-->
                </div>
                <!--end:Menu item-->
                <?php endif; ?>
                
                <!--begin:Menu item-->
                <?php if (can_access_endomarketing_menu()): ?>
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('endomarketing_datas_comemorativas.php') || isActive('endomarketing_acoes.php')) ? 'here show' : '' ?>">
                    <!--begin:Menu link-->
                    <span class="menu-link">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-gift fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </span>
                        <span class="menu-title">Endomarketing</span>
                        <span class="menu-arrow"></span>
                    </span>
                    <!--end:Menu link-->
                    <!--begin:Menu sub-->
                    <div class="menu-sub menu-sub-accordion">
                        <?php if (can_access_page('endomarketing_datas_comemorativas.php')): ?>
                        <!--begin:Menu item-->
                        <div class="menu-item">
                            <a class="menu-link <?= isActive('endomarketing_datas_comemorativas.php') ?>" href="endomarketing_datas_comemorativas.php">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Datas Comemorativas</span>
                            </a>
                        </div>
                        <!--end:Menu item-->
                        <?php endif; ?>
                        <?php if (can_access_page('endomarketing_acoes.php')): ?>
                        <!--begin:Menu item-->
                        <div class="menu-item">
                            <a class="menu-link <?= isActive('endomarketing_acoes.php') ?>" href="endomarketing_acoes.php">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Ações</span>
                            </a>
                        </div>
                        <!--end:Menu item-->
                        <?php endif; ?>
                    </div>
                    <!--end:Menu sub-->
                </div>
                <!--end:Menu item-->
                <?php endif; ?>
                
                <?php if (can_access_recrutamento()): ?>
                    <!--begin:Menu item - Recrutamento e Seleção-->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('vagas.php') || isActive('kanban_selecao.php') || isActive('candidaturas.php')) ? 'here show' : '' ?>">
                        <!--begin:Menu link-->
                        <span class="menu-link">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-people fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Recrutamento</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <!--end:Menu link-->
                        <!--begin:Menu sub-->
                        <div class="menu-sub menu-sub-accordion">
                            <?php if (can_access_page('vagas.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('vagas.php') ?>" href="vagas.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Vagas</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('portal_vagas_config.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('portal_vagas_config.php') ?>" href="portal_vagas_config.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Configurar Portal Público</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('kanban_selecao.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('kanban_selecao.php') ?>" href="kanban_selecao.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Kanban de Seleção</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('candidaturas.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('candidaturas.php') ?>" href="candidaturas.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Candidaturas</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('entrevistas.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('entrevistas.php') ?>" href="entrevistas.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Entrevistas</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('etapas_processo.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('etapas_processo.php') ?>" href="etapas_processo.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Etapas do Processo</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('formularios_cultura.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('formularios_cultura.php') ?>" href="formularios_cultura.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Formulários de Cultura</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('automatizacoes_kanban.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('automatizacoes_kanban.php') ?>" href="automatizacoes_kanban.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Automações</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('onboarding.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('onboarding.php') ?>" href="onboarding.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Onboarding</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('analytics_recrutamento.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('analytics_recrutamento.php') ?>" href="analytics_recrutamento.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Analytics</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                    <?php endif; ?>
                    
                    <?php if (can_access_estrutura()): ?>
                    <!--begin:Menu item-->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('empresas.php') || isActive('setores.php') || isActive('cargos.php')) ? 'here show' : '' ?>">
                        <!--begin:Menu link-->
                        <span class="menu-link">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-<?= getIcon('building') ?> fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </span>
                            <span class="menu-title">Estrutura</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <!--end:Menu link-->
                        <!--begin:Menu sub-->
                        <div class="menu-sub menu-sub-accordion">
                            <?php if (can_access_page('empresas.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('empresas.php') ?>" href="empresas.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Empresas</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('setores.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('setores.php') ?>" href="setores.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Setores</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('cargos.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('cargos.php') ?>" href="cargos.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Cargos</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                    
                    <?php if (can_access_page('hierarquia.php') || can_access_page('niveis_hierarquicos.php')): ?>
                    <!--begin:Menu item-->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('hierarquia.php') || isActive('niveis_hierarquicos.php')) ? 'here show' : '' ?>">
                        <!--begin:Menu link-->
                        <span class="menu-link">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-<?= getIcon('diagram') ?> fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Hierarquia</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <!--end:Menu link-->
                        <!--begin:Menu sub-->
                        <div class="menu-sub menu-sub-accordion">
                            <?php if (can_access_page('hierarquia.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('hierarquia.php') ?>" href="hierarquia.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Organograma</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('niveis_hierarquicos.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('niveis_hierarquicos.php') ?>" href="niveis_hierarquicos.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Níveis Hierárquicos</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                    <?php endif; ?>
                    <?php endif; ?>
                
                <?php if (can_access_page('comunicados.php') || can_access_page('comunicado_add.php')): ?>
                    <!--begin:Menu item - Comunicados-->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('comunicados.php') || isActive('comunicado_add.php') || isActive('comunicado_view.php')) ? 'here show' : '' ?>">
                        <!--begin:Menu link-->
                        <span class="menu-link">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-message-text fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </span>
                            <span class="menu-title">Comunicados</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <!--end:Menu link-->
                        <!--begin:Menu sub-->
                        <div class="menu-sub menu-sub-accordion">
                            <?php if (can_access_page('comunicado_add.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('comunicado_add.php') ?>" href="comunicado_add.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Adicionar Novo Comunicado</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('comunicados.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('comunicados.php') ?>" href="comunicados.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Ver Comunicados</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                <?php endif; ?>
                
                <?php if (can_access_colaboradores_menu()): ?>
                    <!--begin:Menu item-->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('colaboradores.php') || isActive('emocoes_analise.php') || isActive('promocoes.php') || isActive('horas_extras.php') || isActive('fechamento_pagamentos.php')) ? 'here show' : '' ?>">
                        <!--begin:Menu link-->
                        <span class="menu-link">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-<?= getIcon('people') ?> fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Colaboradores</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <!--end:Menu link-->
                        <!--begin:Menu sub-->
                        <div class="menu-sub menu-sub-accordion">
                            <?php if (can_access_page('colaboradores.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('colaboradores.php') ?>" href="colaboradores.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Listar Colaboradores</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('emocoes_analise.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('emocoes_analise.php') ?>" href="emocoes_analise.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Análise de Emoções</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('promocoes.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('promocoes.php') ?>" href="promocoes.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Promoções</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('horas_extras.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('horas_extras.php') ?>" href="horas_extras.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Horas Extras</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('fechamento_pagamentos.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('fechamento_pagamentos.php') ?>" href="fechamento_pagamentos.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Fechamento de Pagamentos</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('tipos_bonus.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('tipos_bonus.php') ?>" href="tipos_bonus.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Tipos de Bônus</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('contratos.php') || can_access_page('contrato_add.php') || can_access_page('contrato_templates.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= (isActive('contratos.php') || isActive('contrato_add.php') || isActive('contrato_view.php') || isActive('contrato_templates.php') || isActive('contrato_template_add.php') || isActive('contrato_template_edit.php')) ? 'active' : '' ?>" href="contratos.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Contratos</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                    
                    <?php if (can_access_ocorrencias_menu()): ?>
                    <!--begin:Menu item-->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('ocorrencias_list.php') || isActive('ocorrencias_add.php') || isActive('ocorrencias_rapida.php') || isActive('tipos_ocorrencias.php') || isActive('categorias_ocorrencias.php') || isActive('relatorio_ocorrencias_avancado.php')) ? 'here show' : '' ?>">
                        <!--begin:Menu link-->
                        <span class="menu-link">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-<?= getIcon('clipboard') ?> fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Ocorrências</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <!--end:Menu link-->
                        <!--begin:Menu sub-->
                        <div class="menu-sub menu-sub-accordion">
                            <?php if (can_access_page('ocorrencias_list.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('ocorrencias_list.php') ?>" href="ocorrencias_list.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Listar Ocorrências</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('ocorrencias_add.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('ocorrencias_add.php') ?>" href="ocorrencias_add.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Nova Ocorrência</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('ocorrencias_rapida.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('ocorrencias_rapida.php') ?>" href="ocorrencias_rapida.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">
                                        <span class="badge badge-light-danger ms-2">Rápida</span>
                                        Ocorrência Rápida
                                    </span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('relatorio_ocorrencias_avancado.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('relatorio_ocorrencias_avancado.php') ?>" href="relatorio_ocorrencias_avancado.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Analytics & Dashboard</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('tipos_ocorrencias.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('tipos_ocorrencias.php') ?>" href="tipos_ocorrencias.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Tipos de Ocorrências</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('categorias_ocorrencias.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('categorias_ocorrencias.php') ?>" href="categorias_ocorrencias.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Categorias</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                    <?php endif; ?>
                    
                    <?php if (can_access_page('relatorio_ocorrencias.php')): ?>
                    <!--begin:Menu item-->
                    <div class="menu-item">
                        <a class="menu-link <?= isActive('relatorio_ocorrencias.php') ?>" href="relatorio_ocorrencias.php">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-<?= getIcon('file') ?> fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Relatórios</span>
                        </a>
                    </div>
                    <!--end:Menu item-->
                    <?php endif; ?>
                    
                    <?php if (can_access_notificacoes_push_menu()): ?>
                    <!--begin:Menu item-->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('enviar_notificacao_push.php') || isActive('notificacoes_enviadas.php')) ? 'here show' : '' ?>">
                        <!--begin:Menu link-->
                        <span class="menu-link">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-<?= getIcon('notification') ?> fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </span>
                            <span class="menu-title">Notificações Push</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <!--end:Menu link-->
                        <!--begin:Menu sub-->
                        <div class="menu-sub menu-sub-accordion">
                            <?php if (can_access_page('enviar_notificacao_push.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('enviar_notificacao_push.php') ?>" href="enviar_notificacao_push.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Enviar Notificação Push</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('notificacoes_enviadas.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('notificacoes_enviadas.php') ?>" href="notificacoes_enviadas.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Notificações Enviadas</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (can_access_engajamento_menu() || can_access_colaboradores_menu() || can_access_ocorrencias_menu() || can_access_notificacoes_push_menu()): ?>
                    <!--begin:Menu separator-->
                    <div class="menu-item pt-5">
                        <div class="menu-content">
                            <span class="menu-heading fw-bold text-uppercase fs-7">Gestão</span>
                        </div>
                    </div>
                    <!--end:Menu separator-->
                <?php endif; ?>
                
                <?php if (can_access_engajamento_menu()): ?>
                    <!--begin:Menu item-->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('gestao_engajamento.php') || isActive('reunioes_1on1.php') || isActive('celebracoes.php') || isActive('pesquisas_satisfacao.php') || isActive('pesquisas_rapidas.php') || isActive('pdis.php')) ? 'here show' : '' ?>">
                        <!--begin:Menu link-->
                        <span class="menu-link">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-chart-simple fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </span>
                            <span class="menu-title">Engajamento</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <!--end:Menu link-->
                        <!--begin:Menu sub-->
                        <div class="menu-sub menu-sub-accordion">
                            <?php if (can_access_page('gestao_engajamento.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('gestao_engajamento.php') ?>" href="gestao_engajamento.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Painel de Engajamento</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('reunioes_1on1.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('reunioes_1on1.php') ?>" href="reunioes_1on1.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Reuniões 1:1</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('celebracoes.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('celebracoes.php') ?>" href="celebracoes.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Celebrações</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('pesquisas_satisfacao.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('pesquisas_satisfacao.php') ?>" href="pesquisas_satisfacao.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Pesquisas de Satisfação</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('pesquisas_rapidas.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('pesquisas_rapidas.php') ?>" href="pesquisas_rapidas.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Pesquisas Rápidas</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('pdis.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('pdis.php') ?>" href="pdis.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title"><?= is_colaborador() ? 'Meus PDIs' : 'PDIs' ?></span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                <?php endif; ?>
                
                <?php if (can_show_menu('ADMIN') || can_access_configuracoes()): ?>
                    <!--begin:Menu separator-->
                    <div class="menu-item pt-5">
                        <div class="menu-content">
                            <span class="menu-heading fw-bold text-uppercase fs-7">Configurações</span>
                        </div>
                    </div>
                    <!--end:Menu separator-->
                    
                    <?php if (can_show_menu('ADMIN')): ?>
                    <!--begin:Menu item-->
                    <div class="menu-item">
                        <a class="menu-link <?= isActive('usuarios.php') ?>" href="usuarios.php">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-<?= getIcon('gear') ?> fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Usuários</span>
                        </a>
                    </div>
                    <!--end:Menu item-->
                    <?php endif; ?>
                    
                    <!--begin:Menu item-->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('configuracoes_email.php') || isActive('configuracoes_onesignal.php') || isActive('permissoes.php') || isActive('chat_configuracoes.php') || isActive('configuracoes_pontos.php') || isActive('templates_email.php') || isActive('configuracoes_autentique.php')) ? 'here show' : '' ?>">
                        <!--begin:Menu link-->
                        <span class="menu-link">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-<?= getIcon('gear') ?> fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Configurações</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <!--end:Menu link-->
                        <!--begin:Menu sub-->
                        <div class="menu-sub menu-sub-accordion">
                            <?php if (can_access_page('permissoes.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('permissoes.php') ?>" href="permissoes.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Permissões</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('configuracoes_email.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('configuracoes_email.php') ?>" href="configuracoes_email.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Configurações de Email</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('templates_email.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('templates_email.php') ?>" href="templates_email.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Templates de Email</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('configuracoes_onesignal.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('configuracoes_onesignal.php') ?>" href="configuracoes_onesignal.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Configuração OneSignal</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('chat_configuracoes.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('chat_configuracoes.php') ?>" href="chat_configuracoes.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Chat</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('configuracoes_pontos.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('configuracoes_pontos.php') ?>" href="configuracoes_pontos.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Pontos</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                            <?php if (can_access_page('configuracoes_autentique.php')): ?>
                            <!--begin:Menu item-->
                            <div class="menu-item">
                                <a class="menu-link <?= isActive('configuracoes_autentique.php') ?>" href="configuracoes_autentique.php">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Autentique</span>
                                </a>
                            </div>
                            <!--end:Menu item-->
                            <?php endif; ?>
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                <?php endif; ?>
                
                <!--begin:Menu item-->
                <div class="menu-item">
                    <a class="menu-link <?= isActive('minha_conta.php') ?>" href="minha_conta.php">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-user fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <span class="menu-title">Minha Conta</span>
                    </a>
                </div>
                <!--end:Menu item-->
            </div>
            <!--end:Menu-->
        </div>
        <!--end:Aside Menu-->
    </div>
    <!--end:Aside menu-->
    
    <!--begin::Footer-->
    <div class="aside-footer flex-column-auto px-9" id="kt_aside_footer">
        <!--begin::User panel-->
        <div class="d-flex flex-stack">
            <!--begin::Wrapper-->
            <div class="d-flex align-items-center">
                <!--begin::Avatar-->
                <div class="symbol symbol-circle symbol-40px">
                    <div class="symbol-label fs-2 fw-semibold bg-primary text-white">
                        <?= strtoupper(substr($usuario['nome'], 0, 1)) ?>
                    </div>
                </div>
                <!--end::Avatar-->
                <!--begin::User info-->
                <div class="ms-2">
                    <!--begin::Name-->
                    <a href="#" class="text-gray-800 text-hover-primary fs-6 fw-bold lh-1"><?= htmlspecialchars($usuario['nome']) ?></a>
                    <!--end::Name-->
                    <!--begin::Major-->
                    <span class="text-muted fw-semibold d-block fs-7 lh-1"><?= $usuario['role'] ?></span>
                    <!--end::Major-->
                </div>
                <!--end::User info-->
            </div>
            <!--end::Wrapper-->
        </div>
        <!--end::User panel-->
    </div>
    <!--end::Footer-->
</div>
<!--end::Aside-->
