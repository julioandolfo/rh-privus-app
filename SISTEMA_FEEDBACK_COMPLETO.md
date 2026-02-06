# Sistema de Feedback Completo - RH Privus

## Resumo da Implementação

Este documento descreve o sistema completo de feedback implementado no RH Privus, incluindo a nova funcionalidade de **Solicitação de Feedback**.

---

## ✅ Funcionalidades Implementadas

### 1. **Envio de Feedback** (Já existia)
- Colaboradores podem enviar feedback para outros colaboradores
- Avaliação por itens com estrelas (1-5)
- Feedback anônimo (opcional)
- Feedback presencial (opcional)
- Anotações internas privadas
- Templates de feedback predefinidos
- Sistema de respostas (thread de conversa)

### 2. **Solicitação de Feedback** (NOVO - Implementado)
- Colaboradores podem **solicitar** que outros enviem feedback sobre eles
- Pode solicitar para qualquer colaborador, gestor, RH ou admin
- Mensagem opcional explicando o motivo da solicitação
- Prazo opcional (máximo 90 dias)
- Notificações completas (email, push, notificação interna)

### 3. **Notificações Completas**
Todas as ações do sistema enviam:
- ✅ **Notificação Interna** (no sistema)
- ✅ **Email** (com template HTML profissional)
- ✅ **Push Notification** (OneSignal)

---

## 📁 Arquivos Criados/Modificados

### Banco de Dados
1. **`migracao_feedback_solicitacoes.sql`**
   - Cria tabela `feedback_solicitacoes`
   - Adiciona pontos para solicitar e responder solicitações

2. **`migracao_template_solicitacao_feedback_email.sql`**
   - Template de email para solicitação de feedback

### Páginas (Frontend)
1. **`pages/feedback_solicitar.php`**
   - Formulário para solicitar feedback
   - Select de colaboradores com busca
   - Campo de mensagem opcional
   - Campo de prazo opcional

2. **`pages/feedback_solicitacoes.php`**
   - Visualização de solicitações enviadas e recebidas
   - Botões para aceitar/recusar solicitações
   - Modal para responder com mensagem
   - Filtros por tipo (recebidas/enviadas)

### APIs (Backend)
1. **`api/feedback/solicitar.php`**
   - Cria nova solicitação de feedback
   - Validações completas
   - Proteção contra duplicação
   - Sistema de pontos

2. **`api/feedback/listar_solicitacoes.php`**
   - Lista solicitações enviadas ou recebidas
   - Paginação
   - Ordenação por status e data

3. **`api/feedback/responder_solicitacao.php`**
   - Aceita ou recusa solicitação
   - Mensagem de resposta opcional
   - Sistema de pontos
   - Redirecionamento automático para envio de feedback (se aceitar)

### Notificações
1. **`includes/feedback_notificacoes.php`** (modificado)
   - Adicionadas funções:
     - `notificar_solicitacao_feedback()`
     - `enviar_email_solicitacao_feedback()`
     - `enviar_push_solicitacao_feedback()`
     - `notificar_resposta_solicitacao()`

---

## 🔄 Fluxo Completo

### Fluxo 1: Solicitação de Feedback

```
1. Colaborador A acessa "Solicitar Feedback"
   ↓
2. Seleciona Colaborador B
   ↓
3. Escreve mensagem (opcional) e define prazo (opcional)
   ↓
4. Envia solicitação
   ↓
5. Sistema cria registro na tabela `feedback_solicitacoes`
   ↓
6. Colaborador A ganha pontos (+10)
   ↓
7. Colaborador B recebe:
   - Notificação interna
   - Email automático
   - Push notification
   ↓
8. Colaborador B acessa "Minhas Solicitações > Recebidas"
   ↓
9. Pode ACEITAR ou RECUSAR
```

### Fluxo 2a: Aceitar Solicitação

```
1. Colaborador B clica em "Aceitar"
   ↓
2. Pode escrever mensagem (opcional)
   ↓
3. Confirma
   ↓
4. Sistema atualiza status para "aceita"
   ↓
5. Colaborador B ganha pontos (+20)
   ↓
6. Colaborador B é redirecionado para "Enviar Feedback"
   ↓
7. Envia feedback normalmente
   ↓
8. Colaborador A recebe notificações do feedback
   ↓
9. Status da solicitação muda para "concluída"
```

### Fluxo 2b: Recusar Solicitação

```
1. Colaborador B clica em "Recusar"
   ↓
2. Pode escrever mensagem explicando (opcional)
   ↓
3. Confirma
   ↓
4. Sistema atualiza status para "recusada"
   ↓
5. Colaborador B ganha pontos (+20)
   ↓
6. Colaborador A recebe notificações da recusa
```

---

## 📊 Status das Solicitações

| Status | Descrição |
|--------|-----------|
| `pendente` | Aguardando resposta do solicitado |
| `aceita` | Solicitação aceita, aguardando envio do feedback |
| `recusada` | Solicitação recusada |
| `concluida` | Feedback foi enviado |
| `expirada` | Prazo expirado sem resposta |

