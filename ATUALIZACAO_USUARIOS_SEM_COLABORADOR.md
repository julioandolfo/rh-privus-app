# ✅ Atualização: Suporte para Usuários sem Colaborador

## 🎉 O que foi implementado

O sistema de feedback agora **suporta enviar e solicitar feedback para usuários que não têm colaborador vinculado!**

---

## 🔄 Mudanças Implementadas

### 1. **Função `get_colaboradores_disponiveis()` - Modificada**
**Arquivo:** `includes/select_colaborador.php`

**Antes:**
- Retornava apenas colaboradores ativos

**Agora:**
- Retorna colaboradores ativos **+** usuários sem colaborador vinculado
- Usa UNION para combinar ambas as queries
- Adiciona badge "(Usuário)" no nome para identificar

### 2. **Formato de ID Modificado**

Para diferenciar colaboradores de usuários, criamos um novo formato:

```
c_123  →  Colaborador ID 123
u_456  →  Usuário ID 456
```

---

## 📁 Arquivos Modificados

### 1. `includes/select_colaborador.php`
- ✅ Função `get_colaboradores_disponiveis()` agora usa UNION
- ✅ Retorna colaboradores + usuários
- ✅ Adiciona campo `tipo` (colaborador/usuario)
- ✅ Adiciona campos `colaborador_id` e `usuario_id`
- ✅ Badge "(Usuário)" no nome

### 2. `api/feedback/enviar.php`
- ✅ Decodifica ID no formato c_ ou u_
- ✅ Valida colaborador OU usuário
- ✅ Busca usuario_id se for colaborador
- ✅ Suporta destinatário como usuário direto

### 3. `api/feedback/solicitar.php`
- ✅ Decodifica ID no formato c_ ou u_
- ✅ Valida colaborador OU usuário
- ✅ Preenche solicitado_colaborador_id ou solicitado_usuario_id
- ✅ Verifica duplicação para ambos os tipos

### 4. `api/feedback/responder_solicitacao.php`
- ✅ Monta ID correto ao redirecionar (c_ ou u_)

### 5. `pages/feedback_enviar.php`
- ✅ Suporta pré-seleção de destinatário vindo de solicitação

### 6. `api/feedback/gestao_solicitacoes.php`
- ✅ Corrigido email_corporativo → email_pessoal

---

## 🎯 Query SQL Utilizada

```sql
-- Para ADMIN (exemplo)
SELECT 
    CONCAT('c_', c.id) as id,
    c.id as colaborador_id,
    NULL as usuario_id,
    c.nome_completo,
    c.foto,
    'colaborador' as tipo
FROM colaboradores c
WHERE c.status = 'ativo'

UNION ALL

SELECT 
    CONCAT('u_', u.id) as id,
    NULL as colaborador_id,
    u.id as usuario_id,
    u.nome as nome_completo,
    NULL as foto,
    'usuario' as tipo
FROM usuarios u
WHERE u.colaborador_id IS NULL
AND u.status = 'ativo'

ORDER BY nome_completo
```

---

## 🎨 Interface do Usuário

### No Select de Colaborador:

```
┌────────────────────────────────────┐
│ Selecione um colaborador...        │
├────────────────────────────────────┤
│ 👤 Ana Silva                       │ ← Colaborador
│ 👤 Carlos Santos                   │ ← Colaborador
│ 👤 João Oliveira (Usuário)         │ ← Usuário sem colaborador
│ 👤 Maria Souza                     │ ← Colaborador
│ 👤 Pedro Costa (Usuário)           │ ← Usuário sem colaborador
└────────────────────────────────────┘
```

O badge **(Usuário)** aparece automaticamente para usuários sem colaborador vinculado.

---

## 🔄 Fluxos Suportados

### Fluxo 1: Colaborador → Usuário
```
1. Colaborador A solicita feedback de Usuário B (sem colaborador)
2. Usuário B recebe notificação
3. Usuário B aceita e envia feedback
4. Feedback é registrado normalmente
```

### Fluxo 2: Usuário → Colaborador
```
1. Usuário A (sem colaborador) solicita feedback de Colaborador B
2. Colaborador B recebe notificação
3. Colaborador B aceita e envia feedback
4. Feedback é registrado normalmente
```

