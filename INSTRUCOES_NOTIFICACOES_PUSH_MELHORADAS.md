# 🔔 Sistema de Notificações Push Melhorado

## 📋 Resumo das Melhorias

### ✅ O que foi implementado:

1. **Login Automático** - Ao clicar na notificação, o usuário faz login automaticamente via token
2. **Página de Detalhes** - Nova página dedicada para exibir informações completas da notificação
3. **Registro em Banco** - Todas as notificações push agora são registradas no banco de dados
4. **Tokens de Segurança** - Cada notificação tem um token único que expira em 7 dias
5. **Rastreamento** - Sistema completo de rastreamento (enviada, visualizada, etc)

---

## 🚀 Como Aplicar as Mudanças

### Passo 1: Executar Migração do Banco de Dados

Execute o arquivo SQL no seu banco de dados:

```sql
-- Arquivo: migracao_notificacoes_push_tokens.sql
```

**Como executar:**
1. Abra o HeidiSQL ou phpMyAdmin
2. Selecione seu banco de dados
3. Execute o conteúdo do arquivo `migracao_notificacoes_push_tokens.sql`

Isso criará a tabela `notificacoes_push` com os seguintes campos:
- `id` - ID único
- `notificacao_id` - Referência para `notificacoes_sistema`
- `usuario_id` / `colaborador_id` - Destinatário
- `token` - Token único para login automático (válido por 7 dias)
- `titulo`, `mensagem`, `url` - Dados da notificação
- `enviado`, `visualizada` - Status de rastreamento
- `expira_em` - Data de expiração do token

---

### Passo 2: Verificar os Arquivos Modificados

Os seguintes arquivos foram criados/modificados:

#### ✅ Criados:
1. **`pages/notificacao_view.php`** - Página para visualizar detalhes da notificação
2. **`migracao_notificacoes_push_tokens.sql`** - Script SQL para criar tabela
3. **`INSTRUCOES_NOTIFICACOES_PUSH_MELHORADAS.md`** - Este arquivo

#### ✅ Modificados:
1. **`includes/push_notifications.php`** - Sistema de envio de push atualizado
2. **`pages/promocoes.php`** - Envio de push com novos parâmetros

---

## 🎯 Como Funciona o Novo Sistema

### Fluxo da Notificação:

```
1. Sistema gera notificação
   ↓
2. Cria registro em `notificacoes_sistema` (banco)
   ↓
3. Gera token único de segurança
   ↓
4. Cria registro em `notificacoes_push` (banco)
   ↓
5. Envia push notification com URL + token
   ↓
6. Usuário clica na notificação
   ↓
7. Sistema valida token e faz login automático
   ↓
8. Redireciona para página de detalhes
   ↓
9. Marca notificação como lida
```

### URL da Notificação:

```
https://seusite.com/rh-privus/pages/notificacao_view.php?id=123&token=abc123...
```

**Onde:**
- `id` = ID da notificação em `notificacoes_sistema`
- `token` = Token único para login automático (válido por 7 dias)

---

## 💡 Como Enviar Notificações (Para Desenvolvedores)

### Exemplo 1: Notificação de Promoção (já implementado)

```php
require_once __DIR__ . '/../includes/push_notifications.php';

$resultado = enviar_push_colaborador(
    $colaborador_id,                          // ID do colaborador
    'Parabéns pela Promoção! 🎉',             // Título
    'Você recebeu uma promoção...',           // Mensagem
    'pages/promocoes.php',                    // URL de referência
    'promocao',                               // Tipo da notificação
    $promocao_id,                             // ID da referência
    'promocao'                                // Tipo da referência
);

if ($resultado['success']) {
    echo "Push enviado! Notificação ID: " . $resultado['notificacao_id'];
}
```

### Exemplo 2: Notificação de Ocorrência

```php
require_once __DIR__ . '/../includes/push_notifications.php';

$resultado = enviar_push_colaborador(
    $colaborador_id,
    'Nova Ocorrência Registrada',
    'Uma ocorrência foi registrada no seu perfil.',
    'pages/colaborador_view.php?id=' . $colaborador_id,
    'ocorrencia',
    $ocorrencia_id,
    'ocorrencia'
);
```

### Exemplo 3: Notificação de Horas Extras

```php
require_once __DIR__ . '/../includes/push_notifications.php';

$resultado = enviar_push_colaborador(
    $colaborador_id,
    'Horas Extras Aprovadas',
    'Suas horas extras foram aprovadas e serão pagas.',
    'pages/horas_extras.php',
    'horas_extras',
    $hora_extra_id,
    'hora_extra'
);
```

### Exemplo 4: Notificação para Usuário (ao invés de colaborador)

```php
require_once __DIR__ . '/../includes/push_notifications.php';

$resultado = enviar_push_usuario(
    $usuario_id,
    'Novo Comunicado',
    'Um novo comunicado foi publicado para você.',
    'pages/comunicados.php',
    'comunicado',
    $comunicado_id,
    'comunicado'
);
```

---

## 📊 Parâmetros das Funções

### `enviar_push_colaborador()`

```php
function enviar_push_colaborador(
    $colaborador_id,      // (int) OBRIGATÓRIO - ID do colaborador
    $titulo,              // (string) OBRIGATÓRIO - Título da notificação
    $mensagem,            // (string) OBRIGATÓRIO - Mensagem da notificação
    $url = null,          // (string) OPCIONAL - URL de referência
    $tipo = 'geral',      // (string) OPCIONAL - Tipo (promocao, ocorrencia, etc)
    $referencia_id = null,// (int) OPCIONAL - ID da referência
    $referencia_tipo = null // (string) OPCIONAL - Tipo da referência
)
```

