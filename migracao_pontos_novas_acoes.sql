-- Migração: Adicionar novas ações de pontos
-- Execute este script para adicionar as novas ações de pontuação

-- Adiciona ação: Comunicado Lido
INSERT INTO pontos_config (acao, descricao, pontos, ativo) 
VALUES ('comunicado_lido', 'Ler comunicado', 10, 1)
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

-- Adiciona ação: Confirmar Presença em Evento
INSERT INTO pontos_config (acao, descricao, pontos, ativo) 
VALUES ('confirmar_evento', 'Confirmar presença em evento', 15, 1)
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

-- Adiciona ação: Conclusão de Curso (descrição genérica, pontos são configurados por curso)
INSERT INTO pontos_config (acao, descricao, pontos, ativo) 
VALUES ('concluir_curso', 'Concluir curso (pontos variam por curso)', 0, 1)
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

-- Adiciona ação: Ajuste Manual Crédito
INSERT INTO pontos_config (acao, descricao, pontos, ativo) 
VALUES ('ajuste_manual_credito', 'Crédito manual', 0, 1)
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

-- Adiciona ação: Ajuste Manual Débito
INSERT INTO pontos_config (acao, descricao, pontos, ativo) 
VALUES ('ajuste_manual_debito', 'Débito manual', 0, 1)
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

-- Exibe as ações configuradas
SELECT * FROM pontos_config ORDER BY pontos DESC;
