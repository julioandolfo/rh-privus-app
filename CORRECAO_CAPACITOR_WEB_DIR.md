# 🔧 Correção: Erro "Cannot copy to subdirectory of itself"

## ✅ Problema Resolvido!

O erro acontecia porque o Capacitor tentava copiar o projeto inteiro para dentro de si mesmo.

**Solução aplicada:**
1. ✅ Criado diretório `public/` com arquivo HTML mínimo
2. ✅ Configurado `webDir` para `public` no `capacitor.config.json`
3. ✅ Removida pasta `android` criada incorretamente
4. ✅ Corrigido `package.json` com todas as dependências

---

## 📋 Próximos Passos

### 1. Instalar Dependências (se ainda não fez)

```bash
npm install
```

### 2. Inicializar Capacitor

```bash
npx cap init
```

**Responda:**
- App name: `RH Privus`
- App ID: `br.com.privus.rh`
- Web dir: `public` (já está configurado no capacitor.config.json)

### 3. Adicionar Plataforma Android

```bash
npx cap add android
```

Agora deve funcionar sem erros! ✅

### 4. Sincronizar Arquivos

```bash
npx cap sync
```

### 5. Abrir no Android Studio

```bash
npx cap open android
```

---

## 📁 O Que Foi Criado

### `public/index.html`
Arquivo HTML simples que:
- Mostra uma tela de carregamento
- Redireciona automaticamente para `https://privus.com.br/rh/`
- Serve como fallback caso o app não consiga carregar do servidor

### `capacitor.config.json` (atualizado)
- `webDir` alterado de `.` para `public`
- Configuração de servidor remoto mantida

---

## 🎯 Como Funciona

O app Capacitor vai:
1. Usar o arquivo `public/index.html` como base
2. Carregar o conteúdo real de `https://privus.com.br/rh/`
3. Funcionar como um "wrapper" nativo do seu site

---

## ⚠️ Importante

O diretório `public/` contém apenas arquivos estáticos mínimos. Todo o conteúdo PHP continua funcionando normalmente no servidor.

---

## 🐛 Se Ainda Der Erro

### Erro: "package.json não encontrado"
```bash
npm install
```

### Erro: "Cannot copy to subdirectory"
- Verifique se o `webDir` no `capacitor.config.json` está como `public`
- Certifique-se de que a pasta `public` existe

### Erro: "ENOENT: no such file"
- Execute `npx cap sync` novamente
- Certifique-se de que todas as dependências estão instaladas

---

## ✅ Verificação

Após executar `npx cap add android`, você deve ver:
- ✅ Pasta `android/` criada
- ✅ Arquivos copiados para `android/app/src/main/assets/public/`
- ✅ Sem erros de "subdirectory"

---

**Agora pode executar os comandos novamente!** 🚀

