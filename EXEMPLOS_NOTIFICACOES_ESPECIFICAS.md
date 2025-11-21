# 🎯 Enviar Notificações Push para Colaborador Específico

## ✅ Resposta Direta

**SIM!** Com Web Push API você pode enviar notificações para:
- ✅ **Um colaborador específico** (apenas ele recebe)
- ✅ **Múltiplos colaboradores** (grupo específico)
- ✅ **Todos os colaboradores** (broadcast)

**Cada dispositivo é único e vinculado a um usuário/colaborador!**

---

## 🔍 Como Funciona

### 1. **Cada Colaborador Registra seu Dispositivo**

Quando o colaborador faz login e permite notificações:
- Sistema cria um registro único (`push_subscriptions`)
- Vincula ao `usuario_id` ou `colaborador_id`
- Cada dispositivo tem um `endpoint` único

### 2. **Você Envia Notificação Específica**

Quando você quer notificar um colaborador:
- Busca o `endpoint` dele na tabela
- Envia push apenas para aquele endpoint
- **Apenas ele recebe!**

---

## 📊 Estrutura do Banco de Dados

### Tabela `push_subscriptions` (melhorada):

```sql
CREATE TABLE push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,              -- Se colaborador tem usuário vinculado
    colaborador_id INT NULL,          -- ID direto do colaborador
    endpoint TEXT NOT NULL,           -- Endpoint único do dispositivo
    p256dh VARCHAR(255) NOT NULL,     -- Chave pública
    auth VARCHAR(255) NOT NULL,       -- Chave de autenticação
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_endpoint (endpoint(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Por que ambos `usuario_id` e `colaborador_id`?**
- Colaborador pode ter usuário vinculado (`colaborador_id` → `usuario_id`)
- Colaborador pode não ter usuário (apenas `colaborador_id`)
- Facilita busca em ambos os casos

---

## 🚀 API Melhorada: Registrar Subscription

Atualize `api/push/subscribe.php`:

```php
<?php
/**
 * API para registrar subscription de push notification
 * Suporta tanto usuario_id quanto colaborador_id
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
$colaborador_id = null;

if (function_exists('validateJWT')) {
    try {
        $usuario = validateJWT();
        $colaborador_id = $usuario['colaborador_id'] ?? null;
    } catch (Exception $e) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['usuario'])) {
            $usuario = $_SESSION['usuario'];
            $colaborador_id = $usuario['colaborador_id'] ?? null;
        }
    }
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['usuario'])) {
        $usuario = $_SESSION['usuario'];
        $colaborador_id = $usuario['colaborador_id'] ?? null;
    }
}

if (!$usuario) {
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
    
    $usuario_id = $usuario['id'] ?? null;
    
    // Se colaborador não tem usuário vinculado, busca ou cria
    if (!$usuario_id && $colaborador_id) {
        // Tenta encontrar usuário vinculado ao colaborador
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE colaborador_id = ? LIMIT 1");
        $stmt->execute([$colaborador_id]);
        $user_data = $stmt->fetch();
        $usuario_id = $user_data['id'] ?? null;
    }
    
    // Verifica se já existe subscription para este endpoint
    $stmt = $pdo->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
    $stmt->execute([$endpoint]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Atualiza subscription existente
        $stmt = $pdo->prepare("
            UPDATE push_subscriptions 
            SET usuario_id = ?, colaborador_id = ?, p256dh = ?, auth = ?, user_agent = ?, updated_at = NOW()
            WHERE endpoint = ?
        ");
        $stmt->execute([
            $usuario_id,
            $colaborador_id,
            $p256dh,
            $auth,
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $endpoint
        ]);
    } else {
        // Cria nova subscription
        $stmt = $pdo->prepare("
            INSERT INTO push_subscriptions (usuario_id, colaborador_id, endpoint, p256dh, auth, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $usuario_id,
            $colaborador_id,
            $endpoint,
            $p256dh,
            $auth,
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    $response['success'] = true;
    $response['message'] = 'Subscription registrada com sucesso';
    $response['data'] = [
        'usuario_id' => $usuario_id,
        'colaborador_id' => $colaborador_id
    ];
    
} catch (Exception $e) {
    $response['message'] = 'Erro ao registrar subscription: ' . $e->getMessage();
}

echo json_encode($response);
```

---

## 🎯 API para Enviar Notificação Específica

Atualize `api/push/send.php` com suporte a colaborador_id:

```php
<?php
/**
 * API para enviar notificações push
 * Suporta: usuario_id, colaborador_id, ou broadcast
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
$colaborador_id = $input['colaborador_id'] ?? null;  // ✅ NOVO
$titulo = $input['titulo'] ?? 'Notificação';
$mensagem = $input['mensagem'] ?? '';
$url = $input['url'] ?? '/';
$icone = $input['icone'] ?? '/assets/media/logos/icon-192x192.png';

if (empty($mensagem)) {
    echo json_encode(['success' => false, 'message' => 'Mensagem é obrigatória']);
    exit;
}

// Validação: precisa de pelo menos um identificador OU broadcast
if (!$usuario_id && !$colaborador_id && !isset($input['broadcast'])) {
    echo json_encode(['success' => false, 'message' => 'Informe usuario_id, colaborador_id ou broadcast']);
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
            'subject' => 'mailto:seu-email@privus.com.br',
            'publicKey' => $vapid['public_key'],
            'privateKey' => $vapid['private_key'],
        ],
    ];
    
    $webPush = new WebPush($auth);
    
    // ✅ Busca subscriptions baseado no critério
    if ($colaborador_id) {
        // Envia para colaborador específico
        $stmt = $pdo->prepare("
            SELECT endpoint, p256dh, auth 
            FROM push_subscriptions 
            WHERE colaborador_id = ?
        ");
        $stmt->execute([$colaborador_id]);
    } elseif ($usuario_id) {
        // Envia para usuário específico
        $stmt = $pdo->prepare("
            SELECT endpoint, p256dh, auth 
            FROM push_subscriptions 
            WHERE usuario_id = ?
        ");
        $stmt->execute([$usuario_id]);
    } else {
        // Broadcast: envia para todos
        $stmt = $pdo->query("SELECT endpoint, p256dh, auth FROM push_subscriptions");
    }
    
    $subscriptions = $stmt->fetchAll();
    
    if (empty($subscriptions)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhuma subscription encontrada para o destinatário'
        ]);
        exit;
    }
    
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

---

## 💡 Função Helper para Facilitar Uso

Crie `includes/push_notifications.php`:

```php
<?php
/**
 * Funções helper para enviar notificações push
 */

