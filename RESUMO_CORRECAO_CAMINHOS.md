# ✅ Correção: Caminhos Dinâmicos para Localhost e Produção

## 🎯 Problema Resolvido

O sistema estava usando caminhos fixos (`/rh-privus/` ou `/rh/`), mas:
- **Localhost**: usa `/rh-privus/`
- **Produção**: usa `/rh/`

## ✅ Solução Implementada

### 1. Manifest.json Dinâmico (`manifest.php`)

Criado arquivo PHP que detecta automaticamente o caminho:
- Detecta se está em `/rh-privus/` ou `/rh/`
- Gera o manifest.json com os caminhos corretos
- Funciona em ambos os ambientes

**Arquivos atualizados:**
- `manifest.php` (novo)
- `includes/header.php` → usa `manifest.php`
- `login.php` → usa `manifest.php`

### 2. Service Worker (`sw.js`)

Atualizado para detectar automaticamente o caminho base:
```javascript
// Detecta automaticamente se está em /rh-privus/ ou /rh/
let BASE_PATH = '/rh'; // Padrão produção
if (swPath.includes('/rh-privus')) {
    BASE_PATH = '/rh-privus';
}
```

### 3. JavaScript de Detecção (`pwa-service-worker.js`)

Melhorado para detectar por:
- Caminho da URL
- Hostname (localhost vs produção)
- Fallback inteligente

### 4. OneSignal Init (`onesignal-init.js`)

Já estava detectando corretamente, mantido como está.

## 📋 Arquivos Modificados

1. ✅ `manifest.php` - Criado (manifest dinâmico)
2. ✅ `manifest.json` - Mantido como fallback
3. ✅ `sw.js` - Atualizado para detecção automática
4. ✅ `assets/js/pwa-service-worker.js` - Melhorado
5. ✅ `includes/header.php` - Usa `manifest.php`
6. ✅ `login.php` - Usa `manifest.php`

## 🧪 Como Testar

### Localhost:
1. Acesse: `http://localhost/rh-privus/manifest.php`
2. Deve retornar JSON com `"start_url": "/rh-privus/"`
3. Instale o PWA
4. Deve funcionar corretamente

### Produção:
1. Acesse: `http://seuservidor.com/rh/manifest.php`
2. Deve retornar JSON com `"start_url": "/rh/"`
3. Instale o PWA
4. Deve funcionar corretamente

## 🔍 Verificação

Abra o console do browser (F12) e verifique:
- `manifest.php` retorna JSON correto
- Service Worker registrado com scope correto
- OneSignal inicializa com caminhos corretos

## 📝 Notas

- O `manifest.json` estático ainda existe como fallback
- O sistema detecta automaticamente qual ambiente está rodando
- Não precisa mais alterar caminhos manualmente ao fazer deploy

---

**Pronto! O sistema agora funciona automaticamente em ambos os ambientes! 🚀**

