# Guia Prático de Implementação - PWA + Capacitor

## 🚀 Início Rápido

Este guia mostra os arquivos e código necessários para começar a transformação.

---

## 1️⃣ Instalação Inicial

### Passo 1: Instalar Dependências

```bash
# No diretório raiz do projeto
npm init -y
npm install @capacitor/core @capacitor/cli
npm install @capacitor/ios @capacitor/android
npm install firebase/php-jwt --save-dev  # Para PHP (via Composer)
```

### Passo 2: Inicializar Capacitor

```bash
npx cap init "RH Privus" "com.privus.rh" --web-dir="."
```

---

## 2️⃣ Arquivos a Criar/Modificar

### Arquivo 1: `manifest.json` (Raiz do projeto)

```json
{
  "name": "RH Privus",
  "short_name": "RH Privus",
  "description": "Sistema de Gestão de Recursos Humanos",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#009ef7",
  "orientation": "portrait-primary",
  "icons": [
    {
      "src": "/assets/media/logos/icon-72x72.png",
      "sizes": "72x72",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/assets/media/logos/icon-96x96.png",
      "sizes": "96x96",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/assets/media/logos/icon-128x128.png",
      "sizes": "128x128",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/assets/media/logos/icon-144x144.png",
      "sizes": "144x144",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/assets/media/logos/icon-152x152.png",
      "sizes": "152x152",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/assets/media/logos/icon-192x192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/assets/media/logos/icon-384x384.png",
      "sizes": "384x384",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/assets/media/logos/icon-512x512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "any maskable"
    }
  ]
}
```

### Arquivo 2: `sw.js` (Service Worker - Raiz do projeto)

```javascript
const CACHE_NAME = 'rh-privus-v1';
const urlsToCache = [
  '/',
  '/login.php',
  '/pages/dashboard.php',
  '/assets/css/style.bundle.css',
  '/assets/js/scripts.bundle.js',
  '/assets/plugins/global/plugins.bundle.css',
  '/assets/plugins/global/plugins.bundle.js'
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
});

// Interceptação de requisições
self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        // Cache hit - retorna resposta do cache
        if (response) {
          return response;
        }
        
        // Clone da requisição
        const fetchRequest = event.request.clone();
        
        return fetch(fetchRequest).then((response) => {
          // Verifica se resposta é válida
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }
          
          // Clone da resposta
          const responseToCache = response.clone();
          
          caches.open(CACHE_NAME)
            .then((cache) => {
              cache.put(event.request, responseToCache);
            });
          
          return response;
        });
      })
  );
});
```

### Arquivo 3: `api/auth/login.php` (Nova API de Login)

