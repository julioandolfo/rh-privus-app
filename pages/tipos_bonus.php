<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('tipos_bonus.php');

$pdo = getDB();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $nome = sanitize($_POST['nome'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $status = $_POST['status'] ?? 'ativo';
        
        if (empty($nome)) {
            redirect('tipos_bonus.php', 'Nome do tipo de bônus é obrigatório!', 'error');
        }
        
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO tipos_bonus (nome, descricao, status) VALUES (?, ?, ?)");
                $stmt->execute([$nome, $descricao, $status]);
                redirect('tipos_bonus.php', 'Tipo de bônus cadastrado com sucesso!');
            } else {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE tipos_bonus SET nome = ?, descricao = ?, status = ? WHERE id = ?");
                $stmt->execute([$nome, $descricao, $status, $id]);
                redirect('tipos_bonus.php', 'Tipo de bônus atualizado com sucesso!');
            }
        } catch (PDOException $e) {
            redirect('tipos_bonus.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            // Verifica se há colaboradores usando este tipo de bônus
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM colaboradores_bonus WHERE tipo_bonus_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                redirect('tipos_bonus.php', 'Não é possível excluir: existem colaboradores com este tipo de bônus!', 'error');
            }
            
            $stmt = $pdo->prepare("DELETE FROM tipos_bonus WHERE id = ?");
            $stmt->execute([$id]);
            redirect('tipos_bonus.php', 'Tipo de bônus excluído com sucesso!');
        } catch (PDOException $e) {
            redirect('tipos_bonus.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    }
}

// Buscar tipos de bônus
$stmt = $pdo->query("SELECT * FROM tipos_bonus ORDER BY nome");
$tipos_bonus = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="d-flex text-dark fw-bolder my-1 fs-3">Tipos de Bônus</h1>
        <ul class="breadcrumb breadcrumb-dot fw-semibold text-gray-600 fs-7 my-1">
            <li class="breadcrumb-item text-gray-600">
                <a href="dashboard.php" class="text-gray-600 text-hover-primary">Dashboard</a>
            </li>
            <li class="breadcrumb-item text-gray-500">Tipos de Bônus</li>
        </ul>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <input type="text" data-kt-filter="search" class="form-control form-control-solid w-250px ps-13" placeholder="Buscar tipos de bônus" />
            </div>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_tipo_bonus" onclick="novoTipoBonus()">
                <i class="ki-duotone ki-plus fs-2"></i>
                Novo Tipo de Bônus
            </button>
        </div>
    </div>
    <div class="card-body pt-0">
        <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_tipos_bonus_table">
            <thead>
                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                    <th class="min-w-150px">Nome</th>
                    <th class="min-w-200px">Descrição</th>
                    <th class="min-w-100px">Status</th>
                    <th class="text-end min-w-100px">Ações</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 fw-semibold">
                <?php foreach ($tipos_bonus as $tipo): ?>
                <tr>
                    <td><?= htmlspecialchars($tipo['nome']) ?></td>
                    <td><?= htmlspecialchars($tipo['descricao'] ?: '-') ?></td>
                    <td>
                        <span class="badge badge-light-<?= $tipo['status'] === 'ativo' ? 'success' : 'secondary' ?>">
                            <?= ucfirst($tipo['status']) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-light-warning me-2" onclick="editarTipoBonus(<?= htmlspecialchars(json_encode($tipo)) ?>)">
                            <i class="ki-duotone ki-pencil fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </button>
                        <button type="button" class="btn btn-sm btn-light-danger" onclick="deletarTipoBonus(<?= $tipo['id'] ?>, '<?= htmlspecialchars($tipo['nome']) ?>')">
                            <i class="ki-duotone ki-trash fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tipo de Bônus -->
<div class="modal fade" id="kt_modal_tipo_bonus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_tipo_bonus_header">
                <h2 class="fw-bold">Novo Tipo de Bônus</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_tipo_bonus_form" method="POST">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <input type="hidden" name="action" id="tipo_bonus_action" value="add">
                    <input type="hidden" name="id" id="tipo_bonus_id">
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Nome *</label>
                        <input type="text" name="nome" id="nome_tipo_bonus" class="form-control form-control-solid mb-3 mb-lg-0" required />
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="descricao" id="descricao_tipo_bonus" class="form-control form-control-solid" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status</label>
                        <select name="status" id="status_tipo_bonus" class="form-select form-select-solid">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
var KTTiposBonusList = function() {
    var table = document.getElementById('kt_tipos_bonus_table');
    var datatable;

    var initDatatable = function() {
        datatable = $(table).DataTable({
            "info": true,
            "order": [],
            "pageLength": 10,
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            }
        });
    }

    return {
        init: function() {
            if (!table) {
                return;
            }
            initDatatable();
        }
    };
}();

function novoTipoBonus() {
    document.getElementById('kt_modal_tipo_bonus_header').querySelector('h2').textContent = 'Novo Tipo de Bônus';
    document.getElementById('tipo_bonus_action').value = 'add';
    document.getElementById('tipo_bonus_id').value = '';
    document.getElementById('kt_modal_tipo_bonus_form').reset();
}

function editarTipoBonus(tipo) {
    document.getElementById('kt_modal_tipo_bonus_header').querySelector('h2').textContent = 'Editar Tipo de Bônus';
    document.getElementById('tipo_bonus_action').value = 'edit';
    document.getElementById('tipo_bonus_id').value = tipo.id;
    document.getElementById('nome_tipo_bonus').value = tipo.nome || '';
    document.getElementById('descricao_tipo_bonus').value = tipo.descricao || '';
    document.getElementById('status_tipo_bonus').value = tipo.status || 'ativo';
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_tipo_bonus'));
    modal.show();
}

function deletarTipoBonus(id, nome) {
    Swal.fire({
        text: `Tem certeza que deseja excluir o tipo de bônus "${nome}"?`,
        icon: "warning",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "Sim, excluir!",
        cancelButtonText: "Cancelar",
        customClass: {
            confirmButton: "btn fw-bold btn-danger",
            cancelButton: "btn fw-bold btn-active-light-primary"
        }
    }).then(function(result) {
        if (result.value) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

KTUtil.onDOMContentLoaded(function() {
    KTTiposBonusList.init();
});
</script>

