# 📋 Como Funciona o Sistema RH Privus

## 🎯 Visão Geral

O **RH Privus** é um sistema completo de gestão de recursos humanos desenvolvido em PHP com MySQL, utilizando o tema Metronic para a interface. O sistema oferece funcionalidades abrangentes para gestão de pessoas, recrutamento, engajamento, ocorrências e muito mais.

---

## 🏗️ Arquitetura do Sistema

### **Stack Tecnológica**
- **Backend**: PHP 8.0+ (PDO para banco de dados)
- **Banco de Dados**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla + jQuery)
- **Framework UI**: Metronic Theme (Bootstrap-based)
- **Autenticação**: Sessões PHP (`$_SESSION`)
- **APIs**: Endpoints JSON RESTful na pasta `/api/`
- **Notificações Push**: OneSignal integrado
- **Email**: PHPMailer
- **PDF**: TCPDF
- **App Mobile**: Capacitor (suporte Android/iOS)

### **Estrutura de Diretórios**
```
rh-privus/
├── api/                    # APIs REST JSON
├── assets/                 # CSS, JS, imagens
├── config/                 # Configurações (DB, email)
├── includes/               # Funções auxiliares e componentes
├── pages/                  # Páginas principais do sistema
├── cron/                   # Scripts agendados
├── uploads/                # Arquivos enviados
├── vendor/                 # Dependências Composer
├── index.php              # Ponto de entrada (redireciona)
├── login.php              # Autenticação
└── migracao_*.sql         # Scripts de migração do banco
```

---

## 🔐 Sistema de Autenticação e Permissões

### **Perfis de Usuário**
O sistema possui 4 níveis de acesso:

1. **ADMIN** - Acesso total ao sistema
2. **RH** - Gestão de recursos humanos (pode ter acesso a múltiplas empresas)
3. **GESTOR** - Gestão de equipe (acesso limitado ao seu setor)
4. **COLABORADOR** - Acesso apenas às próprias informações

### **Controle de Acesso**
- Permissões granulares por página/funcionalidade
- Controle por empresa (usuários podem ter acesso a múltiplas empresas)
- Controle por setor (gestores veem apenas seu setor)
- Sistema de permissões configurável em `config/permissions.json`

### **Fluxo de Autenticação**
1. Usuário acessa `login.php`
2. Sistema valida credenciais no banco (`usuarios`)
3. Busca empresas associadas (`usuarios_empresas`)
4. Cria sessão com dados do usuário
5. Redireciona para `dashboard.php`

---

## 📊 Módulos Principais

### **1. Dashboard**
- **Arquivo**: `pages/dashboard.php`
- **Funcionalidades**:
  - Visão geral do sistema
  - Gráficos e métricas
  - Dashboard personalizado por perfil
  - Colaboradores veem suas próprias informações

### **2. Gestão de Colaboradores**
- **Arquivos**: `pages/colaboradores.php`, `colaborador_add.php`, `colaborador_edit.php`
- **Funcionalidades**:
  - Cadastro completo de colaboradores
  - Vinculação com empresa, setor e cargo
  - Hierarquia organizacional (liderança)
  - Histórico completo
  - Upload de fotos e documentos

### **3. Recrutamento e Seleção** 🎯
- **Arquivos**: `pages/vagas.php`, `kanban_selecao.php`, `candidaturas.php`
- **Funcionalidades**:
  - **Gestão de Vagas**: Cadastro completo com requisitos, benefícios, salários
  - **Portal Público**: `portal_vagas.php` - candidatos se candidatam sem login
  - **Landing Pages**: Páginas customizáveis por vaga (`vaga_landing_page.php`)
  - **Kanban de Seleção**: Visualização e gestão de candidaturas
  - **Etapas Configuráveis**: Jornada personalizada por vaga
  - **Formulários de Cultura**: Avaliação de alinhamento cultural
  - **Entrevistas**: Agendamento e avaliação
  - **Onboarding**: Processo com Kanban após aprovação
  - **Automações**: Ações automáticas por etapa/coluna
  - **Acompanhamento**: Candidatos acompanham via token único (`acompanhar.php`)

### **4. Engajamento**
- **Arquivos**: `pages/gestao_engajamento.php`, `reunioes_1on1.php`, `celebracoes.php`
- **Funcionalidades**:
  - **Reuniões 1:1**: Agendamento e acompanhamento entre líder e liderado
  - **Celebrações**: Datas comemorativas e eventos
  - **Pesquisas de Satisfação**: Pesquisas completas com campos dinâmicos
  - **Pesquisas Rápidas**: Pesquisas simples e diretas
  - **PDIs**: Planos de Desenvolvimento Individual
  - **Feed**: Rede social interna (`pages/feed.php`)
  - **Emoções**: Registro de sentimentos dos colaboradores

### **5. Ocorrências**
- **Arquivos**: `pages/ocorrencias_list.php`, `ocorrencias_add.php`
- **Funcionalidades**:
  - Registro de ocorrências (advertências, elogios, etc.)
  - Workflow de aprovação
  - Histórico completo
  - Anexos e comentários
  - Campos dinâmicos por tipo de ocorrência
  - Relatórios avançados

