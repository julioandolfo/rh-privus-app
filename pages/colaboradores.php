<?php
/**
 * Lista de Colaboradores - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('colaboradores.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$filtro_empresa = $_GET['empresa'] ?? '';
$filtro_setor = $_GET['setor'] ?? '';
$filtro_status = $_GET['status'] ?? '';

// Monta query com filtros
$where = [];
$params = [];

if ($usuario['role'] === 'RH') {
    $where[] = "c.empresa_id = ?";
    $params[] = $usuario['empresa_id'];
} elseif ($usuario['role'] === 'GESTOR') {
    // Busca setor do gestor
    $stmt = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario['id']]);
    $user_data = $stmt->fetch();
    $setor_id = $user_data['setor_id'] ?? 0;
    
    $where[] = "c.setor_id = ?";
    $params[] = $setor_id;
}

if ($filtro_empresa && $usuario['role'] === 'ADMIN') {
    $where[] = "c.empresa_id = ?";
    $params[] = $filtro_empresa;
}

if ($filtro_setor) {
    $where[] = "c.setor_id = ?";
    $params[] = $filtro_setor;
}

if ($filtro_status) {
    $where[] = "c.status = ?";
    $params[] = $filtro_status;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT c.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           car.nome_cargo
    FROM colaboradores c
    LEFT JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN setores s ON c.setor_id = s.id
    LEFT JOIN cargos car ON c.cargo_id = car.id
    $where_sql
    ORDER BY c.nome_completo
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$colaboradores = $stmt->fetchAll();

// Busca empresas para filtro
if ($usuario['role'] === 'ADMIN') {
    $stmt_empresas = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
    $empresas = $stmt_empresas->fetchAll();
} else {
    $empresas = [];
}

// Busca setores para filtro
if ($usuario['role'] === 'ADMIN') {
    $stmt_setores = $pdo->query("SELECT id, nome_setor FROM setores WHERE status = 'ativo' ORDER BY nome_setor");
} elseif ($usuario['role'] === 'RH') {
    $stmt_setores = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_setor");
    $stmt_setores->execute([$usuario['empresa_id']]);
} else {
    $stmt_setores = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE id = ? AND status = 'ativo'");
    $stmt_setores->execute([$setor_id]);
}
$setores = $stmt_setores->fetchAll();

$page_title = 'Colaboradores';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Colaboradores</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Colaboradores</li>
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
                        <input type="text" data-kt-colaborador-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar colaboradores" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end" data-kt-colaborador-table-toolbar="base">
                        <?php if ($usuario['role'] !== 'GESTOR'): ?>
                        <!--begin::Add colaborador-->
                        <a href="colaborador_add.php" class="btn btn-primary">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Novo Colaborador
                        </a>
                        <!--end::Add colaborador-->
                        <?php endif; ?>
                    </div>
                    <!--end::Toolbar-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Filtros-->
                <div class="card mb-5 mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Filtros</span>
                        </h3>
                    </div>
                    <div class="card-body pt-3">
                        <form method="GET" class="row g-3">
                            <?php if ($usuario['role'] === 'ADMIN'): ?>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Empresa</label>
                                <select name="empresa" class="form-select form-select-solid">
                                    <option value="">Todas</option>
                                    <?php foreach ($empresas as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $filtro_empresa == $emp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['nome_fantasia']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Setor</label>
                                <select name="setor" class="form-select form-select-solid">
                                    <option value="">Todos</option>
                                    <?php foreach ($setores as $setor): ?>
                                    <option value="<?= $setor['id'] ?>" <?= $filtro_setor == $setor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($setor['nome_setor']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select form-select-solid">
                                    <option value="">Todos</option>
                                    <option value="ativo" <?= $filtro_status === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="pausado" <?= $filtro_status === 'pausado' ? 'selected' : '' ?>>Pausado</option>
                                    <option value="desligado" <?= $filtro_status === 'desligado' ? 'selected' : '' ?>>Desligado</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="ki-duotone ki-magnifier fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Filtrar
                                </button>
                                <a href="colaboradores.php" class="btn btn-light">
                                    <i class="ki-duotone ki-cross fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Limpar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <!--end::Filtros-->
                
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_colaboradores_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <?php if ($usuario['role'] === 'ADMIN'): ?>
                            <th class="min-w-150px">Empresa</th>
                            <?php endif; ?>
                            <th class="min-w-200px">Nome</th>
                            <th class="min-w-120px">CPF</th>
                            <th class="min-w-150px">Setor</th>
                            <th class="min-w-150px">Cargo</th>
                            <th class="min-w-120px">Data Início</th>
                            <th class="min-w-100px">Status</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($colaboradores as $colab): ?>
                        <tr>
                            <td><?= $colab['id'] ?></td>
                            <?php if ($usuario['role'] === 'ADMIN'): ?>
                            <td>
                                <a href="#" class="text-gray-800 text-hover-primary mb-1"><?= htmlspecialchars($colab['empresa_nome']) ?></a>
                            </td>
                            <?php endif; ?>
                            <td>
                                <div class="d-flex align-items-center">
                                    <!--begin::Avatar-->
                                    <div class="symbol symbol-circle symbol-50px me-3">
                                        <?php if (!empty($colab['foto'])): ?>
                                            <img alt="Pic" src="../uploads/fotos/<?= htmlspecialchars($colab['foto']) ?>" />
                                        <?php else: ?>
                                            <div class="symbol-label fs-2 fw-semibold bg-primary text-white">
                                                <?= strtoupper(substr($colab['nome_completo'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <!--end::Avatar-->
                                    <!--begin::Name-->
                                    <a href="colaborador_view.php?id=<?= $colab['id'] ?>" class="text-gray-800 text-hover-primary mb-1">
                                        <?= htmlspecialchars($colab['nome_completo']) ?>
                                    </a>
                                    <!--end::Name-->
                                </div>
                            </td>
                            <td><?= formatar_cpf($colab['cpf']) ?></td>
                            <td><?= htmlspecialchars($colab['nome_setor']) ?></td>
                            <td><?= htmlspecialchars($colab['nome_cargo']) ?></td>
                            <td><?= formatar_data($colab['data_inicio']) ?></td>
                            <td>
                                <?php
                                $badge_class = 'badge-light-success';
                                if ($colab['status'] === 'pausado') $badge_class = 'badge-light-warning';
                                elseif ($colab['status'] === 'desligado') $badge_class = 'badge-light-secondary';
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= ucfirst($colab['status']) ?></span>
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
                                        <a href="colaborador_view.php?id=<?= $colab['id'] ?>" class="menu-link px-3">Visualizar</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <?php if ($usuario['role'] !== 'GESTOR' && $colab['status'] !== 'desligado'): ?>
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" onclick="abrirModalDemissao(<?= $colab['id'] ?>, '<?= htmlspecialchars($colab['nome_completo'], ENT_QUOTES) ?>')">Demitir</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <?php endif; ?>
                                    <?php if ($usuario['role'] !== 'GESTOR'): ?>
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="colaborador_edit.php?id=<?= $colab['id'] ?>" class="menu-link px-3">Editar</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <?php endif; ?>
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

<script>
"use strict";
var KTColaboradoresList = function() {
    var t, n;
    
    return {
        init: function() {
            n = document.querySelector("#kt_colaboradores_table");
            
            if (n) {
                t = $(n).DataTable({
                    info: false,
                    order: [],
                    pageLength: 25,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    columnDefs: [
                        { orderable: false, targets: <?= $usuario['role'] === 'ADMIN' ? 8 : 7 ?> }
                    ]
                });
                
                // Busca customizada
                document.querySelector('[data-kt-colaborador-table-filter="search"]').addEventListener("keyup", function(e) {
                    t.search(e.target.value).draw();
                });
                
                // Reinicializa apenas os handlers após draw
                t.on("draw", function() {
                    // Inicialização manual de componentes específicos se necessário
                    // Evita chamar KTMenu.createInstances() que causa conflito com o menu lateral
                    var menus = document.querySelectorAll('#kt_colaboradores_table [data-kt-menu="true"]');
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

// Aguarda jQuery estar disponível
(function waitForDependencies() {
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
        setTimeout(waitForDependencies, 50);
        return;
    }
    
    $(document).ready(function() {
        KTColaboradoresList.init();
    });
})();

// Modal de Demissão
function abrirModalDemissao(colaboradorId, nomeColaborador) {
    Swal.fire({
        title: 'Registrar Demissão',
        html: `
            <form id="form_demissao">
                <div class="mb-3">
                    <label class="form-label">Colaborador</label>
                    <input type="text" class="form-control" value="${nomeColaborador}" readonly>
                    <input type="hidden" name="colaborador_id" value="${colaboradorId}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Data da Demissão <span class="text-danger">*</span></label>
                    <input type="date" name="data_demissao" class="form-control" value="${new Date().toISOString().split('T')[0]}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tipo de Demissão <span class="text-danger">*</span></label>
                    <select name="tipo_demissao" class="form-select" required>
                        <option value="">Selecione...</option>
                        <option value="sem_justa_causa">Sem Justa Causa</option>
                        <option value="justa_causa">Justa Causa</option>
                        <option value="pedido_demissao">Pedido de Demissão</option>
                        <option value="aposentadoria">Aposentadoria</option>
                        <option value="falecimento">Falecimento</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Motivo</label>
                    <textarea name="motivo" class="form-control" rows="3" placeholder="Descreva o motivo da demissão..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Observações</label>
                    <textarea name="observacoes" class="form-control" rows="2" placeholder="Observações adicionais..."></textarea>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Registrar Demissão',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-light'
        },
        didOpen: () => {
            const form = document.getElementById('form_demissao');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                });
            }
        },
        preConfirm: () => {
            const form = document.getElementById('form_demissao');
            if (!form) return false;
            
            const formData = new FormData(form);
            
            return fetch('../api/demitir_colaborador.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Erro ao registrar demissão');
                }
                return data;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                text: 'Demissão registrada com sucesso!',
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            }).then(() => {
                location.reload();
            });
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
