# 📋 Análise da Implementação PWA + Capacitor

## ✅ Resumo Executivo

**SIM, a implementação proposta funcionaria**, mas requer **adaptações importantes** para funcionar corretamente no seu ambiente localhost e manter compatibilidade com o sistema atual.

---

## 🔍 Análise do Projeto Atual

### Estrutura Identificada:
- ✅ Sistema PHP tradicional com sessões (`$_SESSION`)
- ✅ Autenticação baseada em sessões PHP
- ✅ APIs que dependem de `$_SESSION['usuario']`
- ✅ Páginas PHP que usam `require_login()` e `check_permission()`
- ✅ Laragon (Windows) - Apache/Nginx local
- ✅ Composer já configurado
- ✅ Estrutura de múltiplas empresas (`usuarios_empresas`)

### Pontos Críticos:
1. **Sistema híbrido necessário**: Páginas PHP tradicionais + APIs JWT
2. **Compatibilidade**: Manter funcionamento atual enquanto migra
3. **Localhost**: Service Worker e PWA têm limitações em HTTP local

---

## ⚠️ Problemas Identificados no Guia

### 1. **Service Worker no Localhost**
```javascript
// ❌ PROBLEMA: Service Workers NÃO funcionam em file://
// ✅ SOLUÇÃO: Precisa de servidor HTTP (você tem Laragon, então OK)
```

**Status**: ✅ **RESOLVIDO** - Laragon já fornece servidor HTTP

### 2. **CORS e Autenticação Híbrida**
O guia propõe APIs com JWT, mas seu sistema atual usa sessões PHP. Isso cria dois sistemas de autenticação rodando simultaneamente.

**Impacto**: 
- Páginas PHP continuam usando sessões ✅
- APIs podem usar JWT ✅
- Precisa de compatibilidade entre ambos ⚠️

### 3. **Falta de empresas_ids no JWT**
O guia não inclui `empresas_ids` no token JWT, mas seu sistema usa isso:

```php
// Seu sistema atual usa:
'empresas_ids' => $empresas_ids, // Array com IDs das empresas
```

**Solução necessária**: Incluir `empresas_ids` no payload do JWT

### 4. **Caminhos Relativos**
O guia usa caminhos absolutos (`/api/auth/login.php`), mas seu projeto pode estar em subpasta no Laragon.

**Exemplo Laragon**: `http://localhost/rh-privus/`

---

## 🔧 Adaptações Necessárias

### 1. **API de Login - Versão Corrigida**

