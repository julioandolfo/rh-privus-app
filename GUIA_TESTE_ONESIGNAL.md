# 🧪 Guia de Teste - Verificar se OneSignal Está Funcionando

## ✅ Checklist de Verificação

Siga estes passos para verificar se o OneSignal está funcionando corretamente:

---

## 📋 Passo 1: Verificar Configuração

### 1.1 Verificar Credenciais no Banco

Execute no banco de dados:

```sql
SELECT * FROM onesignal_config;
```

**Deve retornar:**
- `app_id` preenchido
- `rest_api_key` preenchido

---

## 📋 Passo 2: Verificar no Browser

### 2.1 Console do Browser

1. Abra o sistema no browser
2. Pressione **F12** para abrir DevTools
3. Vá na aba **Console**
4. Faça login no sistema
5. Procure por mensagens:
   - ✅ `✅ Player registrado no servidor` - **SUCESSO!**
   - ⚠️ `OneSignal App ID não configurado` - Verifique configurações
   - ❌ Erros em vermelho - Verifique console

### 2.2 Service Worker

1. Em DevTools, vá em **Application** → **Service Workers**
2. Deve aparecer: `OneSignalSDKWorker.js` registrado e ativo
3. Status deve ser: **activated and is running**

### 2.3 Verificar Subscription no Banco

Execute no banco:

```sql
SELECT * FROM onesignal_subscriptions;
```

**Deve retornar pelo menos um registro** com:
- `player_id` preenchido (ex: `12345678-1234-1234-1234-123456789012`)
- `usuario_id` ou `colaborador_id` vinculado

---

## 📋 Passo 3: Testar Notificação

### 3.1 Via Código PHP

Crie arquivo `test_onesignal.php` na raiz:

```php
<?php
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/push_notifications.php';

// Simula usuário ADMIN logado
$_SESSION['usuario'] = [
    'id' => 1,
    'role' => 'ADMIN'
];

echo "<h2>Teste de Notificação OneSignal</h2>";

// Verifica se há subscriptions
$pdo = getDB();
$stmt = $pdo->query("SELECT COUNT(*) as total FROM onesignal_subscriptions");
$total = $stmt->fetch()['total'];

echo "<p>Total de subscriptions registradas: <strong>$total</strong></p>";

if ($total > 0) {
    // Busca primeiro colaborador com subscription
    $stmt = $pdo->query("
        SELECT colaborador_id 
        FROM onesignal_subscriptions 
        WHERE colaborador_id IS NOT NULL 
        LIMIT 1
    ");
    $sub = $stmt->fetch();
    
    if ($sub) {
        echo "<p>Enviando notificação de teste para colaborador ID: {$sub['colaborador_id']}</p>";
        
        $result = enviar_push_colaborador(
            $sub['colaborador_id'],
            'Teste OneSignal',
            'Esta é uma notificação de teste! Se você recebeu isso, o OneSignal está funcionando! 🎉',
            '/rh-privus/pages/dashboard.php'
        );
        
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
        if ($result['success']) {
            echo "<p style='color: green;'><strong>✅ Notificação enviada com sucesso!</strong></p>";
            echo "<p>Verifique seu dispositivo em alguns segundos.</p>";
        } else {
            echo "<p style='color: red;'><strong>❌ Erro ao enviar:</strong> {$result['message']}</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Nenhum colaborador com subscription encontrado.</p>";
        echo "<p>Faça login como colaborador e permita notificações primeiro.</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ Nenhuma subscription registrada ainda.</p>";
    echo "<p>Faça login no sistema e permita notificações primeiro.</p>";
}
```

Acesse: `http://localhost/rh-privus/test_onesignal.php`

---

## 📋 Passo 4: Verificar no Painel OneSignal

### 4.1 Acessar Dashboard

1. Acesse: https://onesignal.com
2. Faça login
3. Selecione seu app "RH Privus"

### 4.2 Verificar Subscribers

1. Vá em **Audience** → **All Users**
2. Deve aparecer pelo menos **1 subscriber**
3. Se aparecer, significa que está funcionando!

### 4.3 Enviar Notificação de Teste

1. Vá em **Messages** → **New Push**
2. Preencha:
   - **Title**: Teste
   - **Message**: Esta é uma notificação de teste
3. Clique em **Send to All Users**
4. Clique em **Send Message**

**Resultado esperado:** Notificação aparece no dispositivo!

---

## 📋 Passo 5: Verificar Logs

### 5.1 Verificar Erros PHP

Verifique se há erros no log do PHP ou no console do browser.

### 5.2 Verificar API OneSignal

Se houver erro ao enviar, verifique:
- REST API Key está correto?
- App ID está correto?
- Há subscriptions registradas?

---

## ✅ Checklist Completo

- [ ] Credenciais configuradas no sistema
- [ ] Console do browser mostra "Player registrado"
- [ ] Service Worker ativo
- [ ] Subscription no banco de dados
- [ ] Subscriber aparece no painel OneSignal
- [ ] Notificação de teste funciona

---

## 🐛 Problemas Comuns

### Problema: "OneSignal App ID não configurado"

**Solução:**
1. Acesse `pages/configuracoes_onesignal.php`
2. Verifique se App ID está preenchido
3. Salve novamente

### Problema: Nenhuma subscription no banco

**Solução:**
1. Faça login no sistema
2. Permita notificações quando solicitado
3. Verifique console do browser
4. Verifique banco novamente

### Problema: Notificação não aparece

**Solução:**
1. Verifique se permitiu notificações no browser
2. Verifique se player_id está no banco
3. Verifique REST API Key no OneSignal
4. Teste via painel do OneSignal primeiro

### Problema: Erro 401 ao enviar

**Solução:**
- REST API Key está incorreto
- Verifique no painel OneSignal → Settings → Keys & IDs

---

## 🎯 Teste Rápido

**Método mais rápido:**

1. Faça login no sistema
2. Abra console (F12)
3. Procure por: `✅ Player registrado no servidor`
4. Se aparecer = **FUNCIONANDO!** ✅

---

## 📞 Próximos Passos

Se tudo estiver funcionando:
1. ✅ Integre com eventos do sistema (ocorrências, etc.)
2. ✅ Teste em diferentes browsers
3. ✅ Teste em dispositivos móveis
4. ✅ Configure notificações automáticas

---

**Boa sorte com os testes! 🚀**

