# 🔧 Correção: Banner de Instalação PWA não Armazena Preferência

## ❌ Problema

O botão "📱 Instalar RH Privus" estava aparecendo toda vez que a página carregava, mesmo depois que o usuário já havia fechado/recusado. Não estava respeitando o período de 30 dias.

## 🔍 Causa Raiz

1. **Quando o usuário recusava no prompt nativo**, não estava salvando no `localStorage`
2. **O evento `beforeinstallprompt`** podia ser disparado múltiplas vezes
3. **Não verificava se já havia um banner** na tela antes de criar outro

## ✅ Correções Implementadas

### 1. Verificação Antes de Mostrar Banner

**Adicionado:**
```javascript
// Verifica se já existe um banner na tela
if (document.getElementById('pwa-install-banner')) {
    console.log('Banner já está sendo exibido');
    return;
}
```

### 2. Verificação no Init()

**Antes:** Verificava apenas dentro de `showInstallBanner()`

**Depois:** Verifica no `init()` antes de escutar o evento `beforeinstallprompt`:
```javascript
// Verifica se foi dispensado há menos de 30 dias
const dismissed = localStorage.getItem('pwa-install-dismissed');
if (dismissed) {
    const dismissedDate = parseInt(dismissed);
    const thirtyDays = 30 * 24 * 60 * 60 * 1000;
    const daysSinceDismissed = Date.now() - dismissedDate;
    
    if (daysSinceDismissed < thirtyDays) {
        // Não mostra o banner
        return;
    }
}
```

### 3. Salvar Quando Usuário Recusa Instalação

**Adicionado na função `install()`:**
```javascript
if (outcome === 'accepted') {
    // Remove o registro já que foi instalado
    localStorage.removeItem('pwa-install-dismissed');
} else {
    // Salva preferência para não mostrar novamente por 30 dias
    localStorage.setItem('pwa-install-dismissed', Date.now());
    document.getElementById('pwa-install-banner')?.remove();
}
```

### 4. Limpar Registro Antigo Automaticamente

**Adicionado:**
```javascript
if (daysSinceDismissed >= thirtyDays) {
    // Limpa o registro antigo e mostra novamente
    localStorage.removeItem('pwa-install-dismissed');
    this.showInstallBanner();
}
```

## 📋 Arquivo Modificado

- ✅ `assets/js/pwa-install-prompt.js` - Lógica de armazenamento melhorada

## 🧪 Como Testar

### Teste 1: Fechar Banner

1. Acesse o sistema no navegador
2. Quando aparecer o banner "📱 Instalar RH Privus"
3. Clique em **"Agora não"**
4. Recarregue a página (F5)
5. **O banner NÃO deve aparecer** novamente

### Teste 2: Recusar Instalação no Prompt

1. Acesse o sistema no navegador
2. Quando aparecer o banner, clique em **"Instalar"**
3. No prompt nativo, clique em **"Cancelar"** ou **"Não"**
4. Recarregue a página (F5)
5. **O banner NÃO deve aparecer** novamente

### Teste 3: Verificar localStorage

1. Abra o DevTools (F12)
2. Vá em **Application** → **Local Storage**
3. Procure por `pwa-install-dismissed`
4. Deve ter um timestamp (número grande)
5. Este valor será usado para calcular os 30 dias

### Teste 4: Simular 30 Dias Passados

Execute no console do navegador:
```javascript
// Simula que foi dispensado há 31 dias
const thirtyOneDaysAgo = Date.now() - (31 * 24 * 60 * 60 * 1000);
localStorage.setItem('pwa-install-dismissed', thirtyOneDaysAgo);

// Recarrega a página
location.reload();
```

O banner deve aparecer novamente.

### Teste 5: Limpar e Testar Novamente

Execute no console:
```javascript
// Remove o registro
localStorage.removeItem('pwa-install-dismissed');

// Recarrega a página
location.reload();
```

O banner deve aparecer imediatamente.

## 💡 Como Funciona Agora

### Fluxo Normal:

1. **Usuário acessa o sistema**
   - Sistema verifica se foi dispensado há menos de 30 dias
   - Se sim, não mostra o banner
   - Se não, mostra o banner

2. **Usuário clica em "Agora não"**
   - Salva timestamp no `localStorage`
   - Remove o banner
   - Próxima vez que acessar, não mostra por 30 dias

3. **Usuário clica em "Instalar" e recusa**
   - Salva timestamp no `localStorage`
   - Remove o banner
   - Próxima vez que acessar, não mostra por 30 dias

4. **Usuário aceita instalação**
   - Remove o registro do `localStorage`
   - PWA é instalado
   - Banner não aparece mais (PWA já está instalado)

5. **Após 30 dias**
   - Sistema detecta que passou 30 dias
   - Limpa o registro antigo
   - Mostra o banner novamente

## 🔍 Debug

Para verificar o status atual, execute no console:

```javascript
const dismissed = localStorage.getItem('pwa-install-dismissed');
if (dismissed) {
    const dismissedDate = parseInt(dismissed);
    const thirtyDays = 30 * 24 * 60 * 60 * 1000;
    const daysSinceDismissed = Date.now() - dismissedDate;
    const daysRemaining = Math.ceil((thirtyDays - daysSinceDismissed) / (24 * 60 * 60 * 1000));
    
    console.log('Dispensado há:', Math.floor(daysSinceDismissed / (24 * 60 * 60 * 1000)), 'dias');
    console.log('Aparecerá novamente em:', daysRemaining, 'dias');
} else {
    console.log('Não foi dispensado - banner deve aparecer');
}
```

## 🚨 Se Ainda Não Funcionar

1. **Limpe o cache do navegador** completamente
2. **Verifique se o arquivo foi atualizado** no servidor
3. **Teste em modo anônimo/privado** para descartar cache
4. **Verifique o console** para erros JavaScript

---

**A correção foi aplicada. O banner agora respeita o período de 30 dias!**

