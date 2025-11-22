# 📊 Análise: Painel de Engajamento

## 🎯 Objetivo
Criar um painel completo de engajamento que calcula, em tempo real, o nível de engajamento da empresa com relação a feedbacks, celebrações, humores, pesquisas e evolução de objetivos.

---

## ✅ O QUE JÁ TEMOS NO SISTEMA

### 1. **Estrutura Base** ✅
- ✅ Empresas (`empresas`)
- ✅ Setores (`setores`)
- ✅ Colaboradores (`colaboradores`)
- ✅ Hierarquia com líderes (`lider_id` em colaboradores)
- ✅ Usuários com `ultimo_login` (mas não histórico completo de acessos)

### 2. **Funcionalidades Existentes** ✅
- ✅ **Feedbacks** - Sistema completo (`feedbacks`, `feedback_avaliacoes`, `feedback_respostas`)
- ✅ **Humores/Emoções** - Sistema completo (`emocoes` - registro diário de emoções)
- ⚠️ **Celebrações** - Parcialmente existe (`feed_posts` com `tipo = 'celebração'`, mas não é um sistema dedicado)
- ✅ **Feed Social** - Sistema completo (`feed_posts`, `feed_curtidas`, `feed_comentarios`)

### 3. **Sistema de Pontuação** ✅
- ✅ Histórico de ações (`pontos_historico`)
- ✅ Configuração de pontos (`pontos_config`)
- ✅ Total de pontos (`pontos_total`)

---

## ❌ O QUE PRECISA SER IMPLEMENTADO

### 1. **Sistema de Reuniões 1:1** ❌ NOVO
**Descrição:** Reuniões individuais entre líderes e liderados.

**O que precisa:**
- Tabela `reunioes_1on1` com campos:
  - `id`, `lider_id` (colaborador), `liderado_id` (colaborador)
  - `data_reuniao`, `hora_inicio`, `hora_fim`
  - `status` (agendada, realizada, cancelada, reagendada)
  - `assuntos_tratados` (TEXT)
  - `proximos_passos` (TEXT)
  - `avaliacao_liderado` (1-5)
  - `avaliacao_lider` (1-5)
  - `created_at`, `updated_at`

**Funcionalidades necessárias:**
- Agendar reunião 1:1
- Marcar como realizada
- Avaliar reunião
- Listar reuniões por líder/liderado
- Calcular eficiência: % de liderados que receberam pelo menos 1 reunião no período

---

### 2. **Sistema de Celebrações Dedicado** ⚠️ MELHORAR
**Descrição:** Sistema específico para celebrações (reconhecimentos, aniversários, promoções, conquistas).

**O que precisa:**
- Criar tabela `celebracoes` separada do feed:
  - `id`, `remetente_id` (colaborador), `destinatario_id` (colaborador)
  - `tipo` (aniversario, promocao, conquista, reconhecimento, outro)
  - `titulo`, `descricao`, `imagem`
  - `data_celebração`
  - `status` (ativo, oculto)
  - `created_at`, `updated_at`

**Funcionalidades necessárias:**
- Criar celebração
- Listar celebrações
- Calcular eficiência: % de colaboradores que receberam pelo menos 1 celebração no período

**Nota:** Atualmente existe `feed_posts` com `tipo = 'celebração'`, mas seria melhor ter uma tabela dedicada para métricas mais precisas.

---

### 3. **Sistema de Pesquisas** ❌ NOVO
**Descrição:** Pesquisas de satisfação e pesquisas rápidas.

#### 3.1. Pesquisas de Satisfação
**O que precisa:**
- Tabela `pesquisas_satisfacao`:
  - `id`, `titulo`, `descricao`
  - `tipo` (satisfacao, clima, outro)
  - `data_inicio`, `data_fim`
  - `publico_alvo` (todos, empresa, setor, especifico)
  - `empresa_id`, `setor_id`
  - `participantes_ids` (JSON com IDs específicos)
  - `status` (rascunho, ativa, finalizada, cancelada)
  - `created_by` (usuario_id)
  - `created_at`, `updated_at`