### `enviar_push_usuario()`

```php
function enviar_push_usuario(
    $usuario_id,          // (int) OBRIGATÓRIO - ID do usuário
    $titulo,              // (string) OBRIGATÓRIO - Título da notificação
    $mensagem,            // (string) OBRIGATÓRIO - Mensagem da notificação
    $url = null,          // (string) OPCIONAL - URL de referência
    $tipo = 'geral',      // (string) OPCIONAL - Tipo (promocao, ocorrencia, etc)
    $referencia_id = null,// (int) OPCIONAL - ID da referência
    $referencia_tipo = null // (string) OPCIONAL - Tipo da referência
)
```

### Retorno das Funções

```php
[
    'success' => true,                    // Se foi enviada com sucesso
    'enviadas' => 1,                      // Quantidade de push enviados
    'message' => 'Notificação enviada',   // Mensagem de status
    'notificacao_id' => 123,              // ID da notificação criada
    'push_id' => 456                      // ID do registro de push
]
```

---

## 🔐 Segurança

### Token de Autenticação

- **Único:** Cada notificação tem um token único
- **Seguro:** 64 caracteres hexadecimais (256 bits)
- **Tempo limitado:** Expira em 7 dias
- **Uso único:** Após login, o token é consumido
- **Validação:** Verifica se o token pertence ao usuário correto

### Proteção

- Apenas o usuário correto pode acessar a notificação
- Token expira automaticamente após 7 dias
- Validação de propriedade da notificação
- Session hijacking protection

---

## 📱 Experiência do Usuário

### Antes:
1. ❌ Usuário recebe push
2. ❌ Clica e vai para login
3. ❌ Faz login manualmente
4. ❌ Vai para dashboard (perde contexto)
5. ❌ Não sabe qual era a notificação

### Agora:
1. ✅ Usuário recebe push
2. ✅ Clica e faz login AUTOMÁTICO
3. ✅ Vê página DEDICADA com detalhes
4. ✅ Entende completamente a notificação
5. ✅ Pode clicar para ir ao item original

---

## 🎨 Página de Detalhes da Notificação

A nova página `notificacao_view.php` exibe:

- **Ícone** visual do tipo de notificação
- **Título** da notificação
- **Mensagem** completa
- **Data/hora** de criação
- **Tipo** de referência
- **ID** da referência
- **Botão** para ir ao item original
- **Layout** profissional com sidebar e conteúdo

---

## 📋 Checklist de Implementação

### Para Aplicar Agora:

- [ ] Executar `migracao_notificacoes_push_tokens.sql` no banco de dados
- [ ] Verificar se a tabela `notificacoes_push` foi criada
- [ ] Testar envio de notificação de promoção
- [ ] Verificar se login automático funciona
- [ ] Conferir página de detalhes

### Para Implementar em Outros Módulos:

- [ ] Ocorrências - Adicionar push ao registrar ocorrência
- [ ] Horas Extras - Adicionar push ao aprovar horas extras
- [ ] Comunicados - Adicionar push ao publicar comunicado
- [ ] Eventos - Adicionar push ao convidar para evento
- [ ] Fechamento de Pagamento - Adicionar push ao fechar pagamento
- [ ] Feedback - Adicionar push ao solicitar feedback

---

## 🧪 Como Testar

### Teste 1: Criar Promoção

1. Acesse `pages/promocoes.php`
2. Clique em "Nova Promoção"
3. Preencha os dados e salve
4. Verifique se o colaborador recebeu push
5. Clique na notificação no dispositivo
6. Verifique se fez login automático
7. Verifique se foi para página de detalhes

### Teste 2: Verificar Banco de Dados

```sql
-- Ver notificações push enviadas
SELECT * FROM notificacoes_push ORDER BY id DESC LIMIT 10;

-- Ver notificações do sistema
SELECT * FROM notificacoes_sistema ORDER BY id DESC LIMIT 10;

-- Ver tokens ativos
SELECT id, titulo, token, enviado, visualizada, expira_em 
FROM notificacoes_push 
WHERE expira_em > NOW() 
ORDER BY id DESC;
```

### Teste 3: Verificar Token Expirado

1. Crie uma notificação
2. Altere manualmente `expira_em` para o passado:
   ```sql
   UPDATE notificacoes_push SET expira_em = '2020-01-01' WHERE id = 123;
   ```
3. Tente acessar a URL com o token
4. Deve ir para tela de login normal (token expirado)

---

## 🔧 Troubleshooting

### Problema: Notificação não chega

**Solução:**
1. Verifique se OneSignal está configurado
2. Verifique se colaborador tem dispositivo registrado
3. Verifique logs em `logs/enviar_notificacao_push.log`

### Problema: Login automático não funciona

**Solução:**
1. Verifique se token não expirou (7 dias)
2. Verifique se token existe no banco
3. Verifique se `session_start()` está funcionando

### Problema: Página em branco

**Solução:**
1. Ative error_reporting no PHP
2. Verifique se tabelas existem
3. Verifique permissões de arquivo

---

## 📈 Próximos Passos Sugeridos

1. **Implementar em outros módulos** (ocorrências, horas extras, etc)
2. **Adicionar estatísticas** de notificações (taxa de abertura, etc)
3. **Criar dashboard** de notificações no admin
4. **Adicionar filtros** na página de notificações
5. **Implementar agendamento** de notificações push

---

## 📞 Suporte

Se encontrar problemas:
1. Verifique os logs do sistema
2. Verifique a tabela `notificacoes_push`
3. Teste com um usuário específico
4. Verifique se as migrações foram executadas

---

**✅ Sistema implementado e pronto para uso!**
