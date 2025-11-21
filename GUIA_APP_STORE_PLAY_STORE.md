# 📱 Guia: Publicar App nas Lojas (App Store e Play Store)

## 🎯 Opções Disponíveis

Existem várias formas de converter seu PWA em app nativo para as lojas:

### 1. **Capacitor** (⭐ RECOMENDADO)
- ✅ Mantém seu código web existente
- ✅ Gera apps nativos para iOS e Android
- ✅ Adiciona funcionalidades nativas quando necessário
- ✅ Mantido pela equipe do Ionic
- ✅ Suporte moderno e ativo

### 2. **Cordova/PhoneGap**
- ⚠️ Mais antigo, mas ainda funciona
- ⚠️ Menos mantido ativamente
- ✅ Muitos plugins disponíveis

### 3. **PWA Builder** (Microsoft)
- ✅ Ferramenta online simples
- ⚠️ Limitado a funcionalidades básicas
- ⚠️ Menos controle sobre o resultado

### 4. **Bubblewrap** (Google)
- ✅ Específico para Android
- ⚠️ Não gera app iOS

---

## 🚀 Solução Recomendada: Capacitor

### Por que Capacitor?

1. **Mantém seu código PHP/Web** - Não precisa reescrever nada
2. **Gera apps nativos** - iOS (.ipa) e Android (.apk/.aab)
3. **Funcionalidades nativas** - Acesso a câmera, GPS, notificações nativas, etc.
4. **Fácil de manter** - Atualizações no site refletem no app automaticamente
5. **Gratuito** - Open source, sem custos

---

## 📋 Requisitos

### Para Android (Play Store):
- ✅ Conta Google Play Developer ($25 único)
- ✅ Computador Windows/Mac/Linux
- ✅ Node.js instalado

### Para iOS (App Store):
- ✅ Conta Apple Developer ($99/ano)
- ✅ Mac com Xcode instalado
- ✅ Node.js instalado

---

## 🔧 Instalação do Capacitor

### Passo 1: Instalar Capacitor CLI

```bash
npm install -g @capacitor/cli
npm install @capacitor/core @capacitor/app @capacitor/haptics @capacitor/keyboard @capacitor/status-bar
```

### Passo 2: Inicializar Capacitor no Projeto

```bash
cd /caminho/para/rh-privus
npx cap init
```

**Perguntas durante a inicialização:**
- **App name:** RH Privus
- **App ID:** br.com.privus.rh (ou seu domínio invertido)
- **Web dir:** . (raiz do projeto)

### Passo 3: Adicionar Plataformas

```bash
# Adicionar Android
npx cap add android

# Adicionar iOS (apenas no Mac)
npx cap add ios
```

### Passo 4: Configurar Capacitor

Edite `capacitor.config.json`:

```json
{
  "appId": "br.com.privus.rh",
  "appName": "RH Privus",
  "webDir": ".",
  "server": {
    "url": "https://privus.com.br/rh",
    "cleartext": false
  },
  "plugins": {
    "SplashScreen": {
      "launchShowDuration": 2000,
      "backgroundColor": "#009ef7"
    }
  }
}
```

### Passo 5: Sincronizar com Plataformas

```bash
# Sincroniza arquivos web com projetos nativos
npx cap sync
```

---

## 📱 Configuração Android

### Passo 1: Abrir Projeto no Android Studio

```bash
npx cap open android
```

### Passo 2: Configurar App

1. Abra `android/app/src/main/AndroidManifest.xml`
2. Verifique permissões necessárias
3. Configure ícones e splash screen

### Passo 3: Gerar Assinatura (Keystore)

```bash
keytool -genkey -v -keystore rh-privus-release.keystore -alias rh-privus -keyalg RSA -keysize 2048 -validity 10000
```

### Passo 4: Configurar Assinatura

Edite `android/app/build.gradle`:

```gradle
android {
    signingConfigs {
        release {
            storeFile file('../rh-privus-release.keystore')
            storePassword 'sua_senha'
            keyAlias 'rh-privus'
            keyPassword 'sua_senha'
        }
    }
    buildTypes {
        release {
            signingConfig signingConfigs.release
        }
    }
}
```

### Passo 5: Gerar APK/AAB

No Android Studio:
1. **Build** → **Generate Signed Bundle / APK**
2. Escolha **Android App Bundle (.aab)** para Play Store
3. Selecione o keystore criado
4. Gere o arquivo

### Passo 6: Publicar na Play Store