```php
<?php
// api/auth/login.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
        
        // ✅ CORREÇÃO: Busca empresas do usuário (igual ao login.php atual)
        $stmt_empresas = $pdo->prepare("
            SELECT empresa_id 
            FROM usuarios_empresas 
            WHERE usuario_id = ?
        ");
        $stmt_empresas->execute([$usuario['id']]);
        $empresas_ids = $stmt_empresas->fetchAll(PDO::FETCH_COLUMN);
        
        $userData = [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'role' => $usuario['role'],
            'empresa_id' => $usuario['empresa_id'], // Compatibilidade
            'empresas_ids' => $empresas_ids, // ✅ ADICIONADO
            'setor_id' => $usuario['setor_id'] ?? null,
            'colaborador_id' => $usuario['colaborador_id']
        ];
    } else {
        // Tenta login como colaborador (igual ao login.php atual)
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
                
                // ✅ CORREÇÃO: Busca empresas do usuário
                $stmt_empresas = $pdo->prepare("
                    SELECT empresa_id 
                    FROM usuarios_empresas 
                    WHERE usuario_id = ?
                ");
                $stmt_empresas->execute([$usuario['id']]);
                $empresas_ids = $stmt_empresas->fetchAll(PDO::FETCH_COLUMN);
                
                $userData = [
                    'id' => $usuario['id'],
                    'nome' => $usuario['nome'],
                    'email' => $usuario['email'],
                    'role' => $usuario['role'],
                    'empresa_id' => $usuario['empresa_id'],
                    'empresas_ids' => $empresas_ids, // ✅ ADICIONADO
                    'setor_id' => $usuario['setor_id'] ?? null,
                    'colaborador_id' => $usuario['colaborador_id']
                ];
            } else {
                $userData = [
                    'id' => null,
                    'nome' => $colaborador['nome_completo'],
                    'email' => $colaborador['email_pessoal'] ?? $email,
                    'role' => 'COLABORADOR',
                    'empresa_id' => $colaborador['empresa_id'],
                    'empresas_ids' => [$colaborador['empresa_id']], // ✅ ADICIONADO
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

### 2. **Middleware api_auth.php - Versão Corrigida**

```php
<?php
// includes/api_auth.php
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
    
    $token = str_replace('Bearer ', '', $authHeader);
    
    try {
        $secretKey = getenv('JWT_SECRET') ?: 'sua-chave-secreta-super-segura-aqui-mude-isso';
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        
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

// ✅ ADICIONADO: Função para compatibilidade com código existente
function can_access_empresa_jwt($userData, $empresa_id) {
    if ($userData['role'] === 'ADMIN') {
        return true;
    }
    
    if ($userData['role'] === 'RH') {
        if (isset($userData['empresas_ids']) && is_array($userData['empresas_ids'])) {
            return in_array($empresa_id, $userData['empresas_ids']);
        }
        if (isset($userData['empresa_id']) && $userData['empresa_id'] == $empresa_id) {
            return true;
        }
    }
    
    return false;
}
```

### 3. **Manifest.json - Caminhos Relativos**

```json
{
  "name": "RH Privus",
  "short_name": "RH Privus",
  "description": "Sistema de Gestão de Recursos Humanos",
  "start_url": "/rh-privus/",
  "scope": "/rh-privus/",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#009ef7",
  "orientation": "portrait-primary",
  "icons": [
    {
      "src": "/rh-privus/assets/media/logos/icon-72x72.png",
      "sizes": "72x72",
      "type": "image/png"
    }
    // ... outros ícones com caminho correto
  ]
}
```

**⚠️ IMPORTANTE**: Ajuste `/rh-privus/` conforme sua configuração do Laragon!

### 4. **Service Worker - Caminhos Corrigidos**

```javascript
// sw.js
const CACHE_NAME = 'rh-privus-v1';
const BASE_PATH = '/rh-privus'; // ✅ AJUSTAR conforme seu Laragon

const urlsToCache = [
  BASE_PATH + '/',
  BASE_PATH + '/login.php',
  BASE_PATH + '/pages/dashboard.php',
  BASE_PATH + '/assets/css/style.bundle.css',
  BASE_PATH + '/assets/js/scripts.bundle.js',
  BASE_PATH + '/assets/plugins/global/plugins.bundle.css',
  BASE_PATH + '/assets/plugins/global/plugins.bundle.js'
];

// ... resto do código igual, mas usando BASE_PATH nas URLs
```

### 5. **auth.js - Caminhos Relativos**

```javascript
// assets/js/auth.js
const Auth = {
    // ... código igual ...
    
    async login(email, senha) {
        // ✅ CORREÇÃO: Caminho relativo ou absoluto conforme necessário
        const basePath = window.location.pathname.split('/').slice(0, -1).join('/') || '';
        const response = await fetch(basePath + '/api/auth/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email, senha })
        });
        
        // ... resto igual ...
    }
};
```

---

## 🚀 Como Funcionaria na Prática

### Cenário 1: Acesso Web Normal (Desktop/Mobile Browser)
1. Usuário acessa `http://localhost/rh-privus/login.php`
2. Pode fazer login tradicional (sessão PHP) ✅
3. OU fazer login via API (JWT) ✅
4. Service Worker registra e cacheia recursos
5. PWA pode ser instalado no dispositivo

### Cenário 2: App Mobile (Capacitor)
1. App abre e carrega `http://localhost/rh-privus/` (ou URL de produção)
2. Login via API JWT ✅
3. Token salvo no localStorage
4. Todas as requisições incluem `Authorization: Bearer {token}`
5. APIs respondem normalmente

### Cenário 3: Híbrido (Durante Migração)
1. Páginas PHP continuam usando sessões ✅
2. APIs podem usar JWT OU sessões (compatibilidade) ✅
3. Frontend pode escolher qual método usar