---

## 🎯 Sistema de Pontos

| Ação | Pontos | Descrição |
|------|--------|-----------|
| Enviar feedback | +30 | Ao enviar um feedback para alguém |
| Solicitar feedback | +10 | Ao solicitar feedback de alguém |
| Responder solicitação | +20 | Ao aceitar ou recusar uma solicitação |

---

## 📧 Templates de Email

### Email 1: Solicitação de Feedback
**Assunto:** `{solicitante_nome} está solicitando um feedback seu`

**Conteúdo:**
- Informação sobre quem está solicitando
- Mensagem do solicitante (se houver)
- Prazo sugerido (se houver)
- Botão "Ver Solicitação"

### Email 2: Feedback Recebido (já existia)
**Assunto:** `Novo Feedback Recebido - {remetente_nome}`

**Conteúdo:**
- Informação sobre quem enviou
- Avaliações por itens
- Conteúdo do feedback
- Badges (anônimo, presencial)
- Botão "Ver Feedback"

---

## 🔔 Notificações Push

### Push 1: Solicitação Recebida
```
Título: Nova Solicitação de Feedback
Mensagem: {nome} está pedindo que você envie um feedback
URL: /pages/feedback_solicitacoes.php?tipo=recebidas
```

### Push 2: Solicitação Aceita
```
Título: Solicitação Aceita!
Mensagem: {nome} aceitou sua solicitação de feedback
URL: /pages/feedback_solicitacoes.php?tipo=enviadas
```

### Push 3: Solicitação Recusada
```
Título: Solicitação Recusada
Mensagem: {nome} recusou sua solicitação de feedback
URL: /pages/feedback_solicitacoes.php?tipo=enviadas
```

### Push 4: Feedback Recebido (já existia)
```
Título: Novo Feedback Recebido
Mensagem: {nome} enviou um feedback para você
URL: /pages/feedback_meus.php?tipo=recebidos
```

---

## 🛠️ Instalação

### Passo 1: Executar Migrações SQL

```sql
-- 1. Executar migração da tabela (se ainda não existir)
source migracao_feedbacks.sql;

-- 2. Executar migração de solicitações
source migracao_feedback_solicitacoes.sql;

-- 3. Executar template de email de feedback (se ainda não existir)
source migracao_template_feedback_email.sql;

-- 4. Executar template de email de solicitação
source migracao_template_solicitacao_feedback_email.sql;
```

### Passo 2: Verificar Arquivos

Certifique-se de que todos os arquivos foram criados:

```
✅ pages/feedback_solicitar.php
✅ pages/feedback_solicitacoes.php
✅ api/feedback/solicitar.php
✅ api/feedback/listar_solicitacoes.php
✅ api/feedback/responder_solicitacao.php
✅ includes/feedback_notificacoes.php (modificado)
```

### Passo 3: Adicionar ao Menu

Adicione os links no menu de navegação do sistema:

```php
// No arquivo de menu (includes/header.php ou menu.php)

// Submenu de Feedback
<div class="menu-item">
    <a class="menu-link" href="feedback_enviar.php">
        <span class="menu-title">Enviar Feedback</span>
    </a>
</div>

<div class="menu-item">
    <a class="menu-link" href="feedback_solicitar.php">
        <span class="menu-title">Solicitar Feedback</span>
    </a>
</div>

<div class="menu-item">
    <a class="menu-link" href="feedback_solicitacoes.php">
        <span class="menu-title">Minhas Solicitações</span>
    </a>
</div>

<div class="menu-item">
    <a class="menu-link" href="feedback_meus.php">
        <span class="menu-title">Meus Feedbacks</span>
    </a>
</div>

<div class="menu-item">
    <a class="menu-link" href="feedback_gestao.php">
        <span class="menu-title">Gestão (RH/Admin)</span>
    </a>
</div>
```

### Passo 4: Configurar Permissões

Adicione as permissões necessárias no arquivo `permissions.php`:

```php
// Permissões de Feedback
'feedback_solicitar.php' => ['TODOS'], // Todos podem solicitar
'feedback_solicitacoes.php' => ['TODOS'], // Todos podem ver suas solicitações
'feedback_enviar.php' => ['TODOS'], // Todos podem enviar
'feedback_meus.php' => ['TODOS'], // Todos podem ver seus feedbacks
'feedback_gestao.php' => ['ADMIN', 'RH'], // Apenas admin e RH
```

---

## ✅ Validações Implementadas

### Solicitação de Feedback
- ✅ Não pode solicitar para si mesmo
- ✅ Colaborador deve estar ativo
- ✅ Prazo deve ser futuro (máximo 90 dias)
- ✅ Proteção contra duplicação (5 minutos)
- ✅ Lock atômico do MySQL para prevenir race conditions

