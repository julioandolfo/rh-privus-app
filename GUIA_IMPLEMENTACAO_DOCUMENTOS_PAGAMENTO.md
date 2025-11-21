# 📋 Guia de Implementação: Sistema de Documentos de Pagamento

## ✅ Arquivos Criados

### 1. Banco de Dados
- ✅ `migracao_documentos_pagamento.sql` - Execute este arquivo no banco de dados

### 2. Backend
- ✅ `includes/upload_documento.php` - Funções de upload e gerenciamento
- ✅ `api/upload_documento_pagamento.php` - API para upload (colaborador)
- ✅ `api/aprovar_documento_pagamento.php` - API para aprovar/rejeitar (admin)
- ✅ `api/get_documento_pagamento.php` - API para visualizar documento

### 3. Frontend
- ✅ `pages/meus_pagamentos.php` - Página do colaborador

## 📝 Passos para Implementação Completa

### Passo 1: Executar Migração SQL

```sql
-- Execute o arquivo migracao_documentos_pagamento.sql no seu banco de dados
```

Isso criará:
- Campos na tabela `fechamentos_pagamento_itens`
- Campo na tabela `fechamentos_pagamento`
- Tabela `fechamentos_pagamento_documentos_historico`

### Passo 2: Criar Diretório de Uploads

```bash
mkdir -p uploads/documentos_pagamento
chmod 755 uploads/documentos_pagamento
```

### Passo 3: Adicionar Link no Menu para Colaborador

Em `includes/menu.php`, após a linha 425 (dentro do bloco `if ($usuario['role'] === 'COLABORADOR')`), adicione:

```php
<!--begin:Menu item-->
<div class="menu-item">
    <a class="menu-link <?= isActive('meus_pagamentos.php') ?>" href="meus_pagamentos.php">
        <span class="menu-icon">
            <i class="ki-duotone ki-wallet fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
        </span>
        <span class="menu-title">Meus Pagamentos</span>
    </a>
</div>
<!--end:Menu item-->
```

### Passo 4: Modificar `pages/fechamento_pagamentos.php`

Adicione a coluna "Documento" na tabela de itens e botões de ação. Veja exemplo abaixo.

### Passo 5: Testar

1. Criar um fechamento e fechá-lo
2. Como colaborador, acessar "Meus Pagamentos"
3. Enviar documento
4. Como admin, aprovar/rejeitar documento

## 🔧 Modificações Necessárias em `fechamento_pagamentos.php`

### 1. Adicionar Coluna "Documento" na Tabela

Na seção onde mostra os itens do fechamento (linha ~468), adicione:

```php
<th>Documento</th>
```

E no loop dos itens (linha ~485), adicione:

```php
<td>
    <?php
    $status_doc = $item['documento_status'] ?? 'pendente';
    $badges = [
        'pendente' => '<span class="badge badge-light-danger">Pendente</span>',
        'enviado' => '<span class="badge badge-light-warning">Enviado</span>',
        'aprovado' => '<span class="badge badge-light-success">Aprovado</span>',
        'rejeitado' => '<span class="badge badge-light-danger">Rejeitado</span>'
    ];
    echo $badges[$status_doc] ?? '<span class="badge badge-light-secondary">-</span>';
    ?>
    <?php if (!empty($item['documento_anexo'])): ?>
        <br><button type="button" class="btn btn-sm btn-light-primary mt-1" 
                onclick="verDocumentoAdmin(<?= $fechamento_view['id'] ?>, <?= $item['id'] ?>)">
            <i class="ki-duotone ki-eye fs-5">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            Ver
        </button>
    <?php endif; ?>
</td>
```

### 2. Adicionar Botões de Ação

Na coluna "Ações" (se existir), adicione:

```php
<?php if ($fechamento_view['status'] === 'fechado' && $item['documento_status'] === 'enviado'): ?>
    <button type="button" class="btn btn-sm btn-success" 
            onclick="aprovarDocumento(<?= $item['id'] ?>)">
        Aprovar
    </button>
    <button type="button" class="btn btn-sm btn-danger" 
            onclick="rejeitarDocumento(<?= $item['id'] ?>)">
        Rejeitar
    </button>
<?php endif; ?>
```

### 3. Adicionar JavaScript

Adicione estas funções no final do arquivo, antes do `</script>`:

