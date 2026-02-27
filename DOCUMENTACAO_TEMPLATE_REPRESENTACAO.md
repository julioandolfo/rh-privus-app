# Template de Contrato de Representação Comercial

## Visão Geral

Template baseado no contrato de representação comercial da AZZO DISTRIBUIDORA LTDA conforme Lei 4.886/65.

## Arquivos Gerados

1. **template_contrato_representacao_comercial.html** - Template HTML pronto para uso
2. **migracao_template_representacao_comercial.sql** - Script SQL para inserir no sistema

## Variáveis do Sistema Utilizadas

### Dados do Colaborador (Representante)
- `{{colaborador.nome_completo}}` - Nome completo do representante
- `{{colaborador.cpf}}` - CPF formatado
- `{{colaborador.endereco_completo}}` - Endereço completo
- `{{colaborador.cidade}}` - Cidade
- `{{colaborador.regiao}}` - **NOVO CAMPO** - Região de atuação (ex: "Alpinópolis, Arceburgo, Bom Jesus da Penha...")

### Dados da Empresa (Representada)
- `{{empresa.razao_social}}` - Razão social
- `{{empresa.nome_fantasia}}` - Nome fantasia
- `{{empresa.cnpj}}` - CNPJ formatado
- `{{empresa.endereco}}` - Endereço
- `{{empresa.cidade}}` - Cidade
- `{{empresa.estado}}` - Estado (UF)
- `{{empresa.email}}` - Email

### Dados do Representante da Empresa
- `{{representante.nome}}` - Nome do sócio/administrador
- `{{representante.cpf}}` - CPF do representante
- `{{representante.endereco}}` - Endereço do representante
- `{{representante.cidade}}` - Cidade do representante

### Dados Financeiros do Contrato (Campos Manuais Obrigatórios)
- `{{contrato.valor_pedido}}` - Valor por pedido (ex: 30)
- `{{contrato.valor_pedido_extenso}}` - Valor por pedido por extenso (ex: "trinta")
- `{{contrato.valor_cliente_novo}}` - Valor adicional cliente novo (ex: 20)
- `{{contrato.valor_cliente_novo_extenso}}` - Valor cliente novo por extenso (ex: "vinte")
- `{{contrato.ajuda_custo}}` - Ajuda de custo mensal (ex: 250)
- `{{contrato.ajuda_custo_extenso}}` - Ajuda de custo por extenso (ex: "duzentos e cinquenta")
- `{{contrato.percentual_minimo}}` - Percentual mínimo comissão (ex: 3)
- `{{contrato.percentual_minimo_extenso}}` - Percentual mínimo por extenso (ex: "três")
- `{{contrato.percentual_maximo}}` - Percentual máximo comissão (ex: 5)
- `{{contrato.percentual_maximo_extenso}}` - Percentual máximo por extenso (ex: "cinco")
- `{{contrato.bonificacao}}` - Valor bonificação produtividade (ex: 250)
- `{{contrato.bonificacao_extenso}}` - Bonificação por extenso (ex: "duzentos e cinquenta")
- `{{contrato.valor_kit}}` - Valor do kit de produtos (ex: 350)
- `{{contrato.valor_kit_extenso}}` - Valor kit por extenso (ex: "trezentos e cinquenta")

### Dados das Testemunhas
- `{{testemunha1.nome}}` - Nome da primeira testemunha
- `{{testemunha1.cpf}}` - CPF da primeira testemunha
- `{{testemunha2.nome}}` - Nome da segunda testemunha
- `{{testemunha2.cpf}}` - CPF da segunda testemunha

### Datas
- `{{data_formatada}}` - Data atual formatada (ex: "29 de Julho de 2025")

## Como Usar

### 1. Execute a Migração SQL
```sql
-- Execute no banco de dados para inserir o template
source migracao_template_representacao_comercial.sql
```

### 2. Cadastre o Colaborador
- Preencha todos os dados do colaborador
- **IMPORTANTE**: No campo "Região", informe as cidades de atuação separadas por vírgula
  - Exemplo: "Alpinópolis, Arceburgo, Bom Jesus da Penha, Capetinga, Cássia..."

### 3. Configure o Representante da Empresa
Em "Configurações > Autentique":
- Nome do Representante: Nome do sócio/administrador
- CPF do Representante: CPF do sócio/administrador
- Email: Email para assinatura
- CNPJ da Empresa: CNPJ da empresa representada

### 4. Ao Criar o Contrato
O sistema pedirá para preencher manualmente os valores financeiros:
- Valor por pedido
- Valor adicional cliente novo
- Ajuda de custo
- Percentuais de comissão
- Bonificação
- Valor do kit

### 5. Campos Manuais no Contrato
Ao gerar o contrato, você precisará preencher os campos financeiros conforme a negociação com o representante.

## Campos que Precisam ser Configurados

### No Cadastro do Colaborador (Obrigatório)
- ✓ Nome completo
- ✓ CPF
- ✓ Endereço completo
- ✓ Cidade
- ✓ **Região** (NOVO CAMPO - cidades de atuaão)
- ✓ Email

### Em Configurações > Autentique (Obrigatório)
- ✓ Nome do Representante
- ✓ CPF do Representante
- ✓ CNPJ da Empresa

### Dados da Empresa (Obrigatório)
- ✓ Razão social
- ✓ Nome fantasia
- ✓ CNPJ
- ✓ Endereço
- ✓ Cidade
- ✓ Estado
- ✓ Email

## Testemunhas
Ao enviar o contrato para assinatura, você poderá adicionar até 2 testemunhas que também assinarão o documento.

## Personalização

O template utiliza as seguintes cores e formatação:
- Fonte: Times New Roman, 12pt
- Alinhamento: Justificado
- Títulos de cláusulas: Negrito
- Subcláusulas: Recuo de 20px
- Bullets: Recuo de 40px

Para ajustar valores ou cláusulas, edite o arquivo `template_contrato_representacao_comercial.html` diretamente ou através da interface de Templates do sistema.
