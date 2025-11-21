# 🔄 Como Funciona: `npx cap sync`

## 🎯 O Que Faz?

O comando `npx cap sync` **sincroniza** seus arquivos web com os projetos nativos (Android/iOS).

---

## 📂 De Onde Copia? (ORIGEM)

### Configuração no `capacitor.config.json`:

```json
{
  "webDir": "public"
}
```

**Origem:** Pasta `public/` na raiz do projeto

```
rh-privus/
├── public/              ← AQUI (origem)
│   └── index.html
├── android/
├── ios/
└── capacitor.config.json
```

---

## 📱 Para Onde Copia? (DESTINO)

### Android:

```
android/
└── app/
    └── src/
        └── main/
            └── assets/
                ├── public/              ← AQUI (destino Android)
                │   └── index.html       ← Copiado da origem
                ├── capacitor.config.json
                └── capacitor.plugins.json
```

### iOS:

```
ios/
└── App/
    └── public/          ← AQUI (destino iOS)
        └── index.html   ← Copiado da origem
```

**⚠️ Nota:** iOS só funciona no Mac. Se você estiver no Windows, pode sincronizar apenas Android.

---

## 🔄 Processo Completo

Quando você executa `npx cap sync`:

### 1. **Copia Arquivos Web**
   - Copia tudo de `public/` → `android/app/src/main/assets/public/`
   - Inclui: HTML, CSS, JS, imagens, etc.

### 2. **Copia Configuração**
   - Copia `capacitor.config.json` → `android/app/src/main/assets/`
   - Atualiza configurações do app

### 3. **Atualiza Plugins**
   - Gera `capacitor.plugins.json` com plugins instalados
   - Atualiza dependências nativas

### 4. **Atualiza Dependências Nativas**
   - Sincroniza plugins do `package.json`
   - Atualiza código nativo se necessário

---

## 📋 Exemplo Prático

### Antes do Sync:

```
public/
└── index.html          ← Arquivo original
```

### Depois do Sync:

```
public/
└── index.html          ← Arquivo original (não muda)

android/app/src/main/assets/public/
└── index.html          ← CÓPIA sincronizada
```

**Importante:** O arquivo em `public/` é o **original**. O arquivo em `android/` é uma **cópia**.

---

## ⚠️ Importante: Modo Servidor Remoto

No seu caso, o `capacitor.config.json` tem:

```json
{
  "server": {
    "url": "https://privus.com.br/rh"
  }
}
```

**Isso significa:**
- ✅ O app vai carregar conteúdo de `https://privus.com.br/rh/` (servidor remoto)
- ✅ Os arquivos em `public/` são apenas **fallback** (se o servidor não estiver acessível)
- ✅ O app funciona principalmente como um "navegador" que carrega seu site

---

## 🔄 Quando Executar `npx cap sync`?

### Execute quando:

1. ✅ **Adicionar novos arquivos** em `public/`
2. ✅ **Modificar arquivos** em `public/`
3. ✅ **Instalar novos plugins** do Capacitor
4. ✅ **Alterar configurações** no `capacitor.config.json`
5. ✅ **Antes de gerar build** do app

### Sincronizar Plataformas Específicas:

```bash
# Sincronizar tudo (Android + iOS)
npx cap sync

# Sincronizar apenas Android
npx cap sync android

# Sincronizar apenas iOS
npx cap sync ios
```

### NÃO precisa executar quando:

- ❌ Fazer mudanças apenas no código PHP (servidor)
- ❌ Alterar arquivos fora de `public/`
- ❌ Trabalhar apenas no backend

---

## 📊 Fluxo Completo

```
1. Você edita arquivos em public/
   ↓
2. Executa: npx cap sync
   ↓
3. Capacitor copia para:
   - android/app/src/main/assets/public/  (Android)
   - ios/App/public/                        (iOS - se adicionado)
   ↓
4. Abre projetos:
   - npx cap open android  (Android Studio)
   - npx cap open ios      (Xcode - apenas Mac)
   ↓
5. Build do app usa os arquivos copiados
   ↓
6. App carrega de https://privus.com.br/rh/ (servidor remoto)
   ↓
7. Se servidor offline, usa arquivos em public/ (fallback)
```

---

## 🎯 No Seu Caso Específico

### Estrutura Atual:

```
rh-privus/
├── public/
│   └── index.html          ← Fallback (redireciona para servidor)
├── android/
│   └── app/src/main/assets/public/
│       └── index.html      ← Copiado pelo sync
├── [todo resto do projeto PHP]
└── capacitor.config.json   ← Configurado para servidor remoto
```

### Como Funciona:

1. **App instalado** → Abre `index.html` de `public/`
2. **index.html** → Redireciona para `https://privus.com.br/rh/`
3. **Servidor remoto** → Carrega todo o conteúdo PHP normalmente
4. **Se servidor offline** → Mostra tela de carregamento do `index.html`

---

## 💡 Resumo Visual

```
┌─────────────────────────────────────┐
│  npx cap sync                        │
│                                      │
│  ORIGEM:                            │
│  public/                             │
│  ├── index.html                     │
│  └── [outros arquivos]              │
│         ↓                            │
│         │ COPIA                      │
│         ↓                            │
│  DESTINO:                            │
│  android/app/src/main/assets/public/│
│  ├── index.html                     │
│  └── [outros arquivos]              │
└─────────────────────────────────────┘
```

---

## ✅ Checklist

- [x] `webDir` configurado como `public` no `capacitor.config.json`
- [x] Pasta `public/` existe com `index.html`
- [x] `npx cap sync` copia de `public/` → `android/app/src/main/assets/public/`
- [x] App carrega de servidor remoto (`https://privus.com.br/rh/`)
- [x] Arquivos em `public/` servem como fallback

---

**🎯 Resumo:** `npx cap sync` copia arquivos de `public/` para dentro do projeto Android/iOS, preparando-os para o build do app nativo.

