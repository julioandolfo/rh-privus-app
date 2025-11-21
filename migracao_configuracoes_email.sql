-- Script de Migração: Cria tabela de configurações de email
-- Execute este script para criar a tabela de configurações

CREATE TABLE IF NOT EXISTS configuracoes_email (
    id INT AUTO_INCREMENT PRIMARY KEY,
    smtp_host VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_secure ENUM('tls', 'ssl') NOT NULL DEFAULT 'tls',
    smtp_auth TINYINT(1) NOT NULL DEFAULT 1,
    smtp_username VARCHAR(255) NOT NULL DEFAULT '',
    smtp_password VARCHAR(255) NOT NULL DEFAULT '',
    from_email VARCHAR(255) NOT NULL DEFAULT 'noreply@privus.com.br',
    from_name VARCHAR(255) NOT NULL DEFAULT 'RH Privus',
    smtp_debug TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_config_email (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insere registro padrão
INSERT INTO configuracoes_email (id, smtp_host, smtp_port, smtp_secure, smtp_auth, smtp_username, smtp_password, from_email, from_name, smtp_debug)
VALUES (1, 'smtp.gmail.com', 587, 'tls', 1, '', '', 'noreply@privus.com.br', 'RH Privus', 0)
ON DUPLICATE KEY UPDATE id = id;

