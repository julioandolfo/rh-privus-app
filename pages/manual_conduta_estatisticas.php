<?php
/**
 * Estatísticas do Manual de Conduta
 */

$page_title = 'Estatísticas - Manual de Conduta';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/manual_conduta_functions.php';

require_page_permission('manual_conduta_edit.php'); // Apenas ADMIN

$pdo = getDB();

// Estatísticas de visualizações
$stmt = $pdo->prepare("
    SELECT 
        tipo,
        COUNT(*) as total,
        COUNT(DISTINCT usuario_id) as usuarios_unicos,
        COUNT(DISTINCT colaborador_id) as colaboradores_unicos,
        DATE(created_at) as data
    FROM manual_conduta_visualizacoes
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY tipo, DATE(created_at)
    ORDER BY data DESC
");
$stmt->execute();
$visualizacoes_30_dias = $stmt->fetchAll();

// FAQs mais visualizados
$stmt = $pdo->prepare("
    SELECT f.*, COUNT(v.id) as total_visualizacoes
    FROM faq_manual_conduta f
    LEFT JOIN manual_conduta_visualizacoes v ON v.faq_id = f.id AND v.tipo = 'faq'
    WHERE f.ativo = 1
    GROUP BY f.id
    ORDER BY total_visualizacoes DESC, f.visualizacoes DESC
    LIMIT 10
");
$stmt->execute();
$faqs_mais_visualizados = $stmt->fetchAll();

// FAQs mais úteis
$stmt = $pdo->prepare("
    SELECT * FROM faq_manual_conduta
    WHERE ativo = 1
    ORDER BY util_respondeu_sim DESC, visualizacoes DESC
    LIMIT 10
");
$stmt->execute();
$faqs_mais_uteis = $stmt->fetchAll();

// FAQs menos úteis
$stmt = $pdo->prepare("
    SELECT * FROM faq_manual_conduta
    WHERE ativo = 1 AND util_respondeu_nao > 0
    ORDER BY util_respondeu_nao DESC, util_respondeu_sim ASC
    LIMIT 10
");
$stmt->execute();
$faqs_menos_uteis = $stmt->fetchAll();

// Total de visualizações
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN tipo = 'manual' THEN 1 ELSE 0 END) as `manual`,
        SUM(CASE WHEN tipo = 'faq' THEN 1 ELSE 0 END) as faq
    FROM manual_conduta_visualizacoes
");
$stmt->execute();
$totais = $stmt->fetch();

// Visualizações detalhadas (quem visualizou)
$stmt = $pdo->prepare("
    SELECT v.*,
           u.nome as usuario_nome,
           u.email as usuario_email,
           u.role as usuario_role,
           c.nome_completo as colaborador_nome,
           c.email_pessoal as colaborador_email,
           f.pergunta as faq_pergunta
    FROM manual_conduta_visualizacoes v
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    LEFT JOIN colaboradores c ON v.colaborador_id = c.id
    LEFT JOIN faq_manual_conduta f ON v.faq_id = f.id
    ORDER BY v.created_at DESC
    LIMIT 100
");
$stmt->execute();
$visualizacoes_detalhadas = $stmt->fetchAll();

// Visualizações por usuário (resumo)
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(u.nome, c.nome_completo) as nome,
        COALESCE(u.email, c.email_pessoal) as email,
        COALESCE(u.role, 'COLABORADOR') as role,
        COUNT(*) as total_visualizacoes,
        SUM(CASE WHEN v.tipo = 'manual' THEN 1 ELSE 0 END) as visualizacoes_manual,
        SUM(CASE WHEN v.tipo = 'faq' THEN 1 ELSE 0 END) as visualizacoes_faq,
        MAX(v.created_at) as ultima_visualizacao
    FROM manual_conduta_visualizacoes v
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    LEFT JOIN colaboradores c ON v.colaborador_id = c.id
    GROUP BY COALESCE(u.id, c.id), COALESCE(u.nome, c.nome_completo), COALESCE(u.email, c.email_pessoal), COALESCE(u.role, 'COLABORADOR')
    ORDER BY total_visualizacoes DESC
    LIMIT 50
");
$stmt->execute();
$visualizacoes_por_usuario = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Estatísticas - Manual de Conduta</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="manual_conduta_view.php" class="text-muted text-hover-primary">Manual de Conduta</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Estatísticas</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Cards de Resumo-->
        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-4">
                <div class="card card-flush h-xl-100">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Total de Visualizações</span>
                            <span class="text-gray-500 mt-1 fw-semibold fs-6">Todos os tempos</span>
                        </h3>
                    </div>
                    <div class="card-body pt-6">
                        <div class="d-flex flex-column">
                            <div class="d-flex align-items-center mb-7">
                                <span class="fw-bold fs-2x text-gray-800 me-2"><?= number_format($totais['total'] ?? 0) ?></span>
                            </div>
                            <div class="separator separator-dashed my-3"></div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="fw-semibold text-gray-600">Manual</span>
                                <span class="fw-bold text-gray-800"><?= number_format($totais['manual'] ?? 0) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold text-gray-600">FAQ</span>
                                <span class="fw-bold text-gray-800"><?= number_format($totais['faq'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4">
                <div class="card card-flush h-xl-100">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">FAQs Ativos</span>
                            <span class="text-gray-500 mt-1 fw-semibold fs-6">Total cadastrado</span>
                        </h3>
                    </div>
                    <div class="card-body pt-6">
                        <?php
                        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM faq_manual_conduta WHERE ativo = 1");
                        $stmt->execute();
                        $total_faqs = $stmt->fetch();
                        ?>
                        <div class="d-flex align-items-center">
                            <span class="fw-bold fs-2x text-gray-800 me-2"><?= number_format($total_faqs['total'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4">
                <div class="card card-flush h-xl-100">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Taxa de Utilidade</span>
                            <span class="text-gray-500 mt-1 fw-semibold fs-6">Média geral</span>
                        </h3>
                    </div>
                    <div class="card-body pt-6">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT 
                                SUM(util_respondeu_sim) as total_sim,
                                SUM(util_respondeu_nao) as total_nao
                            FROM faq_manual_conduta
                            WHERE ativo = 1
                        ");
                        $stmt->execute();
                        $utilidade = $stmt->fetch();
                        $total_respostas = ($utilidade['total_sim'] ?? 0) + ($utilidade['total_nao'] ?? 0);
                        $taxa_util = $total_respostas > 0 ? (($utilidade['total_sim'] ?? 0) / $total_respostas) * 100 : 0;
                        ?>
                        <div class="d-flex align-items-center">
                            <span class="fw-bold fs-2x text-gray-800 me-2"><?= number_format($taxa_util, 1) ?>%</span>
                        </div>
                        <div class="text-muted fs-7 mt-2">
                            <?= number_format($utilidade['total_sim'] ?? 0) ?> útil / 
                            <?= number_format($utilidade['total_nao'] ?? 0) ?> não útil
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Cards-->
        
        <!--begin::FAQs Mais Visualizados-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">FAQs Mais Visualizados</span>
                    <span class="text-muted fw-semibold fs-7">Top 10</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed gy-7 align-middle">
                        <thead>
                            <tr class="fw-bold fs-6 text-gray-800">
                                <th>Pergunta</th>
                                <th style="width: 120px;">Visualizações</th>
                                <th style="width: 100px;">Útil</th>
                                <th style="width: 100px;">Não Útil</th>
                                <th style="width: 100px;">Taxa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faqs_mais_visualizados as $faq): 
                                $total_feedback = $faq['util_respondeu_sim'] + $faq['util_respondeu_nao'];
                                $taxa = $total_feedback > 0 ? ($faq['util_respondeu_sim'] / $total_feedback) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-gray-800"><?= htmlspecialchars($faq['pergunta']) ?></div>
                                    <?php if ($faq['categoria']): ?>
                                    <div class="text-muted fs-7"><?= htmlspecialchars($faq['categoria']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-light-primary"><?= number_format($faq['total_visualizacoes'] ?? $faq['visualizacoes']) ?></span>
                                </td>
                                <td>
                                    <span class="text-success"><?= number_format($faq['util_respondeu_sim']) ?></span>
                                </td>
                                <td>
                                    <span class="text-danger"><?= number_format($faq['util_respondeu_nao']) ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $taxa >= 70 ? 'badge-light-success' : ($taxa >= 50 ? 'badge-light-warning' : 'badge-light-danger') ?>">
                                        <?= number_format($taxa, 1) ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::FAQs Mais Úteis-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">FAQs Mais Úteis</span>
                    <span class="text-muted fw-semibold fs-7">Maior taxa de aprovação</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed gy-7 align-middle">
                        <thead>
                            <tr class="fw-bold fs-6 text-gray-800">
                                <th>Pergunta</th>
                                <th style="width: 120px;">Visualizações</th>
                                <th style="width: 100px;">Taxa de Utilidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faqs_mais_uteis as $faq): 
                                $total_feedback = $faq['util_respondeu_sim'] + $faq['util_respondeu_nao'];
                                $taxa = $total_feedback > 0 ? ($faq['util_respondeu_sim'] / $total_feedback) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-gray-800"><?= htmlspecialchars($faq['pergunta']) ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-light-primary"><?= number_format($faq['visualizacoes']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-light-success">
                                        <?= number_format($taxa, 1) ?>% (<?= number_format($faq['util_respondeu_sim']) ?> votos)
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
        <?php if (!empty($faqs_menos_uteis)): ?>
        <!--begin::FAQs Menos Úteis-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">FAQs que Precisam de Melhoria</span>
                    <span class="text-muted fw-semibold fs-7">Maior taxa de rejeição</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed gy-7 align-middle">
                        <thead>
                            <tr class="fw-bold fs-6 text-gray-800">
                                <th>Pergunta</th>
                                <th style="width: 120px;">Visualizações</th>
                                <th style="width: 100px;">Taxa de Utilidade</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faqs_menos_uteis as $faq): 
                                $total_feedback = $faq['util_respondeu_sim'] + $faq['util_respondeu_nao'];
                                $taxa = $total_feedback > 0 ? ($faq['util_respondeu_sim'] / $total_feedback) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-gray-800"><?= htmlspecialchars($faq['pergunta']) ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-light-primary"><?= number_format($faq['visualizacoes']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-light-danger">
                                        <?= number_format($taxa, 1) ?>%
                                    </span>
                                </td>
                                <td>
                                    <a href="faq_edit.php" class="btn btn-sm btn-light-primary">
                                        <i class="ki-duotone ki-pencil fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Editar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Card-->
        <?php endif; ?>
        
        <!--begin::Visualizações por Usuário-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Visualizações por Usuário</span>
                    <span class="text-muted fw-semibold fs-7">Top 50 usuários mais ativos</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed gy-7 align-middle">
                        <thead>
                            <tr class="fw-bold fs-6 text-gray-800">
                                <th>Usuário</th>
                                <th style="width: 100px;">Role</th>
                                <th style="width: 120px;">Total</th>
                                <th style="width: 120px;">Manual</th>
                                <th style="width: 120px;">FAQ</th>
                                <th style="width: 150px;">Última Visualização</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visualizacoes_por_usuario as $vis): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-gray-800"><?= htmlspecialchars($vis['nome'] ?? 'N/A') ?></div>
                                    <?php if ($vis['email']): ?>
                                    <div class="text-muted fs-7"><?= htmlspecialchars($vis['email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $role_colors = [
                                        'ADMIN' => 'badge-light-danger',
                                        'RH' => 'badge-light-primary',
                                        'GESTOR' => 'badge-light-warning',
                                        'COLABORADOR' => 'badge-light-success'
                                    ];
                                    $role_color = $role_colors[$vis['role']] ?? 'badge-light-secondary';
                                    ?>
                                    <span class="badge <?= $role_color ?>"><?= htmlspecialchars($vis['role']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-light-primary"><?= number_format($vis['total_visualizacoes']) ?></span>
                                </td>
                                <td>
                                    <span class="text-gray-700"><?= number_format($vis['visualizacoes_manual']) ?></span>
                                </td>
                                <td>
                                    <span class="text-gray-700"><?= number_format($vis['visualizacoes_faq']) ?></span>
                                </td>
                                <td>
                                    <span class="text-muted fs-7">
                                        <?= formatar_data($vis['ultima_visualizacao'], 'd/m/Y H:i') ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($visualizacoes_por_usuario)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">
                                    Nenhuma visualização registrada ainda.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Histórico de Visualizações-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Histórico de Visualizações</span>
                    <span class="text-muted fw-semibold fs-7">Últimas 100 visualizações</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed gy-7 align-middle">
                        <thead>
                            <tr class="fw-bold fs-6 text-gray-800">
                                <th>Data/Hora</th>
                                <th>Usuário</th>
                                <th>Tipo</th>
                                <th>Detalhes</th>
                                <th style="width: 120px;">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visualizacoes_detalhadas as $vis): ?>
                            <tr>
                                <td>
                                    <span class="text-gray-800 fw-semibold">
                                        <?= formatar_data($vis['created_at'], 'd/m/Y') ?>
                                    </span>
                                    <div class="text-muted fs-7">
                                        <?= date('H:i:s', strtotime($vis['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($vis['usuario_nome']): ?>
                                    <div class="fw-bold text-gray-800"><?= htmlspecialchars($vis['usuario_nome']) ?></div>
                                    <?php if ($vis['usuario_email']): ?>
                                    <div class="text-muted fs-7"><?= htmlspecialchars($vis['usuario_email']) ?></div>
                                    <?php endif; ?>
                                    <?php
                                    $role_colors = [
                                        'ADMIN' => 'badge-light-danger',
                                        'RH' => 'badge-light-primary',
                                        'GESTOR' => 'badge-light-warning',
                                        'COLABORADOR' => 'badge-light-success'
                                    ];
                                    $role_color = $role_colors[$vis['usuario_role']] ?? 'badge-light-secondary';
                                    ?>
                                    <span class="badge <?= $role_color ?> fs-8 mt-1"><?= htmlspecialchars($vis['usuario_role']) ?></span>
                                    <?php elseif ($vis['colaborador_nome']): ?>
                                    <div class="fw-bold text-gray-800"><?= htmlspecialchars($vis['colaborador_nome']) ?></div>
                                    <?php if ($vis['colaborador_email']): ?>
                                    <div class="text-muted fs-7"><?= htmlspecialchars($vis['colaborador_email']) ?></div>
                                    <?php endif; ?>
                                    <span class="badge badge-light-success fs-8 mt-1">COLABORADOR</span>
                                    <?php else: ?>
                                    <span class="text-muted">Visitante</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($vis['tipo'] === 'manual'): ?>
                                    <span class="badge badge-light-info">
                                        <i class="ki-duotone ki-document fs-7 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                        </i>
                                        Manual
                                    </span>
                                    <?php else: ?>
                                    <span class="badge badge-light-primary">
                                        <i class="ki-duotone ki-question fs-7 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        FAQ
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($vis['tipo'] === 'faq' && $vis['faq_pergunta']): ?>
                                    <div class="text-gray-800 fw-semibold">
                                        <?= htmlspecialchars(substr($vis['faq_pergunta'], 0, 60)) ?>
                                        <?= strlen($vis['faq_pergunta']) > 60 ? '...' : '' ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-muted fs-7"><?= htmlspecialchars($vis['ip_address'] ?? '-') ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($visualizacoes_detalhadas)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">
                                    Nenhuma visualização registrada ainda.
                                </td>
                            </tr>
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