### Responder Solicitação
- ✅ Apenas o solicitado pode responder
- ✅ Não pode responder duas vezes
- ✅ Status deve ser "pendente"

### Envio de Feedback (já existia)
- ✅ Não pode enviar para si mesmo
- ✅ Colaborador deve estar ativo
- ✅ Conteúdo obrigatório
- ✅ Proteção contra duplicação (30 segundos)
- ✅ Lock atômico do MySQL

---

## 🎨 Interface do Usuário

### Páginas Principais

1. **Solicitar Feedback** (`feedback_solicitar.php`)
   - Card de explicação sobre como funciona
   - Select2 para buscar colaborador
   - Campo de mensagem opcional
   - Campo de prazo opcional
   - Botão "Enviar Solicitação"

2. **Minhas Solicitações** (`feedback_solicitacoes.php`)
   - Tabs: "Recebidas" e "Enviadas"
   - Cards para cada solicitação
   - Badges de status
   - Botões de ação (Aceitar/Recusar)
   - Link para ver feedback (se concluída)

3. **Enviar Feedback** (`feedback_enviar.php`) - já existia
   - Select de colaborador
   - Checkbox feedback anônimo
   - Avaliação por itens (estrelas)
   - Select de template
   - Campo de conteúdo
   - Checkbox feedback presencial
   - Campo de anotações internas

4. **Meus Feedbacks** (`feedback_meus.php`) - já existia
   - Tabs: "Todos", "Enviados", "Recebidos"
   - Cards de feedback
   - Link para ver detalhes

5. **Ver Feedback** (`ver_feedback.php`) - já existia
   - Informações completas do feedback
   - Avaliações por item
   - Thread de respostas
   - Formulário para responder

---

## 📝 Notas Importantes

### Segurança
- ✅ Todas as APIs verificam autenticação
- ✅ Validações de permissão
- ✅ Proteção contra SQL injection (prepared statements)
- ✅ Proteção contra duplicação (locks atômicos)
- ✅ Escape de HTML em outputs

### Performance
- ✅ Índices no banco de dados
- ✅ Paginação nas listagens
- ✅ Cache de colaboradores
- ✅ Queries otimizadas com JOINs

### UX/UI
- ✅ Feedback visual (loading, success, error)
- ✅ Validação no frontend e backend
- ✅ Mensagens claras e em português
- ✅ Design responsivo
- ✅ Tooltips e ajudas contextuais

---

## 🧪 Como Testar

### Teste 1: Solicitar Feedback
1. Login como Colaborador A
2. Acesse "Solicitar Feedback"
3. Selecione Colaborador B
4. Escreva uma mensagem
5. Defina um prazo
6. Envie
7. Verifique se ganhou pontos
8. Logout

### Teste 2: Receber Solicitação
1. Login como Colaborador B
2. Verifique notificação interna
3. Verifique email recebido
4. Verifique push notification
5. Acesse "Minhas Solicitações > Recebidas"
6. Veja a solicitação

### Teste 3: Aceitar e Enviar Feedback
1. (Continuando como Colaborador B)
2. Clique em "Aceitar"
3. Escreva mensagem opcional
4. Confirme
5. Será redirecionado para "Enviar Feedback"
6. Preencha o feedback
7. Envie
8. Logout

### Teste 4: Receber Feedback
1. Login como Colaborador A
2. Verifique notificação de feedback
3. Verifique email
4. Verifique push
5. Acesse "Meus Feedbacks > Recebidos"
6. Veja o feedback completo

### Teste 5: Recusar Solicitação
1. Login como Colaborador C
2. Solicite feedback de Colaborador D
3. Logout
4. Login como Colaborador D
5. Acesse solicitações recebidas
6. Clique em "Recusar"
7. Escreva motivo
8. Confirme
9. Logout
10. Login como Colaborador C
11. Verifique notificação de recusa

---

## 🚀 Próximas Melhorias (Opcional)

### Funcionalidades Futuras
- [ ] Dashboard de estatísticas de feedback
- [ ] Relatórios personalizados
- [ ] Feedback 360° (múltiplas pessoas avaliando)
- [ ] Lembretes automáticos de solicitações pendentes
- [ ] Exportação de feedbacks em PDF
- [ ] Metas de feedback (quantidade por período)
- [ ] Feedback anônimo para a empresa
- [ ] Badges/conquistas por dar feedbacks

### Melhorias Técnicas
- [ ] Cache de queries frequentes
- [ ] Logs detalhados de auditoria
- [ ] Testes automatizados
- [ ] API REST documentada
- [ ] Webhooks para integrações

---

## 📞 Suporte

Para dúvidas ou problemas:
1. Verifique este documento primeiro
2. Consulte os logs em `/logs/feedback.log`
3. Verifique erros no navegador (Console)
4. Verifique erros no servidor (PHP error log)

---

**Desenvolvido com ❤️ para RH Privus**
**Data:** Fevereiro 2026
**Versão:** 1.0.0
