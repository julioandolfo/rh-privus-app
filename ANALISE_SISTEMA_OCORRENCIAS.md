# 📋 ANÁLISE COMPLETA: Sistema de Ocorrências

## 📊 VISÃO GERAL

O sistema de ocorrências é um módulo completo que permite:
- ✅ Registrar ocorrências de colaboradores
- ✅ Calcular descontos automáticos (dinheiro ou banco de horas)
- ✅ Aplicar descontos em bônus configuráveis
- ✅ Sistema de aprovação por tipo
- ✅ Advertências progressivas automáticas
- ✅ Campos dinâmicos por tipo
- ✅ Tags para categorização
- ✅ Histórico completo e auditoria

## 🗄️ ESTRUTURA DO BANCO DE DADOS

### Tabelas Principais

#### 1. `tipos_ocorrencias` - Tipos de Ocorrências Configuráveis
**Campos principais:**
- `id` - ID único
- `nome` - Nome do tipo (ex: "Atraso na Entrada")
- `codigo` - Código único (ex: "atraso_entrada")
- `categoria` - ENUM: 'pontualidade', 'comportamento', 'desempenho', 'outros'
- `severidade` - ENUM: 'leve', 'moderada', 'grave', 'critica'
- `requer_aprovacao` - BOOLEAN - Se precisa aprovação antes de aplicar
- `conta_advertencia` - BOOLEAN - Se conta para advertências progressivas
- `calcula_desconto` - BOOLEAN - Se calcula desconto automaticamente
- `valor_desconto` - DECIMAL(10,2) - Valor fixo de desconto (opcional)
- `permite_desconto_banco_horas` - BOOLEAN - Se permite desconto em banco de horas
- `permite_tempo_atraso` - BOOLEAN - Se permite informar tempo de atraso
- `permite_tipo_ponto` - BOOLEAN - Se permite selecionar tipo de ponto
- `template_descricao` - TEXT - Template de descrição padrão
- `validacoes_customizadas` - JSON - Regras de validação
- `notificar_colaborador` - BOOLEAN - Se notifica colaborador
- `notificar_gestor` - BOOLEAN - Se notifica gestor
- `notificar_rh` - BOOLEAN - Se notifica RH
- `status` - ENUM: 'ativo', 'inativo'

**Tipos padrão cadastrados:**
- Atraso na Entrada (atraso_entrada)
- Atraso no Retorno do Almoço (atraso_almoco)
- Atraso no Retorno do Café (atraso_cafe)
- Saída Antecipada (saida_antecipada)
- Falta (falta)
- Ausência Injustificada (ausencia_injustificada)
- Falha Operacional (falha_operacional)
- Desempenho Baixo (desempenho_baixo)
- Comportamento Inadequado (comportamento_inadequado)
- Advertência (advertencia)
- Elogio (elogio)

#### 2. `ocorrencias` - Ocorrências Registradas
**Campos principais:**
- `id` - ID único
- `colaborador_id` - FK para colaboradores (obrigatório)
- `usuario_id` - FK para usuário que registrou (obrigatório)
- `tipo` - VARCHAR(100) - Tipo antigo (compatibilidade, mantido)
- `tipo_ocorrencia_id` - FK para tipos_ocorrencias (pode ser NULL para compatibilidade)
- `descricao` - LONGTEXT - Descrição da ocorrência
- `data_ocorrencia` - DATE - Data da ocorrência (obrigatório)
- `hora_ocorrencia` - TIME - Hora da ocorrência (opcional, apenas visualização)
- `tempo_atraso_minutos` - INT - Minutos de atraso (usado no cálculo)
- `horario_esperado` - TIME - Horário que deveria ter batido ponto (apenas visualização)
- `horario_real` - TIME - Horário que realmente bateu ponto (apenas visualização)
- `tipo_ponto` - ENUM: 'entrada', 'almoco', 'cafe', 'saida' - Tipo de ponto batido
- `considera_dia_inteiro` - BOOLEAN - Se considera como falta do dia inteiro (8h)
  - Quando TRUE: calcula desconto como 8 horas completas
  - Quando FALSE: calcula proporcional aos minutos
- `apenas_informativa` - BOOLEAN - Se é apenas informativa (sem impacto financeiro)
  - Quando TRUE: NÃO gera desconto, NÃO afeta banco de horas, NÃO afeta bônus
