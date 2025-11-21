# 🚀 Setup Rápido: Capacitor para RH Privus

## 📋 Passo a Passo

### 1. Instalar Node.js (se não tiver)

Baixe em: https://nodejs.org/

### 2. Instalar Capacitor CLI

```bash
npm install -g @capacitor/cli
```

### 3. Instalar Dependências do Projeto

```bash
cd C:\laragon\www\rh-privus
npm install
```

Ou copie as dependências do `package.json.capacitor` para um novo `package.json`:

```bash
npm init -y
npm install @capacitor/core @capacitor/app @capacitor/haptics @capacitor/keyboard @capacitor/status-bar @capacitor/splash-screen
npm install --save-dev @capacitor/cli @capacitor/assets
```

### 4. Inicializar Capacitor

```bash
npx cap init
```

**Responda:**
- App name: `RH Privus`
- App ID: `br.com.privus.rh`
- Web dir: `.` (ponto, raiz do projeto)

### 5. Copiar Configuração

O arquivo `capacitor.config.json` já está criado com as configurações corretas.

### 6. Adicionar Plataforma Android

```bash
npx cap add android
```

### 7. Adicionar Plataforma iOS (apenas no Mac)

```bash
npx cap add ios
```

### 8. Sincronizar Arquivos

```bash
npx cap sync
```

Isso copia seus arquivos web para os projetos nativos.

### 9. Abrir no Android Studio

```bash
npx cap open android
```

### 10. Gerar Ícones e Splash Screen

```bash
npx @capacitor/assets generate --iconPath assets/media/logos/favicon.png --splashPath assets/media/logos/favicon.png
```

---

## 🎨 Personalizar Ícones

Para gerar ícones profissionais, você precisa de:

- **Ícone:** 1024x1024px (PNG, fundo transparente)
- **Splash:** 2732x2732px (PNG)

Coloque em `assets/media/logos/` e execute:

```bash
npx @capacitor/assets generate
```

---

## 📱 Testar Localmente

### Android:

1. Abra Android Studio
2. Conecte dispositivo Android ou inicie emulador
3. Clique em **Run** (▶️)

### iOS (apenas Mac):

1. Abra Xcode
2. Conecte iPhone ou inicie simulador
3. Clique em **Run** (▶️)

---

## 🔧 Comandos Úteis

```bash
# Sincronizar mudanças
npx cap sync

# Abrir Android Studio
npx cap open android

# Abrir Xcode (Mac)
npx cap open ios

# Ver versão do Capacitor
npx cap --version

# Listar plugins instalados
npx cap ls
```

---

## ⚠️ Importante

### Para Produção:

1. **Altere a URL no `capacitor.config.json`:**
   ```json
   "server": {
     "url": "https://privus.com.br/rh"
   }
   ```

2. **Execute sync novamente:**
   ```bash
   npx cap sync
   ```

3. **Gere builds de produção** nas IDEs (Android Studio/Xcode)

---

## 🐛 Problemas Comuns

### "Command not found: cap"
```bash
npm install -g @capacitor/cli
```

### "Cannot find module"
```bash
npm install
npx cap sync
```

### App não carrega conteúdo
- Verifique a URL no `capacitor.config.json`
- Confirme que o servidor está acessível
- Execute `npx cap sync` novamente

---

## 📚 Próximos Passos

Após configurar:
1. ✅ Testar app localmente
2. ✅ Configurar ícones e splash screen
3. ✅ Gerar build de produção
4. ✅ Publicar nas lojas (veja `GUIA_APP_STORE_PLAY_STORE.md`)