### Fluxo 3: Usuário → Usuário
```
1. Usuário A solicita feedback de Usuário B (ambos sem colaborador)
2. Usuário B recebe notificação
3. Usuário B aceita e envia feedback
4. Feedback é registrado normalmente
```

---

## 📊 Estrutura do Banco

### Tabela `feedbacks`
Campos que suportam usuários:
- `remetente_usuario_id` (pode ser preenchido mesmo sem colaborador)
- `remetente_colaborador_id` (NULL se for usuário puro)
- `destinatario_usuario_id` (pode ser preenchido mesmo sem colaborador)
- `destinatario_colaborador_id` (NULL se for usuário puro)

### Tabela `feedback_solicitacoes`
Campos que suportam usuários:
- `solicitante_usuario_id` (pode ser preenchido mesmo sem colaborador)
- `solicitante_colaborador_id` (NULL se for usuário puro)
- `solicitado_usuario_id` (pode ser preenchido mesmo sem colaborador)
- `solicitado_colaborador_id` (NULL se for usuário puro)

---

## ✅ Validações Implementadas

### Para Colaboradores:
- ✅ Deve existir
- ✅ Deve estar ativo (status = 'ativo')
- ✅ Não pode enviar/solicitar para si mesmo

### Para Usuários:
- ✅ Deve existir
- ✅ Deve estar ativo (status = 'ativo')
- ✅ Não pode enviar/solicitar para si mesmo
- ✅ Não precisa ter colaborador vinculado

---

## 🎭 Casos de Uso

### Caso 1: RH sem Colaborador
Um usuário RH que não tem registro na tabela de colaboradores agora pode:
- ✅ Receber feedbacks de colaboradores
- ✅ Ser solicitado a enviar feedback
- ✅ Enviar feedbacks para colaboradores
- ✅ Solicitar feedbacks de colaboradores

### Caso 2: Admin sem Colaborador
Um admin que não está cadastrado como colaborador pode:
- ✅ Participar do sistema de feedback normalmente
- ✅ Aparecer na lista de seleção
- ✅ Receber notificações

### Caso 3: Gestor sem Colaborador
Um gestor que não tem registro de colaborador pode:
- ✅ Enviar e receber feedbacks
- ✅ Participar das solicitações

---

## 🔍 Como Identificar no Sistema

### No Select:
- **Colaborador:** Aparece apenas o nome
- **Usuário:** Aparece o nome + "(Usuário)"

### No Banco:
- **Colaborador:** `colaborador_id` preenchido
- **Usuário:** `colaborador_id` NULL, mas `usuario_id` preenchido

---

## ⚠️ Notas Importantes

### Compatibilidade:
- ✅ 100% compatível com feedbacks antigos
- ✅ Não quebra nenhuma funcionalidade existente
- ✅ Queries antigas continuam funcionando

### Performance:
- ✅ UNION otimizado
- ✅ Índices mantidos
- ✅ Sem impacto negativo

### Segurança:
- ✅ Mesmas validações aplicadas
- ✅ Permissões respeitadas
- ✅ Proteção contra SQL injection

---

## 🧪 Como Testar

### Teste 1: Criar Usuário sem Colaborador
```sql
-- Criar usuário de teste sem colaborador
INSERT INTO usuarios (nome, email, senha, role, status, colaborador_id) 
VALUES ('Teste Usuário', 'teste@usuario.com', 'senha_hash', 'RH', 'ativo', NULL);
```

### Teste 2: Solicitar Feedback
1. Login com colaborador
2. Acesse "Solicitar Feedback"
3. Veja que "Teste Usuário (Usuário)" aparece na lista
4. Selecione e envie
5. Login com "Teste Usuário"
6. Veja a solicitação recebida

### Teste 3: Enviar Feedback
1. Login com colaborador
2. Acesse "Enviar Feedback"
3. Veja que "Teste Usuário (Usuário)" aparece na lista
4. Selecione e envie
5. Login com "Teste Usuário"
6. Veja o feedback recebido

---

## ✅ Tudo Pronto!

O sistema agora é **totalmente inclusivo**:
- ✅ Colaboradores podem participar
- ✅ Usuários sem colaborador podem participar
- ✅ Todos aparecem na lista de seleção
- ✅ Badge "(Usuário)" identifica quem não tem colaborador
- ✅ Notificações funcionam para ambos

**Data:** Fevereiro 2026  
**Versão:** 1.1.0
