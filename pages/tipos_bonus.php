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
        $tipo_valor = $_POST['tipo_valor'] ?? 'variavel';
        $valor_fixo = !empty($_POST['valor_fixo']) ? str_replace(['.', ','], ['', '.'], $_POST['valor_fixo']) : null;
        $ocorrencias_desconto = $_POST['ocorrencias_desconto'] ?? [];
        
        if (empty($nome)) {
            redirect('tipos_bonus.php', 'Nome do tipo de bônus é obrigatório!', 'error');
        }
        
        // Validação: se tipo_valor é 'fixo', valor_fixo é obrigatório
        if ($tipo_valor === 'fixo' && (empty($valor_fixo) || $valor_fixo <= 0)) {
            redirect('tipos_bonus.php', 'Quando o tipo é "Valor Fixo", o valor fixo é obrigatório!', 'error');
        }
        
        // Se não for fixo, limpa valor_fixo
        if ($tipo_valor !== 'fixo') {
            $valor_fixo = null;
        }
        
        try {
            $pdo->beginTransaction();
            
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO tipos_bonus (nome, descricao, status, tipo_valor, valor_fixo) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $descricao, $status, $tipo_valor, $valor_fixo]);
                $tipo_bonus_id = $pdo->lastInsertId();
            } else {
                $id = (int)$_POST['id'];
                $tipo_bonus_id = $id;
                $stmt = $pdo->prepare("UPDATE tipos_bonus SET nome = ?, descricao = ?, status = ?, tipo_valor = ?, valor_fixo = ? WHERE id = ?");
                $stmt->execute([$nome, $descricao, $status, $tipo_valor, $valor_fixo, $id]);
                
                // Remove configurações antigas de ocorrências
                $stmt = $pdo->prepare("DELETE FROM tipos_bonus_ocorrencias WHERE tipo_bonus_id = ?");
                $stmt->execute([$id]);
            }
            
            // Salva configurações de desconto por ocorrências
            if (!empty($ocorrencias_desconto) && is_array($ocorrencias_desconto)) {
                foreach ($ocorrencias_desconto as $ocorrencia_config) {
                    if (empty($ocorrencia_config['tipo_ocorrencia_id'])) continue;
                    
                    $tipo_ocorrencia_id = (int)$ocorrencia_config['tipo_ocorrencia_id'];
                    $tipo_desconto = $ocorrencia_config['tipo_desconto'] ?? 'proporcional';
                    $valor_desconto = !empty($ocorrencia_config['valor_desconto']) 
                        ? str_replace(['.', ','], ['', '.'], $ocorrencia_config['valor_desconto']) 
                        : null;
                    $desconta_apenas_aprovadas = isset($ocorrencia_config['desconta_apenas_aprovadas']) && $ocorrencia_config['desconta_apenas_aprovadas'] == '1';
                    $desconta_banco_horas = isset($ocorrencia_config['desconta_banco_horas']) && $ocorrencia_config['desconta_banco_horas'] == '1';
                    $periodo_dias = !empty($ocorrencia_config['periodo_dias']) ? (int)$ocorrencia_config['periodo_dias'] : null;
                    $verificar_periodo_anterior = isset($ocorrencia_config['verificar_periodo_anterior']) && $ocorrencia_config['verificar_periodo_anterior'] == '1';
                    $periodo_anterior_meses = !empty($ocorrencia_config['periodo_anterior_meses']) ? (int)$ocorrencia_config['periodo_anterior_meses'] : 1;
                    
                    // Se tipo_desconto é 'total', não precisa de valor_desconto
                    if ($tipo_desconto === 'total') {
                        $valor_desconto = null;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO tipos_bonus_ocorrencias 
                        (tipo_bonus_id, tipo_ocorrencia_id, tipo_desconto, valor_desconto, desconta_apenas_aprovadas, desconta_banco_horas, periodo_dias, verificar_periodo_anterior, periodo_anterior_meses, ativo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([
                        $tipo_bonus_id,
                        $tipo_ocorrencia_id,
                        $tipo_desconto,
                        $valor_desconto,
                        $desconta_apenas_aprovadas ? 1 : 0,
                        $desconta_banco_horas ? 1 : 0,
                        $periodo_dias,
                        $verificar_periodo_anterior ? 1 : 0,
                        $periodo_anterior_meses
                    ]);
                }
            }
            
            $pdo->commit();
            redirect('tipos_bonus.php', 'Tipo de bônus ' . ($action === 'add' ? 'cadastrado' : 'atualizado') . ' com sucesso!');
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
$stmt = $pdo->query("SELECT *, COALESCE(tipo_valor, 'variavel') as tipo_valor FROM tipos_bonus ORDER BY nome");
$tipos_bonus = $stmt->fetchAll();

// Buscar tipos de ocorrências para o formulário
$stmt = $pdo->query("SELECT * FROM tipos_ocorrencias WHERE status = 'ativo' ORDER BY categoria, nome");
$tipos_ocorrencias = $stmt->fetchAll();

// Buscar configurações de desconto por ocorrências para cada tipo de bônus
$configuracoes_desconto = [];
foreach ($tipos_bonus as $tipo) {
    $stmt = $pdo->prepare("
        SELECT tbo.*, to_ocorrencia.nome as tipo_ocorrencia_nome, to_ocorrencia.codigo as tipo_ocorrencia_codigo
        FROM tipos_bonus_ocorrencias tbo
        INNER JOIN tipos_ocorrencias to_ocorrencia ON tbo.tipo_ocorrencia_id = to_ocorrencia.id
        WHERE tbo.tipo_bonus_id = ? AND tbo.ativo = 1
    ");
    $stmt->execute([$tipo['id']]);
    $configuracoes_desconto[$tipo['id']] = $stmt->fetchAll();
}

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
                    <th class="min-w-120px">Tipo</th>
                    <th class="min-w-100px">Valor Fixo</th>
                    <th class="min-w-150px">Desconto por Ocorrências</th>
                    <th class="min-w-100px">Status</th>
                    <th class="text-end min-w-100px">Ações</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 fw-semibold">
                <?php foreach ($tipos_bonus as $tipo): 
                    $tipo_valor = $tipo['tipo_valor'] ?? 'variavel';
                    $tipo_labels = [
                        'fixo' => ['label' => 'Valor Fixo', 'badge' => 'primary'],
                        'informativo' => ['label' => 'Informativo', 'badge' => 'info'],
                        'variavel' => ['label' => 'Variável', 'badge' => 'success']
                    ];
                    $tipo_info = $tipo_labels[$tipo_valor] ?? $tipo_labels['variavel'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($tipo['nome']) ?></td>
                    <td><?= htmlspecialchars($tipo['descricao'] ?: '-') ?></td>
                    <td>
                        <span class="badge badge-light-<?= $tipo_info['badge'] ?>">
                            <?= $tipo_info['label'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($tipo_valor === 'fixo' && !empty($tipo['valor_fixo'])): ?>
                            <span class="fw-bold text-success">R$ <?= number_format($tipo['valor_fixo'], 2, ',', '.') ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $configs = $configuracoes_desconto[$tipo['id']] ?? [];
                        if (!empty($configs)): 
                        ?>
                            <div class="d-flex flex-column gap-1">
                                <?php foreach ($configs as $config): ?>
                                <span class="badge badge-light-info">
                                    <?= htmlspecialchars($config['tipo_ocorrencia_nome']) ?>
                                    <?php if ($config['tipo_desconto'] === 'total'): ?>
                                        <small class="text-danger fw-bold">(Valor Total)</small>
                                    <?php elseif ($config['tipo_desconto'] === 'fixo' && !empty($config['valor_desconto'])): ?>
                                        <small>(R$ <?= number_format($config['valor_desconto'], 2, ',', '.') ?>)</small>
                                    <?php elseif ($config['tipo_desconto'] === 'percentual' && !empty($config['valor_desconto'])): ?>
                                        <small>(<?= number_format($config['valor_desconto'], 2, ',', '.') ?>%)</small>
                                    <?php else: ?>
                                        <small>(Proporcional)</small>
                                    <?php endif; ?>
                                    <?php if (!empty($config['verificar_periodo_anterior'])): ?>
                                        <br><small class="text-warning">Período anterior: <?= $config['periodo_anterior_meses'] ?? 1 ?> mês(es)</small>
                                    <?php endif; ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-light-<?= $tipo['status'] === 'ativo' ? 'success' : 'secondary' ?>">
                            <?= ucfirst($tipo['status']) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-light-warning me-2" onclick="editarTipoBonus(<?= htmlspecialchars(json_encode(array_merge($tipo, ['configuracoes_desconto' => $configuracoes_desconto[$tipo['id']] ?? []]))) ?>)">
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
                        <label class="fw-semibold fs-6 mb-2">Tipo de Valor *</label>
                        <select name="tipo_valor" id="tipo_valor_bonus" class="form-select form-select-solid" required onchange="toggleValorFixo()">
                            <option value="variavel">Variável (usa valor do colaborador)</option>
                            <option value="fixo">Valor Fixo (usa valor definido aqui)</option>
                            <option value="informativo">Apenas Informativo (não soma no total)</option>
                        </select>
                        <small class="text-muted d-block mt-1">
                            <strong>Variável:</strong> O valor é definido individualmente para cada colaborador<br>
                            <strong>Valor Fixo:</strong> Todos os colaboradores recebem o mesmo valor definido aqui<br>
                            <strong>Informativo:</strong> Apenas para informação, não entra no cálculo do pagamento
                        </small>
                    </div>
                    
                    <div class="mb-7" id="campo_valor_fixo" style="display: none;">
                        <label class="fw-semibold fs-6 mb-2">Valor Fixo *</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" name="valor_fixo" id="valor_fixo_bonus" class="form-control form-control-solid" placeholder="0,00" />
                        </div>
                        <small class="text-muted d-block mt-1">Valor que será aplicado a todos os colaboradores com este tipo de bônus</small>
                    </div>
                    
                    <div class="mb-7">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="fw-semibold fs-6 mb-0">Desconto por Ocorrências</label>
                            <button type="button" class="btn btn-sm btn-primary" onclick="adicionarOcorrenciaDesconto()">
                                <i class="ki-duotone ki-plus fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Adicionar Tipo de Ocorrência
                            </button>
                        </div>
                        <small class="text-muted d-block mb-3">
                            Configure quais tipos de ocorrências descontam deste bônus e como será calculado o desconto.
                        </small>
                        <div id="ocorrencias_desconto_container" class="border rounded p-4">
                            <p class="text-muted mb-0">Nenhum tipo de ocorrência configurado</p>
                        </div>
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
    document.getElementById('tipo_valor_bonus').value = 'variavel';
    ocorrenciasDescontoConfig = [];
    renderizarOcorrenciasDesconto();
    toggleValorFixo();
    
    // Aplica máscaras quando o modal abrir
    setTimeout(() => {
        if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
            jQuery('#valor_fixo_bonus').mask('#.##0,00', {reverse: true});
        }
    }, 300);
}

function toggleValorFixo() {
    const tipoValor = document.getElementById('tipo_valor_bonus').value;
    const campoValorFixo = document.getElementById('campo_valor_fixo');
    const inputValorFixo = document.getElementById('valor_fixo_bonus');
    
    if (tipoValor === 'fixo') {
        campoValorFixo.style.display = 'block';
        inputValorFixo.required = true;
    } else {
        campoValorFixo.style.display = 'none';
        inputValorFixo.required = false;
        inputValorFixo.value = '';
    }
}


function editarTipoBonus(tipo) {
    document.getElementById('kt_modal_tipo_bonus_header').querySelector('h2').textContent = 'Editar Tipo de Bônus';
    document.getElementById('tipo_bonus_action').value = 'edit';
    document.getElementById('tipo_bonus_id').value = tipo.id;
    document.getElementById('nome_tipo_bonus').value = tipo.nome || '';
    document.getElementById('descricao_tipo_bonus').value = tipo.descricao || '';
    document.getElementById('status_tipo_bonus').value = tipo.status || 'ativo';
    
    const tipoValor = tipo.tipo_valor || 'variavel';
    document.getElementById('tipo_valor_bonus').value = tipoValor;
    
    if (tipo.valor_fixo) {
        const valorFormatado = parseFloat(tipo.valor_fixo).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        document.getElementById('valor_fixo_bonus').value = valorFormatado;
    } else {
        document.getElementById('valor_fixo_bonus').value = '';
    }
    
    // Carrega configurações de desconto por ocorrências
    ocorrenciasDescontoConfig = [];
    if (tipo.configuracoes_desconto && Array.isArray(tipo.configuracoes_desconto)) {
        tipo.configuracoes_desconto.forEach(config => {
            ocorrenciasDescontoConfig.push({
                tipo_ocorrencia_id: config.tipo_ocorrencia_id || '',
                tipo_desconto: config.tipo_desconto || 'proporcional',
                valor_desconto: config.valor_desconto || '',
                desconta_apenas_aprovadas: config.desconta_apenas_aprovadas == 1,
                desconta_banco_horas: config.desconta_banco_horas == 1,
                periodo_dias: config.periodo_dias || '',
                verificar_periodo_anterior: config.verificar_periodo_anterior == 1,
                periodo_anterior_meses: config.periodo_anterior_meses || 1
            });
        });
    }
    renderizarOcorrenciasDesconto();
    
    toggleValorFixo();
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_tipo_bonus'));
    modal.show();
    
    // Aplica máscaras
    setTimeout(() => {
        if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
            jQuery('#valor_fixo_bonus').mask('#.##0,00', {reverse: true});
        }
    }, 300);
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

// Variável global para armazenar ocorrências configuradas
let ocorrenciasDescontoConfig = [];

// Adicionar ocorrência de desconto
function adicionarOcorrenciaDesconto() {
    ocorrenciasDescontoConfig.push({
        tipo_ocorrencia_id: '',
        tipo_desconto: 'proporcional',
        valor_desconto: '',
        desconta_apenas_aprovadas: true,
        desconta_banco_horas: false,
        periodo_dias: '',
        verificar_periodo_anterior: false,
        periodo_anterior_meses: 1
    });
    renderizarOcorrenciasDesconto();
}

// Remover ocorrência de desconto
function removerOcorrenciaDesconto(index) {
    ocorrenciasDescontoConfig.splice(index, 1);
    renderizarOcorrenciasDesconto();
}

// Renderizar container de ocorrências
function renderizarOcorrenciasDesconto() {
    const container = document.getElementById('ocorrencias_desconto_container');
    if (!container) return;
    
    if (ocorrenciasDescontoConfig.length === 0) {
        container.innerHTML = '<p class="text-muted mb-0">Nenhum tipo de ocorrência configurado</p>';
        return;
    }
    
    let html = '';
    ocorrenciasDescontoConfig.forEach((config, index) => {
        html += `
            <div class="card card-flush mb-3" data-ocorrencia-index="${index}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Configuração ${index + 1}</h6>
                        <button type="button" class="btn btn-sm btn-light-danger" onclick="removerOcorrenciaDesconto(${index})">
                            <i class="ki-duotone ki-trash fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Ocorrência *</label>
                            <select class="form-select form-select-sm ocorrencia_tipo" data-index="${index}" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($tipos_ocorrencias as $tipo_ocorrencia): ?>
                                <option value="<?= $tipo_ocorrencia['id'] ?>" ${config.tipo_ocorrencia_id == <?= $tipo_ocorrencia['id'] ?> ? 'selected' : ''}>
                                    <?= htmlspecialchars($tipo_ocorrencia['nome']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Desconto *</label>
                            <select class="form-select form-select-sm ocorrencia_tipo_desconto" data-index="${index}" required onchange="toggleValorDesconto(${index})">
                                <option value="proporcional" ${config.tipo_desconto === 'proporcional' ? 'selected' : ''}>Proporcional (divide por dias úteis)</option>
                                <option value="fixo" ${config.tipo_desconto === 'fixo' ? 'selected' : ''}>Valor Fixo por Ocorrência</option>
                                <option value="percentual" ${config.tipo_desconto === 'percentual' ? 'selected' : ''}>Percentual do Bônus</option>
                                <option value="total" ${config.tipo_desconto === 'total' ? 'selected' : ''}>Valor Total (zera o bônus se houver ocorrência)</option>
                            </select>
                            <small class="text-muted d-block mt-1">
                                <strong>Total:</strong> Se houver qualquer ocorrência do tipo configurado, o bônus inteiro será zerado
                            </small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3" id="campo_valor_desconto_${index}" style="display: ${config.tipo_desconto !== 'proporcional' && config.tipo_desconto !== 'total' ? 'block' : 'none'};">
                            <label class="form-label">Valor/Percentual</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">${config.tipo_desconto === 'percentual' ? '%' : 'R$'}</span>
                                <input type="text" class="form-control ocorrencia_valor_desconto" data-index="${index}" 
                                       value="${config.valor_desconto || ''}" placeholder="${config.tipo_desconto === 'percentual' ? '0,00' : '0,00'}" />
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Período (dias)</label>
                            <input type="number" class="form-control form-control-sm ocorrencia_periodo" data-index="${index}" 
                                   value="${config.periodo_dias || ''}" placeholder="Padrão: período do fechamento" min="1" />
                            <small class="text-muted d-block mt-1">Deixe em branco para usar o período do fechamento</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="card card-flush bg-light-info">
                                <div class="card-body">
                                    <div class="form-check form-check-custom form-check-solid mb-3">
                                        <input class="form-check-input ocorrencia_verificar_periodo_anterior" type="checkbox" data-index="${index}" 
                                               ${config.verificar_periodo_anterior ? 'checked' : ''} onchange="togglePeriodoAnterior(${index})" />
                                        <label class="form-check-label fw-bold">
                                            Verificar Ocorrências em Período Anterior
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mb-3">
                                        Quando marcado, verifica ocorrências em meses anteriores ao período do fechamento. 
                                        <strong>Exemplo:</strong> Se o fechamento é de Janeiro/2024 e esta opção está marcada, 
                                        verifica ocorrências em Dezembro/2023. Se houver ocorrência no período anterior, 
                                        o bônus não será pago no fechamento atual.
                                    </small>
                                    <div class="row" id="campo_periodo_anterior_${index}" style="display: ${config.verificar_periodo_anterior ? 'block' : 'none'};">
                                        <div class="col-md-6">
                                            <label class="form-label">Quantos meses anteriores verificar?</label>
                                            <input type="number" class="form-control form-control-sm ocorrencia_periodo_anterior_meses" data-index="${index}" 
                                                   value="${config.periodo_anterior_meses || 1}" min="1" max="12" />
                                            <small class="text-muted d-block mt-1">Ex: 1 = mês anterior, 2 = 2 meses anteriores, etc.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input ocorrencia_apenas_aprovadas" type="checkbox" data-index="${index}" 
                                       ${config.desconta_apenas_aprovadas ? 'checked' : ''} />
                                <label class="form-check-label">Descontar apenas ocorrências aprovadas</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input ocorrencia_banco_horas" type="checkbox" data-index="${index}" 
                                       ${config.desconta_banco_horas ? 'checked' : ''} />
                                <label class="form-check-label">Incluir ocorrências que descontam banco de horas</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Aplica máscaras
    setTimeout(() => {
        if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
            document.querySelectorAll('.ocorrencia_valor_desconto').forEach(input => {
                const index = input.getAttribute('data-index');
                const tipoDesconto = ocorrenciasDescontoConfig[index]?.tipo_desconto || 'proporcional';
                if (tipoDesconto === 'percentual') {
                    jQuery(input).mask('#0,00', {reverse: true});
                } else {
                    jQuery(input).mask('#.##0,00', {reverse: true});
                }
            });
        }
    }, 100);
}

// Toggle campo de valor desconto
function toggleValorDesconto(index) {
    const select = document.querySelector(`.ocorrencia_tipo_desconto[data-index="${index}"]`);
    const campo = document.getElementById(`campo_valor_desconto_${index}`);
    if (select && campo) {
        const tipoDesconto = select.value;
        ocorrenciasDescontoConfig[index].tipo_desconto = tipoDesconto;
        if (tipoDesconto === 'proporcional' || tipoDesconto === 'total') {
            campo.style.display = 'none';
            ocorrenciasDescontoConfig[index].valor_desconto = '';
        } else {
            campo.style.display = 'block';
        }
        renderizarOcorrenciasDesconto();
    }
}

// Toggle campo de período anterior
function togglePeriodoAnterior(index) {
    const checkbox = document.querySelector(`.ocorrencia_verificar_periodo_anterior[data-index="${index}"]`);
    const campo = document.getElementById(`campo_periodo_anterior_${index}`);
    if (checkbox && campo) {
        ocorrenciasDescontoConfig[index].verificar_periodo_anterior = checkbox.checked;
        campo.style.display = checkbox.checked ? 'block' : 'none';
        if (!checkbox.checked) {
            ocorrenciasDescontoConfig[index].periodo_anterior_meses = 1;
        }
    }
}

// Atualizar configurações ao submeter formulário
document.getElementById('kt_modal_tipo_bonus_form')?.addEventListener('submit', function(e) {
    // Coleta todas as configurações de ocorrências
    const ocorrenciasData = [];
    ocorrenciasDescontoConfig.forEach((config, index) => {
        const tipoSelect = document.querySelector(`.ocorrencia_tipo[data-index="${index}"]`);
        const tipoDescontoSelect = document.querySelector(`.ocorrencia_tipo_desconto[data-index="${index}"]`);
        const valorInput = document.querySelector(`.ocorrencia_valor_desconto[data-index="${index}"]`);
        const periodoInput = document.querySelector(`.ocorrencia_periodo[data-index="${index}"]`);
        const apenasAprovadasCheck = document.querySelector(`.ocorrencia_apenas_aprovadas[data-index="${index}"]`);
        const bancoHorasCheck = document.querySelector(`.ocorrencia_banco_horas[data-index="${index}"]`);
        const verificarPeriodoAnteriorCheck = document.querySelector(`.ocorrencia_verificar_periodo_anterior[data-index="${index}"]`);
        const periodoAnteriorMesesInput = document.querySelector(`.ocorrencia_periodo_anterior_meses[data-index="${index}"]`);
        
        if (tipoSelect && tipoSelect.value) {
            ocorrenciasData.push({
                tipo_ocorrencia_id: tipoSelect.value,
                tipo_desconto: tipoDescontoSelect?.value || 'proporcional',
                valor_desconto: valorInput ? valorInput.value.replace(/[^0-9,]/g, '').replace(',', '.') : '',
                periodo_dias: periodoInput?.value || '',
                desconta_apenas_aprovadas: apenasAprovadasCheck?.checked ? '1' : '0',
                desconta_banco_horas: bancoHorasCheck?.checked ? '1' : '0',
                verificar_periodo_anterior: verificarPeriodoAnteriorCheck?.checked ? '1' : '0',
                periodo_anterior_meses: periodoAnteriorMesesInput?.value || '1'
            });
        }
    });
    
    // Adiciona campos hidden com os dados
    ocorrenciasData.forEach((data, index) => {
        Object.keys(data).forEach(key => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `ocorrencias_desconto[${index}][${key}]`;
            input.value = data[key];
            this.appendChild(input);
        });
    });
});

KTUtil.onDOMContentLoaded(function() {
    KTTiposBonusList.init();
});
</script>

