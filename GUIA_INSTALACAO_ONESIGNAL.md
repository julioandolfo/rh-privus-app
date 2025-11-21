# 🚀 Guia de Instalação - PWA com OneSignal

## ✅ Checklist de Instalação

Siga estes passos na ordem para implementar o PWA completo com OneSignal:

---

## 📋 Passo 1: Criar Conta no OneSignal

### 1.1 Acesse o OneSignal

1. Acesse: https://onesignal.com
2. Clique em **"Sign Up"** e crie uma conta gratuita
3. Faça login

### 1.2 Criar Novo App

1. No dashboard, clique em **"New App/Website"**
2. Escolha um nome: **"RH Privus"**
3. Selecione plataforma: **"Web Push"**
4. Clique em **"Create"**

### 1.3 Obter Credenciais

1. No painel do app criado, vá em **Settings → Keys & IDs**
2. Anote:
   - **OneSignal App ID** (ex: `12345678-1234-1234-1234-123456789012`)
   - **REST API Key** (ex: `NGEwOGZmODItODNiYy00Y2Y0LWI...`)

---

## 📋 Passo 2: Criar Tabelas no Banco de Dados

### 2.1 Execute o SQL

Execute o arquivo `migracao_onesignal.sql` no seu banco de dados MySQL:

```bash
mysql -u seu_usuario -p nome_do_banco < migracao_onesignal.sql
```

**Ou copie e cole** o conteúdo do arquivo no phpMyAdmin/HeidiSQL.

---

## 📋 Passo 3: Configurar OneSignal no Sistema

### 3.1 Acesse a Página de Configuração

1. Faça login no sistema como **ADMIN**
2. Acesse: `http://localhost/rh-privus/pages/configuracoes_onesignal.php`
3. Preencha os campos:
   - **App ID**: Cole o OneSignal App ID
   - **REST API Key**: Cole o REST API Key
   - **Safari Web ID**: (Opcional - deixe vazio se não usar iOS)
4. Clique em **"Salvar Configurações"**

---

## 📋 Passo 4: Verificar Arquivos Criados

Verifique se os seguintes arquivos foram criados:

### Arquivos na Raiz:
- ✅ `manifest.json`
- ✅ `sw.js`
- ✅ `OneSignalSDKWorker.js`

### Arquivos em `api/onesignal/`:
- ✅ `api/onesignal/config.php`
- ✅ `api/onesignal/subscribe.php`
- ✅ `api/onesignal/send.php`

### Arquivos em `assets/js/`:
- ✅ `assets/js/onesignal-init.js`

### Arquivos em `pages/`:
- ✅ `pages/configuracoes_onesignal.php`

---

## 📋 Passo 5: Testar Instalação

### 5.1 Acesse o Sistema

1. Abra `http://localhost/rh-privus/login.php`
2. Faça login normalmente
3. Abra o Console do Browser (F12)
4. Você deve ver: `✅ Player registrado no servidor`

### 5.2 Verificar OneSignal

1. Abra DevTools (F12)
2. Vá em **Application** → **Service Workers**
3. Deve aparecer: `OneSignalSDKWorker.js` registrado

### 5.3 Permitir Notificações

1. O browser perguntará: "Permitir notificações?"
2. Clique em **Permitir**
3. O player_id será registrado automaticamente no banco

---

## 📋 Passo 6: Testar Notificações Push

### 6.1 Enviar Notificação de Teste

Crie um arquivo `test_onesignal.php` na raiz:

```php
<?php
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/push_notifications.php';

// Simula usuário logado (substitua pelo seu ID)
$_SESSION['usuario'] = [
    'id' => 1,
    'role' => 'ADMIN'
];

// Envia notificação de teste
$result = enviar_push_colaborador(
    1, // ID do colaborador
    'Teste OneSignal',
    'Esta é uma notificação de teste do OneSignal!',
    '/rh-privus/pages/dashboard.php'
);

echo "<pre>";
print_r($result);
echo "</pre>";
```

Acesse: `http://localhost/rh-privus/test_onesignal.php`

**Resultado esperado:** Notificação aparece no dispositivo!

---

## 📋 Passo 7: Integrar com Sistema Existente

### 7.1 Exemplo: Notificar ao Criar Ocorrência

Edite `pages/ocorrencias_add.php` e adicione após criar ocorrência:

```php
// Após criar ocorrência (linha ~84)
require_once __DIR__ . '/../includes/push_notifications.php';

// Envia notificação push
enviar_push_colaborador(
    $colaborador_id,
    'Nova Ocorrência Registrada',
    'Uma nova ocorrência foi registrada no seu perfil',
    '/rh-privus/pages/colaborador_view.php?id=' . $colaborador_id
);
```

---

## ✅ Verificação Final

### Checklist de Funcionamento:

- [ ] Conta OneSignal criada
- [ ] App criado no OneSignal
- [ ] Credenciais configuradas no sistema
- [ ] Tabelas criadas no banco
- [ ] Service Worker registrado
- [ ] Notificações permitidas no browser
- [ ] Player registrado no banco
- [ ] Notificação de teste funcionou

---

## 🐛 Solução de Problemas

### Problema: OneSignal não inicializa

**Solução:**
- Verifique se App ID está configurado corretamente
- Verifique console do browser para erros
- Certifique-se de que está usando HTTP/HTTPS (não `file://`)

### Problema: Notificações não aparecem

**Solução:**
1. Verifique se permitiu notificações no browser
2. Verifique se player_id está no banco:
   ```sql
   SELECT * FROM onesignal_subscriptions;
   ```
3. Verifique logs do PHP para erros
4. Verifique se REST API Key está correto

### Problema: CORS Error

**Solução:**
- OneSignal funciona via CDN, não há problema de CORS
- Verifique se está usando HTTPS em produção (OneSignal requer HTTPS)

---

## 📚 Próximos Passos

1. ✅ Testar em diferentes browsers
2. ✅ Criar interface admin para enviar notificações
3. ✅ Integrar com mais eventos do sistema
4. ✅ Personalizar ícones do app

---

## 🎯 Arquivos Modificados/Criados

### Criados:
- `OneSignalSDKWorker.js`
- `api/onesignal/config.php`
- `api/onesignal/subscribe.php`
- `api/onesignal/send.php`
- `assets/js/onesignal-init.js`
- `pages/configuracoes_onesignal.php`
- `migracao_onesignal.sql`

### Modificados:
- `includes/footer.php` (OneSignal SDK)
- `login.php` (OneSignal SDK)
- `includes/push_notifications.php` (API OneSignal)
- `sw.js` (comentário sobre OneSignal)

---

## 💡 Vantagens do OneSignal

- ✅ **Mais fácil de configurar** - Dashboard visual
- ✅ **Melhor suporte iOS** - Funciona melhor no Safari
- ✅ **Analytics integrado** - Veja estatísticas de envio
- ✅ **Gratuito até 10k usuários** - Plano free generoso
- ✅ **Multi-plataforma** - Funciona em web, iOS, Android

---

## 🎉 Pronto!

Seu sistema agora é um **PWA completo** com **OneSignal** funcionando! 🚀

**Dúvidas?** Consulte a documentação oficial: https://documentation.onesignal.com/

