-- Migração: Adicionar campo 'pago' em fechamentos_pagamento_itens
-- Permite marcar individualmente cada colaborador como pago em um fechamento

-- Adiciona coluna pago (0 = não pago, 1 = pago)
ALTER TABLE fechamentos_pagamento_itens 
ADD COLUMN IF NOT EXISTS pago TINYINT(1) DEFAULT 0 COMMENT 'Status de pagamento individual: 0=Pendente, 1=Pago';

-- Adiciona coluna data_pagamento_item para registrar quando foi marcado como pago
ALTER TABLE fechamentos_pagamento_itens 
ADD COLUMN IF NOT EXISTS data_pagamento_item DATETIME DEFAULT NULL COMMENT 'Data em que o item foi marcado como pago';

-- Adiciona coluna usuario_pagamento para registrar quem marcou como pago
ALTER TABLE fechamentos_pagamento_itens 
ADD COLUMN IF NOT EXISTS usuario_pagamento_id INT DEFAULT NULL COMMENT 'ID do usuário que marcou como pago';

-- Índice para consultas de itens pagos/pendentes
ALTER TABLE fechamentos_pagamento_itens 
ADD INDEX IF NOT EXISTS idx_pago (pago);
