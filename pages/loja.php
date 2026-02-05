<?php
/**
 * Loja de Pontos - Catálogo de Produtos
 */

$page_title = 'Loja de Pontos';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/loja_functions.php';
require_once __DIR__ . '/../includes/pontuacao.php';

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $usuario['colaborador_id'] ?? null;

// Verifica se a loja está ativa
if (!loja_ativa() && !in_array($usuario['role'], ['ADMIN', 'RH', 'GESTOR'])) {
    redirect('dashboard.php', loja_config('mensagem_loja_fechada', 'Loja temporariamente fechada.'), 'warning');
}

// Obtém pontos e saldo em R$ do colaborador
$meus_pontos = $colaborador_id ? obter_pontos(null, $colaborador_id) : ['pontos_totais' => 0];
$meu_saldo_dinheiro = $colaborador_id ? obter_saldo_dinheiro($colaborador_id) : 0;

// Obtém categorias
$categorias = loja_get_categorias();

// Obtém produtos em destaque
$produtos_destaque = loja_get_produtos(['destaque' => true, 'em_estoque' => true, 'limite' => 4]);

// Filtros
$categoria_filtro = $_GET['categoria'] ?? null;
$busca = $_GET['busca'] ?? null;
$ordem = $_GET['ordem'] ?? null;

$filtros = [
    'categoria_id' => $categoria_filtro,
    'busca' => $busca,
    'ordem' => $ordem,
    'em_estoque' => true
];

$produtos = loja_get_produtos($filtros);

