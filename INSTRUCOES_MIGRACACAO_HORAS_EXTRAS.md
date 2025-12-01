# 📋 Instruções - Correção de Horas Extras em Fechamentos

## 🎯 Problema Identificado

O sistema não estava controlando quais horas extras já foram pagas em fechamentos anteriores, causando duplicação de valores ao criar múltiplos fechamentos para o mesmo período.

## ✅ Solução Implementada

Foi adicionado um campo `fechamento_pagamento_id` na tabela `horas_extras` para rastrear em qual fechamento cada hora extra foi incluída.

---

## 📝 Passo a Passo para Aplicar

### 1️⃣ Executar Migração do Banco de Dados

Execute o arquivo SQL no seu banco de dados:

```bash
# Via terminal MySQL
mysql -u seu_usuario -p seu_banco < migracao_controle_horas_extras_pagas.sql

# Ou via phpMyAdmin: Importar > migracao_controle_horas_extras_pagas.sql
```

**Arquivo:** `migracao_controle_horas_extras_pagas.sql`

---

### 2️⃣ Aprovar Solicitações Pendentes (Se Houver)

Se você tem solicitações de horas extras pendentes que precisam ser aprovadas em lote:

**Opção A - Via Script PHP:**
```bash
php scripts/aprovar_solicitacoes_pendentes.php
```

**Opção B - Via SQL Direto:**
```bash
mysql -u seu_usuario -p seu_banco < scripts/aprovar_solicitacoes_pendentes.sql
```

---

## 🔧 O Que Foi Alterado

### Arquivos Modificados:

1. **`pages/fechamento_pagamentos.php`**
   - ✅ Busca apenas horas extras NÃO PAGAS (`fechamento_pagamento_id IS NULL`)
   - ✅ Marca horas extras com o ID do fechamento ao incluí-las
   - ✅ Atualizado cálculo de resumo para considerar apenas não pagas

2. **`api/get_resumo_pagamentos.php`**
   - ✅ Filtro de horas extras não pagas simplificado

### Novos Arquivos Criados:

1. **`migracao_controle_horas_extras_pagas.sql`**
   - Adiciona campo `fechamento_pagamento_id` na tabela `horas_extras`

2. **`scripts/aprovar_solicitacoes_pendentes.php`** e `.sql`
   - Aprova todas as solicitações pendentes em lote

---

## ⚙️ Como Funciona Agora

### Fluxo de Horas Extras:

#### **Para RH/GESTOR/ADMIN (Cadastro Direto):**
1. Acessa `horas_extras.php`
2. Cadastra hora extra manualmente
3. Hora extra vai direto para tabela `horas_extras`
4. Campo `fechamento_pagamento_id` = `NULL` (não paga)
5. ✅ **Já aparece no fechamento**

#### **Para COLABORADOR (Solicitação):**
1. Acessa `solicitar_horas_extras.php`
2. Preenche formulário ou usa timer
3. Solicitação vai para `solicitacoes_horas_extras` (status: pendente)
4. RH acessa `aprovar_horas_extras.php`
5. Ao aprovar → cria registro em `horas_extras` com `fechamento_pagamento_id` = `NULL`
6. ✅ **Aparece no próximo fechamento**

#### **No Fechamento de Pagamento:**
1. Sistema busca horas extras onde:
   - `colaborador_id` = colaborador do fechamento
   - `data_trabalho` entre primeiro e último dia do mês
   - `tipo_pagamento` = 'dinheiro' (ou NULL)
   - **`fechamento_pagamento_id IS NULL`** ← NOVO FILTRO
2. Ao criar fechamento → marca as horas extras com `fechamento_pagamento_id = [ID do fechamento]`
3. ✅ **Essas horas NÃO aparecem em fechamentos futuros**

---

## 🧪 Como Testar

1. **Verifique a migração:**
   ```sql
   DESCRIBE horas_extras;
   -- Deve mostrar o campo fechamento_pagamento_id
   ```

2. **Cadastre uma hora extra:**
   - Vá em Colaboradores → Horas Extras
   - Cadastre uma hora extra para um colaborador
   - Verifique se ela aparece no card de "Extras Somados"

3. **Crie um fechamento:**
   - Vá em Financeiro → Fechamento de Pagamentos
   - Crie um fechamento para o mês da hora extra cadastrada
   - Verifique se a hora extra aparece no fechamento

4. **Tente criar outro fechamento para o mesmo mês:**
   - O sistema deve bloquear (já existe fechamento regular)
   - OU se for um fechamento extra, a hora extra NÃO deve aparecer (já foi paga)

5. **Verifique o card de resumo:**
   - O valor em "Extras Somados" deve diminuir após criar o fechamento
   - Pois aquelas horas agora têm `fechamento_pagamento_id` preenchido

---

## ⚠️ Importante

- **Horas extras antigas** (cadastradas antes da migração) terão `fechamento_pagamento_id = NULL`
- Ao criar o primeiro fechamento após a migração, elas serão marcadas automaticamente
- **Não é necessário** atualizar manualmente horas extras antigas
- O sistema trata isso automaticamente no próximo fechamento

---

## 🆘 Troubleshooting

### Problema: "Horas extras não aparecem no fechamento"

**Possíveis causas:**
1. Já foram incluídas em fechamento anterior (verifique `fechamento_pagamento_id`)
2. São do tipo `banco_horas` (não aparecem em dinheiro)
3. Estão fora do período do fechamento

**Verificação:**
```sql
SELECT id, colaborador_id, data_trabalho, quantidade_horas, valor_total, 
       tipo_pagamento, fechamento_pagamento_id
FROM horas_extras
WHERE colaborador_id = [ID_DO_COLABORADOR]
  AND data_trabalho BETWEEN '[DATA_INICIO]' AND '[DATA_FIM]'
ORDER BY data_trabalho DESC;
```

### Problema: "Query error sobre fechamento_pagamento_id"

**Causa:** Migração não foi executada

**Solução:**
```bash
mysql -u seu_usuario -p seu_banco < migracao_controle_horas_extras_pagas.sql
```

---

## ✅ Checklist de Implementação

- [ ] Executar `migracao_controle_horas_extras_pagas.sql`
- [ ] (Opcional) Aprovar solicitações pendentes
- [ ] Testar cadastro de hora extra pelo RH
- [ ] Testar solicitação de hora extra pelo colaborador
- [ ] Testar criação de fechamento
- [ ] Verificar que horas extras não duplicam em fechamentos
- [ ] Verificar card de resumo atualiza corretamente

---

## 📊 Impacto

✅ **Positivo:**
- Horas extras não duplicam mais em fechamentos
- Controle preciso de quais horas já foram pagas
- Possibilidade de rastrear em qual fechamento cada hora foi paga

⚠️ **Atenção:**
- Primeira execução após migração pode incluir horas antigas (correto)
- Certifique-se de executar a migração antes de criar novos fechamentos

---

**Desenvolvido em:** Dezembro 2025
**Versão:** 1.0

