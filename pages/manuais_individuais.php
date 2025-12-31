<?php
/**
 * Lista de Manuais Individuais - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('manuais_individuais.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca todos os manuais
$sql = "
    SELECT m.*, 
           u.nome as criado_por_nome,
           COUNT(DISTINCT mc.colaborador_id) as total_colaboradores
    FROM manuais_individuais m
    LEFT JOIN usuarios u ON m.created_by = u.id
    LEFT JOIN manuais_individuais_colaboradores mc ON m.id = mc.manual_id
    GROUP BY m.id
    ORDER BY m.created_at DESC
";

$stmt = $pdo->query($sql);
$manuais = $stmt->fetchAll();

$page_title = 'Manuais Individuais';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Manuais Individuais</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Manuais Individuais</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <a href="manual_individuais_add.php" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i>
                Novo Manual
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?= get_session_alert() ?>
        
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
                        <input type="text" data-kt-manual-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar manuais" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--end::Card title-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_manuais_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <th class="min-w-200px">Título</th>
                            <th class="min-w-300px">Descrição</th>
                            <th class="min-w-100px">Colaboradores</th>
                            <th class="min-w-100px">Criado por</th>
                            <th class="min-w-100px">Status</th>
                            <th class="min-w-100px">Data Criação</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($manuais as $manual): ?>
                        <tr>
                            <td><?= $manual['id'] ?></td>
                            <td>
                                <a href="manual_individuais_view.php?id=<?= $manual['id'] ?>" class="text-gray-800 text-hover-primary mb-1">
                                    <?= htmlspecialchars($manual['titulo']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="text-gray-600">
                                    <?= htmlspecialchars(mb_substr($manual['descricao'] ?? '', 0, 100)) ?>
                                    <?= mb_strlen($manual['descricao'] ?? '') > 100 ? '...' : '' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-light-info">
                                    <?= $manual['total_colaboradores'] ?> colaborador<?= $manual['total_colaboradores'] != 1 ? 'es' : '' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($manual['criado_por_nome'] ?? 'Sistema') ?></td>
                            <td>
                                <?php
                                $badge_class = $manual['status'] === 'ativo' ? 'badge-light-success' : 'badge-light-secondary';
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= ucfirst($manual['status']) ?></span>
                            </td>
                            <td><?= formatar_data($manual['created_at']) ?></td>
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
                                        <a href="manual_individuais_view.php?id=<?= $manual['id'] ?>" class="menu-link px-3">Visualizar</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="manual_individuais_edit.php?id=<?= $manual['id'] ?>" class="menu-link px-3">Editar</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3 text-danger" onclick="deletarManual(<?= $manual['id'] ?>, '<?= htmlspecialchars($manual['titulo'], ENT_QUOTES) ?>')">Deletar</a>
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

<script>
"use strict";
var KTManuaisList = function() {
    var t, n;
    
    return {
        init: function() {
            n = document.querySelector("#kt_manuais_table");
            
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
                document.querySelector('[data-kt-manual-table-filter="search"]').addEventListener("keyup", function(e) {
                    t.search(e.target.value).draw();
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
        KTManuaisList.init();
    });
})();

// Função para deletar manual
function deletarManual(manualId, titulo) {
    Swal.fire({
        title: 'Deletar Manual?',
        html: `<p>Você está prestes a deletar o manual <strong>${titulo}</strong>.</p><p class="text-danger">Esta ação não pode ser desfeita!</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, deletar!',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-light'
        },
        preConfirm: () => {
            const formData = new FormData();
            formData.append('manual_id', manualId);
            
            return fetch('../api/manuais_individuais/deletar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Erro ao deletar manual');
                }
                return data;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                text: 'Manual deletado com sucesso!',
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
