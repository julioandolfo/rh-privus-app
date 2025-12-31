<?php
/**
 * Visualizar Manual Individual
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('manual_individuais_view.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$id = $_GET['id'] ?? 0;

// Busca manual
$stmt = $pdo->prepare("
    SELECT m.*, u.nome as criado_por_nome
    FROM manuais_individuais m
    LEFT JOIN usuarios u ON m.created_by = u.id
    WHERE m.id = ?
");
$stmt->execute([$id]);
$manual = $stmt->fetch();

if (!$manual) {
    redirect('manuais_individuais.php', 'Manual não encontrado!', 'error');
}

// Busca colaboradores com acesso
$stmt = $pdo->prepare("
    SELECT c.id, c.nome_completo, c.cpf, e.nome_fantasia as empresa_nome, s.nome_setor
    FROM manuais_individuais_colaboradores mc
    INNER JOIN colaboradores c ON mc.colaborador_id = c.id
    LEFT JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN setores s ON c.setor_id = s.id
    WHERE mc.manual_id = ?
    ORDER BY c.nome_completo
");
$stmt->execute([$id]);
$colaboradores = $stmt->fetchAll();

$page_title = 'Visualizar Manual Individual';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0"><?= htmlspecialchars($manual['titulo']) ?></h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="manuais_individuais.php" class="text-muted text-hover-primary">Manuais Individuais</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Visualizar</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <?php if (in_array($usuario['role'], ['ADMIN', 'RH'])): ?>
            <a href="manual_individuais_edit.php?id=<?= $manual['id'] ?>" class="btn btn-sm btn-warning me-2">
                <i class="ki-duotone ki-pencil fs-2"></i>
                Editar
            </a>
            <?php endif; ?>
            <a href="manuais_individuais.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-arrow-left fs-2"></i>
                Voltar
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?= get_session_alert() ?>
        
        <!--begin::Card-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="fw-bold m-0"><?= htmlspecialchars($manual['titulo']) ?></h3>
                </div>
                <div class="card-toolbar">
                    <span class="badge badge-light-<?= $manual['status'] === 'ativo' ? 'success' : 'secondary' ?>">
                        <?= ucfirst($manual['status']) ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if ($manual['descricao']): ?>
                <div class="mb-5">
                    <p class="text-gray-600"><?= nl2br(htmlspecialchars($manual['descricao'])) ?></p>
                </div>
                <?php endif; ?>
                
                <div class="mb-5">
                    <h4 class="fw-bold mb-3">Conteúdo</h4>
                    <div class="border rounded p-5 bg-light">
                        <?= $manual['conteudo'] ?>
                    </div>
                </div>
                
                <div class="mb-5">
                    <h4 class="fw-bold mb-3">Colaboradores com Acesso (<?= count($colaboradores) ?>)</h4>
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Setor</th>
                                    <?php if ($usuario['role'] === 'ADMIN'): ?>
                                    <th>Empresa</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($colaboradores as $colab): ?>
                                <tr>
                                    <td>
                                        <a href="colaborador_view.php?id=<?= $colab['id'] ?>" class="text-gray-800 text-hover-primary">
                                            <?= htmlspecialchars($colab['nome_completo']) ?>
                                        </a>
                                    </td>
                                    <td><?= formatar_cpf($colab['cpf']) ?></td>
                                    <td><?= htmlspecialchars($colab['nome_setor']) ?></td>
                                    <?php if ($usuario['role'] === 'ADMIN'): ?>
                                    <td><?= htmlspecialchars($colab['empresa_nome']) ?></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="separator separator-dashed my-5"></div>
                
                <div class="d-flex flex-wrap text-muted">
                    <div class="me-5">
                        <strong>Criado por:</strong> <?= htmlspecialchars($manual['criado_por_nome'] ?? 'Sistema') ?>
                    </div>
                    <div class="me-5">
                        <strong>Data de criação:</strong> <?= formatar_data($manual['created_at']) ?>
                    </div>
                    <?php if ($manual['updated_at'] != $manual['created_at']): ?>
                    <div>
                        <strong>Última atualização:</strong> <?= formatar_data($manual['updated_at']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
