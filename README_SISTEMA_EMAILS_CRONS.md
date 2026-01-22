# 📧 Sistema de Emails e Cron Jobs

## 📋 Visão Geral

O sistema possui um **módulo completo de emails automatizados** com templates personalizáveis e **cron jobs** para envio periódico de alertas e notificações.

---

## 🎨 Sistema de Templates de Email

### Como Funciona

1. **Templates armazenados no banco de dados** (`email_templates`)
2. **Variáveis dinâmicas** usando o formato `{variavel_nome}`
3. **Suporte HTML e texto puro** (alternativa para clientes que não suportam HTML)
4. **Ativação/Desativação** individual de cada template
5. **Gestão via interface** (`pages/templates_email.php`)

### Templates Disponíveis

#### 1. **Novo Colaborador** (`novo_colaborador`)
- **Quando**: Enviado automaticamente quando um colaborador é cadastrado
- **Função**: `enviar_email_novo_colaborador($colaborador_id, $senha_plana = null)`
- **Variáveis**:
  - `{nome_completo}` - Nome do colaborador
  - `{empresa_nome}` - Nome da empresa
  - `{cargo_nome}` - Cargo
  - `{setor_nome}` - Setor
  - `{data_inicio}` - Data de início
  - `{tipo_contrato}` - Tipo de contrato
  - `{usuario_login}` - Login (CPF ou email)
  - `{senha}` - Senha (se fornecida)
  - `{dados_acesso_html}` - Bloco HTML com dados de acesso

#### 2. **Nova Promoção** (`nova_promocao`)
- **Quando**: Enviado automaticamente quando uma promoção é registrada
- **Função**: `enviar_email_nova_promocao($promocao_id)`
- **Variáveis**:
  - `{nome_completo}` - Nome do colaborador
  - `{data_promocao}` - Data da promoção
  - `{salario_anterior}` - Salário anterior formatado
  - `{salario_novo}` - Novo salário formatado
  - `{motivo}` - Motivo da promoção
  - `{observacoes}` - Observações (HTML)
  - `{empresa_nome}` - Nome da empresa

#### 3. **Fechamento de Pagamento** (`fechamento_pagamento`)
- **Quando**: Enviado quando um fechamento de pagamento é realizado
- **Função**: `enviar_email_fechamento_pagamento($fechamento_id, $colaborador_id)`
- **Variáveis**:
  - `{nome_completo}` - Nome do colaborador
  - `{mes_referencia}` - Mês/Ano do pagamento
  - `{salario_base}` - Salário base
  - `{horas_extras}` - Quantidade de horas extras
  - `{valor_horas_extras}` - Valor das horas extras
  - `{descontos}` - Descontos aplicados
  - `{adicionais}` - Adicionais
  - `{valor_total}` - Valor total
  - `{data_fechamento}` - Data do fechamento
  - `{observacoes}` - Observações (HTML)
  - `{empresa_nome}` - Nome da empresa

#### 4. **Ocorrência Registrada** (`ocorrencia`)
- **Quando**: Enviado quando uma ocorrência é registrada (se habilitado)
- **Função**: `enviar_email_ocorrencia($ocorrencia_id)`
- **Variáveis**:
  - `{nome_completo}` - Nome do colaborador
  - `{tipo_ocorrencia}` - Tipo de ocorrência
  - `{data_ocorrencia}` - Data da ocorrência
  - `{hora_ocorrencia}` - Hora (HTML)
  - `{tempo_atraso}` - Tempo de atraso (HTML)
  - `{severidade}` - Severidade (HTML)
  - `{status_aprovacao}` - Status (HTML)
  - `{tags}` - Tags (HTML)
  - `{valor_desconto}` - Desconto calculado (HTML)
  - `{descricao}` - Descrição da ocorrência
  - `{usuario_registro}` - Quem registrou
  - `{data_registro}` - Data/hora do registro
  - `{empresa_nome}` - Nome da empresa
  - `{setor_nome}` - Setor
  - `{cargo_nome}` - Cargo

