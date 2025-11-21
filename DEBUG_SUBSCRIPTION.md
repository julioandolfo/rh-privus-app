# 🔍 Debug: Subscription OneSignal não aparece

## ❌ Problema

Usuário permitiu notificações no iPhone, mas não aparece nada na tabela `onesignal_subscriptions`.

## 🔧 Solução: Debug Melhorado

Adicionei logs detalhados para identificar o problema. Siga estes passos:

### 1. Abra o Console do Browser

**No iPhone:**
- Abra o Safari
- Vá em Configurações → Safari → Avançado → Web Inspector
- Conecte o iPhone ao Mac
- Abra Safari no Mac → Desenvolvimento → [Seu iPhone] → [Página]

**Ou use Eruda (mais fácil):**
- Adicione `?debug=1` na URL
- Console aparecerá na tela

### 2. Verifique os Logs

Procure por estas mensagens no console:

```
✅ OneSignal inicializado
📱 Player ID obtido: [ID]
📡 Registrando subscription em: [URL]
✅ Player registrado com sucesso!
```

### 3. Possíveis Problemas

#### Problema 1: Player ID não está disponível

**Sintoma:** Console mostra `⚠️ Player ID ainda não disponível`

**Solução:**
- Aguarde alguns segundos após permitir notificações
- Recarregue a página
- Verifique se OneSignal está configurado corretamente

#### Problema 2: Não autenticado (401)

**Sintoma:** Console mostra `❌ Erro ao registrar player: Não autenticado`

**Solução:**
- Faça login primeiro
- Verifique se cookies de sessão estão sendo enviados
- Tente em modo anônimo/privado (pode ser problema de cookies)

#### Problema 3: Caminho da API incorreto (404)

**Sintoma:** Console mostra `404` ou `Failed to fetch`

**Solução:**
- Verifique o caminho no console: `Registrando subscription em:`
- Teste esse caminho diretamente no browser
- Verifique se está usando `/rh/` ou `/rh-privus/` correto

#### Problema 4: Tabela não existe

**Sintoma:** Erro no servidor sobre tabela não encontrada

**Solução:**
- Execute: `criar_tabelas_onesignal.php`
- Ou execute o SQL manualmente: `migracao_onesignal.sql`

### 4. Teste Manual

Abra o console e execute:

```javascript
// Verifica se OneSignal está carregado
console.log('OneSignal:', window.OneSignal);

// Tenta obter player ID manualmente
OneSignal.push(function() {
    OneSignal.getUserId(function(userId) {
        console.log('Player ID:', userId);
        
        if (userId) {
            // Tenta registrar manualmente
            fetch('/rh/api/onesignal/subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ player_id: userId })
            })
            .then(r => r.json())
            .then(data => console.log('Resultado:', data));
        }
    });
});
```

### 5. Verifique no Banco de Dados

Execute no banco:

```sql
SELECT * FROM onesignal_subscriptions ORDER BY created_at DESC LIMIT 10;
```

Se não aparecer nada, o registro não está chegando ao servidor.

## 📋 Checklist de Debug

- [ ] OneSignal está inicializado? (console mostra "OneSignal inicializado")
- [ ] Player ID está disponível? (console mostra o ID)
- [ ] Usuário está logado? (verifique sessão)
- [ ] Caminho da API está correto? (teste diretamente no browser)
- [ ] Tabela existe? (verifique no banco)
- [ ] Cookies estão sendo enviados? (verifique Network tab)

## 🐛 Logs Adicionados

Agora o código mostra:
- ✅ Quando OneSignal inicializa
- ✅ Quando player_id é obtido
- ✅ Quando tenta registrar
- ✅ Resposta completa do servidor
- ✅ Erros detalhados

---

**Abra o console e me diga o que aparece!** Isso vai ajudar a identificar o problema exato.

