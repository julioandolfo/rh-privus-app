# Sistema de Ocorrências Avançado - Documentação Completa

## 📋 Visão Geral

Este documento descreve todas as melhorias implementadas no sistema de ocorrências, transformando-o em uma solução completa e flexível para gestão de ocorrências de colaboradores.

## 🚀 Melhorias Implementadas

### 1. ✅ Campos Dinâmicos Personalizados por Tipo
**Descrição:** Cada tipo de ocorrência pode ter campos customizados (texto, número, data, select, checkbox, radio).

**Como usar:**
- Acesse `tipos_ocorrencias.php`
- Edite um tipo de ocorrência
- Na aba "Campos Dinâmicos", adicione campos personalizados
- Defina tipo, label, obrigatoriedade e validações

**Benefícios:**
- Flexibilidade total para diferentes tipos de ocorrências
- Sem necessidade de alterar código para novos campos
- Validações customizadas por campo

---

### 2. ✅ Sistema de Severidade/Gravidade
**Descrição:** Classificação de ocorrências em 4 níveis: Leve, Moderada, Grave e Crítica.

**Como usar:**
- Ao criar/editar tipo de ocorrência, defina a severidade padrão
- Ao registrar ocorrência, pode alterar a severidade se necessário
- Visualização com cores diferentes por severidade

**Benefícios:**
- Priorização automática
- Filtros por severidade
- Relatórios mais precisos

---

### 3. ✅ Sistema de Anexos/Documentos
**Descrição:** Permite anexar arquivos (PDF, DOC, imagens) às ocorrências.

**Como usar:**
- Ao criar ocorrência, use o campo "Anexos"
- Selecione múltiplos arquivos (máx 10MB cada)
- Visualize e baixe anexos na página de detalhes

**Tipos aceitos:**
- PDF, DOC, DOCX, XLS, XLSX
- JPG, JPEG, PNG, GIF, WEBP

**Benefícios:**
- Evidências documentadas
- Melhor rastreabilidade
- Suporte a múltiplos formatos

---

### 4. ✅ Sistema de Notificações Avançado
**Descrição:** Notificações automáticas para colaborador, gestor e RH baseadas nas configurações do tipo.

**Tipos de notificações enviadas:**
1. **Notificações no Sistema** - Aparecem no campo de notificações do dashboard
2. **Emails** - Enviados automaticamente via SMTP
3. **Push Notifications** - Enviadas via OneSignal para dispositivos móveis/desktop

**Como funciona:**
- Configurável por tipo de ocorrência (3 checkboxes: notificar_colaborador, notificar_gestor, notificar_rh)
- Quando uma ocorrência é criada, o sistema:
  - Cria notificação no sistema (tabela `notificacoes_sistema`)
  - Envia email (se template configurado)
  - Envia push notification via OneSignal (se usuário tem subscription ativa)

**Requisitos para Push:**
- OneSignal configurado em `configuracoes_onesignal.php`
- Usuário/colaborador precisa ter aceitado notificações push no navegador
- Subscription ativa na tabela `onesignal_subscriptions`

**Benefícios:**
- Comunicação automática em múltiplos canais
- Todos informados em tempo real
- Notificações mesmo com sistema fechado (push)
- Configurável por tipo

---

### 5. ✅ Workflow de Aprovação
**Descrição:** Ocorrências graves podem exigir aprovação antes de serem finalizadas.

**Como usar:**
- Configure tipo de ocorrência para "Requer aprovação"
- Ocorrências ficam com status "Pendente"
- Admin/RH aprovam ou rejeitam na página de detalhes

**Benefícios:**
- Controle de qualidade
- Validação antes de aplicar consequências
- Histórico de aprovações

---

### 6. ✅ Sistema de Advertências Progressivas
**Descrição:** Conta advertências automaticamente e aplica consequências progressivas.

**Como funciona:**
- Configure regras em `ocorrencias_regras_advertencias`
- Sistema conta ocorrências do colaborador
- Aplica advertências automaticamente (verbal → escrita → suspensão → demissão)

**Visualização:**
- Acesse `ocorrencias_advertencias.php`
- Veja estatísticas por colaborador
- Histórico completo de advertências

**Benefícios:**
- Automação completa
- Gestão disciplinar consistente
- Histórico detalhado

---

### 7. ✅ Campos Condicionais Avançados
**Descrição:** Campos que aparecem baseados em outros campos ou tipo selecionado.

**Exemplo:**
- Se tipo de ponto = "entrada", mostra campo "horário esperado"
- Campos dinâmicos podem ter condições de exibição

