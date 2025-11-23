# 🔧 Correção: Remover Popup Duplo de Permissão do OneSignal

## ❌ Problema

Ao solicitar permissão para notificações, o usuário via **DUAS solicitações**:

1. **Popup do OneSignal** (slidedown próprio do OneSignal)
2. **Permissão nativa** do navegador/celular

Isso confundia o usuário e causava má experiência (UX ruim).

## 🔍 Causa

O OneSignal, por padrão, mostra um **popup próprio** (slidedown) antes de pedir a permissão nativa do navegador. Isso serve para:
- Explicar o que são as notificações
- Dar contexto ao usuário
- Evitar que ele negue a permissão nativa (que não pode ser revertida facilmente)

Porém, no seu caso, isso fica redundante e chato para o usuário.

## ✅ Solução Implementada

Desabilitei o **popup próprio do OneSignal** e configurei para usar **APENAS a permissão nativa** do navegador.

### Configuração Adicionada no `onesignal-init.js`:

```javascript
promptOptions: {
    autoPrompt: false,      // NÃO mostra popup automático do OneSignal
    slidedown: {
        enabled: false,     // Desabilita slidedown do OneSignal
    },
}
```

### Como Funciona Agora:

**ANTES:**
```
1. Usuário faz login
2. ❌ Popup do OneSignal aparece ("Permitir notificações?")
3. Usuário clica "Permitir"
4. ❌ Permissão nativa do navegador aparece
5. Usuário clica "Permitir" de novo
```

**DEPOIS:**
```
1. Usuário faz login
2. Usuário clica em "Ativar Notificações" (seu botão)
3. ✅ APENAS permissão nativa aparece
4. Usuário clica "Permitir" UMA VEZ
```

## 📋 Arquivo Modificado

- ✅ `assets/js/onesignal-init.js` - Adicionado `promptOptions` com `autoPrompt: false`

## 🧪 Como Testar

### Teste 1: Limpar Permissões Antigas

**Chrome/Edge:**
1. Abra DevTools (F12)
2. Vá em **Application** → **Storage** → **Clear site data**
3. Marque tudo e clique em **Clear site data**
4. Recarregue a página

**Ou via Console:**
```javascript
// Execute no console
navigator.serviceWorker.getRegistrations().then(registrations => {
    registrations.forEach(reg => reg.unregister());
});
location.reload();
```

### Teste 2: Solicitar Permissão

1. Faça login no sistema
2. Procure o botão de **"Ativar Notificações"** ou **"Permitir Notificações"**
3. Clique no botão
4. **Deve aparecer APENAS a permissão nativa do navegador/celular**
5. **NÃO deve aparecer popup do OneSignal antes**

### Teste 3: Verificar Console

1. Abra o console (F12)
2. Após fazer login, verifique as mensagens:
   - ✅ Deve aparecer: `"Popup do OneSignal DESABILITADO - usando apenas permissão nativa"`

## 📱 Comportamento em Diferentes Dispositivos

### Desktop (Chrome/Edge/Firefox)
- ✅ Permissão nativa aparece no topo do navegador
- ✅ Sem popup do OneSignal

### Mobile (Android)
- ✅ Permissão nativa do Android aparece
- ✅ Sem popup do OneSignal

### Mobile (iOS/Safari)
- ✅ Permissão nativa do iOS aparece
- ✅ Sem popup do OneSignal

## 💡 Vantagens da Mudança

### ✅ Melhor Experiência (UX)
- Usuário clica **1 vez** ao invés de 2
- Menos confusão
- Mais direto ao ponto

### ✅ Mais Rápido
- Não precisa esperar popup do OneSignal
- Vai direto para permissão nativa

### ✅ Mais Profissional
- Comportamento igual a apps nativos
- Sem popups redundantes

## ⚠️ Observações Importantes

### Quando o Usuário Nega a Permissão

Se o usuário **negar** a permissão nativa do navegador:
- ⚠️ Não pode solicitar novamente automaticamente
- ⚠️ Usuário precisa ir nas **configurações do navegador** para permitir manualmente

**Como reverter permissão negada:**

**Chrome/Edge:**
1. Clique no **cadeado** na barra de endereço
2. Vá em **Configurações do site**
3. Mude **Notificações** de "Bloquear" para "Permitir"
4. Recarregue a página

