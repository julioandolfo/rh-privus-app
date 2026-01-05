# 📋 Instruções: Como Ajustar o Histórico de Movimentações do Banco de Horas

## 🎯 Quando Usar

Use estas ferramentas quando:
- ✅ Houver movimentações incorretas no histórico
- ✅ O saldo do banco de horas estiver errado
- ✅ Você deletou horas extras mas o saldo não foi ajustado
- ✅ Houver inconsistências entre o histórico e o saldo atual

---

## 🖥️ Método 1: Interface Visual (Recomendado)

### **Passo 1: Acessar o Colaborador**

1. Vá em **Colaboradores** → Clique no colaborador
2. Clique na aba **"Banco de Horas"**
3. Role até a seção **"Histórico de Movimentações"**

### **Passo 2: Deletar Movimentações Incorretas**

1. Localize a movimentação incorreta na tabela
2. Clique no botão **🗑️ (lixeira)** na coluna "Ações"
3. Confirme a exclusão
4. ⚠️ **IMPORTANTE**: Após deletar, você **DEVE** recalcular o saldo!

### **Passo 3: Recalcular o Saldo**

1. Após deletar as movimentações incorretas, clique no botão **"Recalcular Saldo"** (botão amarelo no topo da tabela)
2. Confirme a ação
3. O sistema irá:
   - ✅ Recalcular o saldo baseado em todas as movimentações restantes
   - ✅ Corrigir os saldos anterior/posterior de cada movimentação
   - ✅ Atualizar o saldo atual do colaborador
4. A página será recarregada automaticamente com os dados corretos

### **Resultado Esperado:**

- ✅ Histórico limpo (sem movimentações incorretas)
- ✅ Saldo correto
- ✅ Saldos anterior/posterior corretos em cada movimentação

---

## 💻 Método 2: Script SQL Manual (Avançado)

Use este método se preferir fazer correções direto no banco de dados.

### **Arquivo:** `corrigir_banco_horas_manual.sql`

### **Como Usar:**

1. Abra o arquivo `corrigir_banco_horas_manual.sql`
2. **SUBSTITUA** todos os `123` pelo ID do colaborador que deseja corrigir
3. Execute as seções na ordem:

#### **Seção 1: Consultar Situação Atual**
```sql
-- Ver saldo atual
SELECT c.nome_completo, bh.saldo_horas, bh.saldo_minutos
FROM colaboradores c
LEFT JOIN banco_horas bh ON c.id = bh.colaborador_id
WHERE c.id = 123;  -- <-- ALTERE AQUI

-- Ver movimentações
SELECT * FROM banco_horas_movimentacoes 
WHERE colaborador_id = 123  -- <-- ALTERE AQUI
ORDER BY data_movimentacao ASC;
```

#### **Seção 2: Deletar Movimentações Incorretas**
```sql
-- Remover referências
UPDATE horas_extras 
SET banco_horas_movimentacao_id = NULL 
WHERE banco_horas_movimentacao_id = 456;  -- <-- ID da movimentação

-- Deletar movimentação
DELETE FROM banco_horas_movimentacoes WHERE id = 456;
```

#### **Seção 3: Recalcular Saldo Automaticamente**
```sql
-- Executar o script de recálculo (já está no arquivo)
SET @colaborador_id = 123;  -- <-- ALTERE AQUI
-- ... resto do script
```

#### **Seção 5: Verificar Resultado**
```sql
-- Verificar se está tudo correto
SELECT * FROM banco_horas WHERE colaborador_id = 123;
```

---

## 🔍 Exemplo Prático

### **Situação:**
- Você adicionou **-10 horas** (remoção)
- Depois adicionou **-8 horas** (remoção)
- Saldo ficou: **-18h**
- Você deletou o registro de **-10 horas**
- Mas o saldo continua em **-18h** ❌

### **Solução:**

#### **Opção 1: Interface Visual**
1. Acesse o colaborador → Aba "Banco de Horas"
2. No histórico, localize a movimentação de **-10 horas**
3. Clique no botão 🗑️ para deletar
4. Clique em **"Recalcular Saldo"**
5. ✅ Saldo agora está correto: **-8h**

#### **Opção 2: SQL Manual**
```sql
-- 1. Ver ID da movimentação incorreta
SELECT * FROM banco_horas_movimentacoes 
WHERE colaborador_id = 123 
  AND quantidade_horas = 10
  AND tipo = 'debito';

-- Resultado: id = 456

-- 2. Remover referências
UPDATE horas_extras 
SET banco_horas_movimentacao_id = NULL 
WHERE banco_horas_movimentacao_id = 456;

-- 3. Deletar movimentação
DELETE FROM banco_horas_movimentacoes WHERE id = 456;

-- 4. Recalcular saldo (executar seção 3 do script)
SET @colaborador_id = 123;
-- ... resto do script de recálculo

-- 5. Verificar
SELECT saldo_horas, saldo_minutos FROM banco_horas WHERE colaborador_id = 123;
-- Resultado: -8h 0min ✅
```

---

## ⚠️ Avisos Importantes

### **Antes de Deletar:**
- ✅ Certifique-se de que a movimentação está realmente incorreta
- ✅ Anote os dados da movimentação (caso precise reverter)
- ✅ Verifique se há referências em `horas_extras` ou `ocorrencias`

### **Depois de Deletar:**
- ⚠️ **SEMPRE** recalcule o saldo após deletar movimentações
- ⚠️ Não delete movimentações que estão vinculadas a horas extras ou ocorrências ativas (o sistema remove as referências automaticamente)

### **Backup:**
- 💾 Faça backup do banco de dados antes de fazer correções manuais via SQL
- 💾 O script SQL cria backups temporários automaticamente

---

## 🆘 Problemas Comuns

### **Problema 1: Saldo não atualiza após deletar**
**Solução:** Clique em "Recalcular Saldo"

### **Problema 2: Erro ao deletar movimentação**
**Solução:** A movimentação pode estar referenciada em `horas_extras` ou `ocorrencias`. O sistema remove as referências automaticamente, mas se der erro, use o script SQL manual.

### **Problema 3: Saldo ficou zerado**
**Solução:** Você pode ter deletado todas as movimentações. Verifique o histórico e adicione movimentações de ajuste se necessário.

### **Problema 4: Diferença entre saldo e histórico**
**Solução:** Execute a query de verificação (Seção 5.1 do script SQL) para identificar inconsistências, depois use "Recalcular Saldo".

---

## 📞 Suporte

Se após seguir estas instruções o problema persistir:

1. Anote o ID do colaborador
2. Anote o saldo atual (incorreto)
3. Anote o saldo esperado (correto)
4. Liste as movimentações que estão incorretas
5. Entre em contato com o suporte técnico

---

## 🎓 Dicas

- ✅ Use a interface visual sempre que possível (mais seguro)
- ✅ Use o script SQL apenas para correções em massa ou casos complexos
- ✅ Sempre verifique o resultado após fazer correções
- ✅ Documente as correções feitas (anote em observações)
- ✅ Faça backup antes de correções manuais via SQL

---

**Última atualização:** Janeiro 2025
