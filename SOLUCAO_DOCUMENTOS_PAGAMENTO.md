# 📄 Solução Completa: Sistema de Anexo de Documentos para Pagamento

## 🎯 Objetivo

Implementar sistema completo para anexo de nota fiscal/documento para recebimento de pagamento, com:
- **Para Admin/RH:** Visualização, aprovação/rejeição e controle de pendências
- **Para Colaborador:** Envio de documentos e acompanhamento de status

## 📋 Estrutura da Solução

### 1. **Banco de Dados**

#### Campos Adicionados em `fechamentos_pagamento_itens`:
- `documento_anexo` - Caminho do arquivo
- `documento_status` - Status: pendente, enviado, aprovado, rejeitado
- `documento_data_envio` - Data do envio
- `documento_data_aprovacao` - Data da aprovação/rejeição
- `documento_aprovado_por` - Quem aprovou/rejeitou
- `documento_observacoes` - Observações do admin

#### Campo Adicionado em `fechamentos_pagamento`:
- `documento_obrigatorio` - Se documento é obrigatório (padrão: sim)

#### Nova Tabela `fechamentos_pagamento_documentos_historico`:
- Histórico completo de todas as ações (enviado, aprovado, rejeitado)

### 2. **Funcionalidades para Admin/RH**

#### Na página `fechamento_pagamentos.php`:
- ✅ Coluna "Documento" na tabela de itens mostrando status
- ✅ Badges coloridos para status:
  - 🔴 Pendente (vermelho)
  - 🟡 Enviado (amarelo)
  - 🟢 Aprovado (verde)
  - 🔴 Rejeitado (vermelho)
- ✅ Botão para visualizar/download do documento
- ✅ Botões para aprovar/rejeitar com observações
- ✅ Filtros para ver apenas pendentes, aprovados, etc.
- ✅ Indicador de quantos estão pendentes no card do fechamento

#### Funcionalidades:
- Visualizar documento (preview de imagem ou download de PDF)
- Aprovar documento (com observações opcionais)
- Rejeitar documento (com observações obrigatórias)
- Ver histórico de alterações

### 3. **Funcionalidades para Colaborador**

#### Nova página `meus_pagamentos.php`:
- ✅ Lista de todos os fechamentos fechados do colaborador
- ✅ Status de cada fechamento:
  - Pendente de envio
  - Enviado (aguardando aprovação)
  - Aprovado
  - Rejeitado (com motivo)
- ✅ Botão de upload para itens pendentes
- ✅ Visualização de documento enviado
- ✅ Histórico de envios

#### Funcionalidades:
- Ver lista de pagamentos fechados
- Enviar documento (upload)
- Visualizar documento enviado
- Ver status e observações do admin
- Reenviar se rejeitado

### 4. **Sistema de Upload**

- ✅ Aceita: PDF, DOC, DOCX, XLS, XLSX, imagens (JPG, PNG, GIF, WEBP)
- ✅ Tamanho máximo: 10MB
- ✅ Validação de tipo e tamanho
- ✅ Organização por fechamento (pasta por fechamento)
- ✅ Nome único para evitar conflitos

### 5. **Notificações**

- ✅ Colaborador recebe notificação quando documento é aprovado/rejeitado
- ✅ Admin/RH recebe notificação quando colaborador envia documento

## 🔄 Fluxo de Trabalho

### Fluxo Normal:
1. **Admin cria fechamento** → Status: aberto
2. **Admin fecha fechamento** → Status: fechado, documentos ficam pendentes
3. **Colaborador recebe notificação** → "Fechamento disponível, envie seu documento"
4. **Colaborador envia documento** → Status: enviado
5. **Admin recebe notificação** → "Novo documento para aprovar"
6. **Admin aprova/rejeita** → Status: aprovado/rejeitado
7. **Colaborador recebe notificação** → "Seu documento foi aprovado/rejeitado"

### Se Rejeitado:
1. **Colaborador recebe notificação** com motivo da rejeição
2. **Colaborador pode reenviar** novo documento
3. **Status volta para "enviado"** aguardando nova aprovação

## 📁 Arquivos Criados/Modificados

### Novos Arquivos:
1. ✅ `migracao_documentos_pagamento.sql` - Migração do banco
2. ✅ `includes/upload_documento.php` - Funções de upload
3. ✅ `api/upload_documento_pagamento.php` - API de upload
4. ✅ `api/aprovar_documento_pagamento.php` - API de aprovação/rejeição
5. ✅ `api/get_documento_pagamento.php` - API para visualizar documento
6. ✅ `pages/meus_pagamentos.php` - Página do colaborador

### Arquivos que Precisam ser Modificados:
1. ⚠️ `pages/fechamento_pagamentos.php` - Adicionar coluna e ações de documento
2. ⚠️ `includes/menu.php` - Adicionar link "Meus Pagamentos" para colaborador

## 🎨 Interface Sugerida

### Para Admin (fechamento_pagamentos.php):
```
┌─────────────────────────────────────────────────────────┐
│ Colaborador │ Salário │ H.E. │ Total │ Documento │ Ações│
├─────────────────────────────────────────────────────────┤
│ João Silva  │ R$ 5000 │ 10h  │ R$ 5500 │ 🟡 Enviado │ Ver │
│ Maria Santos│ R$ 3000 │ 5h   │ R$ 3250 │ 🔴 Pendente │ -   │
│ Pedro Costa │ R$ 4000 │ 0h   │ R$ 4000 │ 🟢 Aprovado │ Ver │
└─────────────────────────────────────────────────────────┘
```

### Para Colaborador (meus_pagamentos.php):
```
┌─────────────────────────────────────────────────────────┐
│ Mês/Ano │ Total │ Status Documento │ Ações            │
├─────────────────────────────────────────────────────────┤
│ 12/2024 │ R$ 5500 │ 🟡 Enviado      │ Ver │ Reenviar │
│ 11/2024 │ R$ 3250 │ 🔴 Pendente     │ Enviar          │
│ 10/2024 │ R$ 4000 │ 🟢 Aprovado     │ Ver             │
└─────────────────────────────────────────────────────────┘
```

## 🔐 Segurança

- ✅ Validação de permissões (colaborador só vê seus próprios documentos)
- ✅ Validação de tipo de arquivo (whitelist)
- ✅ Validação de tamanho (máximo 10MB)
- ✅ Sanitização de nomes de arquivo
- ✅ Proteção contra path traversal
- ✅ Verificação de propriedade do item antes de upload

## 📊 Relatórios Sugeridos

- Total de documentos pendentes por empresa
- Total de documentos aprovados/rejeitados no mês
- Tempo médio de aprovação
- Colaboradores com mais documentos rejeitados

## 🚀 Próximos Passos

1. Executar migração SQL
2. Criar página `meus_pagamentos.php` para colaborador ✅ (já criada)
3. Modificar `fechamento_pagamentos.php` para admin (ver guia)
4. Adicionar link no menu para colaborador
5. Criar API de aprovação/rejeição ✅ (já criada)
6. Adicionar notificações ✅ (já implementadas)
7. Testar fluxo completo

---

**Status:** ✅ Estrutura planejada e pronta para implementação

