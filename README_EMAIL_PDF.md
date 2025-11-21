# Sistema de Email e PDF - RH Privus

## 📧 Configuração de Email/SMTP

### 1. Instalar dependências via Composer

```bash
composer install
```

Isso instalará:
- **PHPMailer** - Biblioteca para envio de emails via SMTP
- **TCPDF** - Biblioteca para geração de PDFs

### 2. Configurar SMTP

#### Opção A: Arquivo de configuração (Recomendado)

Edite o arquivo `config/email.php` e configure suas credenciais SMTP:

```php
'smtp' => [
    'host' => 'smtp.gmail.com',           // Seu servidor SMTP
    'port' => 587,                         // Porta SMTP
    'secure' => 'tls',                     // 'tls' ou 'ssl'
    'username' => 'seu_email@gmail.com',   // Seu email
    'password' => 'sua_senha_app',         // Senha ou senha de app
    'from_email' => 'noreply@privus.com.br',
    'from_name' => 'RH Privus',
]
```

#### Opção B: Arquivo .env (Alternativa)

1. Copie o arquivo `.env.example` para `.env`:
```bash
cp .env.example .env
```

2. Edite o arquivo `.env` com suas credenciais:
```
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=seu_email@gmail.com
SMTP_PASSWORD=sua_senha_app
```

### 3. Configurações para Gmail

Se estiver usando Gmail:

1. Ative a "Verificação em duas etapas" na sua conta Google
2. Gere uma "Senha de app":
   - Acesse: https://myaccount.google.com/apppasswords
   - Selecione "App" e "Outro (nome personalizado)"
   - Digite "RH Privus"
   - Use a senha gerada no campo `SMTP_PASSWORD`

### 4. Configurações para outros provedores

#### Outlook/Hotmail
```
SMTP_HOST=smtp-mail.outlook.com
SMTP_PORT=587
SMTP_SECURE=tls
```

#### SendGrid
```
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USERNAME=apikey
SMTP_PASSWORD=sua_api_key_sendgrid
```

#### Amazon SES
```
SMTP_HOST=email-smtp.us-east-1.amazonaws.com
SMTP_PORT=587
SMTP_SECURE=tls
```

## 📄 Uso do Sistema de Email

### Enviar email simples

```php
require_once __DIR__ . '/includes/email.php';

$resultado = enviar_email(
    'destinatario@email.com',
    'Assunto do Email',
    '<h1>Olá!</h1><p>Esta é uma mensagem HTML.</p>'
);

if ($resultado['success']) {
    echo 'Email enviado!';
} else {
    echo 'Erro: ' . $resultado['message'];
}
```

### Enviar email com opções avançadas

```php
$resultado = enviar_email(
    'destinatario@email.com',
    'Assunto',
    '<p>Mensagem HTML</p>',
    [
        'nome_destinatario' => 'João Silva',
        'de_email' => 'outro@email.com',
        'de_nome' => 'Nome Remetente',
        'reply_to' => 'resposta@email.com',
        'cc' => ['cc1@email.com', 'cc2@email.com'],
        'bcc' => ['bcc@email.com'],
        'anexos' => [
            '/caminho/para/arquivo.pdf',
            ['path' => '/caminho/arquivo2.pdf', 'nome' => 'nome_customizado.pdf']
        ],
        'texto_alternativo' => 'Versão texto do email'
    ]
);
```

### Funções pré-configuradas

```php
// Email de boas-vindas
enviar_email_boas_vindas('usuario@email.com', 'Nome Usuário', 'senha123');

// Email de recuperação de senha
enviar_email_recuperacao_senha('usuario@email.com', 'Nome Usuário', $token);
```

## 📑 Uso do Sistema de PDF

### Gerar PDF simples

```php
require_once __DIR__ . '/includes/pdf.php';

$pdf = criar_pdf('Meu Documento', 'Autor', 'Assunto');
$pdf->AddPage();

$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Conteúdo do PDF', 0, 1);

// Mostrar no navegador
output_pdf($pdf, 'documento.pdf', 'I');

// Ou forçar download
output_pdf($pdf, 'documento.pdf', 'D');

// Ou salvar em arquivo
$caminho = output_pdf($pdf, 'documento.pdf', 'F');
```

