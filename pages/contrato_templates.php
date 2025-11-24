<?php
/**
 * Listar Templates de Contrato
 */

$page_title = 'Templates de Contrato';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('contrato_templates.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'excluir') {
    $id = intval($_POST['id'] ?? 0);
    
    if ($id > 0) {
        try {
            // Verifica se o template foi criado pelo usuário ou se é ADMIN
            $stmt = $pdo->prepare("SELECT criado_por_usuario_id FROM contratos_templates WHERE id = ?");
            $stmt->execute([$id]);
            $template = $stmt->fetch();
            
            if ($template && ($template['criado_por_usuario_id'] == $usuario['id'] || $usuario['role'] === 'ADMIN')) {
                // Verifica se há contratos usando este template
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM contratos WHERE template_id = ?");
                $stmt->execute([$id]);
                $uso = $stmt->fetch();
                
                if ($uso['total'] > 0) {
                    redirect('contrato_templates.php', 'Não é possível excluir este template pois existem contratos usando-o.', 'error');
                }
                
                $stmt = $pdo->prepare("DELETE FROM contratos_templates WHERE id = ?");
                $stmt->execute([$id]);
                redirect('contrato_templates.php', 'Template excluído com sucesso!', 'success');
            } else {
                redirect('contrato_templates.php', 'Você não tem permissão para excluir este template.', 'error');
            }
        } catch (PDOException $e) {
            redirect('contrato_templates.php', 'Erro ao excluir template: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca templates
$stmt = $pdo->query("
    SELECT t.*, u.nome as criado_por_nome,
           (SELECT COUNT(*) FROM contratos WHERE template_id = t.id) as total_uso
    FROM contratos_templates t
    LEFT JOIN usuarios u ON t.criado_por_usuario_id = u.id
    ORDER BY t.created_at DESC
");
$templates = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Templates de Contrato</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">Contratos</li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Templates</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <a href="contrato_template_add.php" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Adicionar Template
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
                    <span class="card-label fw-bold fs-3 mb-1">Lista de Templates</span>
                    <span class="text-muted fw-semibold fs-7"><?= count($templates) ?> template(s)</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th class="min-w-200px">Nome</th>
                                <th class="min-w-150px">Criado por</th>
                                <th class="min-w-100px text-center">Status</th>
                                <th class="min-w-100px text-center">Uso</th>
                                <th class="min-w-150px">Data Criação</th>
                                <th class="min-w-100px text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($templates)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">
                                    Nenhum template encontrado
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($templates as $template): ?>
                            <tr>
                                <td>
                                    <span class="text-gray-900 fw-bold d-block fs-6"><?= htmlspecialchars($template['nome']) ?></span>
                                    <?php if ($template['descricao']): ?>
                                    <span class="text-muted fs-7"><?= htmlspecialchars($template['descricao']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-gray-900 fw-semibold d-block fs-7"><?= htmlspecialchars($template['criado_por_nome'] ?? '-') ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($template['ativo']): ?>
                                    <span class="badge badge-light-success">Ativo</span>
                                    <?php else: ?>
                                    <span class="badge badge-light-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="text-gray-900 fw-bold"><?= $template['total_uso'] ?></span>
                                </td>
                                <td>
                                    <span class="text-gray-700 fw-semibold d-block fs-7">
                                        <?= date('d/m/Y H:i', strtotime($template['created_at'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="contrato_template_edit.php?id=<?= $template['id'] ?>" class="btn btn-icon btn-light-primary btn-sm me-2" title="Editar">
                                        <i class="ki-duotone ki-notepad-edit fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </a>
                                    <?php if ($template['criado_por_usuario_id'] == $usuario['id'] || $usuario['role'] === 'ADMIN'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este template?');">
                                        <input type="hidden" name="action" value="excluir">
                                        <input type="hidden" name="id" value="<?= $template['id'] ?>">
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