**Firefox:**
1. Clique no **ícone de permissões** na barra de endereço
2. Clique no **X** ao lado de "Notificações bloqueadas"
3. Recarregue a página e solicite novamente

### Melhores Práticas

Para evitar que o usuário negue:

1. ✅ **Explique ANTES** de solicitar
   - Mostre um texto explicando os benefícios das notificações
   - Ex: "Receba avisos de novas ocorrências, feedbacks e mensagens"

2. ✅ **Não solicite logo no login**
   - Espere o usuário usar o sistema primeiro
   - Solicite em momento relevante (ex: ao criar primeira ocorrência)

3. ✅ **Use um botão claro**
   - "Ativar Notificações" é melhor que solicitar automaticamente
   - Dá controle ao usuário

## 🎯 Exemplo de Implementação Ideal

### Opção 1: Banner Sutil no Topo (Recomendado)

Adicione este código no `dashboard.php` ou `includes/header.php`:

```html
<!-- Banner de notificações - aparece apenas se permissão não foi concedida -->
<div id="banner_notificacoes" class="alert alert-dismissible bg-primary d-flex flex-column flex-sm-row p-5 mb-5" style="display: none;">
    <div class="d-flex flex-column pe-0 pe-sm-10">
        <h4 class="mb-2 text-white">🔔 Ativar Notificações Push</h4>
        <span class="text-white opacity-75">Receba avisos em tempo real sobre ocorrências, feedbacks e mensagens importantes.</span>
    </div>
    <button type="button" class="btn btn-light btn-active-light-primary" onclick="ativarNotificacoesPush()">
        Ativar Agora
    </button>
    <button type="button" class="btn btn-icon btn-light ms-2" data-bs-dismiss="alert">
        <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
    </button>
</div>

<script>
// Verifica se deve mostrar o banner
async function verificarMostrarBanner() {
    if (!('Notification' in window)) {
        return; // Navegador não suporta
    }
    
    const permission = Notification.permission;
    
    // Verifica se já dispensou o banner antes
    const bannerdispensado = localStorage.getItem('banner_notif_dispensado');
    
    if (permission === 'default' && !bannerdispensado) {
        // Espera 3 segundos após carregar para não ser intrusivo
        setTimeout(() => {
            document.getElementById('banner_notificacoes').style.display = 'flex';
        }, 3000);
    }
}

// Função para ativar notificações
async function ativarNotificacoesPush() {
    try {
        const result = await OneSignalInit.subscribe();
        
        if (result) {
            // Sucesso - oculta o banner
            document.getElementById('banner_notificacoes').style.display = 'none';
            
            Swal.fire({
                text: 'Notificações ativadas com sucesso! 🎉',
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        } else {
            Swal.fire({
                text: 'Você negou as notificações. Para ativar, vá nas configurações do navegador.',
                icon: 'warning',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        }
    } catch (error) {
        console.error('Erro ao ativar notificações:', error);
    }
}

// Salva que o usuário dispensou o banner
document.getElementById('banner_notificacoes')?.addEventListener('closed.bs.alert', function() {
    localStorage.setItem('banner_notif_dispensado', 'true');
});

// Verifica ao carregar a página
verificarMostrarBanner();
</script>
```

### Opção 2: Card no Dashboard

```html
<!-- Card de notificações - mostrar no dashboard -->
<div class="card mb-5" id="card_ativar_notificacoes" style="display: none;">
    <div class="card-body p-6">
        <div class="d-flex align-items-center">
            <div class="symbol symbol-50px me-5">
                <span class="symbol-label bg-light-primary">
                    <i class="ki-duotone ki-notification-on fs-2x text-primary">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                </span>
            </div>
            <div class="flex-grow-1">
                <h3 class="fw-bold mb-1">Ativar Notificações Push</h3>
                <p class="text-muted mb-0">Receba avisos instantâneos sobre ocorrências, feedbacks e mensagens</p>
            </div>
            <button onclick="ativarNotificacoesPush()" class="btn btn-primary">
                <i class="ki-duotone ki-check fs-2"><span class="path1"></span><span class="path2"></span></i>
                Ativar
            </button>
        </div>
    </div>
</div>

<script>
// Verifica se deve mostrar o card
if ('Notification' in window && Notification.permission === 'default') {
    const dispensado = localStorage.getItem('card_notif_dispensado');
    if (!dispensado) {
        document.getElementById('card_ativar_notificacoes').style.display = 'block';
    }
}
</script>
```

