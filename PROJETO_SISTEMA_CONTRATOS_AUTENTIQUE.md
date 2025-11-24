# 📋 Projeto: Sistema de Contratos com Integração Autentique

## 🎯 Objetivo

Criar um sistema completo de gestão de contratos dentro do módulo de Colaboradores, integrado com a API Autentique para assinatura eletrônica de documentos.

---

## 📚 Análise da API Autentique

### **Como Funciona**

A Autentique utiliza **GraphQL** (não REST) e oferece:

1. **Endpoint**: `POST https://api.autentique.com.br/v2/graphql`
2. **Autenticação**: `Authorization: Bearer YOUR_API_KEY`
3. **Rate Limit**: 60 requisições por minuto
4. **Sandbox**: Ambiente de testes disponível

### **Principais Operações**

#### **Criar Documento**
```graphql
mutation {
  createDocument(
    document: {
      name: "Nome do Documento"
      file: "base64_encoded_pdf"
      signers: [
        {
          action: "SIGN"
          email: "email@exemplo.com"
          position: { x: 100, y: 100 }
        }
      ]
    }
  ) {
    id
    token
    link
  }
}
```

#### **Consultar Status**
```graphql
query {
  document(id: "document_id") {
    id
    status
    signers {
      email
      signed
      signedAt
    }
  }
}
```

#### **Criar Link Público**
```graphql
mutation {
  createSignatureLink(
    documentId: "document_id"
    signerId: "signer_id"
  ) {
    link
    expiresAt
  }
}
```

### **Webhooks**
- Notificações automáticas sobre eventos (assinatura concluída, visualizado, etc.)
- Configurável no dashboard da Autentique

---

## 🏗️ Arquitetura Proposta

### **1. Estrutura de Banco de Dados**

```sql
-- Tabela de Templates de Contrato
contratos_templates (
    id, nome, descricao, conteudo_html, 
    variaveis_disponiveis, ativo, criado_por, created_at, updated_at
)

-- Tabela de Contratos
contratos (
    id, colaborador_id, template_id, titulo, 
    conteudo_final_html, pdf_path, status, 
    autentique_document_id, autentique_token, 
    criado_por_usuario_id, data_criacao, data_vencimento, 
    created_at, updated_at
)

-- Tabela de Signatários (Colaborador + Testemunhas)
contratos_signatarios (
    id, contrato_id, tipo ENUM('colaborador', 'testemunha', 'rh'),
    nome, email, cpf, autentique_signer_id,
    assinado BOOLEAN, data_assinatura, 
    link_publico, link_expiracao, created_at, updated_at
)

-- Tabela de Histórico de Eventos (Webhooks Autentique)
contratos_eventos (
    id, contrato_id, tipo_evento, dados_json, created_at
)
```

### **2. Sistema de Variáveis Dinâmicas**

#### **Variáveis Disponíveis no Template**

```php
// Dados do Colaborador
{{colaborador.nome_completo}}
{{colaborador.cpf}}
{{colaborador.rg}}
{{colaborador.email_pessoal}}
{{colaborador.telefone}}
{{colaborador.data_nascimento}}
{{colaborador.endereco_completo}}
{{colaborador.cidade}}
{{colaborador.estado}}
{{colaborador.cep}}

// Dados da Empresa/Setor/Cargo
{{colaborador.empresa_nome}}
{{colaborador.setor_nome}}
{{colaborador.cargo_nome}}
{{colaborador.salario}}
{{colaborador.data_admissao}}

// Dados do Contrato
{{contrato.titulo}}
{{contrato.data_criacao}}
{{contrato.data_vencimento}}
{{contrato.observacoes}}

// Dados da Data/Hora
{{data_atual}}
{{hora_atual}}
{{data_formatada}}
```

#### **Sistema de Substituição**

1. **Editor TinyMCE** com botões para inserir variáveis
2. **Preview em tempo real** mostrando dados do colaborador selecionado
3. **Validação** de variáveis antes de salvar template

---

## 💡 Sugestões e Melhorias

### **1. Editor de Contratos**

#### **Opção A: TinyMCE com Variáveis (RECOMENDADO)**
✅ **Vantagens:**
- Flexibilidade total na criação de contratos
- Visual WYSIWYG familiar
- Suporte a HTML/CSS para formatação
- Fácil inserção de variáveis via botões

