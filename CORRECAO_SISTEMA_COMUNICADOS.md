# 🔧 Correção do Sistema de Comunicados

## ⚡ Início Rápido - 3 Passos

### 1️⃣ Executar Migração SQL

```bash
cd C:\laragon\www\rh-privus
C:\laragon\bin\mysql\mysql-8.x.x\bin\mysql.exe -u root rh_privus < migracao_template_comunicado_email.sql
```

**Ou via phpMyAdmin:**
Importar arquivo: `migracao_template_comunicado_email.sql`

---

### 2️⃣ Arquivos Já Modificados ✅

Os seguintes arquivos **JÁ FORAM ATUALIZADOS** automaticamente:

- ✅ `api/comunicados/listar_nao_lidos.php` - Bug corrigido
- ✅ `includes/email_templates.php` - Nova função de envio
- ✅ `pages/comunicado_add.php` - Integração com email

**Não precisa fazer nada manualmente!**

---

### 3️⃣ Testar

1. Acessar: **Comunicados** → **Adicionar Comunicado**
2. Criar comunicado de teste
3. Selecionar status: **"Publicado"**
4. Salvar

**Resultado esperado:**
```
✅ Comunicado criado e emails enviados! (X enviados, Y erros)
```

---

## 🐛 Problemas Corrigidos

### ❌ **Problema 1: Comunicados lidos voltavam a aparecer**

**Sintoma:**
- Colaborador marca comunicado como "Lido"
- Após algumas horas, o mesmo comunicado volta a aparecer

**Causa:**
```php
// Código antigo (PROBLEMÁTICO)
OR (cl.lido = 1 AND TIMESTAMPDIFF(HOUR, cl.data_visualizacao, NOW()) >= 6)
```

O sistema estava configurado para reexibir comunicados lidos após 6 horas.

**✅ Solução:**
```php
// Código novo (CORRIGIDO)
AND (
    cl.id IS NULL -- Nunca foi visualizado
    OR (cl.lido = 0) -- Não foi marcado como lido
)
```

Agora comunicados marcados como lidos **NUNCA** voltam a aparecer.

**Arquivo:** `api/comunicados/listar_nao_lidos.php`

---

### ❌ **Problema 2: Emails não eram enviados**

**Sintoma:**
- Comunicado era criado
- Mas nenhum email era enviado para colaboradores

**✅ Solução Implementada:**

1. **Criado template de email profissional:**
   - Design moderno com gradiente roxo/azul
   - Responsivo (mobile-friendly)
   - Preview do conteúdo
   - Botão de call-to-action

2. **Criada função de envio:**
   - `enviar_email_novo_comunicado($comunicado_id)`
   - Busca todos colaboradores ativos
   - Envia email personalizado para cada um
   - Retorna estatísticas de envio

3. **Integrado ao processo de criação:**
   - Quando status é "Publicado", emails são enviados automaticamente
   - Exibe mensagem de sucesso com estatísticas

**Arquivos:**
- `migracao_template_comunicado_email.sql` (template)
- `includes/email_templates.php` (função)
- `pages/comunicado_add.php` (integração)

---

## 📧 Como Funciona o Envio de Emails

### **Fluxo Automático:**

```
1. RH cria comunicado
   ↓
2. Seleciona status "Publicado"
   ↓
3. Clica em "Salvar"
   ↓
4. Sistema salva no banco
   ↓
5. Sistema verifica: status === 'publicado'?
   ✅ SIM → Envia emails
   ❌ NÃO → Apenas salva
   ↓
6. Busca colaboradores ativos com email:
   - Tabela: colaboradores (email_pessoal)
   - Tabela: usuarios (email)
   ↓
7. Para cada colaborador:
   - Monta email personalizado
   - Envia via SMTP
   - Registra sucesso/erro
   ↓
8. Exibe resultado:
   "Comunicado criado e emails enviados! (25 enviados, 0 erros)"
```

### **Quando NÃO envia emails:**

- ✅ Status é "Rascunho"
- ✅ Comunicado é editado (só envia na criação)
- ✅ Não há colaboradores com email

---

## 📋 Checklist de Verificação

### **Antes de Testar:**

- [ ] Migração SQL executada
- [ ] Email SMTP configurado (`config/email.php`)
- [ ] Pelo menos 1 colaborador ativo com email cadastrado

### **Teste 1: Verificar Bug Corrigido**