- `severidade` - ENUM: 'leve', 'moderada', 'grave', 'critica'
  - Herdado do tipo se não informado
- `status_aprovacao` - ENUM: 'pendente', 'aprovada', 'rejeitada'
  - 'pendente': Se tipo requer_aprovacao = TRUE
  - 'aprovada': Padrão ou após aprovação
  - 'rejeitada': Se rejeitada por ADMIN/RH
- `aprovado_por` - FK para usuarios (quem aprovou)
- `data_aprovacao` - DATETIME - Data de aprovação
- `observacao_aprovacao` - TEXT - Observações da aprovação
- `valor_desconto` - DECIMAL(10,2) - Valor calculado de desconto em R$
  - Calculado automaticamente pela função `calcular_desconto_ocorrencia()`
  - NULL se não há desconto ou se desconta banco de horas
- `desconta_banco_horas` - BOOLEAN - Se desconta do banco de horas
  - Quando TRUE: desconta horas ao invés de dinheiro
  - Quando FALSE ou NULL: desconta dinheiro (se calcula_desconto = TRUE)
- `horas_descontadas` - DECIMAL(10,2) - Quantidade de horas descontadas
  - Preenchido quando desconta_banco_horas = TRUE
  - Calculado pela função `calcular_horas_desconto_ocorrencia()`
- `tags` - JSON - Array de IDs de tags (ex: [1, 3, 5])
- `campos_dinamicos` - JSON - Valores dos campos dinâmicos
  - Formato: {"codigo_campo": "valor", "outro_campo": "valor"}
- `created_at` - TIMESTAMP - Data de criação
- `updated_at` - TIMESTAMP - Data de última atualização

#### 3. `tipos_ocorrencias_campos` - Campos Dinâmicos
Campos customizáveis por tipo de ocorrência:
- `id`, `tipo_ocorrencia_id`, `nome`, `codigo`
- `tipo_campo` - ENUM: 'text', 'textarea', 'number', 'date', 'time', 'select', 'checkbox', 'radio'
- `label`, `placeholder`, `obrigatorio`
- `valor_padrao`, `opcoes` (JSON), `validacao` (JSON)
- `ordem`, `condicao_exibir` (JSON)

#### 4. `ocorrencias_anexos` - Anexos
- `id`, `ocorrencia_id`, `nome_arquivo`, `caminho_arquivo`
- `tipo_mime`, `tamanho_bytes`, `descricao`
- `uploaded_by`, `created_at`

#### 5. `ocorrencias_comentarios` - Comentários
- `id`, `ocorrencia_id`, `usuario_id`
- `comentario`, `tipo` - ENUM: 'comentario', 'resposta', 'defesa'
- `anexos` (JSON), `created_at`, `updated_at`

#### 6. `ocorrencias_historico` - Histórico/Auditoria
- `id`, `ocorrencia_id`, `usuario_id`
- `acao` - ENUM: 'criada', 'editada', 'aprovada', 'rejeitada', 'cancelada', 'comentada'
- `campo_alterado`, `valor_anterior`, `valor_novo`, `observacoes`
- `created_at`

#### 7. `ocorrencias_advertencias` - Advertências Progressivas
- `id`, `colaborador_id`, `ocorrencia_id`
- `tipo_advertencia` - ENUM: 'verbal', 'escrita', 'suspensao', 'demissao'
- `nivel` - INT (1, 2, 3...)
- `data_advertencia`, `data_validade`, `observacoes`
- `created_by`, `created_at`

#### 8. `ocorrencias_regras_advertencias` - Regras de Advertências
- `id`, `tipo_ocorrencia_id` (NULL = regra geral)
- `quantidade_ocorrencias` - Quantas ocorrências para aplicar regra
- `periodo_dias` - Período em dias para contar
- `acao` - ENUM: 'verbal', 'escrita', 'suspensao', 'demissao'
- `nivel_advertencia`, `dias_validade`, `ativo`

#### 9. `ocorrencias_tags` - Tags Disponíveis
- `id`, `nome` (único), `cor` (hexadecimal), `descricao`, `ativo`