### **6. Feedbacks**
- **Arquivos**: `pages/feedback_enviar.php`, `feedback_meus.php`
- **Funcionalidades**:
  - Envio de feedbacks entre colaboradores
  - Respostas e conversas
  - Notificações por email e push

### **7. Pagamentos e Benefícios**
- **Arquivos**: `pages/fechamento_pagamentos.php`, `meus_pagamentos.php`
- **Funcionalidades**:
  - Gestão de salários e pagamentos
  - Documentos de pagamento
  - Bônus e benefícios
  - Histórico financeiro

### **8. Anotações**
- **Arquivos**: `pages/anotacoes.php` (via API)
- **Funcionalidades**:
  - Anotações do sistema
  - Notificações agendadas
  - Público-alvo configurável
  - Histórico e rastreamento

### **9. Endomarketing**
- **Arquivos**: `pages/endomarketing_datas_comemorativas.php`, `endomarketing_acoes.php`
- **Funcionalidades**:
  - Datas comemorativas
  - Ações de endomarketing
  - Celebrações automáticas

### **10. Configurações**
- **Arquivos**: `pages/empresas.php`, `setores.php`, `cargos.php`, `usuarios.php`
- **Funcionalidades**:
  - Gestão de empresas
  - Gestão de setores
  - Gestão de cargos
  - Gestão de usuários
  - Configurações de email
  - Configurações de notificações push (OneSignal)
  - Templates de email

---

## 🔄 Fluxos Principais

### **Fluxo de Recrutamento**
1. RH cria uma vaga (`pages/vaga_add.php`)
2. Configura etapas do processo (`pages/etapas_processo.php`)
3. Publica no portal (`portal_vagas.php`)
4. Candidato se candidata (`formulario_candidatura.php`)
5. Sistema cria candidatura e primeira etapa automaticamente
6. RH visualiza no Kanban (`pages/kanban_selecao.php`)
7. Move candidatura entre colunas (etapas)
8. Automações executam ações (emails, notificações)
9. Ao aprovar, inicia onboarding (`pages/kanban_onboarding.php`)
10. Após onboarding, cria colaborador automaticamente

### **Fluxo de Ocorrências**
1. Usuário cria ocorrência (`pages/ocorrencias_add.php`)
2. Sistema valida campos dinâmicos
3. Envia para aprovação (se necessário)
4. Aprovador recebe notificação
5. Aprova/rejeita ocorrência
6. Sistema registra no histórico
7. Colaborador recebe notificação

### **Fluxo de Pesquisas**
1. RH cria pesquisa (`pages/pesquisas_satisfacao.php`)
2. Define campos dinâmicos
3. Seleciona público-alvo
4. Publica pesquisa
5. Sistema envia emails/push para colaboradores
6. Colaboradores respondem (`pages/responder_pesquisa.php`)
7. RH visualiza resultados e analytics

---

## 🗄️ Estrutura do Banco de Dados

### **Tabelas Principais**

#### **Core**
- `empresas` - Empresas do sistema
- `setores` - Setores das empresas
- `cargos` - Cargos disponíveis
- `colaboradores` - Dados dos colaboradores
- `usuarios` - Usuários do sistema
- `usuarios_empresas` - Relacionamento muitos-para-muitos usuários-empresas

#### **Recrutamento**
- `vagas` - Vagas de emprego
- `candidatos` - Candidatos externos
- `candidaturas` - Candidaturas às vagas
- `processo_seletivo_etapas` - Etapas configuráveis
- `candidaturas_etapas` - Progresso por etapa
- `entrevistas` - Agendamento de entrevistas
- `formularios_cultura` - Formulários de cultura
- `kanban_colunas` - Colunas do Kanban
- `kanban_automatizacoes` - Automações
- `onboarding` - Processo de onboarding
- `onboarding_tarefas` - Tarefas do onboarding

#### **Engajamento**
- `reunioes_1on1` - Reuniões individuais
- `celebracoes` - Celebrações
- `pesquisas_satisfacao` - Pesquisas completas
- `pesquisas_satisfacao_campos` - Campos dinâmicos
- `pesquisas_satisfacao_respostas` - Respostas
- `pesquisas_rapidas` - Pesquisas rápidas
- `pdis` - Planos de Desenvolvimento Individual
- `feed` - Posts do feed interno
- `emocoes` - Registro de emoções

#### **Ocorrências**
- `ocorrencias` - Ocorrências registradas
- `ocorrencias_historico` - Histórico de alterações
- `ocorrencias_comentarios` - Comentários
- `ocorrencias_anexos` - Anexos
- `tipos_ocorrencias` - Tipos de ocorrências
- `categorias_ocorrencias` - Categorias

#### **Outros**
- `notificacoes` - Notificações do sistema
- `email_templates` - Templates de email
- `anotacoes` - Anotações do sistema
- `documentos_pagamento` - Documentos financeiros
- `bonus_colaboradores` - Bônus
- `hierarquia` - Estrutura hierárquica

---

## 🔌 APIs REST

O sistema possui APIs RESTful organizadas por módulo:

