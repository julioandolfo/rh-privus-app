<?php
/**
 * Listar Comunicados
 */

$page_title = 'Comunicados';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('comunicados.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'excluir') {
    $id = intval($_POST['id'] ?? 0);
    
    if ($id > 0) {
        try {
            // Verifica se o comunicado foi criado pelo usuário ou se é ADMIN
            $stmt = $pdo->prepare("SELECT criado_por_usuario_id FROM comunicados WHERE id = ?");
            $stmt->execute([$id]);
            $comunicado = $stmt->fetch();
            
            if ($comunicado && ($comunicado['criado_por_usuario_id'] == $usuario['id'] || $usuario['role'] === 'ADMIN')) {
                $stmt = $pdo->prepare("DELETE FROM comunicados WHERE id = ?");
                $stmt->execute([$id]);
                redirect('comunicados.php', 'Comunicado excluído com sucesso!', 'success');
            } else {
                redirect('comunicados.php', 'Você não tem permissão para excluir este comunicado.', 'error');
            }
        } catch (PDOException $e) {
            redirect('comunicados.php', 'Erro ao excluir comunicado: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca comunicados
$stmt = $pdo->query("
    SELECT c.*, u.nome as criado_por_nome,
           (SELECT COUNT(*) FROM comunicados_leitura cl WHERE cl.comunicado_id = c.id AND cl.lido = 1) as total_lidos,
           (SELECT COUNT(*) FROM comunicados_leitura cl WHERE cl.comunicado_id = c.id) as total_visualizacoes
    FROM comunicados c
    LEFT JOIN usuarios u ON c.criado_por_usuario_id = u.id
    ORDER BY c.created_at DESC
");
$comunicados = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Comunicados</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Comunicados</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <a href="comunicado_add.php" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Adicionar Novo
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Card-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Lista de Comunicados</span>
                    <span class="text-muted fw-semibold fs-7"><?= count($comunicados) ?> comunicado(s)</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th class="min-w-200px">Título</th>
                                <th class="min-w-100px">Status</th>
                                <th class="min-w-150px">Criado por</th>
                                <th class="min-w-150px">Data Publicação</th>
                                <th class="min-w-100px text-center">Visualizações</th>
                                <th class="min-w-100px text-center">Lidos</th>
                                <th class="min-w-100px text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($comunicados)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-10">
                                    Nenhum comunicado encontrado
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($comunicados as $comunicado): ?>
                            <tr>
                                <td>
                                    <span class="text-gray-900 fw-bold d-block fs-6"><?= htmlspecialchars($comunicado['titulo']) ?></span>
                                    <span class="text-muted fs-7"><?= date('d/m/Y H:i', strtotime($comunicado['created_at'])) ?></span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = [
                                        'rascunho' => 'warning',
                                        'publicado' => 'success',
                                        'arquivado' => 'secondary'
                                    ];
                                    $status_label = [
                                        'rascunho' => 'Rascunho',
                                        'publicado' => 'Publicado',
                                        'arquivado' => 'Arquivado'
                                    ];
                                    $class = $status_class[$comunicado['status']] ?? 'secondary';
                                    $label = $status_label[$comunicado['status']] ?? ucfirst($comunicado['status']);
                                    ?>
                                    <span class="badge badge-light-<?= $class ?>"><?= $label ?></span>
                                </td>
                                <td>
                                    <span class="text-gray-900 fw-semibold d-block fs-7"><?= htmlspecialchars($comunicado['criado_por_nome'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <span class="text-gray-900 fw-semibold d-block fs-7">
                                        <?= $comunicado['data_publicacao'] ? date('d/m/Y H:i', strtotime($comunicado['data_publicacao'])) : '-' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="text-gray-900 fw-bold"><?= $comunicado['total_visualizacoes'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="text-gray-900 fw-bold"><?= $comunicado['total_lidos'] ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="comunicado_view.php?id=<?= $comunicado['id'] ?>" class="btn btn-icon btn-light-primary btn-sm me-2" title="Ver">
                                        <i class="ki-duotone ki-eye fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </a>
                                    <?php if ($comunicado['criado_por_usuario_id'] == $usuario['id'] || $usuario['role'] === 'ADMIN'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este comunicado?');">
                                        <input type="hidden" name="action" value="excluir">
                                        <input type="hidden" name="id" value="<?= $comunicado['id'] ?>">
                                        <button type="submit" class="btn btn-icon btn-light-danger btn-sm" title="Excluir">
                                            <i class="ki-duotone ki-trash fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                                <span class="path5"></span>
                                            </i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

