<?php
/**
 * Gerenciar Categorias de Cursos
 */

$page_title = 'Categorias de Cursos';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_categorias_cursos.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $nome = sanitize($_POST['nome'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $icone = sanitize($_POST['icone'] ?? '');
        $cor = sanitize($_POST['cor'] ?? '#009ef7');
        $ordem = !empty($_POST['ordem']) ? (int)$_POST['ordem'] : 0;
        $status = $_POST['status'] ?? 'ativo';
        
        if (empty($nome)) {
            redirect('lms_categorias_cursos.php', 'Preencha o nome da categoria!', 'error');
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO categorias_cursos (nome, descricao, icone, cor, ordem, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nome, $descricao ?: null, $icone ?: null, $cor, $ordem, $status]);
            redirect('lms_categorias_cursos.php', 'Categoria criada com sucesso!', 'success');
        } catch (PDOException $e) {
            error_log("Erro ao criar categoria: " . $e->getMessage());
            redirect('lms_categorias_cursos.php', 'Erro ao criar categoria.', 'error');
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = sanitize($_POST['nome'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $icone = sanitize($_POST['icone'] ?? '');
        $cor = sanitize($_POST['cor'] ?? '#009ef7');
        $ordem = !empty($_POST['ordem']) ? (int)$_POST['ordem'] : 0;
        $status = $_POST['status'] ?? 'ativo';
        
        if (empty($nome) || !$id) {
            redirect('lms_categorias_cursos.php', 'Dados inválidos!', 'error');
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE categorias_cursos 
                SET nome = ?, descricao = ?, icone = ?, cor = ?, ordem = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$nome, $descricao ?: null, $icone ?: null, $cor, $ordem, $status, $id]);
            redirect('lms_categorias_cursos.php', 'Categoria atualizada com sucesso!', 'success');
        } catch (PDOException $e) {
            error_log("Erro ao atualizar categoria: " . $e->getMessage());
            redirect('lms_categorias_cursos.php', 'Erro ao atualizar categoria.', 'error');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if (!$id) {
            redirect('lms_categorias_cursos.php', 'ID inválido!', 'error');
        }
        
        try {
            // Verifica se há cursos usando esta categoria
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cursos WHERE categoria_id = ?");
            $stmt->execute([$id]);
            $check = $stmt->fetch();
            
            if ($check['total'] > 0) {
                redirect('lms_categorias_cursos.php', 'Não é possível excluir categoria com cursos associados!', 'error');
            }
            
            $stmt = $pdo->prepare("DELETE FROM categorias_cursos WHERE id = ?");
            $stmt->execute([$id]);
            redirect('lms_categorias_cursos.php', 'Categoria excluída com sucesso!', 'success');
        } catch (PDOException $e) {
            error_log("Erro ao excluir categoria: " . $e->getMessage());
            redirect('lms_categorias_cursos.php', 'Erro ao excluir categoria.', 'error');
        }
    }
}

// Busca categorias
$stmt = $pdo->query("SELECT * FROM categorias_cursos ORDER BY ordem ASC, nome ASC");
$categorias = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Categorias de Cursos</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="lms_cursos.php" class="text-muted text-hover-primary">Escola Privus</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Categorias</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Categoria
            </button>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Categorias</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($categorias) ?> categoria(s)</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-50px">ID</th>
                                <th class="min-w-200px">Nome</th>
                                <th class="min-w-100px">Ícone</th>
                                <th class="min-w-100px">Cor</th>
                                <th class="min-w-100px">Ordem</th>
                                <th class="min-w-100px">Status</th>
                                <th class="min-w-100px text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php if (empty($categorias)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-10">
                                    <div class="text-muted">Nenhuma categoria cadastrada</div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($categorias as $cat): ?>
                                <tr>
                                    <td><?= $cat['id'] ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($cat['nome']) ?></div>
                                        <?php if ($cat['descricao']): ?>
                                        <div class="text-muted fs-7"><?= htmlspecialchars(substr($cat['descricao'], 0, 60)) ?>...</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cat['icone']): ?>
                                        <i class="ki-duotone ki-<?= htmlspecialchars($cat['icone']) ?> fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: <?= htmlspecialchars($cat['cor']) ?>; color: white;">
                                            <?= htmlspecialchars($cat['cor']) ?>
                                        </span>
                                    </td>
                                    <td><?= $cat['ordem'] ?></td>
                                    <td>
                                        <?php
                                        $status_class = $cat['status'] === 'ativo' ? 'success' : 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $status_class ?>"><?= ucfirst($cat['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="editarCategoria(<?= htmlspecialchars(json_encode($cat)) ?>)">
                                            <i class="ki-duotone ki-pencil fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta categoria?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="ki-duotone ki-trash fs-4">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal Categoria-->
<div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formCategoria">
                <input type="hidden" name="action" id="actionCategoria" value="add">
                <input type="hidden" name="id" id="idCategoria">
                
                <div class="modal-header">
                    <h2 class="modal-title" id="modalCategoriaTitle">Nova Categoria</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-5">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required id="nomeCategoria">
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3" id="descricaoCategoria"></textarea>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Ícone</label>
                            <input type="text" name="icone" class="form-control" placeholder="ex: profile-circle" id="iconeCategoria">
                            <div class="form-text">Nome do ícone do Metronic</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cor</label>
                            <input type="color" name="cor" class="form-control form-control-color" value="#009ef7" id="corCategoria">
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="ordem" class="form-control" value="0" id="ordemCategoria">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" id="statusCategoria">
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal Categoria-->

<script>
function editarCategoria(cat) {
    document.getElementById('modalCategoriaTitle').textContent = 'Editar Categoria';
    document.getElementById('actionCategoria').value = 'edit';
    document.getElementById('idCategoria').value = cat.id;
    document.getElementById('nomeCategoria').value = cat.nome || '';
    document.getElementById('descricaoCategoria').value = cat.descricao || '';
    document.getElementById('iconeCategoria').value = cat.icone || '';
    document.getElementById('corCategoria').value = cat.cor || '#009ef7';
    document.getElementById('ordemCategoria').value = cat.ordem || 0;
    document.getElementById('statusCategoria').value = cat.status || 'ativo';
    
    const modal = new bootstrap.Modal(document.getElementById('modalCategoria'));
    modal.show();
}

// Reset modal ao fechar
document.getElementById('modalCategoria').addEventListener('hidden.bs.modal', function() {
    document.getElementById('modalCategoriaTitle').textContent = 'Nova Categoria';
    document.getElementById('actionCategoria').value = 'add';
    document.getElementById('formCategoria').reset();
    document.getElementById('idCategoria').value = '';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

