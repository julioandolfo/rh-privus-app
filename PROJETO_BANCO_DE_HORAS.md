# 📋 Projeto: Sistema de Banco de Horas Completo

## 🎯 Objetivo

Implementar um sistema completo de banco de horas que permita:
1. **Em horas extras**: Escolher entre pagamento em R$ ou conversão em saldo de banco de horas
2. **Remoção de horas**: Possibilidade de remover horas do saldo
3. **Visualização**: Ver saldo atual e histórico completo nas informações do colaborador
4. **Integração com ocorrências**: Descontar horas do banco quando houver falta ou atraso

---

## 📊 Análise do Sistema Atual

### **Estrutura Atual de Horas Extras**

**Tabela `horas_extras`:**
- `id` - ID único
- `colaborador_id` - Colaborador
- `data_trabalho` - Data do trabalho
- `quantidade_horas` - Quantidade de horas (DECIMAL 5,2)
- `valor_hora` - Valor da hora normal
- `percentual_adicional` - % adicional
- `valor_total` - Valor total calculado
- `observacoes` - Observações
- `usuario_id` - Usuário que cadastrou
- `created_at`, `updated_at` - Timestamps

**Fluxo Atual:**
1. RH cadastra hora extra em `horas_extras.php`
2. Sistema calcula valor total automaticamente
3. Horas extras são somadas no fechamento de pagamento
4. Valor é pago em dinheiro

### **Sistema de Ocorrências**

**Tabela `ocorrencias`:**
- Possui tipos de ocorrências (`tipos_ocorrencias`)
- Tipos relacionados a pontualidade: `falta`, `atraso_entrada`, `atraso_almoco`, etc.
- Sistema já calcula desconto em dinheiro (`valor_desconto`)
- Função `calcular_desconto_ocorrencia()` já existe

---

## 🗄️ Estrutura de Banco de Dados Proposta

### **1. Tabela `banco_horas` (Saldo Atual)**

```sql
CREATE TABLE IF NOT EXISTS banco_horas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    saldo_horas DECIMAL(8,2) DEFAULT 0.00 COMMENT 'Saldo atual em horas (pode ser negativo)',
    saldo_minutos INT DEFAULT 0 COMMENT 'Saldo em minutos para precisão',
    ultima_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    UNIQUE KEY uk_colaborador (colaborador_id),
    INDEX idx_saldo (saldo_horas),
    INDEX idx_ultima_atualizacao (ultima_atualizacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Características:**
- Uma linha por colaborador (UNIQUE)
- Saldo em horas (DECIMAL) e minutos (INT) para precisão
- Atualizado automaticamente via triggers ou funções

### **2. Tabela `banco_horas_movimentacoes` (Histórico Completo)**

```sql
CREATE TABLE IF NOT EXISTS banco_horas_movimentacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    tipo ENUM('credito', 'debito') NOT NULL COMMENT 'Crédito = adiciona, Débito = remove',
    origem ENUM('hora_extra', 'ocorrencia', 'ajuste_manual', 'remocao_manual') NOT NULL,
    origem_id INT NULL COMMENT 'ID da origem (horas_extras.id, ocorrencias.id, etc)',
    quantidade_horas DECIMAL(8,2) NOT NULL COMMENT 'Quantidade de horas (positiva sempre)',
    saldo_anterior DECIMAL(8,2) NOT NULL COMMENT 'Saldo antes da movimentação',
    saldo_posterior DECIMAL(8,2) NOT NULL COMMENT 'Saldo após a movimentação',
    motivo TEXT NOT NULL COMMENT 'Motivo da movimentação',
    observacoes TEXT,
    usuario_id INT NULL COMMENT 'Usuário que realizou a movimentação',
    data_movimentacao DATE NOT NULL COMMENT 'Data da movimentação',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo (tipo),
    INDEX idx_origem (origem, origem_id),
    INDEX idx_data_movimentacao (data_movimentacao),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Tipos de Origem:**
- `hora_extra` - Quando hora extra é convertida em saldo
- `ocorrencia` - Quando falta/atraso desconta do saldo
- `ajuste_manual` - Ajuste manual feito pelo RH
- `remocao_manual` - Remoção manual de horas

