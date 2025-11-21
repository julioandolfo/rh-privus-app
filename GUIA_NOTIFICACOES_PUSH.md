# 🔔 Guia Completo: PWA + Notificações Push

## 🎯 Objetivo

Transformar seu sistema em um **app instalável** com **notificações push** funcionando mesmo quando o app está fechado.

---

## 📊 Comparação de Opções

### Opção 1: Web Push API (Nativo do Browser) ⭐ **RECOMENDADO**
**Prós:**
- ✅ Gratuito e nativo
- ✅ Funciona em PWA sem dependências externas
- ✅ Funciona offline (Service Worker)
- ✅ Suporta Chrome, Firefox, Edge, Safari (iOS 16.4+)
- ✅ Não precisa de serviços externos

**Contras:**
- ⚠️ Precisa gerar chaves VAPID (simples, mas necessário)
- ⚠️ iOS tem suporte limitado

**Melhor para:** Sistema web que quer notificações push simples e diretas

---

### Opção 2: Firebase Cloud Messaging (FCM)
**Prós:**
- ✅ Funciona em PWA e apps nativos
- ✅ Suporte completo iOS/Android
- ✅ Analytics integrado
- ✅ Fácil de escalar

**Contras:**
- ⚠️ Requer conta Google/Firebase
- ⚠️ Mais complexo de configurar
- ⚠️ Depende de serviço externo

**Melhor para:** Quando precisa de apps nativos também

---

### Opção 3: OneSignal (Serviço Terceiro)
**Prós:**
- ✅ Muito fácil de integrar
- ✅ Dashboard visual
- ✅ Suporte multi-plataforma

**Contras:**
- ⚠️ Limite gratuito (10k usuários)
- ⚠️ Depende de serviço externo
- ⚠️ Pode ter custos depois

**Melhor para:** Prototipagem rápida ou quando não quer lidar com infraestrutura

---

## 🏆 RECOMENDAÇÃO: Web Push API

**Por quê?**
1. Você já tem sistema PHP funcionando
2. Não precisa de serviços externos
3. Gratuito e ilimitado
4. Funciona perfeitamente em PWA
5. Integra facilmente com seu sistema atual

---

## 🚀 Implementação: Web Push API

### Passo 1: Estrutura do Banco de Dados

```sql
-- Tabela para armazenar subscriptions de push
CREATE TABLE push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_endpoint (endpoint(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para chaves VAPID (gerar uma vez)
CREATE TABLE vapid_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    public_key TEXT NOT NULL,
    private_key TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Passo 2: Gerar Chaves VAPID

Crie o arquivo `scripts/gerar_vapid_keys.php`:

```php
<?php
/**
 * Gera chaves VAPID para Web Push
 * Execute UMA VEZ: php scripts/gerar_vapid_keys.php
 */

require_once __DIR__ . '/../includes/functions.php';

use Minishlink\WebPush\VAPID;

