# 🎯 Proposta Completa: Sistema de Recrutamento e Seleção

## 📋 Sumário Executivo

Este documento apresenta uma proposta completa para implementação de um **Sistema de Recrutamento e Seleção** integrado ao RH Privus, incluindo:

- ✅ Cadastro e gestão de vagas
- ✅ Portal de recrutamento público
- ✅ Jornada completa de entrevistas
- ✅ Processo de seleção com Kanban
- ✅ Onboarding com Kanban
- ✅ Formulários de alinhamento cultural como etapa
- ✅ Integração com sistema existente

---

## 🔍 Análise do Sistema Atual

### ✅ O Que Já Temos e Podemos Reutilizar

1. **Sistema de Formulários Dinâmicos**
   - ✅ `pesquisas_satisfacao_campos` - Campos dinâmicos
   - ✅ `pesquisas_satisfacao_respostas` - Respostas
   - ✅ Sistema de validação e tipos de campos
   - **Aplicação**: Reutilizar para formulários de cultura

2. **Sistema de Workflow**
   - ✅ `ocorrencias` com `status_aprovacao` e workflow
   - ✅ `ocorrencias_historico` - Auditoria completa
   - ✅ Sistema de aprovação/rejeição
   - **Aplicação**: Adaptar para etapas de seleção

3. **Sistema de Notificações**
   - ✅ OneSignal integrado
   - ✅ Email via PHPMailer
   - ✅ Notificações no sistema
   - **Aplicação**: Notificar candidatos e recrutadores

4. **Sistema de Permissões**
   - ✅ Permissões granulares por perfil
   - ✅ Controle de acesso por empresa/setor
   - **Aplicação**: Controle de acesso a vagas e candidatos

5. **Sistema de Anexos**
   - ✅ `ocorrencias_anexos` - Upload de arquivos
   - ✅ Validação de tipos e tamanhos
   - **Aplicação**: Currículos e documentos de candidatos

6. **Sistema de Comentários**
   - ✅ `ocorrencias_comentarios` - Comentários em processos
   - **Aplicação**: Feedback entre recrutadores

7. **Sistema de Kanban (Metronic)**
   - ✅ Metronic Theme tem componentes de Kanban
   - ✅ Drag & drop nativo
   - **Aplicação**: Visualização de processos

---

## 🎯 Funcionalidades Propostas

### 1. **Gestão de Vagas** 📝

#### Funcionalidades:
- ✅ Cadastro completo de vagas
- ✅ Campos configuráveis (salário, localização, modalidade, etc.)
- ✅ Requisitos e competências
- ✅ Status (aberta, pausada, fechada, cancelada)
- ✅ Publicação automática no portal
- ✅ Integração com setores/cargos existentes
- ✅ Histórico de alterações

#### Campos Principais:
- Título e descrição
- Empresa e setor (relacionamento com tabelas existentes)
- Cargo (relacionamento com `cargos`)
- Tipo de contrato (CLT, PJ, Estágio, etc.)
- Modalidade (Presencial, Remoto, Híbrido)
- Salário (faixa ou valor fixo)
- Requisitos obrigatórios e desejáveis
- Competências técnicas e comportamentais
- Prazo de inscrição
- Quantidade de vagas

---

### 2. **Portal de Recrutamento Público** 🌐

#### Funcionalidades:
- ✅ Página pública (sem login) para candidatos
- ✅ Listagem de vagas abertas
- ✅ Filtros (cargo, modalidade, localização)
- ✅ Busca por palavras-chave
- ✅ Formulário de candidatura
- ✅ Upload de currículo
- ✅ Pré-cadastro de candidatos

#### Experiência do Candidato:
1. Acessa portal público
2. Visualiza vagas disponíveis
3. Clica em "Candidatar-se"
4. Preenche formulário (dados pessoais + currículo)
5. Responde formulário de cultura (se configurado)
6. Recebe confirmação por email
7. Acompanha status da candidatura