### **3. Modificações na Tabela `horas_extras`**

```sql
ALTER TABLE horas_extras
ADD COLUMN tipo_pagamento ENUM('dinheiro', 'banco_horas') DEFAULT 'dinheiro' 
    COMMENT 'Tipo de pagamento: dinheiro ou banco de horas',
ADD COLUMN banco_horas_movimentacao_id INT NULL 
    COMMENT 'ID da movimentação no banco de horas (se aplicável)',
ADD INDEX idx_tipo_pagamento (tipo_pagamento),
ADD INDEX idx_banco_horas_mov (banco_horas_movimentacao_id),
ADD FOREIGN KEY (banco_horas_movimentacao_id) 
    REFERENCES banco_horas_movimentacoes(id) ON DELETE SET NULL;
```

### **4. Modificações na Tabela `ocorrencias`**

```sql
ALTER TABLE ocorrencias
ADD COLUMN desconta_banco_horas BOOLEAN DEFAULT FALSE 
    COMMENT 'Se TRUE, desconta do banco de horas ao invés de dinheiro',
ADD COLUMN horas_descontadas DECIMAL(5,2) NULL 
    COMMENT 'Quantidade de horas descontadas do banco',
ADD COLUMN banco_horas_movimentacao_id INT NULL 
    COMMENT 'ID da movimentação no banco de horas (se aplicável)',
ADD INDEX idx_desconta_banco_horas (desconta_banco_horas),
ADD INDEX idx_banco_horas_mov (banco_horas_movimentacao_id),
ADD FOREIGN KEY (banco_horas_movimentacao_id) 
    REFERENCES banco_horas_movimentacoes(id) ON DELETE SET NULL;
```

---

## 🔄 Fluxos de Funcionamento

### **Fluxo 1: Cadastro de Hora Extra com Escolha de Pagamento**

```
1. RH acessa horas_extras.php
2. Preenche dados: colaborador, data, quantidade de horas
3. Sistema calcula valor total (como já faz)
4. NOVO: RH escolhe tipo de pagamento:
   - Opção 1: "Pagar em R$" (comportamento atual)
   - Opção 2: "Adicionar ao Banco de Horas"
5. Se escolher "Banco de Horas":
   - Não calcula valor monetário (ou calcula mas não usa)
   - Adiciona horas ao saldo do colaborador
   - Cria movimentação no histórico
   - Marca tipo_pagamento = 'banco_horas'
6. Se escolher "R$":
   - Comportamento atual (calcula e paga em dinheiro)
   - Marca tipo_pagamento = 'dinheiro'
```

### **Fluxo 2: Remoção de Horas do Banco**

```
1. RH acessa horas_extras.php
2. Clica em "Remover Horas do Banco"
3. Seleciona colaborador
4. Informa quantidade de horas a remover
5. Informa motivo/observações
6. Sistema valida se colaborador tem saldo suficiente
7. Se tiver saldo:
   - Debita horas do saldo
   - Cria movimentação tipo 'debito', origem 'remocao_manual'
   - Registra no histórico
8. Se não tiver saldo suficiente:
   - Mostra erro e permite remover mesmo assim (saldo negativo)
   - Ou bloqueia a operação (configurável)
```

### **Fluxo 3: Visualização de Saldo e Histórico**

```
1. RH/Colaborador acessa colaborador_view.php
2. NOVO: Aba "Banco de Horas" adicionada
3. Mostra:
   - Saldo atual (em horas e minutos)
   - Indicador visual (positivo/negativo)
   - Gráfico de evolução (opcional)
   - Tabela com histórico completo:
     * Data
     * Tipo (Crédito/Débito)
     * Origem
     * Quantidade
     * Saldo anterior
     * Saldo posterior
     * Motivo
     * Usuário responsável
4. Filtros:
   - Por período
   - Por tipo (crédito/débito)
   - Por origem
```

### **Fluxo 4: Desconto em Ocorrências**

