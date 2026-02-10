<?php
/**
 * CRUD de Promoções - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/select_colaborador.php';

require_page_permission('promocoes.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Extrai ID do colaborador (formato pode ser 'c_123' ou '123')
        $colaborador_id_raw = $_POST['colaborador_id'] ?? '';
        if (strpos($colaborador_id_raw, 'c_') === 0) {
            $colaborador_id = (int)substr($colaborador_id_raw, 2); // Remove 'c_' e converte para int
        } else {
            $colaborador_id = (int)$colaborador_id_raw;
        }
        
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
            
            // Envia notificação push para o colaborador
            require_once __DIR__ . '/../includes/push_notifications.php';
            $push_result = enviar_push_colaborador(
                $colaborador_id,
                'Parabéns pela Promoção! 🎉',
                'Você recebeu uma promoção. Seu novo salário é R$ ' . number_format($salario_novo, 2, ',', '.') . '. Confira os detalhes agora!',
                'pages/promocoes.php', // URL original para referência
                'promocao', // Tipo da notificação
                $promocao_id, // ID da promoção
                'promocao' // Tipo da referência
            );
            
            redirect('promocoes.php', 'Promoção registrada com sucesso!');
        } catch (PDOException $e) {
            redirect('promocoes.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $promocao_id = (int)($_POST['promocao_id'] ?? 0);
        
        if (empty($promocao_id)) {
            redirect('promocoes.php', 'ID da promoção não informado!', 'error');
        }
        
        try {
            // Busca dados da promoção
            $stmt = $pdo->prepare("
                SELECT p.*, c.salario as salario_atual 
                FROM promocoes p
                INNER JOIN colaboradores c ON p.colaborador_id = c.id
                WHERE p.id = ?
            ");
            $stmt->execute([$promocao_id]);
            $promocao = $stmt->fetch();
            
            if (!$promocao) {
                redirect('promocoes.php', 'Promoção não encontrada!', 'error');
            }
            
            // Verifica se o salário atual do colaborador corresponde ao salário_novo da promoção
            // Se sim, reverte para o salário anterior
            if (abs($promocao['salario_atual'] - $promocao['salario_novo']) < 0.01) {
                // Reverte salário para o valor anterior
                $stmt = $pdo->prepare("UPDATE colaboradores SET salario = ? WHERE id = ?");
                $stmt->execute([$promocao['salario_anterior'], $promocao['colaborador_id']]);
            }
            
            // Deleta a promoção
            $stmt = $pdo->prepare("DELETE FROM promocoes WHERE id = ?");
            $stmt->execute([$promocao_id]);
            
            redirect('promocoes.php', 'Promoção deletada e salário revertido com sucesso!');
        } catch (PDOException $e) {
            redirect('promocoes.php', 'Erro ao deletar: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca promoções
$where = '';
$params = [];
if ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where = "WHERE c.empresa_id IN ($placeholders)";
        $params = $usuario['empresas_ids'];
    } else {
        // Fallback para compatibilidade
        $where = "WHERE c.empresa_id = ?";
        $params[] = $usuario['empresa_id'] ?? 0;
    }
} elseif ($usuario['role'] !== 'ADMIN') {
    $where = "WHERE c.empresa_id = ?";
    $params[] = $usuario['empresa_id'] ?? 0;
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

// Busca colaboradores para o select usando função padronizada
$colaboradores_raw = get_colaboradores_disponiveis($pdo, $usuario);

// Adiciona dados extras necessários (salario, empresa_id)
$colaboradores = [];
foreach ($colaboradores_raw as $colab) {
    // A função retorna IDs no formato 'c_123' ou 'u_456', então precisamos extrair o ID real
    // Para promoções, só usamos colaboradores (tipo 'c_')
    if ($colab['tipo'] !== 'colaborador' || empty($colab['colaborador_id'])) {
        continue; // Pula usuários sem colaborador vinculado
    }
    
    $colaborador_id = $colab['colaborador_id'];
    $stmt = $pdo->prepare("SELECT salario, empresa_id FROM colaboradores WHERE id = ?");
    $stmt->execute([$colaborador_id]);
    $colab_data = $stmt->fetch();
    if ($colab_data) {
        $colaboradores[] = array_merge($colab, [
            'salario' => $colab_data['salario'],
            'empresa_id' => $colab_data['empresa_id']
        ]);
    }
}

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
                            <th class="text-end min-w-100px">Ações</th>
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
                            <td class="text-end">
                                <?php if (has_role(['ADMIN', 'RH'])): ?>
                                <button type="button" class="btn btn-sm btn-light-danger" onclick="deletarPromocao(<?= $promocao['id'] ?>, '<?= htmlspecialchars(addslashes($promocao['colaborador_nome'])) ?>')" title="Deletar/Reverter Promoção">
                                    <i class="ki-duotone ki-trash fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                        <span class="path5"></span>
                                    </i>
                                </button>
                                <?php endif; ?>
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
                            <?= render_select_colaborador('colaborador_id', 'colaborador_id', null, $colaboradores, true) ?>
                            <?php if (empty($colaboradores)): ?>
                            <div class="alert alert-warning mt-2">
                                <i class="ki-duotone ki-information-5 fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <strong>Atenção:</strong> Nenhum colaborador ativo encontrado.
                                <?php if ($usuario['role'] === 'RH'): ?>
                                    <br><small>Verifique se há colaboradores ativos cadastrados na(s) empresa(s): <?= htmlspecialchars($usuario['empresa_nome'] ?? 'N/A') ?></small>
                                <?php elseif ($usuario['role'] === 'GESTOR'): ?>
                                    <br><small>Verifique se há colaboradores ativos no seu setor.</small>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <small class="text-muted"><?= count($colaboradores) ?> colaborador(es) disponível(is)</small>
                            <?php endif; ?>
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
function atualizarSalarioAnterior() {
    const select = document.getElementById('colaborador_id');
    if (!select) return;
    
    const option = select.options[select.selectedIndex];
    if (!option || !option.value) {
        document.getElementById('salario_anterior').value = '';
        return;
    }
    
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
}

// Listener para mudança no select
document.getElementById('colaborador_id')?.addEventListener('change', atualizarSalarioAnterior);

// Também atualiza quando Select2 mudar (evento do Select2) - aguarda jQuery
function initSelect2ChangeListener() {
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('change', '#colaborador_id', function() {
            atualizarSalarioAnterior();
        });
    } else {
        setTimeout(initSelect2ChangeListener, 100);
    }
}

// Aplica máscaras quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    aplicarMascarasSalario();
    initSelect2ChangeListener();
});

// Também aplica quando o modal for aberto
document.getElementById('kt_modal_promocao')?.addEventListener('shown.bs.modal', function() {
    aplicarMascarasSalario();
    
    // Inicializa Select2 quando o modal for aberto
    setTimeout(function() {
        console.log('Modal de promoção aberto - inicializando Select2...');
        
        // Verifica se jQuery está disponível
        if (typeof jQuery === 'undefined') {
            console.error('jQuery não está disponível');
            return;
        }
        
        var $select = jQuery('#colaborador_id');
        console.log('Select encontrado:', $select.length);
        
        // Verifica se Select2 está carregado
        if (typeof jQuery.fn.select2 === 'undefined') {
            console.error('Select2 não está carregado');
            
            // Tenta carregar Select2
            if (!jQuery('script[src*="select2"]').length) {
                console.log('Carregando Select2...');
                jQuery.getScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', function() {
                    console.log('Select2 carregado! Inicializando...');
                    initSelect2OnModal();
                });
            }
            return;
        }
        
        initSelect2OnModal();
        
        function initSelect2OnModal() {
            var $select = jQuery('#colaborador_id');
            
            // Se já foi inicializado, destrói primeiro
            if ($select.hasClass('select2-hidden-accessible')) {
                console.log('Destruindo Select2 existente...');
                $select.select2('destroy');
            }
            
            console.log('Inicializando Select2...');
            
            $select.select2({
                placeholder: 'Selecione um colaborador...',
                allowClear: true,
                width: '100%',
                dropdownParent: jQuery('#kt_modal_promocao'), // IMPORTANTE: define o parent como o modal
                minimumResultsForSearch: 0,
                language: {
                    noResults: function() { return 'Nenhum colaborador encontrado'; },
                    searching: function() { return 'Buscando...'; }
                },
                templateResult: function(data) {
                    if (!data.id) return data.text;
                    if (!data.element) return data.text;
                    
                    var $option = jQuery(data.element);
                    var foto = $option.attr('data-foto') || null;
                    var nome = $option.attr('data-nome') || data.text || '';
                    
                    var html = '<span style="display: flex; align-items: center;">';
                    if (foto) {
                        html += '<img src="' + foto + '" class="rounded-circle me-2" width="32" height="32" style="object-fit: cover;" onerror="this.src=\'../assets/media/avatars/blank.png\'" />';
                    } else {
                        var inicial = nome.charAt(0).toUpperCase();
                        html += '<span class="symbol symbol-circle symbol-32px me-2"><span class="symbol-label fs-6 fw-semibold bg-primary text-white">' + inicial + '</span></span>';
                    }
                    html += '<span>' + nome + '</span></span>';
                    return jQuery(html);
                },
                templateSelection: function(data) {
                    if (!data.id) return data.text;
                    if (!data.element) return data.text;
                    
                    var $option = jQuery(data.element);
                    var foto = $option.attr('data-foto') || null;
                    var nome = $option.attr('data-nome') || data.text || '';
                    
                    var html = '<span style="display: flex; align-items: center;">';
                    if (foto) {
                        html += '<img src="' + foto + '" class="rounded-circle me-2" width="24" height="24" style="object-fit: cover;" onerror="this.src=\'../assets/media/avatars/blank.png\'" />';
                    } else {
                        var inicial = nome.charAt(0).toUpperCase();
                        html += '<span class="symbol symbol-circle symbol-24px me-2"><span class="symbol-label fs-7 fw-semibold bg-primary text-white">' + inicial + '</span></span>';
                    }
                    html += '<span>' + nome + '</span></span>';
                    return jQuery(html);
                }
            });
            
            console.log('Select2 inicializado com sucesso!');
            
            // Adiciona listener para mudança no Select2
            $select.on('change', function() {
                atualizarSalarioAnterior();
            });
        }
    }, 350);
});

// DataTables
var KTPromocoesList = function() {
    var initDatatable = function() {
        const table = document.getElementById('kt_promocoes_table');
        if (!table) return;
        
        const datatable = jQuery(table).DataTable({
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
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.DataTable !== 'undefined') {
        KTPromocoesList.init();
    } else {
        setTimeout(waitForDependencies, 100);
    }
}
waitForDependencies();
</script>

<!--begin::SweetAlert2-->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Função para deletar/reverter promoção
function deletarPromocao(promocaoId, colaboradorNome) {
    if (typeof Swal === 'undefined') {
        if (confirm('Tem certeza que deseja deletar e reverter esta promoção? O salário será revertido para o valor anterior.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'promocoes.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'promocao_id';
            idInput.value = promocaoId;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
        return;
    }
    
    Swal.fire({
        title: 'Tem certeza?',
        html: 'Você está prestes a <strong>deletar e reverter</strong> a promoção do colaborador <strong>' + colaboradorNome + '</strong>.<br><br>O salário será revertido para o valor anterior e o registro será removido.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, deletar e reverter!',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn fw-bold btn-danger',
            cancelButton: 'btn fw-bold btn-light'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Cria formulário para enviar POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'promocoes.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'promocao_id';
            idInput.value = promocaoId;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<!--begin::Select2 CSS-->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    /* Ajusta a altura do Select2 */
    .select2-container .select2-selection--single {
        height: 44px !important;
        padding: 0.75rem 1rem !important;
        display: flex !important;
        align-items: center !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 44px !important;
        padding-left: 0 !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px !important;
    }
    
    .select2-container .select2-selection--single .select2-selection__rendered {
        display: flex !important;
        align-items: center !important;
    }
</style>
<!--end::Select2 CSS-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

