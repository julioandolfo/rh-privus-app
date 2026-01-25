<?php
/**
 * Header do Sistema - Metronic Theme
 */

// Carrega configuração de sessão
if (!function_exists('iniciar_sessao_30_dias')) {
    require_once __DIR__ . '/session_config.php';
}

// Inicia sessão com configuração de 30 dias
iniciar_sessao_30_dias();

// Renova sessão se usuário estiver logado
if (isset($_SESSION['usuario'])) {
    verificar_e_renovar_sessao();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: ../login.php');
    exit;
}

$usuario = $_SESSION['usuario'];

// Carrega pontos do usuário para exibir no header
require_once __DIR__ . '/pontuacao.php';
$_header_pontos = obter_pontos($usuario['id'] ?? null, $usuario['colaborador_id'] ?? null);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?>RH Privus</title>
    
    <!--begin::Fonts(mandatory for all pages)-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <!--end::Fonts-->
    
    <!--begin::Vendor Stylesheets(used for this page only)-->
    <link href="../assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
    <!--end::Vendor Stylesheets-->
    
    <!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
    <link href="../assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="../assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->
    
    <link rel="shortcut icon" href="../assets/avatar-privus.png" />
    
    <!--begin::PWA Manifest-->
    <link rel="manifest" href="../manifest.php">
    <meta name="theme-color" content="#009ef7">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="RH Privus">
    <link rel="apple-touch-icon" href="../assets/avatar-privus.png">
    <!--end::PWA Manifest-->
    
    
    <!--begin::Header CSS Fix-->
    <style>
        /* Garantir que o header tenha fundo sólido */
        #kt_header.header {
            background-color: #130061 !important;
            background-image: none !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        [data-bs-theme="dark"] #kt_header.header {
            background-color: #1E1E2D !important;
            border-bottom: 1px solid #2D2D43;
        }
        
        /* Aumentar botão do menu mobile */
        #kt_aside_toggle {
            width: 44px !important;
            height: 44px !important;
            min-width: 44px !important;
            min-height: 44px !important;
            padding: 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        #kt_aside_toggle i {
            font-size: 1.75rem !important;
        }
        
        /* Em desktop, manter layout original do Metronic */
        @media (min-width: 992px) {
            #kt_header .container-fluid {
                display: flex !important;
                align-items: center !important;
            }
            
            #kt_header .header-brand {
                display: flex !important;
                align-items: center !important;
                margin-right: auto !important;
            }
            
            #kt_header .topbar {
                margin-left: auto !important;
            }
        }
        
        /* Garantir que o header seja fixo em mobile */
        @media (max-width: 991.98px) {
            #kt_header {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                z-index: 1040 !important;
                width: 100% !important;
            }
            
            #kt_header .container-fluid {
                display: flex !important;
                align-items: center !important;
                gap: 1rem !important;
                position: relative !important;
                min-height: 60px !important;
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
            
            #kt_header .header-brand {
                display: flex !important;
                align-items: center !important;
                flex: 1 !important;
            }
            
            #kt_header .topbar {
                margin-left: auto !important;
            }
            
            /* Garantir que o body tenha padding-top para compensar o header fixo */
            body {
                padding-top: 0 !important;
            }
            
            /* O wrapper deve ter margem superior para compensar o header */
            .wrapper {
                margin-top: 0 !important;
            }
            
            /* O content deve ter padding-top */
            #kt_content {
                padding-top: 80px !important;
            }
        }
    </style>
    <!--end::Header CSS Fix-->
    
    <script>
        // Frame-busting to prevent site from being loaded within a frame without permission (click-jacking)
        if (window.top != window.self) {
            window.top.location.replace(window.self.location.href);
        }
    </script>
