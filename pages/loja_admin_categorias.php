<?php
/**
 * Admin - Gestão de Categorias da Loja
 */

$page_title = 'Categorias da Loja';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/loja_functions.php';

require_login();

$usuario = $_SESSION['usuario'];
if (!in_array($usuario['role'], ['ADMIN', 'RH'])) {
    redirect('dashboard.php', 'Sem permissão para acessar esta página.', 'danger');
}

$pdo = getDB();

// Obtém categorias
$categorias = loja_get_categorias(false);

// Cores disponíveis
$cores = ['primary', 'success', 'info', 'warning', 'danger', 'dark'];

// Ícones sugeridos
$icones = [
    'ki-category' => 'Categoria',
    'ki-technology-2' => 'Tecnologia',
    'ki-gift' => 'Presente',
    'ki-rocket' => 'Experiência',
    'ki-home-2' => 'Casa',
    'ki-heart' => 'Bem-estar',
    'ki-cup' => 'Copa/Troféu',
    'ki-book' => 'Livro',
    'ki-music' => 'Música',
    'ki-game' => 'Jogo',
    'ki-car-2' => 'Carro',
    'ki-coffee' => 'Café',
    'ki-abstract-26' => 'Outros'
];

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">
                <i class="ki-duotone ki-category fs-2 me-2 text-primary">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                Categorias da Loja
            </h1>
            <span class="text-muted mt-1 fw-semibold fs-7">Gerencie as categorias de produtos</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="loja_admin_produtos.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-package fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                Produtos
            </a>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal_categoria">
                <i class="ki-duotone ki-plus fs-4"></i>
                Nova Categoria
            </button>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <div class="row g-5">
            <?php foreach ($categorias as $cat): ?>
            <div class="col-md-4 col-lg-3">
                <div class="card card-flush h-100">
                    <div class="card-body text-center py-8">
                        <div class="symbol symbol-65px symbol-circle mb-5">
                            <span class="symbol-label bg-light-<?= $cat['cor'] ?>">
                                <i class="ki-duotone ki-<?= str_replace('ki-', '', $cat['icone']) ?> fs-2x text-<?= $cat['cor'] ?>">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                            </span>
                        </div>
                        
                        <h4 class="text-gray-800 fw-bold mb-2"><?= htmlspecialchars($cat['nome']) ?></h4>
                        <p class="text-gray-500 fs-7 mb-4"><?= htmlspecialchars($cat['descricao'] ?? '') ?></p>
                        
                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <span class="badge badge-light"><?= $cat['total_produtos'] ?> produtos</span>
                            <span class="badge badge-light">Ordem: <?= $cat['ordem'] ?></span>
                            <?php if (!$cat['ativo']): ?>
                            <span class="badge badge-light-danger">Inativa</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex justify-content-center gap-2">
                            <button class="btn btn-sm btn-light-primary" onclick="editarCategoria(<?= htmlspecialchars(json_encode($cat)) ?>)">
                                <i class="ki-duotone ki-pencil fs-5"><span class="path1"></span><span class="path2"></span></i>
                                Editar
                            </button>
                            <?php if ($cat['total_produtos'] == 0): ?>
                            <button class="btn btn-sm btn-light-danger" onclick="excluirCategoria(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['nome'])) ?>')">
                                <i class="ki-duotone ki-trash fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Card para adicionar -->
            <div class="col-md-4 col-lg-3">
                <div class="card card-flush h-100 border-dashed border-2" style="cursor:pointer" data-bs-toggle="modal" data-bs-target="#modal_categoria">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center py-8">
                        <i class="ki-duotone ki-plus-circle fs-4x text-gray-300 mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <span class="text-gray-500 fw-bold">Adicionar Categoria</span>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<!-- Modal de Categoria -->
<div class="modal fade" id="modal_categoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="modal_categoria_titulo">Nova Categoria</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <form id="form_categoria">
                <div class="modal-body py-5">
                    <input type="hidden" name="id" id="categoria_id">
                    
                    <div class="mb-5">
                        <label class="form-label required">Nome</label>
                        <input type="text" name="nome" id="categoria_nome" class="form-control form-control-solid" required>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" id="categoria_descricao" class="form-control form-control-solid" rows="2"></textarea>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Ícone</label>
                            <select name="icone" id="categoria_icone" class="form-select form-select-solid">
                                <?php foreach ($icones as $icon => $label): ?>
                                <option value="<?= $icon ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cor</label>
                            <select name="cor" id="categoria_cor" class="form-select form-select-solid">
                                <?php foreach ($cores as $cor): ?>
                                <option value="<?= $cor ?>"><?= ucfirst($cor) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Ordem de Exibição</label>
                            <input type="number" name="ordem" id="categoria_ordem" class="form-control form-control-solid" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-switch form-check-custom form-check-solid mt-3">
                                <input class="form-check-input" type="checkbox" name="ativo" id="categoria_ativo" value="1" checked>
                                <label class="form-check-label" for="categoria_ativo">Ativa</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preview -->
                    <div class="bg-light rounded p-5 text-center">
                        <label class="form-label">Preview</label>
                        <div class="symbol symbol-65px symbol-circle">
                            <span class="symbol-label" id="preview_categoria">
                                <i class="ki-duotone ki-category fs-2x">
                                    <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                                </i>
                            </span>
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
// Atualiza preview
document.getElementById('categoria_icone').addEventListener('change', atualizarPreview);
document.getElementById('categoria_cor').addEventListener('change', atualizarPreview);

function atualizarPreview() {
    const icone = document.getElementById('categoria_icone').value.replace('ki-', '');
    const cor = document.getElementById('categoria_cor').value;
    const preview = document.getElementById('preview_categoria');
    
    preview.className = `symbol-label bg-light-${cor}`;
    preview.innerHTML = `<i class="ki-duotone ki-${icone} fs-2x text-${cor}"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>`;
}

function limparForm() {
    document.getElementById('form_categoria').reset();
    document.getElementById('categoria_id').value = '';
    document.getElementById('modal_categoria_titulo').textContent = 'Nova Categoria';
    document.getElementById('categoria_ativo').checked = true;
    atualizarPreview();
}

document.getElementById('modal_categoria').addEventListener('hidden.bs.modal', limparForm);

function editarCategoria(cat) {
    document.getElementById('modal_categoria_titulo').textContent = 'Editar Categoria';
    document.getElementById('categoria_id').value = cat.id;
    document.getElementById('categoria_nome').value = cat.nome;
    document.getElementById('categoria_descricao').value = cat.descricao || '';
    document.getElementById('categoria_icone').value = cat.icone;
    document.getElementById('categoria_cor').value = cat.cor;
    document.getElementById('categoria_ordem').value = cat.ordem;
    document.getElementById('categoria_ativo').checked = cat.ativo == 1;
    atualizarPreview();
    
    new bootstrap.Modal(document.getElementById('modal_categoria')).show();
}

document.getElementById('form_categoria').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = this.querySelector('button[type="submit"]');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const formData = new FormData(this);
    formData.append('action', 'salvar');
    
    fetch('../api/loja/categorias.php', {
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
            text: 'Erro ao salvar categoria',
            icon: 'error'
        });
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
    });
});

function excluirCategoria(id, nome) {
    Swal.fire({
        title: 'Confirmar exclusão?',
        text: `Deseja excluir a categoria "${nome}"?`,
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
            
            fetch('../api/loja/categorias.php', {
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