### Opção 3: Modal na Primeira Visita

```html
<!-- Modal de boas-vindas -->
<div class="modal fade" id="modal_boas_vindas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Bem-vindo ao RH Privus! 👋</h2>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-center mb-5">
                    <i class="ki-duotone ki-notification-on fs-5x text-primary mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                </div>
                <h4 class="mb-4">Ativar Notificações?</h4>
                <p class="mb-4">Mantenha-se atualizado com notificações em tempo real sobre:</p>
                <ul class="mb-5">
                    <li class="mb-2">📋 Novas ocorrências atribuídas a você</li>
                    <li class="mb-2">💬 Feedbacks de desempenho</li>
                    <li class="mb-2">📢 Mensagens importantes da empresa</li>
                    <li class="mb-2">✅ Atualizações de tarefas</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Agora Não</button>
                <button type="button" class="btn btn-primary" onclick="ativarNotificacoesPushModal()">
                    <i class="ki-duotone ki-check fs-2"><span class="path1"></span><span class="path2"></span></i>
                    Ativar Notificações
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Mostra modal apenas na primeira visita
function verificarPrimeiraVisita() {
    const jaVisitou = localStorage.getItem('ja_visitou');
    const permission = Notification.permission;
    
    if (!jaVisitou && permission === 'default') {
        // Mostra modal após 2 segundos
        setTimeout(() => {
            const modal = new bootstrap.Modal(document.getElementById('modal_boas_vindas'));
            modal.show();
            localStorage.setItem('ja_visitou', 'true');
        }, 2000);
    } else if (!jaVisitou) {
        localStorage.setItem('ja_visitou', 'true');
    }
}

async function ativarNotificacoesPushModal() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('modal_boas_vindas'));
    modal.hide();
    
    setTimeout(async () => {
        const result = await OneSignalInit.subscribe();
        
        if (result) {
            Swal.fire({
                text: 'Notificações ativadas com sucesso! 🎉',
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        }
    }, 500);
}

// Verifica ao carregar
verificarPrimeiraVisita();
</script>
```

## 💡 Qual Opção Escolher?

### Banner Sutil (Opção 1) - ⭐ RECOMENDADO
- ✅ Não intrusivo
- ✅ Pode ser facilmente dispensado
- ✅ Aparece discretamente no topo

### Card no Dashboard (Opção 2)
- ✅ Integrado ao layout
- ✅ Permanece visível até ser ativado
- ✅ Mais discreto que modal

### Modal de Boas-Vindas (Opção 3)
- ✅ Melhor taxa de aceitação
- ✅ Explica os benefícios claramente
- ⚠️ Mais intrusivo (alguns usuários não gostam)

---

## ⚙️ Configuração Importante

Após escolher uma das opções acima, certifique-se de:

1. **Chamar `OneSignalInit.subscribe()`** quando o usuário clicar no botão
2. **Verificar permissão** antes de mostrar o banner/card/modal
3. **Salvar preferência** quando usuário dispensar

Todas as funções chamam `OneSignalInit.subscribe()` que:
1. Mostra **APENAS** a permissão nativa (sem popup do OneSignal)
2. Registra o player_id automaticamente
3. Retorna `true` se permitiu, `false` se negou

## 🚨 Se Ainda Aparecer Popup Duplo

1. **Limpe completamente o cache:**
   ```javascript
   // Console do navegador
   navigator.serviceWorker.getRegistrations().then(registrations => {
       registrations.forEach(reg => reg.unregister());
   });
   caches.keys().then(keys => {
       keys.forEach(key => caches.delete(key));
   });
   location.reload();
   ```

2. **Verifique se o arquivo foi atualizado no servidor**
   - `assets/js/onesignal-init.js` deve ter `promptOptions` configurado

3. **Teste em modo anônimo/privado**
   - Para descartar cache do navegador

4. **Verifique o console**
   - Deve aparecer: `"Popup do OneSignal DESABILITADO - usando apenas permissão nativa"`

---

**A correção foi aplicada. Agora apenas 1 solicitação de permissão aparece! 🎉**

