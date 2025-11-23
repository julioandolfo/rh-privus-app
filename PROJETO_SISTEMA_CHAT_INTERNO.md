# 💬 Projeto: Sistema de Chat Interno RH Privus

## 📋 Sumário Executivo

Sistema completo de chat interno entre colaboradores e equipe de RH, com widget flutuante, notificações push, integração com ChatGPT para resumos automáticos, e funcionalidades rápidas integradas.

---

## 🎯 Objetivos

1. **Comunicação direta** entre colaboradores e RH
2. **Suporte a múltiplos atendentes** RH simultâneos
3. **Widget flutuante** para fácil acesso
4. **Notificações em tempo real** (push e sonoras)
5. **Gestão completa** de conversas pelo RH
6. **Integração com IA** para resumos automáticos
7. **Funcionalidades rápidas** (criar ocorrências, etc)

---

## 🗄️ Estrutura do Banco de Dados

### **1. Tabela: `chat_conversas`**
Armazena as conversas entre colaboradores e RH.

```sql
CREATE TABLE IF NOT EXISTS chat_conversas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL COMMENT 'Colaborador que iniciou a conversa',
    titulo VARCHAR(255) NULL COMMENT 'Título da conversa (gerado automaticamente ou manual)',
    status ENUM('aberta', 'em_atendimento', 'aguardando_resposta', 'fechada', 'arquivada') DEFAULT 'aberta',
    prioridade ENUM('baixa', 'normal', 'alta', 'urgente') DEFAULT 'normal',
    categoria VARCHAR(100) NULL COMMENT 'Categoria da conversa (ex: solicitação, dúvida, problema)',
    atribuido_para_usuario_id INT NULL COMMENT 'RH responsável pela conversa',
    ultima_mensagem_at TIMESTAMP NULL COMMENT 'Data/hora da última mensagem',
    ultima_mensagem_por ENUM('colaborador', 'rh') NULL COMMENT 'Quem enviou a última mensagem',
    colaborador_visualizou_at TIMESTAMP NULL COMMENT 'Última vez que colaborador visualizou',
    rh_visualizou_at TIMESTAMP NULL COMMENT 'Última vez que RH visualizou',
    total_mensagens INT DEFAULT 0 COMMENT 'Contador de mensagens',
    total_mensagens_nao_lidas_colaborador INT DEFAULT 0 COMMENT 'Mensagens não lidas pelo colaborador',
    total_mensagens_nao_lidas_rh INT DEFAULT 0 COMMENT 'Mensagens não lidas pelo RH',
    resumo_ia TEXT NULL COMMENT 'Resumo gerado pela IA',
    resumo_ia_gerado_at TIMESTAMP NULL COMMENT 'Data/hora do resumo gerado',
    tags JSON NULL COMMENT 'Tags para organização',
    metadata JSON NULL COMMENT 'Dados adicionais (ex: ocorrência criada, documentos anexados)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fechada_at TIMESTAMP NULL COMMENT 'Data/hora de fechamento',
    fechada_por INT NULL COMMENT 'Usuário que fechou',
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (atribuido_para_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (fechada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_status (status),
    INDEX idx_atribuido (atribuido_para_usuario_id),
    INDEX idx_prioridade (prioridade),
    INDEX idx_ultima_mensagem (ultima_mensagem_at),
    INDEX idx_abertas (status, ultima_mensagem_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **2. Tabela: `chat_mensagens`**
Armazena todas as mensagens do chat.

```sql
CREATE TABLE IF NOT EXISTS chat_mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    enviado_por_usuario_id INT NULL COMMENT 'Usuário RH que enviou (NULL se foi colaborador)',
    enviado_por_colaborador_id INT NULL COMMENT 'Colaborador que enviou (NULL se foi RH)',
    tipo ENUM('texto', 'anexo', 'sistema', 'acao_rapida') DEFAULT 'texto',
    mensagem TEXT NULL COMMENT 'Texto da mensagem',
    anexo_caminho VARCHAR(500) NULL COMMENT 'Caminho do arquivo anexado',
    anexo_nome_original VARCHAR(255) NULL COMMENT 'Nome original do arquivo',
    anexo_tipo_mime VARCHAR(100) NULL COMMENT 'Tipo MIME do arquivo',
    anexo_tamanho INT NULL COMMENT 'Tamanho em bytes',
    acao_rapida_tipo VARCHAR(50) NULL COMMENT 'Tipo de ação rápida (ex: ocorrencia_criada)',
    acao_rapida_dados JSON NULL COMMENT 'Dados da ação rápida',
    lida_por_colaborador BOOLEAN DEFAULT FALSE COMMENT 'Colaborador leu a mensagem',
    lida_por_rh BOOLEAN DEFAULT FALSE COMMENT 'RH leu a mensagem',
    lida_por_colaborador_at TIMESTAMP NULL,
    lida_por_rh_at TIMESTAMP NULL,
    editada BOOLEAN DEFAULT FALSE COMMENT 'Mensagem foi editada',
    editada_at TIMESTAMP NULL,
    deletada BOOLEAN DEFAULT FALSE COMMENT 'Mensagem foi deletada (soft delete)',
    deletada_at TIMESTAMP NULL,
    deletada_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversa_id) REFERENCES chat_conversas(id) ON DELETE CASCADE,
    FOREIGN KEY (enviado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (enviado_por_colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    FOREIGN KEY (deletada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_conversa (conversa_id),
    INDEX idx_enviado_por_usuario (enviado_por_usuario_id),
    INDEX idx_enviado_por_colaborador (enviado_por_colaborador_id),
    INDEX idx_tipo (tipo),
    INDEX idx_created_at (created_at),
    INDEX idx_nao_lidas_colaborador (conversa_id, lida_por_colaborador, created_at),
    INDEX idx_nao_lidas_rh (conversa_id, lida_por_rh, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **3. Tabela: `chat_participantes`**
Controla quais usuários RH estão participando de cada conversa.

```sql
CREATE TABLE IF NOT EXISTS chat_participantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    usuario_id INT NOT NULL COMMENT 'Usuário RH participando',
    adicionado_por INT NULL COMMENT 'Quem adicionou este participante',
    removido BOOLEAN DEFAULT FALSE COMMENT 'Participante foi removido',
    removido_at TIMESTAMP NULL,
    removido_por INT NULL,
    ultima_visualizacao TIMESTAMP NULL COMMENT 'Última vez que visualizou a conversa',
    notificacoes_ativas BOOLEAN DEFAULT TRUE COMMENT 'Recebe notificações desta conversa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversa_id) REFERENCES chat_conversas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (adicionado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (removido_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_conversa_usuario (conversa_id, usuario_id, removido),
    INDEX idx_conversa (conversa_id),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **4. Tabela: `chat_configuracoes`**
Configurações globais do sistema de chat.

```sql
CREATE TABLE IF NOT EXISTS chat_configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT NULL,
    tipo VARCHAR(50) DEFAULT 'string' COMMENT 'string, json, boolean, integer',
    descricao TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações padrão
INSERT INTO chat_configuracoes (chave, valor, tipo, descricao) VALUES
('chat_ativo', 'true', 'boolean', 'Sistema de chat está ativo'),
('horario_atendimento_inicio', '08:00', 'string', 'Horário de início do atendimento'),
('horario_atendimento_fim', '18:00', 'string', 'Horário de fim do atendimento'),
('mensagem_automatica_fora_horario', 'Olá! Estamos fora do horário de atendimento. Retornaremos em breve.', 'string', 'Mensagem automática fora do horário'),
('notificacoes_push_ativas', 'true', 'boolean', 'Notificações push estão ativas'),
('notificacoes_sonoras_ativas', 'true', 'boolean', 'Efeitos sonoros estão ativos'),
('tempo_auto_fechamento_dias', '30', 'integer', 'Dias para fechar conversas inativas automaticamente'),
('chatgpt_api_key', '', 'string', 'API Key do ChatGPT'),
('chatgpt_modelo', 'gpt-4', 'string', 'Modelo do ChatGPT a usar'),
('chatgpt_ativo', 'false', 'boolean', 'Integração com ChatGPT está ativa'),
('chatgpt_temperatura', '0.7', 'string', 'Temperatura do modelo ChatGPT'),
('chatgpt_max_tokens', '500', 'integer', 'Máximo de tokens para resumo');
```

### **5. Tabela: `chat_preferencias_usuario`**
Preferências individuais de cada usuário/colaborador.

```sql
CREATE TABLE IF NOT EXISTS chat_preferencias_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL COMMENT 'Usuário RH',
    colaborador_id INT NULL COMMENT 'Colaborador',
    notificacoes_push BOOLEAN DEFAULT TRUE COMMENT 'Recebe notificações push',
    notificacoes_email BOOLEAN DEFAULT TRUE COMMENT 'Recebe notificações por email',
    notificacoes_sonoras BOOLEAN DEFAULT TRUE COMMENT 'Efeitos sonoros ativos',
    som_notificacao VARCHAR(50) DEFAULT 'padrao' COMMENT 'Som escolhido',
    status_online BOOLEAN DEFAULT FALSE COMMENT 'Status online (para RH)',
    status_mensagem VARCHAR(255) NULL COMMENT 'Mensagem de status (para RH)',
    auto_resposta TEXT NULL COMMENT 'Mensagem de auto-resposta (para RH)',
    auto_resposta_ativa BOOLEAN DEFAULT FALSE COMMENT 'Auto-resposta está ativa',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    UNIQUE KEY uk_usuario (usuario_id),
    UNIQUE KEY uk_colaborador (colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **6. Tabela: `chat_resumos_ia`**
Histórico de resumos gerados pela IA.

```sql
CREATE TABLE IF NOT EXISTS chat_resumos_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    resumo TEXT NOT NULL COMMENT 'Resumo gerado pela IA',
    prompt_usado TEXT NULL COMMENT 'Prompt usado para gerar o resumo',
    modelo_usado VARCHAR(50) NULL COMMENT 'Modelo usado (ex: gpt-4)',
    tokens_usados INT NULL COMMENT 'Tokens consumidos',
    gerado_por_usuario_id INT NULL COMMENT 'Usuário que solicitou o resumo',
    salvo BOOLEAN DEFAULT FALSE COMMENT 'Resumo foi salvo na conversa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversa_id) REFERENCES chat_conversas(id) ON DELETE CASCADE,
    FOREIGN KEY (gerado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_conversa (conversa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🎨 Interface do Usuário

### **1. Widget Flutuante (Colaborador)**

**Localização**: Aparece em todas as páginas para colaboradores

**Características**:
- Botão flutuante fixo no canto inferior direito
- Badge com contador de conversas não lidas
- Animação quando há nova mensagem
- Abre painel lateral com lista de conversas
- Design moderno e responsivo

**Funcionalidades**:
- Ver conversas abertas
- Criar nova conversa
- Ver notificações de novas mensagens
- Acessar configurações (som, notificações)

### **2. Painel de Chat (Colaborador)**

**Quando widget é clicado**:
- Abre painel lateral deslizante
- Lista de conversas (abertas primeiro)
- Botão "Nova Conversa"
- Campo de busca

**Quando conversa é aberta**:
- Área de mensagens (scroll automático)
- Campo de texto para digitar
- Botão de anexar arquivo
- Indicador de digitação
- Status de leitura
- Timestamp das mensagens

### **3. Página de Gestão de Chat (RH)**

**Arquivo**: `pages/chat_gestao.php`

**Layout**:
- **Sidebar Esquerda**: Lista de conversas
  - Filtros (status, prioridade, atribuído)
  - Busca
  - Contadores (abertas, não lidas, etc)
- **Área Central**: Conversa aberta
  - Header com informações do colaborador
  - Área de mensagens
  - Campo de resposta
  - Ações rápidas (menu dropdown)
- **Sidebar Direita**: Informações e ações
  - Dados do colaborador
  - Histórico da conversa
  - Ações (atribuir, fechar, arquivar)
  - Resumo IA (se disponível)

**Funcionalidades**:
- Atribuir conversa para outro RH
- Adicionar participantes
- Fechar/abrir conversas
- Arquivar conversas
- Criar ocorrência a partir da conversa
- Gerar resumo com IA
- Enviar anexos
- Ver histórico completo

---

## 🔔 Sistema de Notificações

### **1. Notificações Push**

**Quando enviar**:
- Nova mensagem recebida
- Nova conversa criada
- Conversa atribuída para você
- Conversa fechada/arquivada

**Formato**:
```json
{
  "titulo": "Nova mensagem de João Silva",
  "mensagem": "Olá, preciso de ajuda com...",
  "url": "/rh-privus/pages/chat_gestao.php?conversa=123",
  "icone": "/rh-privus/assets/chat-icon.png",
  "badge": 5
}
```

**Implementação**:
- Usar sistema OneSignal existente
- Enviar para `colaborador_id` ou `usuario_id`
- Badge com contador de não lidas

### **2. Efeitos Sonoros**

**Sons disponíveis**:
- `padrao` - Som padrão de notificação
- `suave` - Som mais suave
- `urgente` - Som para prioridade alta
- `desligado` - Sem som

**Quando tocar**:
- Nova mensagem recebida (se chat aberto)
- Nova conversa criada
- Mensagem enviada com sucesso (opcional)

**Configuração**:
- Preferência por usuário
- Pode desativar globalmente
- Volume ajustável

### **3. Notificações por Email**

**Quando enviar**:
- Nova mensagem quando chat está fechado
- Conversa não respondida há X horas (configurável)
- Conversa fechada

**Template**:
- Assunto: "Nova mensagem no chat - [Nome do Colaborador]"
- Corpo: Preview da mensagem + link para abrir

---

## 🤖 Integração com ChatGPT

### **1. Configuração**

**Página**: `pages/chat_configuracoes.php`

**Campos**:
- API Key do OpenAI
- Modelo (gpt-4, gpt-3.5-turbo, etc)
- Temperatura (0.0 - 1.0)
- Máximo de tokens
- Ativar/desativar integração

### **2. Funcionalidades**

#### **A. Gerar Resumo da Conversa**

**Quando usar**:
- Botão "Gerar Resumo com IA" na conversa
- Automaticamente após fechar conversa (opcional)

**Prompt exemplo**:
```
Resuma a seguinte conversa entre colaborador e RH, destacando:
- Assunto principal
- Problemas ou solicitações mencionadas
- Soluções propostas
- Ações tomadas
- Status final

Conversa:
[MENSAGENS DA CONVERSA]
```

**Salvamento**:
- Salva em `chat_resumos_ia`
- Atualiza `chat_conversas.resumo_ia`
- Pode ser editado manualmente

#### **B. Sugestões de Resposta**

**Funcionalidade futura**:
- Sugerir respostas baseadas no contexto
- Botão "Sugerir Resposta" ao digitar

### **3. API Helper**

**Arquivo**: `includes/chatgpt_service.php`

```php
function gerar_resumo_conversa($conversa_id) {
    // Busca mensagens da conversa
    // Monta prompt
    // Chama API OpenAI
    // Salva resultado
    // Retorna resumo
}
```

---

## ⚡ Funcionalidades Rápidas

### **1. Criar Ocorrência**

**Como funciona**:
- Botão "Criar Ocorrência" no chat
- Abre modal com formulário rápido
- Pré-preenche dados do colaborador
- Permite adicionar contexto da conversa
- Cria ocorrência e envia link no chat

**Dados pré-preenchidos**:
- Colaborador (da conversa)
- Descrição (pode copiar mensagens)
- Data/hora atual

### **2. Outras Ações Rápidas (Futuras)**

- Criar PDI
- Agendar Reunião 1:1
- Enviar Feedback
- Criar Pesquisa

---

## 📡 APIs REST

### **1. Listar Conversas**

**Endpoint**: `api/chat/conversas/listar.php`

**Método**: GET

**Parâmetros**:
- `status` - Filtrar por status
- `atribuido_para` - Filtrar por atribuído
- `prioridade` - Filtrar por prioridade
- `busca` - Buscar por título/nome

**Resposta**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "colaborador": {
        "id": 10,
        "nome": "João Silva",
        "foto": "/uploads/fotos/10.jpg"
      },
      "titulo": "Dúvida sobre férias",
      "status": "aberta",
      "prioridade": "normal",
      "ultima_mensagem": "Olá, preciso de ajuda...",
      "ultima_mensagem_at": "2024-01-15 14:30:00",
      "total_nao_lidas": 2,
      "atribuido_para": {
        "id": 5,
        "nome": "Maria Santos"
      }
    }
  ],
  "total": 10
}
```

### **2. Criar Conversa**

**Endpoint**: `api/chat/conversas/criar.php`

**Método**: POST

**Body**:
```json
{
  "titulo": "Dúvida sobre férias",
  "categoria": "solicitacao",
  "mensagem": "Olá, preciso de ajuda..."
}
```

### **3. Enviar Mensagem**

**Endpoint**: `api/chat/mensagens/enviar.php`

**Método**: POST

**Body**:
```json
{
  "conversa_id": 1,
  "mensagem": "Olá, como posso ajudar?",
  "anexo": null
}
```

**Upload de anexo**: Multipart/form-data

### **4. Marcar como Lida**

**Endpoint**: `api/chat/mensagens/marcar_lida.php`

**Método**: POST

**Body**:
```json
{
  "conversa_id": 1,
  "mensagem_id": 5
}
```

### **5. Atribuir Conversa**

**Endpoint**: `api/chat/conversas/atribuir.php`

**Método**: POST

**Body**:
```json
{
  "conversa_id": 1,
  "usuario_id": 5
}
```

### **6. Fechar Conversa**

**Endpoint**: `api/chat/conversas/fechar.php`

**Método**: POST

**Body**:
```json
{
  "conversa_id": 1,
  "motivo": "Resolvido"
}
```

### **7. Gerar Resumo IA**

**Endpoint**: `api/chat/ia/gerar_resumo.php`

**Método**: POST

**Body**:
```json
{
  "conversa_id": 1
}
```

### **8. Buscar Mensagens**

**Endpoint**: `api/chat/mensagens/listar.php`

**Método**: GET

**Parâmetros**:
- `conversa_id` - ID da conversa
- `page` - Página (paginação)
- `limit` - Limite por página

### **9. Polling de Novas Mensagens**

**Endpoint**: `api/chat/mensagens/novas.php`

**Método**: GET

**Parâmetros**:
- `conversa_id` - ID da conversa
- `ultima_mensagem_id` - ID da última mensagem conhecida

**Resposta**:
```json
{
  "success": true,
  "novas_mensagens": [
    {
      "id": 10,
      "mensagem": "Nova mensagem",
      "created_at": "2024-01-15 15:00:00"
    }
  ],
  "total_nao_lidas": 3
}
```

### **10. Upload de Anexo**

**Endpoint**: `api/chat/anexos/upload.php`

**Método**: POST

**Formato**: Multipart/form-data

**Campos**:
- `conversa_id` - ID da conversa
- `arquivo` - Arquivo a enviar

---

## 🔄 Fluxos de Trabalho

### **Fluxo 1: Colaborador Inicia Conversa**

1. Colaborador clica no widget flutuante
2. Clica em "Nova Conversa"
3. Preenche título e primeira mensagem
4. Sistema cria `chat_conversas` com status `aberta`
5. Sistema cria primeira `chat_mensagens`
6. Sistema busca usuários RH disponíveis
7. Sistema envia notificação push para RHs
8. Sistema toca som (se ativado)
9. RH recebe notificação e pode abrir conversa

### **Fluxo 2: RH Responde**

1. RH abre conversa em `chat_gestao.php`
2. Status muda para `em_atendimento`
3. Se não atribuída, atribui para si mesmo
4. RH digita resposta
5. Clica em enviar
6. Sistema cria `chat_mensagens`
7. Sistema atualiza `chat_conversas.ultima_mensagem_at`
8. Sistema envia notificação push para colaborador
9. Sistema toca som para colaborador (se ativado)
10. Colaborador recebe notificação

### **Fluxo 3: Criar Ocorrência a Partir do Chat**

1. RH abre conversa
2. Clica em "Ações Rápidas" > "Criar Ocorrência"
3. Modal abre com formulário pré-preenchido
4. RH completa dados necessários
5. RH pode copiar contexto da conversa
6. Clica em "Criar"
7. Sistema cria ocorrência
8. Sistema envia mensagem automática no chat com link da ocorrência
9. Colaborador recebe notificação

### **Fluxo 4: Gerar Resumo com IA**

1. RH abre conversa
2. Clica em "Gerar Resumo com IA"
3. Sistema busca todas as mensagens da conversa
4. Sistema monta prompt para ChatGPT
5. Sistema chama API do OpenAI
6. Sistema recebe resumo
7. Sistema salva em `chat_resumos_ia`
8. Sistema atualiza `chat_conversas.resumo_ia`
9. Sistema exibe resumo na sidebar
10. RH pode editar/salvar resumo

### **Fluxo 5: Fechar Conversa**

1. RH decide fechar conversa
2. Clica em "Fechar Conversa"
3. Opcionalmente preenche motivo
4. Sistema atualiza status para `fechada`
5. Sistema atualiza `fechada_at` e `fechada_por`
6. Sistema envia notificação para colaborador
7. Colaborador ainda pode reabrir conversa (cria nova mensagem)

---

## 🎨 Design e UX

### **Widget Flutuante**

**Estilo**:
- Botão circular fixo no canto inferior direito
- Cor primária do sistema (#009ef7)
- Ícone de chat/mensagem
- Badge vermelho com contador
- Animação de pulso quando há nova mensagem
- Z-index alto para ficar sempre visível

**Estados**:
- Normal: Botão fechado
- Hover: Efeito de escala
- Aberto: Painel lateral desliza da direita
- Nova mensagem: Animação de pulso

### **Painel de Chat**

**Layout**:
- Largura: 400px (desktop), 100% (mobile)
- Altura: 600px (desktop), 100vh (mobile)
- Posição: Fixa no canto inferior direito
- Header: Título + botão fechar
- Lista de conversas: Scrollável
- Footer: Botão nova conversa

**Responsividade**:
- Mobile: Ocupa tela inteira
- Tablet: 50% da largura
- Desktop: 400px fixo

### **Página de Gestão (RH)**

**Layout**:
- 3 colunas:
  - Sidebar esquerda (300px): Lista de conversas
  - Área central (flex): Conversa aberta
  - Sidebar direita (350px): Informações e ações

**Cores**:
- Conversa aberta: Verde claro
- Conversa não lida: Amarelo claro
- Prioridade alta: Vermelho claro
- Prioridade urgente: Vermelho escuro

---

## 🔧 Implementação Técnica

### **1. Arquivos a Criar**

#### **Backend (PHP)**
```
includes/
├── chat_functions.php          # Funções auxiliares do chat
├── chatgpt_service.php          # Integração com ChatGPT
└── chat_notifications.php        # Notificações do chat

api/chat/
├── conversas/
│   ├── listar.php
│   ├── criar.php
│   ├── atribuir.php
│   ├── fechar.php
│   └── detalhes.php
├── mensagens/
│   ├── listar.php
│   ├── enviar.php
│   ├── marcar_lida.php
│   └── novas.php
├── anexos/
│   └── upload.php
├── ia/
│   └── gerar_resumo.php
└── preferencias/
    └── salvar.php

pages/
├── chat_gestao.php              # Página principal RH
└── chat_configuracoes.php        # Configurações do chat
```

#### **Frontend (JS/CSS)**
```
assets/
├── js/
│   ├── chat-widget.js           # Widget flutuante
│   ├── chat-painel.js            # Painel de chat
│   └── chat-gestao.js            # Gestão RH
├── css/
│   ├── chat-widget.css           # Estilos do widget
│   └── chat-gestao.css           # Estilos da gestão
└── sounds/
    ├── notification-default.mp3
    ├── notification-suave.mp3
    └── notification-urgente.mp3
```

### **2. Dependências**

**PHP**:
- cURL (para API ChatGPT)
- GD ou Imagick (para processar imagens)

**JavaScript**:
- jQuery (já existe no sistema)
- Socket.io ou Polling (para atualizações em tempo real)

**CSS**:
- Bootstrap (já existe - Metronic)

### **3. Integração com Sistema Existente**

**Permissões**:
- Adicionar em `config/permissions.json`:
  ```json
  {
    "chat_gestao.php": ["ADMIN", "RH"],
    "chat_configuracoes.php": ["ADMIN", "RH"]
  }
  ```

**Menu**:
- Adicionar item no menu para RH:
  - "Chat" > "Gestão de Conversas"
  - "Chat" > "Configurações"

**Notificações**:
- Usar `onesignal_send_notification()` existente
- Usar `enviar_email()` existente

---

## 📊 Métricas e Analytics

### **Métricas para Dashboard**

- Total de conversas abertas
- Tempo médio de resposta
- Conversas não respondidas há mais de X horas
- Taxa de resolução
- Conversas por categoria
- Conversas por prioridade
- Horários de pico

### **Relatórios**

- Relatório de atendimento por RH
- Relatório de conversas por período
- Relatório de tempo de resposta
- Relatório de satisfação (futuro)

---

## 🚀 Fases de Implementação

### **Fase 1: Estrutura Base** (Semana 1-2)
- ✅ Criar tabelas do banco de dados
- ✅ Criar APIs básicas (listar, criar, enviar mensagem)
- ✅ Criar widget flutuante básico
- ✅ Criar página de gestão básica

### **Fase 2: Funcionalidades Core** (Semana 3-4)
- ✅ Sistema de notificações push
- ✅ Efeitos sonoros
- ✅ Upload de anexos
- ✅ Marcar como lida
- ✅ Atribuir conversas

### **Fase 3: Funcionalidades Avançadas** (Semana 5-6)
- ✅ Integração com ChatGPT
- ✅ Criar ocorrência a partir do chat
- ✅ Fechar/abrir conversas
- ✅ Preferências de usuário
- ✅ Busca e filtros

### **Fase 4: Polimento** (Semana 7-8)
- ✅ Melhorias de UX
- ✅ Responsividade mobile
- ✅ Testes e correções
- ✅ Documentação
- ✅ Treinamento

---

## 🔒 Segurança

### **Validações**

- Verificar permissões antes de cada ação
- Validar que colaborador só acessa suas conversas
- Validar que RH só acessa conversas permitidas
- Sanitizar todas as mensagens (XSS)
- Validar tipos de arquivo para anexos
- Limitar tamanho de arquivos (10MB)

### **Privacidade**

- Mensagens são privadas (apenas participantes veem)
- Histórico completo mantido para auditoria
- Soft delete de mensagens (mantém histórico)
- Logs de ações importantes

---

## 📝 Considerações Finais

### **Escalabilidade**

- Polling a cada 5 segundos (pode melhorar com WebSockets futuramente)
- Índices no banco para performance
- Cache de conversas abertas
- Paginação de mensagens

### **Melhorias Futuras**

- WebSockets para tempo real
- Chat em grupo
- Transferência automática de conversas
- Integração com WhatsApp/Telegram
- Chatbot inicial (IA)
- Avaliação de atendimento
- Relatórios avançados

---

## ✅ Checklist de Implementação

- [ ] Criar migração SQL completa
- [ ] Criar funções auxiliares PHP
- [ ] Criar APIs REST
- [ ] Criar widget flutuante
- [ ] Criar página de gestão RH
- [ ] Integrar notificações push
- [ ] Implementar efeitos sonoros
- [ ] Criar sistema de upload de anexos
- [ ] Integrar ChatGPT
- [ ] Criar funcionalidade de ocorrências
- [ ] Adicionar preferências de usuário
- [ ] Criar página de configurações
- [ ] Adicionar ao menu
- [ ] Adicionar permissões
- [ ] Testes completos
- [ ] Documentação de uso

---

**Este projeto fornece uma base completa para implementação do sistema de chat interno. Todas as funcionalidades estão detalhadas e prontas para desenvolvimento!**