// Wishlist
$wishlist_ids = [];
if ($colaborador_id) {
    $wishlist = loja_get_wishlist($colaborador_id);
    $wishlist_ids = array_column($wishlist, 'id');
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.produto-card {
    transition: all 0.3s ease;
    border: 1px solid transparent;
}
.produto-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border-color: var(--bs-primary);
}
.produto-imagem {
    height: 180px;
    object-fit: cover;
    background: linear-gradient(135deg, #f5f8fa 0%, #e9ecef 100%);
}
.produto-imagem-placeholder {
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f5f8fa 0%, #e9ecef 100%);
}
.badge-destaque {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 1;
}
.badge-estoque {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1;
}
.btn-wishlist {
    position: absolute;
    bottom: 10px;
    right: 10px;
    z-index: 1;
}
.btn-wishlist.active i {
    color: #dc3545 !important;
}
.pontos-badge {
    font-size: 1.1rem;
}
.categoria-chip {
    cursor: pointer;
    transition: all 0.2s ease;
}
.categoria-chip:hover, .categoria-chip.active {
    transform: scale(1.05);
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
                <i class="ki-duotone ki-shop fs-2 me-2 text-primary">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                    <span class="path5"></span>
                </i>
                Loja de Pontos
            </h1>
            <span class="text-muted mt-1 fw-semibold fs-7">Troque seus pontos por produtos incríveis!</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="loja_meus_resgates.php" class="btn btn-sm btn-light-primary">
                <i class="ki-duotone ki-basket fs-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                Meus Resgates
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
            <?php if ($meu_saldo_dinheiro > 0): ?>
            <div class="rounded px-4 py-2 text-white" style="background: linear-gradient(135deg, #1e7e34 0%, #28a745 100%);">
                <div class="d-flex align-items-center gap-2">
                    <i class="ki-duotone ki-dollar fs-2x text-white">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        <div class="fs-8 opacity-75">Créditos</div>
                        <div class="fs-3 fw-bold">R$ <?= number_format($meu_saldo_dinheiro, 2, ',', '.') ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?php if (!loja_ativa()): ?>
        <div class="alert alert-warning d-flex align-items-center mb-5">
            <i class="ki-duotone ki-information-5 fs-2x text-warning me-4">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <div>
                <h4 class="mb-1 text-warning">Loja fechada para colaboradores</h4>
                <span>Você está vendo a loja em modo administrador. Colaboradores não podem acessar no momento.</span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Produtos em Destaque -->
        <?php if (!empty($produtos_destaque) && !$categoria_filtro && !$busca): ?>
        <div class="mb-8">
            <h3 class="text-gray-800 fw-bold mb-5">
                <i class="ki-duotone ki-star fs-2 text-warning me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Destaques
            </h3>
            <div class="row g-5">
                <?php foreach ($produtos_destaque as $produto): 
                    $pode_resgatar = $meus_pontos['pontos_totais'] >= $produto['pontos_necessarios'];
                    $na_wishlist = in_array($produto['id'], $wishlist_ids);
                    $estoque_baixo = $produto['estoque'] !== null && $produto['estoque'] <= loja_config('estoque_baixo_limite', 5);
                ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card card-flush produto-card h-100 position-relative">
                        <span class="badge badge-warning badge-destaque">
                            <i class="ki-duotone ki-star fs-7 me-1"><span class="path1"></span><span class="path2"></span></i>
                            Destaque
                        </span>
                        <?php if ($produto['estoque'] !== null && $produto['estoque'] == 0): ?>
                            <span class="badge badge-danger badge-estoque">Esgotado</span>
                        <?php elseif ($estoque_baixo): ?>
                            <span class="badge badge-warning badge-estoque">Últimas unidades</span>
                        <?php endif; ?>
                        
                        <?php if ($produto['imagem']): ?>
                            <img src="../<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" class="card-img-top produto-imagem">
                        <?php else: ?>
                            <div class="produto-imagem-placeholder">
                                <i class="ki-duotone ki-<?= $produto['categoria_icone'] ?> fs-3x text-gray-400">
                                    <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                                </i>
                            </div>
                        <?php endif; ?>
                        
                        <button class="btn btn-icon btn-sm btn-light btn-wishlist <?= $na_wishlist ? 'active' : '' ?>" 
                                onclick="toggleWishlist(<?= $produto['id'] ?>, this)" title="Lista de desejos">
                            <i class="ki-duotone ki-heart fs-4 <?= $na_wishlist ? 'text-danger' : '' ?>">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                        </button>
                        
                        <div class="card-body">
                            <span class="badge badge-light-<?= $produto['categoria_cor'] ?> mb-2"><?= htmlspecialchars($produto['categoria_nome']) ?></span>
                            <h4 class="card-title text-gray-800 mb-2"><?= htmlspecialchars($produto['nome']) ?></h4>
                            <p class="text-gray-600 fs-7 mb-3"><?= htmlspecialchars($produto['descricao_curta'] ?? '') ?></p>
                        </div>
                        <div class="card-footer border-top pt-4">
                            <?php 
                                $tem_preco_dinheiro = !empty($produto['preco_dinheiro']) && floatval($produto['preco_dinheiro']) > 0;
                                $pode_pagar_pontos = $meus_pontos['pontos_totais'] >= $produto['pontos_necessarios'];
                                $pode_pagar_dinheiro = $tem_preco_dinheiro && $meu_saldo_dinheiro >= floatval($produto['preco_dinheiro']);
                                $pode_resgatar_alguma_forma = $pode_pagar_pontos || $pode_pagar_dinheiro;
                                $disponivel_estoque = $produto['estoque'] === null || $produto['estoque'] > 0;
                            ?>
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge badge-light-warning pontos-badge">
                                            <i class="ki-duotone ki-medal-star fs-5 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                            <?= number_format($produto['pontos_necessarios'], 0, ',', '.') ?> pts
                                        </span>
                                        <?php if ($tem_preco_dinheiro): ?>
                                        <span class="badge badge-light-success pontos-badge">
                                            <i class="ki-duotone ki-dollar fs-5 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                            R$ <?= number_format($produto['preco_dinheiro'], 2, ',', '.') ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-sm btn-<?= $pode_resgatar_alguma_forma && $disponivel_estoque ? 'primary' : 'secondary' ?>" 
                                            onclick="abrirModalResgate(<?= $produto['id'] ?>)"
                                            <?= !$pode_resgatar_alguma_forma || !$disponivel_estoque ? 'disabled' : '' ?>>
                                        <?= !$disponivel_estoque ? 'Esgotado' : 'Resgatar' ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Filtros e Busca -->
        <div class="card card-flush mb-5">
            <div class="card-body py-4">
                <div class="row align-items-center g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="ki-duotone ki-magnifier fs-4"><span class="path1"></span><span class="path2"></span></i></span>
                            <input type="text" class="form-control" placeholder="Buscar produtos..." id="busca_produto" value="<?= htmlspecialchars($busca ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filtro_categoria">
                            <option value="">Todas as categorias</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoria_filtro == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome']) ?> (<?= $cat['total_produtos'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filtro_ordem">
                            <option value="">Ordenar por</option>
                            <option value="pontos_asc" <?= $ordem == 'pontos_asc' ? 'selected' : '' ?>>Menor pontuação</option>
                            <option value="pontos_desc" <?= $ordem == 'pontos_desc' ? 'selected' : '' ?>>Maior pontuação</option>
                            <option value="nome" <?= $ordem == 'nome' ? 'selected' : '' ?>>Nome A-Z</option>
                            <option value="popular" <?= $ordem == 'popular' ? 'selected' : '' ?>>Mais populares</option>
                            <option value="recente" <?= $ordem == 'recente' ? 'selected' : '' ?>>Mais recentes</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-light-primary w-100" onclick="aplicarFiltros()">
                            <i class="ki-duotone ki-filter fs-4"><span class="path1"></span><span class="path2"></span></i>
                            Filtrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Categorias -->
        <div class="d-flex gap-3 flex-wrap mb-5">
            <a href="loja.php" class="btn btn-sm <?= !$categoria_filtro ? 'btn-primary' : 'btn-light' ?> categoria-chip">
                Todos
            </a>
            <?php foreach ($categorias as $cat): ?>
            <a href="loja.php?categoria=<?= $cat['id'] ?>" 
               class="btn btn-sm <?= $categoria_filtro == $cat['id'] ? 'btn-primary' : 'btn-light' ?> categoria-chip">
                <i class="ki-duotone ki-<?= $cat['icone'] ?> fs-5 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                <?= htmlspecialchars($cat['nome']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Lista de Produtos -->
        <div class="row g-5" id="lista_produtos">
            <?php if (empty($produtos)): ?>
            <div class="col-12">
                <div class="card card-flush">
                    <div class="card-body text-center py-15">
                        <i class="ki-duotone ki-shop fs-5x text-gray-300 mb-5">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span>
                        </i>
                        <h3 class="text-gray-700 mb-3">Nenhum produto encontrado</h3>
                        <p class="text-gray-500">Tente ajustar os filtros ou volte mais tarde.</p>
                        <a href="loja.php" class="btn btn-light-primary mt-3">Ver todos os produtos</a>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($produtos as $produto): 
                    $pode_resgatar = $meus_pontos['pontos_totais'] >= $produto['pontos_necessarios'];
                    $na_wishlist = in_array($produto['id'], $wishlist_ids);
                    $estoque_baixo = $produto['estoque'] !== null && $produto['estoque'] <= loja_config('estoque_baixo_limite', 5);
                    $dias_novidade = loja_config('dias_novidade', 7);
                    $is_novidade = strtotime($produto['created_at']) > strtotime("-{$dias_novidade} days");
                ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card card-flush produto-card h-100 position-relative">
                        <?php if ($produto['destaque']): ?>
                            <span class="badge badge-warning badge-destaque">Destaque</span>
                        <?php elseif ($is_novidade): ?>
                            <span class="badge badge-info badge-destaque">Novo</span>
                        <?php endif; ?>
                        
                        <?php if ($produto['estoque'] !== null && $produto['estoque'] == 0): ?>
                            <span class="badge badge-danger badge-estoque">Esgotado</span>
                        <?php elseif ($estoque_baixo): ?>
                            <span class="badge badge-warning badge-estoque">Últimas unidades</span>
                        <?php endif; ?>
                        
                        <?php if ($produto['imagem']): ?>
                            <img src="../<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" class="card-img-top produto-imagem">
                        <?php else: ?>
                            <div class="produto-imagem-placeholder">
                                <i class="ki-duotone ki-<?= $produto['categoria_icone'] ?> fs-3x text-gray-400">
                                    <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                                </i>
                            </div>
                        <?php endif; ?>
                        
                        <button class="btn btn-icon btn-sm btn-light btn-wishlist <?= $na_wishlist ? 'active' : '' ?>" 
                                onclick="toggleWishlist(<?= $produto['id'] ?>, this)" title="Lista de desejos">
                            <i class="ki-duotone ki-heart fs-4 <?= $na_wishlist ? 'text-danger' : '' ?>">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                        </button>
                        
                        <div class="card-body">
                            <span class="badge badge-light-<?= $produto['categoria_cor'] ?> mb-2"><?= htmlspecialchars($produto['categoria_nome']) ?></span>
                            <h5 class="card-title text-gray-800 mb-2"><?= htmlspecialchars($produto['nome']) ?></h5>
                            <p class="text-gray-600 fs-7 mb-0"><?= htmlspecialchars($produto['descricao_curta'] ?? '') ?></p>
                        </div>
                        <div class="card-footer border-top pt-4">
                            <?php 
                                $tem_preco_dinheiro = !empty($produto['preco_dinheiro']) && floatval($produto['preco_dinheiro']) > 0;
                                $pode_pagar_pontos = $meus_pontos['pontos_totais'] >= $produto['pontos_necessarios'];
                                $pode_pagar_dinheiro = $tem_preco_dinheiro && $meu_saldo_dinheiro >= floatval($produto['preco_dinheiro']);
                                $pode_resgatar_alguma_forma = $pode_pagar_pontos || $pode_pagar_dinheiro;
                                $disponivel_estoque = $produto['estoque'] === null || $produto['estoque'] > 0;
                            ?>
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge badge-light-warning pontos-badge">
                                            <i class="ki-duotone ki-medal-star fs-5 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                            <?= number_format($produto['pontos_necessarios'], 0, ',', '.') ?> pts
                                        </span>
                                        <?php if ($tem_preco_dinheiro): ?>
                                        <span class="badge badge-light-success pontos-badge">
                                            <i class="ki-duotone ki-dollar fs-5 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                            R$ <?= number_format($produto['preco_dinheiro'], 2, ',', '.') ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-sm btn-<?= $pode_resgatar_alguma_forma && $disponivel_estoque ? 'primary' : 'secondary' ?>" 
                                            onclick="abrirModalResgate(<?= $produto['id'] ?>)"
                                            <?= !$pode_resgatar_alguma_forma || !$disponivel_estoque ? 'disabled' : '' ?>>
                                        <?= !$disponivel_estoque ? 'Esgotado' : 'Resgatar' ?>
                                    </button>
                                </div>
                                <?php if (!$pode_resgatar_alguma_forma && $disponivel_estoque): ?>
                                <div class="text-center">
                                    <small class="text-muted">
                                        <?php if (!$pode_pagar_pontos): ?>
                                            Faltam <?= number_format($produto['pontos_necessarios'] - $meus_pontos['pontos_totais'], 0, ',', '.') ?> pts
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    </div>
</div>
<!--end::Post-->

<!-- Modal de Resgate -->
<div class="modal fade" id="modal_resgate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Confirmar Resgate</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body" id="modal_resgate_body">
                <div class="text-center py-10">
                    <span class="spinner-border text-primary"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const meusPontos = <?= $meus_pontos['pontos_totais'] ?>;
const meuSaldoDinheiro = <?= $meu_saldo_dinheiro ?>;

function aplicarFiltros() {
    const busca = document.getElementById('busca_produto').value;
    const categoria = document.getElementById('filtro_categoria').value;
    const ordem = document.getElementById('filtro_ordem').value;
    
    let url = 'loja.php?';
    if (busca) url += 'busca=' + encodeURIComponent(busca) + '&';
    if (categoria) url += 'categoria=' + categoria + '&';
    if (ordem) url += 'ordem=' + ordem + '&';
    
    window.location.href = url.slice(0, -1);
}

document.getElementById('busca_produto').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') aplicarFiltros();
});

