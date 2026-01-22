# 📢 Sistema de Comunicados

## 📋 Visão Geral

O sistema de comunicados permite que RH e gestores publiquem informações importantes que são exibidas automaticamente para todos os colaboradores ao fazer login.

---

## 🎯 Funcionalidades

### ✅ **Para Administradores e RH**

1. **Criar Comunicados**
   - Editor de texto rico (TinyMCE) com formatação completa
   - Upload de imagens
   - Status: Rascunho ou Publicado
   - Data de publicação agendada (opcional)
   - Data de expiração (opcional)

2. **Gerenciar Comunicados**
   - Listar todos os comunicados
   - Ver estatísticas de leitura
   - Editar comunicados existentes
   - Excluir comunicados

3. **Envio Automático de Emails** 🆕
   - Quando um comunicado é publicado, **emails são enviados automaticamente** para **todos os colaboradores ativos**
   - Email com design profissional e responsivo
   - Preview do conteúdo no email
   - Link direto para visualizar no sistema

### ✅ **Para Colaboradores**

1. **Notificação Automática**
   - Modal aparece automaticamente ao fazer login
   - Mostra comunicados não lidos

2. **Marcar como Lido**
   - Botão "Marcar como Lido" em cada comunicado
   - ✅ **CORREÇÃO**: Comunicados marcados como lidos **NÃO aparecem mais** (bug corrigido)

3. **Histórico**
   - Acesso a todos os comunicados publicados
   - Busca e filtros

---

## 🗄️ Estrutura do Banco de Dados

### **Tabela: `comunicados`**