#### 10. `tipos_bonus_ocorrencias` - Desconto de Bônus por Ocorrências
**Tabela que relaciona tipos de bônus com tipos de ocorrências:**
- `id`, `tipo_bonus_id`, `tipo_ocorrencia_id`
- `tipo_desconto` - ENUM: 'proporcional', 'fixo', 'percentual', 'total'
  - **proporcional**: Divide pelo número de dias úteis do período
  - **fixo**: Valor fixo por ocorrência
  - **percentual**: Percentual do valor do bônus
  - **total**: Zera o bônus completamente se houver ocorrência
- `valor_desconto` - DECIMAL(10,2) - Valor fixo ou percentual
- `desconta_apenas_aprovadas` - BOOLEAN - Só desconta ocorrências aprovadas
- `desconta_banco_horas` - BOOLEAN - Se também desconta ocorrências que descontam banco de horas
- `periodo_dias` - INT - Período em dias para considerar ocorrências
- `verificar_periodo_anterior` - BOOLEAN - Verifica período anterior ao fechamento
- `periodo_anterior_meses` - INT - Quantos meses anteriores verificar
- `ativo` - BOOLEAN

---

## 💰 IMPACTO NO FECHAMENTO DE PAGAMENTOS

### 1. Desconto Direto no Salário (valor_desconto)

**Como funciona:**
- Ocorrências com `valor_desconto > 0` e `desconta_banco_horas = 0` são descontadas diretamente do salário
- Ocorrências apenas informativas (`apenas_informativa = 1`) NÃO são descontadas
- Ocorrências que descontam banco de horas (`desconta_banco_horas = 1`) NÃO são descontadas em dinheiro

**Query no fechamento:**
```sql
SELECT SUM(valor_desconto) as total_descontos
FROM ocorrencias
WHERE colaborador_id = ?
AND data_ocorrencia >= ? -- data_inicio do período
AND data_ocorrencia <= ? -- data_fim do período
AND valor_desconto > 0
AND (desconta_banco_horas = 0 OR desconta_banco_horas IS NULL)
AND (apenas_informativa = 0 OR apenas_informativa IS NULL)
```

**Cálculo do desconto:**
A função `calcular_desconto_ocorrencia()` calcula automaticamente:

1. **Se tem valor fixo** (`valor_desconto` no tipo): Usa valor fixo
2. **Se considera dia inteiro** (`considera_dia_inteiro = 1`):
   - Calcula: `(salário / 220 horas) × 8 horas`
3. **Se tem tempo de atraso** (`tempo_atraso_minutos > 0`):
   - Calcula: `(salário / 220 horas / 60 minutos) × tempo_atraso_minutos`
4. **Se for falta/ausência injustificada**:
   - Calcula: `(salário / 220 horas) × 8 horas`

**Fórmula padrão:**
- Jornada diária: 8 horas
- Horas mês: 220 horas (padrão CLT)
- Valor hora: `salário / 220`
- Valor minuto: `valor_hora / 60`

### 2. Desconto em Banco de Horas

**Como funciona:**
- Ocorrências com `desconta_banco_horas = 1` descontam horas do banco de horas
- Campo `horas_descontadas` armazena quantas horas foram descontadas
- Função `descontar_horas_banco_ocorrencia()` faz o desconto automaticamente

**Cálculo de horas:**
A função `calcular_horas_desconto_ocorrencia()` calcula:

1. **Se for falta/ausência injustificada**:
   - Retorna: `jornada_diaria` (padrão 8h)
2. **Se for atraso e considera dia inteiro**:
   - Retorna: `jornada_diaria` (8h)
3. **Se for atraso com minutos**:
   - Retorna: `tempo_atraso_minutos / 60` (converte para horas)
4. **Se for saída antecipada**:
   - Retorna: `tempo_atraso_minutos / 60`

**Impacto:**
- Cria movimentação no banco de horas (tipo 'desconto_ocorrencia')
- Atualiza saldo do colaborador
- Registra histórico

### 3. Desconto em Bônus (tipos_bonus_ocorrencias)

**Como funciona:**
- Configuração por tipo de bônus e tipo de ocorrência
- Função `calcular_desconto_bonus_ocorrencias()` calcula desconto

**Tipos de desconto:**

1. **proporcional** (padrão):
   - Divide valor do bônus pelos dias úteis do período
   - Multiplica pelo número de ocorrências
   - Exemplo: Bônus R$ 1000, 20 dias úteis, 2 ocorrências = R$ 100 de desconto