### Gerar PDF de relatório de ocorrências

```php
$ocorrencias = [
    [
        'data_ocorrencia' => '2024-01-15',
        'colaborador_nome' => 'João Silva',
        'tipo' => 'Atraso',
        'descricao' => 'Atraso de 30 minutos'
    ],
    // ... mais ocorrências
];

$pdf = gerar_pdf_ocorrencias($ocorrencias, [
    'data_inicio' => '2024-01-01',
    'data_fim' => '2024-01-31'
]);

output_pdf($pdf, 'relatorio_ocorrencias.pdf', 'D');
```

### Gerar PDF de holerite

```php
$colaborador = [
    'nome_completo' => 'João Silva',
    'cpf' => '12345678900',
    'cargo_nome' => 'Desenvolvedor',
    'setor_nome' => 'TI'
];

$fechamento = [
    'empresa_nome' => 'Empresa XYZ',
    'empresa_cnpj' => '12.345.678/0001-90',
    'mes_referencia' => '2024-01'
];

$itens = [
    ['descricao' => 'Salário Base', 'valor' => 5000.00],
    ['descricao' => 'Horas Extras', 'valor' => 500.00],
    ['descricao' => 'Descontos', 'valor' => -300.00]
];

$pdf = gerar_pdf_holerite($colaborador, $fechamento, $itens);
output_pdf($pdf, 'holerite.pdf', 'D');
```

### Gerar PDF de colaboradores

```php
$colaboradores = [
    [
        'nome_completo' => 'João Silva',
        'cpf' => '12345678900',
        'cargo_nome' => 'Desenvolvedor',
        'setor_nome' => 'TI',
        'status' => 'ativo'
    ],
    // ... mais colaboradores
];

$pdf = gerar_pdf_colaboradores($colaboradores);
output_pdf($pdf, 'relatorio_colaboradores.pdf', 'D');
```

## 🔧 Funções Disponíveis

### Email (`includes/email.php`)

- `enviar_email($para, $assunto, $mensagem, $opcoes)` - Envia email genérico
- `enviar_email_boas_vindas($email, $nome, $senha)` - Email de boas-vindas
- `enviar_email_recuperacao_senha($email, $nome, $token)` - Email de recuperação

### PDF (`includes/pdf.php`)

- `criar_pdf($titulo, $autor, $assunto)` - Cria instância do PDF
- `adicionar_header_pdf($pdf, $titulo, $subtitulo)` - Adiciona header
- `adicionar_footer_pdf($pdf, $texto)` - Adiciona footer
- `gerar_pdf_ocorrencias($ocorrencias, $filtros)` - PDF de ocorrências
- `gerar_pdf_holerite($colaborador, $fechamento, $itens)` - PDF de holerite
- `gerar_pdf_colaboradores($colaboradores, $filtros)` - PDF de colaboradores
- `output_pdf($pdf, $nome, $destino)` - Exibe/salva PDF

## 📝 Notas Importantes

1. **Segurança**: Nunca commite o arquivo `.env` ou `config/email.php` com credenciais reais no Git
2. **Testes**: Use modo debug (`SMTP_DEBUG=true`) apenas em desenvolvimento
3. **Limites**: Verifique os limites de envio do seu provedor SMTP
4. **PDFs**: Os PDFs são gerados em tempo real, considere cachear se necessário

## 🐛 Troubleshooting

### Erro ao enviar email

1. Verifique se as credenciais estão corretas
2. Verifique se a porta está aberta no firewall
3. Para Gmail, use senha de app, não a senha normal
4. Ative o debug temporariamente para ver mensagens detalhadas

### Erro ao gerar PDF

1. Verifique se a biblioteca TCPDF foi instalada: `composer install`
2. Verifique permissões de escrita na pasta `temp/` (se usar destino 'F')
3. Verifique se as fontes estão disponíveis

