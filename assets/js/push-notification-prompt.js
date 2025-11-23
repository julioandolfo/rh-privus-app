/**
 * Botão Flutuante de Solicitação de Notificações Push
 * Aparece quando o usuário não tem notificações ativadas
 */

(function() {
    'use strict';
    
    const PushNotificationPrompt = {
        initialized: false,
        checkInterval: null,
        
        init: function() {
            if (this.initialized) {
                return;
            }
            
            // Verifica se usuário está logado (verifica se há sessão ou indicador de login)
            // Se não houver indicador de login, não inicializa
            const isLoggedIn = document.body.classList.contains('is-colaborador') || 
                              window.userRole !== undefined ||
                              document.querySelector('[data-kt-aside-menu]') !== null;
            
            if (!isLoggedIn) {
                console.log('Usuário não logado, botão de notificações não será exibido');
                return;
            }
            
            // Cria o botão no DOM
            this.createButton();
            
            // Verifica status após alguns segundos (aguarda OneSignal inicializar)
            setTimeout(() => {
                this.checkNotificationStatus();
            }, 3000);
            
            // Verifica periodicamente (a cada 10 segundos)
            this.checkInterval = setInterval(() => {
                this.checkNotificationStatus();
            }, 10000);
            
            this.initialized = true;
        },
        
        createButton: function() {
            // Verifica se já existe
            if (document.getElementById('push-notification-prompt')) {
                return;
            }
            
            const buttonContainer = document.createElement('div');
            buttonContainer.id = 'push-notification-prompt';
            buttonContainer.className = 'push-notification-prompt';
            
            buttonContainer.innerHTML = `
                <button class="push-notification-prompt-btn" id="btn-push-prompt" title="Ativar notificações push">
                    <i class="ki-duotone ki-notification-bing">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <span class="push-notification-prompt-badge">1</span>
                </button>
            `;
            
            document.body.appendChild(buttonContainer);
            
            // Adiciona evento de clique
            document.getElementById('btn-push-prompt').addEventListener('click', () => {
                this.handleClick();
            });
        },
        
        checkNotificationStatus: function() {
            // Verifica se notificações estão ativadas
            if (!('Notification' in window)) {
                this.hideButton();
                return;
            }
            
            // Verifica permissão nativa primeiro
            const nativePermission = Notification.permission;
            
            if (nativePermission === 'granted') {
                // Se permissão nativa foi concedida, verifica OneSignal
                if (typeof OneSignal !== 'undefined' && typeof OneSignal.push === 'function') {
                    try {
                        OneSignal.push(function() {
                            OneSignal.isPushNotificationsEnabled(function(isEnabled) {
                                if (isEnabled) {
                                    PushNotificationPrompt.hideButton();
                                } else {
                                    // Permissão concedida mas OneSignal não está habilitado
                                    // Pode estar processando, não mostra botão ainda
                                    setTimeout(() => {
                                        PushNotificationPrompt.checkNotificationStatus();
                                    }, 2000);
                                }
                            });
                        });
                    } catch (e) {
                        console.warn('Erro ao verificar OneSignal:', e);
                        // Se der erro mas permissão foi concedida, esconde o botão
                        this.hideButton();
                    }
                } else {
                    // Permissão concedida mas OneSignal não carregou ainda
                    // Aguarda um pouco e verifica novamente
                    setTimeout(() => {
                        this.checkNotificationStatus();
                    }, 2000);
                }
            } else if (nativePermission === 'denied') {
                // Permissão negada, não mostra botão
                this.hideButton();
            } else {
                // Permissão ainda não foi solicitada (default)
                // Verifica se OneSignal está disponível antes de mostrar
                if (typeof OneSignal !== 'undefined' && typeof OneSignal.push === 'function') {
                    // OneSignal está disponível, mostra botão
                    this.showButton();
                } else {
                    // OneSignal ainda não carregou, aguarda um pouco
                    setTimeout(() => {
                        this.checkNotificationStatus();
                    }, 1000);
                }
            }
        },
        
        showButton: function() {
            const button = document.getElementById('push-notification-prompt');
            if (button) {
                button.classList.add('show');
            }
        },
        
        hideButton: function() {
            const button = document.getElementById('push-notification-prompt');
            if (button) {
                button.classList.remove('show');
            }
        },
        
        handleClick: function() {
            // Esconde o botão imediatamente ao clicar
            this.hideButton();
            
            // Verifica se OneSignal está disponível
            if (typeof OneSignal === 'undefined' || typeof OneSignalInit === 'undefined') {
                // Fallback: usa API nativa
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission().then((permission) => {
                        if (permission === 'granted') {
                            // Aguarda um pouco e verifica novamente
                            setTimeout(() => {
                                this.checkNotificationStatus();
                            }, 2000);
                        }
                    });
                }
                return;
            }
            
            // Usa OneSignal para solicitar permissão
            if (typeof OneSignalInit.subscribe === 'function') {
                OneSignalInit.subscribe().then((success) => {
                    if (success) {
                        // Sucesso! Aguarda um pouco e verifica status
                        setTimeout(() => {
                            this.checkNotificationStatus();
                        }, 2000);
                    } else {
                        // Não foi possível ativar, mostra botão novamente após alguns segundos
                        setTimeout(() => {
                            this.checkNotificationStatus();
                        }, 5000);
                    }
                }).catch((error) => {
                    console.error('Erro ao solicitar permissão:', error);
                    // Tenta novamente após alguns segundos
                    setTimeout(() => {
                        this.checkNotificationStatus();
                    }, 5000);
                });
            } else {
                // Fallback: usa API nativa
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission().then((permission) => {
                        if (permission === 'granted') {
                            setTimeout(() => {
                                this.checkNotificationStatus();
                            }, 2000);
                        }
                    });
                }
            }
        }
    };
    
    // Inicializa quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => PushNotificationPrompt.init(), 2000);
        });
    } else {
        setTimeout(() => PushNotificationPrompt.init(), 2000);
    }
    
    // Exporta globalmente
    window.PushNotificationPrompt = PushNotificationPrompt;
})();