```javascript
// Aprovar documento
function aprovarDocumento(itemId) {
    Swal.fire({
        title: 'Aprovar Documento?',
        text: 'Tem certeza que deseja aprovar este documento?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, aprovar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('acao', 'aprovar');
            formData.append('observacoes', '');
            
            fetch('../api/aprovar_documento_pagamento.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        }
    });
}

// Rejeitar documento
function rejeitarDocumento(itemId) {
    Swal.fire({
        title: 'Rejeitar Documento',
        input: 'textarea',
        inputLabel: 'Motivo da rejeição',
        inputPlaceholder: 'Digite o motivo da rejeição...',
        inputAttributes: {
            'aria-label': 'Digite o motivo da rejeição'
        },
        showCancelButton: true,
        confirmButtonText: 'Rejeitar',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            if (!value) {
                return 'O motivo da rejeição é obrigatório!';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('acao', 'rejeitar');
            formData.append('observacoes', result.value);
            
            fetch('../api/aprovar_documento_pagamento.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        }
    });
}

// Ver documento (admin)
function verDocumentoAdmin(fechamentoId, itemId) {
    fetch(`../api/get_documento_pagamento.php?fechamento_id=${fechamentoId}&item_id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const doc = data.data;
                const isImage = doc.is_image;
                
                let html = '';
                if (isImage) {
                    html = `<img src="../${doc.documento_anexo}" class="img-fluid" alt="Documento">`;
                } else {
                    html = `
                        <div class="text-center py-10">
                            <i class="ki-duotone ki-file fs-3x text-primary mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="text-gray-600">Clique em "Download" para baixar</div>
                        </div>
                    `;
                }
                
                Swal.fire({
                    title: 'Documento',
                    html: html,
                    width: isImage ? '80%' : '600px',
                    showCancelButton: true,
                    confirmButtonText: 'Download',
                    cancelButtonText: 'Fechar',
                    customClass: {
                        popup: 'text-start'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.open('../' + doc.documento_anexo, '_blank');
                    }
                });
            } else {
                Swal.fire('Erro', data.message || 'Erro ao carregar documento', 'error');
            }
        });
}
```

### 4. Atualizar Query para Buscar Status do Documento

Na query que busca os itens do fechamento (linha ~329), adicione os campos:

```php
SELECT i.*, c.nome_completo as colaborador_nome, c.id as colaborador_id,
       i.documento_anexo, i.documento_status, i.documento_data_envio,
       i.documento_data_aprovacao, i.documento_observacoes
```

## 📊 Estatísticas Sugeridas para Admin

Adicione cards de estatísticas no topo da página de visualização do fechamento:

```php
<!-- Estatísticas de Documentos -->
<div class="row g-3 mb-5">
    <div class="col-md-3">
        <div class="card bg-light-danger">
            <div class="card-body">
                <span class="text-muted fw-semibold d-block">Pendentes</span>
                <span class="text-gray-800 fw-bold fs-2"><?= $stats_pendentes ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-light-warning">
            <div class="card-body">
                <span class="text-muted fw-semibold d-block">Enviados</span>
                <span class="text-gray-800 fw-bold fs-2"><?= $stats_enviados ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-light-success">
            <div class="card-body">
                <span class="text-muted fw-semibold d-block">Aprovados</span>
                <span class="text-gray-800 fw-bold fs-2"><?= $stats_aprovados ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-light-info">
            <div class="card-body">
                <span class="text-muted fw-semibold d-block">Total Itens</span>
                <span class="text-gray-800 fw-bold fs-2"><?= count($itens_fechamento) ?></span>
            </div>
        </div>
    </div>
</div>
```

E calcule as estatísticas antes de incluir o header:

```php
// Calcula estatísticas de documentos
$stats_pendentes = 0;
$stats_enviados = 0;
$stats_aprovados = 0;

foreach ($itens_fechamento as $item) {
    $status = $item['documento_status'] ?? 'pendente';
    if ($status === 'pendente') $stats_pendentes++;
    elseif ($status === 'enviado') $stats_enviados++;
    elseif ($status === 'aprovado') $stats_aprovados++;
}
```

## 🔔 Notificações

As notificações já estão implementadas:
- ✅ Colaborador recebe quando documento é aprovado/rejeitado
- ✅ Admin recebe quando colaborador envia documento

## 🎨 Melhorias Futuras (Opcional)

1. **Filtros na Lista de Fechamentos:**
   - Filtrar por status de documento
   - Ver apenas pendentes

2. **Relatórios:**
   - Relatório de documentos pendentes por empresa
   - Tempo médio de aprovação

3. **Validações:**
   - Validar formato específico (ex: apenas PDF)
   - Validar tamanho mínimo

4. **Histórico:**
   - Mostrar histórico completo de alterações
   - Timeline de eventos

## ✅ Checklist de Implementação

- [ ] Executar migração SQL
- [ ] Criar diretório `uploads/documentos_pagamento`
- [ ] Adicionar link "Meus Pagamentos" no menu para colaborador
- [ ] Modificar `fechamento_pagamentos.php` (adicionar coluna e ações)
- [ ] Testar upload de documento (colaborador)
- [ ] Testar visualização de documento (admin)
- [ ] Testar aprovação de documento (admin)
- [ ] Testar rejeição de documento (admin)
- [ ] Verificar notificações funcionando
- [ ] Adicionar estatísticas (opcional)

---

**Status:** ✅ Estrutura completa criada e pronta para implementação

