# 🚩 Sistema de Flags Automáticas - Documentação de Implementação

## 📋 Resumo

Sistema completo de flags automáticas para controle disciplinar, implementado conforme as regras de conduta da empresa. O sistema cria flags automaticamente quando ocorrências específicas são registradas e aprovadas, com validade de 30 dias corridos.

## ✅ Funcionalidades Implementadas

### 1. **Criação Automática de Flags**
- ✅ Flags são criadas automaticamente ao registrar ocorrências dos tipos:
  - **Falta** (`falta`)
  - **Ausência Injustificada** (`ausencia_injustificada`)
  - **Comportamento Inadequado** (`comportamento_inadequado`)
- ✅ Flags só são criadas quando a ocorrência está **aprovada**
- ✅ Cada flag tem validade de **30 dias corridos** a partir da data da ocorrência

### 2. **Renovação Automática de Validade**
- ✅ Quando um colaborador recebe uma nova flag enquanto outra está ativa, **todas as flags ativas são renovadas** para contar juntas
- ✅ Todas passam a ter a mesma data de validade (30 dias a partir da nova flag)

### 3. **Expiração Automática**
- ✅ Flags expiram automaticamente após 30 dias
- ✅ Processo de verificação pode ser executado:
  - **Via Cron (RECOMENDADO)** ⭐ - Execução diária automática às 00:00 (`cron/verificar_expiracao_flags.php`)
  - Manualmente via página web (ADMIN/RH)
  - Via script CLI (`cron/verificar_expiracao_flags.php`)
  - **Fallback**: Automaticamente ao acessar páginas que listam flags (otimizado por colaborador)

### 4. **Contagem de Flags Ativas**
- ✅ Sistema conta automaticamente quantas flags ativas cada colaborador possui
- ✅ Alerta visual quando colaborador possui **3 ou mais flags ativas** (mas **NÃO desliga automaticamente**)

### 5. **Interface Visual**
- ✅ Página dedicada para visualizar flags (`pages/flags_view.php`)
- ✅ Indicador visual na página do colaborador mostrando quantidade de flags ativas
- ✅ Badge colorido:
  - **Azul**: 1 flag ativa
  - **Amarelo**: 2 flags ativas
  - **Vermelho**: 3+ flags ativas (com alerta ⚠️)

## 📁 Arquivos Criados/Modificados

### Novos Arquivos

1. **`migracao_sistema_flags.sql`**
   - Criação das tabelas `ocorrencias_flags` e `ocorrencias_flags_historico`
   - Adição de campos `gera_flag` e `tipo_flag` na tabela `tipos_ocorrencias`
   - Configuração automática dos tipos de ocorrências que geram flags

2. **`pages/flags_view.php`**
   - Página completa para visualizar flags
   - Filtros por colaborador, status e tipo de flag
   - Estatísticas e alertas visuais

3. **`cron/verificar_expiracao_flags.php`**
   - Script para verificar e expirar flags automaticamente
   - Pode ser executado via cron ou manualmente

4. **`SISTEMA_FLAGS_IMPLEMENTACAO.md`** (este arquivo)
   - Documentação completa do sistema

### Arquivos Modificados

1. **`includes/ocorrencias_functions.php`**
   - Adicionadas funções:
     - `criar_flag_automatica()` - Cria flag quando ocorrência é aprovada
     - `contar_flags_ativas()` - Conta flags ativas de um colaborador
     - `get_flags_ativas()` - Busca flags ativas
     - `get_flags_colaborador()` - Busca todas as flags de um colaborador
     - `verificar_expiracao_flags()` - Expira flags vencidas
     - `registrar_historico_flag()` - Registra histórico de ações
     - `renovar_validade_flag()` - Renova validade de uma flag
     - `verificar_renovacao_flags()` - Renova flags existentes ao criar nova
     - `get_label_tipo_flag()` - Retorna label formatado do tipo
     - `get_cor_badge_flag()` - Retorna cor do badge por tipo

2. **`pages/ocorrencias_add.php`**
   - Integração para criar flag automaticamente após criar ocorrência aprovada

3. **`pages/ocorrencias_rapida.php`**
   - Integração para criar flag automaticamente após criar ocorrência rápida aprovada

4. **`pages/ocorrencias_approve.php`**
   - Integração para criar flag quando ocorrência pendente é aprovada

5. **`pages/colaborador_view.php`**
   - Adicionado indicador visual de flags ativas
   - Badge colorido com link para página de flags

6. **`includes/menu.php`**
   - Adicionado item de menu "Flags" no submenu de Ocorrências

7. **`includes/permissions.php`**
   - Adicionada permissão para `flags_view.php` (ADMIN, RH, GESTOR)

## 🗄️ Estrutura do Banco de Dados

### Tabela `ocorrencias_flags`
```sql
- id (PK)
- colaborador_id (FK)
- ocorrencia_id (FK)
- tipo_flag (ENUM: 'falta_nao_justificada', 'falta_compromisso_pessoal', 'ma_conduta')
- data_flag (DATE) - Data em que a flag foi recebida
- data_validade (DATE) - Data de expiração (30 dias após data_flag)
- status (ENUM: 'ativa', 'expirada')
- observacoes (TEXT)
- created_by (FK usuarios)
- created_at, updated_at
```

