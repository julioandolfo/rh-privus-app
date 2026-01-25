<?php
/**
 * Meus Resgates - Histórico de resgates do colaborador
 */

$page_title = 'Meus Resgates';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/loja_functions.php';
require_once __DIR__ . '/../includes/pontuacao.php';

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $usuario['colaborador_id'] ?? null;

if (!$colaborador_id) {
    redirect('dashboard.php', 'Usuário não vinculado a um colaborador.', 'warning');
}

// Obtém pontos do colaborador
$meus_pontos = obter_pontos(null, $colaborador_id);

// Obtém resgates
$resgates = loja_get_resgates_colaborador($colaborador_id);

// Obtém wishlist
$wishlist = loja_get_wishlist($colaborador_id);

// Contadores
$pendentes = count(array_filter($resgates, fn($r) => $r['status'] === 'pendente'));
$em_andamento = count(array_filter($resgates, fn($r) => in_array($r['status'], ['aprovado', 'preparando', 'enviado'])));
$entregues = count(array_filter($resgates, fn($r) => $r['status'] === 'entregue'));

require_once __DIR__ . '/../includes/header.php';

// Status labels
$status_labels = [
    'pendente' => ['label' => 'Aguardando Aprovação', 'badge' => 'warning', 'icon' => 'time'],
    'aprovado' => ['label' => 'Aprovado', 'badge' => 'success', 'icon' => 'check-circle'],
    'rejeitado' => ['label' => 'Rejeitado', 'badge' => 'danger', 'icon' => 'cross-circle'],
    'preparando' => ['label' => 'Preparando', 'badge' => 'info', 'icon' => 'delivery'],
    'enviado' => ['label' => 'Enviado', 'badge' => 'primary', 'icon' => 'delivery-24'],
    'entregue' => ['label' => 'Entregue', 'badge' => 'success', 'icon' => 'check-square'],
    'cancelado' => ['label' => 'Cancelado', 'badge' => 'secondary', 'icon' => 'cross-square']
];
?>

