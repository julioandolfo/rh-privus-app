-- =====================================================
-- MIGRAÇÃO: Tabela de Execuções de Cron
-- Data: 2024
-- Descrição: Registra execuções dos scripts cron para monitoramento
-- =====================================================

CREATE TABLE IF NOT EXISTS cron_execucoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_cron VARCHAR(100) NOT NULL COMMENT 'Nome do script cron (ex: processar_fechamentos_recorrentes)',
    data_execucao DATETIME NOT NULL COMMENT 'Data e hora da última execução',
    processados INT DEFAULT 0 COMMENT 'Quantidade de itens processados com sucesso',
    erros INT DEFAULT 0 COMMENT 'Quantidade de erros ocorridos',
    status VARCHAR(20) DEFAULT 'sucesso' COMMENT 'Status: sucesso, erro',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_nome_cron (nome_cron),
    INDEX idx_data_execucao (data_execucao),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FIM DA MIGRAÇÃO
-- =====================================================