```sql
CREATE TABLE comunicados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    conteudo TEXT NOT NULL,
    imagem VARCHAR(255) NULL,
    criado_por_usuario_id INT NOT NULL,
    status ENUM('rascunho', 'publicado', 'arquivado'),
    data_publicacao DATETIME NULL,
    data_expiracao DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### **Tabela: `comunicados_leitura`**

Rastreia quem visualizou e leu cada comunicado.

```sql
CREATE TABLE comunicados_leitura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comunicado_id INT NOT NULL,
    usuario_id INT NULL,
    colaborador_id INT NULL,
    lido TINYINT(1) DEFAULT 0,
    data_leitura DATETIME NULL,
    data_visualizacao DATETIME NULL,
    vezes_visualizado INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_comunicado_usuario (comunicado_id, usuario_id, colaborador_id)
);
```

---

## 🔧 Correções Implementadas

### 1. ❌ **Bug: Comunicados Lidos Voltavam a Aparecer**

**Problema Original:**
```php
// Código antigo que causava o problema
OR (cl.lido = 1 AND TIMESTAMPDIFF(HOUR, cl.data_visualizacao, NOW()) >= 6)
```

Comunicados marcados como lidos voltavam a aparecer após 6 horas.

**✅ Solução:**
```php
// Código corrigido - só mostra se nunca foi visualizado OU se não foi marcado como lido
AND (
    cl.id IS NULL -- Nunca foi visualizado
    OR (cl.lido = 0) -- Não foi marcado como lido
)
```

**Arquivo corrigido:** `api/comunicados/listar_nao_lidos.php` (linha 44-47)

---

### 2. 🆕 **Novo: Envio Automático de Emails**

**Funcionalidade Implementada:**

Quando um comunicado é criado com status **"Publicado"**, o sistema:

1. ✅ Busca **todos os colaboradores ativos** com email
2. ✅ Envia email personalizado para cada um
3. ✅ Usa template bonito e profissional
4. ✅ Inclui preview do conteúdo
5. ✅ Link direto para visualizar no sistema
6. ✅ Exibe estatística de envios (quantos foram enviados, quantos falharam)

**Arquivo modificado:** `pages/comunicado_add.php` (linhas 71-88)

**Nova função criada:** `enviar_email_novo_comunicado()` em `includes/email_templates.php`

---

## 📧 Template de Email

### **Código do Template:** `novo_comunicado`

**Variáveis disponíveis:**
- `{nome_completo}` - Nome do colaborador
- `{titulo}` - Título do comunicado
- `{conteudo_preview}` - Preview de 300 caracteres do conteúdo
- `{conteudo_texto}` - Conteúdo completo em texto puro
- `{imagem_html}` - HTML da imagem (se houver)
- `{criado_por_nome}` - Nome de quem criou
- `{data_publicacao}` - Data/hora da publicação
- `{sistema_url}` - URL do sistema
- `{empresa_nome}` - Nome da empresa

**Design do Email:**
- 📱 Responsivo (adaptável a mobile)
- 🎨 Gradiente moderno (roxo/azul)
- 🖼️ Suporte a imagens
- 🔗 Botão de call-to-action destacado
- ✨ Preview do conteúdo no email

---

## 🚀 Como Usar

### **1. Criar Novo Comunicado**

1. Acessar: `Comunicados` → `Adicionar Comunicado`
2. Preencher:
   - **Título** (obrigatório)
   - **Conteúdo** (editor rico com formatação)
   - **Imagem** (opcional, até 5MB)
   - **Status**:
     - **Rascunho**: Salva mas não publica
     - **Publicado**: ✅ Publica E envia emails automaticamente
   - **Data de Publicação** (opcional, pré-preenchida com data/hora atual)
   - **Data de Expiração** (opcional)
3. Clicar em **"Salvar Comunicado"**

**Resultado:**
```
✅ Comunicado criado e emails enviados! (25 enviados, 0 erros)
```

### **2. Visualizar Estatísticas**

Na listagem de comunicados (`pages/comunicados.php`):
- **Total de Lidos**: Quantas pessoas marcaram como lido
- **Total de Visualizações**: Quantas vezes foi visualizado

### **3. Editar Comunicado**

1. Clicar em **"Editar"** no comunicado desejado
2. Fazer alterações
3. Salvar

**Nota:** Alterar status de "Rascunho" para "Publicado" **NÃO envia emails** novamente (apenas no momento da criação).

---

## 📦 Instalação

### **1. Executar Migração do Template de Email**

```bash
mysql -u root -p rh_privus < migracao_template_comunicado_email.sql
```

Ou via phpMyAdmin: Importar `migracao_template_comunicado_email.sql`

### **2. Verificar Configuração de Email**

Editar `config/email.php` com suas credenciais SMTP:

```php
return [
    'from_email' => 'noreply@suaempresa.com.br',
    'from_name' => 'RH - Sua Empresa',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'seu-email@gmail.com',
    'smtp_password' => 'sua-senha-app',
    'smtp_secure' => 'tls'
];
```

### **3. Testar Envio**

1. Criar um comunicado de teste
2. Publicar
3. Verificar se emails foram recebidos

---

## 🎨 Personalizar Template de Email

### **Via Interface (Recomendado)**

1. Acessar: `Configurações` → `Templates de Email`
2. Buscar template: **"Novo Comunicado"**
3. Clicar em **"Editar"**
4. Modificar:
   - **Assunto**
   - **Corpo HTML**
   - **Corpo Texto** (alternativa para clientes sem suporte HTML)
5. Salvar

### **Via SQL**

```sql
UPDATE email_templates 
SET corpo_html = 'SEU_HTML_AQUI'
WHERE codigo = 'novo_comunicado';
```

---

## 🔍 Fluxo Completo

### **Fluxo de Criação:**

```
1. RH cria comunicado
   ↓
2. Define status como "Publicado"
   ↓
3. Salva
   ↓
4. Sistema insere no banco
   ↓
5. Sistema busca todos os colaboradores ativos
   ↓
6. Para cada colaborador:
   - Prepara variáveis do template
   - Envia email personalizado
   ↓
7. Exibe resultado: "X enviados, Y erros"
```

### **Fluxo de Visualização:**

```
1. Colaborador faz login
   ↓
2. Sistema verifica comunicados não lidos
   ↓
3. Se houver, abre modal automaticamente (1s depois)
   ↓
4. Colaborador lê
   ↓
5. Clica em "Marcar como Lido"
   ↓
6. Sistema registra em comunicados_leitura
   ↓
7. Comunicado não aparece mais
```

---

## 📊 Consultas SQL Úteis

### **Ver comunicados e estatísticas de leitura:**

```sql
SELECT 
    c.id,
    c.titulo,
    c.status,
    c.data_publicacao,
    COUNT(DISTINCT cl.id) as total_visualizacoes,
    SUM(CASE WHEN cl.lido = 1 THEN 1 ELSE 0 END) as total_lidos
