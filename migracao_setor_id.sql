-- Script de Migração: Adiciona coluna setor_id na tabela usuarios
-- Execute este script apenas se o sistema já estiver instalado

ALTER TABLE usuarios 
ADD COLUMN setor_id INT NULL AFTER empresa_id,
ADD CONSTRAINT fk_usuarios_setor FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE SET NULL,
ADD INDEX idx_setor (setor_id);