---

### 3. **Jornada de Entrevistas** 🎤

#### Etapas Propostas:
1. **Triagem Inicial** (RH)
   - Análise de currículo
   - Verificação de requisitos
   - Decisão: Aprovado/Reprovado

2. **Entrevista por Telefone/Zoom** (RH)
   - Agendamento automático
   - Link de videoconferência
   - Avaliação inicial

3. **Entrevista Técnica** (Gestor/Especialista)
   - Agendamento
   - Avaliação técnica
   - Feedback estruturado

4. **Entrevista Comportamental** (RH/Gestor)
   - Avaliação de soft skills
   - Alinhamento cultural
   - Feedback

5. **Entrevista Final** (Diretoria/Gestão)
   - Decisão final
   - Proposta de contratação

#### Funcionalidades:
- ✅ Agendamento de entrevistas
- ✅ Calendário integrado
- ✅ Link de videoconferência (Zoom/Meet)
- ✅ Formulários de avaliação por etapa
- ✅ Notas e feedback estruturado
- ✅ Histórico completo

---

### 4. **Formulários de Alinhamento Cultural** 📋

#### Funcionalidades:
- ✅ Criar formulários customizados
- ✅ Campos dinâmicos (texto, múltipla escolha, escala, etc.)
- ✅ Aplicar em etapas específicas
- ✅ Avaliação automática (pontuação)
- ✅ Relatórios de alinhamento

#### Exemplo de Formulário:
```
1. "Você prefere trabalhar em equipe ou individualmente?"
   - Equipe
   - Individual
   - Ambos

2. "Como você lida com prazos apertados?"
   - Fico estressado
   - Me organizo melhor
   - Trabalho melhor sob pressão

3. "Qual é sua prioridade no trabalho?"
   - Crescimento profissional
   - Equilíbrio vida-trabalho
   - Remuneração
```

#### Integração:
- Formulário pode ser etapa obrigatória ou opcional
- Pode ser aplicado em múltiplas etapas
- Resultado influencia decisão final

---

### 5. **Processo de Seleção com Kanban** 📊

#### Colunas do Kanban:
1. **Novos Candidatos** (Triagem)
2. **Em Análise** (RH analisando)
3. **Entrevistas** (Em processo de entrevista)
4. **Avaliação** (Aguardando decisão)
5. **Aprovados** (Prontos para contratação)
6. **Reprovados** (Arquivados)

#### Funcionalidades:
- ✅ Drag & drop entre colunas
- ✅ Cards com informações resumidas
- ✅ Filtros por vaga, status, recrutador
- ✅ Busca rápida
- ✅ Visualização detalhada ao clicar
- ✅ Atualização automática de status

#### Informações no Card:
- Foto do candidato
- Nome
- Vaga aplicada
- Status atual
- Data de candidatura
- Última atualização
- Badge de prioridade

---

### 6. **Onboarding com Kanban** 🚀

#### Colunas do Kanban:
1. **Contratado** (Assinatura de contrato)
2. **Documentação** (Envio de documentos)
3. **Treinamento** (Treinamentos iniciais)
4. **Integração** (Apresentação à equipe)
5. **Acompanhamento** (Primeiros dias)
6. **Concluído** (Onboarding finalizado)

#### Etapas de Onboarding:
1. **Assinatura de Contrato**
   - Upload de contrato assinado
   - Verificação de documentos

2. **Documentação**
   - CPF, RG, CTPS
   - Comprovantes
   - Documentos médicos (se necessário)

3. **Treinamentos**
   - Treinamento inicial
   - Treinamento técnico
   - Treinamento de cultura

4. **Integração**
   - Apresentação à equipe
   - Definição de mentor/buddy
   - Acesso a sistemas

5. **Acompanhamento**
   - Check-ins semanais
   - Feedback inicial
   - Ajustes necessários