**Benefícios:**
- Formulários mais inteligentes
- Menos campos desnecessários
- Melhor UX

---

### 8. ✅ Validações Customizadas por Tipo
**Descrição:** Regras de validação específicas por tipo de ocorrência.

**Exemplos:**
- Atraso máximo de 2 horas
- Não permitir datas futuras
- Validações em JSON no tipo de ocorrência

**Benefícios:**
- Dados mais consistentes
- Validações específicas por contexto
- Menos erros de digitação

---

### 9. ✅ Histórico e Auditoria Completo
**Descrição:** Rastreamento de todas as alterações em ocorrências.

**O que é registrado:**
- Criação
- Edições (campo alterado, valor anterior, novo valor)
- Aprovações/Rejeições
- Comentários

**Visualização:**
- Na página de detalhes da ocorrência
- Histórico completo com usuário e data

**Benefícios:**
- Transparência total
- Compliance
- Rastreabilidade

---

### 10. ✅ Sistema de Comentários/Respostas
**Descrição:** Colaboradores podem se defender, gestores podem responder.

**Tipos de comentários:**
- Comentário normal
- Resposta
- Defesa do colaborador

**Como usar:**
- Na página de detalhes da ocorrência
- Adicione comentários
- Colaboradores podem marcar como "defesa"

**Benefícios:**
- Comunicação bidirecional
- Direito de defesa
- Melhor gestão de conflitos

---

### 11. ✅ Cálculos Automáticos
**Descrição:** Descontos automáticos baseados em atrasos ou valores fixos.

**Como funciona:**
- Configure tipo para "Calcula desconto"
- Defina valor fixo ou deixe calcular por tempo de atraso
- Cálculo proporcional ao salário

**Benefícios:**
- Automação de processos
- Cálculos precisos
- Economia de tempo

---

### 12. ✅ Tags e Categorização Múltipla
**Descrição:** Múltiplas tags por ocorrência além da categoria principal.

**Tags padrão:**
- Urgente
- Reincidente
- Primeira-vez
- Documentado
- Resolvido
- Pendente ação

**Como usar:**
- Ao criar ocorrência, selecione tags
- Filtre por tags na lista
- Visualize tags na página de detalhes

**Benefícios:**
- Melhor organização
- Filtros avançados
- Categorização flexível

---

### 13. ✅ Templates de Descrição
**Descrição:** Templates pré-definidos de descrição por tipo.

**Como usar:**
- Configure template no tipo de ocorrência
- Ao criar ocorrência, clique em "Usar Template"
- Variáveis disponíveis: {colaborador}, {data}, {hora}

**Benefícios:**
- Agilidade
- Padronização
- Menos erros

---

### 14. ✅ Dashboard e Analytics
**Descrição:** Gráficos, estatísticas e relatórios completos.

**Acesse:** `relatorio_ocorrencias_avancado.php`

**Recursos:**
- Cards com estatísticas gerais
- Gráfico de severidade (pizza)
- Gráfico de categorias (barras)
- Gráfico temporal (linha)
- Top 10 tipos de ocorrências
- Top 10 colaboradores com mais ocorrências
- Filtros por período

**Benefícios:**
- Insights visuais
- Tomada de decisão baseada em dados
- Identificação de padrões

---

## 📁 Estrutura de Arquivos

### Novos Arquivos Criados:
- `migracao_ocorrencias_completo.sql` - Migração completa do banco
- `includes/ocorrencias_functions.php` - Funções auxiliares
- `pages/ocorrencia_view.php` - Visualização detalhada
- `pages/ocorrencias_approve.php` - Aprovação/rejeição
- `pages/ocorrencias_advertencias.php` - Advertências progressivas
- `pages/relatorio_ocorrencias_avancado.php` - Dashboard/Analytics
- `api/ocorrencias/get_campos_dinamicos.php` - API campos dinâmicos
- `api/ocorrencias/get_templates_descricao.php` - API templates

### Arquivos Atualizados:
- `pages/tipos_ocorrencias.php` - Gerenciamento completo de tipos
- `pages/ocorrencias_add.php` - Formulário completo
- `pages/ocorrencias_list.php` - Lista com novos filtros
- `includes/email_templates.php` - Emails com novos campos

---

## 🗄️ Estrutura do Banco de Dados

