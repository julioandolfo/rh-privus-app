<?php
/**
 * Visualizar Comunicado com Analytics
 */

$page_title = 'Visualizar Comunicado';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('comunicado_view.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$comunicado_id = intval($_GET['id'] ?? 0);

if ($comunicado_id <= 0) {
    redirect('comunicados.php', 'Comunicado não encontrado.', 'error');
}

// Busca comunicado
$stmt = $pdo->prepare("
    SELECT c.*, u.nome as criado_por_nome
    FROM comunicados c
    LEFT JOIN usuarios u ON c.criado_por_usuario_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$comunicado_id]);
$comunicado = $stmt->fetch();

if (!$comunicado) {
    redirect('comunicados.php', 'Comunicado não encontrado.', 'error');
}

// Busca estatísticas
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_visualizacoes,
        SUM(CASE WHEN lido = 1 THEN 1 ELSE 0 END) as total_lidos,
        SUM(vezes_visualizado) as total_vezes_visualizado
    FROM comunicados_leitura
    WHERE comunicado_id = ?
");
$stmt->execute([$comunicado_id]);
$stats = $stmt->fetch();

// Busca leituras detalhadas
$stmt = $pdo->prepare("
    SELECT cl.*, 
           COALESCE(u.nome, c.nome_completo) as nome,
           COALESCE(u.email, c.email_pessoal) as email,
           cl.usuario_id IS NOT NULL as tem_usuario
    FROM comunicados_leitura cl
    LEFT JOIN usuarios u ON cl.usuario_id = u.id
    LEFT JOIN colaboradores c ON cl.colaborador_id = c.id
    WHERE cl.comunicado_id = ?
    ORDER BY cl.data_leitura DESC, cl.data_visualizacao DESC
");
$stmt->execute([$comunicado_id]);
$leituras = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Visualizar Comunicado</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">Comunicados</li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Visualizar</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <a href="comunicados.php" class="btn btn-light">Voltar</a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Card - Comunicado-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1"><?= htmlspecialchars($comunicado['titulo']) ?></span>
                    <span class="text-muted fw-semibold fs-7">
                        Criado por <?= htmlspecialchars($comunicado['criado_por_nome']) ?> em <?= date('d/m/Y H:i', strtotime($comunicado['created_at'])) ?>
                    </span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <?php if ($comunicado['imagem']): ?>
                <div class="mb-10 text-center">
                    <img src="../<?= htmlspecialchars($comunicado['imagem']) ?>" alt="<?= htmlspecialchars($comunicado['titulo']) ?>" class="img-fluid rounded" style="max-height: 400px;" />
                </div>
                <?php endif; ?>
                
                <div class="fs-6 fw-semibold text-gray-700">
                    <?= $comunicado['conteudo'] ?>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Card - Estatísticas-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Estatísticas</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <div class="row">
                    <div class="col-md-4">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7 fw-semibold mb-2">Total de Visualizações</span>
                            <span class="text-gray-900 fw-bold fs-2"><?= $stats['total_visualizacoes'] ?? 0 ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7 fw-semibold mb-2">Total de Leituras</span>
                            <span class="text-gray-900 fw-bold fs-2"><?= $stats['total_lidos'] ?? 0 ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7 fw-semibold mb-2">Vezes Visualizado</span>
                            <span class="text-gray-900 fw-bold fs-2"><?= $stats['total_vezes_visualizado'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Card - Leituras Detalhadas-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Quem Visualizou</span>
                    <span class="text-muted fw-semibold fs-7"><?= count($leituras) ?> pessoa(s)</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th class="min-w-200px">Nome</th>
                                <th class="min-w-150px">Email</th>
                                <th class="min-w-100px text-center">Vezes Visualizado</th>
                                <th class="min-w-100px text-center">Marcou como Lido</th>
                                <th class="min-w-150px">Última Visualização</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leituras)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">
                                    Nenhuma visualização registrada
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($leituras as $leitura): ?>
                            <tr>
                                <td>
                                    <span class="text-gray-900 fw-bold d-block fs-6"><?= htmlspecialchars($leitura['nome'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <span class="text-gray-700 fw-semibold d-block fs-7"><?= htmlspecialchars($leitura['email'] ?? '-') ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="text-gray-900 fw-bold"><?= $leitura['vezes_visualizado'] ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($leitura['lido']): ?>
                                    <span class="badge badge-light-success">Sim</span>
                                    <?php if ($leitura['data_leitura']): ?>
                                    <div class="text-muted fs-7 mt-1"><?= date('d/m/Y H:i', strtotime($leitura['data_leitura'])) ?></div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="badge badge-light-warning">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-gray-700 fw-semibold d-block fs-7">
                                        <?= $leitura['data_visualizacao'] ? date('d/m/Y H:i', strtotime($leitura['data_visualizacao'])) : '-' ?>
                                    </span>
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