### Tabela `ocorrencias_flags_historico`
```sql
- id (PK)
- flag_id (FK)
- acao (ENUM: 'criada', 'expirada', 'renovada', 'cancelada')
- usuario_id (FK)
- observacoes (TEXT)
- created_at
```

### Campos Adicionados em `tipos_ocorrencias`
```sql
- gera_flag (BOOLEAN) - Se TRUE, gera flag automaticamente
- tipo_flag (ENUM) - Tipo de flag gerada
```

## 🔄 Fluxo de Funcionamento

### 1. Criação de Ocorrência
```
Ocorrência Criada
    ↓
Tipo gera flag? → NÃO → Fim
    ↓ SIM
Status = aprovada? → NÃO → Aguarda aprovação
    ↓ SIM
Colaborador tem flags ativas? → SIM → Renova todas as flags
    ↓
Cria nova flag (validade = 30 dias)
    ↓
Conta flags ativas
    ↓
3+ flags? → SIM → Alerta (log + visual)
    ↓
Fim
```

### 2. Aprovação de Ocorrência Pendente
```
Ocorrência Aprovada
    ↓
Tipo gera flag? → NÃO → Fim
    ↓ SIM
Colaborador tem flags ativas? → SIM → Renova todas as flags
    ↓
Cria nova flag (validade = 30 dias)
    ↓
Conta flags ativas
    ↓
3+ flags? → SIM → Alerta (log + visual)
    ↓
Fim
```

### 3. Expiração de Flags
```
Verificação de Expiração (automática ou manual)
    ↓
Busca flags com data_validade < HOJE e status = 'ativa'
    ↓
Atualiza status para 'expirada'
    ↓
Registra histórico
    ↓
Fim
```

## 🎯 Regras de Negócio Implementadas

### ✅ Regras Implementadas

1. **Cada falta/má conduta gera 1 flag** ✅
2. **Cada flag tem validade de 30 dias corridos** ✅
3. **Flags expiram automaticamente após 30 dias** ✅
4. **Se receber nova flag enquanto outra está ativa, ambas contam juntas** ✅
   - Todas as flags ativas são renovadas para mesma validade
5. **Sistema alerta quando colaborador tem 3+ flags ativas** ✅
   - Badge vermelho na página do colaborador
   - Alerta na página de flags
   - Log no sistema

### ❌ Regras NÃO Implementadas (conforme solicitado)

1. **Desligamento automático ao atingir 3 flags** ❌
   - Sistema apenas alerta, mas não desliga automaticamente
   - Desligamento deve ser feito manualmente pelo RH/ADMIN

## 📊 Tipos de Flags

| Tipo | Código | Ocorrências que Geram |
|------|--------|----------------------|
| Falta Não Justificada | `falta_nao_justificada` | Falta, Ausência Injustificada |
| Falta por Compromisso Pessoal | `falta_compromisso_pessoal` | (Configurável) |
| Má Conduta | `ma_conduta` | Comportamento Inadequado |

## 🔧 Configuração

### Configurar Tipo de Ocorrência para Gerar Flag

1. Acesse **Tipos de Ocorrências**
2. Edite o tipo desejado
3. Marque **"Gera Flag"**
4. Selecione o **Tipo de Flag**:
   - Falta Não Justificada
   - Falta por Compromisso Pessoal
   - Má Conduta

### Executar Verificação de Expiração Manualmente

**Via Web:**
- Acesse qualquer página que lista flags (verificação automática)

**Via CLI:**
```bash
php cron/verificar_expiracao_flags.php
```

**Via Cron (recomendado - diariamente às 00:00):**
```cron
0 0 * * * /usr/bin/php /caminho/para/cron/verificar_expiracao_flags.php
```

## 📝 Exemplo Prático

### Cenário: Colaborador com Flags

**01/05**: Colaborador recebe 1ª flag (Falta)
- Flag válida até **31/05**

**20/05**: Colaborador recebe 2ª flag (Má Conduta)
- Sistema renova 1ª flag para contar junto
- Ambas válidas até **19/06**

**15/06**: Colaborador recebe 3ª flag (Falta)
- Sistema renova 1ª e 2ª flags
- Todas válidas até **15/07**
- ⚠️ **ALERTA**: Colaborador possui 3 flags ativas

**16/07**: Todas as flags expiram automaticamente
- Status muda para "expirada"
- Colaborador volta a ter 0 flags ativas

## 🚀 Próximos Passos (Opcional)

1. **Notificações**: Enviar notificação quando colaborador recebe flag
2. **Relatórios**: Dashboard com estatísticas de flags
3. **Histórico Completo**: Visualizar histórico detalhado de flags por colaborador
4. **Configuração de Validade**: Permitir configurar dias de validade por tipo de flag

## 📞 Suporte

Para dúvidas ou problemas, consulte:
- `ANALISE_SISTEMA_OCORRENCIAS.md` - Documentação do sistema de ocorrências
- `includes/ocorrencias_functions.php` - Funções de flags (linhas 680-1037)

---

**Sistema implementado e funcional!** ✅