function toggleWishlist(produtoId, btn) {
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
            btn.classList.toggle('active');
            const icon = btn.querySelector('i');
            icon.classList.toggle('text-danger');
        }
    });
}

function abrirModalResgate(produtoId) {
    const modal = new bootstrap.Modal(document.getElementById('modal_resgate'));
    const body = document.getElementById('modal_resgate_body');
    
    body.innerHTML = '<div class="text-center py-10"><span class="spinner-border text-primary"></span></div>';
    modal.show();
    
    fetch('../api/loja/produtos.php?action=detalhe&id=' + produtoId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const p = data.produto;
                const podePontos = meusPontos >= p.pontos_necessarios;
                const temPrecoDinheiro = p.preco_dinheiro && parseFloat(p.preco_dinheiro) > 0;
                const podeDinheiro = temPrecoDinheiro && meuSaldoDinheiro >= parseFloat(p.preco_dinheiro);
                const podeAlgumaForma = podePontos || podeDinheiro;
                const estoqueBaixo = p.estoque !== null && p.estoque <= 5;
                
                // HTML para escolha de forma de pagamento
                let formaPagamentoHtml = '';
                if (temPrecoDinheiro && podeAlgumaForma) {
                    formaPagamentoHtml = `
                        <div class="mb-5">
                            <label class="form-label fw-semibold">Forma de Pagamento</label>
                            <div class="d-flex gap-4">
                                <label class="d-flex flex-stack cursor-pointer">
                                    <span class="d-flex align-items-center me-2">
                                        <span class="form-check form-check-custom form-check-solid">
                                            <input class="form-check-input" type="radio" name="forma_pagamento" value="pontos" 
                                                   ${podePontos ? 'checked' : 'disabled'}>
                                        </span>
                                        <span class="d-flex flex-column ms-3">
                                            <span class="fw-bold text-gray-800 fs-6">Pontos</span>
                                            <span class="fs-7 text-muted">${p.pontos_necessarios.toLocaleString('pt-BR')} pts</span>
                                            ${!podePontos ? '<span class="badge badge-light-danger fs-8">Saldo insuficiente</span>' : ''}
                                        </span>
                                    </span>
                                </label>
                                <label class="d-flex flex-stack cursor-pointer">
                                    <span class="d-flex align-items-center me-2">
                                        <span class="form-check form-check-custom form-check-solid">
                                            <input class="form-check-input" type="radio" name="forma_pagamento" value="dinheiro" 
                                                   ${!podePontos && podeDinheiro ? 'checked' : ''} ${!podeDinheiro ? 'disabled' : ''}>
                                        </span>
                                        <span class="d-flex flex-column ms-3">
                                            <span class="fw-bold text-gray-800 fs-6">Créditos R$</span>
                                            <span class="fs-7 text-muted">R$ ${parseFloat(p.preco_dinheiro).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                                            ${!podeDinheiro ? '<span class="badge badge-light-danger fs-8">Saldo insuficiente</span>' : ''}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    `;
                } else if (podeAlgumaForma) {
                    formaPagamentoHtml = '<input type="hidden" name="forma_pagamento" value="pontos">';
                }
                
                body.innerHTML = `
                    <div class="text-center mb-5">
                        ${p.imagem ? 
                            `<img src="../${p.imagem}" alt="${p.nome}" class="rounded" style="max-height: 200px;">` :
                            `<div class="bg-light rounded p-10"><i class="ki-duotone ki-${p.categoria_icone} fs-5x text-gray-400"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i></div>`
                        }
                    </div>
                    
                    <h3 class="text-center mb-3">${p.nome}</h3>
                    <p class="text-gray-600 text-center mb-5">${p.descricao || p.descricao_curta || ''}</p>
                    
                    <div class="d-flex justify-content-center gap-4 mb-5 flex-wrap">
                        <div class="border rounded p-3 text-center">
                            <div class="fs-7 text-gray-500">Seus Pontos</div>
                            <div class="fs-3 fw-bold ${podePontos ? 'text-success' : 'text-muted'}">${meusPontos.toLocaleString('pt-BR')} pts</div>
                        </div>
                        <div class="border rounded p-3 text-center">
                            <div class="fs-7 text-gray-500">Custo em Pontos</div>
                            <div class="fs-3 fw-bold text-warning">${p.pontos_necessarios.toLocaleString('pt-BR')} pts</div>
                        </div>
                        ${temPrecoDinheiro ? `
                        <div class="border rounded p-3 text-center">
                            <div class="fs-7 text-gray-500">Seu Saldo R$</div>
                            <div class="fs-3 fw-bold ${podeDinheiro ? 'text-success' : 'text-muted'}">R$ ${meuSaldoDinheiro.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                        </div>
                        <div class="border rounded p-3 text-center">
                            <div class="fs-7 text-gray-500">Custo em R$</div>
                            <div class="fs-3 fw-bold text-success">R$ ${parseFloat(p.preco_dinheiro).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${estoqueBaixo ? `<div class="alert alert-warning text-center mb-5">Últimas ${p.estoque} unidades!</div>` : ''}
                    
                    ${podeAlgumaForma ? `
                        <form id="form_resgate">
                            <input type="hidden" name="produto_id" value="${p.id}">
                            
                            ${formaPagamentoHtml}
                            
                            <div class="mb-5">
                                <label class="form-label">Observação (opcional)</label>
                                <textarea name="observacao" class="form-control form-control-solid" rows="2" 
                                          placeholder="Alguma observação sobre a entrega?"></textarea>
                            </div>
                            
                            <div class="text-center">
                                <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">
                                    <span class="indicator-label">
                                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        Confirmar Resgate
                                    </span>
                                    <span class="indicator-progress">Processando...
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                            </div>
                        </form>
                    ` : `
                        <div class="alert alert-danger text-center">
                            <i class="ki-duotone ki-information-5 fs-2x mb-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            <div>Você não tem saldo suficiente para este produto.</div>
                            ${!podePontos ? `<div class="mt-2">Faltam <strong>${(p.pontos_necessarios - meusPontos).toLocaleString('pt-BR')}</strong> pontos.</div>` : ''}
                            ${temPrecoDinheiro && !podeDinheiro ? `<div class="mt-1">Ou faltam <strong>R$ ${(parseFloat(p.preco_dinheiro) - meuSaldoDinheiro).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong> em créditos.</div>` : ''}
                        </div>
                        <div class="text-center">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    `}
                `;
                
                // Form submit
                const form = document.getElementById('form_resgate');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        processarResgate(this);
                    });
                }
            } else {
                body.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(err => {
            body.innerHTML = `<div class="alert alert-danger">Erro ao carregar produto</div>`;
        });
}

function processarResgate(form) {
    const btn = form.querySelector('button[type="submit"]');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const formData = new FormData(form);
    
    fetch('../api/loja/resgatar.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Resgate Realizado!',
                text: data.message,
                icon: 'success',
                confirmButtonText: 'Ver meus resgates'
            }).then(() => {
                window.location.href = 'loja_meus_resgates.php';
            });
        } else {
            Swal.fire({
                title: 'Erro',
                text: data.message,
                icon: 'error'
            });
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
        }
    })
    .catch(err => {
        Swal.fire({
            title: 'Erro',
            text: 'Erro ao processar resgate',
            icon: 'error'
        });
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
