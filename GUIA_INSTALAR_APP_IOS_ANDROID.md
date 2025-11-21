# 📱 Guia: Instalar PWA no iOS e Android

## 🎯 Resumo

Seu sistema já está configurado como PWA! Agora você só precisa **instalar no dispositivo**.

---

## 🤖 Android (Chrome/Edge)

### Método 1: Instalação Automática

1. **Acesse o sistema** no Chrome/Edge do Android
2. **Faça login** normalmente
3. **Aguarde alguns segundos**
4. **Aparecerá um banner** na parte inferior: "Adicionar à tela inicial"
5. **Toque em "Adicionar"**
6. ✅ **Pronto!** O app aparecerá na tela inicial

### Método 2: Menu do Browser

1. Acesse o sistema no Chrome/Edge
2. Toque no **menu (3 pontos)** no canto superior direito
3. Selecione **"Adicionar à tela inicial"** ou **"Instalar app"**
4. Confirme
5. ✅ **Pronto!**

### Método 3: Configurações do Chrome

1. No Chrome, vá em **Menu → Configurações**
2. Role até **"Adicionar à tela inicial"**
3. Selecione seu site
4. Toque em **"Adicionar"**

---

## 🍎 iOS (Safari)

### Passo a Passo:

1. **Abra o Safari** no iPhone/iPad (não funciona no Chrome iOS)
2. **Acesse** `http://seu-ip-local/rh-privus/` ou sua URL de produção
3. **Faça login** normalmente
4. **Toque no botão de compartilhar** (quadrado com seta para cima) na barra inferior
5. **Role para baixo** e encontre **"Adicionar à Tela de Início"**
6. **Toque** nele
7. **Personalize o nome** (opcional) - padrão: "RH Privus"
8. **Toque em "Adicionar"** no canto superior direito
9. ✅ **Pronto!** O app aparecerá na tela inicial

### Importante para iOS:

- ⚠️ **Safari apenas** - Chrome/Firefox no iOS não suportam instalação de PWA
- ⚠️ **HTTPS necessário** - Em produção, precisa de HTTPS (localhost funciona com HTTP)
- ⚠️ **iOS 16.4+** - Versões antigas têm suporte limitado

---

## 🌐 Para Testar em Dispositivos Físicos

### Problema: Localhost não funciona em dispositivos

Quando você acessa `http://localhost/rh-privus/` no celular, ele procura localhost **do celular**, não do computador.

### Solução 1: Usar IP Local

1. **Descubra o IP do seu computador:**
   - Windows: Abra CMD e digite `ipconfig`
   - Procure por "IPv4" (ex: `192.168.1.100`)

2. **Acesse do celular:**
   ```
   http://192.168.1.100/rh-privus/
   ```
   (Substitua pelo seu IP)

3. **Importante:** Celular e computador devem estar na **mesma rede WiFi**

### Solução 2: Usar ngrok (Túnel)

1. **Instale ngrok:** https://ngrok.com/download
2. **Execute:**
   ```bash
   ngrok http 80
   ```
3. **Copie a URL** gerada (ex: `https://abc123.ngrok.io`)
4. **Acesse do celular:** `https://abc123.ngrok.io/rh-privus/`

### Solução 3: Deploy em Servidor

1. Faça upload para seu servidor de produção
2. Configure HTTPS (necessário para iOS)
3. Acesse normalmente do celular

---

## ✅ Verificar se PWA Está Funcionando

### Checklist:

- [ ] `manifest.json` existe e está acessível
- [ ] `sw.js` (Service Worker) está registrado
- [ ] OneSignal está configurado
- [ ] Ícones do app estão configurados
- [ ] HTTPS (para iOS em produção)

### Teste Rápido:

1. Abra o sistema no browser do celular
2. Abra DevTools (se possível) ou verifique:
   - Menu do browser mostra "Instalar app" ou "Adicionar à tela inicial"
   - Service Worker está ativo

---

## 🔧 Configurações Necessárias

### 1. Verificar Manifest.json

Certifique-se de que o `manifest.json` está acessível:
```
http://localhost/rh-privus/manifest.json
```

Deve retornar JSON, não 404.

### 2. Verificar Service Worker

No console do browser (F12), deve aparecer:
```
Service Worker registrado: http://localhost/rh-privus/
```

### 3. Verificar Ícones

Os ícones em `manifest.json` devem existir:
- `/rh-privus/assets/media/logos/favicon.png`

---

## 📱 Após Instalar

### Como Funciona:

1. **App aparece na tela inicial** com ícone personalizado
2. **Abre em janela própria** (sem barra do browser)
3. **Funciona offline** (recursos em cache)
4. **Notificações push funcionam** mesmo com app fechado

### Diferenças do Browser Normal:

- ✅ Sem barra de endereço
- ✅ Parece app nativo
- ✅ Ícone na tela inicial
- ✅ Abre mais rápido (cache)

---

## 🐛 Problemas Comuns

### Problema: Não aparece opção de instalar

**Solução:**
- Verifique se `manifest.json` está acessível
- Verifique se Service Worker está registrado
- Tente em modo anônimo/privado

### Problema: iOS não mostra "Adicionar à Tela de Início"

**Solução:**
- Use Safari (não Chrome)
- Precisa HTTPS em produção (localhost funciona HTTP)
- iOS 16.4+ recomendado

### Problema: App não abre offline

**Solução:**
- Verifique se Service Worker está cacheando recursos
- Abra o app uma vez online primeiro
- Verifique console para erros

---

## 🎯 Próximos Passos

1. ✅ Teste no Android primeiro (mais fácil)
2. ✅ Depois teste no iOS
3. ✅ Configure HTTPS para produção
4. ✅ Teste notificações push em ambos

---

## 📚 Recursos Adicionais

- **Teste PWA:** https://web.dev/pwa-checklist/
- **Manifest Validator:** https://manifest-validator.appspot.com/
- **Service Worker Status:** Verifique em DevTools → Application → Service Workers

---

**Pronto para instalar! Siga os passos acima! 🚀**

