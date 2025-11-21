<?php
/**
 * CRUD de Níveis Hierárquicos - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

// Apenas ADMIN e RH podem acessar
if (!check_permission('ADMIN') && !check_permission('RH')) {
    redirect('dashboard.php', 'Você não tem permissão para acessar esta página.', 'error');
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $nome = sanitize($_POST['nome'] ?? '');
        $codigo = sanitize($_POST['codigo'] ?? '');
        $nivel = (int)($_POST['nivel'] ?? 1);
        $descricao = sanitize($_POST['descricao'] ?? '');
        $status = $_POST['status'] ?? 'ativo';
        
        if (empty($nome) || empty($codigo) || $nivel < 1) {
            redirect('niveis_hierarquicos.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        // Valida código único
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("SELECT id FROM niveis_hierarquicos WHERE codigo = ?");
                $stmt->execute([$codigo]);
                if ($stmt->fetch()) {
                    redirect('niveis_hierarquicos.php', 'Código já existe!', 'error');
                }
                
                $stmt = $pdo->prepare("INSERT INTO niveis_hierarquicos (nome, codigo, nivel, descricao, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $codigo, $nivel, $descricao, $status]);
                redirect('niveis_hierarquicos.php', 'Nível hierárquico cadastrado com sucesso!');
            } else {
                $id = $_POST['id'] ?? 0;
                
                // Verifica código único (exceto o próprio registro)
                $stmt = $pdo->prepare("SELECT id FROM niveis_hierarquicos WHERE codigo = ? AND id != ?");
                $stmt->execute([$codigo, $id]);
                if ($stmt->fetch()) {
                    redirect('niveis_hierarquicos.php', 'Código já existe!', 'error');
                }
                
                $stmt = $pdo->prepare("UPDATE niveis_hierarquicos SET nome = ?, codigo = ?, nivel = ?, descricao = ?, status = ? WHERE id = ?");
                $stmt->execute([$nome, $codigo, $nivel, $descricao, $status, $id]);
                redirect('niveis_hierarquicos.php', 'Nível hierárquico atualizado com sucesso!');
            }
        } catch (PDOException $e) {
            redirect('niveis_hierarquicos.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            // Verifica se há colaboradores usando este nível
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE nivel_hierarquico_id = ?");
            $stmt->execute([$id]);
            $total = $stmt->fetch()['total'];
            
            if ($total > 0) {
                redirect('niveis_hierarquicos.php', 'Não é possível excluir: existem ' . $total . ' colaborador(es) usando este nível!', 'error');
            }
            
            $stmt = $pdo->prepare("DELETE FROM niveis_hierarquicos WHERE id = ?");
            $stmt->execute([$id]);
            redirect('niveis_hierarquicos.php', 'Nível hierárquico excluído com sucesso!');
        } catch (PDOException $e) {
            redirect('niveis_hierarquicos.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca níveis hierárquicos ordenados por nível
$stmt = $pdo->query("SELECT * FROM niveis_hierarquicos ORDER BY nivel ASC, nome ASC");
$niveis = $stmt->fetchAll();

$page_title = 'Níveis Hierárquicos';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Níveis Hierárquicos</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Níveis Hierárquicos</li>
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
                        <input type="text" data-kt-nivel-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar níveis" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end" data-kt-nivel-table-toolbar="base">
                        <!--begin::Add nivel-->
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_nivel">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Novo Nível
                        </button>
                        <!--end::Add nivel-->
                    </div>
                    <!--end::Toolbar-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_niveis_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <th class="min-w-100px">Nível</th>
                            <th class="min-w-200px">Nome</th>
                            <th class="min-w-150px">Código</th>
                            <th class="min-w-250px">Descrição</th>
                            <th class="min-w-100px">Status</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($niveis as $nivel): ?>
                        <tr>
                            <td><?= $nivel['id'] ?></td>
                            <td>
                                <span class="badge badge-light-primary fs-7"><?= $nivel['nivel'] ?></span>
                            </td>
                            <td>
                                <a href="#" class="text-gray-800 text-hover-primary mb-1"><?= htmlspecialchars($nivel['nome']) ?></a>
                            </td>
                            <td>
                                <span class="badge badge-light-info"><?= htmlspecialchars($nivel['codigo']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($nivel['descricao']) ?></td>
                            <td>
                                <?php if ($nivel['status'] === 'ativo'): ?>
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
                                        <a href="#" class="menu-link px-3" onclick="editarNivel(<?= htmlspecialchars(json_encode($nivel)) ?>); return false;">Editar</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" data-kt-nivel-table-filter="delete_row" data-nivel-id="<?= $nivel['id'] ?>" data-nivel-nome="<?= htmlspecialchars($nivel['nome']) ?>">Excluir</a>
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

<!--begin::Modal - Nível Hierárquico-->
<div class="modal fade" id="kt_modal_nivel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_nivel_header">
                <h2 class="fw-bold">Novo Nível Hierárquico</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_nivel_form" method="POST" class="form">
                    <input type="hidden" name="action" id="nivel_action" value="add">
                    <input type="hidden" name="id" id="nivel_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-8">
                            <label class="required fw-semibold fs-6 mb-2">Nome</label>
                            <input type="text" name="nome" id="nome" class="form-control form-control-solid mb-3 mb-lg-0" required />
                        </div>
                        <div class="col-md-4">
                            <label class="required fw-semibold fs-6 mb-2">Código</label>
                            <input type="text" name="codigo" id="codigo" class="form-control form-control-solid mb-3 mb-lg-0" placeholder="ex: DIRETORIA" required />
                            <small class="text-muted">Código único (sem espaços, use _)</small>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Nível</label>
                            <input type="number" name="nivel" id="nivel" class="form-control form-control-solid mb-3 mb-lg-0" min="1" value="1" required />
                            <small class="text-muted">1 = mais alto, números maiores = mais baixo</small>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Status</label>
                            <select name="status" id="status" class="form-select form-select-solid">
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="descricao" id="descricao" class="form-control form-control-solid" rows="3"></textarea>
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
<!--end::Modal - Nível Hierárquico-->

<script>
"use strict";
var KTNiveisList = function() {
    var t, n;
    
    var initDeleteHandlers = function() {
        n.querySelectorAll('[data-kt-nivel-table-filter="delete_row"]').forEach(function(element) {
            element.addEventListener("click", function(e) {
                e.preventDefault();
                const nivelId = this.getAttribute("data-nivel-id");
                const nivelNome = this.getAttribute("data-nivel-nome");
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        text: "Tem certeza que deseja excluir " + nivelNome + "?",
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
                                <input type="hidden" name="id" value="${nivelId}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            Swal.fire({
                                text: nivelNome + " não foi excluído.",
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
                    if (confirm("Tem certeza que deseja excluir " + nivelNome + "?")) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="${nivelId}">
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
            n = document.querySelector("#kt_niveis_table");
            
            if (n) {
                t = $(n).DataTable({
                    info: false,
                    order: [[1, 'asc']],
                    pageLength: 25,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    columnDefs: [
                        { orderable: false, targets: 6 }
                    ]
                });
                
                // Busca customizada
                document.querySelector('[data-kt-nivel-table-filter="search"]').addEventListener("keyup", function(e) {
                    t.search(e.target.value).draw();
                });
                
                // Inicializa handlers de exclusão
                initDeleteHandlers();
                
                // Reinicializa apenas os handlers após draw
                t.on("draw", function() {
                    initDeleteHandlers();
                    
                    // Inicialização manual de componentes específicos se necessário
                    // Evita chamar KTMenu.createInstances() que causa conflito com o menu lateral
                    var menus = document.querySelectorAll('#kt_niveis_table [data-kt-menu="true"]');
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
                KTNiveisList.init();
            } else {
                console.warn('SweetAlert2 não foi carregado, usando fallback');
                KTNiveisList.init();
            }
        }, 100);
    } else {
        $(document).ready(function() {
            KTNiveisList.init();
        });
    }
})();

function editarNivel(nivel) {
    document.getElementById('kt_modal_nivel_header').querySelector('h2').textContent = 'Editar Nível Hierárquico';
    document.getElementById('nivel_action').value = 'edit';
    document.getElementById('nivel_id').value = nivel.id;
    document.getElementById('nome').value = nivel.nome || '';
    document.getElementById('codigo').value = nivel.codigo || '';
    document.getElementById('nivel').value = nivel.nivel || 1;
    document.getElementById('descricao').value = nivel.descricao || '';
    document.getElementById('status').value = nivel.status || 'ativo';
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_nivel'));
    modal.show();
}

// Reset modal ao fechar
document.getElementById('kt_modal_nivel').addEventListener('hidden.bs.modal', function () {
    document.getElementById('kt_modal_nivel_form').reset();
    document.getElementById('kt_modal_nivel_header').querySelector('h2').textContent = 'Novo Nível Hierárquico';
    document.getElementById('nivel_action').value = 'add';
    document.getElementById('nivel_id').value = '';
    document.getElementById('nivel').value = '1';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

