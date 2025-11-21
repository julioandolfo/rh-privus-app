# 📱 Guia: Instalar e Configurar Android Studio

## 🎯 Situação Atual

O Android Studio não está instalado ou não está no PATH do sistema.

---

## 📥 Opção 1: Instalar Android Studio (Recomendado)

### Passo 1: Baixar Android Studio

1. Acesse: https://developer.android.com/studio
2. Clique em **"Download Android Studio"**
3. Baixe o instalador para Windows

### Passo 2: Instalar

1. Execute o instalador baixado
2. Siga o assistente de instalação
3. **Importante:** Marque a opção "Add to PATH" durante a instalação
4. Aguarde a instalação completar (pode demorar 10-20 minutos)

### Passo 3: Configurar Android Studio

1. Abra o Android Studio
2. Na primeira vez, ele vai baixar componentes adicionais:
   - Android SDK
   - Android SDK Platform
   - Android Virtual Device (AVD)
3. Aguarde o download completar

### Passo 4: Verificar Instalação

Abra um novo terminal e execute:

```bash
where android-studio
```

Ou tente:

```bash
npx cap open android
```

---

## ⚙️ Opção 2: Configurar Caminho Manualmente

Se o Android Studio já está instalado mas não está no PATH:

### Windows:

1. Encontre o caminho do Android Studio (geralmente):
   ```
   C:\Program Files\Android\Android Studio\bin\studio64.exe
   ```
   ou
   ```
   C:\Users\SeuUsuario\AppData\Local\Android\Android Studio\bin\studio64.exe
   ```

2. Configure a variável de ambiente:

**Via PowerShell (como Administrador):**
```powershell
[System.Environment]::SetEnvironmentVariable('CAPACITOR_ANDROID_STUDIO_PATH', 'C:\Program Files\Android\Android Studio\bin\studio64.exe', 'User')
```

**Ou manualmente:**
1. Pressione `Win + R`
2. Digite `sysdm.cpl` e pressione Enter
3. Vá em **Avançado** → **Variáveis de Ambiente**
4. Clique em **Novo** em "Variáveis do usuário"
5. Nome: `CAPACITOR_ANDROID_STUDIO_PATH`
6. Valor: Caminho completo para `studio64.exe`
7. Clique em **OK**

3. Feche e reabra o terminal

4. Teste:
```bash
npx cap open android
```

---

## 🔧 Opção 3: Trabalhar Sem Android Studio (Temporário)

Você pode trabalhar com o projeto Android sem abrir o Android Studio:

### Ver estrutura do projeto:

```bash
# Listar arquivos do projeto Android
dir android
```

### Sincronizar mudanças:

```bash
npx cap sync
```

### Gerar APK via linha de comando (avançado):

```bash
cd android
.\gradlew assembleDebug
```

O APK será gerado em: `android/app/build/outputs/apk/debug/app-debug.apk`

---

## 📋 Requisitos do Android Studio

### Mínimos:
- **RAM:** 8 GB (recomendado 16 GB)
- **Espaço em disco:** 4 GB (mais 2 GB para SDK)
- **Sistema:** Windows 10/11 (64-bit)

### Recomendados:
- **RAM:** 16 GB ou mais
- **Espaço em disco:** 10 GB livres
- **Processador:** Multi-core

---

## 🚀 Alternativa: Usar Android Studio via Docker (Avançado)

Se não quiser instalar localmente, pode usar containers Docker, mas é mais complexo.

---

## ✅ Checklist de Instalação

Após instalar o Android Studio:

- [ ] Android Studio instalado
- [ ] SDK Android baixado
- [ ] Variável de ambiente configurada (se necessário)
- [ ] `npx cap open android` funciona
- [ ] Projeto abre no Android Studio

---

## 🐛 Problemas Comuns

### "Java not found"
**Solução:** O Android Studio inclui o JDK. Se der erro, instale o JDK 17:
- Baixe em: https://adoptium.net/

### "SDK not found"
**Solução:** 
1. Abra Android Studio
2. Vá em **File** → **Settings** → **Appearance & Behavior** → **System Settings** → **Android SDK**
3. Instale o SDK necessário

### "Gradle sync failed"
**Solução:**
1. No Android Studio, vá em **File** → **Invalidate Caches / Restart**
2. Aguarde o Gradle sincronizar novamente

---

## 📚 Próximos Passos

Após instalar o Android Studio:

1. ✅ Abrir projeto: `npx cap open android`
2. ✅ Conectar dispositivo Android ou iniciar emulador
3. ✅ Clicar em **Run** (▶️) para testar
4. ✅ Gerar APK de produção
5. ✅ Publicar na Play Store

---

## 💡 Dica

Se você só quer testar rapidamente sem instalar o Android Studio completo, pode:
1. Usar um dispositivo Android físico
2. Habilitar **Modo Desenvolvedor** no celular
3. Habilitar **Depuração USB**
4. Conectar via USB
5. Usar `adb` (Android Debug Bridge) para instalar o APK diretamente

Mas para desenvolvimento completo, o Android Studio é essencial.

---

**🎯 Recomendação: Instale o Android Studio seguindo a Opção 1 para ter a melhor experiência de desenvolvimento!**

