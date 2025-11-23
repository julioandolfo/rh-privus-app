<?php
/**
 * Gerenciamento de Categorias de Ocorrências
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('tipos_ocorrencias.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $nome = sanitize($_POST['nome'] ?? '');
        $codigo = sanitize($_POST['codigo'] ?? '');
        $cor = sanitize($_POST['cor'] ?? '#6c757d');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $ordem = (int)($_POST['ordem'] ?? 0);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        if (empty($nome) || empty($codigo)) {
            redirect('categorias_ocorrencias.php', 'Preencha nome e código!', 'error');
        }
        
        // Gera código automaticamente se não fornecido
        if (empty($codigo)) {
            $codigo = generateSlug($nome);
        }
        
        try {
            if ($action === 'add') {
                // Verifica código único
                $stmt = $pdo->prepare("SELECT id FROM ocorrencias_categorias WHERE codigo = ?");
                $stmt->execute([$codigo]);
                if ($stmt->fetch()) {
                    redirect('categorias_ocorrencias.php', 'Código já existe!', 'error');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO ocorrencias_categorias (nome, codigo, cor, descricao, ordem, ativo)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $codigo, $cor, $descricao, $ordem, $ativo]);
                
                redirect('categorias_ocorrencias.php', 'Categoria cadastrada com sucesso!');
            } else {
                $id = $_POST['id'] ?? 0;
                
                // Verifica código único (exceto o próprio registro)
                $stmt = $pdo->prepare("SELECT id FROM ocorrencias_categorias WHERE codigo = ? AND id != ?");
                $stmt->execute([$codigo, $id]);
                if ($stmt->fetch()) {
                    redirect('categorias_ocorrencias.php', 'Código já existe!', 'error');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE ocorrencias_categorias SET
                    nome = ?, codigo = ?, cor = ?, descricao = ?, ordem = ?, ativo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $codigo, $cor, $descricao, $ordem, $ativo, $id]);
                
                redirect('categorias_ocorrencias.php', 'Categoria atualizada com sucesso!');
            }
        } catch (PDOException $e) {
            error_log("Erro ao salvar categoria: " . $e->getMessage());
            redirect('categorias_ocorrencias.php', 'Erro ao salvar categoria. Tente novamente.', 'error');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        
        if ($id) {
            try {
                // Verifica se há tipos de ocorrência usando esta categoria
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tipos_ocorrencias WHERE categoria_id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch();
                
                if ($result['total'] > 0) {
                    redirect('categorias_ocorrencias.php', 'Não é possível excluir categoria que está em uso!', 'error');
                }
                
                $stmt = $pdo->prepare("DELETE FROM ocorrencias_categorias WHERE id = ?");
                $stmt->execute([$id]);
                
                redirect('categorias_ocorrencias.php', 'Categoria excluída com sucesso!');
            } catch (PDOException $e) {
                error_log("Erro ao excluir categoria: " . $e->getMessage());
                redirect('categorias_ocorrencias.php', 'Erro ao excluir categoria. Tente novamente.', 'error');
            }
        }
    }
}

// Busca categorias
$stmt = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM tipos_ocorrencias WHERE categoria_id = c.id) as total_tipos
    FROM ocorrencias_categorias c
    ORDER BY c.ordem, c.nome
");
$categorias = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!--begin::Main-->
<div class="app-main flex-column flex-row-fluid" id="kt_app_main">
    <!--begin::Content wrapper-->
    <div class="d-flex flex-column flex-column-fluid">
        <!--begin::Toolbar-->
        <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-0">
            <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
                <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                    <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                        Categorias de Ocorrências
                    </h1>
                    <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                        <li class="breadcrumb-item text-muted">
                            <a href="dashboard.php" class="text-muted text-hover-primary">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <span class="bullet bg-gray-400 w-5px h-2px"></span>
                        </li>
                        <li class="breadcrumb-item text-muted">
                            <a href="tipos_ocorrencias.php" class="text-muted text-hover-primary">Tipos de Ocorrências</a>
                        </li>
                        <li class="breadcrumb-item">
                            <span class="bullet bg-gray-400 w-5px h-2px"></span>
                        </li>
                        <li class="breadcrumb-item text-dark">Categorias</li>
                    </ul>
                </div>
                <div class="d-flex align-items-center gap-2 gap-lg-3">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_categoria">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        Nova Categoria
                    </button>
                </div>
            </div>
        </div>
        <!--end::Toolbar-->
        
        <!--begin::Content-->
        <div id="kt_app_content" class="app-content flex-column-fluid">
            <div id="kt_app_content_container" class="app-container container-xxl">
                
                <!--begin::Card-->
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <div class="d-flex align-items-center position-relative my-1">
                                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <input type="text" id="kt_filter_search" class="form-control form-control-solid w-250px ps-13" placeholder="Buscar categoria..." />
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_table_categorias">
                                <thead>
                                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-50px">Ordem</th>
                                        <th class="min-w-150px">Nome</th>
                                        <th class="min-w-100px">Código</th>
                                        <th class="min-w-100px">Cor</th>
                                        <th class="min-w-150px">Descrição</th>
                                        <th class="min-w-100px">Tipos</th>
                                        <th class="min-w-100px">Status</th>
                                        <th class="text-end min-w-100px">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600 fw-semibold">
                                    <?php if (empty($categorias)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-10">
                                            <div class="text-gray-500">Nenhuma categoria cadastrada</div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($categorias as $categoria): ?>
                                    <tr>
                                        <td><?= $categoria['ordem'] ?></td>
                                        <td>
                                            <span class="badge" style="background-color: <?= htmlspecialchars($categoria['cor']) ?>20; color: <?= htmlspecialchars($categoria['cor']) ?>;">
                                                <?= htmlspecialchars($categoria['nome']) ?>
                                            </span>
                                        </td>
                                        <td><code><?= htmlspecialchars($categoria['codigo']) ?></code></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-circle symbol-30px me-2" style="background-color: <?= htmlspecialchars($categoria['cor']) ?>;"></div>
                                                <span><?= htmlspecialchars($categoria['cor']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($categoria['descricao'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge badge-light-primary"><?= $categoria['total_tipos'] ?> tipo(s)</span>
                                        </td>
                                        <td>
                                            <?php if ($categoria['ativo']): ?>
                                                <span class="badge badge-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="#" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" onclick="editarCategoria(<?= htmlspecialchars(json_encode($categoria)) ?>); return false;">
                                                <i class="ki-duotone ki-pencil fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </a>
                                            <?php if ($categoria['total_tipos'] == 0): ?>
                                            <a href="#" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm" onclick="excluirCategoria(<?= $categoria['id'] ?>, '<?= htmlspecialchars($categoria['nome']) ?>'); return false;">
                                                <i class="ki-duotone ki-trash fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!--end::Card-->
                
            </div>
        </div>
        <!--end::Content-->
    </div>
    <!--end::Content wrapper-->
</div>
<!--end::Main-->

<!--begin::Modal - Categoria-->
<div class="modal fade" id="kt_modal_categoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_categoria_header">
                <h2 class="fw-bold">Nova Categoria</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_categoria_form" method="POST">
                <input type="hidden" name="action" id="categoria_action" value="add" />
                <input type="hidden" name="id" id="categoria_id" value="" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="row mb-7">
                        <div class="col-md-8">
                            <label class="required fw-semibold fs-6 mb-2">Nome</label>
                            <input type="text" name="nome" id="categoria_nome" class="form-control form-control-solid" placeholder="Ex: Pontualidade" required />
                        </div>
                        <div class="col-md-4">
                            <label class="required fw-semibold fs-6 mb-2">Ordem</label>
                            <input type="number" name="ordem" id="categoria_ordem" class="form-control form-control-solid" value="0" min="0" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Código</label>
                            <input type="text" name="codigo" id="categoria_codigo" class="form-control form-control-solid" placeholder="Ex: pontualidade" required />
                            <small class="text-muted">Será gerado automaticamente se deixado vazio</small>
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Cor</label>
                            <input type="color" name="cor" id="categoria_cor" class="form-control form-control-solid form-control-color" value="#6c757d" required />
                        </div>
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="descricao" id="categoria_descricao" class="form-control form-control-solid" rows="3" placeholder="Descrição opcional da categoria"></textarea>
                    </div>
                    
                    <div class="mb-7">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="ativo" id="categoria_ativo" value="1" checked />
                            <label class="form-check-label" for="categoria_ativo">
                                Categoria ativa
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Categoria-->

<script>
"use strict";

// Inicializa DataTable
var KTCategoriasList = function() {
    var initTable = function() {
        var table = document.getElementById('kt_table_categorias');
        if (!table) return;
        
        var datatable = $(table).DataTable({
            "info": true,
            "order": [[0, 'asc']],
            "pageLength": 25,
            "columnDefs": [
                { "orderable": false, "targets": 7 }
            ]
        });
        
        var filterSearch = document.getElementById('kt_filter_search');
        if (filterSearch) {
            filterSearch.addEventListener('keyup', function(e) {
                datatable.search(e.target.value).draw();
            });
        }
    };
    
    return {
        init: function() {
            initTable();
        }
    };
}();

// Inicializa quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    KTCategoriasList.init();
});

// Gera código automaticamente do nome
document.getElementById('categoria_nome')?.addEventListener('input', function() {
    var codigoInput = document.getElementById('categoria_codigo');
    if (codigoInput && !codigoInput.value) {
        var nome = this.value.toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
        codigoInput.value = nome;
    }
});

function editarCategoria(categoria) {
    document.getElementById('kt_modal_categoria_header').querySelector('h2').textContent = 'Editar Categoria';
    document.getElementById('categoria_action').value = 'edit';
    document.getElementById('categoria_id').value = categoria.id;
    document.getElementById('categoria_nome').value = categoria.nome || '';
    document.getElementById('categoria_codigo').value = categoria.codigo || '';
    document.getElementById('categoria_cor').value = categoria.cor || '#6c757d';
    document.getElementById('categoria_descricao').value = categoria.descricao || '';
    document.getElementById('categoria_ordem').value = categoria.ordem || 0;
    document.getElementById('categoria_ativo').checked = categoria.ativo != 0;
    
    var modal = new bootstrap.Modal(document.getElementById('kt_modal_categoria'));
    modal.show();
}

function excluirCategoria(id, nome) {
    Swal.fire({
        text: "Tem certeza que deseja excluir a categoria '" + nome + "'?",
        icon: "warning",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "Sim, excluir!",
        cancelButtonText: "Cancelar",
        customClass: {
            confirmButton: "btn btn-primary",
            cancelButton: "btn btn-light"
        }
    }).then(function(result) {
        if (result.value) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete" />' +
                           '<input type="hidden" name="id" value="' + id + '" />';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Reset modal ao fechar
document.getElementById('kt_modal_categoria').addEventListener('hidden.bs.modal', function () {
    document.getElementById('kt_modal_categoria_form').reset();
    document.getElementById('kt_modal_categoria_header').querySelector('h2').textContent = 'Nova Categoria';
    document.getElementById('categoria_action').value = 'add';
    document.getElementById('categoria_id').value = '';
    document.getElementById('categoria_cor').value = '#6c757d';
    document.getElementById('categoria_ativo').checked = true;
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