```
1. RH cadastra ocorrência (falta ou atraso) em ocorrencias_add.php
2. NOVO: Sistema verifica tipo de ocorrência:
   - Se for 'falta' ou 'atraso_*':
     * Mostra opção: "Descontar do Banco de Horas?"
     * Checkbox para escolher
3. Se marcado "Descontar do Banco de Horas":
   - Calcula horas a descontar:
     * Falta = 8 horas (ou jornada do colaborador)
     * Atraso = tempo_atraso_minutos convertido em horas
   - Verifica saldo disponível
   - Se tiver saldo:
     * Debita do banco de horas
     * Cria movimentação tipo 'debito', origem 'ocorrencia'
     * Marca desconta_banco_horas = TRUE
     * Não calcula desconto em dinheiro
   - Se não tiver saldo suficiente:
     * Opção 1: Permite saldo negativo
     * Opção 2: Desconta parcialmente do banco + resto em dinheiro
     * Opção 3: Desconta tudo em dinheiro (comportamento atual)
4. Se NÃO marcado:
   - Comportamento atual (desconta em dinheiro)
```

---

## 💻 Modificações em Arquivos Existentes

### **1. `pages/horas_extras.php`**

**Modificações necessárias:**

#### **No formulário de cadastro:**
- Adicionar campo de seleção: "Tipo de Pagamento"
  - Radio buttons: "Pagar em R$" (padrão) | "Adicionar ao Banco de Horas"
- Mostrar/ocultar cálculo de valor conforme seleção
- Se escolher "Banco de Horas", mostrar apenas quantidade de horas

#### **No processamento POST:**
- Capturar `tipo_pagamento` do formulário
- Se `tipo_pagamento == 'banco_horas'`:
  - Não calcular valor monetário (ou calcular mas não usar)
  - Chamar função `adicionar_horas_banco()`
  - Criar movimentação no histórico
- Se `tipo_pagamento == 'dinheiro'`:
  - Comportamento atual

#### **Na tabela de listagem:**
- Adicionar coluna "Tipo" mostrando "R$" ou "Banco de Horas"
- Badge visual diferenciado

#### **Nova funcionalidade: Remover Horas**
- Botão "Remover Horas do Banco"
- Modal com formulário:
  - Select colaborador
  - Input quantidade de horas
  - Textarea motivo
  - Validação de saldo

### **2. `pages/colaborador_view.php`**

**Modificações necessárias:**

#### **Nova aba "Banco de Horas":**
- Card com saldo atual (grande e destacado)
- Indicador visual:
  - Verde: Saldo positivo
  - Amarelo: Saldo próximo de zero
  - Vermelho: Saldo negativo
- Tabela com histórico completo
- Filtros e busca
- Gráfico de evolução (Chart.js)

#### **Query para buscar saldo:**
```php
SELECT saldo_horas, saldo_minutos, ultima_atualizacao
FROM banco_horas
WHERE colaborador_id = ?
```

#### **Query para buscar histórico:**
```php
SELECT m.*, u.nome as usuario_nome
FROM banco_horas_movimentacoes m
LEFT JOIN usuarios u ON m.usuario_id = u.id
WHERE m.colaborador_id = ?
ORDER BY m.created_at DESC
```

### **3. `pages/ocorrencias_add.php`**

**Modificações necessárias:**

#### **No formulário:**
- Verificar se tipo de ocorrência permite desconto de banco de horas
- Se for falta ou atraso:
  - Mostrar checkbox: "Descontar do Banco de Horas"
  - Se marcado, mostrar:
    - Quantidade de horas que serão descontadas (calculada)
    - Saldo atual do colaborador
    - Saldo após desconto

#### **No processamento POST:**
- Capturar `desconta_banco_horas`
- Se marcado:
  - Calcular horas a descontar
  - Chamar função `descontar_horas_banco_ocorrencia()`
  - Criar movimentação
  - Não calcular desconto em dinheiro

### **4. `includes/functions.php` ou novo arquivo `includes/banco_horas_functions.php`**

**Funções auxiliares necessárias:**