2. **fixo**:
   - Valor fixo por ocorrência
   - Exemplo: R$ 50 por ocorrência, 2 ocorrências = R$ 100 de desconto

3. **percentual**:
   - Percentual do valor do bônus por ocorrência
   - Exemplo: 10% por ocorrência, bônus R$ 1000, 2 ocorrências = R$ 200 de desconto

4. **total**:
   - Se houver qualquer ocorrência, zera o bônus completamente
   - Exemplo: Bônus R$ 1000, 1 ocorrência = R$ 1000 de desconto (bônus = 0)

**Período de verificação:**
- Por padrão: Período do fechamento (data_inicio a data_fim)
- Pode verificar período anterior: `verificar_periodo_anterior = TRUE`
- Se verificar período anterior e encontrar ocorrência: **zera o bônus completamente** (independente do tipo)

**Filtros:**
- `desconta_apenas_aprovadas`: Se TRUE, só conta ocorrências aprovadas
- `desconta_banco_horas`: Se TRUE, também conta ocorrências que descontam banco de horas
- `periodo_dias`: Período customizado em dias

**Armazenamento:**
- Campo `desconto_ocorrencias` em `fechamentos_pagamento_bonus`
- Campo `valor_original` - Valor antes do desconto
- Campo `detalhes_desconto` (JSON) - Detalhes do desconto aplicado

---

## 🔄 FLUXO DE CRIAÇÃO DE OCORRÊNCIA

### 1. Registro da Ocorrência (`ocorrencias_add.php`)

**Passos:**

1. **Validação:**
   - Verifica permissão de acesso ao colaborador
   - Valida campos obrigatórios do tipo
   - Valida campos dinâmicos se existirem

2. **Determinação de Severidade:**
   - Usa severidade do tipo se não informada
   - Padrão: 'moderada'

3. **Status de Aprovação:**
   - Se tipo `requer_aprovacao = TRUE`: status = 'pendente'
   - Senão: status = 'aprovada'

4. **Inserção no Banco:**
   - Insere ocorrência com todos os dados
   - Obtém `ocorrencia_id`

5. **Processamento de Impacto:**

   **Se `apenas_informativa = FALSE`:**

   a) **Desconto em Banco de Horas:**
      - Se `tipo_desconto = 'banco_horas'` E tipo permite banco de horas
      - Chama `descontar_horas_banco_ocorrencia()`
      - Calcula horas baseado no tipo
      - Cria movimentação no banco de horas
      - Atualiza `horas_descontadas` na ocorrência
      - Marca `desconta_banco_horas = 1`

   b) **Desconto em Dinheiro:**
      - Se tipo `calcula_desconto = TRUE` E não desconta banco de horas
      - Chama `calcular_desconto_ocorrencia()`
      - Calcula valor baseado em:
        - Valor fixo do tipo OU
        - Cálculo proporcional (salário, horas, minutos)
      - Atualiza `valor_desconto` na ocorrência

   **Se `apenas_informativa = TRUE`:**
   - Garante que `valor_desconto = NULL`
   - Garante que `desconta_banco_horas = 0`
   - Garante que `horas_descontadas = NULL`

6. **Processamento Adicional:**
   - Upload de anexos (se houver)
   - Registro de histórico
   - Aplicação de advertências progressivas (se configurado)
   - Envio de notificações (se configurado)

### 2. Aprovação de Ocorrência (`ocorrencias_approve.php`)

**Quando uma ocorrência requer aprovação:**

1. Status inicial: `pendente`
2. ADMIN/RH pode aprovar ou rejeitar
3. Ao aprovar:
   - Status muda para `aprovada`
   - `aprovado_por` = ID do usuário
   - `data_aprovacao` = NOW()
   - Se ainda não tinha desconto calculado, calcula agora
4. Ao rejeitar:
   - Status muda para `rejeitada`
   - Não gera desconto nem impacto

---

## 📊 IMPACTO NO FECHAMENTO DE PAGAMENTOS

### Ordem de Cálculo no Fechamento:

1. **Salário Base**
   - Salário do colaborador

2. **Horas Extras**
   - Horas extras trabalhadas (se houver)