require_once __DIR__ . '/functions.php';

/**
 * Envia notificação push para um colaborador específico
 * 
 * @param int $colaborador_id ID do colaborador
 * @param string $titulo Título da notificação
 * @param string $mensagem Mensagem da notificação
 * @param string $url URL para abrir ao clicar (opcional)
 * @return array ['success' => bool, 'enviadas' => int, 'message' => string]
 */
function enviar_push_colaborador($colaborador_id, $titulo, $mensagem, $url = null) {
    try {
        $pdo = getDB();
        
        // Busca dados do colaborador para URL padrão
        if (!$url) {
            $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE id = ?");
            $stmt->execute([$colaborador_id]);
            $colab = $stmt->fetch();
            if ($colab) {
                $url = '/rh-privus/pages/colaborador_view.php?id=' . $colaborador_id;
            }
        }
        
        // Chama API de push
        $ch = curl_init('http://localhost/rh-privus/api/push/send.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'colaborador_id' => $colaborador_id,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'url' => $url
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . ($_SESSION['token'] ?? '')
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'enviadas' => $data['enviadas'] ?? 0,
                'message' => 'Notificação enviada com sucesso'
            ];
        } else {
            return [
                'success' => false,
                'enviadas' => 0,
                'message' => 'Erro ao enviar notificação'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'enviadas' => 0,
            'message' => 'Erro: ' . $e->getMessage()
        ];
    }
}

/**
 * Envia notificação push para um usuário específico
 */
function enviar_push_usuario($usuario_id, $titulo, $mensagem, $url = '/rh-privus/pages/dashboard.php') {
    // Similar à função acima, mas usando usuario_id
    // ... código similar ...
}

/**
 * Envia notificação push para múltiplos colaboradores
 */
