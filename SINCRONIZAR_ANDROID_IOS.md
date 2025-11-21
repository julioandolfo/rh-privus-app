# 🔄 Guia: Sincronizar Android e iOS

## 🎯 Objetivo

Preparar o projeto para sincronizar arquivos web com **ambas as plataformas** (Android e iOS).

---

## 📋 Passo a Passo

### 1. Instalar Dependência iOS

```bash
npm install @capacitor/ios --save-dev
```

### 2. Adicionar Plataforma iOS

```bash
npx cap add ios
```

**⚠️ Importante:** Este comando só funciona no **Mac** com Xcode instalado.

Se você estiver no Windows, pode:
- Pular este passo por enquanto
- Ou executar depois quando tiver acesso a um Mac

### 3. Sincronizar Ambas as Plataformas

#### Sincronizar Tudo (Android + iOS):

```bash
npx cap sync
```

Isso sincroniza para **ambas** as plataformas automaticamente.

#### Sincronizar Apenas Android:

```bash
npx cap sync android
```

#### Sincronizar Apenas iOS:

```bash
npx cap sync ios
```

---

## 📂 Estrutura Após Sincronização

```
rh-privus/
├── public/                          ← ORIGEM
│   └── index.html
│
├── android/                         ← DESTINO Android
│   └── app/src/main/assets/public/
│       └── index.html               ← Copiado pelo sync
│
├── ios/                             ← DESTINO iOS
│   └── App/public/
│       └── index.html               ← Copiado pelo sync
│
└── capacitor.config.json
```

---

## 🔄 Comandos Úteis

### Sincronizar Tudo:

```bash
npx cap sync
```

### Abrir Projetos:

```bash
# Abrir Android Studio
npx cap open android

# Abrir Xcode (apenas Mac)
npx cap open ios
```

### Scripts no package.json:

```json
{
  "scripts": {
    "cap:sync": "npx cap sync",
    "cap:sync:android": "npx cap sync android",
    "cap:sync:ios": "npx cap sync ios",
    "cap:open:android": "npx cap open android",
    "cap:open:ios": "npx cap open ios"
  }
}
```

**Uso:**
```bash
npm run cap:sync          # Sincroniza tudo
npm run cap:sync:android # Sincroniza apenas Android
npm run cap:sync:ios     # Sincroniza apenas iOS
npm run cap:open:android # Abre Android Studio
npm run cap:open:ios     # Abre Xcode
```

---

## ⚠️ Requisitos por Plataforma

### Android:
- ✅ Funciona no Windows/Mac/Linux
- ✅ Precisa Android Studio instalado
- ✅ Pode testar em dispositivo físico ou emulador

### iOS:
- ❌ **Só funciona no Mac**
- ❌ Precisa Xcode instalado
- ❌ Precisa conta Apple Developer ($99/ano)
- ✅ Pode testar em dispositivo físico ou simulador

---

## 🎯 Cenários de Uso

### Cenário 1: Desenvolvimento no Windows

```bash
# 1. Trabalhar normalmente
# 2. Sincronizar apenas Android
npx cap sync android

# 3. Testar no Android Studio
npx cap open android

# 4. Para iOS, precisa de Mac (ou usar serviço de build na nuvem)
```

### Cenário 2: Desenvolvimento no Mac

```bash
# 1. Trabalhar normalmente
# 2. Sincronizar ambas plataformas
npx cap sync

# 3. Testar Android
npx cap open android

# 4. Testar iOS
npx cap open ios
```

### Cenário 3: Equipe com Windows + Mac

```bash
# No Windows:
npx cap sync android
npx cap open android

# No Mac:
npx cap sync ios
npx cap open ios

# Ou sincronizar tudo no Mac:
npx cap sync  # Sincroniza Android + iOS
```

---

## 📱 O Que É Sincronizado?

### Arquivos Copiados:

- ✅ `public/index.html` → `android/.../public/index.html`
- ✅ `public/index.html` → `ios/App/public/index.html`
- ✅ `capacitor.config.json` → Ambos os projetos
- ✅ Plugins instalados → Ambos os projetos

### Configurações Atualizadas:

- ✅ `capacitor.plugins.json` gerado automaticamente
- ✅ Dependências nativas atualizadas
- ✅ Configurações de build sincronizadas

---

## 🔧 Configurações Específicas por Plataforma

### Android (`capacitor.config.json`):

```json
{
  "android": {
    "allowMixedContent": false,
    "captureInput": true,
    "webContentsDebuggingEnabled": false
  }
}
```

### iOS (`capacitor.config.json`):

```json
{
  "ios": {
    "contentInset": "automatic",
    "scrollEnabled": true,
    "allowsLinkPreview": false
  }
}
```

---

## ✅ Checklist

### Setup Inicial:

- [x] `package.json` atualizado com `@capacitor/ios`
- [x] `capacitor.config.json` configurado para ambas plataformas
- [ ] Plataforma Android adicionada (`npx cap add android`)
- [ ] Plataforma iOS adicionada (`npx cap add ios`) - **apenas Mac**
- [ ] Primeira sincronização executada (`npx cap sync`)

### Desenvolvimento:

- [ ] Fazer alterações em `public/`
- [ ] Executar `npx cap sync` após mudanças
- [ ] Testar no Android Studio
- [ ] Testar no Xcode (se tiver Mac)

---

## 🚀 Fluxo de Trabalho Recomendado

```
1. Desenvolver código web em public/
   ↓
2. Testar no navegador (https://privus.com.br/rh/)
   ↓
3. Executar: npx cap sync
   ↓
4. Testar no Android: npx cap open android
   ↓
5. Testar no iOS: npx cap open ios (apenas Mac)
   ↓
6. Gerar builds de produção
   ↓
7. Publicar nas lojas
```

---

## 💡 Dicas

### 1. Sincronizar Sempre que Mudar Arquivos Web

```bash
# Após qualquer mudança em public/
npx cap sync
```

### 2. Verificar Diferenças Antes de Sincronizar

```bash
# Ver o que será sincronizado
npx cap sync --dry-run
```

### 3. Limpar e Sincronizar Novamente

```bash
# Se houver problemas, limpar e sincronizar
npx cap sync --clean
```

### 4. Trabalhar com Git

```bash
# Adicionar ao .gitignore:
android/
ios/
node_modules/
```

**Não commitar:** Pastas `android/` e `ios/` (geradas automaticamente)

---

## 🐛 Problemas Comuns

### Erro: "iOS platform not found"

**Solução:** Adicione a plataforma iOS:
```bash
npx cap add ios
```

### Erro: "Cannot add iOS on Windows"

**Solução:** iOS só funciona no Mac. Você pode:
- Trabalhar apenas com Android no Windows
- Usar Mac para iOS depois
- Usar serviço de build na nuvem para iOS

### Erro: "Sync failed"

**Solução:**
```bash
# Limpar e sincronizar novamente
npx cap sync --clean
```

---

## 📚 Próximos Passos

Após configurar ambas as plataformas:

1. ✅ Desenvolver e testar no Android
2. ✅ Desenvolver e testar no iOS (se tiver Mac)
3. ✅ Gerar builds de produção
4. ✅ Publicar na Play Store (Android)
5. ✅ Publicar na App Store (iOS)

---

**🎯 Agora você pode sincronizar para Android e iOS com um único comando: `npx cap sync`!**