</head>
<body id="kt_body" class="header-tablet-and-mobile-fixed aside-enabled">
    <!--begin::Theme mode setup on page load-->
    <script>
        var defaultThemeMode = "dark";
        var themeMode;
        if (document.documentElement) {
            if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
                themeMode = document.documentElement.getAttribute("data-bs-theme-mode");
            } else {
                if (localStorage.getItem("data-bs-theme") !== null) {
                    themeMode = localStorage.getItem("data-bs-theme");
                } else {
                    themeMode = defaultThemeMode;
                }
            }
            var appliedTheme = themeMode;
            if (themeMode === "system") {
                appliedTheme = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            }
            document.documentElement.setAttribute("data-bs-theme", appliedTheme);
        }
    </script>
    <!--end::Theme mode setup on page load-->
    
    <!--begin::Main-->
    <!--begin::Root-->
    <div class="d-flex flex-column flex-root">
        <!--begin::Page-->
        <div class="page d-flex flex-row flex-column-fluid">
            <?php include __DIR__ . '/menu.php'; ?>
            
            <!--begin::Wrapper-->
            <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
                <!--begin::Header-->
                <div id="kt_header" class="header header-bg">
                    <!--begin::Container-->
                    <div class="container-fluid d-flex align-items-center position-relative">
                        <!--begin::Aside toggle-->
                        <div class="d-flex align-items-center d-lg-none me-2" title="Show aside menu">
                            <div class="btn btn-icon btn-color-white btn-active-color-primary" id="kt_aside_toggle">
                                <i class="ki-duotone ki-abstract-14 fs-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                        <!--end::Aside toggle-->
                        <!--begin::Brand-->
                        <div class="header-brand flex-grow-1 d-flex">
                            <!--begin::Logo-->
                            <a href="dashboard.php" class="d-flex align-items-center">
                                <img alt="Logo Privus" src="../assets/media/logos/logo-privus-light.png" class="h-25px h-lg-30px d-none d-md-block theme-light-show" />
                                <img alt="Logo Privus" src="../assets/media/logos/logo-privus-light.png" class="h-25px h-lg-30px d-none d-md-block theme-dark-show" />
                                <img alt="Logo Privus" src="../assets/media/logos/logo-privus-light.png" class="h-25px d-block d-md-none theme-light-show" />
                                <img alt="Logo Privus" src="../assets/media/logos/logo-privus-light.png" class="h-25px d-block d-md-none theme-dark-show" />
                            </a>
                            <!--end::Logo-->
                        </div>
                        <!--end::Brand-->
                        <!--begin::Topbar-->
                        <div class="topbar d-flex align-items-stretch">
                            <?php if (isset($_SESSION['impersonating']) && $_SESSION['impersonating']): ?>
                            <!--begin::Aviso Impersonation-->
                            <div class="d-flex align-items-center me-2">
                                <div class="alert alert-warning d-flex align-items-center p-3 mb-0" style="min-width: 300px;">
                                    <i class="ki-duotone ki-information-5 fs-2x text-warning me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold">Logado como Colaborador</span>
                                        <span class="fs-7">Você está visualizando como outro usuário</span>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light-warning ms-3" onclick="voltarUsuarioOriginal()">
                                        Voltar
                                    </button>
                                </div>
                            </div>
                            <!--end::Aviso Impersonation-->
                            <?php endif; ?>
                            <!--begin::Theme mode-->
                            <div class="d-flex align-items-center me-2 me-lg-4">
                                <!--begin::Menu toggle-->
                                <a href="#" class="btn btn-icon btn-borderless btn-color-white btn-active-primary bg-white bg-opacity-10" data-kt-menu-trigger="{default:'click', lg: 'hover'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                                    <i class="ki-duotone ki-night-day theme-light-show fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                        <span class="path5"></span>
                                        <span class="path6"></span>
                                        <span class="path7"></span>
                                        <span class="path8"></span>
                                        <span class="path9"></span>
                                        <span class="path10"></span>
                                    </i>
                                    <i class="ki-duotone ki-moon theme-dark-show fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </a>
                                <!--begin::Menu toggle-->
                                <!--begin::Menu-->
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px" data-kt-menu="true" data-kt-element="theme-mode-menu">
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3 my-0">
                                        <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="light">
                                            <span class="menu-icon" data-kt-element="icon">
                                                <i class="ki-duotone ki-night-day fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                    <span class="path4"></span>
                                                    <span class="path5"></span>
                                                    <span class="path6"></span>
                                                    <span class="path7"></span>
                                                    <span class="path8"></span>
                                                    <span class="path9"></span>
                                                    <span class="path10"></span>
                                                </i>
                                            </span>
                                            <span class="menu-title">Light</span>
                                        </a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3 my-0">
                                        <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="dark">
                                            <span class="menu-icon" data-kt-element="icon">
                                                <i class="ki-duotone ki-moon fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </span>
                                            <span class="menu-title">Dark</span>
                                        </a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3 my-0">
                                        <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="system">
                                            <span class="menu-icon" data-kt-element="icon">
                                                <i class="ki-duotone ki-screen fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                    <span class="path4"></span>
                                                </i>
                                            </span>
                                            <span class="menu-title">System</span>
                                        </a>
                                    </div>
                                    <!--end::Menu item-->
                                </div>
                                <!--end::Menu-->
                            </div>
                            <!--end::Theme mode-->
                            <!--begin::Pontos-->
                            <div class="d-flex align-items-center ms-1 ms-lg-3">
                                <!--begin::Pontos Badge-->
                                <a href="ranking_pontos.php" class="btn btn-sm btn-light-warning d-flex align-items-center gap-2 px-3 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Seus pontos acumulados">
                                    <i class="ki-duotone ki-medal-star fs-3 text-warning">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                    </i>
                                    <span class="fw-bold text-gray-800" id="header_pontos_total"><?= number_format($_header_pontos['pontos_totais'] ?? 0, 0, ',', '.') ?></span>
                                    <span class="d-none d-lg-inline text-muted fs-8">pts</span>
                                </a>
                                <!--end::Pontos Badge-->
                            </div>
                            <!--end::Pontos-->
                            <!--begin::Notifications-->
                            <div class="d-flex align-items-center ms-1 ms-lg-3">
                                <!--begin::Menu wrapper-->
                                <div class="btn btn-icon btn-color-white btn-active-color-primary position-relative w-30px h-30px" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end" id="kt_notifications_menu">
                                    <i class="ki-duotone ki-notification-status fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <span class="bullet bullet-dot bg-success h-6px w-6px position-absolute translate-middle top-0 start-50 animation-blink" id="kt_notifications_badge" style="display: none;"></span>
                                </div>
                                <!--begin::Menu-->
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-350px" data-kt-menu="true" id="kt_notifications_dropdown">
                                    <!--begin::Heading-->
                                    <div class="menu-item px-3">
                                        <div class="menu-content d-flex align-items-center px-3">
                                            <h3 class="fw-bold px-2 mb-0">Notificações</h3>
                                            <span class="badge badge-light-primary" id="kt_notifications_count">0</span>
                                        </div>
                                    </div>
                                    <!--end::Heading-->
                                    <!--begin::Separator-->
                                    <div class="separator mb-2 opacity-75"></div>
                                    <!--end::Separator-->
                                    <!--begin::Notifications list-->
                                    <div class="menu-item px-3" id="kt_notifications_list">
                                        <div class="text-center text-muted py-10">
                                            <p>Nenhuma notificação</p>
                                        </div>
                                    </div>
                                    <!--end::Notifications list-->
                                    <!--begin::Footer-->
                                    <div class="menu-item px-3 py-2">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button id="btn_marcar_todas_lidas" class="btn btn-sm btn-light-success" style="display: none;">
                                                <i class="ki-duotone ki-check fs-6">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Marcar todas como lidas
                                            </button>
                                            <a href="notificacoes.php" class="btn btn-sm btn-light-primary">Ver todas</a>
                                        </div>
                                    </div>
                                    <!--end::Footer-->
                                </div>
                                <!--end::Menu-->
                            </div>
                            <!--end::Notifications-->
                            <!--begin::User-->
                            <div class="d-flex align-items-center ms-1 ms-lg-3">
                                <!--begin::Menu wrapper-->
                                <div class="btn btn-icon btn-color-white btn-active-color-primary position-relative w-30px h-30px" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                    <i class="ki-duotone ki-user fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                                <!--begin::Menu-->
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px" data-kt-menu="true">
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <div class="menu-content d-flex align-items-center px-3">
                                            <!--begin::Avatar-->
                                            <div class="symbol symbol-50px me-5">
                                                <div class="symbol-label fs-2 fw-semibold bg-primary text-white">
                                                    <?= strtoupper(substr($usuario['nome'], 0, 1)) ?>
                                                </div>
                                            </div>
                                            <!--end::Avatar-->
                                            <!--begin::Username-->
                                            <div class="d-flex flex-column">
                                                <div class="fw-bold d-flex align-items-center fs-5"><?= htmlspecialchars($usuario['nome']) ?></div>
                                                <a href="#" class="fw-semibold text-muted text-hover-primary fs-7"><?= htmlspecialchars($usuario['email']) ?></a>
                                            </div>
                                            <!--end::Username-->
                                        </div>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu separator-->
                                    <div class="separator my-2"></div>
                                    <!--end::Menu separator-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-5">
                                        <a href="minha_conta.php" class="menu-link px-5">
                                            <span class="menu-text">Minha Conta</span>
                                            <span class="menu-icon">
                                                <i class="ki-duotone ki-user fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </span>
                                        </a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu separator-->
                                    <div class="separator my-2"></div>
                                    <!--end::Menu separator-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-5">
                                        <a href="../logout.php" class="menu-link px-5">
                                            <span class="menu-text">Sair</span>
                                            <span class="menu-icon">
                                                <i class="ki-duotone ki-exit fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </span>
                                        </a>
                                    </div>
                                    <!--end::Menu item-->
                                </div>
                                <!--end::Menu-->
                            </div>
                            <!--end::User-->
                        </div>
                        <!--end::Topbar-->
                    </div>
                    <!--end::Container-->
                </div>
                <!--end::Header-->
                
                <!--begin::Content-->
                <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                    <!--begin::Container-->
                    <div id="kt_content_container" class="container-xxl">
                        <?= get_session_alert(); ?>
                        
                        <?php 
                        // Inclui modal de comunicados
                        require_once __DIR__ . '/comunicados_modal.php'; 
                        ?>