```php
<?php
/**
 * API de Autenticação - Login com JWT
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // Composer autoload

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false, 'message' => '', 'data' => null];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método não permitido';
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$senha = $input['senha'] ?? '';

if (empty($email) || empty($senha)) {
    $response['message'] = 'Email e senha são obrigatórios';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getDB();
    
    // Tenta login como usuário do sistema
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    $userData = null;
    
    if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
        // Atualiza último login
        $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $stmt->execute([$usuario['id']]);
        
        $userData = [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'role' => $usuario['role'],
            'empresa_id' => $usuario['empresa_id'],
            'setor_id' => $usuario['setor_id'] ?? null,
            'colaborador_id' => $usuario['colaborador_id']
        ];
    } else {
        // Tenta login como colaborador
        $cpf_limpo = preg_replace('/[^0-9]/', '', $email);
        $stmt = $pdo->prepare("
            SELECT c.*, u.id as usuario_id, u.role, u.empresa_id as usuario_empresa_id
            FROM colaboradores c
            LEFT JOIN usuarios u ON c.id = u.colaborador_id
            WHERE (c.cpf = ? OR c.email_pessoal = ?) 
            AND c.status = 'ativo'
            AND c.senha_hash IS NOT NULL
        ");
        $stmt->execute([$cpf_limpo, $email]);
        $colaborador = $stmt->fetch();
        
        if ($colaborador && password_verify($senha, $colaborador['senha_hash'])) {
            if ($colaborador['usuario_id']) {
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$colaborador['usuario_id']]);
                $usuario = $stmt->fetch();
                
                $userData = [
                    'id' => $usuario['id'],
                    'nome' => $usuario['nome'],
                    'email' => $usuario['email'],
                    'role' => $usuario['role'],
                    'empresa_id' => $usuario['empresa_id'],
                    'setor_id' => $usuario['setor_id'] ?? null,
                    'colaborador_id' => $usuario['colaborador_id']
                ];
            } else {
                // Cria sessão temporária para colaborador sem usuário
                $userData = [
                    'id' => null,
                    'nome' => $colaborador['nome_completo'],
                    'email' => $colaborador['email_pessoal'] ?? $email,
                    'role' => 'COLABORADOR',
                    'empresa_id' => $colaborador['empresa_id'],
                    'setor_id' => $colaborador['setor_id'],
                    'colaborador_id' => $colaborador['id']
                ];
            }
        }
    }
    
    if ($userData) {
        // Gera token JWT
        $secretKey = getenv('JWT_SECRET') ?: 'sua-chave-secreta-super-segura-aqui-mude-isso';
        $issuedAt = time();
        $expirationTime = $issuedAt + (60 * 60 * 24 * 7); // 7 dias
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'data' => $userData
        ];
        
        $token = JWT::encode($payload, $secretKey, 'HS256');
        
        $response['success'] = true;
        $response['message'] = 'Login realizado com sucesso';
        $response['data'] = [
            'token' => $token,
            'user' => $userData
        ];
    } else {
        $response['message'] = 'Credenciais inválidas';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Erro ao processar login: ' . $e->getMessage();
}

echo json_encode($response);
```

### Arquivo 4: `includes/api_auth.php` (Middleware de Autenticação)

```php
<?php
/**
 * Middleware de Autenticação para APIs
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function validateJWT() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token não fornecido',
            'code' => 'NO_TOKEN'
        ]);
        exit;
    }
    
    // Remove "Bearer " do início
    $token = str_replace('Bearer ', '', $authHeader);
    
    try {
        $secretKey = getenv('JWT_SECRET') ?: 'sua-chave-secreta-super-segura-aqui-mude-isso';
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        
        // Retorna dados do usuário
        return (array) $decoded->data;
        
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token inválido ou expirado',
            'code' => 'INVALID_TOKEN'
        ]);
        exit;
    }
}

// Função para compatibilidade com código existente
function getCurrentUser() {
    return validateJWT();
}
```

### Arquivo 5: `capacitor.config.json` (Configuração do Capacitor)

```json
{
  "appId": "com.privus.rh",
  "appName": "RH Privus",
  "webDir": ".",
  "server": {
    "url": "https://seu-dominio.com.br",
    "cleartext": false
  },
  "plugins": {
    "SplashScreen": {
      "launchShowDuration": 2000,
      "backgroundColor": "#ffffff"
    }
  },
  "android": {
    "allowMixedContent": false
  },
  "ios": {
    "contentInset": "automatic"
  }
}
```

### Arquivo 6: Modificação em `includes/header.php`

Adicionar antes do `</head>`:

```php
<!-- PWA Manifest -->
<link rel="manifest" href="/manifest.json">

<!-- Meta tags para iOS -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="RH Privus">
<link rel="apple-touch-icon" href="/assets/media/logos/icon-152x152.png">

<!-- Theme Color -->
<meta name="theme-color" content="#009ef7">

<!-- Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.log('SW registrado:', registration.scope);
            })
            .catch((error) => {
                console.log('SW falhou:', error);
            });
    });
}
</script>
```

### Arquivo 7: `composer.json` (Para instalar JWT)

```json
{
    "require": {
        "firebase/php-jwt": "^6.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}
```

Instalar com: `composer install`

---

## 3️⃣ Modificar API Existente (Exemplo)

### Antes (`api/get_colaboradores.php`):