3. **Bônus**
   - Para cada tipo de bônus:
     - Calcula valor original (fixo ou variável)
     - **Calcula desconto por ocorrências** (`calcular_desconto_bonus_ocorrencias`)
     - Valor final = valor original - desconto_ocorrencias
     - Se desconto > valor original: desconto = valor original (não fica negativo)

4. **Descontos de Ocorrências (Direto no Salário)**
   - Soma todas as ocorrências do período com:
     - `valor_desconto > 0`
     - `desconta_banco_horas = 0`
     - `apenas_informativa = 0`
   - Adiciona ao total de descontos

5. **Adiantamentos**
   - Adiciona adiantamentos com `mes_desconto` = mês de referência

6. **Total Final**
   - Total = Salário + Horas Extras + Bônus - Descontos Ocorrências - Adiantamentos

---

## 🏷️ TIPOS DE OCORRÊNCIAS E IMPACTO

### Ocorrências que Descontam em Dinheiro:

1. **Atrasos** (se não desconta banco de horas):
   - Calcula proporcional aos minutos de atraso
   - Ou dia inteiro se `considera_dia_inteiro = 1`

2. **Faltas/Ausências Injustificadas**:
   - Calcula como dia inteiro (8 horas)
   - Valor: `(salário / 220) × 8`

3. **Saída Antecipada**:
   - Calcula proporcional aos minutos

### Ocorrências que Descontam Banco de Horas:

- Mesmas regras acima, mas desconta horas ao invés de dinheiro
- Colaborador fica devendo horas no banco

### Ocorrências Apenas Informativas:

- **Não geram desconto**
- **Não afetam banco de horas**
- **Não afetam bônus** (a menos que configurado)
- Apenas para registro/documentação

---

## ⚙️ CONFIGURAÇÕES AVANÇADAS

### Campos Dinâmicos:
- Permite criar campos customizados por tipo
- Valores armazenados em JSON no campo `campos_dinamicos`
- Validação customizada por campo

### Tags:
- Sistema de tags para categorização múltipla
- Armazenadas em JSON no campo `tags`
- Tags padrão: urgente, reincidente, primeira-vez, documentado, resolvido, pendente-acao

### Templates de Descrição:
- Templates pré-definidos com variáveis
- Variáveis: {colaborador}, {data}, {hora}, etc.

### Advertências Progressivas:
- Regras automáticas baseadas em quantidade de ocorrências
- Aplicação automática ao criar ocorrência
- Níveis: verbal → escrita → suspensão → demissão

---

## 📝 RESUMO DO FLUXO COMPLETO

```
1. Criar Ocorrência
   ├── Validações
   ├── Inserção no BD
   ├── Cálculo de Impacto
   │   ├── Banco de Horas OU
   │   └── Dinheiro (valor_desconto)
   ├── Anexos
   ├── Histórico
   └── Notificações

2. Aprovação (se necessário)
   ├── Pendente → Aprovada/Rejeitada
   └── Se aprovada: calcula desconto

3. Fechamento de Pagamento
   ├── Busca ocorrências do período
   ├── Soma valor_desconto (se não banco horas)
   ├── Calcula desconto em bônus
   └── Aplica no total final
```

---

## 🔍 PONTOS IMPORTANTES

1. **Ocorrências apenas informativas** (`apenas_informativa = 1`):
   - ❌ NÃO geram desconto em dinheiro
   - ❌ NÃO afetam banco de horas
   - ❌ NÃO afetam bônus (a menos que configurado explicitamente)
   - ✅ Apenas para registro/documentação

2. **Desconto banco de horas OU dinheiro** - nunca ambos:
   - Se `desconta_banco_horas = 1`: desconta horas, `valor_desconto = NULL`
   - Se `desconta_banco_horas = 0`: desconta dinheiro, `horas_descontadas = NULL`

3. **Bônus podem ser descontados** por ocorrências configuradas:
   - Configuração em `tipos_bonus_ocorrencias`
   - 4 tipos de desconto: proporcional, fixo, percentual, total
   - Pode verificar período anterior (zera bônus se encontrar)

4. **Período anterior** pode zerar bônus completamente:
   - Se `verificar_periodo_anterior = TRUE` e encontrar ocorrência
   - Zera o bônus independente do tipo de desconto