```php
/**
 * Adiciona horas ao banco de horas do colaborador
 */
function adicionar_horas_banco($colaborador_id, $quantidade_horas, $origem, $origem_id, $motivo, $observacoes = '', $usuario_id = null, $data_movimentacao = null) {
    // 1. Busca saldo atual (ou cria se não existir)
    // 2. Calcula novo saldo
    // 3. Insere movimentação
    // 4. Atualiza saldo na tabela banco_horas
    // 5. Retorna dados da movimentação
}

/**
 * Remove horas do banco de horas do colaborador
 */
function remover_horas_banco($colaborador_id, $quantidade_horas, $origem, $origem_id, $motivo, $observacoes = '', $usuario_id = null, $data_movimentacao = null, $permitir_saldo_negativo = false) {
    // 1. Busca saldo atual
    // 2. Valida se tem saldo suficiente (se não permitir negativo)
    // 3. Calcula novo saldo
    // 4. Insere movimentação tipo 'debito'
    // 5. Atualiza saldo
    // 6. Retorna dados da movimentação
}

/**
 * Busca saldo atual do colaborador
 */
function get_saldo_banco_horas($colaborador_id) {
    // Retorna array com saldo_horas, saldo_minutos, ultima_atualizacao
}

/**
 * Calcula horas a descontar baseado na ocorrência
 */
function calcular_horas_desconto_ocorrencia($ocorrencia_id) {
    // Se for falta: retorna jornada do colaborador (ex: 8h)
    // Se for atraso: converte tempo_atraso_minutos em horas
    // Retorna quantidade de horas
}

/**
 * Desconta horas do banco por ocorrência
 */
function descontar_horas_banco_ocorrencia($ocorrencia_id, $usuario_id = null) {
    // 1. Busca dados da ocorrência
    // 2. Calcula horas a descontar
    // 3. Chama remover_horas_banco()
    // 4. Atualiza ocorrencia com banco_horas_movimentacao_id
    // 5. Retorna resultado
}
```

---

## 🎨 Interface do Usuário

### **1. Página `horas_extras.php`**

#### **Modal de Cadastro:**
```
┌─────────────────────────────────────────┐
│ Nova Hora Extra                        │
├─────────────────────────────────────────┤
│ Colaborador: [Select]                  │
│ Data: [Date]                           │
│ Quantidade de Horas: [Input]           │
│                                         │
│ Tipo de Pagamento:                     │
│ ○ Pagar em R$ (padrão)                 │
│ ● Adicionar ao Banco de Horas          │
│                                         │
│ [Se R$: Mostra cálculo de valor]      │
│ [Se Banco: Mostra saldo atual]        │
│                                         │
│ Observações: [Textarea]                 │
│                                         │
│ [Cancelar] [Salvar]                    │
└─────────────────────────────────────────┘
```

#### **Tabela de Listagem:**
```
| ID | Colaborador | Data | Horas | Tipo | Valor | Ações |
|----|-------------|------|-------|------|-------|-------|
| 1  | João Silva  | ...  | 2.00h | R$   | R$... | [X]   |
| 2  | Maria       | ...  | 1.50h | Banco| -     | [X]   |
```

#### **Botão "Remover Horas do Banco":**
- Ao lado do botão "Nova Hora Extra"
- Abre modal para remoção

### **2. Página `colaborador_view.php`**

#### **Aba "Banco de Horas":**
```
┌─────────────────────────────────────────┐
│ Saldo Atual                            │
│ ┌───────────────────────────────────┐  │
│ │  15.50 horas                      │  │
│ │  Última atualização: 15/01/2024   │  │
│ └───────────────────────────────────┘  │
│                                         │
│ Histórico de Movimentações             │
│ [Filtros: Período | Tipo | Origem]    │
│                                         │
│ Data      | Tipo   | Origem | Horas |  │
│ 15/01/2024| Crédito| H.Extra| +2.00 |  │
│ 10/01/2024| Débito | Ocorr. | -1.50 |  │
│ ...                                    │
└─────────────────────────────────────────┘
```

### **3. Página `ocorrencias_add.php`**