6. **Conclusão**
   - Avaliação final
   - Ativação como colaborador
   - Criação de usuário no sistema

#### Funcionalidades:
- ✅ Tarefas por etapa
- ✅ Checklist de documentos
- ✅ Prazos e alertas
- ✅ Notificações automáticas
- ✅ Integração com cadastro de colaboradores

---

## 🗄️ Estrutura de Banco de Dados Proposta

### Tabelas Principais

#### 1. `vagas`
```sql
CREATE TABLE vagas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    setor_id INT NULL,
    cargo_id INT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NOT NULL,
    requisitos_obrigatorios TEXT,
    requisitos_desejaveis TEXT,
    competencias_tecnicas TEXT,
    competencias_comportamentais TEXT,
    tipo_contrato ENUM('CLT', 'PJ', 'Estágio', 'Temporário', 'Freelance') DEFAULT 'CLT',
    modalidade ENUM('Presencial', 'Remoto', 'Híbrido') DEFAULT 'Presencial',
    salario_min DECIMAL(10,2) NULL,
    salario_max DECIMAL(10,2) NULL,
    localizacao VARCHAR(255) NULL,
    quantidade_vagas INT DEFAULT 1,
    quantidade_preenchida INT DEFAULT 0,
    status ENUM('aberta', 'pausada', 'fechada', 'cancelada') DEFAULT 'aberta',
    publicar_portal BOOLEAN DEFAULT TRUE,
    data_abertura DATE NULL,
    data_fechamento DATE NULL,
    criado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (setor_id) REFERENCES setores(id),
    FOREIGN KEY (cargo_id) REFERENCES cargos(id),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    INDEX idx_status (status),
    INDEX idx_empresa (empresa_id),
    INDEX idx_publicar_portal (publicar_portal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. `candidatos`
```sql
CREATE TABLE candidatos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefone VARCHAR(20) NULL,
    cpf VARCHAR(14) NULL,
    data_nascimento DATE NULL,
    endereco TEXT NULL,
    cidade VARCHAR(100) NULL,
    estado VARCHAR(2) NULL,
    linkedin VARCHAR(255) NULL,
    portfolio VARCHAR(255) NULL,
    observacoes TEXT NULL,
    status ENUM('ativo', 'inativo', 'contratado', 'desistente') DEFAULT 'ativo',
    origem ENUM('portal', 'indicação', 'linkedin', 'outro') DEFAULT 'portal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3. `candidaturas`
