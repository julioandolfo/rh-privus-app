<?php
/**
 * Sistema de Instalação - RH Privus
 * Este arquivo cria as tabelas necessárias no banco de dados
 */

// Verifica se já está instalado
if (file_exists('config/db.php')) {
    $config = include 'config/db.php';
    if (!empty($config['host'])) {
        die('Sistema já instalado! Delete este arquivo após a instalação.');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'] ?? 'localhost';
    $dbname = $_POST['dbname'] ?? '';
    $username = $_POST['username'] ?? 'root';
    $password = $_POST['password'] ?? '';
    
    if (empty($dbname)) {
        $error = 'Nome do banco de dados é obrigatório!';
    } else {
        try {
            // Testa conexão
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Cria o banco se não existir
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");
            
            // SQL para criar todas as tabelas
            $sql = "
            -- Tabela de empresas
            CREATE TABLE IF NOT EXISTS empresas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome_fantasia VARCHAR(255) NOT NULL,
                razao_social VARCHAR(255) NOT NULL,
                cnpj VARCHAR(18) UNIQUE,
                telefone VARCHAR(20),
                email VARCHAR(255),
                cidade VARCHAR(100),
                estado VARCHAR(2),
                status ENUM('ativo', 'inativo') DEFAULT 'ativo',
                percentual_hora_extra DECIMAL(5,2) DEFAULT 50.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_cnpj (cnpj),
                INDEX idx_percentual_hora_extra (percentual_hora_extra)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de setores
            CREATE TABLE IF NOT EXISTS setores (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                nome_setor VARCHAR(255) NOT NULL,
                descricao TEXT,
                status ENUM('ativo', 'inativo') DEFAULT 'ativo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
                INDEX idx_empresa (empresa_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de cargos
            CREATE TABLE IF NOT EXISTS cargos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                nome_cargo VARCHAR(255) NOT NULL,
                descricao TEXT,
                salario_base DECIMAL(10,2),
                status ENUM('ativo', 'inativo') DEFAULT 'ativo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
                INDEX idx_empresa (empresa_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de níveis hierárquicos
            CREATE TABLE IF NOT EXISTS niveis_hierarquicos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                codigo VARCHAR(50) UNIQUE NOT NULL,
                nivel INT NOT NULL COMMENT 'Nível na hierarquia (1 = mais alto, maior número = mais baixo)',
                descricao TEXT,
                status ENUM('ativo', 'inativo') DEFAULT 'ativo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_codigo (codigo),
                INDEX idx_nivel (nivel),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Inserir níveis padrão
            INSERT INTO niveis_hierarquicos (nome, codigo, nivel, descricao, status) VALUES
            ('Diretoria', 'DIRETORIA', 1, 'Nível mais alto da hierarquia', 'ativo'),
            ('Gerência', 'GERENCIA', 2, 'Nível de gerência', 'ativo'),
            ('Supervisão', 'SUPERVISAO', 3, 'Nível de supervisão', 'ativo'),
            ('Coordenação', 'COORDENACAO', 4, 'Nível de coordenação', 'ativo'),
            ('Liderança', 'LIDERANCA', 5, 'Nível de liderança', 'ativo'),
            ('Operacional', 'OPERACIONAL', 6, 'Nível operacional', 'ativo');
            
            -- Tabela de colaboradores
            CREATE TABLE IF NOT EXISTS colaboradores (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                setor_id INT NOT NULL,
                cargo_id INT NOT NULL,
                nivel_hierarquico_id INT NULL,
                lider_id INT NULL,
                nome_completo VARCHAR(255) NOT NULL,
                cpf VARCHAR(14) UNIQUE,
                rg VARCHAR(20),
                data_nascimento DATE,
                telefone VARCHAR(20),
                email_pessoal VARCHAR(255),
                data_inicio DATE NOT NULL,
                status ENUM('ativo', 'pausado', 'desligado') DEFAULT 'ativo',
                tipo_contrato ENUM('PJ', 'CLT', 'Estágio', 'Terceirizado') DEFAULT 'PJ',
                salario DECIMAL(10,2) NULL,
                pix VARCHAR(255) NULL,
                banco VARCHAR(100) NULL,
                agencia VARCHAR(20) NULL,
                conta VARCHAR(30) NULL,
                tipo_conta ENUM('corrente', 'poupanca') NULL,
                cnpj VARCHAR(18) NULL,
                cep VARCHAR(10) NULL,
                logradouro VARCHAR(255) NULL,
                numero VARCHAR(20) NULL,
                complemento VARCHAR(255) NULL,
                bairro VARCHAR(100) NULL,
                cidade_endereco VARCHAR(100) NULL,
                estado_endereco VARCHAR(2) NULL,
                observacoes TEXT,
                foto VARCHAR(255) NULL,
                senha_hash VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
                FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE RESTRICT,
                FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE RESTRICT,
                FOREIGN KEY (nivel_hierarquico_id) REFERENCES niveis_hierarquicos(id) ON DELETE SET NULL,
                FOREIGN KEY (lider_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
                INDEX idx_empresa (empresa_id),
                INDEX idx_setor (setor_id),
                INDEX idx_cargo (cargo_id),
                INDEX idx_nivel_hierarquico (nivel_hierarquico_id),
                INDEX idx_lider (lider_id),
                INDEX idx_status (status),
                INDEX idx_cpf (cpf)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de usuários do sistema
            CREATE TABLE IF NOT EXISTS usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                senha_hash VARCHAR(255) NOT NULL,
                role ENUM('ADMIN', 'RH', 'GESTOR', 'COLABORADOR') NOT NULL,
                empresa_id INT NULL,
                setor_id INT NULL,
                colaborador_id INT NULL,
                foto VARCHAR(255) NULL,
                ultimo_login TIMESTAMP NULL,
                status ENUM('ativo', 'inativo') DEFAULT 'ativo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
                FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE SET NULL,
                FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
                INDEX idx_email (email),
                INDEX idx_role (role),
                INDEX idx_empresa (empresa_id),
                INDEX idx_setor (setor_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de relacionamento usuários-empresas (muitos-para-muitos)
            CREATE TABLE IF NOT EXISTS usuarios_empresas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                empresa_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
                UNIQUE KEY uk_usuario_empresa (usuario_id, empresa_id),
                INDEX idx_usuario (usuario_id),
                INDEX idx_empresa (empresa_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de tipos de ocorrências
            CREATE TABLE IF NOT EXISTS tipos_ocorrencias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                codigo VARCHAR(50) UNIQUE NOT NULL,
                categoria ENUM('pontualidade', 'comportamento', 'desempenho', 'outros') DEFAULT 'outros',
                permite_tempo_atraso BOOLEAN DEFAULT FALSE,
                permite_tipo_ponto BOOLEAN DEFAULT FALSE,
                status ENUM('ativo', 'inativo') DEFAULT 'ativo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_codigo (codigo),
                INDEX idx_categoria (categoria),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Inserir tipos padrão de ocorrências
            INSERT INTO tipos_ocorrencias (nome, codigo, categoria, permite_tempo_atraso, permite_tipo_ponto, status) VALUES
            ('Atraso na Entrada', 'atraso_entrada', 'pontualidade', TRUE, TRUE, 'ativo'),
            ('Atraso no Retorno do Almoço', 'atraso_almoco', 'pontualidade', TRUE, TRUE, 'ativo'),
            ('Atraso no Retorno do Café', 'atraso_cafe', 'pontualidade', TRUE, TRUE, 'ativo'),
            ('Saída Antecipada', 'saida_antecipada', 'pontualidade', FALSE, TRUE, 'ativo'),
            ('Falta', 'falta', 'pontualidade', FALSE, FALSE, 'ativo'),
            ('Ausência Injustificada', 'ausencia_injustificada', 'pontualidade', FALSE, FALSE, 'ativo'),
            ('Falha Operacional', 'falha_operacional', 'desempenho', FALSE, FALSE, 'ativo'),
            ('Desempenho Baixo', 'desempenho_baixo', 'desempenho', FALSE, FALSE, 'ativo'),
            ('Comportamento Inadequado', 'comportamento_inadequado', 'comportamento', FALSE, FALSE, 'ativo'),
            ('Advertência', 'advertencia', 'comportamento', FALSE, FALSE, 'ativo'),
            ('Elogio', 'elogio', 'outros', FALSE, FALSE, 'ativo');
            
            -- Tabela de ocorrências
            CREATE TABLE IF NOT EXISTS ocorrencias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                colaborador_id INT NOT NULL,
                usuario_id INT NOT NULL,
                tipo VARCHAR(100) NOT NULL,
                tipo_ocorrencia_id INT NULL,
                tempo_atraso_minutos INT NULL,
                tipo_ponto ENUM('entrada', 'almoco', 'cafe', 'saida') NULL,
                descricao LONGTEXT,
                data_ocorrencia DATE NOT NULL,
                hora_ocorrencia TIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
                FOREIGN KEY (tipo_ocorrencia_id) REFERENCES tipos_ocorrencias(id) ON DELETE SET NULL,
                INDEX idx_colaborador (colaborador_id),
                INDEX idx_usuario (usuario_id),
                INDEX idx_data (data_ocorrencia),
                INDEX idx_tipo (tipo),
                INDEX idx_tipo_ocorrencia (tipo_ocorrencia_id),
                INDEX idx_tipo_ponto (tipo_ponto)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de promoções/salários
            CREATE TABLE IF NOT EXISTS promocoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                colaborador_id INT NOT NULL,
                salario_anterior DECIMAL(10,2) NOT NULL,
                salario_novo DECIMAL(10,2) NOT NULL,
                motivo TEXT NOT NULL,
                data_promocao DATE NOT NULL,
                usuario_id INT NULL,
                observacoes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
                INDEX idx_colaborador (colaborador_id),
                INDEX idx_data_promocao (data_promocao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de horas extras
            CREATE TABLE IF NOT EXISTS horas_extras (
                id INT AUTO_INCREMENT PRIMARY KEY,
                colaborador_id INT NOT NULL,
                data_trabalho DATE NOT NULL,
                quantidade_horas DECIMAL(5,2) NOT NULL COMMENT 'Quantidade de horas extras trabalhadas',
                valor_hora DECIMAL(10,2) NOT NULL COMMENT 'Valor da hora normal do colaborador',
                percentual_adicional DECIMAL(5,2) NOT NULL COMMENT '% adicional de hora extra',
                valor_total DECIMAL(10,2) NOT NULL COMMENT 'Valor total calculado',
                observacoes TEXT,
                usuario_id INT NULL COMMENT 'Usuário que cadastrou',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
                INDEX idx_colaborador (colaborador_id),
                INDEX idx_data_trabalho (data_trabalho),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de fechamentos de pagamento
            CREATE TABLE IF NOT EXISTS fechamentos_pagamento (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                mes_referencia VARCHAR(7) NOT NULL COMMENT 'Formato: YYYY-MM',
                data_fechamento DATE NOT NULL,
                total_colaboradores INT DEFAULT 0,
                total_pagamento DECIMAL(12,2) DEFAULT 0.00,
                total_horas_extras DECIMAL(12,2) DEFAULT 0.00,
                status ENUM('aberto', 'fechado', 'pago') DEFAULT 'aberto',
                observacoes TEXT,
                usuario_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
                INDEX idx_empresa (empresa_id),
                INDEX idx_mes_referencia (mes_referencia),
                INDEX idx_status (status),
                UNIQUE KEY uk_empresa_mes (empresa_id, mes_referencia)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de itens do fechamento
            CREATE TABLE IF NOT EXISTS fechamentos_pagamento_itens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fechamento_id INT NOT NULL,
                colaborador_id INT NOT NULL,
                salario_base DECIMAL(10,2) NOT NULL,
                horas_extras DECIMAL(5,2) DEFAULT 0.00,
                valor_horas_extras DECIMAL(10,2) DEFAULT 0.00,
                descontos DECIMAL(10,2) DEFAULT 0.00,
                adicionais DECIMAL(10,2) DEFAULT 0.00,
                valor_total DECIMAL(10,2) NOT NULL,
                observacoes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (fechamento_id) REFERENCES fechamentos_pagamento(id) ON DELETE CASCADE,
                FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
                INDEX idx_fechamento (fechamento_id),
                INDEX idx_colaborador (colaborador_id),
                UNIQUE KEY uk_fechamento_colaborador (fechamento_id, colaborador_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de tipos de bônus
            CREATE TABLE IF NOT EXISTS tipos_bonus (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                descricao TEXT NULL,
                status ENUM('ativo', 'inativo') DEFAULT 'ativo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_nome (nome)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Inserir tipos de bônus padrão
            INSERT INTO tipos_bonus (nome, descricao, status) VALUES
            ('Vale Transporte', 'Auxílio transporte para deslocamento', 'ativo'),
            ('Vale Alimentação', 'Auxílio alimentação', 'ativo'),
            ('Vale Refeição', 'Auxílio refeição', 'ativo'),
            ('Plano de Saúde', 'Auxílio plano de saúde', 'ativo'),
            ('Bônus', 'Bônus variável', 'ativo');
            
            -- Tabela de bônus dos colaboradores
            CREATE TABLE IF NOT EXISTS colaboradores_bonus (
                id INT AUTO_INCREMENT PRIMARY KEY,
                colaborador_id INT NOT NULL,
                tipo_bonus_id INT NOT NULL,
                valor DECIMAL(10,2) NOT NULL,
                data_inicio DATE NULL,
                data_fim DATE NULL,
                observacoes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
                FOREIGN KEY (tipo_bonus_id) REFERENCES tipos_bonus(id) ON DELETE RESTRICT,
                INDEX idx_colaborador (colaborador_id),
                INDEX idx_tipo_bonus (tipo_bonus_id),
                INDEX idx_data_inicio (data_inicio),
                INDEX idx_data_fim (data_fim)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de itens de bônus no fechamento de pagamentos
            CREATE TABLE IF NOT EXISTS fechamentos_pagamento_bonus (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fechamento_pagamento_id INT NOT NULL,
                colaborador_id INT NOT NULL,
                tipo_bonus_id INT NOT NULL,
                valor DECIMAL(10,2) NOT NULL,
                observacoes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (fechamento_pagamento_id) REFERENCES fechamentos_pagamento(id) ON DELETE CASCADE,
                FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
                FOREIGN KEY (tipo_bonus_id) REFERENCES tipos_bonus(id) ON DELETE RESTRICT,
                INDEX idx_fechamento (fechamento_pagamento_id),
                INDEX idx_colaborador (colaborador_id),
                INDEX idx_tipo_bonus (tipo_bonus_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Tabela de configurações de email
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
            
            -- Insere registro padrão de configurações de email
            INSERT INTO configuracoes_email (id, smtp_host, smtp_port, smtp_secure, smtp_auth, smtp_username, smtp_password, from_email, from_name, smtp_debug)
            VALUES (1, 'smtp.gmail.com', 587, 'tls', 1, '', '', 'noreply@privus.com.br', 'RH Privus', 0);
            
            -- Tabela de templates de email
            CREATE TABLE IF NOT EXISTS email_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(50) UNIQUE NOT NULL COMMENT 'Código único do template (ex: novo_colaborador)',
                nome VARCHAR(255) NOT NULL COMMENT 'Nome descritivo do template',
                assunto VARCHAR(255) NOT NULL COMMENT 'Assunto do email (pode conter variáveis)',
                corpo_html LONGTEXT NOT NULL COMMENT 'Corpo do email em HTML (pode conter variáveis)',
                corpo_texto TEXT NULL COMMENT 'Versão texto do email (opcional)',
                ativo TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se o template está ativo',
                variaveis_disponiveis TEXT NULL COMMENT 'JSON com lista de variáveis disponíveis',
                descricao TEXT NULL COMMENT 'Descrição do template e quando é usado',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_codigo (codigo),
                INDEX idx_ativo (ativo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- Insere templates padrão
            INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, variaveis_disponiveis, descricao) VALUES
            ('novo_colaborador', 'Novo Colaborador', 'Bem-vindo ao {empresa_nome}!', 
            '<h2>Olá {nome_completo}!</h2><p>Bem-vindo ao <strong>{empresa_nome}</strong>!</p><p>Estamos felizes em tê-lo(a) em nossa equipe.</p><p><strong>Dados do seu cadastro:</strong></p><ul><li><strong>Cargo:</strong> {cargo_nome}</li><li><strong>Setor:</strong> {setor_nome}</li><li><strong>Data de Início:</strong> {data_inicio}</li><li><strong>Tipo de Contrato:</strong> {tipo_contrato}</li></ul><p>Bem-vindo(a)!</p>',
            'Olá {nome_completo}!\\n\\nBem-vindo ao {empresa_nome}!\\n\\nDados: Cargo: {cargo_nome}, Setor: {setor_nome}, Data: {data_inicio}',
            1,
            '[\"nome_completo\", \"empresa_nome\", \"cargo_nome\", \"setor_nome\", \"data_inicio\", \"tipo_contrato\", \"cpf\", \"email_pessoal\", \"telefone\"]',
            'Enviado quando um novo colaborador é cadastrado.'),
            ('nova_promocao', 'Nova Promoção', 'Parabéns! Você recebeu uma promoção!',
            '<h2>Parabéns, {nome_completo}!</h2><p>Temos o prazer de informar que você recebeu uma promoção!</p><p><strong>Detalhes:</strong></p><ul><li><strong>Data:</strong> {data_promocao}</li><li><strong>Salário Anterior:</strong> R$ {salario_anterior}</li><li><strong>Novo Salário:</strong> R$ {salario_novo}</li><li><strong>Motivo:</strong> {motivo}</li></ul><p>Parabéns!</p>',
            'Parabéns, {nome_completo}!\\n\\nVocê recebeu uma promoção!\\nData: {data_promocao}\\nNovo Salário: R$ {salario_novo}',
            1,
            '[\"nome_completo\", \"data_promocao\", \"salario_anterior\", \"salario_novo\", \"motivo\", \"observacoes\", \"empresa_nome\"]',
            'Enviado quando uma promoção é registrada.'),
            ('fechamento_pagamento', 'Fechamento de Pagamento', 'Seu pagamento de {mes_referencia} está disponível',
            '<h2>Olá {nome_completo}!</h2><p>Informamos que o fechamento do pagamento referente ao mês de <strong>{mes_referencia}</strong> está disponível.</p><p><strong>Resumo:</strong></p><ul><li><strong>Salário Base:</strong> R$ {salario_base}</li><li><strong>Horas Extras:</strong> {horas_extras} horas - R$ {valor_horas_extras}</li><li><strong>Valor Total:</strong> R$ {valor_total}</li></ul>',
            'Olá {nome_completo}!\\n\\nFechamento do mês {mes_referencia} disponível.\\nValor Total: R$ {valor_total}',
            1,
            '[\"nome_completo\", \"mes_referencia\", \"salario_base\", \"horas_extras\", \"valor_horas_extras\", \"descontos\", \"adicionais\", \"valor_total\", \"data_fechamento\", \"observacoes\"]',
            'Enviado para cada colaborador quando um fechamento é realizado.'),
            ('ocorrencia', 'Ocorrência Registrada', 'Ocorrência registrada - {tipo_ocorrencia}',
            '<h2>Olá {nome_completo}!</h2><p>Informamos que foi registrada uma ocorrência em seu nome.</p><p><strong>Detalhes:</strong></p><ul><li><strong>Tipo:</strong> {tipo_ocorrencia}</li><li><strong>Data:</strong> {data_ocorrencia}</li><li><strong>Descrição:</strong> {descricao}</li></ul>',
            'Olá {nome_completo}!\\n\\nOcorrência registrada:\\nTipo: {tipo_ocorrencia}\\nData: {data_ocorrencia}',
            1,
            '[\"nome_completo\", \"tipo_ocorrencia\", \"data_ocorrencia\", \"hora_ocorrencia\", \"tempo_atraso\", \"descricao\", \"usuario_registro\", \"data_registro\", \"empresa_nome\", \"setor_nome\", \"cargo_nome\"]',
            'Enviado quando uma ocorrência é registrada para um colaborador.');
            ";
            
            // Executa o SQL
            $pdo->exec($sql);
            
            // Cria arquivo de configuração
            $configContent = "<?php\n";
            $configContent .= "return [\n";
            $configContent .= "    'host' => '$host',\n";
            $configContent .= "    'dbname' => '$dbname',\n";
            $configContent .= "    'username' => '$username',\n";
            $configContent .= "    'password' => '$password',\n";
            $configContent .= "];\n";
            
            // Cria diretório config se não existir
            if (!is_dir('config')) {
                mkdir('config', 0755, true);
            }
            
            file_put_contents('config/db.php', $configContent);
            
            // Cria usuário admin padrão
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, role, status) VALUES (?, ?, ?, 'ADMIN', 'ativo')");
            $stmt->execute(['Administrador', 'admin@privus.com.br', $adminPassword]);
            
            $success = 'Instalação concluída com sucesso!<br>Usuário padrão: admin@privus.com.br<br>Senha: admin123<br><strong>IMPORTANTE: Delete o arquivo install.php após o primeiro login!</strong>';
            
        } catch (PDOException $e) {
            $error = 'Erro: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - RH Privus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Instalação - Sistema RH Privus</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                            <a href="login.php" class="btn btn-primary">Ir para Login</a>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Host do Banco</label>
                                    <input type="text" name="host" class="form-control" value="localhost" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nome do Banco de Dados</label>
                                    <input type="text" name="dbname" class="form-control" placeholder="rh_privus" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Usuário</label>
                                    <input type="text" name="username" class="form-control" value="root" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Senha</label>
                                    <input type="password" name="password" class="form-control">
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Instalar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

