/**
 * Registro do Service Worker para PWA
 * Detecta automaticamente o caminho base
 */

(function() {
    'use strict';
    
    if ('serviceWorker' in navigator) {
        // Detecta o caminho base automaticamente
        const path = window.location.pathname;
        let basePath = '';
        
        // Detecta automaticamente o caminho base
        // Prioriza /rh-privus (localhost) ou /rh (produção)
        if (path.includes('/rh-privus/') || path.startsWith('/rh-privus')) {
            basePath = '/rh-privus';
        } else if (path.includes('/rh/') || path.match(/^\/rh[^a-z]/)) {
            // Verifica se é /rh/ mas não /rh-privus
            basePath = '/rh';
        } else {
            // Fallback: detecta pelo hostname
            const hostname = window.location.hostname;
            if (hostname === 'localhost' || hostname === '127.0.0.1' || hostname.includes('local')) {
                basePath = '/rh-privus';
            } else {
                basePath = '/rh';
            }
        }
        
        // Registra Service Worker quando a página carregar
        window.addEventListener('load', function() {
            const swPath = basePath ? basePath + '/sw.js' : '/sw.js';
            const scope = basePath ? basePath + '/' : '/';
            
            navigator.serviceWorker.register(swPath, { scope: scope })
                .then(function(registration) {
                    console.log('✅ Service Worker registrado com sucesso:', registration.scope);
                    
                    // Verifica atualizações periodicamente
                    setInterval(function() {
                        registration.update();
                    }, 60000); // A cada 1 minuto
                })
                .catch(function(error) {
                    console.error('❌ Erro ao registrar Service Worker:', error);
                });
        });
    } else {
        console.warn('⚠️ Service Worker não suportado neste navegador');
    }
})();