#### 5. **Horas Extras** (`horas_extras`)
- **Quando**: Enviado quando horas extras são registradas (se template ativo)
- **Função**: `enviar_email_horas_extras($hora_extra_id)`
- **Variáveis**:
  - `{nome_completo}` - Nome do colaborador
  - `{data_trabalho}` - Data do trabalho
  - `{quantidade_horas}` - Quantidade formatada (ex: "2h 30min")
  - `{tipo_pagamento_html}` - Dinheiro ou Banco de Horas (HTML)
  - `{valor_hora_html}` - Valor da hora (HTML)
  - `{percentual_adicional_html}` - % adicional (HTML)
  - `{valor_total_html}` - Valor total (HTML)
  - `{saldo_banco_html}` - Saldo do banco de horas (HTML)
  - `{observacoes_html}` - Observações (HTML)
  - Versões `_texto` das variáveis acima para email texto puro
  - `{usuario_registro}` - Quem registrou
  - `{data_registro}` - Data/hora do registro
  - `{empresa_nome}` - Nome da empresa
  - `{setor_nome}` - Setor
  - `{cargo_nome}` - Cargo
  - `{ano_atual}` - Ano atual

#### 6. **Alerta de Inatividade** (`alerta_inatividade`) 🆕
- **Quando**: Enviado periodicamente via cron para colaboradores inativos
- **Cron**: `processar_alertas_inatividade.php`
- **Frequência**: Diária (padrão: 9h)
- **Variáveis**:
  - `{nome_completo}` - Nome do colaborador
  - `{dias_inativo}` - Quantidade de dias sem acessar
  - `{data_ultimo_acesso}` - Data do último acesso
  - `{sistema_url}` - URL do sistema
  - `{empresa_nome}` - Nome da empresa

#### 7. **Alerta de Emoções** (`alerta_emocoes`) 🆕
- **Quando**: Enviado periodicamente via cron para colaboradores que não registram emoções
- **Cron**: `processar_alertas_emocoes.php`
- **Frequência**: Diária (padrão: 9h)
- **Variáveis**:
  - `{nome_completo}` - Nome do colaborador
  - `{dias_sem_registro}` - Dias sem registrar emoções
  - `{data_ultimo_registro}` - Data do último registro
  - `{sistema_url}` - URL do sistema
  - `{empresa_nome}` - Nome da empresa

---

## ⏰ Cron Jobs

### 1. **Verificar Expiração de Flags** 
**Arquivo**: `cron/verificar_expiracao_flags.php`

**Função**: Expira flags de ocorrências que venceram

**Frequência Recomendada**: Diária (00:00)

**Cron**:
```bash
0 0 * * * /usr/bin/php /caminho/rh-privus/cron/verificar_expiracao_flags.php >> /var/log/flags_expiration.log 2>&1
```

---

### 2. **Processar Alertas LMS**
**Arquivo**: `cron/processar_alertas_lms.php`

**Função**: Processa alertas de cursos obrigatórios e atualiza status

**Frequência Recomendada**: A cada 6 horas

**Cron**:
```bash
0 */6 * * * /usr/bin/php /caminho/rh-privus/cron/processar_alertas_lms.php >> /var/log/lms_alertas.log 2>&1
```

---

### 3. **Processar Fechamentos Recorrentes**
**Arquivo**: `cron/processar_fechamentos_recorrentes.php`

**Função**: Processa fechamentos de pagamento recorrentes

**Frequência Recomendada**: Diária (01:00)

**Cron**:
```bash
0 1 * * * /usr/bin/php /caminho/rh-privus/cron/processar_fechamentos_recorrentes.php >> /var/log/fechamentos.log 2>&1
```

---

### 4. **Processar Notificações de Anotações**
**Arquivo**: `cron/processar_notificacoes_anotacoes.php`

**Função**: Envia notificações de anotações pendentes

**Frequência Recomendada**: Diária (09:00)

**Cron**:
```bash
0 9 * * * /usr/bin/php /caminho/rh-privus/cron/processar_notificacoes_anotacoes.php >> /var/log/notificacoes_anotacoes.log 2>&1
```

---

### 5. **Alertar Inatividade** 🆕
**Arquivo**: `cron/processar_alertas_inatividade.php`

**Função**: Envia emails para colaboradores que não acessam o sistema há 7+ dias

**Frequência Recomendada**: Diária (09:00)

**Configurações Editáveis**:
```php
$DIAS_INATIVIDADE = 7; // Dias sem acessar para enviar alerta
$LIMITE_ALERTAS_POR_EXECUCAO = 50; // Limite de emails por execução
```

**Cron**:
```bash
0 9 * * * /usr/bin/php /caminho/rh-privus/cron/processar_alertas_inatividade.php >> /var/log/alertas_inatividade.log 2>&1
```

