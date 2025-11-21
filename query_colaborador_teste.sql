-- Query para cadastrar um colaborador de teste
-- Execute esta query após ter empresas, setores e cargos cadastrados

-- Primeiro, vamos verificar se existem empresas, setores e cargos
-- Se não existirem, você precisará cadastrá-los primeiro

-- Exemplo de INSERT para colaborador de teste
INSERT INTO colaboradores (
    empresa_id,
    setor_id,
    cargo_id,
    nivel_hierarquico_id,
    lider_id,
    nome_completo,
    cpf,
    cnpj,
    rg,
    data_nascimento,
    telefone,
    email_pessoal,
    data_inicio,
    status,
    tipo_contrato,
    salario,
    pix,
    banco,
    agencia,
    conta,
    tipo_conta,
    observacoes,
    senha_hash,
    created_at,
    updated_at
) VALUES (
    1,  -- empresa_id (ajuste conforme sua empresa)
    1,  -- setor_id (ajuste conforme seu setor)
    1,  -- cargo_id (ajuste conforme seu cargo)
    NULL,  -- nivel_hierarquico_id (opcional)
    NULL,  -- lider_id (opcional)
    'João Silva Santos',  -- nome_completo
    '12345678900',  -- cpf (apenas números)
    '12345678000190',  -- cnpj (apenas números, apenas para PJ)
    '123456789',  -- rg
    '1990-05-15',  -- data_nascimento
    '11987654321',  -- telefone (apenas números)
    'joao.silva@email.com',  -- email_pessoal
    CURDATE(),  -- data_inicio (data atual)
    'ativo',  -- status
    'CLT',  -- tipo_contrato (PJ, CLT, Estágio, Terceirizado)
    5000.00,  -- salario
    'joao.silva@email.com',  -- pix (chave PIX)
    'Banco do Brasil',  -- banco
    '1234',  -- agencia
    '12345-6',  -- conta
    'corrente',  -- tipo_conta (corrente ou poupanca)
    'Colaborador de teste para desenvolvimento',  -- observacoes
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- senha_hash (senha: "password" - use password_hash() no PHP)
    NOW(),  -- created_at
    NOW()   -- updated_at
);

-- Exemplo alternativo: Colaborador PJ (com CNPJ)
INSERT INTO colaboradores (
    empresa_id,
    setor_id,
    cargo_id,
    nome_completo,
    cpf,
    cnpj,
    data_inicio,
    status,
    tipo_contrato,
    salario,
    pix,
    observacoes,
    senha_hash
) VALUES (
    1,
    1,
    1,
    'Maria Oliveira Costa',
    '98765432100',
    '98765432000111',  -- CNPJ para PJ
    CURDATE(),
    'ativo',
    'PJ',  -- Tipo PJ
    8000.00,
    'maria.costa@email.com',
    'Colaborador PJ de teste',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'  -- senha: "password"
);

-- Exemplo mínimo (apenas campos obrigatórios)
INSERT INTO colaboradores (
    empresa_id,
    setor_id,
    cargo_id,
    nome_completo,
    data_inicio,
    status,
    tipo_contrato
) VALUES (
    1,
    1,
    1,
    'Pedro Teste',
    CURDATE(),
    'ativo',
    'CLT'
);

-- Para gerar hash de senha no PHP, use:
-- password_hash('sua_senha_aqui', PASSWORD_DEFAULT)

-- Exemplo com senha "123456":
-- password_hash('123456', PASSWORD_DEFAULT)

