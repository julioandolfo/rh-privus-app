# 🔧 Correção: Service Worker - Erro de Redirect

## ❌ Problema

Erro no console do navegador:
```
The FetchEvent for "<URL>" resulted in a network error response: 
a redirected response was used for a request whose redirect mode is not "follow".
```

## 🔍 Causa Raiz

O Service Worker estava interceptando requisições que resultavam em redirects (como quando `index.php` redireciona para `login.php` ou `dashboard.php`), mas o `fetch()` não estava configurado para seguir redirects automaticamente.

**Problemas específicos:**
1. Requisições para `/rh/` ou `/rh/index.php` resultam em redirects
2. O Service Worker tentava fazer cache dessas requisições
3. O `fetch()` não tinha `redirect: 'follow'` configurado
4. Respostas de redirect não devem ser cacheadas

## ✅ Correções Implementadas

### 1. Adicionado `redirect: 'follow'` em Todas as Requisições

**Antes:**
```javascript
return fetch(request);
```

**Depois:**
```javascript
return fetch(request, { redirect: 'follow' });
```

### 2. Ignorar Requisições que Podem Resultar em Redirects

**Adicionado:**
```javascript
// Ignora requisições que podem resultar em redirects (index.php, etc)
if (url.pathname === BASE_PATH + '/' || 
    url.pathname === BASE_PATH + '/index.php' ||
    url.pathname.endsWith('/')) {
  // Deixa o browser lidar normalmente com redirects
  return fetch(request, { redirect: 'follow' });
}
```

### 3. Não Fazer Cache de Respostas de Redirect

**Adicionado:**
```javascript
// Se a resposta foi um redirect (status 301, 302, etc), não faz cache
if (response.redirected || response.status === 301 || response.status === 302 || 
    response.status === 303 || response.status === 307 || response.status === 308) {
  return response; // Retorna sem fazer cache
}
```

### 4. Não Fazer Cache de Páginas PHP com Redirects

**Adicionado:**
```javascript
// Não faz cache de páginas PHP que podem ter redirects
if (responseUrl.pathname.endsWith('.php') && 
    (responseUrl.pathname.includes('index') || 
     responseUrl.pathname.includes('login') ||
     responseUrl.pathname.includes('dashboard'))) {
  return response; // Retorna sem fazer cache
}
```

## 📋 Arquivo Modificado

- ✅ `sw.js` - Service Worker atualizado com tratamento correto de redirects

## 🧪 Como Testar

### Teste 1: Verificar Console
1. Abra o DevTools (F12)
2. Vá na aba **Console**
3. Acesse `https://privus.com.br/rh/`
4. **Não deve aparecer** o erro de redirect

### Teste 2: Verificar Service Worker
1. Abra o DevTools (F12)
2. Vá em **Application** → **Service Workers**
3. Verifique se o Service Worker está ativo
4. Clique em **Update** se necessário

### Teste 3: Limpar Cache Antigo
Se ainda aparecer erro, limpe o cache do Service Worker:

```javascript
// Execute no console do navegador
navigator.serviceWorker.getRegistrations().then(function(registrations) {
    for(let registration of registrations) {
        registration.unregister();
    }
    caches.keys().then(function(names) {
        for (let name of names) {
            caches.delete(name);
        }
    });
    location.reload();
});
```

## 🔄 Como Forçar Atualização

### Método 1: Via DevTools
1. Abra DevTools (F12)
2. Vá em **Application** → **Service Workers**
3. Clique em **Unregister** no Service Worker antigo
4. Recarregue a página (Ctrl+Shift+R)

### Método 2: Via Console
Execute no console:
```javascript
navigator.serviceWorker.getRegistrations().then(function(registrations) {
    for(let registration of registrations) {
        registration.unregister();
    }
    location.reload();
});
```

### Método 3: Limpar Cache do Browser
1. Pressione **Ctrl+Shift+Delete**
2. Selecione **Cache** e **Service Workers**
3. Clique em **Limpar dados**
4. Recarregue a página

## 💡 O Que Mudou

### Antes:
- Service Worker tentava fazer cache de redirects
- `fetch()` não tinha `redirect: 'follow'` configurado
- Erro aparecia no console

### Depois:
- Service Worker ignora requisições que resultam em redirects
- Todas as requisições têm `redirect: 'follow'` configurado
- Respostas de redirect não são cacheadas
- Páginas PHP com redirects não são cacheadas
- Sem erros no console

## 🚨 Se Ainda Aparecer Erro

1. **Limpe completamente o cache do Service Worker** (veja métodos acima)
2. **Verifique se o arquivo `sw.js` foi atualizado no servidor**
3. **Teste em modo anônimo/privado** para descartar cache
4. **Verifique a versão do cache** - deve ser `rh-privus-v2`

---

**A correção foi aplicada. Limpe o cache do Service Worker e teste novamente!**