1. [ ] Logar como colaborador
2. [ ] Ver comunicado no modal
3. [ ] Clicar em "Marcar como Lido"
4. [ ] Fazer logout
5. [ ] Logar novamente
6. [ ] **✅ Comunicado NÃO deve aparecer novamente**

### **Teste 2: Verificar Envio de Emails**

1. [ ] Logar como RH/Admin
2. [ ] Criar novo comunicado
3. [ ] Status: **"Publicado"**
4. [ ] Salvar
5. [ ] **✅ Ver mensagem: "X emails enviados"**
6. [ ] Verificar caixa de email dos colaboradores
7. [ ] **✅ Email deve ter chegado**

---

## 📊 Verificar Colaboradores com Email

```sql
-- Ver quantos colaboradores têm email
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN email_pessoal IS NOT NULL AND email_pessoal != '' THEN 1 ELSE 0 END) as com_email,
    SUM(CASE WHEN email_pessoal IS NULL OR email_pessoal = '' THEN 1 ELSE 0 END) as sem_email
FROM colaboradores
WHERE status = 'ativo';
```

```sql
-- Ver lista de colaboradores com email
SELECT id, nome_completo, email_pessoal, status
FROM colaboradores
WHERE status = 'ativo'
AND email_pessoal IS NOT NULL
AND email_pessoal != ''
ORDER BY nome_completo;
```

---

## 🔍 Logs e Debug

### **Ver último comunicado criado:**

```sql
SELECT * FROM comunicados 
ORDER BY created_at DESC 
LIMIT 1;
```

### **Ver leituras do último comunicado:**

```sql
SELECT 
    cl.*,
    c.nome_completo,
    u.nome as usuario_nome
FROM comunicados_leitura cl
LEFT JOIN colaboradores c ON cl.colaborador_id = c.id
LEFT JOIN usuarios u ON cl.usuario_id = u.id
WHERE cl.comunicado_id = (SELECT MAX(id) FROM comunicados)
ORDER BY cl.created_at DESC;
```

### **Verificar template de email:**

```sql
SELECT * FROM email_templates 
WHERE codigo = 'novo_comunicado';
```

Se retornar vazio, **executar migração novamente**.

---

## ⚙️ Configuração de Email (Se Necessário)

Editar: `config/email.php`

```php
<?php
return [
    'from_email' => 'noreply@suaempresa.com.br',
    'from_name' => 'RH - Sua Empresa',
    'smtp_host' => 'smtp.gmail.com',  // ou seu servidor SMTP
    'smtp_port' => 587,
    'smtp_username' => 'seu-email@gmail.com',
    'smtp_password' => 'sua-senha-ou-app-password',
    'smtp_secure' => 'tls'  // ou 'ssl'
];
```

**Gmail App Password:**
1. Acessar: https://myaccount.google.com/security
2. Ativar "Verificação em 2 etapas"
3. Criar "Senhas de app"
4. Usar a senha gerada em `smtp_password`

---

## 🎨 Personalizar Email (Opcional)

### **Via Interface:**
1. Acessar: `Configurações` → `Templates de Email`
2. Buscar: **"Novo Comunicado"**
3. Editar HTML/Texto
4. Salvar

### **Via SQL:**
```sql
UPDATE email_templates 
SET 
    assunto = 'Novo comunicado: {titulo}',
    corpo_html = '...SEU HTML...'
WHERE codigo = 'novo_comunicado';
```

---

## 🚀 Resultado Final

### **Antes (Problemas):**
- ❌ Comunicados lidos voltavam a aparecer
- ❌ Nenhum email era enviado

### **Depois (Corrigido):**
- ✅ Comunicados lidos **NUNCA** voltam a aparecer
- ✅ Emails enviados **AUTOMATICAMENTE** para todos
- ✅ Estatísticas de envio exibidas
- ✅ Design profissional do email
- ✅ Preview do conteúdo no email
- ✅ Link direto para visualizar

---

## 📞 Suporte

**Problemas? Verifique:**

1. ✅ Migração SQL executada
2. ✅ Configuração SMTP correta
3. ✅ Colaboradores têm email cadastrado
4. ✅ Status do comunicado é "Publicado"
5. ✅ Logs do PHP (`error_log`)

**Documentação Completa:**
- `README_SISTEMA_COMUNICADOS.md` - Guia completo

---

**✅ Sistema Corrigido e Funcionando!** 🎉
