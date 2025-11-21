# 🍎 Guia Rápido: Quando Tiver Mac

## ⚡ Setup Rápido iOS (5 minutos)

Quando você tiver acesso a um Mac, siga estes passos:

---

## 📋 Pré-requisitos

1. **Mac com macOS** (qualquer versão recente)
2. **Xcode** instalado (via App Store)
3. **Conta Apple Developer** ($99/ano) - para publicar na App Store

---

## 🚀 Passo a Passo

### 1. Instalar Xcode

```bash
# Abra App Store no Mac
# Procure por "Xcode"
# Instale (pode demorar 10-20 minutos)
```

### 2. Instalar CocoaPods

```bash
# Abra Terminal no Mac
sudo gem install cocoapods
```

### 3. Copiar Projeto para Mac

```bash
# Copie a pasta rh-privus para o Mac
# Via USB, Dropbox, Git, etc.
```

### 4. Instalar Dependências

```bash
cd /caminho/para/rh-privus
npm install
```

### 5. Adicionar Plataforma iOS

```bash
npx cap add ios
```

**Isso vai criar a pasta `ios/` com o projeto Xcode.**

### 6. Sincronizar

```bash
npx cap sync ios
```

### 7. Abrir no Xcode

```bash
npx cap open ios
```

### 8. Testar

1. No Xcode, selecione um simulador iOS
2. Clique em **Run** (▶️)
3. App deve abrir no simulador

---

## ✅ Pronto!

Agora você pode:
- ✅ Testar no simulador iOS
- ✅ Testar em iPhone físico
- ✅ Gerar build de produção
- ✅ Publicar na App Store

---

## 🔧 Comandos Úteis

```bash
# Sincronizar iOS
npx cap sync ios

# Abrir Xcode
npx cap open ios

# Sincronizar tudo (Android + iOS)
npx cap sync
```

---

## 📱 Gerar Build iOS

### No Xcode:

1. Selecione **Any iOS Device** como destino
2. **Product** → **Archive**
3. Aguarde build completar
4. **Distribute App** → **App Store Connect**
5. Siga o assistente

---

## 🎯 Checklist

- [ ] Mac disponível
- [ ] Xcode instalado
- [ ] CocoaPods instalado
- [ ] Projeto copiado para Mac
- [ ] `npm install` executado
- [ ] `npx cap add ios` executado
- [ ] `npx cap sync ios` executado
- [ ] Projeto aberto no Xcode
- [ ] Testado no simulador
- [ ] Build de produção gerado
- [ ] Publicado na App Store

---

**⏱️ Tempo total: ~30 minutos (incluindo instalação do Xcode)**

