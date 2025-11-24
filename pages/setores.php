<?php
/**
 * CRUD de Setores - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('setores.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $empresa_id = $_POST['empresa_id'] ?? null;
        
        // Para RH, valida se a empresa selecionada está nas empresas permitidas
        if ($usuario['role'] === 'RH' && $empresa_id) {
            if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                if (!in_array($empresa_id, $usuario['empresas_ids'])) {
                    redirect('setores.php', 'Você não tem permissão para criar setores nesta empresa!', 'error');
                }
            } elseif (isset($usuario['empresa_id']) && $empresa_id != $usuario['empresa_id']) {
                redirect('setores.php', 'Você não tem permissão para criar setores nesta empresa!', 'error');
            }
        } elseif ($usuario['role'] !== 'ADMIN' && empty($empresa_id)) {
            // Se não for ADMIN e não tiver empresa_id no POST, usa a primeira empresa disponível
            if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                $empresa_id = $usuario['empresas_ids'][0];
            } else {
                $empresa_id = $usuario['empresa_id'] ?? null;
            }
        }
        
        $nome_setor = sanitize($_POST['nome_setor'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $status = $_POST['status'] ?? 'ativo';
        
        if (empty($nome_setor) || empty($empresa_id)) {
            redirect('setores.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO setores (empresa_id, nome_setor, descricao, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$empresa_id, $nome_setor, $descricao, $status]);
                redirect('setores.php', 'Setor cadastrado com sucesso!');
            } else {
                $id = $_POST['id'] ?? 0;
                $stmt = $pdo->prepare("UPDATE setores SET empresa_id = ?, nome_setor = ?, descricao = ?, status = ? WHERE id = ?");
                $stmt->execute([$empresa_id, $nome_setor, $descricao, $status, $id]);
                redirect('setores.php', 'Setor atualizado com sucesso!');
            }
        } catch (PDOException $e) {
            redirect('setores.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM setores WHERE id = ?");
            $stmt->execute([$id]);
            redirect('setores.php', 'Setor excluído com sucesso!');
        } catch (PDOException $e) {
            redirect('setores.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca setores
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("
        SELECT s.*, e.nome_fantasia as empresa_nome 
        FROM setores s
        LEFT JOIN empresas e ON s.empresa_id = e.id
        ORDER BY e.nome_fantasia, s.nome_setor
    ");
    $setores = $stmt->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt = $pdo->prepare("
            SELECT s.*, e.nome_fantasia as empresa_nome 
            FROM setores s
            LEFT JOIN empresas e ON s.empresa_id = e.id
            WHERE s.empresa_id IN ($placeholders)
            ORDER BY e.nome_fantasia, s.nome_setor
        ");
        $stmt->execute($usuario['empresas_ids']);
        $setores = $stmt->fetchAll();
    } else {
        // Fallback para compatibilidade
        $stmt = $pdo->prepare("
            SELECT s.*, e.nome_fantasia as empresa_nome 
            FROM setores s
            LEFT JOIN empresas e ON s.empresa_id = e.id
            WHERE s.empresa_id = ?
            ORDER BY s.nome_setor
        ");
        $stmt->execute([$usuario['empresa_id'] ?? 0]);
        $setores = $stmt->fetchAll();
    }
} else {
    // Outros roles (GESTOR, etc) - apenas uma empresa
    $stmt = $pdo->prepare("
        SELECT s.*, e.nome_fantasia as empresa_nome 
        FROM setores s
        LEFT JOIN empresas e ON s.empresa_id = e.id
        WHERE s.empresa_id = ?
        ORDER BY s.nome_setor
    ");
    $stmt->execute([$usuario['empresa_id'] ?? 0]);
    $setores = $stmt->fetchAll();
}

// Busca empresas para select
if ($usuario['role'] === 'ADMIN') {
    $stmt_empresas = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
    $empresas = $stmt_empresas->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt_empresas = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id IN ($placeholders) AND status = 'ativo' ORDER BY nome_fantasia");
        $stmt_empresas->execute($usuario['empresas_ids']);
        $empresas = $stmt_empresas->fetchAll();
    } else {
        // Fallback para compatibilidade
        $stmt_empresas = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? AND status = 'ativo'");
        $stmt_empresas->execute([$usuario['empresa_id'] ?? 0]);
        $empresas = $stmt_empresas->fetchAll();
    }
} else {
    // Outros roles (GESTOR, etc) - apenas uma empresa
    $stmt_empresas = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? AND status = 'ativo'");
    $stmt_empresas->execute([$usuario['empresa_id'] ?? 0]);
    $empresas = $stmt_empresas->fetchAll();
}

$page_title = 'Setores';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Setores</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Setores</li>
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
                        <input type="text" data-kt-setor-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar setores" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end" data-kt-setor-table-toolbar="base">
                        <!--begin::Add setor-->
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_setor">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Novo Setor
                        </button>
                        <!--end::Add setor-->
                    </div>
                    <!--end::Toolbar-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_setores_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <?php if ($usuario['role'] === 'ADMIN' || ($usuario['role'] === 'RH' && isset($usuario['empresas_ids']) && count($usuario['empresas_ids']) > 1)): ?>
                            <th class="min-w-150px">Empresa</th>
                            <?php endif; ?>
                            <th class="min-w-200px">Nome do Setor</th>
                            <th class="min-w-250px">Descrição</th>
                            <th class="min-w-100px">Status</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($setores as $setor): ?>
                        <tr>
                            <td><?= $setor['id'] ?></td>
                            <?php if ($usuario['role'] === 'ADMIN' || ($usuario['role'] === 'RH' && isset($usuario['empresas_ids']) && count($usuario['empresas_ids']) > 1)): ?>
                            <td>
                                <a href="#" class="text-gray-800 text-hover-primary mb-1"><?= htmlspecialchars($setor['empresa_nome']) ?></a>
                            </td>
                            <?php endif; ?>
                            <td>
                                <a href="#" class="text-gray-800 text-hover-primary mb-1"><?= htmlspecialchars($setor['nome_setor']) ?></a>
                            </td>
                            <td><?= htmlspecialchars($setor['descricao']) ?></td>
                            <td>
                                <?php if ($setor['status'] === 'ativo'): ?>
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
                                        <a href="#" class="menu-link px-3" onclick="editarSetor(<?= htmlspecialchars(json_encode($setor)) ?>); return false;">Editar</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" data-kt-setor-table-filter="delete_row" data-setor-id="<?= $setor['id'] ?>" data-setor-nome="<?= htmlspecialchars($setor['nome_setor']) ?>">Excluir</a>
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

<!--begin::Modal - Setor-->
<div class="modal fade" id="kt_modal_setor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_setor_header">
                <h2 class="fw-bold">Novo Setor</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_setor_form" method="POST" class="form">
                    <input type="hidden" name="action" id="setor_action" value="add">
                    <input type="hidden" name="id" id="setor_id">
                    
                    <?php if ($usuario['role'] === 'ADMIN' || ($usuario['role'] === 'RH' && count($empresas) > 1)): ?>
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
                    <input type="hidden" name="empresa_id" value="<?= !empty($empresas) ? $empresas[0]['id'] : ($usuario['empresa_id'] ?? '') ?>">
                    <?php endif; ?>
                    
                    <div class="mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome do Setor</label>
                        <input type="text" name="nome_setor" id="nome_setor" class="form-control form-control-solid mb-3 mb-lg-0" required />
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="descricao" id="descricao" class="form-control form-control-solid" rows="3"></textarea>
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
<!--end::Modal - Setor-->

<script>
"use strict";
var KTSetoresList = function() {
    var t, n;
    
    var initDeleteHandlers = function() {
        n.querySelectorAll('[data-kt-setor-table-filter="delete_row"]').forEach(function(element) {
            element.addEventListener("click", function(e) {
                e.preventDefault();
                const setorId = this.getAttribute("data-setor-id");
                const setorNome = this.getAttribute("data-setor-nome");
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        text: "Tem certeza que deseja excluir " + setorNome + "?",
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
                                <input type="hidden" name="id" value="${setorId}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            Swal.fire({
                                text: setorNome + " não foi excluído.",
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
                    if (confirm("Tem certeza que deseja excluir " + setorNome + "?")) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="${setorId}">
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
            n = document.querySelector("#kt_setores_table");
            
            if (n) {
                t = $(n).DataTable({
                    info: false,
                    order: [],
                    pageLength: 25,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    columnDefs: [
                        { orderable: false, targets: <?= ($usuario['role'] === 'ADMIN' || ($usuario['role'] === 'RH' && isset($usuario['empresas_ids']) && count($usuario['empresas_ids']) > 1)) ? 5 : 4 ?> }
                    ]
                });
                
                // Busca customizada
                document.querySelector('[data-kt-setor-table-filter="search"]').addEventListener("keyup", function(e) {
                    t.search(e.target.value).draw();
                });
                
                // Inicializa handlers de exclusão
                initDeleteHandlers();
                
                // Reinicializa apenas os handlers após draw
                t.on("draw", function() {
                    initDeleteHandlers();
                    
                    // Inicialização manual de componentes específicos se necessário
                    // Evita chamar KTMenu.createInstances() que causa conflito com o menu lateral
                    var menus = document.querySelectorAll('#kt_setores_table [data-kt-menu="true"]');
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
                KTSetoresList.init();
            } else {
                console.warn('SweetAlert2 não foi carregado, usando fallback');
                KTSetoresList.init();
            }
        }, 100);
    } else {
        $(document).ready(function() {
            KTSetoresList.init();
        });
    }
})();

function editarSetor(setor) {
    document.getElementById('kt_modal_setor_header').querySelector('h2').textContent = 'Editar Setor';
    document.getElementById('setor_action').value = 'edit';
    document.getElementById('setor_id').value = setor.id;
    if (document.getElementById('empresa_id')) {
        document.getElementById('empresa_id').value = setor.empresa_id || '';
    }
    document.getElementById('nome_setor').value = setor.nome_setor || '';
    document.getElementById('descricao').value = setor.descricao || '';
    document.getElementById('status').value = setor.status || 'ativo';
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_setor'));
    modal.show();
}

// Reset modal ao fechar
document.getElementById('kt_modal_setor').addEventListener('hidden.bs.modal', function () {
    document.getElementById('kt_modal_setor_form').reset();
    document.getElementById('kt_modal_setor_header').querySelector('h2').textContent = 'Novo Setor';
    document.getElementById('setor_action').value = 'add';
    document.getElementById('setor_id').value = '';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