function enviar_push_colaboradores($colaboradores_ids, $titulo, $mensagem, $url = null) {
    $enviadas_total = 0;
    $falhas = 0;
    
    foreach ($colaboradores_ids as $colab_id) {
        $result = enviar_push_colaborador($colab_id, $titulo, $mensagem, $url);
        if ($result['success']) {
            $enviadas_total += $result['enviadas'];
        } else {
            $falhas++;
        }
    }
    
    return [
        'success' => $enviadas_total > 0,
        'enviadas' => $enviadas_total,
        'falhas' => $falhas
    ];
}
```

---

## 🎯 Exemplos Práticos de Uso

### Exemplo 1: Notificar Colaborador ao Criar Ocorrência

Em `pages/ocorrencias_add.php`:

```php
// ... código existente de criação de ocorrência ...

$ocorrencia_id = $pdo->lastInsertId();

// Envia email (já existe)
require_once __DIR__ . '/../includes/email_templates.php';
enviar_email_ocorrencia($ocorrencia_id);

// ✅ NOVO: Envia notificação push
require_once __DIR__ . '/../includes/push_notifications.php';
enviar_push_colaborador(
    $colaborador_id,
    'Nova Ocorrência Registrada',
    'Uma nova ocorrência foi registrada no seu perfil',
    '/rh-privus/pages/colaborador_view.php?id=' . $colaborador_id
);

redirect('colaborador_view.php?id=' . $colaborador_id, 'Ocorrência registrada com sucesso!');
```

### Exemplo 2: Notificar Colaborador ao Aprovar Promoção

```php
// Quando aprovar promoção
enviar_push_colaborador(
    $colaborador_id,
    'Promoção Aprovada! 🎉',
    'Parabéns! Sua promoção foi aprovada.',
    '/rh-privus/pages/colaborador_view.php?id=' . $colaborador_id
);
```

### Exemplo 3: Notificar Múltiplos Colaboradores (Setor)

```php
// Notificar todos do setor sobre reunião
$pdo = getDB();
$stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE setor_id = ? AND status = 'ativo'");
$stmt->execute([$setor_id]);
$colaboradores = $stmt->fetchAll(PDO::FETCH_COLUMN);

enviar_push_colaboradores(
    $colaboradores,
    'Reunião de Setor',
    'Reunião marcada para amanhã às 14h',
    '/rh-privus/pages/dashboard.php'
);
```

### Exemplo 4: Notificar Colaborador Específico via API

```bash
curl -X POST http://localhost/rh-privus/api/push/send.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "colaborador_id": 123,
    "titulo": "Lembrete Importante",
    "mensagem": "Não esqueça de fechar o ponto hoje!",
    "url": "/rh-privus/pages/dashboard.php"
  }'
```

---

## ✅ Garantias de Privacidade

### Como Garantir que Apenas o Colaborador Recebe:

1. **Subscription Vinculada ao Login**
   - Quando colaborador faz login → registra dispositivo
   - Vincula `colaborador_id` ao `endpoint` único
   - Cada dispositivo tem endpoint diferente

2. **Busca Específica**
   - Você busca apenas subscriptions do `colaborador_id` específico
   - Envia push apenas para esses endpoints
   - **Impossível** outro colaborador receber

3. **Segurança**
   - Endpoint é único por dispositivo
   - Chaves de criptografia (`p256dh`, `auth`) são únicas
   - Não há como "interceptar" notificação de outro

---

## 🧪 Teste Prático

### 1. Colaborador A faz login e permite notificações
- Sistema registra dispositivo dele
- Vincula ao `colaborador_id` dele

### 2. Colaborador B faz login e permite notificações
- Sistema registra dispositivo dele
- Vincula ao `colaborador_id` dele

### 3. Você envia notificação para Colaborador A
```php
enviar_push_colaborador($colaborador_a_id, "Teste", "Esta é sua notificação");
```

**Resultado:**
- ✅ Colaborador A recebe
- ❌ Colaborador B NÃO recebe

---

## 📊 Resumo

| Cenário | Como Enviar | Quem Recebe |
|---------|-------------|-------------|
| **1 colaborador** | `colaborador_id: 123` | Apenas ele |
| **Múltiplos colaboradores** | Loop com IDs | Apenas eles |
| **Setor inteiro** | Busca por `setor_id` | Todos do setor |
| **Todos** | `broadcast: true` | Todos registrados |

---

**✅ Conclusão: SIM, você pode enviar notificações específicas para um colaborador, e apenas ele receberá!**

