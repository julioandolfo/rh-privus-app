<?php
/**
 * Admin - Gestão de Resgates
 */

$page_title = 'Gestão de Resgates';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/loja_functions.php';

require_login();

$usuario = $_SESSION['usuario'];
if (!in_array($usuario['role'], ['ADMIN', 'RH', 'GESTOR'])) {
    redirect('dashboard.php', 'Sem permissão para acessar esta página.', 'danger');
}

$pdo = getDB();

// Filtros
$status_filtro = $_GET['status'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

$filtros = [
    'status' => $status_filtro ?: null,
    'data_inicio' => $data_inicio ?: null,
    'data_fim' => $data_fim ?: null
];

// Obtém resgates
$resgates = loja_get_resgates_admin($filtros);

// Estatísticas
$estatisticas = loja_get_estatisticas();

// Status labels
$status_labels = [
    'pendente' => ['label' => 'Pendente', 'badge' => 'warning', 'icon' => 'time'],
    'aprovado' => ['label' => 'Aprovado', 'badge' => 'success', 'icon' => 'check-circle'],
    'rejeitado' => ['label' => 'Rejeitado', 'badge' => 'danger', 'icon' => 'cross-circle'],
    'preparando' => ['label' => 'Preparando', 'badge' => 'info', 'icon' => 'delivery'],
    'enviado' => ['label' => 'Enviado', 'badge' => 'primary', 'icon' => 'delivery-24'],
    'entregue' => ['label' => 'Entregue', 'badge' => 'success', 'icon' => 'check-square'],
    'cancelado' => ['label' => 'Cancelado', 'badge' => 'secondary', 'icon' => 'cross-square']
];

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.status-card {
    cursor: pointer;
    transition: all 0.2s ease;
}
.status-card:hover {
    transform: translateY(-2px);
}
.status-card.active {
    border: 2px solid var(--bs-primary) !important;
}
</style>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">
                <i class="ki-duotone ki-basket fs-2 me-2 text-primary">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                Gestão de Resgates
            </h1>
            <span class="text-muted mt-1 fw-semibold fs-7">Gerencie os resgates da loja de pontos</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="loja_admin_produtos.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-package fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                Produtos
            </a>
            <a href="loja_admin_categorias.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-category fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                Categorias
            </a>
            <a href="loja_admin_config.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-setting-2 fs-4"><span class="path1"></span><span class="path2"></span></i>
                Configurações
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!-- Estatísticas -->
        <div class="row g-4 mb-8">
            <div class="col-6 col-md-2">
                <div class="card card-flush status-card <?= $status_filtro === 'pendente' ? 'active' : '' ?>" onclick="filtrarStatus('pendente')">
                    <div class="card-body py-4 text-center">
                        <i class="ki-duotone ki-time fs-2x text-warning mb-2">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                        <div class="fs-2 fw-bold"><?= $estatisticas['resgates_pendentes'] ?></div>
                        <div class="text-gray-500 fs-7">Pendentes</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card card-flush status-card <?= $status_filtro === 'aprovado' ? 'active' : '' ?>" onclick="filtrarStatus('aprovado')">
                    <div class="card-body py-4 text-center">
                        <i class="ki-duotone ki-check-circle fs-2x text-success mb-2">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                        <div class="fs-2 fw-bold"><?= $estatisticas['resgates_aprovados'] ?></div>
                        <div class="text-gray-500 fs-7">Aprovados</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card card-flush status-card <?= $status_filtro === 'preparando' ? 'active' : '' ?>" onclick="filtrarStatus('preparando')">
                    <div class="card-body py-4 text-center">
                        <i class="ki-duotone ki-delivery fs-2x text-info mb-2">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span>
                        </i>
                        <div class="fs-2 fw-bold"><?= $estatisticas['resgates_preparando'] ?></div>
                        <div class="text-gray-500 fs-7">Preparando</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card card-flush status-card <?= $status_filtro === 'enviado' ? 'active' : '' ?>" onclick="filtrarStatus('enviado')">
                    <div class="card-body py-4 text-center">
                        <i class="ki-duotone ki-delivery-24 fs-2x text-primary mb-2">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                        <div class="fs-2 fw-bold"><?= $estatisticas['resgates_enviados'] ?></div>
                        <div class="text-gray-500 fs-7">Enviados</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card card-flush status-card <?= $status_filtro === 'entregue' ? 'active' : '' ?>" onclick="filtrarStatus('entregue')">
                    <div class="card-body py-4 text-center">
                        <i class="ki-duotone ki-check-square fs-2x text-success mb-2">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                        <div class="fs-2 fw-bold"><?= $estatisticas['resgates_entregues'] ?></div>
                        <div class="text-gray-500 fs-7">Entregues</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card card-flush bg-light-warning">
                    <div class="card-body py-4 text-center">
                        <i class="ki-duotone ki-medal-star fs-2x text-warning mb-2">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                        </i>
                        <div class="fs-2 fw-bold"><?= number_format($estatisticas['pontos_gastos_mes'], 0, ',', '.') ?></div>
                        <div class="text-gray-500 fs-7">Pontos (mês)</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card card-flush mb-5">
            <div class="card-body py-4">
                <form class="row align-items-center g-3" method="GET">
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-solid">
                            <option value="">Todos os status</option>
                            <?php foreach ($status_labels as $key => $s): ?>
                            <option value="<?= $key ?>" <?= $status_filtro === $key ? 'selected' : '' ?>><?= $s['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="data_inicio" class="form-control form-control-solid" value="<?= $data_inicio ?>" placeholder="Data início">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="data_fim" class="form-control form-control-solid" value="<?= $data_fim ?>" placeholder="Data fim">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ki-duotone ki-filter fs-4"><span class="path1"></span><span class="path2"></span></i>
                            Filtrar
                        </button>
                    </div>
                    <div class="col-md-1">
                        <a href="loja_admin_resgates.php" class="btn btn-light w-100">Limpar</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Lista de Resgates -->
        <div class="card card-flush">
            <div class="card-body pt-0">
                <?php if (empty($resgates)): ?>
                <div class="text-center py-15">
                    <i class="ki-duotone ki-basket fs-5x text-gray-300 mb-5">
                        <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                    </i>
                    <h3 class="text-gray-700 mb-3">Nenhum resgate encontrado</h3>
                    <p class="text-gray-500">Tente ajustar os filtros ou aguarde novos resgates.</p>
                </div>
                <?php else: ?>
                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th class="min-w-50px">#</th>
                            <th class="min-w-150px">Colaborador</th>
                            <th class="min-w-150px">Produto</th>
                            <th class="min-w-80px text-center">Qtd</th>
                            <th class="min-w-100px text-center">Pontos</th>
                            <th class="min-w-100px">Data</th>
                            <th class="min-w-100px text-center">Status</th>
                            <th class="min-w-150px text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resgates as $resgate): 
                            $status = $status_labels[$resgate['status']] ?? ['label' => $resgate['status'], 'badge' => 'secondary', 'icon' => 'abstract'];
                        ?>
                        <tr>
                            <td><span class="text-gray-600 fw-bold"><?= $resgate['id'] ?></span></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-35px me-3">
                                        <?php if ($resgate['colaborador_foto']): ?>
                                        <img src="../<?= htmlspecialchars($resgate['colaborador_foto']) ?>" alt="">
                                        <?php else: ?>
                                        <span class="symbol-label bg-light-primary">
                                            <?= strtoupper(substr($resgate['colaborador_nome'], 0, 1)) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="text-gray-800 fw-bold d-block"><?= htmlspecialchars($resgate['colaborador_nome']) ?></span>
                                        <span class="text-gray-500 fs-7"><?= htmlspecialchars($resgate['empresa_nome'] ?? '') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-35px me-3">
                                        <?php if ($resgate['produto_imagem']): ?>
                                        <img src="../<?= htmlspecialchars($resgate['produto_imagem']) ?>" alt="" class="rounded">
                                        <?php else: ?>
                                        <span class="symbol-label bg-light">
                                            <i class="ki-duotone ki-gift fs-4 text-gray-500"><span class="path1"></span><span class="path2"></span></i>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-gray-800"><?= htmlspecialchars($resgate['produto_nome']) ?></span>
                                </div>
                            </td>
                            <td class="text-center"><?= $resgate['quantidade'] ?></td>
                            <td class="text-center">
                                <span class="badge badge-light-warning"><?= number_format($resgate['pontos_total'], 0, ',', '.') ?> pts</span>
                            </td>
                            <td>
                                <span class="text-gray-600"><?= date('d/m/Y', strtotime($resgate['created_at'])) ?></span>
                                <span class="text-gray-500 fs-7 d-block"><?= date('H:i', strtotime($resgate['created_at'])) ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-light-<?= $status['badge'] ?>">
                                    <i class="ki-duotone ki-<?= $status['icon'] ?> fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                                    <?= $status['label'] ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-icon btn-light-primary" onclick="verDetalhes(<?= $resgate['id'] ?>)" title="Ver detalhes">
                                    <i class="ki-duotone ki-eye fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                </button>
                                
                                <?php if ($resgate['status'] === 'pendente'): ?>
                                <button class="btn btn-sm btn-icon btn-light-success" onclick="aprovarResgate(<?= $resgate['id'] ?>)" title="Aprovar">
                                    <i class="ki-duotone ki-check fs-5"><span class="path1"></span><span class="path2"></span></i>
                                </button>
                                <button class="btn btn-sm btn-icon btn-light-danger" onclick="rejeitarResgate(<?= $resgate['id'] ?>)" title="Rejeitar">
                                    <i class="ki-duotone ki-cross fs-5"><span class="path1"></span><span class="path2"></span></i>
                                </button>
                                <?php elseif ($resgate['status'] === 'aprovado'): ?>
                                <button class="btn btn-sm btn-icon btn-light-info" onclick="atualizarStatus(<?= $resgate['id'] ?>, 'preparando')" title="Marcar como preparando">
                                    <i class="ki-duotone ki-delivery fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                                </button>
                                <?php elseif ($resgate['status'] === 'preparando'): ?>
                                <button class="btn btn-sm btn-icon btn-light-primary" onclick="marcarEnviado(<?= $resgate['id'] ?>)" title="Marcar como enviado">
                                    <i class="ki-duotone ki-delivery-24 fs-5"><span class="path1"></span><span class="path2"></span></i>
                                </button>
                                <button class="btn btn-sm btn-icon btn-light-success" onclick="atualizarStatus(<?= $resgate['id'] ?>, 'entregue')" title="Marcar como entregue">
                                    <i class="ki-duotone ki-check-square fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                </button>
                                <?php elseif ($resgate['status'] === 'enviado'): ?>
                                <button class="btn btn-sm btn-icon btn-light-success" onclick="atualizarStatus(<?= $resgate['id'] ?>, 'entregue')" title="Marcar como entregue">
                                    <i class="ki-duotone ki-check-square fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<!-- Modal de Detalhes -->
<div class="modal fade" id="modal_detalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-700px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Detalhes do Resgate</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body" id="modal_detalhes_body">
                <div class="text-center py-10">
                    <span class="spinner-border text-primary"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Envio -->
<div class="modal fade" id="modal_envio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Marcar como Enviado</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <form id="form_envio">
                <div class="modal-body py-5">
                    <input type="hidden" name="resgate_id" id="envio_resgate_id">
                    
                    <div class="mb-5">
                        <label class="form-label">Código de Rastreio (opcional)</label>
                        <input type="text" name="codigo_rastreio" class="form-control form-control-solid" placeholder="Ex: AA123456789BR">
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label">Observação (opcional)</label>
                        <textarea name="observacao" class="form-control form-control-solid" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ki-duotone ki-delivery-24 fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Confirmar Envio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Rejeição -->
<div class="modal fade" id="modal_rejeicao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Rejeitar Resgate</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <form id="form_rejeicao">
                <div class="modal-body py-5">
                    <input type="hidden" name="resgate_id" id="rejeicao_resgate_id">
                    
                    <div class="alert alert-warning mb-5">
                        <i class="ki-duotone ki-information-5 fs-2x me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        Os pontos serão devolvidos ao colaborador.
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label required">Motivo da Rejeição</label>
                        <textarea name="motivo" class="form-control form-control-solid" rows="3" required placeholder="Informe o motivo da rejeição..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Rejeitar Resgate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const statusLabels = <?= json_encode($status_labels) ?>;

function filtrarStatus(status) {
    const url = new URL(window.location);
    if (url.searchParams.get('status') === status) {
        url.searchParams.delete('status');
    } else {
        url.searchParams.set('status', status);
    }
    window.location = url;
}

function verDetalhes(resgateId) {
    const modal = new bootstrap.Modal(document.getElementById('modal_detalhes'));
    const body = document.getElementById('modal_detalhes_body');
    
    body.innerHTML = '<div class="text-center py-10"><span class="spinner-border text-primary"></span></div>';
    modal.show();
    
    fetch('../api/loja/resgates.php?action=detalhe&id=' + resgateId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const r = data.resgate;
                const status = statusLabels[r.status] || {label: r.status, badge: 'secondary'};
                
                body.innerHTML = `
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <div class="border rounded p-4">
                                <h5 class="mb-3">Colaborador</h5>
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        ${r.colaborador_foto ? 
                                            `<img src="../${r.colaborador_foto}" alt="">` :
                                            `<span class="symbol-label bg-light-primary fs-4">${r.colaborador_nome.charAt(0)}</span>`
                                        }
                                    </div>
                                    <div>
                                        <div class="fw-bold">${r.colaborador_nome}</div>
                                        <div class="text-gray-500 fs-7">${r.colaborador_email}</div>
                                        <div class="text-gray-500 fs-7">${r.empresa_nome || ''}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-4">
                                <h5 class="mb-3">Produto</h5>
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        ${r.produto_imagem ? 
                                            `<img src="../${r.produto_imagem}" alt="" class="rounded">` :
                                            `<span class="symbol-label bg-light"><i class="ki-duotone ki-gift fs-2x text-gray-500"><span class="path1"></span><span class="path2"></span></i></span>`
                                        }
                                    </div>
                                    <div>
                                        <div class="fw-bold">${r.produto_nome}</div>
                                        <div class="text-gray-500 fs-7">Qtd: ${r.quantidade} | ${r.pontos_total.toLocaleString('pt-BR')} pts</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center">
                                <div class="text-gray-500 fs-7">Status</div>
                                <span class="badge badge-light-${status.badge} mt-1">${status.label}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center">
                                <div class="text-gray-500 fs-7">Solicitado em</div>
                                <div class="fw-bold">${new Date(r.created_at).toLocaleString('pt-BR')}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center">
                                <div class="text-gray-500 fs-7">Pontos</div>
                                <div class="fw-bold text-warning">${r.pontos_total.toLocaleString('pt-BR')} pts</div>
                            </div>
                        </div>
                    </div>
                    
                    ${r.observacao_colaborador ? `
                        <div class="mb-4">
                            <div class="text-gray-500 fs-7 mb-1">Observação do colaborador:</div>
                            <div class="bg-light rounded p-3">${r.observacao_colaborador}</div>
                        </div>
                    ` : ''}
                    
                    ${r.codigo_rastreio ? `
                        <div class="alert alert-info mb-4">
                            <strong>Código de Rastreio:</strong> ${r.codigo_rastreio}
                        </div>
                    ` : ''}
                    
                    ${r.motivo_rejeicao ? `
                        <div class="alert alert-danger mb-4">
                            <strong>Motivo da Rejeição:</strong> ${r.motivo_rejeicao}
                        </div>
                    ` : ''}
                    
                    <div class="separator my-5"></div>
                    
                    <h5 class="mb-4">Histórico</h5>
                    <div class="timeline">
                        <div class="timeline-item mb-3">
                            <div class="d-flex">
                                <span class="badge badge-light-success me-3">Solicitado</span>
                                <span class="text-gray-600">${new Date(r.created_at).toLocaleString('pt-BR')}</span>
                            </div>
                        </div>
                        ${r.data_aprovacao ? `
                            <div class="timeline-item mb-3">
                                <div class="d-flex">
                                    <span class="badge badge-light-${r.status === 'rejeitado' ? 'danger' : 'success'} me-3">${r.status === 'rejeitado' ? 'Rejeitado' : 'Aprovado'}</span>
                                    <span class="text-gray-600">${new Date(r.data_aprovacao).toLocaleString('pt-BR')} por ${r.aprovador_nome || 'Sistema'}</span>
                                </div>
                            </div>
                        ` : ''}
                        ${r.data_preparacao ? `
                            <div class="timeline-item mb-3">
                                <div class="d-flex">
                                    <span class="badge badge-light-info me-3">Preparando</span>
                                    <span class="text-gray-600">${new Date(r.data_preparacao).toLocaleString('pt-BR')} por ${r.preparador_nome || 'Sistema'}</span>
                                </div>
                            </div>
                        ` : ''}
                        ${r.data_envio ? `
                            <div class="timeline-item mb-3">
                                <div class="d-flex">
                                    <span class="badge badge-light-primary me-3">Enviado</span>
                                    <span class="text-gray-600">${new Date(r.data_envio).toLocaleString('pt-BR')} por ${r.enviador_nome || 'Sistema'}</span>
                                </div>
                            </div>
                        ` : ''}
                        ${r.data_entrega ? `
                            <div class="timeline-item mb-3">
                                <div class="d-flex">
                                    <span class="badge badge-light-success me-3">Entregue</span>
                                    <span class="text-gray-600">${new Date(r.data_entrega).toLocaleString('pt-BR')} por ${r.entregador_nome || 'Sistema'}</span>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                `;
            } else {
                body.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        });
}

function aprovarResgate(id) {
    Swal.fire({
        title: 'Aprovar Resgate?',
        text: 'O colaborador será notificado.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Aprovar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'aprovar');
            formData.append('resgate_id', id);
            
            fetch('../api/loja/resgates.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Aprovado!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        }
    });
}

function rejeitarResgate(id) {
    document.getElementById('rejeicao_resgate_id').value = id;
    new bootstrap.Modal(document.getElementById('modal_rejeicao')).show();
}

document.getElementById('form_rejeicao').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'rejeitar');
    
    fetch('../api/loja/resgates.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Rejeitado!', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Erro', data.message, 'error');
        }
    });
});

function atualizarStatus(id, status) {
    const formData = new FormData();
    formData.append('action', 'atualizar_status');
    formData.append('resgate_id', id);
    formData.append('status', status);
    
    fetch('../api/loja/resgates.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            toastr.success(data.message);
            setTimeout(() => location.reload(), 500);
        } else {
            Swal.fire('Erro', data.message, 'error');
        }
    });
}

function marcarEnviado(id) {
    document.getElementById('envio_resgate_id').value = id;
    new bootstrap.Modal(document.getElementById('modal_envio')).show();
}

document.getElementById('form_envio').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'atualizar_status');
    formData.append('status', 'enviado');
    
    fetch('../api/loja/resgates.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Enviado!', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Erro', data.message, 'error');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
