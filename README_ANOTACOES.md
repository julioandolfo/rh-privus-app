# Sistema de Anotações - RH Privus

## 📝 Descrição

Sistema completo de anotações com notificações por email e push, permitindo criar, editar, excluir e gerenciar anotações do sistema.

## 🚀 Funcionalidades

### ✅ Funcionalidades Principais

1. **Criação de Anotações**
   - Título e conteúdo
   - Tipo (geral, lembrete, importante, urgente, informação)
   - Prioridade (baixa, média, alta, urgente)
   - Categoria personalizada
   - Tags (separadas por vírgula)
   - Data de vencimento
   - Fixar no topo

2. **Público Alvo**
   - Específico (usuários/colaboradores selecionados)
   - Todos
   - Por Empresa
   - Por Setor
   - Por Cargo

3. **Notificações**
   - Email (com data/hora agendada)
   - Push Notification (com data/hora agendada)
   - Envio imediato ou agendado

4. **Gerenciamento**
   - Listar anotações com filtros
   - Editar anotações
   - Excluir anotações
   - Marcar como visualizada
   - Status (ativa, concluída, arquivada)

5. **Histórico e Rastreamento**
   - Histórico de alterações
   - Contador de visualizações
   - Comentários (estrutura criada)
   - Rastreamento de ações

## 📋 Instalação

### 1. Executar Migração SQL

Execute o arquivo `migracao_anotacoes_sistema.sql` no banco de dados:

```bash
mysql -u seu_usuario -p nome_do_banco < migracao_anotacoes_sistema.sql
```

Ou execute diretamente no MySQL/MariaDB:

```sql
SOURCE migracao_anotacoes_sistema.sql;
```

### 2. Configurar Cron Job (Opcional mas Recomendado)

Para processar notificações agendadas automaticamente, configure um cron job:

**Linux/Mac:**
```bash
# Edite o crontab
crontab -e

# Adicione esta linha para executar a cada 5 minutos
*/5 * * * * /usr/bin/php /caminho/para/rh-privus/cron/processar_notificacoes_anotacoes.php >> /var/log/anotacoes_cron.log 2>&1
```

**Windows (Task Scheduler):**
1. Abra o Agendador de Tarefas
2. Crie uma nova tarefa básica
3. Configure para executar a cada 5 minutos
4. Ação: Iniciar um programa
5. Programa: `php.exe`
6. Argumentos: `C:\laragon\www\rh-privus\cron\processar_notificacoes_anotacoes.php`

**Nota:** Se não configurar o cron, as notificações agendadas serão enviadas apenas quando alguém acessar o dashboard (não recomendado para produção).

## 🎯 Como Usar

### Criar Nova Anotação

1. No Dashboard, clique em "Nova Anotação"
2. Preencha os campos:
   - **Título**: Título da anotação (obrigatório)
   - **Conteúdo**: Descrição completa (obrigatório)
   - **Tipo**: Selecione o tipo
   - **Prioridade**: Selecione a prioridade
   - **Categoria**: Categoria opcional
   - **Data de Vencimento**: Data limite (opcional)
   - **Tags**: Separe por vírgula (ex: importante, urgente, reunião)
   - **Público Alvo**: Quem pode ver a anotação
   - **Fixar no topo**: Marque para fixar
3. Configure notificações:
   - Marque "Enviar notificação por Email" se desejar
   - Marque "Enviar notificação Push" se desejar
   - Selecione data/hora para agendar (ou deixe em branco para enviar imediatamente)
4. Clique em "Salvar"

### Editar Anotação

1. Clique no menu de três pontos (⋮) na anotação
2. Selecione "Editar"
3. Modifique os campos desejados
4. Clique em "Salvar"

### Excluir Anotação

1. Clique no menu de três pontos (⋮) na anotação
2. Selecione "Excluir"
3. Confirme a exclusão

### Filtrar Anotações

- Use o filtro de **Status** (Ativas, Todas, Concluídas, Arquivadas)
- Use o filtro de **Prioridade** (Todas, Urgente, Alta, Média, Baixa)
- Clique em **Fixadas** para ver apenas anotações fixadas

## 🔔 Sistema de Notificações

### Notificações por Email

- Envia email HTML formatado com título, conteúdo e link para a anotação
- Requer configuração SMTP (Configurações > Configurações de Email)
- Envia para o email do usuário ou email pessoal do colaborador

### Notificações Push

- Envia notificação push via OneSignal
- Requer configuração OneSignal (Configurações > Configuração OneSignal)
- Aparece como notificação no dispositivo do usuário

### Agendamento de Notificações

- Você pode agendar notificações para uma data/hora específica
- O sistema processa automaticamente via cron job
- Se não houver cron configurado, as notificações serão enviadas quando alguém acessar o dashboard

## 📊 Estrutura do Banco de Dados

### Tabelas Criadas

1. **anotacoes_sistema**: Tabela principal de anotações
2. **anotacoes_visualizacoes**: Registro de visualizações
3. **anotacoes_comentarios**: Comentários nas anotações (estrutura criada)
4. **anotacoes_historico**: Histórico de alterações

## 🔐 Permissões

- **ADMIN**: Pode criar, editar e excluir todas as anotações
- **RH**: Pode criar, editar e excluir anotações próprias e da empresa
- **GESTOR**: Pode criar, editar e excluir anotações próprias e do setor
- **COLABORADOR**: Não tem acesso ao sistema de anotações

## 🎨 Personalização

### Cores por Prioridade

- **Urgente**: Vermelho (#f1416c)
- **Alta**: Amarelo (#ffc700)
- **Média**: Azul (#009ef7)
- **Baixa**: Verde (#50cd89)

### Tipos de Anotação

- **Geral**: Anotação comum
- **Lembrete**: Para lembretes importantes
- **Importante**: Informação importante
- **Urgente**: Requer atenção imediata
- **Informação**: Apenas informativa

## 🐛 Troubleshooting

### Notificações não estão sendo enviadas

1. Verifique se o cron job está configurado e rodando
2. Verifique as configurações de email (SMTP)
3. Verifique as configurações do OneSignal
4. Verifique os logs em `/var/log/anotacoes_cron.log` (Linux) ou console do Task Scheduler (Windows)

### Anotações não aparecem

1. Verifique os filtros aplicados
2. Verifique as permissões do usuário
3. Verifique se o público alvo está correto

## 📝 Exemplos de Uso

### Exemplo 1: Lembrete de Reunião

- **Título**: "Reunião de Alinhamento - 15/12"
- **Tipo**: Lembrete
- **Prioridade**: Alta
- **Data de Vencimento**: 15/12/2024
- **Notificação**: Agendar para 14/12/2024 às 17:00
- **Público Alvo**: Setor específico

### Exemplo 2: Informação Importante

- **Título**: "Novo Processo de Férias"
- **Tipo**: Informação
- **Prioridade**: Média
- **Fixar**: Sim
- **Notificação**: Enviar imediatamente
- **Público Alvo**: Todos

## 🔄 Próximas Melhorias (Sugestões)

- [ ] Upload de anexos nas anotações
- [ ] Sistema de comentários completo
- [ ] Compartilhamento de anotações
- [ ] Exportação de anotações (PDF/Excel)
- [ ] Busca avançada
- [ ] Templates de anotações
- [ ] Integração com calendário

## 📞 Suporte

Para dúvidas ou problemas, consulte a documentação do sistema ou entre em contato com o administrador.

