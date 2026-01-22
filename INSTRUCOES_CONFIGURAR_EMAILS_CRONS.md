# 🚀 Instruções Rápidas: Configurar Emails e Crons

## ⚡ Início Rápido

### 1️⃣ Instalar Templates de Email (1 comando)

```bash
# Navegar até o projeto
cd /caminho/rh-privus

# Executar migração
mysql -u SEU_USUARIO -p SEU_BANCO < migracao_templates_alertas_periodicos.sql
```

**No Windows (Laragon):**
```bash
cd C:\laragon\www\rh-privus
C:\laragon\bin\mysql\mysql-8.x.x\bin\mysql.exe -u root rh_privus < migracao_templates_alertas_periodicos.sql
```

### 2️⃣ Testar Scripts Manualmente

```bash
# Testar alerta de inatividade
php cron/processar_alertas_inatividade.php

# Testar alerta de emoções
php cron/processar_alertas_emocoes.php
```

### 3️⃣ Configurar Crons Automáticos

#### 🐧 Linux/Mac

```bash
crontab -e
```

Adicionar:
```bash
# Alertas de inatividade (diário às 9h)
0 9 * * * /usr/bin/php /var/www/rh-privus/cron/processar_alertas_inatividade.php >> /var/log/alertas_inatividade.log 2>&1

# Alertas de emoções (diário às 9h)  
0 9 * * * /usr/bin/php /var/www/rh-privus/cron/processar_alertas_emocoes.php >> /var/log/alertas_emocoes.log 2>&1
```

#### 🪟 Windows (Task Scheduler)

**Para Alertas de Inatividade:**
1. Abrir **Agendador de Tarefas**
2. Criar Tarefa Básica
3. Nome: `RH - Alertas de Inatividade`
4. Gatilho: Diariamente às 09:00
5. Ação: Iniciar um programa
6. Programa: `C:\laragon\bin\php\php-8.x.x\php.exe`
7. Argumentos: `C:\laragon\www\rh-privus\cron\processar_alertas_inatividade.php`
8. Iniciar em: `C:\laragon\www\rh-privus`

**Repetir para Alertas de Emoções** (mesmo processo, mudando nome e arquivo)

---

## 📋 O Que Foi Criado

### ✅ Templates de Email

| Template | Código | Quando Envia |
|----------|--------|--------------|
| 🎉 Nova Promoção | `nova_promocao` | Ao registrar promoção (automático) |
| 😴 Inatividade | `alerta_inatividade` | Não acessa há 7+ dias (cron) |
| 💙 Sem Emoções | `alerta_emocoes` | Não registra emoção há 7+ dias (cron) |

### ✅ Cron Jobs

| Script | Função | Quando Executar |
|--------|--------|-----------------|
| `processar_alertas_inatividade.php` | Alertar quem não acessa | Diário 9h |
| `processar_alertas_emocoes.php` | Alertar sem registro de emoções | Diário 9h |

### ✅ Arquivos Criados

```
📁 rh-privus/
├── 📄 migracao_templates_alertas_periodicos.sql  ← MIGRAÇÃO
├── 📁 cron/
│   ├── 📄 processar_alertas_inatividade.php      ← CRON INATIVIDADE
│   └── 📄 processar_alertas_emocoes.php          ← CRON EMOÇÕES
├── 📄 README_SISTEMA_EMAILS_CRONS.md             ← DOCUMENTAÇÃO COMPLETA
└── 📄 INSTRUCOES_CONFIGURAR_EMAILS_CRONS.md      ← ESTE ARQUIVO
```

---

## ⚙️ Personalizar Configurações

### Alterar Dias para Alerta de Inatividade

Editar `cron/processar_alertas_inatividade.php`:

```php
$DIAS_INATIVIDADE = 7; // Mudar para 5, 10, 14, etc
```

### Alterar Dias para Alerta de Emoções

Editar `cron/processar_alertas_emocoes.php`:

```php
$DIAS_SEM_EMOCAO = 7; // Mudar para 5, 10, 14, etc
```

### Limite de Emails por Execução

Ambos os scripts:

```php
$LIMITE_ALERTAS_POR_EXECUCAO = 50; // Mudar conforme necessário
```

---

## 🔍 Como Verificar se Está Funcionando

### Ver Logs (Linux/Mac)

```bash
tail -f /var/log/alertas_inatividade.log
tail -f /var/log/alertas_emocoes.log
```

### Ver Alertas Enviados (SQL)

```sql
-- Ver todos os alertas enviados hoje
SELECT * FROM alertas_enviados 
WHERE DATE(data_envio) = CURDATE()
ORDER BY data_envio DESC;
```

### Testar Email de Promoção

No código de `pages/promocoes.php`, a função já está sendo chamada:

```php
enviar_email_nova_promocao($promocao_id);
```

✅ **Funciona automaticamente ao registrar uma promoção!**

---

## ❓ FAQ

### Os emails serão enviados em spam?

- Configure SPF/DKIM no servidor de email
- Veja configurações em `config/email.php`
- Use serviço de SMTP confiável (Gmail, SendGrid, etc)

### Posso desativar um template?

Sim! Acesse `pages/templates_email.php` no sistema e desative o template desejado.

### Como evitar enviar muitos emails?

O sistema já tem proteção:
- ✅ Máximo 1 alerta a cada 7 dias por pessoa
- ✅ Limite de 50 emails por execução
- ✅ Registro na tabela `alertas_enviados`

### Posso personalizar os emails?

Sim! Acesse `pages/templates_email.php` e edite:
- Assunto
- Corpo HTML
- Corpo texto

### Como mudar o horário dos crons?

Edite o crontab (Linux) ou Agendador de Tarefas (Windows):
- `0 9` = 9h
- `0 14` = 14h (2 PM)
- `30 8` = 8:30h

---

## 🎯 Checklist de Instalação

- [ ] Executar `migracao_templates_alertas_periodicos.sql`
- [ ] Testar manualmente `processar_alertas_inatividade.php`
- [ ] Testar manualmente `processar_alertas_emocoes.php`
- [ ] Configurar cron/tarefa para inatividade
- [ ] Configurar cron/tarefa para emoções
- [ ] Verificar logs após primeira execução
- [ ] Testar envio de email de promoção

---

## 📞 Precisa de Ajuda?

Consulte a documentação completa: `README_SISTEMA_EMAILS_CRONS.md`

---

**✅ Sistema pronto para uso!** 🚀