<style>
.resgate-card {
    transition: all 0.3s ease;
}
.resgate-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}
.timeline-status {
    position: relative;
    padding-left: 25px;
}
.timeline-status::before {
    content: '';
    position: absolute;
    left: 6px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-status .status-item {
    position: relative;
    padding-bottom: 15px;
}
.timeline-status .status-item::before {
    content: '';
    position: absolute;
    left: -21px;
    top: 3px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #e9ecef;
    border: 2px solid #fff;
}
.timeline-status .status-item.active::before {
    background: var(--bs-primary);
}
.timeline-status .status-item.completed::before {
    background: var(--bs-success);
}
.saldo-card {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
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
                Meus Resgates
            </h1>
            <span class="text-muted mt-1 fw-semibold fs-7">Acompanhe seus pedidos da loja de pontos</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="loja.php" class="btn btn-sm btn-primary">
                <i class="ki-duotone ki-shop fs-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                    <span class="path5"></span>
                </i>
                Voltar à Loja
            </a>
            <div class="saldo-card rounded px-4 py-2 text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="ki-duotone ki-medal-star fs-2x text-white">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    <div>
                        <div class="fs-8 opacity-75">Seu saldo</div>
                        <div class="fs-3 fw-bold"><?= number_format($meus_pontos['pontos_totais'], 0, ',', '.') ?> pts</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!-- Estatísticas -->
        <div class="row g-5 mb-8">
            <div class="col-md-4">
                <div class="card card-flush bg-light-warning">
                    <div class="card-body py-5">
                        <div class="d-flex align-items-center">
                            <i class="ki-duotone ki-time fs-3x text-warning me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div>
                                <div class="fs-2 fw-bold text-gray-800"><?= $pendentes ?></div>
                                <div class="text-gray-600">Pendentes</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-flush bg-light-info">
                    <div class="card-body py-5">
                        <div class="d-flex align-items-center">
                            <i class="ki-duotone ki-delivery fs-3x text-info me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                            <div>
                                <div class="fs-2 fw-bold text-gray-800"><?= $em_andamento ?></div>
                                <div class="text-gray-600">Em andamento</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-flush bg-light-success">
                    <div class="card-body py-5">
                        <div class="d-flex align-items-center">
                            <i class="ki-duotone ki-check-circle fs-3x text-success me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div>
                                <div class="fs-2 fw-bold text-gray-800"><?= $entregues ?></div>
                                <div class="text-gray-600">Entregues</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#tab_resgates">
                    <i class="ki-duotone ki-basket fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                    Meus Resgates
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab_wishlist">
                    <i class="ki-duotone ki-heart fs-4 me-2"><span class="path1"></span><span class="path2"></span></i>
                    Lista de Desejos
                    <?php if (count($wishlist) > 0): ?>
                    <span class="badge badge-sm badge-circle badge-light-danger ms-2"><?= count($wishlist) ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- Tab: Resgates -->
            <div class="tab-pane fade show active" id="tab_resgates">
                <?php if (empty($resgates)): ?>
                <div class="card card-flush">
                    <div class="card-body text-center py-15">
                        <i class="ki-duotone ki-basket fs-5x text-gray-300 mb-5">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                        </i>
                        <h3 class="text-gray-700 mb-3">Nenhum resgate ainda</h3>
                        <p class="text-gray-500">Visite a loja e troque seus pontos por produtos incríveis!</p>
                        <a href="loja.php" class="btn btn-primary mt-3">
                            <i class="ki-duotone ki-shop fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                            Ir para a Loja
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="row g-5">
                    <?php foreach ($resgates as $resgate): 
                        $status = $status_labels[$resgate['status']] ?? ['label' => $resgate['status'], 'badge' => 'secondary', 'icon' => 'abstract'];
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card card-flush resgate-card h-100">
                            <div class="card-header border-0 pt-5">
                                <div class="card-title m-0">
                                    <div class="symbol symbol-45px me-3">
                                        <?php if ($resgate['produto_imagem']): ?>
                                        <img src="../<?= htmlspecialchars($resgate['produto_imagem']) ?>" alt="">
                                        <?php else: ?>
                                        <span class="symbol-label bg-light-primary">
                                            <i class="ki-duotone ki-gift fs-2x text-primary">
                                                <span class="path1"></span><span class="path2"></span>
                                            </i>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="fs-6 fw-bold text-gray-800"><?= htmlspecialchars($resgate['produto_nome']) ?></span>
                                        <span class="text-gray-500 fs-7"><?= htmlspecialchars($resgate['categoria_nome']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body pt-3">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-gray-600">Quantidade:</span>
                                    <span class="fw-bold"><?= $resgate['quantidade'] ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-gray-600">Pontos:</span>
                                    <span class="fw-bold text-warning"><?= number_format($resgate['pontos_total'], 0, ',', '.') ?> pts</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-gray-600">Data:</span>
                                    <span class="fw-bold"><?= date('d/m/Y H:i', strtotime($resgate['created_at'])) ?></span>
                                </div>
                                
                                <div class="separator my-4"></div>
                                
                                <div class="d-flex align-items-center">
                                    <span class="badge badge-light-<?= $status['badge'] ?> fs-7 fw-semibold">
                                        <i class="ki-duotone ki-<?= $status['icon'] ?> fs-5 me-1">
                                            <span class="path1"></span><span class="path2"></span>
                                        </i>
                                        <?= $status['label'] ?>
                                    </span>
                                    <?php if ($resgate['codigo_rastreio']): ?>
                                    <span class="badge badge-light ms-2" title="Código de rastreio">
                                        <i class="ki-duotone ki-delivery-24 fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        <?= htmlspecialchars($resgate['codigo_rastreio']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($resgate['motivo_rejeicao']): ?>
                                <div class="alert alert-danger mt-3 py-2 px-3 mb-0">
                                    <small><strong>Motivo:</strong> <?= htmlspecialchars($resgate['motivo_rejeicao']) ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer border-top">
                                <button class="btn btn-sm btn-light-primary w-100" onclick="verDetalhes(<?= $resgate['id'] ?>)">
                                    <i class="ki-duotone ki-eye fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    Ver Detalhes
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Wishlist -->
            <div class="tab-pane fade" id="tab_wishlist">
                <?php if (empty($wishlist)): ?>
                <div class="card card-flush">
                    <div class="card-body text-center py-15">
                        <i class="ki-duotone ki-heart fs-5x text-gray-300 mb-5">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                        <h3 class="text-gray-700 mb-3">Lista de desejos vazia</h3>
                        <p class="text-gray-500">Adicione produtos que deseja à sua lista de desejos!</p>
                        <a href="loja.php" class="btn btn-light-danger mt-3">
                            <i class="ki-duotone ki-shop fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                            Explorar Loja
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="row g-5">
                    <?php foreach ($wishlist as $produto): 
                        $pode_resgatar = $meus_pontos['pontos_totais'] >= $produto['pontos_necessarios'];
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card card-flush h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="symbol symbol-50px me-3">
                                        <?php if ($produto['imagem']): ?>
                                        <img src="../<?= htmlspecialchars($produto['imagem']) ?>" alt="">
                                        <?php else: ?>
                                        <span class="symbol-label bg-light-<?= $produto['categoria_cor'] ?>">
                                            <i class="ki-duotone ki-<?= $produto['categoria_icone'] ?> fs-2x text-<?= $produto['categoria_cor'] ?>">
                                                <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                                            </i>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <span class="fs-6 fw-bold text-gray-800"><?= htmlspecialchars($produto['nome']) ?></span>
                                        <br>
                                        <span class="badge badge-light-warning mt-1">
                                            <?= number_format($produto['pontos_necessarios'], 0, ',', '.') ?> pts
                                        </span>
                                    </div>
                                    <button class="btn btn-icon btn-sm btn-light-danger" onclick="removerWishlist(<?= $produto['id'] ?>, this)">
                                        <i class="ki-duotone ki-cross fs-4"><span class="path1"></span><span class="path2"></span></i>
                                    </button>
                                </div>
                                
                                <?php if ($pode_resgatar): ?>
                                <button class="btn btn-sm btn-primary w-100" onclick="window.location.href='loja.php'">
                                    <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                    Posso Resgatar!
                                </button>
                                <?php else: ?>
                                <div class="text-center">
                                    <small class="text-muted">
                                        Faltam <?= number_format($produto['pontos_necessarios'] - $meus_pontos['pontos_totais'], 0, ',', '.') ?> pts
                                    </small>
                                    <div class="progress h-6px mt-2">
                                        <div class="progress-bar bg-warning" style="width: <?= min(100, ($meus_pontos['pontos_totais'] / $produto['pontos_necessarios']) * 100) ?>%"></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
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

<script>
const statusLabels = <?= json_encode($status_labels) ?>;

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
                const status = statusLabels[r.status] || {label: r.status, badge: 'secondary', icon: 'abstract'};
                
                let timeline = '';
                const etapas = [
                    {status: 'pendente', label: 'Solicitado', data: r.created_at, concluido: true},
                    {status: 'aprovado', label: 'Aprovado', data: r.data_aprovacao, concluido: ['aprovado', 'preparando', 'enviado', 'entregue'].includes(r.status)},
                    {status: 'preparando', label: 'Preparando', data: r.data_preparacao, concluido: ['preparando', 'enviado', 'entregue'].includes(r.status)},
                    {status: 'enviado', label: 'Enviado', data: r.data_envio, concluido: ['enviado', 'entregue'].includes(r.status)},
                    {status: 'entregue', label: 'Entregue', data: r.data_entrega, concluido: r.status === 'entregue'}
                ];
                
                if (!['rejeitado', 'cancelado'].includes(r.status)) {
                    timeline = '<div class="timeline-status mt-4">';
                    for (const etapa of etapas) {
                        const ativo = r.status === etapa.status;
                        const completo = etapa.concluido && !ativo;
                        timeline += `
                            <div class="status-item ${ativo ? 'active' : ''} ${completo ? 'completed' : ''}">
                                <div class="d-flex justify-content-between">
                                    <span class="${completo || ativo ? 'fw-bold' : 'text-gray-500'}">${etapa.label}</span>
                                    ${etapa.data ? `<small class="text-muted">${new Date(etapa.data).toLocaleString('pt-BR')}</small>` : ''}
                                </div>
                            </div>
                        `;
                    }
                    timeline += '</div>';
                }
                
                body.innerHTML = `
                    <div class="d-flex align-items-center mb-5">
                        <div class="symbol symbol-80px me-4">
                            ${r.produto_imagem ? 
                                `<img src="../${r.produto_imagem}" alt="">` :
                                `<span class="symbol-label bg-light-primary"><i class="ki-duotone ki-gift fs-3x text-primary"><span class="path1"></span><span class="path2"></span></i></span>`
                            }
                        </div>
                        <div>
                            <h3 class="mb-1">${r.produto_nome}</h3>
                            <span class="badge badge-light-${status.badge}">
                                <i class="ki-duotone ki-${status.icon} fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                                ${status.label}
                            </span>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <div class="text-gray-500 fs-7">Quantidade</div>
                                <div class="fs-4 fw-bold">${r.quantidade}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <div class="text-gray-500 fs-7">Pontos Gastos</div>
                                <div class="fs-4 fw-bold text-warning">${r.pontos_total.toLocaleString('pt-BR')} pts</div>
                            </div>
                        </div>
                    </div>
                    
                    ${r.codigo_rastreio ? `
                        <div class="alert alert-info mb-5">
                            <div class="d-flex align-items-center">
                                <i class="ki-duotone ki-delivery-24 fs-2x me-3"><span class="path1"></span><span class="path2"></span></i>
                                <div>
                                    <div class="fs-7 text-info">Código de Rastreio</div>
                                    <div class="fw-bold">${r.codigo_rastreio}</div>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                    
                    ${r.motivo_rejeicao ? `
                        <div class="alert alert-danger mb-5">
                            <div class="fw-bold">Motivo da rejeição:</div>
                            ${r.motivo_rejeicao}
                        </div>
                    ` : ''}
                    
                    ${r.observacao_colaborador ? `
                        <div class="mb-4">
                            <div class="text-gray-500 fs-7 mb-1">Sua observação:</div>
                            <div class="bg-light rounded p-3">${r.observacao_colaborador}</div>
                        </div>
                    ` : ''}
                    
                    ${r.observacao_admin ? `
                        <div class="mb-4">
                            <div class="text-gray-500 fs-7 mb-1">Observação do administrador:</div>
                            <div class="bg-light rounded p-3">${r.observacao_admin}</div>
                        </div>
                    ` : ''}
                    
                    ${timeline}
                `;
            } else {
                body.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(err => {
            body.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes</div>';
        });
}

function removerWishlist(produtoId, btn) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('produto_id', produtoId);
    
    fetch('../api/loja/wishlist.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.closest('.col-md-6').remove();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