---

## ⚡ Funcionaria no Localhost?

### ✅ SIM, com ressalvas:

1. **Service Worker**: ✅ Funciona em `http://localhost`
2. **PWA**: ✅ Funciona, mas instalação pode variar por browser
3. **JWT**: ✅ Funciona normalmente
4. **Capacitor**: ⚠️ Precisa de URL acessível (não pode ser `localhost` para dispositivos físicos)

### ⚠️ Limitações no Localhost:

1. **Capacitor + Dispositivo Físico**:
   - Não consegue acessar `localhost` do computador
   - **Solução**: Usar IP local (`http://192.168.x.x/rh-privus/`) ou ngrok/tunnel

2. **HTTPS**:
   - PWA funciona melhor com HTTPS
   - Service Worker requer HTTPS em produção
   - **Localhost**: HTTP funciona ✅
   - **Produção**: Precisa HTTPS ⚠️

3. **CORS**:
   - APIs precisam configurar CORS corretamente
   - Headers já estão no guia ✅

---

## 📝 Checklist de Implementação Adaptado

### Fase 1: Preparação
- [ ] Instalar `firebase/php-jwt` via Composer
- [ ] Criar pasta `api/auth/`
- [ ] Configurar variável de ambiente `JWT_SECRET` (ou usar fallback)

### Fase 2: APIs JWT
- [ ] Criar `api/auth/login.php` (versão corrigida acima)
- [ ] Criar `includes/api_auth.php` (versão corrigida acima)
- [ ] Adaptar APIs existentes para aceitar JWT OU sessão (híbrido)

### Fase 3: Frontend
- [ ] Criar `assets/js/auth.js`
- [ ] Modificar `login.php` para suportar ambos os métodos
- [ ] Criar `manifest.json` (ajustar caminhos)
- [ ] Criar `sw.js` (ajustar caminhos)

### Fase 4: PWA
- [ ] Adicionar meta tags no `includes/header.php`
- [ ] Criar ícones do app (vários tamanhos)
- [ ] Testar instalação PWA no browser

### Fase 5: Capacitor (Opcional)
- [ ] Instalar Node.js e npm
- [ ] `npm init -y`
- [ ] `npm install @capacitor/core @capacitor/cli`
- [ ] `npx cap init`
- [ ] Configurar `capacitor.config.json`
- [ ] Testar em emulador/dispositivo

---

## 🎯 Recomendações

### 1. **Implementação Gradual**
Não migre tudo de uma vez. Mantenha ambos os sistemas funcionando:
- Páginas PHP: continuam com sessões
- APIs novas: usam JWT
- APIs antigas: podem aceitar ambos

### 2. **Testes Incrementais**
- Teste login JWT isoladamente
- Teste APIs com JWT
- Teste PWA no browser
- Só depois teste Capacitor

### 3. **Variáveis de Ambiente**
Use arquivo `.env` ou configure no Laragon:
```php
// config/jwt.php (novo arquivo)
return [
    'secret' => getenv('JWT_SECRET') ?: 'sua-chave-super-secreta-aqui'
];
```

### 4. **Compatibilidade com empresas_ids**
Certifique-se de que todas as funções que usam `can_access_empresa()` funcionem com JWT também.

---

## ✅ Conclusão

**A implementação FUNCIONARIA**, mas precisa das correções acima para:
1. ✅ Incluir `empresas_ids` no JWT
2. ✅ Ajustar caminhos para Laragon/localhost
3. ✅ Manter compatibilidade com sistema atual
4. ✅ Testar incrementalmente

**Tempo estimado**: 2-3 dias de desenvolvimento + testes

**Risco**: Baixo (se fizer gradualmente e manter compatibilidade)

---

## 📞 Próximos Passos

1. Instalar dependências (Composer)
2. Criar API de login corrigida
3. Testar login JWT isoladamente
4. Adaptar uma API existente como teste
5. Implementar frontend auth.js
6. Testar PWA no browser
7. Só depois partir para Capacitor

**Boa sorte! 🚀**

