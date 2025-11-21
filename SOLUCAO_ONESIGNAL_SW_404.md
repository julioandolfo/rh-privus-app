# 🔧 Solução: Erro 404 OneSignalSDKWorker.js

## ❌ Problema

```
GET https://privus.com.br/OneSignalSDKWorker.js 404 (Not Found)
```

O OneSignal está tentando carregar o Service Worker na raiz do domínio, mas o arquivo está em `/rh/`.

## ✅ Solução

### 1. Verifique se o arquivo existe

O arquivo `OneSignalSDKWorker.js` deve estar na raiz do projeto:
- ✅ `C:\laragon\www\rh-privus\OneSignalSDKWorker.js` (localhost)
- ✅ `/var/www/html/rh/OneSignalSDKWorker.js` (produção)

### 2. O código já detecta automaticamente

O código em `onesignal-init.js` detecta automaticamente o caminho base:
- Se está em `/rh/` → usa `/rh/OneSignalSDKWorker.js`
- Se está em `/rh-privus/` → usa `/rh-privus/OneSignalSDKWorker.js`

### 3. Se ainda não funcionar

Adicione uma meta tag no `<head>` para forçar o caminho:

```html
<meta name="onesignal-service-worker-path" content="/rh/OneSignalSDKWorker.js">
```

Ou configure diretamente no código:

```javascript
OneSignal.init({
    appId: 'seu-app-id',
    serviceWorkerPath: '/rh/OneSignalSDKWorker.js',
    serviceWorkerParam: {
        scope: '/rh/'
    }
});
```

## 🔍 Debug

Abra o console e verifique:

```javascript
console.log('Base path:', window.location.pathname);
```

Deve mostrar algo como `/rh/pages/dashboard.php` ou `/rh-privus/pages/dashboard.php`.

## ✅ Teste

1. Recarregue a página
2. Abra o console (F12)
3. Procure por: `🔧 Base path detectado para Service Worker:`
4. Verifique se está correto (`/rh` ou `/rh-privus`)

---

**O código foi atualizado para detectar melhor o caminho. Teste novamente!**