**Como Funciona**:
1. Busca usuários/colaboradores ativos que não acessam há X dias
2. Considera `ultimo_login` (tabela `usuarios`) e último acesso (tabela `acessos`)
3. Verifica se já foi enviado alerta nos últimos 7 dias (evita spam)
4. Envia email usando template `alerta_inatividade`
5. Registra envio na tabela `alertas_enviados`

**Prevenção de Duplicatas**:
- Só envia 1 alerta a cada 7 dias para o mesmo usuário
- Tabela `alertas_enviados` controla histórico

---

### 6. **Alertar Ausência de Emoções** 🆕
**Arquivo**: `cron/processar_alertas_emocoes.php`

**Função**: Envia emails para colaboradores que não registram emoções há 7+ dias

**Frequência Recomendada**: Diária (09:00)

**Configurações Editáveis**:
```php
$DIAS_SEM_EMOCAO = 7; // Dias sem registrar emoção para enviar alerta
$LIMITE_ALERTAS_POR_EXECUCAO = 50; // Limite de emails por execução
```

**Cron**:
```bash
0 9 * * * /usr/bin/php /caminho/rh-privus/cron/processar_alertas_emocoes.php >> /var/log/alertas_emocoes.log 2>&1
```

**Como Funciona**:
1. Busca colaboradores ativos (role COLABORADOR) que:
   - Nunca registraram emoção E foram criados há mais de X dias
   - OU registraram mas há mais de X dias
2. Verifica se já foi enviado alerta nos últimos 7 dias (evita spam)
3. Envia email usando template `alerta_emocoes`
4. Registra envio na tabela `alertas_enviados`

**Prevenção de Duplicatas**:
- Só envia 1 alerta a cada 7 dias para o mesmo colaborador
- Tabela `alertas_enviados` controla histórico

---

## 📦 Instalação

### 1. Executar Migração de Templates

```bash
# Navegar até o diretório do projeto
cd /caminho/rh-privus

# Executar migração (se ainda não foi executada)
mysql -u usuario -p banco_de_dados < migracao_templates_alertas_periodicos.sql
```

**Ou via phpMyAdmin**: Importar `migracao_templates_alertas_periodicos.sql`

### 2. Configurar Crons

#### Linux/Mac:

```bash
# Editar crontab
crontab -e

# Adicionar as linhas:
0 0 * * * /usr/bin/php /var/www/rh-privus/cron/verificar_expiracao_flags.php >> /var/log/flags_expiration.log 2>&1
0 */6 * * * /usr/bin/php /var/www/rh-privus/cron/processar_alertas_lms.php >> /var/log/lms_alertas.log 2>&1
0 1 * * * /usr/bin/php /var/www/rh-privus/cron/processar_fechamentos_recorrentes.php >> /var/log/fechamentos.log 2>&1
0 9 * * * /usr/bin/php /var/www/rh-privus/cron/processar_notificacoes_anotacoes.php >> /var/log/notificacoes_anotacoes.log 2>&1
0 9 * * * /usr/bin/php /var/www/rh-privus/cron/processar_alertas_inatividade.php >> /var/log/alertas_inatividade.log 2>&1
0 9 * * * /usr/bin/php /var/www/rh-privus/cron/processar_alertas_emocoes.php >> /var/log/alertas_emocoes.log 2>&1
```

#### Windows (Laragon/XAMPP):

1. Abra **Agendador de Tarefas** (Task Scheduler)
2. Criar Tarefa Básica para cada cron
3. **Exemplo para Alertas de Inatividade**:
   - **Nome**: "RH - Alertas de Inatividade"
   - **Gatilho**: Diariamente às 09:00
   - **Ação**: Iniciar um programa
   - **Programa**: `C:\laragon\bin\php\php-8.x.x\php.exe`
   - **Argumentos**: `C:\laragon\www\rh-privus\cron\processar_alertas_inatividade.php`
   - **Iniciar em**: `C:\laragon\www\rh-privus`

### 3. Testar Manualmente

```bash
# Testar alertas de inatividade
php cron/processar_alertas_inatividade.php

# Testar alertas de emoções
php cron/processar_alertas_emocoes.php
```

