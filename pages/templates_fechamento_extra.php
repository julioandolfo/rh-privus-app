<?php
/**
 * Templates de Fechamento Extra
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('templates_fechamento_extra.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = sanitize($_POST['nome'] ?? '');
        $tipo_bonus_id = !empty($_POST['tipo_bonus_id']) ? (int)$_POST['tipo_bonus_id'] : null;
        $subtipo = $_POST['subtipo'] ?? '';
        $recorrente = isset($_POST['recorrente']) ? 1 : 0;
        $dia_mes = !empty($_POST['dia_mes']) ? (int)$_POST['dia_mes'] : null;
        $valor_padrao = !empty($_POST['valor_padrao']) ? str_replace(['.', ','], ['', '.'], $_POST['valor_padrao']) : null;
        $empresa_id = !empty($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $observacoes = sanitize($_POST['observacoes'] ?? '');
        
        if (empty($nome) || empty($subtipo)) {
            redirect('templates_fechamento_extra.php', 'Preencha todos os campos obrigatórios!', 'error');
        }
        
        // Valida dia_mes se recorrente
        if ($recorrente && (empty($dia_mes) || $dia_mes < 1 || $dia_mes > 31)) {
            redirect('templates_fechamento_extra.php', 'Dia do mês deve estar entre 1 e 31!', 'error');
        }
        
        try {
            if ($action === 'edit' && $id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE fechamentos_pagamento_extras_config 
                    SET nome = ?, tipo_bonus_id = ?, subtipo = ?, recorrente = ?, dia_mes = ?, 
                        valor_padrao = ?, empresa_id = ?, ativo = ?, observacoes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $nome, $tipo_bonus_id, $subtipo, $recorrente, $dia_mes, 
                    $valor_padrao, $empresa_id, $ativo, $observacoes, $id
                ]);
                redirect('templates_fechamento_extra.php', 'Template atualizado com sucesso!');
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO fechamentos_pagamento_extras_config 
                    (nome, tipo_bonus_id, subtipo, recorrente, dia_mes, valor_padrao, empresa_id, ativo, observacoes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $nome, $tipo_bonus_id, $subtipo, $recorrente, $dia_mes, 
                    $valor_padrao, $empresa_id, $ativo, $observacoes
                ]);
                redirect('templates_fechamento_extra.php', 'Template criado com sucesso!');
            }
        } catch (PDOException $e) {
            redirect('templates_fechamento_extra.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM fechamentos_pagamento_extras_config WHERE id = ?");
            $stmt->execute([$id]);
            redirect('templates_fechamento_extra.php', 'Template excluído com sucesso!');
        } catch (PDOException $e) {
            redirect('templates_fechamento_extra.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $ativo = (int)($_POST['ativo'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE fechamentos_pagamento_extras_config SET ativo = ? WHERE id = ?");
            $stmt->execute([$ativo, $id]);
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Busca templates
$where = [];
$params = [];

if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "(empresa_id IS NULL OR empresa_id IN ($placeholders))";
        $params = array_merge($params, $usuario['empresas_ids']);
    } else {
        $where[] = "(empresa_id IS NULL OR empresa_id = ?)";
        $params[] = $usuario['empresa_id'] ?? 0;
    }
} elseif ($usuario['role'] !== 'ADMIN') {
    $where[] = "(empresa_id IS NULL OR empresa_id = ?)";
    $params[] = $usuario['empresa_id'] ?? 0;
}

$sql = "
    SELECT 
        t.*,
        tb.nome as tipo_bonus_nome,
        e.nome_fantasia as empresa_nome
    FROM fechamentos_pagamento_extras_config t
    LEFT JOIN tipos_bonus tb ON t.tipo_bonus_id = tb.id
    LEFT JOIN empresas e ON t.empresa_id = e.id
    " . (!empty($where) ? "WHERE " . implode(' AND ', $where) : "") . "
    ORDER BY t.nome
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$templates = $stmt->fetchAll();

// Busca tipos de bônus para select
$stmt = $pdo->query("SELECT id, nome FROM tipos_bonus WHERE status = 'ativo' ORDER BY nome");
$tipos_bonus = $stmt->fetchAll();

// Busca empresas para select
$empresas = [];
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome_fantasia FROM empresas ORDER BY nome_fantasia");
    $empresas = $stmt->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id IN ($placeholders) ORDER BY nome_fantasia");
        $stmt->execute($usuario['empresas_ids']);
        $empresas = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? ORDER BY nome_fantasia");
        $stmt->execute([$usuario['empresa_id'] ?? 0]);
        $empresas = $stmt->fetchAll();
    }
} else {
    $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? ORDER BY nome_fantasia");
    $stmt->execute([$usuario['empresa_id'] ?? 0]);
    $empresas = $stmt->fetchAll();
}

$subtipos_labels = [
    'bonus_especifico' => 'Bônus Específico',
    'individual' => 'Bônus Individual',
    'grupal' => 'Bônus Grupal',
    'adiantamento' => 'Adiantamento'
];

$page_title = 'Templates de Fechamento Extra';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="d-flex text-dark fw-bolder my-1 fs-3">Templates de Fechamento Extra</h1>
        <ul class="breadcrumb breadcrumb-dot fw-bold text-gray-600 fs-7 my-1">
            <li class="breadcrumb-item text-gray-500">
                <a href="dashboard.php" class="text-gray-500 text-hover-primary">Dashboard</a>
            </li>
            <li class="breadcrumb-item">
                <span class="bullet bg-gray-200 w-5px h-2px"></span>
            </li>
            <li class="breadcrumb-item text-gray-900">Templates</li>
        </ul>
    </div>
    <div class="d-flex align-items-center gap-2 gap-lg-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_template">
            <i class="ki-duotone ki-plus fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Novo Template
        </button>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?= get_session_alert() ?>
        
        <!--begin::Card-->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Templates Cadastrados</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3" id="kt_table_templates">
                        <thead>
                            <tr class="fw-bolder text-muted">
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Tipo de Bônus</th>
                                <th>Empresa</th>
                                <th>Recorrente</th>
                                <th>Dia do Mês</th>
                                <th>Valor Padrão</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($templates)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-10">Nenhum template cadastrado</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($templates as $template): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($template['nome']) ?></strong>
                                    <?php if ($template['observacoes']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars(mb_substr($template['observacoes'], 0, 50)) ?><?= mb_strlen($template['observacoes']) > 50 ? '...' : '' ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?= htmlspecialchars($subtipos_labels[$template['subtipo']] ?? $template['subtipo']) ?></span>
                                </td>
                                <td>
                                    <?php if ($template['tipo_bonus_nome']): ?>
                                        <?= htmlspecialchars($template['tipo_bonus_nome']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($template['empresa_nome']): ?>
                                        <?= htmlspecialchars($template['empresa_nome']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Todas</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($template['recorrente']): ?>
                                        <span class="badge badge-success">Sim</span>
                                    <?php else: ?>
                                        <span class="badge badge-light">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($template['dia_mes']): ?>
                                        <?= $template['dia_mes'] ?>º
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($template['valor_padrao']): ?>
                                        R$ <?= number_format($template['valor_padrao'], 2, ',', '.') ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input class="form-check-input toggle-template" type="checkbox" 
                                               data-id="<?= $template['id'] ?>" 
                                               <?= $template['ativo'] ? 'checked' : '' ?> />
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-light-primary" 
                                                onclick="editarTemplate(<?= htmlspecialchars(json_encode($template)) ?>)">
                                            <i class="ki-duotone ki-pencil fs-6">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light-danger" 
                                                onclick="deletarTemplate(<?= $template['id'] ?>, '<?= htmlspecialchars($template['nome']) ?>')">
                                            <i class="ki-duotone ki-trash fs-6">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Template-->
<div class="modal fade" id="kt_modal_template" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="modal_template_titulo">Novo Template</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_template_form" method="POST" class="form">
                    <input type="hidden" name="action" id="template_action" value="add">
                    <input type="hidden" name="id" id="template_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Nome do Template</label>
                            <input type="text" name="nome" id="template_nome" class="form-control form-control-solid" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Tipo</label>
                            <select name="subtipo" id="template_subtipo" class="form-select form-select-solid" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($subtipos_labels as $key => $label): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Tipo de Bônus</label>
                            <select name="tipo_bonus_id" id="template_tipo_bonus_id" class="form-select form-select-solid">
                                <option value="">Nenhum (valor livre)</option>
                                <?php foreach ($tipos_bonus as $tb): ?>
                                <option value="<?= $tb['id'] ?>"><?= htmlspecialchars($tb['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Empresa</label>
                            <select name="empresa_id" id="template_empresa_id" class="form-select form-select-solid">
                                <option value="">Todas as empresas</option>
                                <?php foreach ($empresas as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nome_fantasia']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Valor Padrão</label>
                            <input type="text" name="valor_padrao" id="template_valor_padrao" class="form-control form-control-solid" placeholder="0,00" />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <div class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="recorrente" id="template_recorrente" value="1" />
                                <label class="form-check-label fw-semibold fs-6" for="template_recorrente">
                                    Fechamento Recorrente
                                </label>
                            </div>
                            <div class="form-text text-muted">Se marcado, o sistema criará automaticamente este fechamento no dia configurado</div>
                        </div>
                    </div>
                    
                    <div class="row mb-7" id="template_dia_mes_container" style="display: none;">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Dia do Mês (1-31)</label>
                            <input type="number" name="dia_mes" id="template_dia_mes" class="form-control form-control-solid" min="1" max="31" />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Observações</label>
                            <textarea name="observacoes" id="template_observacoes" class="form-control form-control-solid" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <div class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="ativo" id="template_ativo" value="1" checked />
                                <label class="form-check-label fw-semibold fs-6" for="template_ativo">
                                    Ativo
                                </label>
                            </div>
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
$(document).ready(function() {
    $('#kt_table_templates').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
        },
        order: [[0, 'asc']],
        pageLength: 25
    });
    
    // Máscara de moeda
    $('#template_valor_padrao').mask('#.##0,00', {reverse: true});
    
    // Toggle recorrente
    $('#template_recorrente').on('change', function() {
        if ($(this).is(':checked')) {
            $('#template_dia_mes_container').show();
            $('#template_dia_mes').prop('required', true);
        } else {
            $('#template_dia_mes_container').hide();
            $('#template_dia_mes').prop('required', false);
        }
    });
    
    // Toggle status template
    $('.toggle-template').on('change', function() {
        var id = $(this).data('id');
        var ativo = $(this).is(':checked') ? 1 : 0;
        
        $.ajax({
            url: 'templates_fechamento_extra.php',
            method: 'POST',
            data: {
                action: 'toggle',
                id: id,
                ativo: ativo
            },
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    alert('Erro ao atualizar status: ' + (response.message || 'Erro desconhecido'));
                    location.reload();
                }
            },
            error: function() {
                alert('Erro ao atualizar status');
                location.reload();
            }
        });
    });
});

function editarTemplate(template) {
    $('#modal_template_titulo').text('Editar Template');
    $('#template_action').val('edit');
    $('#template_id').val(template.id);
    $('#template_nome').val(template.nome);
    $('#template_subtipo').val(template.subtipo);
    $('#template_tipo_bonus_id').val(template.tipo_bonus_id || '');
    $('#template_empresa_id').val(template.empresa_id || '');
    $('#template_valor_padrao').val(template.valor_padrao ? parseFloat(template.valor_padrao).toFixed(2).replace('.', ',') : '');
    $('#template_recorrente').prop('checked', template.recorrente == 1);
    $('#template_dia_mes').val(template.dia_mes || '');
    $('#template_observacoes').val(template.observacoes || '');
    $('#template_ativo').prop('checked', template.ativo == 1);
    
    if (template.recorrente == 1) {
        $('#template_dia_mes_container').show();
        $('#template_dia_mes').prop('required', true);
    } else {
        $('#template_dia_mes_container').hide();
        $('#template_dia_mes').prop('required', false);
    }
    
    $('#kt_modal_template').modal('show');
}

function deletarTemplate(id, nome) {
    Swal.fire({
        title: 'Confirmar exclusão',
        text: 'Deseja realmente excluir o template "' + nome + '"?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            var form = $('<form>', {
                'method': 'POST',
                'action': 'templates_fechamento_extra.php'
            });
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'action',
                'value': 'delete'
            }));
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'id',
                'value': id
            }));
            $('body').append(form);
            form.submit();
        }
    });
}

// Limpa formulário ao fechar modal
$('#kt_modal_template').on('hidden.bs.modal', function() {
    $('#kt_modal_template_form')[0].reset();
    $('#modal_template_titulo').text('Novo Template');
    $('#template_action').val('add');
    $('#template_id').val('');
    $('#template_dia_mes_container').hide();
    $('#template_dia_mes').prop('required', false);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

