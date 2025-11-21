<?php
/**
 * CRUD de Tipos de Ocorrências - Metronic Theme
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
        $categoria = $_POST['categoria'] ?? 'outros';
        $permite_tempo_atraso = isset($_POST['permite_tempo_atraso']) ? 1 : 0;
        $permite_tipo_ponto = isset($_POST['permite_tipo_ponto']) ? 1 : 0;
        $status = $_POST['status'] ?? 'ativo';
        
        if (empty($nome) || empty($codigo)) {
            redirect('tipos_ocorrencias.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        // Valida código único
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("SELECT id FROM tipos_ocorrencias WHERE codigo = ?");
                $stmt->execute([$codigo]);
                if ($stmt->fetch()) {
                    redirect('tipos_ocorrencias.php', 'Código já existe!', 'error');
                }
                
                $stmt = $pdo->prepare("INSERT INTO tipos_ocorrencias (nome, codigo, categoria, permite_tempo_atraso, permite_tipo_ponto, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $codigo, $categoria, $permite_tempo_atraso, $permite_tipo_ponto, $status]);
                redirect('tipos_ocorrencias.php', 'Tipo de ocorrência cadastrado com sucesso!');
            } else {
                $id = $_POST['id'] ?? 0;
                
                // Verifica código único (exceto o próprio registro)
                $stmt = $pdo->prepare("SELECT id FROM tipos_ocorrencias WHERE codigo = ? AND id != ?");
                $stmt->execute([$codigo, $id]);
                if ($stmt->fetch()) {
                    redirect('tipos_ocorrencias.php', 'Código já existe!', 'error');
                }
                
                $stmt = $pdo->prepare("UPDATE tipos_ocorrencias SET nome = ?, codigo = ?, categoria = ?, permite_tempo_atraso = ?, permite_tipo_ponto = ?, status = ? WHERE id = ?");
                $stmt->execute([$nome, $codigo, $categoria, $permite_tempo_atraso, $permite_tipo_ponto, $status, $id]);
                redirect('tipos_ocorrencias.php', 'Tipo de ocorrência atualizado com sucesso!');
            }
        } catch (PDOException $e) {
            redirect('tipos_ocorrencias.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            // Verifica se há ocorrências usando este tipo
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ocorrencias WHERE tipo_ocorrencia_id = ?");
            $stmt->execute([$id]);
            $total = $stmt->fetch()['total'];
            
            if ($total > 0) {
                redirect('tipos_ocorrencias.php', 'Não é possível excluir: existem ' . $total . ' ocorrência(s) usando este tipo!', 'error');
            }
            
            $stmt = $pdo->prepare("DELETE FROM tipos_ocorrencias WHERE id = ?");
            $stmt->execute([$id]);
            redirect('tipos_ocorrencias.php', 'Tipo de ocorrência excluído com sucesso!');
        } catch (PDOException $e) {
            redirect('tipos_ocorrencias.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca tipos de ocorrências
$stmt = $pdo->query("SELECT * FROM tipos_ocorrencias ORDER BY categoria, nome");
$tipos = $stmt->fetchAll();

$page_title = 'Tipos de Ocorrências';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Tipos de Ocorrências</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Tipos de Ocorrências</li>
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
                        <input type="text" data-kt-tipo-ocorrencia-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar tipos" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end" data-kt-tipo-ocorrencia-table-toolbar="base">
                        <!--begin::Add tipo-->
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_tipo_ocorrencia">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Novo Tipo
                        </button>
                        <!--end::Add tipo-->
                    </div>
                    <!--end::Toolbar-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_tipos_ocorrencias_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <th class="min-w-200px">Nome</th>
                            <th class="min-w-150px">Código</th>
                            <th class="min-w-150px">Categoria</th>
                            <th class="min-w-100px">Permite Tempo</th>
                            <th class="min-w-100px">Permite Ponto</th>
                            <th class="min-w-100px">Status</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($tipos as $tipo): ?>
                        <tr>
                            <td><?= $tipo['id'] ?></td>
                            <td>
                                <a href="#" class="text-gray-800 text-hover-primary mb-1"><?= htmlspecialchars($tipo['nome']) ?></a>
                            </td>
                            <td>
                                <span class="badge badge-light-info"><?= htmlspecialchars($tipo['codigo']) ?></span>
                            </td>
                            <td>
                                <?php
                                $categoria_labels = [
                                    'pontualidade' => 'Pontualidade',
                                    'comportamento' => 'Comportamento',
                                    'desempenho' => 'Desempenho',
                                    'outros' => 'Outros'
                                ];
                                $categoria_colors = [
                                    'pontualidade' => 'badge-light-warning',
                                    'comportamento' => 'badge-light-danger',
                                    'desempenho' => 'badge-light-primary',
                                    'outros' => 'badge-light-secondary'
                                ];
                                ?>
                                <span class="badge <?= $categoria_colors[$tipo['categoria']] ?? 'badge-light-secondary' ?>">
                                    <?= $categoria_labels[$tipo['categoria']] ?? ucfirst($tipo['categoria']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($tipo['permite_tempo_atraso']): ?>
                                    <span class="badge badge-light-success">Sim</span>
                                <?php else: ?>
                                    <span class="badge badge-light-secondary">Não</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tipo['permite_tipo_ponto']): ?>
                                    <span class="badge badge-light-success">Sim</span>
                                <?php else: ?>
                                    <span class="badge badge-light-secondary">Não</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tipo['status'] === 'ativo'): ?>
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
                                        <a href="#" class="menu-link px-3" onclick="editarTipoOcorrencia(<?= htmlspecialchars(json_encode($tipo)) ?>); return false;">Editar</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" data-kt-tipo-ocorrencia-table-filter="delete_row" data-tipo-id="<?= $tipo['id'] ?>" data-tipo-nome="<?= htmlspecialchars($tipo['nome']) ?>">Excluir</a>
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

<!--begin::Modal - Tipo de Ocorrência-->
<div class="modal fade" id="kt_modal_tipo_ocorrencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_tipo_ocorrencia_header">
                <h2 class="fw-bold">Novo Tipo de Ocorrência</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_tipo_ocorrencia_form" method="POST" class="form">
                    <input type="hidden" name="action" id="tipo_ocorrencia_action" value="add">
                    <input type="hidden" name="id" id="tipo_ocorrencia_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-8">
                            <label class="required fw-semibold fs-6 mb-2">Nome</label>
                            <input type="text" name="nome" id="nome" class="form-control form-control-solid mb-3 mb-lg-0" required />
                        </div>
                        <div class="col-md-4">
                            <label class="required fw-semibold fs-6 mb-2">Código</label>
                            <input type="text" name="codigo" id="codigo" class="form-control form-control-solid mb-3 mb-lg-0" placeholder="ex: atraso_entrada" required />
                            <small class="text-muted">Código único (sem espaços, use _)</small>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Categoria</label>
                            <select name="categoria" id="categoria" class="form-select form-select-solid" required>
                                <option value="pontualidade">Pontualidade</option>
                                <option value="comportamento">Comportamento</option>
                                <option value="desempenho">Desempenho</option>
                                <option value="outros">Outros</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Status</label>
                            <select name="status" id="status" class="form-select form-select-solid">
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <div class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="permite_tempo_atraso" id="permite_tempo_atraso" value="1" />
                                <label class="form-check-label fw-semibold" for="permite_tempo_atraso">
                                    Permite informar tempo de atraso
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="permite_tipo_ponto" id="permite_tipo_ponto" value="1" />
                                <label class="form-check-label fw-semibold" for="permite_tipo_ponto">
                                    Permite informar tipo de ponto
                                </label>
                            </div>
                        </div>
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
<!--end::Modal - Tipo de Ocorrência-->

<script>
"use strict";
var KTTiposOcorrenciasList = function() {
    var t, n;
    
    var initDeleteHandlers = function() {
        n.querySelectorAll('[data-kt-tipo-ocorrencia-table-filter="delete_row"]').forEach(function(element) {
            element.addEventListener("click", function(e) {
                e.preventDefault();
                const tipoId = this.getAttribute("data-tipo-id");
                const tipoNome = this.getAttribute("data-tipo-nome");
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        text: "Tem certeza que deseja excluir " + tipoNome + "?",
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
                                <input type="hidden" name="id" value="${tipoId}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            Swal.fire({
                                text: tipoNome + " não foi excluído.",
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
                    if (confirm("Tem certeza que deseja excluir " + tipoNome + "?")) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="${tipoId}">
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
            n = document.querySelector("#kt_tipos_ocorrencias_table");
            
            if (n) {
                t = $(n).DataTable({
                    info: false,
                    order: [],
                    pageLength: 25,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    columnDefs: [
                        { orderable: false, targets: 7 }
                    ]
                });
                
                // Busca customizada
                document.querySelector('[data-kt-tipo-ocorrencia-table-filter="search"]').addEventListener("keyup", function(e) {
                    t.search(e.target.value).draw();
                });
                
                // Inicializa handlers de exclusão
                initDeleteHandlers();
                
                // Reinicializa apenas os handlers após draw
                t.on("draw", function() {
                    initDeleteHandlers();
                    
                    // Inicialização manual de componentes específicos se necessário
                    // Evita chamar KTMenu.createInstances() que causa conflito com o menu lateral
                    var menus = document.querySelectorAll('#kt_tipos_ocorrencias_table [data-kt-menu="true"]');
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
                KTTiposOcorrenciasList.init();
            } else {
                console.warn('SweetAlert2 não foi carregado, usando fallback');
                KTTiposOcorrenciasList.init();
            }
        }, 100);
    } else {
        $(document).ready(function() {
            KTTiposOcorrenciasList.init();
        });
    }
})();

function editarTipoOcorrencia(tipo) {
    document.getElementById('kt_modal_tipo_ocorrencia_header').querySelector('h2').textContent = 'Editar Tipo de Ocorrência';
    document.getElementById('tipo_ocorrencia_action').value = 'edit';
    document.getElementById('tipo_ocorrencia_id').value = tipo.id;
    document.getElementById('nome').value = tipo.nome || '';
    document.getElementById('codigo').value = tipo.codigo || '';
    document.getElementById('categoria').value = tipo.categoria || 'outros';
    document.getElementById('status').value = tipo.status || 'ativo';
    document.getElementById('permite_tempo_atraso').checked = tipo.permite_tempo_atraso == 1;
    document.getElementById('permite_tipo_ponto').checked = tipo.permite_tipo_ponto == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_tipo_ocorrencia'));
    modal.show();
}

// Reset modal ao fechar
document.getElementById('kt_modal_tipo_ocorrencia').addEventListener('hidden.bs.modal', function () {
    document.getElementById('kt_modal_tipo_ocorrencia_form').reset();
    document.getElementById('kt_modal_tipo_ocorrencia_header').querySelector('h2').textContent = 'Novo Tipo de Ocorrência';
    document.getElementById('tipo_ocorrencia_action').value = 'add';
    document.getElementById('tipo_ocorrencia_id').value = '';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

