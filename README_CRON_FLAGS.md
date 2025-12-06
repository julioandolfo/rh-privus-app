# ⏰ Configuração de Cron para Expiração de Flags

## 📋 Sobre

O sistema de flags possui **duas formas** de verificar e expirar flags vencidas:

1. **Verificação ao Acessar** (fallback)
   - Verifica flags ao acessar páginas que mostram flags
   - Garante dados atualizados mesmo sem cron configurado
   - Funciona, mas pode ser menos eficiente com muitos acessos

2. **Cron Automático** (recomendado) ⭐
   - Executa verificação diária automaticamente
   - Mais eficiente e não depende de acessos
   - Processa todas as flags de uma vez

## 🚀 Configuração do Cron (Recomendado)

### Passo 1: Verificar caminho do PHP

```bash
which php
# ou
whereis php
```

Exemplo de saída: `/usr/bin/php` ou `C:\laragon\bin\php\php-8.x.x\php.exe`

### Passo 2: Verificar caminho completo do script

Caminho do script: `cron/verificar_expiracao_flags.php`

Caminho completo (exemplo): `/var/www/rh-privus/cron/verificar_expiracao_flags.php`

### Passo 3: Configurar Cron

#### Linux/Mac:

```bash
crontab -e
```

Adicione a linha (executa diariamente às 00:00):

```cron
0 0 * * * /usr/bin/php /caminho/completo/para/rh-privus/cron/verificar_expiracao_flags.php >> /var/log/flags_expiration.log 2>&1
```

**Exemplo prático:**

```cron
0 0 * * * /usr/bin/php /var/www/rh-privus/cron/verificar_expiracao_flags.php >> /var/log/flags_expiration.log 2>&1
```

#### Windows (Task Scheduler):

1. Abra o **Agendador de Tarefas** (Task Scheduler)
2. Criar Tarefa Básica
3. Nome: "Verificar Expiração de Flags"
4. Gatilho: Diariamente às 00:00
5. Ação: Iniciar um programa
6. Programa: `C:\laragon\bin\php\php-8.x.x\php.exe`
7. Argumentos: `C:\laragon\www\rh-privus\cron\verificar_expiracao_flags.php`
8. Iniciar em: `C:\laragon\www\rh-privus`

### Passo 4: Testar Manualmente

Execute o script manualmente para verificar se funciona:

```bash
php cron/verificar_expiracao_flags.php
```

Saída esperada:
```
Iniciando verificação de expiração de flags...
Flags expiradas: X

Verificação concluída com sucesso!
```

## 📊 Horários Recomendados

- **00:00** (meia-noite) - Recomendado
- **01:00** - Alternativa (menos carga no servidor)
- **02:00** - Alternativa

## 🔍 Verificar se Cron Está Funcionando

### Ver logs do cron:

```bash
tail -f /var/log/flags_expiration.log
```

### Verificar última execução:

```bash
grep "Flags expiradas" /var/log/flags_expiration.log | tail -1
```

### Verificar se há flags vencidas no banco:

```sql
SELECT COUNT(*) as flags_vencidas
FROM ocorrencias_flags
WHERE status = 'ativa' 
AND data_validade < CURDATE();
```

Se retornar > 0 e o cron está configurado, verifique os logs para erros.

## ⚙️ Como Funciona

### Com Cron Configurado:
1. ✅ Cron executa diariamente às 00:00
2. ✅ Processa todas as flags vencidas
3. ✅ Atualiza status para "expirada"
4. ✅ Registra histórico
5. ✅ Usuários veem dados atualizados ao acessar

### Sem Cron (Fallback):
1. ⚠️ Verificação acontece ao acessar páginas de flags
2. ⚠️ Processa apenas flags do colaborador específico (ou todas se ADMIN)
3. ⚠️ Funciona, mas pode ser menos eficiente
4. ⚠️ Dados podem não estar atualizados se ninguém acessar

## 🎯 Recomendação

**SEMPRE configure o cron** para garantir:
- ✅ Performance melhor
- ✅ Dados sempre atualizados
- ✅ Processamento eficiente
- ✅ Não depende de acessos de usuários

## 🔧 Troubleshooting

### Cron não executa:

1. Verificar permissões do arquivo:
```bash
chmod +x cron/verificar_expiracao_flags.php
```

2. Verificar se PHP CLI está funcionando:
```bash
php -v
```

3. Verificar logs do cron:
```bash
grep CRON /var/log/syslog
```

### Erro de permissão:

Verificar se o usuário do cron tem permissão para:
- Ler arquivos do projeto
- Conectar ao banco de dados
- Escrever logs (se configurado)

### Erro de conexão ao banco:

Verificar se `config/db.php` está acessível e configurado corretamente.

## 📝 Notas Importantes

- O sistema funciona **mesmo sem cron** (verificação ao acessar)
- Cron é **recomendado** para melhor performance
- Verificação ao acessar é **otimizada** (só verifica flags do colaborador específico quando necessário)
- Flags são expiradas **automaticamente** em ambos os casos

---

**Configure o cron para melhor experiência!** ⭐

