-- Migração: Campos complementares em colaboradores
-- Estado civil, filhos e formações

-- 1. Adicionar estado civil em colaboradores
SET @dbname = DATABASE();
SET @tablename = 'colaboradores';

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'estado_civil') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN estado_civil ENUM(\'solteiro\', \'casado\', \'divorciado\', \'viuvo\', \'uniao_estavel\', \'outro\') NULL AFTER data_nascimento'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Tabela de filhos dos colaboradores
CREATE TABLE IF NOT EXISTS colaboradores_filhos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    data_nascimento DATE NULL,
    idade INT NULL COMMENT 'Idade calculada ou informada',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_colaborador (colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabela de cursos e formações dos colaboradores
CREATE TABLE IF NOT EXISTS colaboradores_formacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    tipo ENUM('curso', 'graduacao', 'pos_graduacao', 'mestrado', 'doutorado', 'tecnico', 'certificacao', 'outro') DEFAULT 'curso',
    nome VARCHAR(255) NOT NULL COMMENT 'Nome do curso/formação',
    instituicao VARCHAR(255) NULL COMMENT 'Nome da instituição',
    data_inicio DATE NULL,
    data_conclusao DATE NULL,
    carga_horaria INT NULL COMMENT 'Carga horária em horas',
    status ENUM('em_andamento', 'concluido', 'trancado', 'cancelado') DEFAULT 'concluido',
    certificado VARCHAR(500) NULL COMMENT 'Caminho do arquivo do certificado',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo (tipo),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

