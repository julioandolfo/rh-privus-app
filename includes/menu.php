<?php
/**
 * Menu Lateral - Metronic Theme
 */

if (!isset($_SESSION['usuario'])) {
    return;
}

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);

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
                
                <?php if ($usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH'): ?>
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
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                    
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
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                <?php endif; ?>
                
                <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                    <!--begin:Menu item-->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('colaboradores.php') || isActive('promocoes.php') || isActive('horas_extras.php') || isActive('fechamento_pagamentos.php')) ? 'here show' : '' ?>">
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
                            <?php if ($usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH'): ?>
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
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                    
                    <!--begin:Menu item-->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('ocorrencias_list.php') || isActive('ocorrencias_add.php') || isActive('tipos_ocorrencias.php')) ? 'here show' : '' ?>">
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
                            <?php if ($usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH'): ?>
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
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                    
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
                    
                    <!--begin:Menu item-->
                    <div class="menu-item">
                        <a class="menu-link <?= isActive('enviar_notificacao_push.php') ?>" href="enviar_notificacao_push.php">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-<?= getIcon('notification') ?> fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </span>
                            <span class="menu-title">Enviar Notificação Push</span>
                        </a>
                    </div>
                    <!--end:Menu item-->
                <?php else: ?>
                    <!--begin:Menu item-->
                    <div class="menu-item">
                        <a class="menu-link <?= isActive('colaborador_view.php') ?>" href="colaborador_view.php?id=<?= $usuario['colaborador_id'] ?>">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-<?= getIcon('person') ?> fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Meu Perfil</span>
                        </a>
                    </div>
                    <!--end:Menu item-->
                <?php endif; ?>
                
                <?php if ($usuario['role'] === 'ADMIN'): ?>
                    <!--begin:Menu separator-->
                    <div class="menu-item pt-5">
                        <div class="menu-content">
                            <span class="menu-heading fw-bold text-uppercase fs-7">Configurações</span>
                        </div>
                    </div>
                    <!--end:Menu separator-->
                    
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
                    
                    <!--begin:Menu item-->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= (isActive('configuracoes_email.php') || isActive('configuracoes_onesignal.php')) ? 'here show' : '' ?>">
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
                        </div>
                        <!--end:Menu sub-->
                    </div>
                    <!--end:Menu item-->
                <?php endif; ?>
                
                <?php if ($usuario['role'] === 'COLABORADOR'): ?>
                    <!--begin:Menu item-->
                    <div class="menu-item">
                        <a class="menu-link <?= isActive('colaborador_view.php') ?>" href="colaborador_view.php?id=<?= $usuario['colaborador_id'] ?>">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-<?= getIcon('person') ?> fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Meu Perfil</span>
                        </a>
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