```sql
CREATE TABLE candidaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaga_id INT NOT NULL,
    candidato_id INT NOT NULL,
    status ENUM('nova', 'triagem', 'entrevista', 'avaliacao', 'aprovada', 'reprovada', 'desistente') DEFAULT 'nova',
    etapa_atual VARCHAR(50) NULL COMMENT 'Etapa atual do processo',
    prioridade ENUM('baixa', 'media', 'alta', 'urgente') DEFAULT 'media',
    nota_geral DECIMAL(3,1) NULL COMMENT 'Nota geral do candidato (0-10)',
    observacoes TEXT NULL,
    recrutador_responsavel INT NULL,
    data_candidatura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aprovacao DATE NULL,
    data_reprovacao DATE NULL,
    motivo_reprovacao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vaga_id) REFERENCES vagas(id) ON DELETE CASCADE,
    FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
    FOREIGN KEY (recrutador_responsavel) REFERENCES usuarios(id),
    INDEX idx_vaga (vaga_id),
    INDEX idx_candidato (candidato_id),
    INDEX idx_status (status),
    INDEX idx_recrutador (recrutador_responsavel),
    UNIQUE KEY uk_vaga_candidato (vaga_id, candidato_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 4. `candidaturas_anexos`
```sql
CREATE TABLE candidaturas_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    tipo ENUM('curriculo', 'carta_apresentacao', 'portfolio', 'outro') DEFAULT 'curriculo',
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(500) NOT NULL,
    tipo_mime VARCHAR(100) NULL,
    tamanho_bytes INT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
    INDEX idx_candidatura (candidatura_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 5. `processo_seletivo_etapas`
```sql
CREATE TABLE processo_seletivo_etapas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaga_id INT NULL COMMENT 'NULL = etapa padrão para todas as vagas',
    nome VARCHAR(100) NOT NULL,
    codigo VARCHAR(50) NOT NULL COMMENT 'Identificador único da etapa',
    tipo ENUM('triagem', 'entrevista', 'teste', 'formulario_cultura', 'aprovacao', 'outro') NOT NULL,
    ordem INT DEFAULT 0,
    obrigatoria BOOLEAN DEFAULT TRUE,
    permite_pular BOOLEAN DEFAULT FALSE,
    tempo_medio_minutos INT NULL COMMENT 'Tempo médio estimado',
    descricao TEXT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vaga_id) REFERENCES vagas(id) ON DELETE CASCADE,
    INDEX idx_vaga (vaga_id),
    INDEX idx_codigo (codigo),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 6. `candidaturas_etapas`
```sql
CREATE TABLE candidaturas_etapas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    etapa_id INT NOT NULL,
    status ENUM('pendente', 'em_andamento', 'concluida', 'reprovada', 'pulada') DEFAULT 'pendente',
    data_inicio DATETIME NULL,
    data_conclusao DATETIME NULL,
    avaliador_id INT NULL COMMENT 'Usuário que avaliou',
    nota DECIMAL(3,1) NULL COMMENT 'Nota da etapa (0-10)',
    feedback TEXT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
    FOREIGN KEY (etapa_id) REFERENCES processo_seletivo_etapas(id),
    FOREIGN KEY (avaliador_id) REFERENCES usuarios(id),
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_etapa (etapa_id),
    INDEX idx_status (status),
    UNIQUE KEY uk_candidatura_etapa (candidatura_id, etapa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 7. `entrevistas`
```sql
CREATE TABLE entrevistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    etapa_id INT NULL,
    tipo ENUM('telefone', 'video', 'presencial', 'grupo') DEFAULT 'presencial',
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    entrevistador_id INT NOT NULL,
    data_agendada DATETIME NOT NULL,
    duracao_minutos INT DEFAULT 60,
    link_videoconferencia VARCHAR(500) NULL,
    localizacao VARCHAR(255) NULL,
    status ENUM('agendada', 'realizada', 'cancelada', 'reagendada', 'nao_compareceu') DEFAULT 'agendada',
    data_realizacao DATETIME NULL,
    avaliacao_entrevistador TEXT NULL,
    nota_entrevistador DECIMAL(3,1) NULL,
    feedback_candidato TEXT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
    FOREIGN KEY (etapa_id) REFERENCES processo_seletivo_etapas(id),
    FOREIGN KEY (entrevistador_id) REFERENCES usuarios(id),
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_entrevistador (entrevistador_id),
    INDEX idx_data_agendada (data_agendada),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 8. `formularios_cultura`
```sql
CREATE TABLE formularios_cultura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    etapa_id INT NULL COMMENT 'Etapa onde será aplicado',
    ativo BOOLEAN DEFAULT TRUE,
    criado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (etapa_id) REFERENCES processo_seletivo_etapas(id),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    INDEX idx_etapa (etapa_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 9. `formularios_cultura_campos`
```sql
CREATE TABLE formularios_cultura_campos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    tipo_campo ENUM('text', 'textarea', 'number', 'select', 'radio', 'checkbox', 'escala') NOT NULL,
    label VARCHAR(200) NOT NULL,
    placeholder VARCHAR(200) NULL,
    obrigatorio BOOLEAN DEFAULT FALSE,
    valor_padrao TEXT NULL,
    opcoes JSON NULL COMMENT 'Para select/radio: array de opções',
    escala_min INT NULL COMMENT 'Para tipo escala',
    escala_max INT NULL COMMENT 'Para tipo escala',
    escala_label_min VARCHAR(50) NULL,
    escala_label_max VARCHAR(50) NULL,
    peso DECIMAL(3,2) DEFAULT 1.00 COMMENT 'Peso na pontuação final',
    ordem INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (formulario_id) REFERENCES formularios_cultura(id) ON DELETE CASCADE,
    INDEX idx_formulario (formulario_id),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 10. `formularios_cultura_respostas`
```sql
CREATE TABLE formularios_cultura_respostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    formulario_id INT NOT NULL,
    campo_id INT NOT NULL,
    resposta TEXT NOT NULL,
    pontuacao DECIMAL(5,2) NULL COMMENT 'Pontuação calculada',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
    FOREIGN KEY (formulario_id) REFERENCES formularios_cultura(id),
    FOREIGN KEY (campo_id) REFERENCES formularios_cultura_campos(id),
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_formulario (formulario_id),
    UNIQUE KEY uk_candidatura_campo (candidatura_id, campo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 11. `onboarding`
```sql
CREATE TABLE onboarding (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    colaborador_id INT NULL COMMENT 'Criado após contratação',
    status ENUM('contratado', 'documentacao', 'treinamento', 'integracao', 'acompanhamento', 'concluido') DEFAULT 'contratado',
    data_inicio DATE NOT NULL,
    data_previsao_conclusao DATE NULL,
    data_conclusao DATE NULL,
    responsavel_id INT NOT NULL COMMENT 'RH responsável',
    mentor_id INT NULL COMMENT 'Colaborador mentor/buddy',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id),
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id),
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id),
    FOREIGN KEY (mentor_id) REFERENCES colaboradores(id),
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 12. `onboarding_tarefas`
```sql
CREATE TABLE onboarding_tarefas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id INT NOT NULL,
    etapa VARCHAR(50) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    tipo ENUM('documento', 'treinamento', 'reuniao', 'configuracao', 'outro') NOT NULL,
    status ENUM('pendente', 'em_andamento', 'concluida', 'cancelada') DEFAULT 'pendente',
    responsavel_id INT NULL COMMENT 'Quem deve executar',
    data_vencimento DATE NULL,
    data_conclusao DATETIME NULL,
    anexos JSON NULL COMMENT 'Array de caminhos de arquivos',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (onboarding_id) REFERENCES onboarding(id) ON DELETE CASCADE,
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id),
    INDEX idx_onboarding (onboarding_id),
    INDEX idx_status (status),
    INDEX idx_etapa (etapa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 13. `candidaturas_historico`
```sql
CREATE TABLE candidaturas_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    usuario_id INT NULL,
    acao ENUM('criada', 'status_alterado', 'etapa_concluida', 'entrevista_agendada', 'entrevista_realizada', 'aprovada', 'reprovada', 'comentario') NOT NULL,
    campo_alterado VARCHAR(100) NULL,
    valor_anterior TEXT NULL,
    valor_novo TEXT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_acao (acao),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 14. `candidaturas_comentarios`
```sql
CREATE TABLE candidaturas_comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    usuario_id INT NOT NULL,
    comentario TEXT NOT NULL,
    tipo ENUM('comentario', 'feedback', 'observacao') DEFAULT 'comentario',
    anexos JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 💡 Sugestões e Melhorias

### 1. **Integração com LinkedIn** 🔗
- Importar perfil do LinkedIn
- Verificar recomendações
- Análise automática de perfil

### 2. **Sistema de Pontuação Automática** 📊
- Pontuação baseada em:
  - Requisitos atendidos
  - Experiência relevante
  - Formulário de cultura
  - Performance em entrevistas
- Ranking automático de candidatos

### 3. **Banco de Talentos** 💼
- Candidatos reprovados ficam no banco
- Reativação automática para vagas similares
- Busca inteligente por competências

### 4. **Relatórios e Analytics** 📈
- Tempo médio de contratação
- Taxa de conversão por etapa
- Fonte de candidatos mais eficaz
- Performance de recrutadores
- Custo por contratação

### 5. **Integração com Email Marketing** 📧
- Campanhas automáticas
- Templates de email
- Follow-up automático

### 6. **Avaliação por IA (Futuro)** 🤖
- Análise de currículo por IA
- Matching automático candidato-vaga
- Predição de sucesso

### 7. **Portal do Candidato** 👤
- Login para candidatos
- Acompanhamento de processos
- Atualização de perfil
- Histórico de candidaturas

### 8. **Sistema de Indicações** 🎁
- Colaboradores indicam candidatos
- Bonificação por indicação bem-sucedida
- Tracking de indicações

### 9. **Testes Online** 🧪
- Testes técnicos integrados
- Testes comportamentais
- Correção automática

### 10. **Integração com Calendário** 📅
- Google Calendar
- Outlook
- Sincronização automática

---

## 🔄 Fluxos de Trabalho Propostos

### Fluxo 1: Candidatura pelo Portal
```
1. Candidato acessa portal público
2. Visualiza vagas disponíveis
3. Clica em "Candidatar-se"
4. Preenche dados pessoais
5. Faz upload do currículo
6. Responde formulário de cultura (se aplicável)
7. Recebe email de confirmação
8. Candidatura aparece no Kanban como "Nova"
```

### Fluxo 2: Processo de Seleção
```
1. RH visualiza candidatura no Kanban
2. Move para "Em Análise"
3. Analisa currículo e requisitos
4. Aprova ou reprova na triagem
5. Se aprovado:
   - Move para "Entrevistas"
   - Agenda entrevista inicial
   - Envia email com link/agendamento
6. Após entrevista:
   - Avalia candidato
   - Move para próxima etapa ou reprova
7. Repete até aprovação final
8. Move para "Aprovados"
```

### Fluxo 3: Onboarding
```
1. Candidato aprovado
2. RH cria processo de onboarding
3. Move para coluna "Contratado"
4. Envia contrato para assinatura
5. Após assinatura:
   - Move para "Documentação"
   - Cria tarefas de documentos
6. Após documentos:
   - Move para "Treinamento"
   - Agenda treinamentos
7. Após treinamentos:
   - Move para "Integração"
   - Apresenta à equipe
8. Após integração:
   - Move para "Acompanhamento"
   - Check-ins semanais
9. Após acompanhamento:
   - Move para "Concluído"
   - Cria colaborador no sistema
   - Envia credenciais
```

---

## 🎨 Interface Proposta

### 1. **Página de Gestão de Vagas**
- Listagem com filtros
- Cards de vagas
- Status visual
- Ações rápidas

### 2. **Kanban de Seleção**
- Colunas drag & drop
- Cards com foto e informações
- Filtros laterais
- Busca rápida
- Modal de detalhes

### 3. **Kanban de Onboarding**
- Similar ao de seleção
- Tarefas por etapa
- Checklist visual
- Progresso percentual

### 4. **Portal Público**
- Design moderno e responsivo
- Listagem de vagas
- Filtros e busca
- Formulário de candidatura
- Página de sucesso

### 5. **Página de Detalhes da Candidatura**
- Informações completas
- Histórico de etapas
- Comentários e feedback
- Anexos
- Ações rápidas

---

## 🔐 Permissões Propostas

### ADMIN
- ✅ Acesso total
- ✅ Configuração de etapas
- ✅ Criação de formulários
- ✅ Relatórios completos

### RH
- ✅ Gestão de vagas
- ✅ Gestão de candidaturas
- ✅ Agendamento de entrevistas
- ✅ Aprovação/reprovação
- ✅ Gestão de onboarding

### GESTOR
- ✅ Visualizar vagas do setor
- ✅ Avaliar candidatos
- ✅ Participar de entrevistas
- ✅ Feedback em candidaturas

### COLABORADOR
- ✅ Indicar candidatos
- ✅ Ver vagas abertas (se configurado)

---

## 📱 Integração com Sistema Existente

### 1. **Colaboradores**
- Após onboarding concluído, criar registro em `colaboradores`
- Vincular com `candidatura_id` original
- Manter histórico completo

### 2. **Setores e Cargos**
- Vagas vinculadas a setores/cargos existentes
- Reutilizar estrutura atual

### 3. **Notificações**
- Usar sistema OneSignal existente
- Notificar candidatos e recrutadores
- Templates de email existentes

### 4. **Permissões**
- Integrar com `includes/permissions.php`
- Reutilizar sistema de roles

### 5. **Anexos**
- Reutilizar sistema de upload
- Mesma estrutura de validação

---

## 🚀 Fases de Implementação Sugeridas

### Fase 1: Base (Sprint 1-2)
- ✅ Estrutura de banco de dados
- ✅ CRUD de vagas
- ✅ CRUD de candidatos
- ✅ Sistema básico de candidaturas

### Fase 2: Portal e Processo (Sprint 3-4)
- ✅ Portal público
- ✅ Formulário de candidatura
- ✅ Kanban de seleção básico
- ✅ Etapas do processo

### Fase 3: Entrevistas e Formulários (Sprint 5-6)
- ✅ Sistema de entrevistas
- ✅ Formulários de cultura
- ✅ Avaliações e feedback
- ✅ Notificações

### Fase 4: Onboarding (Sprint 7-8)
- ✅ Kanban de onboarding
- ✅ Tarefas e checklists
- ✅ Integração com colaboradores
- ✅ Relatórios básicos

### Fase 5: Melhorias (Sprint 9-10)
- ✅ Analytics e relatórios
- ✅ Banco de talentos
- ✅ Melhorias de UX
- ✅ Otimizações

---

## 📊 Métricas e KPIs Sugeridos

1. **Tempo médio de contratação** (dias)
2. **Taxa de conversão por etapa** (%)
3. **Taxa de aceitação de ofertas** (%)
4. **Custo por contratação** (R$)
5. **Fonte de candidatos mais eficaz**
6. **Taxa de desistência** (%)
7. **Satisfação do candidato** (pesquisa)
8. **Taxa de sucesso no onboarding** (%)

---

## ✅ Checklist de Implementação

### Banco de Dados
- [ ] Criar todas as tabelas
- [ ] Criar índices necessários
- [ ] Criar foreign keys
- [ ] Criar triggers (se necessário)
- [ ] Popular dados iniciais

### Backend
- [ ] APIs de vagas
- [ ] APIs de candidatos
- [ ] APIs de candidaturas
- [ ] APIs de entrevistas
- [ ] APIs de formulários
- [ ] APIs de onboarding
- [ ] Funções auxiliares

### Frontend
- [ ] Página de gestão de vagas
- [ ] Portal público
- [ ] Kanban de seleção
- [ ] Kanban de onboarding
- [ ] Página de detalhes
- [ ] Formulários dinâmicos

### Integrações
- [ ] Notificações
- [ ] Email
- [ ] Upload de arquivos
- [ ] Permissões
- [ ] Colaboradores

### Testes
- [ ] Testes unitários
- [ ] Testes de integração
- [ ] Testes de usuário
- [ ] Validação de segurança

---

## 🎯 Próximos Passos

1. **Revisar esta proposta**
2. **Aprovar estrutura**
3. **Definir prioridades**
4. **Iniciar implementação**

---

## 📝 Observações Finais

- Sistema totalmente integrado com RH Privus
- Reutiliza componentes existentes
- Escalável e extensível
- Foco em experiência do usuário
- Dados seguros e auditados

---

**Documento criado em:** {{ data_atual }}  
**Versão:** 1.0  
**Status:** Proposta para Aprovação

