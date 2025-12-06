# 📋 PROJETO: Manual de Conduta Privus

## 🎯 OBJETIVO

Criar um módulo completo de **Manual de Conduta** que permita:
- Visualização pública do manual de conduta para todos os colaboradores
- Sistema de FAQ (Perguntas Frequentes) acessível a todos
- Edição e gestão do conteúdo apenas para ADMIN
- Histórico de alterações e versionamento
- Interface moderna e responsiva

---

## 📊 ESTRUTURA DO MENU

```
Manual de Conduta (aparece para todos)
├── Conduta Privus (visualização - todos)
├── FAQ (visualização - todos)
├── Editar Conduta (ADMIN)
└── Editar FAQ (ADMIN)
```

---

## 🗄️ ESTRUTURA DO BANCO DE DADOS

### 1. Tabela: `manual_conduta`
Armazena o conteúdo principal do manual de conduta.

```sql
CREATE TABLE IF NOT EXISTS manual_conduta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL DEFAULT 'Manual de Conduta Privus',
    conteudo LONGTEXT NOT NULL COMMENT 'Conteúdo HTML/Markdown do manual',
    versao VARCHAR(50) NULL COMMENT 'Versão do manual (ex: 1.0, 2.1)',
    ativo BOOLEAN DEFAULT TRUE COMMENT 'Se está ativo e visível',
    publicado_em DATETIME NULL COMMENT 'Data de publicação',
    publicado_por INT NULL COMMENT 'Usuário que publicou',
    criado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (publicado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    INDEX idx_ativo (ativo),
    INDEX idx_publicado_em (publicado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Tabela: `manual_conduta_historico`
Armazena histórico de alterações do manual.

```sql
CREATE TABLE IF NOT EXISTS manual_conduta_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manual_conduta_id INT NOT NULL,
    versao VARCHAR(50) NULL,
    conteudo_anterior LONGTEXT NULL COMMENT 'Conteúdo antes da alteração',
    conteudo_novo LONGTEXT NULL COMMENT 'Conteúdo após alteração',
    alterado_por INT NOT NULL,
    motivo_alteracao TEXT NULL COMMENT 'Motivo da alteração',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (manual_conduta_id) REFERENCES manual_conduta(id) ON DELETE CASCADE,
    FOREIGN KEY (alterado_por) REFERENCES usuarios(id),
    INDEX idx_manual_conduta (manual_conduta_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. Tabela: `faq_manual_conduta`
Armazena perguntas e respostas do FAQ.

```sql
CREATE TABLE IF NOT EXISTS faq_manual_conduta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pergunta TEXT NOT NULL,
    resposta LONGTEXT NOT NULL,
    categoria VARCHAR(100) NULL COMMENT 'Categoria para agrupamento (ex: Geral, Regras, Benefícios)',
    ordem INT DEFAULT 0 COMMENT 'Ordem de exibição',
    ativo BOOLEAN DEFAULT TRUE,
    visualizacoes INT DEFAULT 0 COMMENT 'Contador de visualizações',
    util_respondeu_sim INT DEFAULT 0 COMMENT 'Contador de "útil"',
    util_respondeu_nao INT DEFAULT 0 COMMENT 'Contador de "não útil"',
    criado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    INDEX idx_ativo (ativo),
    INDEX idx_categoria (categoria),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4. Tabela: `faq_manual_conduta_historico`
Histórico de alterações do FAQ.

```sql
CREATE TABLE IF NOT EXISTS faq_manual_conduta_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faq_id INT NOT NULL,
    pergunta_anterior TEXT NULL,
    pergunta_nova TEXT NULL,
    resposta_anterior LONGTEXT NULL,
    resposta_nova LONGTEXT NULL,
    alterado_por INT NOT NULL,
    motivo_alteracao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (faq_id) REFERENCES faq_manual_conduta(id) ON DELETE CASCADE,
    FOREIGN KEY (alterado_por) REFERENCES usuarios(id),
    INDEX idx_faq (faq_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5. Tabela: `manual_conduta_visualizacoes` (OPCIONAL - Analytics)
Rastreia visualizações do manual.

```sql
CREATE TABLE IF NOT EXISTS manual_conduta_visualizacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL COMMENT 'Usuário logado',
    colaborador_id INT NULL COMMENT 'Colaborador (se não tiver usuário)',
    tipo ENUM('manual', 'faq') NOT NULL,
    faq_id INT NULL COMMENT 'ID do FAQ se tipo = faq',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    FOREIGN KEY (faq_id) REFERENCES faq_manual_conduta(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo (tipo),
    INDEX idx_faq (faq_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 📁 ESTRUTURA DE ARQUIVOS

```
rh-privus/
├── migracao_manual_conduta.sql          # Script de migração do banco
├── includes/
│   ├── manual_conduta_functions.php      # Funções auxiliares do módulo
│   ├── permissions.php                   # Atualizar com novas permissões
│   └── menu.php                          # Atualizar com novo menu
├── pages/
│   ├── manual_conduta_view.php           # Visualização do manual (todos)
│   ├── manual_conduta_edit.php           # Edição do manual (ADMIN)
│   ├── faq_view.php                      # Visualização do FAQ (todos)
│   ├── faq_edit.php                      # Edição do FAQ (ADMIN)
│   └── manual_conduta_historico.php      # Histórico de alterações (ADMIN)
└── api/
    └── manual_conduta/
        ├── salvar_manual.php             # API para salvar manual
        ├── salvar_faq.php                # API para salvar FAQ
        ├── deletar_faq.php                # API para deletar FAQ
        ├── reordenar_faq.php              # API para reordenar FAQ
        ├── marcar_util.php                # API para marcar FAQ como útil/não útil
        └── visualizacao.php               # API para registrar visualização
```

---

## 🎨 FUNCIONALIDADES PRINCIPAIS

### 1. Visualização do Manual de Conduta (`manual_conduta_view.php`)

**Permissões:** Todos os usuários logados

**Funcionalidades:**
- ✅ Exibição do conteúdo formatado (HTML/Markdown)
- ✅ Navegação por seções (se aplicável)
- ✅ Busca no conteúdo
- ✅ Botão de impressão/PDF
- ✅ Indicador de última atualização
- ✅ Versão do manual exibida
- ✅ Botão "Editar" (apenas ADMIN)

**Melhorias Sugeridas:**
- 📌 **Índice navegável** - Seções clicáveis para navegação rápida
- 📌 **Modo de impressão** - Versão otimizada para impressão
- 📌 **Exportar PDF** - Geração automática de PDF
- 📌 **Compartilhamento** - Link direto para seções específicas
- 📌 **Busca avançada** - Busca por palavras-chave com highlight
- 📌 **Favoritos** - Marcar seções favoritas
- 📌 **Comentários** - Colaboradores podem deixar comentários/dúvidas (moderados)

### 2. Edição do Manual (`manual_conduta_edit.php`)

**Permissões:** Apenas ADMIN

**Funcionalidades:**
- ✅ Editor WYSIWYG (TinyMCE ou similar)
- ✅ Preview em tempo real
- ✅ Versionamento automático
- ✅ Campo de motivo da alteração
- ✅ Histórico de alterações
- ✅ Publicar/Despublicar
- ✅ Upload de imagens
- ✅ Formatação rica (negrito, itálico, listas, etc)

**Melhorias Sugeridas:**
- 📌 **Editor Markdown** - Suporte a Markdown com preview
- 📌 **Templates** - Templates pré-definidos de estrutura
- 📌 **Colaboração** - Múltiplos editores com sugestões
- 📌 **Revisão** - Sistema de aprovação antes de publicar
- 📌 **Backup automático** - Salvamento automático a cada X segundos
- 📌 **Comparação de versões** - Diff visual entre versões
- 📌 **Notificações** - Notificar colaboradores sobre atualizações
- 📌 **Anexos** - Upload de documentos relacionados

### 3. Visualização do FAQ (`faq_view.php`)

**Permissões:** Todos os usuários logados

**Funcionalidades:**
- ✅ Lista de perguntas e respostas
- ✅ Busca por palavras-chave
- ✅ Filtro por categoria
- ✅ Accordion/Collapse para respostas
- ✅ Botão "Foi útil?" (sim/não)
- ✅ Contador de visualizações
- ✅ Ordenação (mais visualizadas, mais úteis, etc)

**Melhorias Sugeridas:**
- 📌 **Busca inteligente** - Busca semântica nas perguntas e respostas
- 📌 **Perguntas relacionadas** - Sugestão de FAQs relacionadas
- 📌 **Categorias visuais** - Ícones e cores por categoria
- 📌 **Sugerir pergunta** - Colaboradores podem sugerir novas perguntas
- 📌 **Feedback detalhado** - Campo de texto para feedback adicional
- 📌 **FAQ em destaque** - Marcar FAQs importantes
- 📌 **Exportar FAQ** - Gerar PDF com todas as perguntas
- 📌 **Compartilhamento** - Link direto para FAQ específico

### 4. Edição do FAQ (`faq_edit.php`)

**Permissões:** Apenas ADMIN

**Funcionalidades:**
- ✅ CRUD completo (Criar, Ler, Atualizar, Deletar)
- ✅ Editor WYSIWYG para respostas
- ✅ Categorias editáveis
- ✅ Reordenação por drag & drop
- ✅ Ativar/Desativar FAQs
- ✅ Estatísticas (visualizações, útil/não útil)
- ✅ Histórico de alterações

**Melhorias Sugeridas:**
- 📌 **Importar/Exportar** - Importar FAQ de CSV/Excel
- 📌 **Bulk actions** - Ações em massa (ativar, desativar, deletar)
- 📌 **Tags** - Sistema de tags para organização
- 📌 **Preview** - Preview antes de salvar
- 📌 **Duplicar FAQ** - Criar cópia de FAQ existente
- 📌 **Templates de resposta** - Templates pré-definidos
- 📌 **Analytics** - Gráficos de FAQs mais acessados
- 📌 **Moderação de sugestões** - Aprovar/rejeitar sugestões de colaboradores

### 5. Histórico de Alterações (`manual_conduta_historico.php`)

**Permissões:** Apenas ADMIN

**Funcionalidades:**
- ✅ Lista de todas as alterações
- ✅ Comparação lado a lado
- ✅ Restaurar versão anterior
- ✅ Filtros por data, usuário, versão
- ✅ Exportar histórico

---

## 🔐 PERMISSÕES

### Adicionar em `includes/permissions.php`:

```php
// Manual de Conduta - todos podem visualizar
'manual_conduta_view.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
'faq_view.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],

// Edição - apenas ADMIN
'manual_conduta_edit.php' => ['ADMIN'],
'faq_edit.php' => ['ADMIN'],
'manual_conduta_historico.php' => ['ADMIN'],
```

---

## 🎯 MELHORIAS E FUNCIONALIDADES AVANÇADAS

### 1. **Sistema de Notificações**
- Notificar colaboradores quando o manual for atualizado
- Notificar quando novas FAQs forem adicionadas
- Preferências de notificação por usuário

### 2. **Analytics e Relatórios**
- Dashboard com métricas de visualização
- FAQs mais acessados
- Taxa de "útil" por FAQ
- Horários de maior acesso
- Relatórios mensais automáticos

### 3. **Integração com Outros Módulos**
- Linkar manual com ocorrências (quando violação de conduta)
- Linkar manual com treinamentos (LMS)
- Referências cruzadas entre manual e FAQ

### 4. **Acessibilidade**
- Suporte a leitores de tela
- Alto contraste
- Navegação por teclado
- Textos alternativos em imagens

### 5. **Multilíngua** (Futuro)
- Suporte a múltiplos idiomas
- Tradução do manual e FAQ
- Seleção de idioma por usuário

### 6. **Mobile-First**
- Interface otimizada para mobile
- Menu hambúrguer
- Touch-friendly
- PWA support

### 7. **Gamificação** (Opcional)
- Pontos por ler o manual completo
- Badges por completar seções
- Ranking de engajamento

### 8. **Assinatura Digital**
- Colaboradores assinam que leram o manual
- Histórico de assinaturas
- Notificações de renovação

---

## 🚀 FLUXO DE IMPLEMENTAÇÃO

### Fase 1: Estrutura Base (MVP)
1. ✅ Criar migração do banco de dados
2. ✅ Criar funções auxiliares (`manual_conduta_functions.php`)
3. ✅ Criar página de visualização do manual
4. ✅ Criar página de edição do manual (ADMIN)
5. ✅ Criar página de visualização do FAQ
6. ✅ Criar página de edição do FAQ (ADMIN)
7. ✅ Atualizar permissões
8. ✅ Atualizar menu

### Fase 2: Funcionalidades Essenciais
1. ✅ Editor WYSIWYG
2. ✅ Sistema de versionamento
3. ✅ Histórico de alterações
4. ✅ Busca básica
5. ✅ Categorias no FAQ

### Fase 3: Melhorias e Refinamentos
1. 📌 Analytics de visualização
2. 📌 Sistema de "útil/não útil"
3. 📌 Exportar PDF
4. 📌 Busca avançada
5. 📌 Notificações

### Fase 4: Funcionalidades Avançadas
1. 📌 Sugestão de perguntas pelos colaboradores
2. 📌 Sistema de comentários
3. 📌 Assinatura digital
4. 📌 Integração com outros módulos

---

## 📝 EXEMPLO DE CONTEÚDO INICIAL

### Manual de Conduta (Estrutura Sugerida)

1. **Introdução**
   - Bem-vindo à Privus
   - Missão, Visão e Valores
   - Compromisso com a conduta ética

2. **Código de Ética**
   - Princípios fundamentais
   - Comportamento esperado
   - Relacionamento com colegas

3. **Políticas e Regulamentos**
   - Horários e pontualidade
   - Uso de recursos da empresa
   - Redes sociais e comunicação
   - Confidencialidade

4. **Direitos e Deveres**
   - Direitos do colaborador
   - Deveres do colaborador
   - Canais de comunicação

5. **Consequências**
   - Disciplina progressiva
   - Tipos de ocorrências
   - Processo de apuração

### FAQ (Exemplos Iniciais)

**Categoria: Geral**
- O que é o Manual de Conduta?
- Como posso acessar o manual?
- O manual é atualizado com frequência?

**Categoria: Regras**
- Qual o horário de trabalho?
- Posso usar o celular durante o trabalho?
- Como funciona o banco de horas?

**Categoria: Benefícios**
- Quais benefícios tenho direito?
- Como solicitar férias?
- Como funciona o vale-transporte?

---

## 🎨 DESIGN E UX

### Cores Sugeridas (Metronic Theme)
- **Primária:** Azul Privus (#009ef7)
- **Sucesso:** Verde (#50cd89)
- **Aviso:** Amarelo (#ffc700)
- **Perigo:** Vermelho (#f1416c)

### Componentes Metronic
- Cards para seções
- Accordion para FAQ
- Tabs para categorias
- Modals para edição
- Tooltips para ajuda

### Responsividade
- Mobile: 1 coluna
- Tablet: 2 colunas
- Desktop: 3 colunas (FAQ)

---

## 📊 MÉTRICAS DE SUCESSO

- ✅ Taxa de visualização do manual (>80% dos colaboradores)
- ✅ Número de FAQs acessados por mês
- ✅ Taxa de "útil" nas FAQs (>70%)
- ✅ Tempo médio de leitura do manual
- ✅ Número de atualizações do manual por trimestre
- ✅ Redução de dúvidas recorrentes

---

## 🔄 MANUTENÇÃO

### Tarefas Regulares
- Revisar manual trimestralmente
- Atualizar FAQs baseado em dúvidas frequentes
- Analisar métricas mensalmente
- Coletar feedback dos colaboradores

### Responsabilidades
- **ADMIN:** Manter conteúdo atualizado
- **RH:** Sugerir melhorias baseado em feedback
- **Gestores:** Incentivar leitura do manual

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

- [ ] Criar migração do banco de dados
- [ ] Criar funções auxiliares
- [ ] Criar página de visualização do manual
- [ ] Criar página de edição do manual
- [ ] Criar página de visualização do FAQ
- [ ] Criar página de edição do FAQ
- [ ] Criar página de histórico
- [ ] Atualizar permissões
- [ ] Atualizar menu
- [ ] Criar APIs necessárias
- [ ] Testar funcionalidades
- [ ] Criar conteúdo inicial
- [ ] Documentar uso
- [ ] Treinar equipe RH

---

## 📚 REFERÊNCIAS E INSPIRAÇÃO

- Manual de Conduta da empresa
- FAQs de sistemas similares
- Boas práticas de UX para documentação
- Padrões de acessibilidade WCAG

---

**Pronto para implementação!** 🚀

Este projeto segue os padrões já estabelecidos no sistema e pode ser implementado de forma incremental, começando pelo MVP e adicionando melhorias conforme necessário.

