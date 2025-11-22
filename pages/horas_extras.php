<?php
/**
 * CRUD de Horas Extras - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('horas_extras.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $colaborador_id = (int)($_POST['colaborador_id'] ?? 0);
        $data_trabalho = $_POST['data_trabalho'] ?? date('Y-m-d');
        $quantidade_horas = str_replace(',', '.', $_POST['quantidade_horas'] ?? '0');
        $observacoes = sanitize($_POST['observacoes'] ?? '');
        
        if (empty($colaborador_id) || empty($quantidade_horas) || $quantidade_horas <= 0) {
            redirect('horas_extras.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        try {
            // Busca dados do colaborador e empresa
            $stmt = $pdo->prepare("
                SELECT c.salario, c.empresa_id, e.percentual_hora_extra
                FROM colaboradores c
                LEFT JOIN empresas e ON c.empresa_id = e.id
                WHERE c.id = ?
            ");
            $stmt->execute([$colaborador_id]);
            $colab_data = $stmt->fetch();
            
            if (!$colab_data || !$colab_data['salario']) {
                redirect('horas_extras.php', 'Colaborador não encontrado ou sem salário cadastrado!', 'error');
            }
            
            // Calcula valor da hora normal (assumindo 220 horas/mês)
            $valor_hora = $colab_data['salario'] / 220;
            $percentual_adicional = $colab_data['percentual_hora_extra'] ?? 50.00;
            
            // Calcula valor total da hora extra
            $valor_hora_extra = $valor_hora * (1 + ($percentual_adicional / 100));
            $valor_total = $valor_hora_extra * $quantidade_horas;
            
            // Insere hora extra
            $stmt = $pdo->prepare("
                INSERT INTO horas_extras (colaborador_id, data_trabalho, quantidade_horas, valor_hora, percentual_adicional, valor_total, observacoes, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $colaborador_id, $data_trabalho, $quantidade_horas, $valor_hora, 
                $percentual_adicional, $valor_total, $observacoes, $usuario['id']
            ]);
            
            redirect('horas_extras.php', 'Hora extra cadastrada com sucesso!');
        } catch (PDOException $e) {
            redirect('horas_extras.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM horas_extras WHERE id = ?");
            $stmt->execute([$id]);
            redirect('horas_extras.php', 'Hora extra excluída com sucesso!');
        } catch (PDOException $e) {
            redirect('horas_extras.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca horas extras
$where = '';
$params = [];
if ($usuario['role'] !== 'ADMIN') {
    $where = "WHERE c.empresa_id = ?";
    $params[] = $usuario['empresa_id'];
}

$stmt = $pdo->prepare("
    SELECT h.*, c.nome_completo as colaborador_nome, c.empresa_id,
           e.nome_fantasia as empresa_nome, u.nome as usuario_nome
    FROM horas_extras h
    INNER JOIN colaboradores c ON h.colaborador_id = c.id
    LEFT JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN usuarios u ON h.usuario_id = u.id
    $where
    ORDER BY h.data_trabalho DESC, h.created_at DESC
");
$stmt->execute($params);
$horas_extras = $stmt->fetchAll();

// Busca colaboradores para o select
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome_completo, salario, empresa_id FROM colaboradores WHERE status = 'ativo' AND salario IS NOT NULL ORDER BY nome_completo");
} else {
    $stmt = $pdo->prepare("SELECT id, nome_completo, salario, empresa_id FROM colaboradores WHERE empresa_id = ? AND status = 'ativo' AND salario IS NOT NULL ORDER BY nome_completo");
    $stmt->execute([$usuario['empresa_id']]);
}
$colaboradores = $stmt->fetchAll();

// Busca percentuais das empresas para cálculo
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, percentual_hora_extra FROM empresas");
} else {
    $stmt = $pdo->prepare("SELECT id, percentual_hora_extra FROM empresas WHERE id = ?");
    $stmt->execute([$usuario['empresa_id']]);
}
$empresas_percentual = $stmt->fetchAll();

$page_title = 'Horas Extras';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Horas Extras</h1>
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
                <li class="breadcrumb-item text-gray-900">Horas Extras</li>
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
                        <input type="text" data-kt-horaextra-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar horas extras" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end" data-kt-horaextra-table-toolbar="base">
                        <!--begin::Add hora extra-->
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_horaextra">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Nova Hora Extra
                        </button>
                        <!--end::Add hora extra-->
                    </div>
                    <!--end::Toolbar-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_horas_extras_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <th class="min-w-200px">Colaborador</th>
                            <th class="min-w-150px">Empresa</th>
                            <th class="min-w-100px">Data</th>
                            <th class="min-w-100px">Quantidade</th>
                            <th class="min-w-120px">Valor Hora</th>
                            <th class="min-w-100px">% Adicional</th>
                            <th class="min-w-120px">Valor Total</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($horas_extras as $he): ?>
                        <tr>
                            <td><?= $he['id'] ?></td>
                            <td>
                                <a href="colaborador_view.php?id=<?= $he['colaborador_id'] ?>" class="text-gray-800 text-hover-primary mb-1">
                                    <?= htmlspecialchars($he['colaborador_nome']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($he['empresa_nome'] ?? '-') ?></td>
                            <td><?= date('d/m/Y', strtotime($he['data_trabalho'])) ?></td>
                            <td><?= number_format($he['quantidade_horas'], 2, ',', '.') ?>h</td>
                            <td>R$ <?= number_format($he['valor_hora'], 2, ',', '.') ?></td>
                            <td><?= number_format($he['percentual_adicional'], 2, ',', '.') ?>%</td>
                            <td>
                                <span class="text-success fw-bold">R$ <?= number_format($he['valor_total'], 2, ',', '.') ?></span>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-light-danger" onclick="deletarHoraExtra(<?= $he['id'] ?>)">
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
                <!--end::Table-->
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Hora Extra-->
<div class="modal fade" id="kt_modal_horaextra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_horaextra_header">
                <h2 class="fw-bold">Nova Hora Extra</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_horaextra_form" method="POST" class="form">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Colaborador</label>
                            <select name="colaborador_id" id="colaborador_id" class="form-select form-select-solid" required>
                                <option value="">Selecione o colaborador...</option>
                                <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= $colab['id'] ?>" data-salario="<?= $colab['salario'] ?? 0 ?>" data-empresa="<?= $colab['empresa_id'] ?>">
                                    <?= htmlspecialchars($colab['nome_completo']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Data do Trabalho</label>
                            <input type="date" name="data_trabalho" class="form-control form-control-solid" value="<?= date('Y-m-d') ?>" required />
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Quantidade de Horas</label>
                            <input type="text" name="quantidade_horas" id="quantidade_horas" class="form-control form-control-solid" placeholder="0,00" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <div class="card card-flush bg-light-primary">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <i class="ki-duotone ki-calculator fs-2hx text-primary me-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-gray-600 fw-semibold">Valor Total Calculado:</span>
                                                <span class="text-primary fw-bold fs-2" id="valor_total_calculado">R$ 0,00</span>
                                            </div>
                                            <div class="text-gray-500 fs-7" id="detalhes_calculo">
                                                Selecione um colaborador e informe a quantidade de horas
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
// Dados dos colaboradores e empresas para cálculo
const colaboradoresData = {
    <?php foreach ($colaboradores as $colab): ?>
    <?= $colab['id'] ?>: {
        salario: <?= $colab['salario'] ?? 0 ?>,
        empresa_id: <?= $colab['empresa_id'] ?>
    },
    <?php endforeach; ?>
};

const empresasPercentual = {
    <?php foreach ($empresas_percentual as $emp): ?>
    <?= $emp['id'] ?>: <?= $emp['percentual_hora_extra'] ?? 50.00 ?>,
    <?php endforeach; ?>
};

// Função para calcular valor total
function calcularValorTotal() {
    const colaboradorId = document.getElementById('colaborador_id')?.value;
    const quantidadeHoras = parseFloat(document.getElementById('quantidade_horas')?.value.replace(',', '.') || 0);
    
    const valorTotalEl = document.getElementById('valor_total_calculado');
    const detalhesEl = document.getElementById('detalhes_calculo');
    
    if (!colaboradorId || !colaboradoresData[colaboradorId]) {
        valorTotalEl.textContent = 'R$ 0,00';
        detalhesEl.textContent = 'Selecione um colaborador e informe a quantidade de horas';
        return;
    }
    
    const colabData = colaboradoresData[colaboradorId];
    const salario = colabData.salario;
    const percentual = empresasPercentual[colabData.empresa_id] || 50.00;
    
    if (!salario || salario <= 0) {
        valorTotalEl.textContent = 'R$ 0,00';
        detalhesEl.textContent = 'Colaborador sem salário cadastrado';
        return;
    }
    
    if (quantidadeHoras <= 0) {
        valorTotalEl.textContent = 'R$ 0,00';
        detalhesEl.textContent = 'Informe a quantidade de horas';
        return;
    }
    
    // Calcula valor da hora normal (220 horas/mês)
    const valorHora = salario / 220;
    
    // Calcula valor da hora extra com percentual adicional
    const valorHoraExtra = valorHora * (1 + (percentual / 100));
    
    // Calcula valor total
    const valorTotal = valorHoraExtra * quantidadeHoras;
    
    // Atualiza exibição
    valorTotalEl.textContent = 'R$ ' + valorTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Atualiza detalhes
    detalhesEl.innerHTML = `
        <div class="d-flex flex-column gap-1">
            <span>Salário: R$ ${salario.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
            <span>Valor Hora Normal: R$ ${valorHora.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
            <span>Percentual Adicional: ${percentual.toLocaleString('pt-BR', {minimumFractionDigits: 2})}%</span>
            <span>Valor Hora Extra: R$ ${valorHoraExtra.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
            <span class="fw-bold mt-1">${quantidadeHoras.toLocaleString('pt-BR', {minimumFractionDigits: 2})}h × R$ ${valorHoraExtra.toLocaleString('pt-BR', {minimumFractionDigits: 2})} = R$ ${valorTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
        </div>
    `;
}

// Event listeners
document.getElementById('colaborador_id')?.addEventListener('change', calcularValorTotal);
document.getElementById('quantidade_horas')?.addEventListener('input', calcularValorTotal);
document.getElementById('quantidade_horas')?.addEventListener('keyup', calcularValorTotal);

// Máscara para quantidade de horas
if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
    jQuery('#quantidade_horas').mask('#0,00', {reverse: true});
    
    // Recalcula quando a máscara é aplicada
    jQuery('#quantidade_horas').on('input', function() {
        calcularValorTotal();
    });
}

// DataTables
var KTHorasExtrasList = function() {
    var initDatatable = function() {
        const table = document.getElementById('kt_horas_extras_table');
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
        const filterSearch = document.querySelector('[data-kt-horaextra-table-filter="search"]');
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

// Deletar hora extra
function deletarHoraExtra(id) {
    Swal.fire({
        text: "Tem certeza que deseja excluir esta hora extra?",
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

// Inicializa quando jQuery e DataTables estiverem prontos
function waitForDependencies() {
    if (typeof jQuery !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
        KTHorasExtrasList.init();
    } else {
        setTimeout(waitForDependencies, 100);
    }
}
waitForDependencies();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

