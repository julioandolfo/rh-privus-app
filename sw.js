// Service Worker - RH Privus PWA
// OneSignal SDK será carregado automaticamente via OneSignalSDKWorker.js
const CACHE_NAME = 'rh-privus-v6'; // Incrementado para forçar atualização (correção redirects)

// Detecta BASE_PATH automaticamente
// Funciona tanto em /rh-privus/ (localhost) quanto /rh/ (produção)
let BASE_PATH = '/rh'; // Padrão para produção

try {
    const swPath = self.location.pathname;
    if (swPath.includes('/rh-privus')) {
        BASE_PATH = '/rh-privus';
    } else if (swPath.includes('/rh/') || swPath.startsWith('/rh')) {
        BASE_PATH = '/rh';
    }
} catch (e) {
    // Fallback para /rh se não conseguir detectar
    BASE_PATH = '/rh';
}

// Lista de extensões que NUNCA devem ser cacheadas
const NO_CACHE_EXTENSIONS = ['.php', '.html', '.htm'];
const NO_CACHE_PATHS = [
    '/api/',
    '/pages/',
    '/includes/',
    'login.php',
    'logout.php',
    'index.php',
    'dashboard.php'
];

// Verifica se uma URL não deve ser cacheada
function shouldNotCache(url) {
    const urlPath = url.pathname.toLowerCase();
    
    // Não cacheia requisições de API
    if (urlPath.includes('/api/')) {
        return true;
    }
    
    // Não cacheia páginas PHP
    if (urlPath.endsWith('.php')) {
        return true;
    }
    
    // Não cacheia páginas HTML dinâmicas
    if (urlPath.endsWith('.html') || urlPath.endsWith('.htm')) {
        return true;
    }
    
    // Não cacheia caminhos específicos
    for (const path of NO_CACHE_PATHS) {
        if (urlPath.includes(path.toLowerCase())) {
            return true;
        }
    }
    
    return false;
}

// Instalação do Service Worker
self.addEventListener('install', (event) => {
    console.log('[SW] Instalando Service Worker...');
    // Não cacheia nada na instalação - apenas ativa imediatamente
    self.skipWaiting();
});

// Ativação do Service Worker
self.addEventListener('activate', (event) => {
    console.log('[SW] Ativando Service Worker...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    // Remove todos os caches antigos
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] Removendo cache antigo:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    return self.clients.claim();
});

// Interceptação de requisições (cache)
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Ignora requisições que não são HTTP/HTTPS
    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        return; // Deixa o browser lidar normalmente
    }
    
    // Ignora requisições POST, PUT, DELETE, PATCH
    if (request.method !== 'GET' && request.method !== 'HEAD') {
        return; // Deixa o browser lidar normalmente
    }
    
    // Ignora requisições de OneSignal
    if (url.pathname.includes('onesignal.com') || url.pathname.includes('OneSignalSDKWorker')) {
        return; // Deixa o browser lidar normalmente
    }
    
    // CRÍTICO: Para páginas que podem resultar em redirects (index.php, /, etc)
    // ou páginas dinâmicas, sempre busca do servidor SEM interceptar redirects
    if (shouldNotCache(url) || 
        url.pathname === BASE_PATH + '/' || 
        url.pathname === BASE_PATH + '/index.php' ||
        url.pathname.endsWith('/')) {
        
        // Usa event.respondWith para garantir que o service worker não interfira com redirects
        event.respondWith(
            fetch(request, {
                cache: 'no-store',
                redirect: 'follow' // CRÍTICO: Segue redirects automaticamente
            })
            .then((response) => {
                // CRÍTICO: Se a resposta foi um redirect, retorna diretamente sem processar
                // Service Workers não podem servir respostas de redirect diretamente
                if (response.redirected || 
                    response.status === 301 || 
                    response.status === 302 || 
                    response.status === 303 || 
                    response.status === 307 || 
                    response.status === 308) {
                    // Retorna a resposta de redirect diretamente - o navegador vai seguir
                    return response;
                }
                
                // Para respostas normais, retorna sem cachear
                return response;
            })
            .catch((error) => {
                // Se falhar, deixa o navegador lidar normalmente
                console.error('[SW] Erro ao buscar:', error);
                throw error;
            })
        );
        return;
    }
    
    // Para assets estáticos (CSS, JS, imagens), usa estratégia Network First
    // Isso garante que sempre pegue a versão mais recente
    event.respondWith(
        fetch(request, {
            cache: 'no-cache', // Sempre valida com servidor
            redirect: 'follow'
        })
        .then((response) => {
            // CRÍTICO: Não cacheia respostas de redirect
            if (response.redirected || 
                response.status === 301 || 
                response.status === 302 || 
                response.status === 303 || 
                response.status === 307 || 
                response.status === 308) {
                return response; // Retorna sem cachear
            }
            
            // Só cacheia se for resposta válida de asset estático
            if (response && response.status === 200 && response.type === 'basic') {
                // Verifica se é um asset estático (CSS, JS, imagens, fonts)
                const contentType = response.headers.get('content-type') || '';
                const isStaticAsset = 
                    contentType.includes('text/css') ||
                    contentType.includes('application/javascript') ||
                    contentType.includes('text/javascript') ||
                    contentType.includes('image/') ||
                    contentType.includes('font/') ||
                    contentType.includes('application/font') ||
                    url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot)$/i);
                
                if (isStaticAsset) {
                    // Cache apenas assets estáticos com versionamento
                    const responseToCache = response.clone();
                    caches.open(CACHE_NAME)
                        .then((cache) => {
                            cache.put(request, responseToCache).catch(() => {
                                // Ignora erros de cache silenciosamente
                            });
                        })
                        .catch(() => {
                            // Ignora erros
                        });
                }
            }
            
            return response;
        })
        .catch((error) => {
            // Se falhar ao buscar do servidor, tenta cache apenas para assets estáticos
            return caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                // Se não tiver cache, retorna erro
                throw error;
            });
        })
    );
});

// Recebe notificações push
self.addEventListener('push', (event) => {
    const data = event.data ? event.data.json() : {};
    const title = data.title || 'RH Privus';
    const options = {
        body: data.body || 'Nova notificação',
        icon: BASE_PATH + '/assets/media/logos/favicon.png',
        badge: BASE_PATH + '/assets/media/logos/favicon.png',
        data: data.data || {},
        vibrate: [200, 100, 200],
        tag: 'rh-privus-notification',
        requireInteraction: false
    };
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// Clique na notificação
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    
    const urlToOpen = event.notification.data.url || BASE_PATH + '/pages/dashboard.php';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Tenta focar em janela existente
                for (let client of clientList) {
                    if (client.url.includes(BASE_PATH) && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Abre nova janela
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});
