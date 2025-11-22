<?php
/**
 * Meus Pagamentos - Página do Colaborador para ver e enviar documentos
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/upload_documento.php';

require_page_permission('meus_pagamentos.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$colaborador_id = $usuario['colaborador_id'];

// Busca fechamentos fechados do colaborador
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        f.id as fechamento_id,
        f.mes_referencia,
        f.data_fechamento,
        f.status as fechamento_status,
        f.documento_obrigatorio,
        e.nome_fantasia as empresa_nome,
        i.id as item_id,
        i.valor_total,
        i.documento_anexo,
        i.documento_status,
        i.documento_data_envio,
        i.documento_data_aprovacao,
        i.documento_observacoes,
        u_aprovador.nome as aprovador_nome
    FROM fechamentos_pagamento f
    INNER JOIN fechamentos_pagamento_itens i ON f.id = i.fechamento_id
    LEFT JOIN empresas e ON f.empresa_id = e.id
    LEFT JOIN usuarios u_aprovador ON i.documento_aprovado_por = u_aprovador.id
    WHERE i.colaborador_id = ? AND f.status = 'fechado'
    ORDER BY f.mes_referencia DESC, f.data_fechamento DESC
");
$stmt->execute([$colaborador_id]);
$fechamentos = $stmt->fetchAll();

// Agrupa por fechamento
$fechamentos_agrupados = [];
foreach ($fechamentos as $fechamento) {
    $fechamento_id = $fechamento['fechamento_id'];
    if (!isset($fechamentos_agrupados[$fechamento_id])) {
        $fechamentos_agrupados[$fechamento_id] = [
            'fechamento_id' => $fechamento['fechamento_id'],
            'mes_referencia' => $fechamento['mes_referencia'],
            'data_fechamento' => $fechamento['data_fechamento'],
            'empresa_nome' => $fechamento['empresa_nome'],
            'documento_obrigatorio' => $fechamento['documento_obrigatorio'],
            'itens' => []
        ];
    }
    $fechamentos_agrupados[$fechamento_id]['itens'][] = $fechamento;
}

// Calcula estatísticas
$stats = [
    'total' => count($fechamentos_agrupados),
    'pendente' => 0,
    'enviado' => 0,
    'aprovado' => 0,
    'rejeitado' => 0
];

foreach ($fechamentos_agrupados as $fechamento) {
    foreach ($fechamento['itens'] as $item) {
        $status = $item['documento_status'] ?? 'pendente';
        if ($status === 'pendente') {
            $stats['pendente']++;
        } elseif ($status === 'enviado') {
            $stats['enviado']++;
        } elseif ($status === 'aprovado') {
            $stats['aprovado']++;
        } elseif ($status === 'rejeitado') {
            $stats['rejeitado']++;
        }
    }
}

$page_title = 'Meus Pagamentos';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Meus Pagamentos</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Meus Pagamentos</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?= get_session_alert() ?>
        
        <!--begin::Estatísticas-->
        <div class="row g-3 mb-5">
            <div class="col-md-3">
                <div class="card bg-light-primary">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <span class="text-muted fw-semibold d-block">Total</span>
                                <span class="text-gray-800 fw-bold fs-2"><?= $stats['total'] ?></span>
                            </div>
                            <i class="ki-duotone ki-wallet fs-1 text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light-warning">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <span class="text-muted fw-semibold d-block">Pendente</span>
                                <span class="text-gray-800 fw-bold fs-2"><?= $stats['pendente'] ?></span>
                            </div>
                            <i class="ki-duotone ki-time fs-1 text-warning">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light-info">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <span class="text-muted fw-semibold d-block">Enviado</span>
                                <span class="text-gray-800 fw-bold fs-2"><?= $stats['enviado'] ?></span>
                            </div>
                            <i class="ki-duotone ki-file-up fs-1 text-info">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light-success">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <span class="text-muted fw-semibold d-block">Aprovado</span>
                                <span class="text-gray-800 fw-bold fs-2"><?= $stats['aprovado'] ?></span>
                            </div>
                            <i class="ki-duotone ki-check-circle fs-1 text-success">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Estatísticas-->
        
        <!--begin::Card-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h2 class="fw-bold">Fechamentos de Pagamento</h2>
                </div>
            </div>
            <div class="card-body pt-0">
                <?php if (empty($fechamentos_agrupados)): ?>
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-information-5 fs-3x mb-3 text-muted">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="text-muted">Nenhum fechamento encontrado.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-dashed gy-4 align-middle" id="kt_meus_pagamentos_table">
                            <thead>
                                <tr class="fw-bold fs-6 text-gray-800">
                                    <th>Mês/Ano</th>
                                    <th>Empresa</th>
                                    <th>Valor Total</th>
                                    <th>Status Documento</th>
                                    <th>Data Envio</th>
                                    <th>Observações</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fechamentos_agrupados as $fechamento): 
                                    $item = $fechamento['itens'][0]; // Pega primeiro item (todos têm mesmo status)
                                    $total_fechamento = array_sum(array_column($fechamento['itens'], 'valor_total'));
                                ?>
                                <tr>
                                    <td>
                                        <span class="text-gray-800 fw-bold">
                                            <?= date('m/Y', strtotime($fechamento['mes_referencia'] . '-01')) ?>
                                        </span>
                                        <div class="text-muted fs-7">
                                            Fechado em <?= date('d/m/Y', strtotime($fechamento['data_fechamento'])) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($fechamento['empresa_nome']) ?></td>
                                    <td>
                                        <span class="text-success fw-bold">R$ <?= number_format($total_fechamento, 2, ',', '.') ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $item['documento_status'] ?? 'pendente';
                                        $badges = [
                                            'pendente' => '<span class="badge badge-light-danger">Pendente</span>',
                                            'enviado' => '<span class="badge badge-light-warning">Enviado</span>',
                                            'aprovado' => '<span class="badge badge-light-success">Aprovado</span>',
                                            'rejeitado' => '<span class="badge badge-light-danger">Rejeitado</span>'
                                        ];
                                        echo $badges[$status] ?? '<span class="badge badge-light-secondary">-</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($item['documento_data_envio']): ?>
                                            <?= date('d/m/Y H:i', strtotime($item['documento_data_envio'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['documento_observacoes']): ?>
                                            <span class="text-gray-600" title="<?= htmlspecialchars($item['documento_observacoes']) ?>">
                                                <?= htmlspecialchars(mb_substr($item['documento_observacoes'], 0, 50)) ?>
                                                <?= mb_strlen($item['documento_observacoes']) > 50 ? '...' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($status === 'pendente' || $status === 'rejeitado'): ?>
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                    onclick="enviarDocumento(<?= $fechamento['fechamento_id'] ?>, <?= $item['item_id'] ?>)">
                                                <i class="ki-duotone ki-file-up fs-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Enviar Documento
                                            </button>
                                        <?php elseif ($status === 'enviado'): ?>
                                            <button type="button" class="btn btn-sm btn-light-info" 
                                                    onclick="verDocumento(<?= $fechamento['fechamento_id'] ?>, <?= $item['item_id'] ?>)">
                                                <i class="ki-duotone ki-eye fs-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                                Ver Enviado
                                            </button>
                                        <?php elseif ($status === 'aprovado'): ?>
                                            <button type="button" class="btn btn-sm btn-light-success" 
                                                    onclick="verDocumento(<?= $fechamento['fechamento_id'] ?>, <?= $item['item_id'] ?>)">
                                                <i class="ki-duotone ki-check-circle fs-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Ver Aprovado
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Enviar Documento-->
<div class="modal fade" id="kt_modal_enviar_documento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Enviar Documento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_form_enviar_documento" enctype="multipart/form-data">
                    <input type="hidden" name="fechamento_id" id="doc_fechamento_id">
                    <input type="hidden" name="item_id" id="doc_item_id">
                    
                    <div class="mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Documento (Nota Fiscal, Recibo, etc.)</label>
                        <input type="file" name="documento" id="doc_arquivo" class="form-control form-control-solid" 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" required>
                        <div class="form-text">Formatos aceitos: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, WEBP (máx. 10MB)</div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btn_enviar_documento">
                            <span class="indicator-label">Enviar</span>
                            <span class="indicator-progress">Enviando...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Ver Documento-->
<div class="modal fade" id="kt_modal_ver_documento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Visualizar Documento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div id="doc_preview_container">
                    <!-- Conteúdo será preenchido via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="doc_download_link" class="btn btn-primary" download>
                    <i class="ki-duotone ki-file-down fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Download
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<script>
// Enviar documento
function enviarDocumento(fechamentoId, itemId) {
    document.getElementById('doc_fechamento_id').value = fechamentoId;
    document.getElementById('doc_item_id').value = itemId;
    document.getElementById('doc_arquivo').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_enviar_documento'));
    modal.show();
}

// Ver documento
function verDocumento(fechamentoId, itemId) {
    // Busca dados do documento via API
    fetch(`../api/get_documento_pagamento.php?fechamento_id=${fechamentoId}&item_id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const doc = data.data;
                const container = document.getElementById('doc_preview_container');
                const downloadLink = document.getElementById('doc_download_link');
                
                downloadLink.href = '../' + doc.documento_anexo;
                downloadLink.download = doc.documento_nome || 'documento';
                
                // Verifica se é imagem para preview
                const isImage = doc.documento_anexo.match(/\.(jpg|jpeg|png|gif|webp)$/i);
                
                if (isImage) {
                    container.innerHTML = `
                        <div class="text-center">
                            <img src="../${doc.documento_anexo}" class="img-fluid" alt="Documento" style="max-height: 600px;">
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div class="text-center py-10">
                            <i class="ki-duotone ki-file fs-3x text-primary mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="text-gray-600">Clique em "Download" para baixar o documento</div>
                            <div class="text-muted fs-7 mt-2">${doc.documento_nome || 'documento'}</div>
                        </div>
                    `;
                }
                
                const modal = new bootstrap.Modal(document.getElementById('kt_modal_ver_documento'));
                modal.show();
            } else {
                Swal.fire('Erro', data.message || 'Erro ao carregar documento', 'error');
            }
        })
        .catch(error => {
            Swal.fire('Erro', 'Erro ao carregar documento', 'error');
        });
}

// Submit do formulário de envio
document.getElementById('kt_form_enviar_documento')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const btn = document.getElementById('btn_enviar_documento');
    
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    fetch('../api/upload_documento_pagamento.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (data.success) {
            Swal.fire('Sucesso!', data.message, 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Erro', data.message, 'error');
        }
    })
    .catch(error => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        Swal.fire('Erro', 'Erro ao enviar documento', 'error');
    });
});

// Inicializa DataTable
(function() {
    function waitForDataTable() {
        if (typeof jQuery !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
            $('#kt_meus_pagamentos_table').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                },
                pageLength: 10,
                order: [[0, 'desc']],
                responsive: true
            });
        } else {
            setTimeout(waitForDataTable, 100);
        }
    }
    waitForDataTable();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

