-- Migração: Adiciona coluna para armazenar o desconto de saldo negativo separadamente
-- Permite detalhar no modal de detalhes do pagamento o que é desconto de ocorrências vs saldo negativo

ALTER TABLE fechamentos_pagamento_itens
ADD COLUMN desconto_saldo_negativo DECIMAL(10,2) DEFAULT 0.00 AFTER descontos;
