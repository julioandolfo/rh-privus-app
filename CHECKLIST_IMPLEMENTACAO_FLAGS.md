# ✅ Checklist de Implementação - Sistema de Flags

## 📋 Verificações Necessárias

### 1. Banco de Dados
- [x] Tabela `ocorrencias_flags` criada
- [x] Tabela `ocorrencias_flags_historico` criada
- [x] Campos `gera_flag` e `tipo_flag` adicionados em `tipos_ocorrencias`
- [ ] **VERIFICAR**: Executar `migracao_sistema_flags.sql` no banco de dados

### 2. Funções PHP
- [x] `criar_flag_automatica()` implementada
- [x] `contar_flags_ativas()` implementada
- [x] `get_flags_ativas()` implementada
- [x] `verificar_expiracao_flags()` implementada
- [x] `verificar_renovacao_flags()` implementada
- [x] `registrar_historico_flag()` implementada
- [x] `get_label_tipo_flag()` implementada
- [x] `get_cor_badge_flag()` implementada

### 3. Integração com Ocorrências
- [x] Chamada em `ocorrencias_add.php` (quando aprovada)
- [x] Chamada em `ocorrencias_rapida.php` (quando aprovada)
- [x] Chamada em `ocorrencias_approve.php` (quando aprovada)

### 4. Interface - Tipos de Ocorrências
- [x] Campo "Gera Flag Automática" no formulário
- [x] Campo "Tipo de Flag" no formulário
- [x] Validação JavaScript (gera_flag requer tipo_flag)
- [x] Validação PHP (gera_flag requer tipo_flag)
- [x] JavaScript para mostrar/ocultar campo tipo_flag
- [x] Carregamento de valores ao editar

### 5. Interface - Visualização de Flags
- [x] Página `flags_view.php` criada
- [x] Filtros por colaborador, status e tipo
- [x] Estatísticas de flags
- [x] Indicador visual no perfil do colaborador
- [x] Menu "Flags" para ADMIN/RH/GESTOR
- [x] Menu "Minhas Flags" para COLABORADOR

### 6. Permissões
- [x] Permissão `flags_view.php` configurada para todos os roles
- [x] Filtros de acesso baseados em role (RH vê só sua empresa, GESTOR só seu setor)

### 7. Cron Job
- [x] Script `cron/verificar_expiracao_flags.php` criado
- [ ] **VERIFICAR**: Configurar cron job no servidor (executar diariamente às 00:00)

### 8. Validações e Segurança
- [x] Validação: gera_flag requer tipo_flag
- [x] Validação: flag só é criada se ocorrência estiver aprovada
- [x] Validação: não cria flag duplicada para mesma ocorrência
- [x] Tratamento de erros em todas as funções

### 9. Documentação
- [x] `SISTEMA_FLAGS_IMPLEMENTACAO.md` criado
- [x] `README_CRON_FLAGS.md` criado
- [x] `FAQ_FLAGS_ADICIONADAS.md` criado

## ⚠️ Ações Necessárias

1. **Executar migração SQL**: Execute o arquivo `migracao_sistema_flags.sql` no banco de dados
2. **Configurar Cron Job**: Configure o cron job para executar `cron/verificar_expiracao_flags.php` diariamente
3. **Testar criação de flags**: Crie uma ocorrência de um tipo que gera flag e verifique se a flag foi criada após aprovação
4. **Verificar renovação**: Crie duas ocorrências que geram flags para o mesmo colaborador e verifique se as flags são renovadas

## 🔍 Testes Recomendados

1. Criar tipo de ocorrência com `gera_flag = TRUE` e `tipo_flag` preenchido
2. Criar ocorrência deste tipo e aprovar → Verificar se flag foi criada
3. Criar segunda ocorrência para mesmo colaborador → Verificar se flags foram renovadas
4. Verificar expiração após 30 dias (ou ajustar data manualmente no banco)
5. Verificar visualização de flags por diferentes roles (ADMIN, RH, GESTOR, COLABORADOR)
6. Verificar alerta quando colaborador tem 3+ flags ativas

