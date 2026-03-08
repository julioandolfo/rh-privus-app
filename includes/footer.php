                    </div>
                    <!--end::Container-->
                </div>
                <!--end::Content-->
            </div>
            <!--end::Wrapper-->
        </div>
        <!--end::Page-->
    </div>
    <!--end::Root-->
    
    <!--begin::Scrolltop-->
    <div id="kt_scrolltop" class="scrolltop" data-kt-scrolltop="true">
        <i class="ki-duotone ki-arrow-up">
            <span class="path1"></span>
            <span class="path2"></span>
        </i>
    </div>
    <!--end::Scrolltop-->
    
    <!--begin::Javascript-->
    <script>var hostUrl = "../assets/";</script>
    <!--begin::jQuery (carregado primeiro para estar disponível para outros scripts)-->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>
    <script>
        if (typeof window.jQuery === 'undefined') {
            document.write('<script src="../assets/vendor/jquery/jquery-3.6.1.min.js"><\/script>');
            console.warn('Fallback local do jQuery carregado (CDN indisponível).');
        }
        if (typeof window.$ === 'undefined' && typeof window.jQuery !== 'undefined') {
            window.$ = window.jQuery;
        }
    </script>
    <!--end::jQuery-->
    
    <!--begin::Global Javascript Bundle(mandatory for all pages)-->
    <script src="../assets/plugins/global/plugins.bundle.js"></script>
    <script src="../assets/js/scripts.bundle.js"></script>
    <!--end::Global Javascript Bundle-->
    
    <!--begin::OneSignal SDK-->
    <!-- CRÍTICO: Bloqueia prompts automáticos ANTES do SDK carregar -->
    <script>
    // Intercepta OneSignal antes do SDK carregar para prevenir prompts automáticos
    window.OneSignal = window.OneSignal || [];
    window.OneSignalDeferred = window.OneSignalDeferred || [];
    
    // Bloqueia qualquer tentativa de mostrar prompts automaticamente
    const blockAutoPrompts = function() {
        if (typeof OneSignal !== 'undefined') {
            // Bloqueia showSlidedownPrompt
            if (typeof OneSignal.showSlidedownPrompt === 'function') {
                const original = OneSignal.showSlidedownPrompt;
                OneSignal.showSlidedownPrompt = function() {
                    console.log('🚫 Bloqueado: showSlidedownPrompt() automático');
                    return Promise.resolve(false);
                };
            }
            
            // Bloqueia showHttpPrompt
            if (typeof OneSignal.showHttpPrompt === 'function') {
                const original = OneSignal.showHttpPrompt;
                OneSignal.showHttpPrompt = function() {
                    console.log('🚫 Bloqueado: showHttpPrompt() automático');
                    return Promise.resolve(false);
                };
            }
            
            // Bloqueia registerForPushNotifications automático
            if (typeof OneSignal.registerForPushNotifications === 'function') {
                const original = OneSignal.registerForPushNotifications;
                OneSignal.registerForPushNotifications = function() {
                    console.log('🚫 Bloqueado: registerForPushNotifications() automático');
                    return Promise.resolve(false);
                };
            }
        }
    };
    
    // Executa bloqueio imediatamente e periodicamente
    blockAutoPrompts();
    setInterval(blockAutoPrompts, 1000);
    </script>
    <script src="https://cdn.onesignal.com/sdks/OneSignalSDK.js" async></script>
    <script src="../assets/js/onesignal-init.js"></script>
    <!--end::OneSignal SDK-->
    
    <!--begin::Push Notification Prompt-->
    <link href="../assets/css/push-notification-prompt.css" rel="stylesheet" type="text/css" />
    <script src="../assets/js/push-notification-prompt.js"></script>
    <!--end::Push Notification Prompt-->
    
    <!--begin::PWA Service Worker-->
    <script src="../assets/js/pwa-service-worker.js"></script>
    <!--end::PWA Service Worker-->
    
    <!--begin::PWA Install Prompt-->
    <script src="../assets/js/pwa-install-prompt.js"></script>
    <!--end::PWA Install Prompt-->
    
    <!--begin::Vendors Javascript(used for this page only)-->
    <script src="../assets/plugins/custom/datatables/datatables.bundle.js"></script>
    <!--end::Vendors Javascript-->
    
    <!--begin::SweetAlert2-->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!--end::SweetAlert2-->
    
    <!--begin::jQuery Mask Plugin-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <!--end::jQuery Mask Plugin-->
    
    
    <!--begin::Custom Javascript-->
    <script>
        // Sistema de Troca de Tema
        (function() {
            // Aguarda jQuery estar disponível (proteção extra)
            function waitForJQuery(callback) {
                if (typeof window.jQuery !== 'undefined' || typeof window.$ !== 'undefined') {
                    callback();
                } else {
                    setTimeout(function() {
                        waitForJQuery(callback);
                    }, 50);
                }
            }
            
            var themeMode = localStorage.getItem("data-bs-theme") || "light";
            
            // Função para aplicar o tema
            function setTheme(mode) {
                var savedMode = mode;
                if (mode === "system") {
                    mode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
                }
                document.documentElement.setAttribute("data-bs-theme", mode);
                localStorage.setItem("data-bs-theme", savedMode);
                
                // Atualiza ícones usando jQuery se disponível
                waitForJQuery(function() {
                    var $ = window.jQuery || window.$;
                    if ($) {
                        if (mode === "dark") {
                            $('.theme-light-show').hide();
                            $('.theme-dark-show').show();
                        } else {
                            $('.theme-light-show').show();
                            $('.theme-dark-show').hide();
                        }
                    }
                });
            }
            
            // Aplica tema inicial após jQuery estar carregado
            waitForJQuery(function() {
                var $ = window.jQuery || window.$;
                if (!$) {
                    console.warn('jQuery não disponível para sistema de tema');
                    return;
                }
                
                $(document).ready(function() {
                    setTheme(themeMode);
                    
                    // Listener para mudanças no sistema
                    if (themeMode === "system") {
                        window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", function() {
                            setTheme("system");
                        });
                    }
                    
                    // Handler para cliques no menu de tema
                    $(document).on('click', '[data-kt-element="mode"]', function(e) {
                        e.preventDefault();
                        themeMode = $(this).attr('data-kt-value');
                        setTheme(themeMode);
                    });
                });
            });
        })();
        
        // ============================================
        // Sistema Centralizado de Gerenciamento do Drawer/Menu Lateral
        // ============================================
        // Esta solução garante que o drawer funcione corretamente em todas as páginas
        // sem necessidade de código específico em cada página
        
        (function() {
            'use strict';
            
            var DEBUG = true; // Ativar/desativar logs de debug
            
            function log(message, data) {
                if (DEBUG && console && console.log) {
                    if (data !== undefined) {
                        console.log('[Drawer Manager]', message, data);
                    } else {
                        console.log('[Drawer Manager]', message);
                    }
                }
            }
            
            function ensureDrawerState() {
                var asideEl = document.querySelector('#kt_aside');
                if (!asideEl) {
                    log('Elemento #kt_aside não encontrado');
                    return false;
                }
                
                var isDesktop = window.innerWidth >= 992;
                var isDrawerOpen = asideEl.classList.contains('drawer-on') || 
                                  document.body.hasAttribute('data-kt-drawer-aside') ||
                                  document.body.getAttribute('data-kt-drawer-aside') === 'on';
                
                log('Estado atual:', {
                    isDesktop: isDesktop,
                    isDrawerOpen: isDrawerOpen,
                    windowWidth: window.innerWidth,
                    hasDrawerOn: asideEl.classList.contains('drawer-on'),
                    hasBodyAttr: document.body.hasAttribute('data-kt-drawer-aside'),
                    bodyAttrValue: document.body.getAttribute('data-kt-drawer-aside'),
                    drawerActivate: asideEl.getAttribute('data-kt-drawer-activate')
                });
                
                // Em desktop, o drawer deve estar fechado (menu fixo)
                if (isDesktop && isDrawerOpen) {
                    log('Tentando fechar drawer em desktop...');
                    
                    var fixed = false;
                    
                    try {
                        if (typeof KTDrawer !== 'undefined') {
                            var drawer = KTDrawer.getInstance(asideEl);
                            if (drawer) {
                                if (typeof drawer.isShown === 'function' && drawer.isShown()) {
                                    drawer.hide();
                                    log('Drawer fechado via API do Metronic (método hide)');
                                    fixed = true;
                                } else {
                                    log('Drawer já está fechado segundo API');
                                }
                            } else {
                                log('Instância do drawer não encontrada');
                            }
                        } else {
                            log('KTDrawer não está disponível ainda');
                        }
                    } catch (e) {
                        log('Erro ao usar API do Metronic:', e);
                    }
                    
                    // Sempre aplica fallback manual para garantir
                    if (!fixed || isDrawerOpen) {
                        log('Aplicando fallback manual...');
                        asideEl.classList.remove('drawer-on');
                        document.body.removeAttribute('data-kt-drawer-aside');
                        document.body.removeAttribute('data-kt-drawer');
                        
                        var overlays = document.querySelectorAll('.drawer-overlay');
                        overlays.forEach(function(overlay) {
                            overlay.remove();
                        });
                        
                        // Remove também qualquer estilo inline que possa estar forçando abertura
                        asideEl.style.display = '';
                        asideEl.style.transform = '';
                        asideEl.style.visibility = '';
                        
                        log('Drawer fechado via fallback manual');
                        fixed = true;
                    }
                    
                    return fixed;
                }
                
                // Em mobile, verifica se o drawer está funcionando corretamente
                if (!isDesktop) {
                    log('Mobile detectado, verificando funcionalidade do drawer...');
                    
                    // Verifica se o botão de toggle existe e está funcionando
                    var toggleBtn = document.querySelector('#kt_aside_toggle');
                    if (toggleBtn) {
                        log('Botão de toggle encontrado');
                        
                        // Verifica se o drawer está inicializado
                        try {
                            if (typeof KTDrawer !== 'undefined') {
                                var drawer = KTDrawer.getInstance(asideEl);
                                if (!drawer) {
                                    log('AVISO: Drawer não está inicializado, tentando inicializar...');
                                    try {
                                        new KTDrawer(asideEl);
                                        log('Drawer inicializado com sucesso');
                                        drawer = KTDrawer.getInstance(asideEl);
                                    } catch (e) {
                                        log('ERRO ao inicializar drawer:', e);
                                    }
                                } else {
                                    log('Drawer está inicializado corretamente');
                                }
                                
                                // Se o drawer está aberto mas não deveria estar (problema de inicialização)
                                if (drawer && typeof drawer.isShown === 'function' && drawer.isShown() && !isDrawerOpen) {
                                    log('PROBLEMA DETECTADO: Drawer está aberto segundo API mas não tem marcações visuais');
                                    log('Tentando fechar drawer...');
                                    try {
                                        drawer.hide();
                                        log('Drawer fechado com sucesso');
                                    } catch (e) {
                                        log('ERRO ao fechar drawer:', e);
                                    }
                                }
                            } else {
                                log('AVISO: KTDrawer não está disponível');
                            }
                        } catch (e) {
                            log('ERRO ao verificar drawer:', e);
                        }
                        
                        // Verifica se há problema visual (menu visível quando não deveria estar)
                        // Mas só corrige uma vez para evitar loop
                        if (!asideEl.hasAttribute('data-drawer-fixed')) {
                            var computedStyle = window.getComputedStyle(asideEl);
                            var isVisible = computedStyle.display !== 'none' && 
                                           computedStyle.visibility !== 'hidden' &&
                                           computedStyle.opacity !== '0';
                            
                            // Em mobile, o drawer deve estar oculto por padrão (só aparece quando aberto)
                            // Se está visível mas não está marcado como aberto, há problema
                            if (isVisible && !isDrawerOpen) {
                                log('PROBLEMA DETECTADO: Menu está visível mas drawer não está marcado como aberto');
                                log('Estilos computados:', {
                                    display: computedStyle.display,
                                    visibility: computedStyle.visibility,
                                    opacity: computedStyle.opacity,
                                    transform: computedStyle.transform,
                                    left: computedStyle.left,
                                    position: computedStyle.position,
                                    zIndex: computedStyle.zIndex
                                });
                                
                                // Tenta fechar usando API primeiro
                                try {
                                    if (drawer && typeof drawer.hide === 'function') {
                                        drawer.hide();
                                        log('Drawer fechado via API');
                                    }
                                } catch (e) {
                                    log('Erro ao fechar via API:', e);
                                }
                                
                                // Limpa estados
                                asideEl.classList.remove('drawer-on');
                                document.body.removeAttribute('data-kt-drawer-aside');
                                document.body.removeAttribute('data-kt-drawer');
                                
                                var overlays = document.querySelectorAll('.drawer-overlay');
                                overlays.forEach(function(overlay) {
                                    overlay.remove();
                                });
                                
                                // Marca como corrigido para evitar loop
                                asideEl.setAttribute('data-drawer-fixed', 'true');
                                log('Drawer corrigido e marcado');
                            }
                        }
                    } else {
                        log('ERRO: Botão de toggle não encontrado!');
                    }
                }
                
                return false;
            }
            
            // Função para verificar e corrigir o drawer periodicamente até estabilizar
            function monitorDrawerState(maxAttempts, interval) {
                maxAttempts = maxAttempts || 10;
                interval = interval || 300;
                var attempts = 0;
                
                var checkInterval = setInterval(function() {
                    attempts++;
                    var fixed = ensureDrawerState();
                    
                    if (fixed || attempts >= maxAttempts) {
                        clearInterval(checkInterval);
                        if (attempts >= maxAttempts) {
                            log('Monitoramento finalizado após', maxAttempts, 'tentativas');
                        }
                    }
                }, interval);
            }
            
            // Função para verificar e garantir inicialização do drawer
            function ensureDrawerInitialization() {
                var asideEl = document.querySelector('#kt_aside');
                if (!asideEl) {
                    log('Elemento #kt_aside não encontrado para inicialização');
                    return;
                }
                
                try {
                    if (typeof KTDrawer !== 'undefined') {
                        var drawer = KTDrawer.getInstance(asideEl);
                        if (!drawer) {
                            log('Drawer não inicializado, criando instância...');
                            try {
                                new KTDrawer(asideEl);
                                log('Drawer inicializado com sucesso');
                            } catch (e) {
                                log('ERRO ao criar instância do drawer:', e);
                            }
                        } else {
                            log('Drawer já está inicializado');
                        }
                    } else {
                        log('KTDrawer ainda não está disponível');
                    }
                } catch (e) {
                    log('ERRO ao verificar inicialização do drawer:', e);
                }
            }
            
            // Inicialização quando o DOM estiver pronto
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    log('DOM carregado, aguardando Metronic...');
                    // Aguarda o Metronic inicializar (scripts.bundle.js)
                    setTimeout(function() {
                        ensureDrawerInitialization();
                        ensureDrawerState();
                        monitorDrawerState(5, 200);
                    }, 500);
                });
            } else {
                log('DOM já carregado, aguardando Metronic...');
                setTimeout(function() {
                    ensureDrawerInitialization();
                    ensureDrawerState();
                    monitorDrawerState(5, 200);
                }, 500);
            }
            
            // Também tenta após o window.load para garantir
            window.addEventListener('load', function() {
                log('Window carregado completamente, verificando drawer...');
                setTimeout(function() {
                    ensureDrawerInitialization();
                    ensureDrawerState();
                }, 300);
            });
            
            // Monitora mudanças de tamanho da tela
            var resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    log('Tela redimensionada, verificando estado do drawer...');
                    ensureDrawerState();
                }, 250);
            });
            
            // Expõe função global para uso manual se necessário
            window.fixDrawerState = ensureDrawerState;
            
        })();
        
        // Inicializa DataTables em tabelas com classe .datatable
        // Aguarda jQuery estar disponível
        (function waitForJQuery() {
            if (typeof window.jQuery !== 'undefined' || typeof window.$ !== 'undefined') {
                var $ = window.jQuery || window.$;
                $(document).ready(function() {
                    $('.datatable').each(function() {
                        if (!$(this).hasClass('dataTable') && !$(this).attr('id')) {
                            var dt = $(this).DataTable({
                                language: {
                                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                                },
                                pageLength: 25,
                                order: [[0, 'desc']],
                                responsive: true,
                                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
                            });
                            
                            // Hook no evento draw - não reinicializa menus para evitar conflito com menu lateral
                            // Cada página específica deve reinicializar apenas seus próprios menus
                        }
                    });
                });
            } else {
                setTimeout(waitForJQuery, 50);
            }
        })();
        
        // Sistema de Notificações
        (function() {
            function carregarNotificacoes() {
                fetch('../api/notificacoes/listar.php?limite=5')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro na resposta: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('📬 Dados de notificações recebidos:', data);
                        
                        if (data.success) {
                            const badge = document.getElementById('kt_notifications_badge');
                            const count = document.getElementById('kt_notifications_count');
                            const list = document.getElementById('kt_notifications_list');
                            
                            console.log('📊 Total não lidas:', data.total_nao_lidas);
                            console.log('📋 Notificações recebidas:', data.notificacoes ? data.notificacoes.length : 0);
                            
                            if (data.total_nao_lidas > 0) {
                                if (badge) badge.style.display = 'block';
                                if (count) {
                                    count.textContent = data.total_nao_lidas;
                                    count.style.display = 'inline-block';
                                }
                            } else {
                                if (badge) badge.style.display = 'none';
                                if (count) count.style.display = 'none';
                            }
                            
                            if (list) {
                                // Limpa a lista primeiro
                                list.innerHTML = '';
                                
                                // Mostra/oculta botão "Marcar todas como lidas"
                                const btnMarcarTodas = document.getElementById('btn_marcar_todas_lidas');
                                if (btnMarcarTodas) {
                                    if (data.total_nao_lidas > 0) {
                                        btnMarcarTodas.style.display = 'inline-block';
                                    } else {
                                        btnMarcarTodas.style.display = 'none';
                                    }
                                }
                                
                                if (data.notificacoes && Array.isArray(data.notificacoes) && data.notificacoes.length > 0) {
                                    console.log('✅ Renderizando', data.notificacoes.length, 'notificações');
                                    data.notificacoes.forEach(function(notif) {
                                        const item = document.createElement('div');
                                        item.className = 'menu-item px-3';
                                        item.innerHTML = `
                                            <div class="d-flex align-items-center">
                                                <a href="${notif.link || '#'}" class="menu-link px-3 py-2 flex-grow-1" onclick="marcarNotificacaoLida(${notif.id}, event)">
                                                    <div class="d-flex flex-column">
                                                        <span class="fw-bold text-gray-800">${notif.titulo || 'Sem título'}</span>
                                                        <span class="text-muted fs-7">${notif.mensagem || ''}</span>
                                                        <span class="text-muted fs-8 mt-1">${formatarData(notif.created_at)}</span>
                                                    </div>
                                                </a>
                                                <button class="btn btn-sm btn-icon btn-light-success ms-2" onclick="marcarNotificacaoLida(${notif.id}, event)" title="Marcar como lida">
                                                    <i class="ki-duotone ki-check fs-5">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </button>
                                            </div>
                                        `;
                                        list.appendChild(item);
                                    });
                                } else {
                                    console.log('⚠️ Nenhuma notificação para exibir');
                                    list.innerHTML = '<div class="text-center text-muted py-10"><p>Nenhuma notificação</p></div>';
                                }
                            } else {
                                console.error('❌ Elemento kt_notifications_list não encontrado');
                            }
                        } else {
                            console.error('❌ Erro na API:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('❌ Erro ao carregar notificações:', error);
                    });
            }
            
            function formatarData(dataStr) {
                const data = new Date(dataStr);
                const agora = new Date();
                const diffMs = agora - data;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);
                
                if (diffMins < 1) return 'Agora';
                if (diffMins < 60) return `${diffMins} min atrás`;
                if (diffHours < 24) return `${diffHours}h atrás`;
                if (diffDays < 7) return `${diffDays}d atrás`;
                
                return data.toLocaleDateString('pt-BR');
            }
            
            window.marcarNotificacaoLida = function(notificacaoId, event) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                console.log('🔔 Marcando notificação como lida:', notificacaoId);
                
                const formData = new FormData();
                formData.append('notificacao_id', notificacaoId);
                
                fetch('../api/notificacoes/marcar_lida.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('📡 Resposta da API:', response.status, response.statusText);
                    return response.text().then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (!response.ok) {
                                throw new Error(data.message || 'Erro na requisição');
                            }
                            return data;
                        } catch (e) {
                            console.error('❌ Erro ao parsear JSON:', text);
                            throw new Error('Resposta inválida do servidor: ' + text.substring(0, 100));
                        }
                    });
                })
                .then(data => {
                    console.log('✅ Dados recebidos:', data);
                    if (data.success) {
                        carregarNotificacoes();
                    } else {
                        Swal.fire({
                            text: data.message || 'Erro ao marcar notificação como lida',
                            icon: "error",
                            buttonsStyling: false,
                            confirmButtonText: "Ok",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('❌ Erro ao marcar notificação:', error);
                    Swal.fire({
                        text: error.message || 'Erro ao marcar notificação como lida',
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    });
                });
            };
            
            window.marcarTodasNotificacoesLidas = function() {
                fetch('../api/notificacoes/marcar_todas_lidas.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            text: data.message,
                            icon: "success",
                            buttonsStyling: false,
                            confirmButtonText: "Ok",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        }).then(function() {
                            carregarNotificacoes();
                        });
                    } else {
                        Swal.fire({
                            text: data.message || 'Erro ao marcar notificações como lidas',
                            icon: "error",
                            buttonsStyling: false,
                            confirmButtonText: "Ok",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Erro ao marcar todas as notificações:', error);
                    Swal.fire({
                        text: 'Erro ao marcar notificações como lidas',
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    });
                });
            };
            
            // Adiciona listener ao botão "Marcar todas como lidas" usando event delegation
            document.addEventListener('click', function(e) {
                if (e.target.closest('#btn_marcar_todas_lidas')) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.marcarTodasNotificacoesLidas();
                }
            });
            
            // Carrega notificações ao carregar a página
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(carregarNotificacoes, 1000);
                    setInterval(carregarNotificacoes, 60000); // Atualiza a cada minuto
                });
            } else {
                setTimeout(carregarNotificacoes, 1000);
                setInterval(carregarNotificacoes, 60000);
            }
        })();
    </script>
    <!--end::Custom Javascript-->
    
    <!--begin::Responsive CSS-->
    <style>
        /* Estilos gerais do header */
        #kt_header {
            background-color: #130061 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        [data-bs-theme="dark"] #kt_header {
            background-color: #1E1E2D !important;
            border-bottom: 1px solid #2D2D43;
        }
        
        /* Melhorias de responsividade para mobile */
        @media (max-width: 991.98px) {
            /* Ajustes no toolbar */
            #kt_toolbar {
                padding: 0.75rem 0;
            }
            
            #kt_toolbar_container {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            
            /* Ajustes nas tabelas */
            .table-responsive {
                -webkit-overflow-scrolling: touch;
                overflow-x: auto;
            }
            
            .table-responsive table {
                min-width: 600px;
            }
            
            /* Ajustes nos cards */
            .card {
                margin-bottom: 1rem;
            }
            
            /* Ajustes nos botões */
            .btn {
                white-space: nowrap;
            }
            
            /* Ajustes nas tabs */
            .nav-line-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }
            
            .nav-line-tabs::-webkit-scrollbar {
                height: 4px;
            }
            
            .nav-line-tabs::-webkit-scrollbar-track {
                background: #f1f1f1;
            }
            
            .nav-line-tabs::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 2px;
            }
            
            /* Garantir que o drawer apareça corretamente */
            #kt_aside {
                z-index: 1050;
            }
            
            /* Ajustes no header */
            #kt_header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1040;
                background-color: #130061 !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            }
            
            [data-bs-theme="dark"] #kt_header {
                background-color: #1E1E2D !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            }
            
            /* Ajustes no conteúdo para compensar header fixo */
            #kt_content {
                padding-top: 70px;
            }
            
            /* Ajustes no modal */
            .modal-dialog {
                margin: 0.5rem;
            }
            
            /* Melhorias em tabelas responsivas */
            .table th,
            .table td {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
            }
            
            /* Ajustes em badges */
            .badge {
                font-size: 0.7rem;
                padding: 0.25em 0.5em;
            }
        }
        
        @media (max-width: 575.98px) {
            /* Ajustes extras para telas muito pequenas */
            .page-title h1 {
                font-size: 1.25rem !important;
            }
            
            .card-title {
                font-size: 1rem;
            }
            
            .table th,
            .table td {
                padding: 0.4rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            /* Tabs em mobile - texto menor */
            .nav-link {
                font-size: 0.875rem;
                padding: 0.75rem 0.5rem !important;
            }
            
            .nav-link i {
                font-size: 1.25rem !important;
            }
        }
        
        /* Garantir que tabelas sejam scrolláveis horizontalmente em mobile */
        @media (max-width: 767.98px) {
            .table-responsive {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table-responsive table {
                width: 100%;
                margin-bottom: 0;
            }
        }
        
        /* Drawer e menu lateral - manter layout original do Metronic */
        #kt_aside {
            display: flex !important;
            flex-direction: column !important;
        }
        
        /* Garantir largura consistente do aside em desktop 
        @media (min-width: 992px) {
            #kt_aside {
                width: 265px !important;
                min-width: 265px !important;
                max-width: 265px !important;
            }
        }*/
        
        /* Em mobile, garantir altura correta do drawer */
        @media (max-width: 991.98px) {
            #kt_aside.drawer-on {
                height: 100vh !important;
                max-height: 100vh !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                bottom: 0 !important;
            }
        }
        
        
        /* Estrutura do menu - manter layout original do Metronic */
        #kt_aside_menu {
            display: flex !important;
            flex-direction: column !important;
        }
        
        /* Em mobile, ajustar altura para não cortar */
        @media (max-width: 991.98px) {
            #kt_aside.drawer-on #kt_aside_menu {
                max-height: calc(100vh - 90px) !important;
            }
        }
        
        /* Wrapper do menu - manter comportamento padrão do Metronic */
        #kt_aside_menu_wrapper {
            -webkit-overflow-scrolling: touch;
            padding-bottom: 1rem !important;
        }
        
        /* Menu interno - adicionar espaçamento no final */
        #kt_aside_menu_wrapper > .menu {
            padding-bottom: 0.5rem;
        }
        
        /* Scrollbar personalizada */
        #kt_aside_menu_wrapper::-webkit-scrollbar {
            width: 5px;
        }
        
        #kt_aside_menu_wrapper::-webkit-scrollbar-track {
            background: transparent;
        }
        
        #kt_aside_menu_wrapper::-webkit-scrollbar-thumb {
            background-color: rgba(0, 0, 0, 0.25);
            border-radius: 3px;
        }
        
        [data-bs-theme="dark"] #kt_aside_menu_wrapper::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.25);
        }
        
        #kt_aside_menu .menu-content span {
            letter-spacing: 0.08em;
        }
        
        /* Ajustes específicos para mobile */
        @media (max-width: 991.98px) {
            /* Garante que o wrapper do menu tenha altura correta */
            #kt_aside.drawer-on #kt_aside_menu_wrapper {
                max-height: calc(100vh - 170px) !important;
            }
            
            /* Espaçamento do footer em mobile */
            #kt_aside_footer {
                padding-top: 1rem !important;
                margin-top: 0.75rem !important;
            }
        }
        
        /* Footer do menu - manter visível com espaçamento adequado */
        #kt_aside_footer {
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            padding-top: 1.25rem !important;
            margin-top: 1rem !important;
        }
        
        [data-bs-theme="dark"] #kt_aside_footer {
            border-top-color: rgba(255, 255, 255, 0.1);
        }
        
        
        
        /* Em mobile, o drawer deve estar oculto por padrão */
        @media (max-width: 991.98px) {
            #kt_aside:not(.drawer-on) {
                display: none !important;
            }
            
            /* Quando o drawer está aberto, ele deve aparecer corretamente */
            #kt_aside.drawer-on {
                display: flex !important;
                flex-direction: column !important;
                z-index: 1050 !important;
            }
            
            /* Garante que o wrapper não tenha padding quando drawer está fechado */
            body:not([data-kt-drawer-aside="on"]) .wrapper {
                padding-left: 0 !important;
            }
            
            /* Overlay do drawer */
            .drawer-overlay {
                z-index: 1049 !important;
            }
            
            /* Ajusta padding e margens do aside em mobile */
            #kt_aside.drawer-on .aside-menu {
                margin-bottom: 0 !important;
            }
        }
        
        /* Ajustes gerais de responsividade */
        @media (max-width: 991.98px) {
            #kt_content_container,
            .container-xxl {
                max-width: 100% !important;
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            
            .wrapper {
                padding-left: 0 !important;
            }
            
            .page {
                flex-direction: column;
            }
        }
        
        @media (max-width: 767.98px) {
            .row {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            
            [class*="col-"] {
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }
            
            .card {
                width: 100%;
                margin-bottom: 1rem;
            }
            
            .card .card-body {
                padding: 1rem;
            }
            
            .card .fs-2 {
                font-size: 1.5rem !important;
            }
            
            .card .fs-3 {
                font-size: 1.25rem !important;
            }
            
            canvas {
                width: 100% !important;
                height: auto !important;
            }
        }
    </style>
    <!--end::Responsive CSS-->
    
    
<!--begin::Sistema de Toast de Pontos-->
<script>
// Sistema Global de Pontos - Toast e Atualização do Header
window.RHPrivusPontos = {
    // Mostra toast de pontos ganhos
    mostrarToastPontos: function(pontosGanhos, novoPontosTotal, acao) {
        if (!pontosGanhos || pontosGanhos <= 0) return;
        
        // Atualiza o contador de pontos no header
        this.atualizarHeaderPontos(novoPontosTotal);
        
        // Descrições das ações
        const descricoes = {
            'postar_feed': 'publicação',
            'curtir_feed': 'curtida',
            'comentar_feed': 'comentário',
            'registrar_emocao': 'registro de emoção',
            'enviar_feedback': 'feedback enviado',
            'acesso_diario': 'acesso diário',
            'comunicado_lido': 'leitura de comunicado',
            'confirmar_evento': 'confirmação de presença',
            'concluir_curso': 'conclusão de curso'
        };
        
        const descricaoAcao = descricoes[acao] || 'ação';
        
        // Toast usando Toastr se disponível, senão usa SweetAlert2
        if (typeof toastr !== 'undefined') {
            toastr.success(
                `<div class="d-flex align-items-center">
                    <i class="ki-duotone ki-medal-star fs-2x text-warning me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    <div>
                        <strong>+${pontosGanhos} pontos!</strong><br>
                        <small>Por ${descricaoAcao}</small>
                    </div>
                </div>`,
                'Parabéns!',
                {
                    timeOut: 3000,
                    progressBar: true,
                    closeButton: true,
                    positionClass: 'toast-top-right',
                    enableHtml: true
                }
            );
        } else if (typeof Swal !== 'undefined') {
            // Toast usando SweetAlert2
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
            
            Toast.fire({
                icon: 'success',
                title: `<span class="text-success fw-bold">+${pontosGanhos} pontos!</span>`,
                html: `<small class="text-muted">Por ${descricaoAcao}</small>`
            });
        }
    },
    
    // Atualiza o contador de pontos no header
    atualizarHeaderPontos: function(novoPontosTotal) {
        const headerPontos = document.getElementById('header_pontos_total');
        if (headerPontos && novoPontosTotal !== undefined) {
            // Animação de destaque
            headerPontos.classList.add('text-success');
            headerPontos.style.transition = 'all 0.3s ease';
            headerPontos.style.transform = 'scale(1.3)';
            
            // Atualiza o valor
            headerPontos.textContent = novoPontosTotal.toLocaleString('pt-BR');
            
            // Remove animação após 500ms
            setTimeout(() => {
                headerPontos.classList.remove('text-success');
                headerPontos.style.transform = 'scale(1)';
            }, 500);
        }
    },
    
    // Atualiza o saldo em R$ no header
    atualizarHeaderSaldoDinheiro: function(novoSaldo) {
        const headerSaldo = document.getElementById('header_saldo_dinheiro');
        if (headerSaldo && novoSaldo !== undefined) {
            // Animação de destaque
            headerSaldo.classList.add('text-success');
            headerSaldo.style.transition = 'all 0.3s ease';
            headerSaldo.style.transform = 'scale(1.3)';
            
            // Atualiza o valor
            headerSaldo.textContent = novoSaldo.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            // Remove animação após 500ms
            setTimeout(() => {
                headerSaldo.classList.remove('text-success');
                headerSaldo.style.transform = 'scale(1)';
            }, 500);
        }
    },
    
    // Processa resposta de API que contém pontos
    processarRespostaPontos: function(data, acao) {
        if (data && data.pontos_ganhos && data.pontos_ganhos > 0) {
            this.mostrarToastPontos(data.pontos_ganhos, data.pontos_totais, acao);
        }
    }
};

// Alias global para facilitar o uso
window.mostrarToastPontos = function(pontosGanhos, novoPontosTotal, acao) {
    window.RHPrivusPontos.mostrarToastPontos(pontosGanhos, novoPontosTotal, acao);
};

window.processarRespostaPontos = function(data, acao) {
    window.RHPrivusPontos.processarRespostaPontos(data, acao);
};

window.atualizarHeaderSaldoDinheiro = function(novoSaldo) {
    window.RHPrivusPontos.atualizarHeaderSaldoDinheiro(novoSaldo);
};
</script>
<!--end::Sistema de Toast de Pontos-->

<script>
// Função para voltar ao usuário original após impersonation
function voltarUsuarioOriginal() {
    Swal.fire({
        title: 'Voltar ao seu usuário?',
        text: 'Você está prestes a voltar ao seu usuário original.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, voltar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-light'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Voltando...',
                text: 'Por favor, aguarde.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('../api/voltar_usuario_original.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect || 'dashboard.php';
                } else {
                    Swal.fire({
                        text: data.message || 'Erro ao voltar',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'Ok',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    text: 'Erro ao voltar: ' + error.message,
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            });
        }
    });
}
</script>

</body>
</html>
