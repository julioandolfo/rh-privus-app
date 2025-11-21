# 🚀 Guia de Instalação - PWA Completo com Notificações Push

## ✅ Checklist de Instalação

Siga estes passos na ordem para implementar o PWA completo:

---

## 📋 Passo 1: Instalar Dependências

### 1.1 Instalar biblioteca PHP Web Push

```bash
composer require minishlink/web-push
```

**Ou manualmente**, edite `composer.json` e execute:
```bash
composer install
```

---

## 📋 Passo 2: Criar Tabelas no Banco de Dados

### 2.1 Execute o SQL

Execute o arquivo `migracao_push_notifications.sql` no seu banco de dados MySQL:

```bash
mysql -u seu_usuario -p nome_do_banco < migracao_push_notifications.sql
```

**Ou copie e cole** o conteúdo do arquivo no phpMyAdmin/HeidiSQL.

---

## 📋 Passo 3: Gerar Chaves VAPID

### 3.1 Execute o script PHP

```bash
php scripts/gerar_vapid_keys.php
```

**Importante:** Guarde as chaves geradas em local seguro!

Você verá algo como:
```
✅ Chaves VAPID geradas com sucesso!

Public Key (use no frontend):
BElGCi...

Private Key (mantenha segura):
8xKLx...
```

---

## 📋 Passo 4: Verificar Arquivos Criados

Verifique se os seguintes arquivos foram criados:

### Arquivos na Raiz:
- ✅ `manifest.json`
- ✅ `sw.js`

### Arquivos em `api/push/`:
- ✅ `api/push/vapid-key.php`
- ✅ `api/push/subscribe.php`
- ✅ `api/push/send.php`

### Arquivos em `assets/js/`:
- ✅ `assets/js/push-notifications.js`

### Arquivos em `includes/`:
- ✅ `includes/push_notifications.php`

### Arquivos em `scripts/`:
- ✅ `scripts/gerar_vapid_keys.php`

---

## 📋 Passo 5: Ajustar Caminhos (Se Necessário)

### 5.1 Verificar Base Path

Se seu projeto está em subpasta (ex: `/rh-privus/`), os arquivos já estão configurados.

Se estiver na raiz do servidor, ajuste:

**Em `manifest.json`:**
```json
"start_url": "/",
"scope": "/",
```

**Em `sw.js`:**
```javascript
const BASE_PATH = ''; // Vazio se na raiz
```

**Em `assets/js/push-notifications.js`:**
```javascript
basePath: '' // Vazio se na raiz
```

---

## 📋 Passo 6: Testar Instalação

### 6.1 Acesse o Sistema

1. Abra `http://localhost/rh-privus/login.php`
2. Faça login normalmente
3. Abra o Console do Browser (F12)
4. Você deve ver: `✅ Push notifications ativadas`

### 6.2 Verificar Service Worker

1. Abra DevTools (F12)
2. Vá em **Application** → **Service Workers**
3. Deve aparecer: `sw.js` registrado e ativo

### 6.3 Verificar Manifest

1. Em **Application** → **Manifest**
2. Deve mostrar informações do PWA

---

## 📋 Passo 7: Testar Notificações Push

### 7.1 Permitir Notificações

1. Faça login no sistema
2. O browser perguntará: "Permitir notificações?"
3. Clique em **Permitir**

### 7.2 Enviar Notificação de Teste

Crie um arquivo `test_push.php` na raiz:

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
    'Teste de Notificação',
    'Esta é uma notificação de teste do sistema!',
    '/rh-privus/pages/dashboard.php'
);

echo json_encode($result);
```

Acesse: `http://localhost/rh-privus/test_push.php`

**Resultado esperado:** Notificação aparece no dispositivo!

---

## 📋 Passo 8: Integrar com Sistema Existente

### 8.1 Exemplo: Notificar ao Criar Ocorrência

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

## 📋 Passo 9: Instalar como App (PWA)

### 9.1 No Chrome/Edge (Desktop)

1. Acesse o sistema
2. Clique no ícone de instalação na barra de endereço
3. Ou: Menu → "Instalar RH Privus"

### 9.2 No Chrome (Mobile)

1. Acesse o sistema
2. Menu (3 pontos) → "Adicionar à tela inicial"
3. Confirme

### 9.3 No Safari (iOS)

1. Acesse o sistema
2. Compartilhar → "Adicionar à Tela de Início"

---

## ✅ Verificação Final

### Checklist de Funcionamento:

- [ ] Service Worker registrado
- [ ] Manifest carregado
- [ ] Push notifications permitidas
- [ ] Subscription registrada no banco
- [ ] Notificação de teste funcionou
- [ ] App instalável aparece no browser
- [ ] Ícone aparece na tela inicial (após instalar)

---

## 🐛 Solução de Problemas

### Problema: Service Worker não registra

**Solução:**
- Verifique se está usando HTTP/HTTPS (não `file://`)
- Verifique console do browser para erros
- Limpe cache: Ctrl+Shift+Delete → Cache

### Problema: Chaves VAPID não encontradas

**Solução:**
```bash
php scripts/gerar_vapid_keys.php
```

### Problema: Notificações não aparecem

**Solução:**
1. Verifique se permitiu notificações no browser
2. Verifique se subscription está no banco:
   ```sql
   SELECT * FROM push_subscriptions;
   ```
3. Verifique logs do PHP para erros

### Problema: CORS Error

**Solução:**
- Verifique se headers CORS estão nas APIs
- Verifique se `Access-Control-Allow-Origin` está correto

---

## 📚 Próximos Passos

1. ✅ Testar em diferentes browsers
2. ✅ Criar interface admin para enviar notificações
3. ✅ Integrar com mais eventos do sistema
4. ✅ Personalizar ícones do app (criar ícones específicos)

---

## 🎯 Arquivos Modificados/Criados

### Criados:
- `manifest.json`
- `sw.js`
- `api/push/vapid-key.php`
- `api/push/subscribe.php`
- `api/push/send.php`
- `assets/js/push-notifications.js`
- `includes/push_notifications.php`
- `scripts/gerar_vapid_keys.php`
- `migracao_push_notifications.sql`

### Modificados:
- `composer.json` (adicionada biblioteca web-push)
- `includes/header.php` (adicionado manifest)
- `includes/footer.php` (adicionado script push)
- `login.php` (adicionado manifest e SW)

---

## 🎉 Pronto!

Seu sistema agora é um **PWA completo** com **notificações push** funcionando! 🚀

**Dúvidas?** Consulte os outros guias:
- `GUIA_NOTIFICACOES_PUSH.md` - Guia completo de push
- `EXEMPLOS_NOTIFICACOES_ESPECIFICAS.md` - Exemplos práticos