```php
<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode([]);
    exit;
}

$usuario = $_SESSION['usuario'];
// ... resto do código
```

### Depois (`api/get_colaboradores.php`):

```php
<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Valida token e obtém usuário
$usuario = validateJWT();

$pdo = getDB();
// ... resto do código permanece igual
```

---

## 4️⃣ JavaScript no Frontend (Login)

### Criar `assets/js/auth.js`:

```javascript
// Gerenciamento de autenticação no frontend

const Auth = {
    token: null,
    user: null,
    
    // Salva token no localStorage
    setToken(token) {
        this.token = token;
        localStorage.setItem('auth_token', token);
    },
    
    // Obtém token do localStorage
    getToken() {
        if (!this.token) {
            this.token = localStorage.getItem('auth_token');
        }
        return this.token;
    },
    
    // Salva dados do usuário
    setUser(user) {
        this.user = user;
        localStorage.setItem('auth_user', JSON.stringify(user));
    },
    
    // Obtém dados do usuário
    getUser() {
        if (!this.user) {
            const userStr = localStorage.getItem('auth_user');
            if (userStr) {
                this.user = JSON.parse(userStr);
            }
        }
        return this.user;
    },
    
    // Verifica se está autenticado
    isAuthenticated() {
        return !!this.getToken();
    },
    
    // Faz logout
    logout() {
        this.token = null;
        this.user = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        window.location.href = '/login.php';
    },
    
    // Faz requisição autenticada
    async fetch(url, options = {}) {
        const token = this.getToken();
        
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };
        
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const response = await fetch(url, {
            ...options,
            headers
        });
        
        // Se token expirou, faz logout
        if (response.status === 401) {
            this.logout();
            throw new Error('Sessão expirada');
        }
        
        return response;
    },
    
    // Login
    async login(email, senha) {
        const response = await fetch('/api/auth/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email, senha })
        });
        
        const data = await response.json();
        
        if (data.success) {
            this.setToken(data.data.token);
            this.setUser(data.data.user);
            return data;
        } else {
            throw new Error(data.message);
        }
    }
};

// Exportar para uso global
window.Auth = Auth;
```

---

## 5️⃣ Modificar `login.php` (Frontend)

Adicionar script antes do `</body>`:

```javascript
<script src="/assets/js/auth.js"></script>
<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = document.getElementById('email').value;
    const senha = document.getElementById('senha').value;
    const submitBtn = document.querySelector('button[type="submit"]');
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Entrando...';
    
    try {
        await Auth.login(email, senha);
        window.location.href = '/pages/dashboard.php';
    } catch (error) {
        alert('Erro ao fazer login: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Entrar';
    }
});
</script>
```

---

## 6️⃣ Comandos Úteis

```bash
# Inicializar Capacitor
npx cap init

# Adicionar plataformas
npx cap add ios
npx cap add android

# Sincronizar código
npx cap sync

# Abrir no Xcode (iOS)
npx cap open ios

# Abrir no Android Studio
npx cap open android

# Build para produção
npx cap build

# Atualizar após mudanças
npx cap sync
```

---

## ✅ Checklist de Implementação

- [ ] Instalar dependências (npm, composer)
- [ ] Criar `manifest.json`
- [ ] Criar `sw.js` (Service Worker)
- [ ] Criar API de login com JWT
- [ ] Criar middleware `api_auth.php`
- [ ] Adaptar APIs existentes
- [ ] Adicionar meta tags no header
- [ ] Criar `auth.js` no frontend
- [ ] Modificar `login.php` para usar JWT
- [ ] Criar ícones do app (vários tamanhos)
- [ ] Configurar Capacitor
- [ ] Testar em dispositivos
- [ ] Publicar nas lojas

---

## 🎯 Próximos Passos

1. Seguir este guia passo a passo
2. Testar cada etapa antes de prosseguir
3. Adaptar conforme necessário para seu ambiente
4. Fazer testes extensivos antes de publicar

---

**Boa sorte com a implementação! 🚀**