try {
    $keys = VAPID::createVapidKeys();
    
    $pdo = getDB();
    
    // Remove chaves antigas
    $pdo->exec("DELETE FROM vapid_keys");
    
    // Insere novas chaves
    $stmt = $pdo->prepare("
        INSERT INTO vapid_keys (public_key, private_key) 
        VALUES (?, ?)
    ");
    $stmt->execute([$keys['publicKey'], $keys['privateKey']]);
    
    echo "✅ Chaves VAPID geradas com sucesso!\n\n";
    echo "Public Key (use no frontend):\n";
    echo $keys['publicKey'] . "\n\n";
    echo "Private Key (mantenha segura):\n";
    echo $keys['privateKey'] . "\n\n";
    echo "⚠️  IMPORTANTE: Guarde essas chaves em local seguro!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
```

**Instalar dependência:**
```bash
composer require minishlink/web-push
```

### Passo 3: API para Registrar Subscription

Crie `api/push/subscribe.php`:

```php
<?php
/**
 * API para registrar subscription de push notification
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/api_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método não permitido';
    echo json_encode($response);
    exit;
}

// Valida token JWT ou sessão
$usuario = null;
if (function_exists('validateJWT')) {
    try {
        $usuario = validateJWT();
    } catch (Exception $e) {
        // Tenta sessão como fallback
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['usuario'])) {
            $usuario = $_SESSION['usuario'];
        }
    }
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['usuario'])) {
        $usuario = $_SESSION['usuario'];
    }
}

if (!$usuario || !isset($usuario['id'])) {
    http_response_code(401);
    $response['message'] = 'Não autenticado';
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$endpoint = $input['endpoint'] ?? '';
$p256dh = $input['keys']['p256dh'] ?? '';
$auth = $input['keys']['auth'] ?? '';

if (empty($endpoint) || empty($p256dh) || empty($auth)) {
    $response['message'] = 'Dados de subscription inválidos';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getDB();
    
    // Verifica se já existe subscription para este endpoint
    $stmt = $pdo->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
    $stmt->execute([$endpoint]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Atualiza subscription existente
        $stmt = $pdo->prepare("
            UPDATE push_subscriptions 
            SET usuario_id = ?, p256dh = ?, auth = ?, user_agent = ?, updated_at = NOW()
            WHERE endpoint = ?
        ");
        $stmt->execute([
            $usuario['id'],
            $p256dh,
            $auth,
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $endpoint
        ]);
    } else {
        // Cria nova subscription
        $stmt = $pdo->prepare("
            INSERT INTO push_subscriptions (usuario_id, endpoint, p256dh, auth, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $usuario['id'],
            $endpoint,
            $p256dh,
            $auth,
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    $response['success'] = true;
    $response['message'] = 'Subscription registrada com sucesso';
    
} catch (Exception $e) {
    $response['message'] = 'Erro ao registrar subscription: ' . $e->getMessage();
}

echo json_encode($response);
```

### Passo 4: API para Enviar Notificações

Crie `api/push/send.php`:

```php
<?php
/**
 * API para enviar notificações push
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/api_auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Valida autenticação (apenas ADMIN ou RH pode enviar)
$usuario = null;
if (function_exists('validateJWT')) {
    try {
        $usuario = validateJWT();
    } catch (Exception $e) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['usuario'])) {
            $usuario = $_SESSION['usuario'];
        }
    }
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['usuario'])) {
        $usuario = $_SESSION['usuario'];
    }
}

if (!$usuario || !in_array($usuario['role'], ['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$usuario_id = $input['usuario_id'] ?? null;
$titulo = $input['titulo'] ?? 'Notificação';
$mensagem = $input['mensagem'] ?? '';
$url = $input['url'] ?? '/';
$icone = $input['icone'] ?? '/assets/media/logos/icon-192x192.png';

if (empty($mensagem)) {
    echo json_encode(['success' => false, 'message' => 'Mensagem é obrigatória']);
    exit;
}

try {
    $pdo = getDB();
    
    // Busca chaves VAPID
    $stmt = $pdo->query("SELECT public_key, private_key FROM vapid_keys ORDER BY id DESC LIMIT 1");
    $vapid = $stmt->fetch();
    
    if (!$vapid) {
        throw new Exception('Chaves VAPID não configuradas. Execute scripts/gerar_vapid_keys.php');
    }
    
    // Configura WebPush
    $auth = [
        'VAPID' => [
            'subject' => 'mailto:seu-email@privus.com.br', // Seu email
            'publicKey' => $vapid['public_key'],
            'privateKey' => $vapid['private_key'],
        ],
    ];
    
    $webPush = new WebPush($auth);
    
    // Busca subscriptions
    if ($usuario_id) {
        // Envia para usuário específico
        $stmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
    } else {
        // Envia para todos (cuidado!)
        $stmt = $pdo->query("SELECT endpoint, p256dh, auth FROM push_subscriptions");
    }
    
    $subscriptions = $stmt->fetchAll();
    $enviadas = 0;
    $falhas = 0;
    
    $payload = json_encode([
        'title' => $titulo,
        'body' => $mensagem,
        'icon' => $icone,
        'badge' => '/assets/media/logos/icon-72x72.png',
        'data' => [
            'url' => $url
        ]
    ]);
    
    foreach ($subscriptions as $sub) {
        $subscription = Subscription::create([
            'endpoint' => $sub['endpoint'],
            'keys' => [
                'p256dh' => $sub['p256dh'],
                'auth' => $sub['auth']
            ]
        ]);
        
        $result = $webPush->sendOneNotification($subscription, $payload);
        
        if ($result->isSuccess()) {
            $enviadas++;
        } else {
            $falhas++;
            // Remove subscription inválida
            if ($result->isSubscriptionExpired()) {
                $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
                $stmt->execute([$sub['endpoint']]);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'enviadas' => $enviadas,
        'falhas' => $falhas,
        'total' => count($subscriptions)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
```

### Passo 5: Service Worker Atualizado

Atualize `sw.js`:

```javascript
// sw.js
const CACHE_NAME = 'rh-privus-v1';
const BASE_PATH = '/rh-privus'; // Ajuste conforme seu Laragon

const urlsToCache = [
  BASE_PATH + '/',
  BASE_PATH + '/login.php',
  BASE_PATH + '/pages/dashboard.php',
  BASE_PATH + '/assets/css/style.bundle.css',
  BASE_PATH + '/assets/js/scripts.bundle.js',
  BASE_PATH + '/assets/plugins/global/plugins.bundle.css',
  BASE_PATH + '/assets/plugins/global/plugins.bundle.js'
];

// Instalação
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(urlsToCache))
  );
  self.skipWaiting();
});

// Ativação
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  return self.clients.claim();
});

// Cache de requisições
self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        return response || fetch(event.request);
      })
  );
});

// ✅ NOVO: Recebe notificações push
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'RH Privus';
  const options = {
    body: data.body || 'Nova notificação',
    icon: data.icon || BASE_PATH + '/assets/media/logos/icon-192x192.png',
    badge: BASE_PATH + '/assets/media/logos/icon-72x72.png',
    data: data.data || {},
    vibrate: [200, 100, 200],
    tag: 'rh-privus-notification',
    requireInteraction: false
  };
  
  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

// ✅ NOVO: Clique na notificação
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  
  const urlToOpen = event.notification.data.url || BASE_PATH + '/';
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // Tenta focar em janela existente
        for (let client of clientList) {
          if (client.url === urlToOpen && 'focus' in client) {
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
```

### Passo 6: JavaScript no Frontend

Crie `assets/js/push-notifications.js`:

```javascript
/**
 * Gerenciamento de Notificações Push
 */

const PushNotifications = {
    vapidPublicKey: null,
    registration: null,
    
    // Inicializa (chamar após login)
    async init() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('Push notifications não suportadas neste browser');
            return false;
        }
        
        try {
            // Busca chave VAPID pública do servidor
            const response = await fetch('/rh-privus/api/push/vapid-key.php');
            const data = await response.json();
            this.vapidPublicKey = data.publicKey;
            
            // Registra service worker
            this.registration = await navigator.serviceWorker.register('/rh-privus/sw.js');
            
            // Solicita permissão e subscribe
            await this.subscribe();
            
            return true;
        } catch (error) {
            console.error('Erro ao inicializar push notifications:', error);
            return false;
        }
    },
    
    // Solicita permissão e cria subscription
    async subscribe() {
        if (!this.registration) {
            throw new Error('Service Worker não registrado');
        }
        
        // Verifica permissão
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            throw new Error('Permissão de notificação negada');
        }
        
        // Cria subscription
        const subscription = await this.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
        });
        
        // Envia subscription para o servidor
        await this.sendSubscriptionToServer(subscription);
        
        return subscription;
    },
    
    // Envia subscription para o servidor
    async sendSubscriptionToServer(subscription) {
        const token = window.Auth?.getToken();
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const response = await fetch('/rh-privus/api/push/subscribe.php', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(subscription)
        });
        
        if (!response.ok) {
            throw new Error('Erro ao registrar subscription');
        }
        
        return await response.json();
    },
    
    // Converte chave VAPID para formato correto
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    },
    
    // Cancela subscription
    async unsubscribe() {
        if (!this.registration) {
            return;
        }
        
        const subscription = await this.registration.pushManager.getSubscription();
        if (subscription) {
            await subscription.unsubscribe();
        }
    }
};

