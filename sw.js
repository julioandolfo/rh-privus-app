// Service Worker - RH Privus PWA
// OneSignal SDK será carregado automaticamente via OneSignalSDKWorker.js
const CACHE_NAME = 'rh-privus-v2'; // Atualizado para forçar atualização

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

const urlsToCache = [
  BASE_PATH + '/',
  BASE_PATH + '/login.php',
  BASE_PATH + '/pages/dashboard.php',
  BASE_PATH + '/assets/css/style.bundle.css',
  BASE_PATH + '/assets/js/scripts.bundle.js',
  BASE_PATH + '/assets/plugins/global/plugins.bundle.css',
  BASE_PATH + '/assets/plugins/global/plugins.bundle.js'
];

// Instalação do Service Worker
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Cache aberto');
        return cache.addAll(urlsToCache);
      })
  );
  self.skipWaiting();
});

// Ativação do Service Worker
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('Removendo cache antigo:', cacheName);
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
  
  // Ignora requisições que não são HTTP/HTTPS (chrome-extension, data:, etc)
  if (url.protocol !== 'http:' && url.protocol !== 'https:') {
    return; // Não faz cache, deixa o browser lidar normalmente
  }
  
  // Ignora requisições POST, PUT, DELETE, PATCH (não podem ser cacheadas)
  if (request.method !== 'GET' && request.method !== 'HEAD') {
    return fetch(request);
  }
  
  // Ignora requisições de API e OneSignal (não devem ser cacheadas)
  if (url.pathname.includes('/api/') || url.pathname.includes('onesignal.com')) {
    return fetch(request);
  }
  
  event.respondWith(
    caches.match(request)
      .then((response) => {
        // Cache hit - retorna resposta do cache
        if (response) {
          return response;
        }
        
        // Clone da requisição
        const fetchRequest = request.clone();
        
        return fetch(fetchRequest).then((response) => {
          // Verifica se resposta é válida e pode ser cacheada
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }
          
          // Verifica novamente se é uma URL válida para cache
          const responseUrl = new URL(response.url);
          if (responseUrl.protocol !== 'http:' && responseUrl.protocol !== 'https:') {
            return response; // Não faz cache
          }
          
          // Clone da resposta
          const responseToCache = response.clone();
          
          // Tenta fazer cache, mas ignora erros
          // Verifica novamente antes de fazer cache (proteção extra)
          try {
            const finalUrl = new URL(request.url);
            if (finalUrl.protocol === 'http:' || finalUrl.protocol === 'https:') {
              caches.open(CACHE_NAME)
                .then((cache) => {
                  cache.put(request, responseToCache).catch((err) => {
                    // Ignora erros silenciosamente (chrome-extension, etc)
                    if (err.message && !err.message.includes('chrome-extension')) {
                      console.warn('Erro ao fazer cache:', err);
                    }
                  });
                })
                .catch((err) => {
                  // Ignora erros silenciosamente
                });
            }
          } catch (e) {
            // Ignora erros de URL inválida
          }
          
          return response;
        }).catch((error) => {
          console.warn('Erro no fetch:', error);
          return fetch(request); // Fallback para fetch normal
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