1. Acesse [Google Play Console](https://play.google.com/console)
2. Crie novo app
3. Preencha informações do app
4. Faça upload do arquivo .aab
5. Configure preços e distribuição
6. Envie para revisão

---

## 🍎 Configuração iOS

### Passo 1: Abrir Projeto no Xcode

```bash
npx cap open ios
```

### Passo 2: Configurar Certificados

1. Abra Xcode
2. Selecione o projeto
3. Vá em **Signing & Capabilities**
4. Selecione seu **Team** (conta Apple Developer)
5. Xcode gerará certificados automaticamente

### Passo 3: Configurar App ID

1. Acesse [Apple Developer Portal](https://developer.apple.com)
2. Crie novo **App ID**
3. Use o mesmo `appId` do Capacitor: `br.com.privus.rh`

### Passo 4: Gerar Archive

No Xcode:
1. Selecione **Any iOS Device** como destino
2. **Product** → **Archive**
3. Aguarde o build completar

### Passo 5: Publicar na App Store

1. No **Organizer** (Window → Organizer)
2. Selecione o archive criado
3. Clique em **Distribute App**
4. Escolha **App Store Connect**
5. Siga o assistente
6. Faça upload

### Passo 6: Configurar na App Store Connect

1. Acesse [App Store Connect](https://appstoreconnect.apple.com)
2. Crie novo app
3. Preencha informações:
   - Nome: RH Privus
   - Categoria: Business
   - Descrição, screenshots, etc.
4. Envie para revisão

---

## 🎨 Melhorias para App Nativo

### 1. Ícones e Splash Screen

Capacitor pode gerar automaticamente:

```bash
npm install @capacitor/assets
npx capacitor-assets generate --iconPath assets/media/logos/favicon.png --splashPath assets/media/logos/favicon.png
```

### 2. Status Bar (Barra de Status)

```javascript
import { StatusBar, Style } from '@capacitor/status-bar';

// Definir cor da barra de status
StatusBar.setStyle({ style: Style.Light });
StatusBar.setBackgroundColor({ color: '#009ef7' });
```

### 3. Deep Links (Links Diretos)

Configure no `capacitor.config.json`:

```json
{
  "plugins": {
    "App": {
      "launchUrl": "https://privus.com.br/rh"
    }
  }
}
```

### 4. Notificações Nativas

Capacitor suporta notificações nativas além do OneSignal:

```bash
npm install @capacitor/push-notifications
```

---

## 📝 Checklist Antes de Publicar

### Android:
- [ ] Ícone do app configurado (512x512px)
- [ ] Splash screen configurado
- [ ] Nome e descrição do app
- [ ] Screenshots (pelo menos 2)
- [ ] Política de privacidade (obrigatório)
- [ ] App assinado corretamente
- [ ] Testado em dispositivos reais

### iOS:
- [ ] Ícone do app configurado (1024x1024px)
- [ ] Splash screen configurado
- [ ] Nome e descrição do app
- [ ] Screenshots para diferentes tamanhos de tela
- [ ] Política de privacidade (obrigatório)
- [ ] App ID configurado
- [ ] Certificados válidos
- [ ] Testado em dispositivos reais

---

## 💰 Custos

### Google Play Store:
- **Taxa única:** $25 USD (válido para sempre)
- **Sem taxas de atualização**

### Apple App Store:
- **Taxa anual:** $99 USD/ano
- **Sem taxas de atualização**

---

## 🔄 Atualizações do App

### Opção 1: Atualização Automática (Recomendado)

Se você configurar o Capacitor para apontar para seu servidor web:

```json
{
  "server": {
    "url": "https://privus.com.br/rh"
  }
}
```

O app sempre carregará a versão mais recente do site, sem precisar atualizar nas lojas!

### Opção 2: Atualização via Loja

Quando fizer mudanças significativas:
1. Atualize o código
2. Execute `npx cap sync`
3. Gere novo build
4. Publique atualização nas lojas

---

## 🆘 Problemas Comuns

### Android: "App não instalado"
- Verifique se o keystore está correto
- Confirme que está usando release build, não debug

### iOS: "No signing certificate found"
- Verifique se está logado com conta Apple Developer
- Confirme que o App ID está criado no portal

### App não carrega conteúdo
- Verifique a URL no `capacitor.config.json`
- Confirme que o servidor está acessível
- Verifique CORS se necessário

---

## 📚 Recursos Adicionais

- [Documentação Capacitor](https://capacitorjs.com/docs)
- [Google Play Console](https://play.google.com/console)
- [App Store Connect](https://appstoreconnect.apple.com)
- [Guia de Publicação Android](https://developer.android.com/distribute/googleplay/start)
- [Guia de Publicação iOS](https://developer.apple.com/app-store/review/guidelines/)

---

## 🎯 Próximos Passos

1. ✅ Instalar Capacitor CLI
2. ✅ Inicializar projeto Capacitor
3. ✅ Adicionar plataformas (Android/iOS)
4. ✅ Configurar ícones e splash screen
5. ✅ Testar localmente
6. ✅ Gerar builds de produção
7. ✅ Publicar nas lojas

**Tempo estimado:** 2-4 horas para configuração inicial + tempo de revisão das lojas (1-7 dias)