❌ **Desvantagens:**
- Requer conversão HTML → PDF
- Pode ter problemas de formatação na conversão

#### **Opção B: Templates em PDF com Campos**
✅ **Vantagens:**
- Formatação perfeita garantida
- Profissional

❌ **Desvantagens:**
- Menos flexível
- Requer ferramentas externas para edição

#### **Opção C: Híbrido (MELHOR SOLUÇÃO)**
- **TinyMCE** para criar/editar templates HTML
- **Conversão HTML → PDF** usando biblioteca (TCPDF já existe no sistema)
- **Preview** antes de enviar
- **Salvar PDF** gerado para envio ao Autentique

### **2. Fluxo de Assinatura**

```
1. RH cria contrato → Seleciona colaborador + template
2. Sistema substitui variáveis → Gera preview
3. RH confirma → Gera PDF → Envia para Autentique
4. Autentique retorna: document_id, token, links
5. Sistema salva links e envia notificações:
   - Email para colaborador (link de assinatura)
   - Link público para testemunhas (se houver)
6. Webhook recebe atualizações → Atualiza status no sistema
7. Quando todos assinam → Notifica RH + Colaborador
```

### **3. Gestão de Testemunhas**

- **Opção 1**: RH adiciona testemunhas manualmente (nome, email, CPF)
- **Opção 2**: Selecionar de lista de colaboradores
- **Link público** gerado para cada testemunha
- **Expiração** configurável (padrão: 30 dias)

### **4. Dashboard de Contratos**

#### **Para RH/ADMIN:**
- **Kanban** com status:
  - 📝 Rascunho
  - 📤 Enviado para Assinatura
  - ⏳ Aguardando Assinaturas
  - ✅ Assinado
  - ❌ Cancelado
  - ⚠️ Expirado

- **Filtros:**
  - Por colaborador
  - Por status
  - Por data de criação
  - Por tipo de contrato

- **Estatísticas:**
  - Total de contratos
  - Aguardando assinatura
  - Assinados este mês
  - Taxa de conclusão

- **Ações Rápidas:**
  - Reenviar link de assinatura
  - Cancelar contrato
  - Baixar PDF assinado
  - Ver histórico

### **5. Notificações**

- **Email** quando contrato é criado
- **Push notification** quando precisa assinar
- **Email** quando todas assinaturas são concluídas
- **Lembrete** automático se não assinar em X dias

### **6. Segurança e Compliance**

- ✅ **Logs** de todas as ações
- ✅ **Auditoria** completa (quem criou, quando, quem assinou)
- ✅ **Armazenamento seguro** de PDFs
- ✅ **Validação** de CPF/Email antes de enviar
- ✅ **Permissões** granulares (quem pode criar/ver contratos)

---

## 📁 Estrutura de Arquivos Proposta

```
pages/
├── contratos.php                    # Lista de contratos (Kanban)
├── contrato_add.php                 # Criar novo contrato
├── contrato_view.php                 # Visualizar contrato + status
├── contrato_template_add.php         # Criar template
├── contrato_template_edit.php        # Editar template
├── contrato_templates.php            # Lista de templates

api/
├── contratos/
│   ├── criar.php                    # Criar contrato + enviar Autentique
│   ├── listar.php                   # Listar contratos
│   ├── detalhes.php                 # Detalhes do contrato
│   ├── cancelar.php                 # Cancelar contrato
│   ├── reenviar_link.php            # Reenviar link de assinatura
│   ├── webhook.php                  # Receber webhooks da Autentique
│   └── gerar_pdf.php                # Gerar PDF do contrato
│
└── contratos_templates/
    ├── criar.php
    ├── editar.php
    ├── excluir.php
    ├── preview.php                  # Preview com dados do colaborador
    └── variaveis.php                # Lista de variáveis disponíveis

includes/
├── autentique_service.php          # Classe para comunicação com Autentique
├── contratos_functions.php          # Funções auxiliares
└── pdf_contrato.php                 # Geração de PDF

uploads/
└── contratos/                       # PDFs gerados
    ├── rascunhos/
    └── assinados/
```

---

## 🔧 Integração com Autentique

### **Classe AutentiqueService**