#### **Campo adicional no formulário:**
```
┌─────────────────────────────────────────┐
│ [Se tipo = falta ou atraso]             │
│                                         │
│ ☑ Descontar do Banco de Horas          │
│                                         │
│ Saldo atual: 15.50 horas               │
│ Horas a descontar: 1.50 horas          │
│ Saldo após: 14.00 horas                │
└─────────────────────────────────────────┘
```

---

## 🔧 Configurações e Regras de Negócio

### **1. Regras de Conversão**

- **Hora Extra → Banco de Horas:**
  - 1 hora extra trabalhada = 1 hora no banco
  - Não há conversão diferente (1:1)

### **2. Regras de Desconto**

- **Falta:**
  - Desconta jornada completa do colaborador (padrão: 8h)
  - Se não tiver jornada cadastrada, usa 8h padrão

- **Atraso:**
  - Converte minutos de atraso em horas
  - Exemplo: 30 minutos = 0.50 horas

### **3. Saldo Negativo**

- **Opção 1:** Permitir saldo negativo (recomendado)
  - Colaborador pode ficar devendo horas
  - Útil para flexibilidade

- **Opção 2:** Bloquear operação se não tiver saldo
  - Mais restritivo
  - Pode ser configurável por empresa

### **4. Validações**

- Não permitir remover mais horas do que o saldo (se não permitir negativo)
- Validar quantidade de horas > 0
- Validar colaborador existe e está ativo
- Validar data não é futura (para horas extras)

---

## 📈 Melhorias e Sugestões

### **1. Relatórios**

- Relatório de saldo por colaborador
- Relatório de movimentações por período
- Relatório de colaboradores com saldo negativo
- Exportação para Excel/PDF

### **2. Notificações**

- Notificar colaborador quando horas são adicionadas
- Notificar quando saldo está baixo (ex: < 2 horas)
- Notificar quando saldo fica negativo

### **3. Integração com Fechamento de Pagamento**

- Opção de converter saldo em dinheiro no fechamento
- Mostrar saldo disponível no fechamento
- Permitir usar saldo para compensar descontos

### **4. Dashboard**

- Card no dashboard mostrando:
  - Total de horas em banco (soma de todos colaboradores)
  - Colaboradores com saldo negativo
  - Movimentações do mês

### **5. Configurações por Empresa**

- Permitir configurar se empresa usa banco de horas
- Configurar jornada padrão por empresa
- Configurar se permite saldo negativo

### **6. Validade de Horas**

- Opção de configurar validade do saldo (ex: expira em 1 ano)
- Alertar quando horas estão próximas de expirar
- Remover automaticamente horas expiradas

### **7. Ajustes Manuais**

- Interface para RH fazer ajustes manuais no saldo
- Com motivo obrigatório
- Histórico completo

### **8. API REST**

- Endpoints para:
  - Consultar saldo
  - Consultar histórico
  - Adicionar horas (via API)
  - Remover horas (via API)

---

## 🚀 Plano de Implementação

### **Fase 1: Estrutura Base**
1. Criar tabelas `banco_horas` e `banco_horas_movimentacoes`
2. Criar funções auxiliares em `includes/banco_horas_functions.php`
3. Migrar dados existentes (se houver)

### **Fase 2: Horas Extras**
1. Modificar `horas_extras.php` para escolher tipo de pagamento
2. Implementar lógica de adicionar ao banco
3. Adicionar funcionalidade de remover horas
4. Atualizar listagem com tipo de pagamento

### **Fase 3: Visualização**
1. Adicionar aba "Banco de Horas" em `colaborador_view.php`
2. Implementar visualização de saldo
3. Implementar histórico com filtros
4. Adicionar gráfico de evolução (opcional)

### **Fase 4: Integração com Ocorrências**
1. Modificar `ocorrencias_add.php` para opção de desconto
2. Implementar cálculo de horas a descontar
3. Integrar com funções de banco de horas
4. Atualizar visualização de ocorrências

### **Fase 5: Melhorias**
1. Adicionar notificações
2. Criar relatórios
3. Adicionar configurações por empresa
4. Implementar APIs REST

---

## 📝 Scripts SQL Necessários

