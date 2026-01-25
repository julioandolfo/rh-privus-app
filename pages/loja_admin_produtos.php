<?php
/**
 * Admin - Gestão de Produtos da Loja
 */

$page_title = 'Gestão de Produtos';
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

// Obtém categorias
$categorias = loja_get_categorias(false);

// Obtém produtos (todos, incluindo inativos)
$sql = "SELECT p.*, c.nome as categoria_nome, c.cor as categoria_cor,
        u.nome as criador_nome
        FROM loja_produtos p
        INNER JOIN loja_categorias c ON p.categoria_id = c.id
        LEFT JOIN usuarios u ON p.created_by = u.id
        ORDER BY p.ativo DESC, p.destaque DESC, p.nome";
$produtos = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.produto-thumb {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
}
.produto-thumb-placeholder {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f8fa;
    border-radius: 8px;
}
</style>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">
                <i class="ki-duotone ki-package fs-2 me-2 text-primary">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Gestão de Produtos
            </h1>
            <span class="text-muted mt-1 fw-semibold fs-7">Gerencie os produtos da loja de pontos</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="loja_admin_categorias.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-category fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                Categorias
            </a>
            <a href="loja_admin_resgates.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-basket fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                Resgates
            </a>
            <a href="loja_admin_config.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-setting-2 fs-4"><span class="path1"></span><span class="path2"></span></i>
                Configurações
            </a>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal_produto">
                <i class="ki-duotone ki-plus fs-4"></i>
                Novo Produto
            </button>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <div class="card card-flush">
            <div class="card-header border-0 pt-5">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                        <input type="text" class="form-control form-control-solid w-250px ps-12" 
                               placeholder="Buscar produto..." id="busca_produto">
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-solid w-150px" id="filtro_categoria">
                            <option value="">Todas categorias</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-select form-select-solid w-120px" id="filtro_status">
                            <option value="">Todos</option>
                            <option value="1">Ativos</option>
                            <option value="0">Inativos</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0">
                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4" id="tabela_produtos">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th class="min-w-200px">Produto</th>
                            <th class="min-w-100px">Categoria</th>
                            <th class="min-w-80px text-center">Pontos</th>
                            <th class="min-w-80px text-center">Estoque</th>
                            <th class="min-w-80px text-center">Resgates</th>
                            <th class="min-w-80px text-center">Status</th>
                            <th class="min-w-100px text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $prod): 
                            $estoque_baixo = $prod['estoque'] !== null && $prod['estoque'] <= loja_config('estoque_baixo_limite', 5);
                        ?>
                        <tr data-categoria="<?= $prod['categoria_id'] ?>" data-ativo="<?= $prod['ativo'] ?>">
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if ($prod['imagem']): ?>
                                    <img src="../<?= htmlspecialchars($prod['imagem']) ?>" class="produto-thumb me-3">
                                    <?php else: ?>
                                    <div class="produto-thumb-placeholder me-3">
                                        <i class="ki-duotone ki-package fs-2x text-gray-400">
                                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                        </i>
                                    </div>
                                    <?php endif; ?>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800 fw-bold"><?= htmlspecialchars($prod['nome']) ?></span>
                                        <span class="text-gray-500 fs-7"><?= htmlspecialchars($prod['descricao_curta'] ?? '') ?></span>
                                    </div>
                                    <?php if ($prod['destaque']): ?>
                                    <span class="badge badge-light-warning ms-2">Destaque</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-light-<?= $prod['categoria_cor'] ?>">
                                    <?= htmlspecialchars($prod['categoria_nome']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-light-warning fs-6">
                                    <?= number_format($prod['pontos_necessarios'], 0, ',', '.') ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($prod['estoque'] === null): ?>
                                    <span class="badge badge-light">Ilimitado</span>
                                <?php elseif ($prod['estoque'] == 0): ?>
                                    <span class="badge badge-danger">Esgotado</span>
                                <?php elseif ($estoque_baixo): ?>
                                    <span class="badge badge-warning"><?= $prod['estoque'] ?></span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?= $prod['estoque'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold"><?= $prod['total_resgates'] ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($prod['ativo']): ?>
                                    <span class="badge badge-light-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-light-danger">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-icon btn-light-primary" onclick="editarProduto(<?= $prod['id'] ?>)" title="Editar">
                                    <i class="ki-duotone ki-pencil fs-5"><span class="path1"></span><span class="path2"></span></i>
                                </button>
                                <button class="btn btn-sm btn-icon btn-light-danger" onclick="excluirProduto(<?= $prod['id'] ?>, '<?= htmlspecialchars(addslashes($prod['nome'])) ?>')" title="Excluir">
                                    <i class="ki-duotone ki-trash fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<!-- Modal de Produto -->
<div class="modal fade" id="modal_produto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="modal_produto_titulo">Novo Produto</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <form id="form_produto" enctype="multipart/form-data">
                <div class="modal-body py-5">
                    <input type="hidden" name="id" id="produto_id">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-5">
                                <label class="form-label required">Nome do Produto</label>
                                <input type="text" name="nome" id="produto_nome" class="form-control form-control-solid" required>
                            </div>
                            
                            <div class="row mb-5">
                                <div class="col-md-6">
                                    <label class="form-label required">Categoria</label>
                                    <select name="categoria_id" id="produto_categoria" class="form-select form-select-solid" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Pontos Necessários</label>
                                    <input type="number" name="pontos_necessarios" id="produto_pontos" class="form-control form-control-solid" min="1" required>
                                </div>
                            </div>
                            
                            <div class="mb-5">
                                <label class="form-label">Descrição Curta</label>
                                <input type="text" name="descricao_curta" id="produto_descricao_curta" class="form-control form-control-solid" maxlength="255">
                            </div>
                            
                            <div class="mb-5">
                                <label class="form-label">Descrição Completa</label>
                                <textarea name="descricao" id="produto_descricao" class="form-control form-control-solid" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-5">
                                <label class="form-label">Imagem</label>
                                <div class="image-input image-input-outline" id="produto_imagem_container">
                                    <div class="image-input-wrapper w-125px h-125px" id="produto_imagem_preview" style="background-image: url('../assets/media/svg/files/blank-image.svg'); background-size: 50%; background-position: center;"></div>
                                    <label class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow" title="Alterar imagem">
                                        <i class="ki-duotone ki-pencil fs-7"><span class="path1"></span><span class="path2"></span></i>
                                        <input type="file" name="imagem" accept=".png,.jpg,.jpeg,.gif,.webp" onchange="previewImagem(this)">
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-5">
                                <label class="form-label">Estoque</label>
                                <input type="number" name="estoque" id="produto_estoque" class="form-control form-control-solid" min="0" placeholder="Vazio = ilimitado">
                                <div class="form-text">Deixe vazio para estoque ilimitado</div>
                            </div>
                            
                            <div class="mb-5">
                                <label class="form-label">Limite por Colaborador</label>
                                <input type="number" name="limite_por_colaborador" id="produto_limite" class="form-control form-control-solid" min="1" placeholder="Vazio = sem limite">
                            </div>
                        </div>
                    </div>
                    
                    <div class="separator my-5"></div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-5">
                                <label class="form-label">Disponível de</label>
                                <input type="date" name="disponivel_de" id="produto_disponivel_de" class="form-control form-control-solid">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-5">
                                <label class="form-label">Disponível até</label>
                                <input type="date" name="disponivel_ate" id="produto_disponivel_ate" class="form-control form-control-solid">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-5">
                                <label class="form-label d-block">Destaque</label>
                                <div class="form-check form-switch form-check-custom form-check-solid mt-3">
                                    <input class="form-check-input" type="checkbox" name="destaque" id="produto_destaque" value="1">
                                    <label class="form-check-label" for="produto_destaque">Exibir em destaque</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-5">
                                <label class="form-label d-block">Status</label>
                                <div class="form-check form-switch form-check-custom form-check-solid mt-3">
                                    <input class="form-check-input" type="checkbox" name="ativo" id="produto_ativo" value="1" checked>
                                    <label class="form-check-label" for="produto_ativo">Ativo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Salvando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Filtros
document.getElementById('busca_produto').addEventListener('input', filtrarTabela);
document.getElementById('filtro_categoria').addEventListener('change', filtrarTabela);
document.getElementById('filtro_status').addEventListener('change', filtrarTabela);

function filtrarTabela() {
    const busca = document.getElementById('busca_produto').value.toLowerCase();
    const categoria = document.getElementById('filtro_categoria').value;
    const status = document.getElementById('filtro_status').value;
    
    document.querySelectorAll('#tabela_produtos tbody tr').forEach(row => {
        const texto = row.textContent.toLowerCase();
        const cat = row.dataset.categoria;
        const ativo = row.dataset.ativo;
        
        let mostrar = true;
        if (busca && !texto.includes(busca)) mostrar = false;
        if (categoria && cat !== categoria) mostrar = false;
        if (status !== '' && ativo !== status) mostrar = false;
        
        row.style.display = mostrar ? '' : 'none';
    });
}

function previewImagem(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('produto_imagem_preview').style.backgroundImage = `url('${e.target.result}')`;
            document.getElementById('produto_imagem_preview').style.backgroundSize = 'cover';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function limparForm() {
    document.getElementById('form_produto').reset();
    document.getElementById('produto_id').value = '';
    document.getElementById('modal_produto_titulo').textContent = 'Novo Produto';
    document.getElementById('produto_imagem_preview').style.backgroundImage = "url('../assets/media/svg/files/blank-image.svg')";
    document.getElementById('produto_imagem_preview').style.backgroundSize = '50%';
    document.getElementById('produto_ativo').checked = true;
}

document.getElementById('modal_produto').addEventListener('hidden.bs.modal', limparForm);

function editarProduto(id) {
    fetch('../api/loja/produtos.php?action=detalhe&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const p = data.produto;
                document.getElementById('modal_produto_titulo').textContent = 'Editar Produto';
                document.getElementById('produto_id').value = p.id;
                document.getElementById('produto_nome').value = p.nome;
                document.getElementById('produto_categoria').value = p.categoria_id;
                document.getElementById('produto_pontos').value = p.pontos_necessarios;
                document.getElementById('produto_descricao_curta').value = p.descricao_curta || '';
                document.getElementById('produto_descricao').value = p.descricao || '';
                document.getElementById('produto_estoque').value = p.estoque ?? '';
                document.getElementById('produto_limite').value = p.limite_por_colaborador ?? '';
                document.getElementById('produto_disponivel_de').value = p.disponivel_de || '';
                document.getElementById('produto_disponivel_ate').value = p.disponivel_ate || '';
                document.getElementById('produto_destaque').checked = p.destaque == 1;
                document.getElementById('produto_ativo').checked = p.ativo == 1;
                
                if (p.imagem) {
                    document.getElementById('produto_imagem_preview').style.backgroundImage = `url('../${p.imagem}')`;
                    document.getElementById('produto_imagem_preview').style.backgroundSize = 'cover';
                }
                
                new bootstrap.Modal(document.getElementById('modal_produto')).show();
            }
        });
}

document.getElementById('form_produto').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = this.querySelector('button[type="submit"]');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const formData = new FormData(this);
    formData.append('action', 'salvar');
    
    fetch('../api/loja/produtos.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Sucesso!',
                text: data.message,
                icon: 'success'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                title: 'Erro',
                text: data.message,
                icon: 'error'
            });
        }
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
    })
    .catch(err => {
        Swal.fire({
            title: 'Erro',
            text: 'Erro ao salvar produto',
            icon: 'error'
        });
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
    });
});

function excluirProduto(id, nome) {
    Swal.fire({
        title: 'Confirmar exclusão?',
        text: `Deseja excluir o produto "${nome}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Sim, excluir!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'excluir');
            formData.append('id', id);
            
            fetch('../api/loja/produtos.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Excluído!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