// Exportar globalmente
window.PushNotifications = PushNotifications;
```

### Passo 7: API para Retornar Chave VAPID Pública

Crie `api/push/vapid-key.php`:

```php
<?php
/**
 * Retorna chave VAPID pública para o frontend
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT public_key FROM vapid_keys ORDER BY id DESC LIMIT 1");
    $vapid = $stmt->fetch();
    
    if (!$vapid) {
        throw new Exception('Chaves VAPID não configuradas');
    }
    
    echo json_encode(['publicKey' => $vapid['public_key']]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

### Passo 8: Integrar no Sistema Existente

**Exemplo: Enviar notificação quando ocorrência é criada**

Modifique `pages/ocorrencias_add.php` (após criar ocorrência):

```php
// ... código existente de criação de ocorrência ...

// Envia email (já existe)
enviar_email_ocorrencia($ocorrencia_id);

// ✅ NOVO: Envia notificação push
try {
    $colaborador = $pdo->prepare("SELECT u.id as usuario_id FROM colaboradores c LEFT JOIN usuarios u ON c.id = u.colaborador_id WHERE c.id = ?");
    $colaborador->execute([$colaborador_id]);
    $colab = $colaborador->fetch();
    
    if ($colab && $colab['usuario_id']) {
        // Envia push notification
        $ch = curl_init('http://localhost/rh-privus/api/push/send.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'usuario_id' => $colab['usuario_id'],
            'titulo' => 'Nova Ocorrência',
            'mensagem' => 'Você recebeu uma nova ocorrência',
            'url' => '/rh-privus/pages/colaborador_view.php?id=' . $colaborador_id
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . ($_SESSION['token'] ?? '')
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
} catch (Exception $e) {
    // Falha silenciosa - não interrompe o fluxo
    error_log('Erro ao enviar push: ' . $e->getMessage());
}
```

### Passo 9: Inicializar no Frontend

Adicione em `includes/header.php` (após login):

```php
<!-- Push Notifications -->
<script src="/rh-privus/assets/js/push-notifications.js"></script>
<script>
// Inicializa push notifications após login
if (typeof PushNotifications !== 'undefined') {
    PushNotifications.init().then(() => {
        console.log('✅ Push notifications ativadas');
    }).catch(err => {
        console.warn('⚠️ Push notifications não disponíveis:', err);
    });
}
</script>
```

---

## 📦 Instalação Completa

### 1. Instalar Dependências

```bash
composer require minishlink/web-push
```

### 2. Criar Tabelas

Execute o SQL do Passo 1 no seu banco de dados.

### 3. Gerar Chaves VAPID

```bash
php scripts/gerar_vapid_keys.php
```

### 4. Criar Arquivos

Crie todos os arquivos listados acima.

### 5. Testar

1. Acesse o sistema no browser
2. Faça login
3. Permita notificações quando solicitado
4. Teste enviando uma notificação via API

---

## 🧪 Como Testar

### Teste Manual via API:

```bash
curl -X POST http://localhost/rh-privus/api/push/send.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN_JWT" \
  -d '{
    "usuario_id": 1,
    "titulo": "Teste",
    "mensagem": "Esta é uma notificação de teste",
    "url": "/rh-privus/pages/dashboard.php"
  }'
```

---

## ✅ Vantagens desta Solução

1. ✅ **Gratuita** - Sem limites ou custos
2. ✅ **Funciona offline** - Service Worker cacheia
3. ✅ **Notificações reais** - Mesmo com app fechado
4. ✅ **Integração fácil** - Usa seu sistema PHP atual
5. ✅ **Multi-plataforma** - Chrome, Firefox, Edge, Safari

---

## 🎯 Próximos Passos

1. Implementar código acima
2. Testar em diferentes browsers
3. Criar interface admin para enviar notificações
4. Integrar com eventos do sistema (ocorrências, etc.)

**Boa sorte! 🚀**

