# 🧪 Teste OneSignal no Chrome

## ✅ Funciona Perfeitamente no Chrome!

O OneSignal funciona muito bem no Chrome. Siga estes passos:

## 📋 Passo a Passo

### 1. Acesse a Página de Teste

```
http://localhost/rh-privus/test_subscription.php
```

Ou no servidor:
```
http://seuservidor.com/rh/test_subscription.php
```

### 2. Verifique se OneSignal Está Carregado

- Clique em **"Verificar OneSignal"**
- Deve aparecer: `✅ OneSignal está carregado`

### 3. Solicite Permissão

- Clique em **"🔔 Solicitar Permissão"**
- O Chrome mostrará um prompt na barra de endereço
- Clique em **"Permitir"**

### 4. Aguarde o Player ID

- Após permitir, aguarde 2-3 segundos
- Clique em **"Obter Player ID"**
- Deve aparecer um ID longo (ex: `abc123-def456-...`)

### 5. Verifique o Registro

- O registro deve acontecer automaticamente
- Clique em **"Verificar Subscriptions"**
- Deve aparecer sua subscription na lista

## 🔍 Debug no Chrome

### Abra o Console (F12)

Você deve ver estas mensagens:

```
✅ OneSignal inicializado
📱 Permissão atual: default
📱 Solicitando permissão...
📱 Permissão mudou para: granted
✅ Player ID obtido: [ID]
📡 Registrando subscription em: [URL]
✅ Player registrado com sucesso!
```

### Se Não Funcionar

1. **Verifique se OneSignal está configurado:**
   - Acesse: `pages/configuracoes_onesignal.php`
   - Verifique se App ID e REST API Key estão preenchidos

2. **Verifique o Console:**
   - Procure por erros em vermelho
   - Veja qual mensagem aparece

3. **Limpe Cache:**
   - Ctrl+Shift+Delete
   - Limpe cache e cookies
   - Recarregue a página

## 🎯 Diferenças Chrome vs Safari iOS

| Recurso | Chrome | Safari iOS |
|---------|--------|------------|
| Prompt de permissão | ✅ Barra de endereço | ✅ Prompt nativo |
| Service Worker | ✅ Suportado | ✅ Suportado |
| Player ID | ✅ Gerado automaticamente | ✅ Gerado automaticamente |
| Notificações | ✅ Funciona | ✅ Funciona |

## ✅ Checklist

- [ ] OneSignal está carregado
- [ ] Permissão foi solicitada
- [ ] Usuário permitiu notificações
- [ ] Player ID foi gerado
- [ ] Subscription foi registrada no banco

---

**Teste no Chrome e me diga o que aparece no console!**