### **Estrutura**
```
api/
├── recrutamento/
│   ├── vagas/
│   ├── candidaturas/
│   ├── kanban/
│   ├── entrevistas/
│   └── onboarding/
├── engajamento/
│   ├── dados.php
│   └── historico_mensal.php
├── feed/
│   ├── listar.php
│   ├── postar.php
│   └── comentar.php
├── feedback/
│   ├── enviar.php
│   └── listar.php
├── notificacoes/
│   ├── listar.php
│   └── marcar_lida.php
└── onesignal/
    ├── subscribe.php
    └── send.php
```

### **Formato de Resposta**
Todas as APIs retornam JSON:
```json
{
  "success": true,
  "data": {...},
  "message": "Operação realizada com sucesso"
}
```

---

## 📧 Sistema de Notificações

### **Tipos de Notificação**
1. **Email**: Via PHPMailer
   - Templates configuráveis
   - Variáveis dinâmicas (`{nome}`, `{vaga_titulo}`, etc.)
   - HTML e texto plano

2. **Push Notification**: Via OneSignal
   - Notificações no navegador
   - Notificações no app mobile
   - Agendamento de envios

3. **Notificações Internas**: No sistema
   - Badge de contagem
   - Lista de notificações
   - Marcação de lidas

### **Configuração**
- Templates em `email_templates`
- Configurações em `config/email.php`
- OneSignal em `config/onesignal.php`

---

## 🎨 Interface do Usuário

### **Tema Metronic**
- Design moderno e responsivo
- Componentes prontos (tabelas, formulários, gráficos)
- Suporte a dark mode
- Menu lateral colapsável

### **Componentes Principais**
- **Kanban**: Drag & drop para processos
- **Gráficos**: Chart.js para visualizações
- **Tabelas**: DataTables para listagens
- **Formulários**: Validação e campos dinâmicos
- **Modais**: Para ações rápidas

---

## 📱 Aplicativo Mobile

### **Capacitor**
- Sistema pode ser transformado em app nativo
- Suporte Android e iOS
- Acesso a recursos do dispositivo
- Notificações push nativas

### **PWA (Progressive Web App)**
- Instalável no navegador
- Funciona offline parcialmente
- Service Worker para cache
- Manifest.json para instalação

---

## 🔒 Segurança

### **Medidas Implementadas**
- Autenticação por sessão
- Hash de senhas (password_hash)
- Prepared statements (PDO)
- Validação de entrada
- Controle de acesso por perfil
- Sanitização de dados
- Proteção CSRF (em algumas áreas)

---

## 🚀 Funcionalidades Avançadas

### **1. Campos Dinâmicos**
- Formulários configuráveis
- Tipos variados (texto, número, data, seleção)
- Validação personalizada
- Usado em: Pesquisas, Ocorrências, Formulários de Cultura

### **2. Automações**
- Ações automáticas por evento
- Condições configuráveis
- Templates de email
- Usado em: Recrutamento (Kanban), Onboarding

### **3. Workflow**
- Aprovações em múltiplas etapas
- Histórico completo
- Notificações automáticas
- Usado em: Ocorrências, Documentos

### **4. Analytics**
- Gráficos e métricas
- Relatórios personalizados
- Exportação de dados
- Usado em: Dashboard, Recrutamento, Engajamento

---

## 📝 Scripts de Migração

O sistema possui vários scripts SQL para criar/atualizar tabelas:

- `migracao_recrutamento_selecao_completo.sql` - Sistema de recrutamento
- `migracao_engajamento_completo.sql` - Sistema de engajamento
- `migracao_anotacoes_sistema.sql` - Sistema de anotações
- `migracao_hierarquia.sql` - Sistema de hierarquia
- E muitos outros...

---

## 🔧 Configuração

### **Arquivos de Configuração**
- `config/db.php` - Conexão com banco de dados
- `config/email.php` - Configurações de email
- `config/permissions.json` - Permissões do sistema

### **Instalação**
1. Execute `install.php` para criar estrutura inicial
2. Execute scripts de migração SQL conforme necessário
3. Configure arquivos em `config/`
4. Configure OneSignal (se usar push)
5. Configure PHPMailer (se usar email)

---

## 📚 Documentação Adicional

O sistema possui vários arquivos de documentação:
- `PROPOSTA_SISTEMA_RECRUTAMENTO_SELECAO.md`
- `RESUMO_IMPLEMENTACAO_RECRUTAMENTO.md`
- `RESUMO_IMPLEMENTACAO_ENGAJAMENTO.md`
- `GUIA_NOTIFICACOES_PUSH.md`
- E muitos outros...

---

## 🎯 Conclusão

O **RH Privus** é um sistema completo e robusto para gestão de recursos humanos, com funcionalidades modernas como:
- ✅ Recrutamento completo com Kanban
- ✅ Engajamento de colaboradores
- ✅ Gestão de ocorrências
- ✅ Pesquisas e feedbacks
- ✅ Notificações push e email
- ✅ App mobile (via Capacitor)
- ✅ APIs RESTful
- ✅ Interface moderna e responsiva

O sistema é modular, extensível e bem documentado, facilitando manutenção e novas funcionalidades.