- Tabela `pesquisas_satisfacao_perguntas`:
  - `id`, `pesquisa_id`
  - `pergunta` (TEXT)
  - `tipo` (texto, multipla_escolha, escala_1_5, escala_1_10)
  - `opcoes` (JSON para múltipla escolha)
  - `ordem`

- Tabela `pesquisas_satisfacao_respostas`:
  - `id`, `pesquisa_id`, `pergunta_id`
  - `colaborador_id`
  - `resposta` (TEXT ou JSON)
  - `created_at`

**Funcionalidades necessárias:**
- Criar pesquisa
- Enviar pesquisa para colaboradores
- Responder pesquisa
- Visualizar resultados
- Calcular eficiência: % de colaboradores que responderam

#### 3.2. Pesquisas Rápidas
**O que precisa:**
- Tabela `pesquisas_rapidas`:
  - `id`, `titulo`, `pergunta`
  - `tipo_resposta` (sim_nao, multipla_escolha, texto_curto)
  - `opcoes` (JSON)
  - `data_inicio`, `data_fim`
  - `publico_alvo` (todos, empresa, setor, especifico)
  - `empresa_id`, `setor_id`
  - `participantes_ids` (JSON)
  - `status` (ativa, finalizada)
  - `created_by` (usuario_id)
  - `created_at`, `updated_at`

- Tabela `pesquisas_rapidas_respostas`:
  - `id`, `pesquisa_id`
  - `colaborador_id`
  - `resposta` (TEXT ou JSON)
  - `created_at`

**Funcionalidades necessárias:**
- Criar pesquisa rápida
- Enviar para colaboradores
- Responder
- Visualizar resultados em tempo real
- Calcular eficiência: % de colaboradores que responderam

---

### 4. **Sistema de PDI (Plano de Desenvolvimento Individual)** ❌ NOVO
**Descrição:** Planos de desenvolvimento para colaboradores.

**O que precisa:**
- Tabela `pdis` (Planos de Desenvolvimento Individual):
  - `id`, `colaborador_id`
  - `titulo`, `descricao`
  - `objetivo_geral` (TEXT)
  - `data_inicio`, `data_fim_prevista`, `data_fim_real`
  - `status` (rascunho, ativo, concluido, cancelado, pausado)
  - `criado_por` (usuario_id - geralmente RH ou gestor)
  - `created_at`, `updated_at`

- Tabela `pdi_objetivos`:
  - `id`, `pdi_id`
  - `objetivo` (TEXT)
  - `prazo`
  - `status` (pendente, em_andamento, concluido, cancelado)
  - `data_conclusao`
  - `observacoes`

- Tabela `pdi_acoes`:
  - `id`, `pdi_id`, `objetivo_id`
  - `acao` (TEXT)
  - `prazo`
  - `status` (pendente, em_andamento, concluido)
  - `data_conclusao`
  - `evidencia` (TEXT ou caminho de arquivo)

**Funcionalidades necessárias:**
- Criar PDI para colaborador
- Adicionar objetivos e ações
- Acompanhar evolução
- Marcar objetivos/ações como concluídos
- Calcular eficiência: % de colaboradores com PDI ativo

---

### 5. **Sistema de Histórico de Acessos** ⚠️ MELHORAR
**Descrição:** Rastrear todos os acessos dos colaboradores (não apenas último login).

**O que precisa:**
- Criar tabela `acessos_historico`:
  - `id`, `usuario_id`, `colaborador_id`
  - `data_acesso` (DATE)
  - `hora_acesso` (TIME)
  - `ip_address`
  - `user_agent`
  - `created_at`

**Funcionalidades necessárias:**
- Registrar acesso a cada login
- Calcular % de colaboradores que acessaram no período
- Histórico mensal de acessos
- Gráfico de engajamento ao longo do tempo

**Nota:** Atualmente só temos `ultimo_login` em `usuarios`, mas precisamos de histórico completo.

