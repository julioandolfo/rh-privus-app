<?php
/**
 * CRUD de Promoções - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

// Verifica permissão
if (!check_permission('ADMIN') && !check_permission('RH')) {
    redirect('dashboard.php', 'Você não tem permissão para acessar esta página.', 'error');
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $colaborador_id = (int)($_POST['colaborador_id'] ?? 0);
        $salario_anterior = str_replace(['.', ','], ['', '.'], $_POST['salario_anterior'] ?? '0');
        $salario_novo = str_replace(['.', ','], ['', '.'], $_POST['salario_novo'] ?? '0');
        $motivo = sanitize($_POST['motivo'] ?? '');
        $data_promocao = $_POST['data_promocao'] ?? date('Y-m-d');
        $observacoes = sanitize($_POST['observacoes'] ?? '');
        
        if (empty($colaborador_id) || empty($salario_novo) || empty($motivo)) {
            redirect('promocoes.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        try {
            // Atualiza salário do colaborador
            $stmt = $pdo->prepare("UPDATE colaboradores SET salario = ? WHERE id = ?");
            $stmt->execute([$salario_novo, $colaborador_id]);
            
            // Registra promoção
            $stmt = $pdo->prepare("
                INSERT INTO promocoes (colaborador_id, salario_anterior, salario_novo, motivo, data_promocao, usuario_id, observacoes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$colaborador_id, $salario_anterior, $salario_novo, $motivo, $data_promocao, $usuario['id'], $observacoes]);
            
            $promocao_id = $pdo->lastInsertId();
            
            // Envia email de promoção se template estiver ativo
            require_once __DIR__ . '/../includes/email_templates.php';
            enviar_email_nova_promocao($promocao_id);
            
            redirect('promocoes.php', 'Promoção registrada com sucesso!');
        } catch (PDOException $e) {
            redirect('promocoes.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca promoções
$where = '';
$params = [];
if ($usuario['role'] !== 'ADMIN') {
    $where = "WHERE c.empresa_id = ?";
    $params[] = $usuario['empresa_id'];
}

$stmt = $pdo->prepare("
    SELECT p.*, c.nome_completo as colaborador_nome, c.salario as salario_atual,
           e.nome_fantasia as empresa_nome, u.nome as usuario_nome
    FROM promocoes p
    INNER JOIN colaboradores c ON p.colaborador_id = c.id
    LEFT JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN usuarios u ON p.usuario_id = u.id
    $where
    ORDER BY p.data_promocao DESC, p.created_at DESC
");
$stmt->execute($params);
$promocoes = $stmt->fetchAll();

// Busca colaboradores para o select
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome_completo, salario, empresa_id FROM colaboradores WHERE status = 'ativo' ORDER BY nome_completo");
} else {
    $stmt = $pdo->prepare("SELECT id, nome_completo, salario, empresa_id FROM colaboradores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_completo");
    $stmt->execute([$usuario['empresa_id']]);
}
$colaboradores = $stmt->fetchAll();

$page_title = 'Promoções';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Promoções</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="colaboradores.php" class="text-muted text-hover-primary">Colaboradores</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Promoções</li>
            </ul>
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
                        <input type="text" data-kt-promocao-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar promoções" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end" data-kt-promocao-table-toolbar="base">
                        <!--begin::Add promoção-->
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_promocao">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Nova Promoção
                        </button>
                        <!--end::Add promoção-->
                    </div>
                    <!--end::Toolbar-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_promocoes_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <th class="min-w-200px">Colaborador</th>
                            <th class="min-w-150px">Empresa</th>
                            <th class="min-w-120px">Salário Anterior</th>
                            <th class="min-w-120px">Salário Novo</th>
                            <th class="min-w-100px">Data</th>
                            <th class="min-w-200px">Motivo</th>
                            <th class="min-w-150px">Registrado por</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($promocoes as $promocao): ?>
                        <tr>
                            <td><?= $promocao['id'] ?></td>
                            <td>
                                <a href="colaborador_view.php?id=<?= $promocao['colaborador_id'] ?>" class="text-gray-800 text-hover-primary mb-1">
                                    <?= htmlspecialchars($promocao['colaborador_nome']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($promocao['empresa_nome'] ?? '-') ?></td>
                            <td>R$ <?= number_format($promocao['salario_anterior'], 2, ',', '.') ?></td>
                            <td>
                                <span class="text-success fw-bold">R$ <?= number_format($promocao['salario_novo'], 2, ',', '.') ?></span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($promocao['data_promocao'])) ?></td>
                            <td><?= htmlspecialchars($promocao['motivo']) ?></td>
                            <td><?= htmlspecialchars($promocao['usuario_nome'] ?? '-') ?></td>
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

<!--begin::Modal - Promoção-->
<div class="modal fade" id="kt_modal_promocao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_promocao_header">
                <h2 class="fw-bold">Nova Promoção</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_promocao_form" method="POST" class="form">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Colaborador</label>
                            <select name="colaborador_id" id="colaborador_id" class="form-select form-select-solid" required>
                                <option value="">Selecione o colaborador...</option>
                                <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= $colab['id'] ?>" data-salario="<?= $colab['salario'] ?? 0 ?>">
                                    <?= htmlspecialchars($colab['nome_completo']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Salário Anterior</label>
                            <input type="text" name="salario_anterior" id="salario_anterior" class="form-control form-control-solid" readonly />
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Novo Salário</label>
                            <input type="text" name="salario_novo" id="salario_novo" class="form-control form-control-solid" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Data da Promoção</label>
                            <input type="date" name="data_promocao" class="form-control form-control-solid" value="<?= date('Y-m-d') ?>" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Motivo</label>
                            <textarea name="motivo" class="form-control form-control-solid" rows="3" required></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Observações</label>
                            <textarea name="observacoes" class="form-control form-control-solid" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
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
<!--end::Modal-->

<script>
// Máscara para salário - aplica quando jQuery e mask estiverem prontos
function aplicarMascarasSalario() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
        jQuery('#salario_anterior').mask('#.##0,00', {reverse: true});
        jQuery('#salario_novo').mask('#.##0,00', {reverse: true});
    } else {
        setTimeout(aplicarMascarasSalario, 100);
    }
}

// Carrega salário atual ao selecionar colaborador
document.getElementById('colaborador_id')?.addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const salario = parseFloat(option.dataset.salario || 0);
    const salarioAnterior = document.getElementById('salario_anterior');
    const salarioNovo = document.getElementById('salario_novo');
    
    if (salarioAnterior) {
        const valorFormatado = salario.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        salarioAnterior.value = valorFormatado;
        // Reaplica máscara após definir valor
        if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
            jQuery('#salario_anterior').trigger('input');
        }
    }
    if (salarioNovo) {
        salarioNovo.value = '';
    }
});

// Aplica máscaras quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    aplicarMascarasSalario();
});

// Também aplica quando o modal for aberto
document.getElementById('kt_modal_promocao')?.addEventListener('shown.bs.modal', function() {
    aplicarMascarasSalario();
});

// DataTables
var KTPromocoesList = function() {
    var initDatatable = function() {
        const table = document.getElementById('kt_promocoes_table');
        if (!table) return;
        
        const datatable = $(table).DataTable({
            "info": true,
            "order": [],
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            }
        });
        
        // Filtro de busca
        const filterSearch = document.querySelector('[data-kt-promocao-table-filter="search"]');
        if (filterSearch) {
            filterSearch.addEventListener('keyup', function(e) {
                datatable.search(e.target.value).draw();
            });
        }
    };
    
    return {
        init: function() {
            initDatatable();
        }
    };
}();

// Inicializa quando jQuery e DataTables estiverem prontos
function waitForDependencies() {
    if (typeof jQuery !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
        KTPromocoesList.init();
    } else {
        setTimeout(waitForDependencies, 100);
    }
}
waitForDependencies();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