**Saída Esperada**:
```
=== PROCESSAMENTO DE ALERTAS DE INATIVIDADE ===
Data/Hora: 2026-01-13 09:00:00
Dias de inatividade: 7
Limite de alertas: 50

Usuários/Colaboradores inativos encontrados: 3

  [ENVIANDO] João Silva (joao@email.com) - 10 dias inativo
  [OK] Email enviado com sucesso

  [ENVIANDO] Maria Santos (maria@email.com) - 14 dias inativo
  [OK] Email enviado com sucesso

=== RESUMO ===
Alertas enviados: 2
Erros: 0

Processamento concluído com sucesso!
```

---

## 🎯 Boas Práticas

### Gerenciamento de Templates

1. **Sempre teste templates** antes de ativar
2. **Use variáveis descritivas** - facilita manutenção
3. **Inclua versão texto** - fallback para clientes sem HTML
4. **Mantenha design responsivo** - muitos usuários acessam via mobile

### Configuração de Crons

1. **Use logs separados** - facilita debugging
2. **Configure horários estratégicos**:
   - Alertas de engajamento: 9h (início do expediente)
   - Processos pesados: 1h-3h (madrugada)
   - Verificações diárias: 0h (meia-noite)
3. **Monitore logs regularmente**
4. **Ajuste limites conforme necessário**

### Prevenção de Spam

- ✅ Controle de envio (máx 1 alerta a cada 7 dias)
- ✅ Limite de emails por execução
- ✅ Templates amigáveis e informativos
- ✅ Opção de desativar templates individualmente

---

## 🔍 Monitoramento

### Ver Logs

```bash
# Alertas de inatividade
tail -f /var/log/alertas_inatividade.log

# Alertas de emoções
tail -f /var/log/alertas_emocoes.log

# Todos os logs de cron
tail -f /var/log/*.log
```

### Verificar Última Execução

```bash
grep "=== RESUMO ===" /var/log/alertas_inatividade.log | tail -5
```

### Consultas SQL Úteis

```sql
-- Ver alertas enviados hoje
SELECT * FROM alertas_enviados 
WHERE DATE(data_envio) = CURDATE()
ORDER BY data_envio DESC;

-- Contar alertas por tipo (últimos 30 dias)
SELECT tipo_alerta, COUNT(*) as total
FROM alertas_enviados 
WHERE data_envio >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY tipo_alerta;

-- Ver usuários inativos há mais de 7 dias
SELECT u.nome, u.email, u.ultimo_login,
       DATEDIFF(CURDATE(), u.ultimo_login) as dias_inativo
FROM usuarios u
WHERE u.status = 'ativo'
AND DATEDIFF(CURDATE(), u.ultimo_login) >= 7
ORDER BY dias_inativo DESC;

-- Ver colaboradores sem emoções registradas
SELECT c.nome_completo, c.email_pessoal,
       MAX(e.data_registro) as ultimo_registro,
       DATEDIFF(CURDATE(), MAX(e.data_registro)) as dias_sem_registro
FROM colaboradores c
LEFT JOIN emocoes e ON c.id = e.colaborador_id
WHERE c.status = 'ativo'
GROUP BY c.id
HAVING ultimo_registro IS NULL OR dias_sem_registro >= 7
ORDER BY dias_sem_registro DESC;
```

---

## 🚀 Próximas Melhorias

### Sugestões de Novos Alertas

1. **Aniversariantes do Mês**
   - Email automático no dia do aniversário
   - Cron diário verificando aniversários

2. **Documentos Vencendo**
   - Alertar RH sobre documentos próximos do vencimento
   - Cron semanal

3. **Metas Não Cumpridas**
   - Alerta para gestores sobre metas atrasadas
   - Cron semanal

4. **Cursos Obrigatórios Próximos do Prazo**
   - Email 7 dias antes do vencimento
   - Já implementado em `processar_alertas_lms.php`

5. **Feedbacks Pendentes**
   - Lembrar gestores de feedbacks não respondidos
   - Cron semanal

### Melhorias nos Templates

1. Adicionar botão de **"Não quero receber mais"** (unsubscribe)
2. Personalização por empresa (logo, cores)
3. Variáveis de contato do RH
4. Links diretos para funcionalidades específicas

---

## 📞 Suporte

- Para adicionar novos templates: edite `pages/templates_email.php`
- Para criar novos crons: use exemplos em `cron/` como base
- Para testar emails: use `includes/email_templates.php` → função `enviar_email_template()`

---

**✅ Sistema de Emails e Crons completamente operacional!**