### Novas Tabelas:
1. `tipos_ocorrencias_campos` - Campos dinâmicos
2. `ocorrencias_anexos` - Anexos de ocorrências
3. `ocorrencias_comentarios` - Comentários
4. `ocorrencias_historico` - Histórico/auditoria
5. `ocorrencias_advertencias` - Advertências progressivas
6. `ocorrencias_regras_advertencias` - Regras de advertências
7. `ocorrencias_tags` - Tags disponíveis
8. `ocorrencias_templates_descricao` - Templates de descrição
9. `ocorrencias_notificacoes` - Notificações

### Campos Adicionados:
- `tipos_ocorrencias`: severidade, requer_aprovacao, conta_advertencia, calcula_desconto, valor_desconto, template_descricao, validacoes_customizadas, notificar_*
- `ocorrencias`: severidade, status_aprovacao, aprovado_por, data_aprovacao, observacao_aprovacao, valor_desconto, tags, campos_dinamicos

---

## 🔧 Como Usar

### 1. Executar Migração
```sql
-- Execute o arquivo migracao_ocorrencias_completo.sql no seu banco de dados
```

### 2. Configurar Tipos de Ocorrências
1. Acesse `tipos_ocorrencias.php`
2. Crie/edite tipos conforme necessário
3. Configure severidade, aprovação, campos dinâmicos, etc.

### 3. Registrar Ocorrências
1. Acesse `ocorrencias_add.php`
2. Selecione colaborador e tipo
3. Preencha campos (incluindo dinâmicos se houver)
4. Anexe documentos se necessário
5. Adicione tags
6. Salve

### 4. Aprovar Ocorrências (se necessário)
1. Acesse `ocorrencias_list.php`
2. Filtre por "Pendente"
3. Clique em "Ver Detalhes"
4. Aprove ou rejeite

### 5. Visualizar Dashboard
1. Acesse `relatorio_ocorrencias_avancado.php`
2. Selecione período
3. Analise gráficos e estatísticas

---

## 📊 Permissões

- **ADMIN**: Acesso total
- **RH**: Todas as funcionalidades exceto algumas configurações avançadas
- **GESTOR**: Pode criar ocorrências, ver do seu setor, comentar
- **COLABORADOR**: Pode ver próprias ocorrências, comentar, se defender

---

## 🎨 Recursos Visuais

- Badges coloridos por severidade
- Badges por categoria
- Badges por status de aprovação
- Gráficos interativos (Chart.js)
- Tabelas com DataTables
- Modais para ações rápidas

---

## 🔔 Notificações

O sistema envia **3 tipos de notificações** automáticas:

### 1. Notificações no Sistema
- Aparecem no campo de notificações do dashboard
- Armazenadas na tabela `notificacoes_sistema`
- Visíveis apenas quando usuário está logado

### 2. Emails
- Enviados via SMTP configurado
- Usam templates de email (`email_templates`)
- Incluem todos os detalhes da ocorrência

### 3. Push Notifications (OneSignal)
- Enviadas para dispositivos móveis/desktop
- Funcionam mesmo com sistema fechado
- Requer OneSignal configurado e usuário com subscription ativa

### Destinatários (configurável por tipo):
- **Colaborador** - Se `notificar_colaborador = true`
- **Gestor do Setor** - Se `notificar_gestor = true`
- **RH da Empresa** - Se `notificar_rh = true`

### Configuração:
1. Acesse `tipos_ocorrencias.php`
2. Edite o tipo desejado
3. Na aba "Notificações", marque/desmarque os checkboxes
4. Salve

**Nota:** Push notifications requerem OneSignal configurado em `configuracoes_onesignal.php`

---

## 📝 Próximos Passos Sugeridos

1. Testar todas as funcionalidades
2. Configurar tipos de ocorrências específicos da empresa
3. Criar templates de descrição personalizados
4. Configurar regras de advertências progressivas
5. Treinar usuários nas novas funcionalidades

---

## 🐛 Troubleshooting

### Campos dinâmicos não aparecem
- Verifique se o tipo de ocorrência tem campos configurados
- Verifique se os campos estão ativos

### Anexos não fazem upload
- Verifique permissões da pasta `uploads/ocorrencias/`
- Verifique tamanho máximo do arquivo (10MB)

### Notificações não são enviadas
- Verifique configurações de email
- Verifique se o tipo está configurado para notificar

### Advertências não são aplicadas
- Verifique se o tipo está marcado como "conta advertência"
- Verifique regras em `ocorrencias_regras_advertencias`

---

## 📞 Suporte

Para dúvidas ou problemas, consulte:
- Este documento
- Código comentado
- Logs do sistema

---

**Versão:** 2.0  
**Data:** <?= date('d/m/Y') ?>  
**Status:** ✅ Implementação Completa

