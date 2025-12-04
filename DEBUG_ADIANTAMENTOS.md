# Guia de Debug - Adiantamentos não aparecem na coluna de Descontos

## Como debugar:

### 1. Verificar no código fonte da página

1. Abra um fechamento que deveria ter adiantamentos descontados
2. Clique com botão direito → "Ver código-fonte da página" (ou Ctrl+U)
3. Procure por `<!-- DEBUG ADIANTAMENTOS:`
4. Você verá algo como:
   ```
   <!-- DEBUG ADIANTAMENTOS: Colab 123 (João Silva): Adiantamentos=50.00 | Colab 456 (Maria Santos): Adiantamentos=0 -->
   ```

### 2. Usar o script de debug

Execute no terminal:
```bash
php scripts/debug_adiantamentos_fechamento.php [ID_DO_FECHAMENTO]
```

Exemplo:
```bash
php scripts/debug_adiantamentos_fechamento.php 10
```

O script mostrará:
- Dados do fechamento
- Itens do fechamento com valores de descontos
- Adiantamentos encontrados por colaborador
- Adiantamentos pendentes

### 3. Verificar diretamente no banco de dados

Execute estas queries:

```sql
-- Ver adiantamentos descontados em um fechamento específico
SELECT 
    fa.*,
    c.nome_completo as colaborador,
    f.mes_referencia as fechamento_mes
FROM fechamentos_pagamento_adiantamentos fa
INNER JOIN colaboradores c ON fa.colaborador_id = c.id
INNER JOIN fechamentos_pagamento f ON fa.fechamento_pagamento_id = f.id
WHERE fa.fechamento_desconto_id = [ID_DO_FECHAMENTO]
AND fa.descontado = 1;

-- Ver itens do fechamento com descontos
SELECT 
    i.*,
    c.nome_completo as colaborador
FROM fechamentos_pagamento_itens i
INNER JOIN colaboradores c ON i.colaborador_id = c.id
WHERE i.fechamento_id = [ID_DO_FECHAMENTO];
```

### 4. Verificar no console do navegador

1. Abra o DevTools (F12)
2. Vá na aba "Console"
3. Execute:
```javascript
// Verificar se há comentários de debug no HTML
document.querySelectorAll('*').forEach(el => {
    Array.from(el.childNodes).forEach(node => {
        if (node.nodeType === 8 && node.textContent.includes('DEBUG ADIANTAMENTOS')) {
            console.log('DEBUG encontrado:', node.textContent);
        }
    });
});
```

## Possíveis problemas:

1. **Adiantamentos não foram marcados como descontados**
   - Verificar se `descontado = 1` na tabela `fechamentos_pagamento_adiantamentos`
   - Verificar se `fechamento_desconto_id` está preenchido

2. **Fechamento criado antes da implementação**
   - Os adiantamentos podem não estar incluídos no campo `descontos`
   - O código tenta buscar alternativamente por mês de referência

3. **Mês de referência diferente**
   - Verificar se `mes_desconto` do adiantamento é igual ao `mes_referencia` do fechamento

4. **Colaborador não encontrado**
   - Verificar se o `colaborador_id` do adiantamento corresponde ao do item do fechamento

## Solução rápida:

Se os adiantamentos existem mas não aparecem, pode ser que o `fechamento_desconto_id` não foi salvo. Execute:

```sql
-- Atualizar adiantamentos para um fechamento específico
UPDATE fechamentos_pagamento_adiantamentos fa
INNER JOIN fechamentos_pagamento f ON fa.fechamento_pagamento_id = f.id
SET fa.fechamento_desconto_id = [ID_DO_FECHAMENTO],
    fa.descontado = 1
WHERE fa.colaborador_id IN (
    SELECT colaborador_id 
    FROM fechamentos_pagamento_itens 
    WHERE fechamento_id = [ID_DO_FECHAMENTO]
)
AND fa.mes_desconto = (SELECT mes_referencia FROM fechamentos_pagamento WHERE id = [ID_DO_FECHAMENTO])
AND fa.descontado = 0;
```