### **Script de Migração Completo**

```sql
-- 1. Criar tabela de saldo
CREATE TABLE IF NOT EXISTS banco_horas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    saldo_horas DECIMAL(8,2) DEFAULT 0.00,
    saldo_minutos INT DEFAULT 0,
    ultima_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    UNIQUE KEY uk_colaborador (colaborador_id),
    INDEX idx_saldo (saldo_horas)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Criar tabela de movimentações
CREATE TABLE IF NOT EXISTS banco_horas_movimentacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    tipo ENUM('credito', 'debito') NOT NULL,
    origem ENUM('hora_extra', 'ocorrencia', 'ajuste_manual', 'remocao_manual') NOT NULL,
    origem_id INT NULL,
    quantidade_horas DECIMAL(8,2) NOT NULL,
    saldo_anterior DECIMAL(8,2) NOT NULL,
    saldo_posterior DECIMAL(8,2) NOT NULL,
    motivo TEXT NOT NULL,
    observacoes TEXT,
    usuario_id INT NULL,
    data_movimentacao DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo (tipo),
    INDEX idx_origem (origem, origem_id),
    INDEX idx_data_movimentacao (data_movimentacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Modificar tabela horas_extras
ALTER TABLE horas_extras
ADD COLUMN tipo_pagamento ENUM('dinheiro', 'banco_horas') DEFAULT 'dinheiro',
ADD COLUMN banco_horas_movimentacao_id INT NULL,
ADD INDEX idx_tipo_pagamento (tipo_pagamento),
ADD INDEX idx_banco_horas_mov (banco_horas_movimentacao_id),
ADD FOREIGN KEY (banco_horas_movimentacao_id) 
    REFERENCES banco_horas_movimentacoes(id) ON DELETE SET NULL;

-- 4. Modificar tabela ocorrencias
ALTER TABLE ocorrencias
ADD COLUMN desconta_banco_horas BOOLEAN DEFAULT FALSE,
ADD COLUMN horas_descontadas DECIMAL(5,2) NULL,
ADD COLUMN banco_horas_movimentacao_id INT NULL,
ADD INDEX idx_desconta_banco_horas (desconta_banco_horas),
ADD INDEX idx_banco_horas_mov (banco_horas_movimentacao_id),
ADD FOREIGN KEY (banco_horas_movimentacao_id) 
    REFERENCES banco_horas_movimentacoes(id) ON DELETE SET NULL;

-- 5. Inicializar saldos para colaboradores existentes (opcional)
INSERT INTO banco_horas (colaborador_id, saldo_horas, saldo_minutos)
SELECT id, 0.00, 0
FROM colaboradores
WHERE id NOT IN (SELECT colaborador_id FROM banco_horas);
```

---

## ✅ Checklist de Implementação

- [ ] Criar tabelas no banco de dados
- [ ] Criar funções auxiliares (`banco_horas_functions.php`)
- [ ] Modificar `horas_extras.php` (formulário e processamento)
- [ ] Adicionar funcionalidade de remover horas
- [ ] Adicionar aba em `colaborador_view.php`
- [ ] Implementar visualização de saldo e histórico
- [ ] Modificar `ocorrencias_add.php` para desconto
- [ ] Implementar cálculo de horas em ocorrências
- [ ] Adicionar validações e tratamento de erros
- [ ] Testar todos os fluxos
- [ ] Adicionar notificações (opcional)
- [ ] Criar relatórios (opcional)
- [ ] Documentar funcionalidades

---

## 🎯 Conclusão

Este projeto implementa um sistema completo de banco de horas integrado ao sistema existente, permitindo:

✅ **Flexibilidade**: Escolher entre pagamento em R$ ou banco de horas  
✅ **Rastreabilidade**: Histórico completo de todas as movimentações  
✅ **Integração**: Desconto automático em faltas e atrasos  
✅ **Transparência**: Colaboradores podem ver seu saldo e histórico  
✅ **Controle**: RH pode gerenciar e ajustar saldos  

O sistema mantém compatibilidade com o código existente e adiciona novas funcionalidades de forma modular e extensível.