5. **Aprovação** pode ser obrigatória por tipo:
   - Se `requer_aprovacao = TRUE`: status inicial = 'pendente'
   - Só calcula desconto após aprovação
   - Rejeitadas não geram impacto

6. **Advertências progressivas** são aplicadas automaticamente:
   - Regras em `ocorrencias_regras_advertencias`
   - Aplicadas ao criar ocorrência se atingir quantidade

7. **Cálculo automático** de desconto baseado em salário e tempo:
   - Fórmula padrão: `(salário / 220 horas) × tempo`
   - Considera dia inteiro: `× 8 horas`
   - Considera minutos: `× (minutos / 60)`

8. **Campos de horário** (`horario_esperado`, `horario_real`):
   - Apenas para visualização/documentação
   - Não são usados no cálculo
   - O cálculo usa `tempo_atraso_minutos`

9. **Valor fixo vs cálculo automático**:
   - Se tipo tem `valor_desconto` fixo: usa valor fixo
   - Senão: calcula automaticamente baseado em salário

10. **Tipo de ponto** (`tipo_ponto`):
    - Usado apenas para contexto/visualização
    - Não afeta cálculo diretamente
    - Ajuda a entender qual ponto foi afetado

---

## 📋 RESUMO DAS TABELAS RELACIONADAS

| Tabela | Propósito | Relacionamento |
|--------|-----------|----------------|
| `tipos_ocorrencias` | Configuração dos tipos | 1:N com ocorrencias |
| `ocorrencias` | Ocorrências registradas | N:1 com colaboradores, tipos |
| `tipos_ocorrencias_campos` | Campos dinâmicos | N:1 com tipos_ocorrencias |
| `ocorrencias_anexos` | Anexos | N:1 com ocorrencias |
| `ocorrencias_comentarios` | Comentários | N:1 com ocorrencias |
| `ocorrencias_historico` | Auditoria | N:1 com ocorrencias |
| `ocorrencias_advertencias` | Advertências aplicadas | N:1 com ocorrencias |
| `ocorrencias_regras_advertencias` | Regras de advertências | N:1 com tipos_ocorrencias |
| `ocorrencias_tags` | Tags disponíveis | M:N com ocorrencias (via JSON) |
| `tipos_bonus_ocorrencias` | Desconto em bônus | N:N entre tipos_bonus e tipos_ocorrencias |

---

## 🎯 CASOS DE USO

### Caso 1: Atraso de 30 minutos
- Tipo: Atraso na Entrada
- `tempo_atraso_minutos` = 30
- `considera_dia_inteiro` = FALSE
- `desconta_banco_horas` = FALSE
- **Resultado**: Desconto = `(salário / 220 / 60) × 30`

### Caso 2: Falta completa
- Tipo: Falta
- `tempo_atraso_minutos` = NULL
- `considera_dia_inteiro` = FALSE (ou TRUE)
- `desconta_banco_horas` = FALSE
- **Resultado**: Desconto = `(salário / 220) × 8`

### Caso 3: Atraso considerado dia inteiro
- Tipo: Atraso na Entrada
- `tempo_atraso_minutos` = 15
- `considera_dia_inteiro` = TRUE
- `desconta_banco_horas` = FALSE
- **Resultado**: Desconto = `(salário / 220) × 8` (ignora minutos)

### Caso 4: Desconto em banco de horas
- Tipo: Atraso na Entrada
- `tempo_atraso_minutos` = 30
- `desconta_banco_horas` = TRUE
- **Resultado**: 
  - `horas_descontadas` = 0.5h
  - `valor_desconto` = NULL
  - Saldo banco de horas reduzido

### Caso 5: Ocorrência apenas informativa
- Tipo: Elogio
- `apenas_informativa` = TRUE
- **Resultado**: 
  - `valor_desconto` = NULL
  - `horas_descontadas` = NULL
  - `desconta_banco_horas` = FALSE
  - Sem impacto financeiro

### Caso 6: Desconto em bônus
- Bônus: R$ 1000
- Configuração: 2 faltas = desconto proporcional
- Ocorrências: 2 faltas no período
- Dias úteis: 20
- **Resultado**: 
  - Desconto = `(1000 / 20) × 2` = R$ 100
  - Bônus final = R$ 900

---

**Sistema completo e funcional!** ✅