FROM comunicados c
LEFT JOIN comunicados_leitura cl ON c.id = cl.comunicado_id
GROUP BY c.id
ORDER BY c.created_at DESC;
```

### **Ver quem já leu um comunicado específico:**

```sql
SELECT 
    cl.*,
    c.nome_completo,
    u.nome as usuario_nome
FROM comunicados_leitura cl
LEFT JOIN colaboradores c ON cl.colaborador_id = c.id
LEFT JOIN usuarios u ON cl.usuario_id = u.id
WHERE cl.comunicado_id = 1
AND cl.lido = 1
ORDER BY cl.data_leitura DESC;
```

### **Ver comunicados não lidos de um colaborador:**

```sql
SELECT c.*
FROM comunicados c
LEFT JOIN comunicados_leitura cl ON c.id = cl.comunicado_id 
    AND cl.colaborador_id = 123
WHERE c.status = 'publicado'
AND (cl.id IS NULL OR cl.lido = 0);
```

---

## 🐛 Troubleshooting

### **Emails não estão sendo enviados**

1. Verificar configuração em `config/email.php`
2. Testar credenciais SMTP manualmente
3. Verificar logs do PHP (`error_log`)
4. Verificar se colaboradores têm email cadastrado:
   ```sql
   SELECT COUNT(*) FROM colaboradores 
   WHERE status = 'ativo' 
   AND (email_pessoal IS NULL OR email_pessoal = '');
   ```

### **Comunicados lidos voltam a aparecer**

✅ **JÁ CORRIGIDO!** Verifique se está usando a versão atualizada de `api/comunicados/listar_nao_lidos.php`

### **Modal não abre automaticamente**

1. Limpar cache do navegador
2. Verificar console do navegador (F12) para erros JavaScript
3. Verificar se `includes/comunicados_modal.php` está incluído no header

---

## 📝 Arquivos do Sistema

### **Backend (PHP)**

| Arquivo | Função |
|---------|--------|
| `pages/comunicados.php` | Listar comunicados (admin) |
| `pages/comunicado_add.php` | Criar comunicado + enviar emails |
| `pages/comunicado_view.php` | Visualizar comunicado individual |
| `api/comunicados/listar_nao_lidos.php` | API para buscar não lidos |
| `api/comunicados/marcar_lido.php` | API para marcar como lido |
| `api/comunicados/registrar_visualizacao.php` | API para registrar view |
| `includes/email_templates.php` | Função `enviar_email_novo_comunicado()` |

### **Frontend (HTML/JS)**

| Arquivo | Função |
|---------|--------|
| `includes/comunicados_modal.php` | Modal que aparece ao logar |

### **Banco de Dados**

| Arquivo | Função |
|---------|--------|
| `migracao_comunicados.sql` | Cria tabelas principais |
| `migracao_template_comunicado_email.sql` | Adiciona template de email |

---

## 🎯 Próximas Melhorias Sugeridas

1. **Segmentação de Envio**
   - Enviar apenas para setores específicos
   - Enviar apenas para cargos específicos
   - Enviar apenas para unidades específicas

2. **Agendamento de Envio**
   - Agendar envio de email para data/hora futura
   - Não enviar emails imediatamente, mas no horário agendado

3. **Anexos**
   - Permitir anexar PDFs e outros documentos
   - Enviar anexos nos emails

4. **Confirmação de Leitura Obrigatória**
   - Comunicados "urgentes" que bloqueiam acesso até serem lidos
   - Relatório de quem não leu

5. **Categorias**
   - Categorizar comunicados (RH, TI, Financeiro, etc)
   - Filtrar por categoria

6. **Notificação Push**
   - Além de email, enviar notificação push via OneSignal
   - Notificar em tempo real colaboradores online

---

## ✅ Checklist de Funcionalidades

- [x] Criar comunicados com editor rico
- [x] Upload de imagens
- [x] Status (rascunho/publicado)
- [x] Data de publicação agendada
- [x] Data de expiração
- [x] Modal automático ao logar
- [x] Marcar como lido
- [x] Rastreamento de visualizações
- [x] **Envio automático de emails** 🆕
- [x] **Correção: Lidos não voltam a aparecer** 🆕
- [x] Estatísticas de leitura
- [x] Editar/Excluir comunicados
- [ ] Segmentação de público (futura)
- [ ] Anexos (futura)
- [ ] Categorias (futura)
- [ ] Push notifications (futura)

---

**✅ Sistema de Comunicados Completo e Funcional!** 📢