---

### 6. **Painel de Engajamento** ❌ NOVO
**Descrição:** Página principal com todas as métricas.

**O que precisa:**

#### 6.1. Filtros
- ✅ Unidade (empresa) - Já existe
- ✅ Departamento (setor) - Já existe
- ✅ Liderados de (lider) - Já existe
- ❌ Data inicial/final - Implementar datepicker
- ✅ Status colaboradores - Já existe

#### 6.2. Seção "Eficiência"
- Feedbacks: % de colaboradores que receberam pelo menos 1 feedback
- 1:1: % de colaboradores que receberam pelo menos 1 reunião 1:1
- Celebrações: % de colaboradores que receberam pelo menos 1 celebração
- Desenvolvimento: % de colaboradores com PDI ativo

#### 6.3. Cards de Dados
- Humores Respondidos (total e variação %)
- Celebrações (total e variação %)
- Feedbacks (total e variação %)
- Engajados (% de colaboradores que acessaram)

#### 6.4. Engajamento por Módulo
- Barras de progresso para cada métrica:
  - Acessos
  - Feedbacks
  - Celebrações
  - Reuniões 1:1
  - Humores Respondidos
  - Pesquisa de Satisfação
  - Pesquisa Rápida
  - PDI

#### 6.5. Gráfico de Histórico
- Gráfico de linha mostrando evolução mensal do engajamento
- Período configurável (ex: últimos 12 meses)

#### 6.6. Tabela "Engajamento por Líder"
- Lista todos os líderes
- Mostra:
  - Nome
  - Departamento
  - Total de liderados
  - Liderados que acessaram (no período)
  - Porcentagem de engajamento
  - Liderados que nunca acessaram
  - Porcentagem de não engajados

---

## 📋 RESUMO DO QUE PRECISA SER CRIADO

### Tabelas Novas:
1. ❌ `reunioes_1on1` - Reuniões individuais
2. ❌ `celebracoes` - Celebrações dedicadas (ou melhorar feed_posts)
3. ❌ `pesquisas_satisfacao` - Pesquisas de satisfação
4. ❌ `pesquisas_satisfacao_perguntas` - Perguntas das pesquisas
5. ❌ `pesquisas_satisfacao_respostas` - Respostas das pesquisas
6. ❌ `pesquisas_rapidas` - Pesquisas rápidas
7. ❌ `pesquisas_rapidas_respostas` - Respostas das pesquisas rápidas
8. ❌ `pdis` - Planos de Desenvolvimento Individual
9. ❌ `pdi_objetivos` - Objetivos dos PDIs
10. ❌ `pdi_acoes` - Ações dos PDIs
11. ❌ `acessos_historico` - Histórico completo de acessos

### Páginas/APIs Novas:
1. ❌ `pages/gestao_engajamento.php` - Página principal do painel
2. ❌ `api/engajamento/dados.php` - API para buscar dados do painel
3. ❌ `pages/reunioes_1on1.php` - Gerenciar reuniões 1:1
4. ❌ `api/reunioes_1on1/criar.php` - Criar reunião
5. ❌ `api/reunioes_1on1/listar.php` - Listar reuniões
6. ❌ `pages/celebracoes.php` - Gerenciar celebrações
7. ❌ `api/celebracoes/criar.php` - Criar celebração
8. ❌ `pages/pesquisas_satisfacao.php` - Gerenciar pesquisas
9. ❌ `api/pesquisas/criar.php` - Criar pesquisa
10. ❌ `api/pesquisas/responder.php` - Responder pesquisa
11. ❌ `pages/pesquisas_rapidas.php` - Gerenciar pesquisas rápidas
12. ❌ `pages/pdis.php` - Gerenciar PDIs
13. ❌ `api/pdis/criar.php` - Criar PDI

### Melhorias:
1. ⚠️ Registrar acessos em `acessos_historico` a cada login
2. ⚠️ Melhorar sistema de celebrações (separar do feed ou criar métricas específicas)

