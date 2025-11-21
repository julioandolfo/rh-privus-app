# 🔧 Correção: Erro 404 ao Enviar Notificação

## ❌ Problema

Ao tentar enviar uma notificação push, aparece o erro:
```
Erro ao enviar notificação (HTTP 404)
```

Mas o registro do player funciona normalmente (HTTP 200).

## 🔍 Causa Raiz

O erro 404 pode estar acontecendo porque:

1. **A função `get_base_url()` está retornando URL incorreta**
2. **O arquivo `api/onesignal/send.php` não existe no servidor**
3. **O caminho da API está sendo calculado incorretamente**

## ✅ Correções Implementadas

### 1. Função `get_base_url()` Melhorada

**Antes:**
```php
function get_base_url() {
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname($script);
    // ... retornava caminho baseado no script atual
}
```

**Problema:** Quando chamada de dentro de `includes/push_notifications.php`, o caminho podia estar incorreto.

**Depois:**
```php
function get_base_url() {
    // Detecta automaticamente pelo REQUEST_URI
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $requestUri = strtok($requestUri, '?'); // Remove query string
    
    // Detecta se está em /rh-privus/ ou /rh/
    if (strpos($requestUri, '/rh-privus') !== false) {
        $basePath = '/rh-privus';
    } elseif (strpos($requestUri, '/rh/') !== false) {
        $basePath = '/rh';
    } else {
        $basePath = '/rh'; // Padrão produção
    }
    
    return $protocol . '://' . $host . $basePath;
}
```

### 2. Logs de Debug Adicionados

Adicionados logs detalhados para identificar o problema:

```php
error_log("enviar_push_usuario - Tentando acessar: {$apiUrl}");
error_log("enviar_push_usuario - HTTP Code: {$httpCode}");
error_log("enviar_push_usuario - URL Efetiva: {$effectiveUrl}");
```

### 3. Tratamento de Erro 404 Específico

```php
if ($httpCode === 404) {
    error_log("Erro 404: Arquivo não encontrado em {$apiUrl}");
    throw new Exception("API não encontrada (404). URL tentada: {$apiUrl}");
}
```

## 📋 Arquivos Modificados

- ✅ `includes/functions.php` - Função `get_base_url()` melhorada
- ✅ `includes/push_notifications.php` - Logs e tratamento de erro 404 adicionados

## 🧪 Como Diagnosticar

### Teste 1: Verificar se o Arquivo Existe

Acesse diretamente no navegador:
```
https://privus.com.br/rh/api/onesignal/send.php
```

**Se aparecer:**
- `{"success":false,"message":"Sem permissão"}` → ✅ Arquivo existe e funciona
- `404 Not Found` → ❌ Arquivo não existe no servidor

### Teste 2: Verificar Logs de Erro

No servidor, execute:
```bash
tail -50 /var/log/apache2/error.log
# ou
tail -50 /var/log/nginx/error.log
```

Procure por linhas como:
```
enviar_push_usuario - Tentando acessar: https://privus.com.br/rh/api/onesignal/send.php
enviar_push_usuario - HTTP Code: 404
```

Isso vai mostrar qual URL está sendo tentada.

### Teste 3: Verificar Função get_base_url()

Crie um arquivo de teste `test_base_url.php`:

```php
<?php
require_once __DIR__ . '/includes/functions.php';
echo "Base URL: " . get_base_url() . "\n";
echo "API URL seria: " . get_base_url() . "/api/onesignal/send.php\n";
?>
```

Acesse: `https://privus.com.br/rh/test_base_url.php`

Deve mostrar:
```
Base URL: https://privus.com.br/rh
API URL seria: https://privus.com.br/rh/api/onesignal/send.php
```

## 🔧 Soluções Possíveis

### Solução 1: Arquivo Não Existe no Servidor

Se o arquivo não existe, você precisa enviá-lo:

1. Verifique se `api/onesignal/send.php` existe localmente
2. Envie para o servidor via FTP/SFTP
3. Configure permissões: `chmod 644 api/onesignal/send.php`

### Solução 2: Caminho Incorreto

Se a URL gerada estiver incorreta, verifique:

1. A função `get_base_url()` está retornando o caminho correto?
2. O caminho base está correto (`/rh` ou `/rh-privus`)?

### Solução 3: Problema de Sessão

Se o erro for de permissão (403), pode ser problema de sessão:

1. Verifique se a sessão está sendo passada corretamente no cURL
2. Verifique se o usuário tem permissão (ADMIN ou RH)

## 📝 Próximos Passos

1. **Verifique os logs** para ver qual URL está sendo tentada
2. **Teste a API diretamente** no navegador
3. **Verifique se o arquivo existe** no servidor
4. **Me envie os logs** para análise mais detalhada

## 🔍 Verificar Agora

Execute no console do navegador (F12) após tentar enviar uma notificação:

```javascript
// Verifica se há erros na Network
// Vá em Network → procure por "send.php"
// Veja qual URL está sendo chamada e qual o status
```

Ou verifique os logs do servidor para ver a URL exata que está sendo tentada.

---

**As correções foram aplicadas. Verifique os logs para identificar a URL exata que está causando o 404!**

