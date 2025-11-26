<?php
/**
 * Gerenciar Badges/Conquistas
 */

$page_title = 'Gerenciar Badges';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_badges.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $nome = sanitize($_POST['nome'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $icone = sanitize($_POST['icone'] ?? '');
        $cor = sanitize($_POST['cor'] ?? '#ffc700');
        $tipo = $_POST['tipo'] ?? 'curso_completo';
        $ativo = isset($_POST['ativo']) && $_POST['ativo'] == '1' ? 1 : 0;
        
        if (empty($nome)) {
            redirect('lms_badges.php', 'Preencha o nome do badge!', 'error');
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO badges_conquistas (nome, descricao, icone, cor, tipo, ativo)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nome, $descricao ?: null, $icone ?: null, $cor, $tipo, $ativo]);
            redirect('lms_badges.php', 'Badge criado com sucesso!', 'success');
        } catch (PDOException $e) {
            error_log("Erro ao criar badge: " . $e->getMessage());
            redirect('lms_badges.php', 'Erro ao criar badge.', 'error');
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = sanitize($_POST['nome'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $icone = sanitize($_POST['icone'] ?? '');
        $cor = sanitize($_POST['cor'] ?? '#ffc700');
        $tipo = $_POST['tipo'] ?? 'curso_completo';
        $ativo = isset($_POST['ativo']) && $_POST['ativo'] == '1' ? 1 : 0;
        
        if (empty($nome) || !$id) {
            redirect('lms_badges.php', 'Dados inválidos!', 'error');
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE badges_conquistas 
                SET nome = ?, descricao = ?, icone = ?, cor = ?, tipo = ?, ativo = ?
                WHERE id = ?
            ");
            $stmt->execute([$nome, $descricao ?: null, $icone ?: null, $cor, $tipo, $ativo, $id]);
            redirect('lms_badges.php', 'Badge atualizado com sucesso!', 'success');
        } catch (PDOException $e) {
            error_log("Erro ao atualizar badge: " . $e->getMessage());
            redirect('lms_badges.php', 'Erro ao atualizar badge.', 'error');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if (!$id) {
            redirect('lms_badges.php', 'ID inválido!', 'error');
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM badges_conquistas WHERE id = ?");
            $stmt->execute([$id]);
            redirect('lms_badges.php', 'Badge excluído com sucesso!', 'success');
        } catch (PDOException $e) {
            error_log("Erro ao excluir badge: " . $e->getMessage());
            redirect('lms_badges.php', 'Erro ao excluir badge.', 'error');
        }
    }
}

// Busca badges
$stmt = $pdo->query("
    SELECT b.*, 
           COUNT(cb.id) as total_conquistas
    FROM badges_conquistas b
    LEFT JOIN colaborador_badges cb ON cb.badge_id = b.id
    GROUP BY b.id
    ORDER BY b.nome ASC
");
$badges = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Gerenciar Badges</h1>
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
                <li class="breadcrumb-item text-gray-900">Badges</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBadge">
                <i class="ki-duotone ki-plus fs-2"></i>
                Novo Badge
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
                    <span class="card-label fw-bold fs-3 mb-1">Badges/Conquistas</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($badges) ?> badge(s)</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="row g-5">
                    <?php if (empty($badges)): ?>
                    <div class="col-12 text-center p-10">
                        <div class="text-muted">Nenhum badge cadastrado</div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($badges as $badge): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100">
                                <div class="card-body text-center p-5">
                                    <?php if ($badge['icone']): ?>
                                    <i class="ki-duotone ki-<?= htmlspecialchars($badge['icone']) ?> fs-3x mb-4" style="color: <?= htmlspecialchars($badge['cor'] ?? '#ffc700') ?>;">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <?php else: ?>
                                    <div class="mb-4" style="width: 80px; height: 80px; margin: 0 auto; background-color: <?= htmlspecialchars($badge['cor'] ?? '#ffc700') ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="ki-duotone ki-award fs-2x text-white">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <h4 class="fw-bold mb-2"><?= htmlspecialchars($badge['nome']) ?></h4>
                                    <?php if ($badge['descricao']): ?>
                                    <p class="text-muted mb-3"><?= htmlspecialchars($badge['descricao']) ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <span class="badge badge-light"><?= ucfirst(str_replace('_', ' ', $badge['tipo'])) ?></span>
                                        <?php if ($badge['ativo']): ?>
                                        <span class="badge badge-success">Ativo</span>
                                        <?php else: ?>
                                        <span class="badge badge-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="text-muted fs-7 mb-3">
                                        <?= $badge['total_conquistas'] ?> conquista(s)
                                    </div>
                                    
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="editarBadge(<?= htmlspecialchars(json_encode($badge)) ?>)">
                                            <i class="ki-duotone ki-pencil fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este badge?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $badge['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="ki-duotone ki-trash fs-4">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal Badge-->
<div class="modal fade" id="modalBadge" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formBadge">
                <input type="hidden" name="action" id="actionBadge" value="add">
                <input type="hidden" name="id" id="idBadge">
                
                <div class="modal-header">
                    <h2 class="modal-title" id="modalBadgeTitle">Novo Badge</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-5">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required id="nomeBadge">
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3" id="descricaoBadge"></textarea>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Ícone</label>
                            <input type="text" name="icone" class="form-control" placeholder="ex: award" id="iconeBadge">
                            <div class="form-text">Nome do ícone do Metronic</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cor</label>
                            <input type="color" name="cor" class="form-control form-control-color" value="#ffc700" id="corBadge">
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" id="tipoBadge">
                                <option value="curso_completo">Curso Completo</option>
                                <option value="sequencia">Sequência</option>
                                <option value="desempenho">Desempenho</option>
                                <option value="personalizado">Personalizado</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="ativo" class="form-select" id="ativoBadge">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
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
<!--end::Modal Badge-->

<script>
function editarBadge(badge) {
    document.getElementById('modalBadgeTitle').textContent = 'Editar Badge';
    document.getElementById('actionBadge').value = 'edit';
    document.getElementById('idBadge').value = badge.id;
    document.getElementById('nomeBadge').value = badge.nome || '';
    document.getElementById('descricaoBadge').value = badge.descricao || '';
    document.getElementById('iconeBadge').value = badge.icone || '';
    document.getElementById('corBadge').value = badge.cor || '#ffc700';
    document.getElementById('tipoBadge').value = badge.tipo || 'curso_completo';
    document.getElementById('ativoBadge').value = badge.ativo ? '1' : '0';
    
    const modal = new bootstrap.Modal(document.getElementById('modalBadge'));
    modal.show();
}

// Reset modal ao fechar
document.getElementById('modalBadge').addEventListener('hidden.bs.modal', function() {
    document.getElementById('modalBadgeTitle').textContent = 'Novo Badge';
    document.getElementById('actionBadge').value = 'add';
    document.getElementById('formBadge').reset();
    document.getElementById('idBadge').value = '';
    document.getElementById('corBadge').value = '#ffc700';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