```php
class AutentiqueService {
    private $apiKey;
    private $endpoint = 'https://api.autentique.com.br/v2/graphql';
    private $sandbox = false; // true para testes
    
    // Criar documento
    public function criarDocumento($nome, $pdfBase64, $signatarios) {}
    
    // Consultar status
    public function consultarStatus($documentId) {}
    
    // Criar link público
    public function criarLinkPublico($documentId, $signerId) {}
    
    // Cancelar documento
    public function cancelarDocumento($documentId) {}
    
    // Reenviar assinatura
    public function reenviarAssinatura($documentId, $signerId) {}
}
```

### **Webhook Handler**

```php
// api/contratos/webhook.php
// Recebe eventos da Autentique e atualiza status
// Eventos: document.signed, document.viewed, signer.signed, etc.
```

---

## 🎨 Interface Proposta

### **1. Criar Contrato**

```
┌─────────────────────────────────────────┐
│ Adicionar Novo Contrato                 │
├─────────────────────────────────────────┤
│                                         │
│ Colaborador: [Select com busca]        │
│ Template: [Select de templates]        │
│ Título: [Input]                         │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ Preview do Contrato                 │ │
│ │ (com variáveis substituídas)        │ │
│ │                                     │ │
│ │ CONTRATO DE TRABALHO                │ │
│ │                                     │ │
│ │ Eu, João Silva, CPF 123.456.789-00 │ │
│ │ ...                                 │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ Testemunhas:                            │
│ [+ Adicionar Testemunha]                │
│                                         │
│ [Cancelar] [Salvar Rascunho] [Enviar]  │
└─────────────────────────────────────────┘
```

### **2. Dashboard de Contratos**

```
┌─────────────────────────────────────────┐
│ Contratos                               │
├─────────────────────────────────────────┤
│ Filtros: [Colaborador] [Status] [Data] │
│                                         │
│ 📊 Estatísticas                         │
│ Total: 45 | Aguardando: 12 | Assinados: 33 │
│                                         │
│ 📋 Kanban                               │
│ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐   │
│ │Rasc. │ │Enviado│ │Aguard│ │Assin.│   │
│ │  3   │ │  5    │ │  12  │ │  33  │   │
│ └──────┘ └──────┘ └──────┘ └──────┘   │
│                                         │
│ Lista de Contratos                      │
│ [Tabela com ações rápidas]              │
└─────────────────────────────────────────┘
```

---

## ✅ Checklist de Implementação

### **Fase 1: Estrutura Base**
- [ ] Criar migração SQL (tabelas)
- [ ] Criar classe AutentiqueService
- [ ] Criar funções auxiliares
- [ ] Configurar página de configurações (API Key)

### **Fase 2: Templates**
- [ ] CRUD de templates
- [ ] Editor TinyMCE com variáveis
- [ ] Sistema de substituição de variáveis
- [ ] Preview de template

### **Fase 3: Contratos**
- [ ] Criar contrato
- [ ] Geração de PDF
- [ ] Integração com Autentique
- [ ] Envio de notificações

### **Fase 4: Gestão**
- [ ] Dashboard/Kanban
- [ ] Visualização de contratos
- [ ] Reenvio de links
- [ ] Cancelamento

### **Fase 5: Webhooks**
- [ ] Handler de webhooks
- [ ] Atualização automática de status
- [ ] Notificações de eventos

### **Fase 6: Testemunhas**
- [ ] Adicionar testemunhas
- [ ] Links públicos
- [ ] Gestão de expiração

---

## 🚀 Próximos Passos

1. **Aprovar arquitetura** proposta
2. **Definir prioridades** (o que implementar primeiro)
3. **Configurar API Key** da Autentique
4. **Criar ambiente de testes** (sandbox)
5. **Implementar fase por fase**

---

## 📝 Observações Importantes

1. **API Key**: Deve ser configurada em `config/autentique.php` (não commitada)
2. **Sandbox**: Usar durante desenvolvimento para não consumir documentos reais
3. **PDF**: Usar TCPDF (já existe no sistema) para gerar PDFs
4. **Webhooks**: Configurar URL pública no dashboard Autentique
5. **Rate Limit**: Implementar cache/queue se necessário

---

## 💬 Decisões Pendentes

1. **Editor**: TinyMCE ou outra solução?
2. **PDF**: Gerar no servidor ou usar serviço externo?
3. **Armazenamento**: Onde salvar PDFs? (local, S3, etc.)
4. **Notificações**: Email apenas ou incluir Push?
5. **Permissões**: Apenas RH/ADMIN ou GESTOR também pode criar?

---

**Aguardando aprovação para iniciar implementação!** 🎯

