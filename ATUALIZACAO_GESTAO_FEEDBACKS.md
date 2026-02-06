# ✅ Atualização: Gestão de Feedbacks

## 🎉 O que foi adicionado

A página **Gestão de Feedbacks** (`feedback_gestao.php`) agora possui **DUAS ABAS**:

---

## 📑 Aba 1: Feedbacks (já existia)

Visualização de todos os feedbacks enviados no sistema.

### Estatísticas:
- ✅ Total de Feedbacks
- ✅ Feedbacks Anônimos
- ✅ Feedbacks Presenciais
- ✅ Participantes Únicos

### Filtros:
- Remetente
- Destinatário
- Data Início/Fim
- Tipo (Anônimo/Não Anônimo)
- Presencial (Sim/Não)

### Tabela:
- Remetente (com foto)
- Destinatário (com foto)
- Conteúdo (preview)
- Avaliações (estrelas)
- Informações (badges)
- Data
- Ações (Ver detalhes)

---

## 📑 Aba 2: Solicitações ⭐ *NOVA*

Visualização de todas as solicitações de feedback do sistema.

### Estatísticas:
- ✅ Total de Solicitações
- ✅ Pendentes
- ✅ Aceitas
- ✅ Recusadas
- ✅ Concluídas

### Filtros:
- Solicitante
- Solicitado
- Status (Pendente/Aceita/Recusada/Concluída/Expirada)
- Data Início/Fim

### Tabela:
- **Solicitante** (com foto e email)
- **Solicitado** (com foto e email)
- **Mensagem** (preview)
- **Status** (badge colorido)
  - Se concluída: link para ver o feedback
- **Prazo** (com destaque se vencido)
- **Data Solicitação**
- **Data Resposta**

---

## 📂 Arquivos Criados/Modificados

### Novo Arquivo:
- ✅ `api/feedback/gestao_solicitacoes.php` - API para listar solicitações (RH/Admin)

### Arquivo Modificado:
- ✅ `pages/feedback_gestao.php` - Adicionada aba de Solicitações

---

## 🎨 Interface

### Tabs Bootstrap
```
┌─────────────┬──────────────────┐
│  Feedbacks  │  Solicitações   │  ← Tabs para alternar
└─────────────┴──────────────────┘
```

### Aba Feedbacks
```
┌─────────────────────────────────┐
│  📊 ESTATÍSTICAS (4 cards)      │
├─────────────────────────────────┤
│  🔍 FILTROS                     │
├─────────────────────────────────┤
│  📋 TABELA DE FEEDBACKS         │
└─────────────────────────────────┘
```

### Aba Solicitações
```
┌─────────────────────────────────┐
│  📊 ESTATÍSTICAS (5 cards)      │
├─────────────────────────────────┤
│  🔍 FILTROS                     │
├─────────────────────────────────┤
│  📋 TABELA DE SOLICITAÇÕES      │
└─────────────────────────────────┘
```

---

## 🔐 Permissões

Apenas **ADMIN** e **RH** podem acessar:
- ✅ Gestão de Feedbacks
- ✅ Gestão de Solicitações

---

## 📊 Informações Mostradas nas Solicitações

### Badges de Status:
- 🟡 **Pendente** - Aguardando resposta
- 🟢 **Aceita** - Solicitação aceita
- 🔴 **Recusada** - Solicitação recusada
- 🔵 **Concluída** - Feedback já foi enviado
- ⚫ **Expirada** - Prazo vencido

### Destaque de Prazo:
- ⚠️ **Prazo vencido** - Destacado em vermelho com badge "Vencido"
- ✅ **No prazo** - Texto normal

### Link para Feedback:
- Se status = **Concluída**, mostra link "Ver Feedback" na coluna Status

---

## 🚀 Como Usar

### Para RH/Admin:

1. Acesse **Gestão de Feedbacks**
2. Clique na aba **"Solicitações"**
3. Veja todas as solicitações do sistema
4. Use os filtros para buscar:
   - Quem solicitou
   - Para quem foi solicitado
   - Status específico
   - Período
5. Acompanhe:
   - Quantas solicitações estão pendentes
   - Quais foram aceitas/recusadas
   - Quais já foram concluídas
   - Prazos vencidos

---

## 📈 Benefícios

### Visão Completa:
- ✅ RH pode acompanhar todo o fluxo de solicitações
- ✅ Identificar colaboradores que não respondem solicitações
- ✅ Ver quem está solicitando mais feedbacks
- ✅ Acompanhar prazos vencidos

### Gestão Proativa:
- ✅ Enviar lembretes para solicitações pendentes
- ✅ Entender a cultura de feedback da empresa
- ✅ Identificar problemas (muitas recusas)
- ✅ Monitorar engajamento

---

## 🎯 Estatísticas Úteis

### Total de Solicitações
Quantidade geral de solicitações criadas

### Pendentes
Solicitações aguardando resposta (Aceitar/Recusar)

### Aceitas
Solicitações aceitas, aguardando envio do feedback

### Recusadas
Solicitações que foram recusadas

### Concluídas
Solicitações aceitas e com feedback já enviado

---

## 💡 Exemplos de Uso

### Caso 1: Acompanhar Pendentes
```
Filtro: Status = "Pendente"
Resultado: Lista todas solicitações aguardando resposta
Ação: RH pode enviar lembrete manual
```

### Caso 2: Prazos Vencidos
```
Filtro: Status = "Pendente" + observar coluna Prazo
Resultado: Identifica solicitações com prazo vencido (vermelho)
Ação: Cobrar resposta do colaborador
```

### Caso 3: Taxa de Aceitação
```
Comparar: Total Aceitas vs Total Recusadas
Resultado: Entender se as pessoas estão dispostas a dar feedback
Ação: Trabalhar cultura se taxa de recusa for alta
```

### Caso 4: Quem Solicita Mais
```
Filtro: Solicitante = "João Silva"
Resultado: Ver todas solicitações feitas por João
Ação: Identificar colaboradores proativos
```

### Caso 5: Quem Recusa Mais
```
Filtro: Solicitado = "Maria Santos" + Status = "Recusada"
Resultado: Ver se Maria recusa muitas solicitações
Ação: Conversar sobre importância do feedback
```

---

## 🔄 Fluxo Completo Rastreável

```
1. Colaborador A solicita feedback de Colaborador B
   ↓
2. RH vê na aba "Solicitações" com Status "Pendente"
   ↓
3. Colaborador B aceita
   ↓
4. Status muda para "Aceita"
   ↓
5. Colaborador B envia o feedback
   ↓
6. Status muda para "Concluída"
   ↓
7. RH pode clicar em "Ver Feedback" para visualizar
```

---

## ✅ Tudo Pronto!

A página **Gestão de Feedbacks** agora oferece:
- ✅ Visão completa de feedbacks
- ✅ Visão completa de solicitações
- ✅ Estatísticas detalhadas
- ✅ Filtros poderosos
- ✅ Interface intuitiva com tabs
- ✅ Informações completas (fotos, emails, datas)

**Basta acessar a página e clicar na aba "Solicitações"!** 🎉

---

**Data:** Fevereiro 2026  
**Versão:** 1.0.0
