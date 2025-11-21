<?php
/**
 * CRUD de Cargos - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

if (!check_permission('ADMIN') && !check_permission('RH')) {
    redirect('dashboard.php', 'Você não tem permissão para acessar esta página.', 'error');
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $empresa_id = $_POST['empresa_id'] ?? ($usuario['role'] === 'ADMIN' ? null : $usuario['empresa_id']);
        $nome_cargo = sanitize($_POST['nome_cargo'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $salario_base = !empty($_POST['salario_base']) ? str_replace(['.', ','], ['', '.'], $_POST['salario_base']) : null;
        $status = $_POST['status'] ?? 'ativo';
        
        if (empty($nome_cargo) || empty($empresa_id)) {
            redirect('cargos.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO cargos (empresa_id, nome_cargo, descricao, salario_base, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$empresa_id, $nome_cargo, $descricao, $salario_base, $status]);
                redirect('cargos.php', 'Cargo cadastrado com sucesso!');
            } else {
                $id = $_POST['id'] ?? 0;
                $stmt = $pdo->prepare("UPDATE cargos SET empresa_id = ?, nome_cargo = ?, descricao = ?, salario_base = ?, status = ? WHERE id = ?");
                $stmt->execute([$empresa_id, $nome_cargo, $descricao, $salario_base, $status, $id]);
                redirect('cargos.php', 'Cargo atualizado com sucesso!');
            }
        } catch (PDOException $e) {
            redirect('cargos.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM cargos WHERE id = ?");
            $stmt->execute([$id]);
            redirect('cargos.php', 'Cargo excluído com sucesso!');
        } catch (PDOException $e) {
            redirect('cargos.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca cargos
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("
        SELECT c.*, e.nome_fantasia as empresa_nome 
        FROM cargos c
        LEFT JOIN empresas e ON c.empresa_id = e.id
        ORDER BY e.nome_fantasia, c.nome_cargo
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT c.*, e.nome_fantasia as empresa_nome 
        FROM cargos c
        LEFT JOIN empresas e ON c.empresa_id = e.id
        WHERE c.empresa_id = ?
        ORDER BY c.nome_cargo
    ");
    $stmt->execute([$usuario['empresa_id']]);
}
$cargos = $stmt->fetchAll();

// Busca empresas para select
if ($usuario['role'] === 'ADMIN') {
    $stmt_empresas = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
} else {
    $stmt_empresas = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? AND status = 'ativo'");
    $stmt_empresas->execute([$usuario['empresa_id']]);
}
$empresas = $stmt_empresas->fetchAll();

$page_title = 'Cargos';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Cargos</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Cargos</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Card-->
        <div class="card">
            <!--begin::Card header-->
            <div class="card-header border-0 pt-6">
                <!--begin::Card title-->
                <div class="card-title">
                    <!--begin::Search-->
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <input type="text" data-kt-cargo-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar cargos" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end" data-kt-cargo-table-toolbar="base">
                        <!--begin::Add cargo-->
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_cargo">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Novo Cargo
                        </button>
                        <!--end::Add cargo-->
                    </div>
                    <!--end::Toolbar-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_cargos_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <?php if ($usuario['role'] === 'ADMIN'): ?>
                            <th class="min-w-150px">Empresa</th>
                            <?php endif; ?>
                            <th class="min-w-200px">Nome do Cargo</th>
                            <th class="min-w-250px">Descrição</th>
                            <th class="min-w-120px">Salário Base</th>
                            <th class="min-w-100px">Status</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($cargos as $cargo): ?>
                        <tr>
                            <td><?= $cargo['id'] ?></td>
                            <?php if ($usuario['role'] === 'ADMIN'): ?>
                            <td>
                                <a href="#" class="text-gray-800 text-hover-primary mb-1"><?= htmlspecialchars($cargo['empresa_nome']) ?></a>
                            </td>
                            <?php endif; ?>
                            <td>
                                <a href="#" class="text-gray-800 text-hover-primary mb-1"><?= htmlspecialchars($cargo['nome_cargo']) ?></a>
                            </td>
                            <td><?= htmlspecialchars($cargo['descricao']) ?></td>
                            <td><?= $cargo['salario_base'] ? formatar_moeda($cargo['salario_base']) : '-' ?></td>
                            <td>
                                <?php if ($cargo['status'] === 'ativo'): ?>
                                    <span class="badge badge-light-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-light-secondary">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="#" class="btn btn-sm btn-light btn-flex btn-center btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                    Ações 
                                    <i class="ki-duotone ki-down fs-5 ms-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </a>
                                <!--begin::Menu-->
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" onclick="editarCargo(<?= htmlspecialchars(json_encode($cargo)) ?>); return false;">Editar</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" data-kt-cargo-table-filter="delete_row" data-cargo-id="<?= $cargo['id'] ?>" data-cargo-nome="<?= htmlspecialchars($cargo['nome_cargo']) ?>">Excluir</a>
                                    </div>
                                    <!--end::Menu item-->
                                </div>
                                <!--end::Menu-->
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!--end::Table-->
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Cargo-->
<div class="modal fade" id="kt_modal_cargo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_cargo_header">
                <h2 class="fw-bold">Novo Cargo</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_cargo_form" method="POST" class="form">
                    <input type="hidden" name="action" id="cargo_action" value="add">
                    <input type="hidden" name="id" id="cargo_id">
                    
                    <?php if ($usuario['role'] === 'ADMIN'): ?>
                    <div class="mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Empresa</label>
                        <select name="empresa_id" id="empresa_id" class="form-select form-select-solid" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($empresas as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nome_fantasia']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="empresa_id" value="<?= $usuario['empresa_id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome do Cargo</label>
                        <input type="text" name="nome_cargo" id="nome_cargo" class="form-control form-control-solid mb-3 mb-lg-0" required />
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="descricao" id="descricao" class="form-control form-control-solid" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Salário Base (opcional)</label>
                        <input type="text" name="salario_base" id="salario_base" class="form-control form-control-solid mb-3 mb-lg-0" placeholder="0,00" />
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status</label>
                        <select name="status" id="status" class="form-select form-select-solid">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                    
                    <div class="text-center pt-15">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal - Cargo-->

<script>
"use strict";
var KTCargosList = function() {
    var t, n;
    
    var initDeleteHandlers = function() {
        n.querySelectorAll('[data-kt-cargo-table-filter="delete_row"]').forEach(function(element) {
            element.addEventListener("click", function(e) {
                e.preventDefault();
                const cargoId = this.getAttribute("data-cargo-id");
                const cargoNome = this.getAttribute("data-cargo-nome");
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        text: "Tem certeza que deseja excluir " + cargoNome + "?",
                        icon: "warning",
                        showCancelButton: true,
                        buttonsStyling: false,
                        confirmButtonText: "Sim, excluir!",
                        cancelButtonText: "Não, cancelar",
                        customClass: {
                            confirmButton: "btn fw-bold btn-danger",
                            cancelButton: "btn fw-bold btn-active-light-primary"
                        }
                    }).then(function(result) {
                        if (result.value) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="${cargoId}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            Swal.fire({
                                text: cargoNome + " não foi excluído.",
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok, entendi!",
                                customClass: {
                                    confirmButton: "btn fw-bold btn-primary"
                                }
                            });
                        }
                    });
                } else {
                    if (confirm("Tem certeza que deseja excluir " + cargoNome + "?")) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="${cargoId}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            });
        });
    };
    
    return {
        init: function() {
            n = document.querySelector("#kt_cargos_table");
            
            if (n) {
                t = $(n).DataTable({
                    info: false,
                    order: [],
                    pageLength: 25,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    columnDefs: [
                        { orderable: false, targets: <?= $usuario['role'] === 'ADMIN' ? 6 : 5 ?> }
                    ]
                });
                
                // Busca customizada
                document.querySelector('[data-kt-cargo-table-filter="search"]').addEventListener("keyup", function(e) {
                    t.search(e.target.value).draw();
                });
                
                // Inicializa handlers de exclusão
                initDeleteHandlers();
                
                // Reinicializa apenas os handlers após draw
                t.on("draw", function() {
                    initDeleteHandlers();
                    
                    // Inicialização manual de componentes específicos se necessário
                    // Evita chamar KTMenu.createInstances() que causa conflito com o menu lateral
                    var menus = document.querySelectorAll('#kt_cargos_table [data-kt-menu="true"]');
                    if (menus && menus.length > 0) {
                        menus.forEach(function(el) {
                            if (typeof KTMenu !== 'undefined') {
                                // Tenta reinicializar apenas este elemento
                                try {
                                    KTMenu.init(el);
                                } catch (e) {}
                            }
                        });
                    }
                });
            }
        }
    };
}();

// Aguarda jQuery e SweetAlert estarem disponíveis
(function waitForDependencies() {
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
        setTimeout(waitForDependencies, 50);
        return;
    }
    
    if (typeof Swal === 'undefined') {
        setTimeout(function() {
            if (typeof Swal !== 'undefined') {
                KTCargosList.init();
            } else {
                console.warn('SweetAlert2 não foi carregado, usando fallback');
                KTCargosList.init();
            }
        }, 100);
    } else {
        $(document).ready(function() {
            KTCargosList.init();
        });
    }
})();

function editarCargo(cargo) {
    document.getElementById('kt_modal_cargo_header').querySelector('h2').textContent = 'Editar Cargo';
    document.getElementById('cargo_action').value = 'edit';
    document.getElementById('cargo_id').value = cargo.id;
    if (document.getElementById('empresa_id')) {
        document.getElementById('empresa_id').value = cargo.empresa_id || '';
    }
    document.getElementById('nome_cargo').value = cargo.nome_cargo || '';
    document.getElementById('descricao').value = cargo.descricao || '';
    
    // Formata salário para exibição
    if (cargo.salario_base) {
        var salario = parseFloat(cargo.salario_base).toFixed(2).replace('.', ',');
        salario = salario.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        document.getElementById('salario_base').value = salario;
    } else {
        document.getElementById('salario_base').value = '';
    }
    
    document.getElementById('status').value = cargo.status || 'ativo';
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_cargo'));
    modal.show();
}

// Reset modal ao fechar
document.getElementById('kt_modal_cargo').addEventListener('hidden.bs.modal', function () {
    document.getElementById('kt_modal_cargo_form').reset();
    document.getElementById('kt_modal_cargo_header').querySelector('h2').textContent = 'Novo Cargo';
    document.getElementById('cargo_action').value = 'add';
    document.getElementById('cargo_id').value = '';
});

// Aplica máscaras quando o modal é aberto
(function waitForDependencies() {
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
        setTimeout(waitForDependencies, 50);
        return;
    }
    
    $(document).ready(function() {
        // Aguarda jQuery Mask estar disponível
        if (typeof $.fn.mask !== 'undefined') {
            // Máscara para salário (moeda brasileira)
            $('#salario_base').mask('#.##0,00', {reverse: true});
            
            // Reaplica máscara quando o modal é aberto
            $('#kt_modal_cargo').on('shown.bs.modal', function() {
                $('#salario_base').mask('#.##0,00', {reverse: true});
            });
        } else {
            setTimeout(waitForDependencies, 100);
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