---

## 🎨 ESTRUTURA DO MENU

```
Gestão
  └── Engajamento
      ├── Painel de Engajamento (página principal)
      ├── Reuniões 1:1
      ├── Celebrações
      ├── Pesquisas de Satisfação
      ├── Pesquisas Rápidas
      └── PDIs
```

---

## 📊 MÉTRICAS E CÁLCULOS

### Eficiência de Feedbacks:
```
(colaboradores que receberam pelo menos 1 feedback no período / total de colaboradores) * 100
```

### Eficiência de 1:1:
```
(colaboradores que receberam pelo menos 1 reunião 1:1 no período / total de colaboradores) * 100
```

### Eficiência de Celebrações:
```
(colaboradores que receberam pelo menos 1 celebração no período / total de colaboradores) * 100
```

### Eficiência de Desenvolvimento:
```
(colaboradores com PDI ativo / total de colaboradores) * 100
```

### Engajamento (Acessos):
```
(colaboradores que acessaram pelo menos 1 vez no período / total de colaboradores) * 100
```

### Engajamento por Módulo:
- **Acessos:** (colaboradores que acessaram / total) * 100
- **Feedbacks:** (colaboradores que enviaram pelo menos 1 feedback / total) * 100
- **Celebrações:** (colaboradores que enviaram pelo menos 1 celebração / total) * 100
- **Reuniões 1:1:** (colaboradores que receberam pelo menos 1 reunião / total) * 100
- **Humores:** (total de humores respondidos / (total colaboradores * dias úteis no período)) * 100
- **Pesquisa Satisfação:** (colaboradores que responderam / colaboradores que receberam) * 100
- **Pesquisa Rápida:** (colaboradores que responderam / colaboradores que receberam) * 100
- **PDI:** (colaboradores com PDI ativo / total) * 100

---

## 🚀 PRÓXIMOS PASSOS

1. **Criar menu "Gestão > Engajamento"** no `includes/menu.php`
2. **Criar migrações SQL** para todas as tabelas novas
3. **Implementar sistema de Reuniões 1:1** (tabelas + APIs + páginas)
4. **Implementar sistema de Celebrações** (melhorar ou criar novo)
5. **Implementar sistema de Pesquisas** (satisfação + rápidas)
6. **Implementar sistema de PDI**
7. **Implementar histórico de acessos**
8. **Criar página principal do Painel de Engajamento**
9. **Criar API para buscar dados do painel**
10. **Implementar gráficos e visualizações**

---

## ⚠️ OBSERVAÇÕES IMPORTANTES

1. **Performance:** O painel vai fazer muitas consultas. Considere usar cache ou views materializadas para métricas que não precisam ser em tempo real.

2. **Permissões:** Definir quem pode ver o painel:
   - ADMIN: Vê tudo
   - RH: Vê empresas/setores que tem acesso
   - GESTOR: Vê apenas seu setor/liderados
   - COLABORADOR: Não acessa (ou vê apenas seus próprios dados)

3. **Filtros:** Todos os cálculos devem respeitar os filtros selecionados (empresa, setor, líder, período, status).

4. **Variação %:** Para calcular variação, precisa comparar com período anterior (ex: mês atual vs mês anterior).

5. **Gráfico:** Usar biblioteca JavaScript (ex: Chart.js, ApexCharts) para gráfico de histórico.

---

## ✅ CONCLUSÃO

O sistema já tem uma boa base com **feedbacks**, **emoções** e **feed social**. 

**Principais gaps:**
- ❌ Reuniões 1:1 (sistema completo)
- ❌ Pesquisas (satisfação + rápidas)
- ❌ PDI (Planos de Desenvolvimento)
- ⚠️ Histórico de acessos completo
- ⚠️ Sistema de celebrações dedicado

**Estimativa:** ~15-20 tabelas novas, ~20-25 APIs novas, ~10-12 páginas novas.

Posso começar a implementação quando você aprovar este plano! 🚀

