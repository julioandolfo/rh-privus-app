# 🔧 Solução Definitiva: OneSignal Service Worker 404

## ❌ Problema Persistente

O OneSignal continua tentando carregar o Service Worker da raiz:
```
https://privus.com.br/OneSignalSDKWorker.js ❌
```

Ao invés de:
```
https://privus.com.br/rh/OneSignalSDKWorker.js ✅
```

## ✅ Solução Aplicada

### 1. Meta Tag no HTML (ANTES do SDK)
Adicionada em `includes/header.php` e `login.php` ANTES do script do OneSignal.

### 2. Configuração Global (window.OneSignalConfig)
Definida ANTES do SDK carregar para garantir que seja lida.

### 3. JavaScript Melhorado
O código agora usa a configuração global se disponível.

## 🚨 Se AINDA Não Funcionar

O OneSignal pode estar ignorando completamente a configuração. Nesse caso, você tem 2 opções:

### Opção 1: Copiar Arquivo para Raiz (Mais Rápido)

No servidor de produção, execute:

```bash
cd /home/privus/public_html
cp rh/OneSignalSDKWorker.js OneSignalSDKWorker.js
```

Isso permite que o OneSignal encontre o arquivo na raiz enquanto mantém o original em `/rh/`.

### Opção 2: Criar Symlink

```bash
cd /home/privus/public_html
ln -s rh/OneSignalSDKWorker.js OneSignalSDKWorker.js
```

### Opção 3: .htaccess Redirect

Crie/edite `/home/privus/public_html/.htaccess`:

```apache
# Redirect OneSignal Service Worker
RewriteEngine On
RewriteRule ^OneSignalSDKWorker\.js$ /rh/OneSignalSDKWorker.js [L]
```

## 🧪 Teste

1. Recarregue a página completamente (Ctrl+Shift+R)
2. Verifique o console:
   - `🔧 Usando Service Worker Path: /rh/OneSignalSDKWorker.js`
   - `🔧 Configuração OneSignal completa: {...}`
3. Se ainda der 404, use uma das opções acima

---

**Recomendação: Use a Opção 1 (copiar arquivo) - é a mais simples e funciona imediatamente!**

