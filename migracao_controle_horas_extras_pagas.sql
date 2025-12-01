-- =====================================================
-- Migração: Adicionar controle de horas extras pagas
-- =====================================================
-- Este script adiciona um campo para controlar quais 
-- horas extras já foram incluídas em fechamentos de pagamento

-- 1. Adiciona campo de controle na tabela horas_extras
ALTER TABLE horas_extras
ADD COLUMN fechamento_pagamento_id INT NULL COMMENT 'ID do fechamento que incluiu esta hora extra' AFTER tipo_pagamento,
ADD INDEX idx_fechamento_pagamento (fechamento_pagamento_id),
ADD FOREIGN KEY (fechamento_pagamento_id) 
    REFERENCES fechamentos_pagamento(id) ON DELETE SET NULL;

-- 2. Comentário explicativo
-- Este campo será preenchido quando um fechamento de pagamento for criado
-- Apenas horas extras com fechamento_pagamento_id NULL serão incluídas em novos fechamentos
-- Isso evita que a mesma hora extra seja paga múltiplas vezes

SELECT 'Migração concluída com sucesso! Campo fechamento_pagamento_id adicionado.' as status;

