<?php
/**
 * Relatórios de Fechamentos Extras
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('relatorios_fechamentos_extras.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$filtro_mes_inicio = $_GET['mes_inicio'] ?? date('Y-m', strtotime('-6 months'));
$filtro_mes_fim = $_GET['mes_fim'] ?? date('Y-m');
$filtro_subtipo = $_GET['subtipo'] ?? '';
$filtro_empresa_id = $_GET['empresa_id'] ?? '';

// Busca empresas disponíveis
$empresas = [];
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome_fantasia FROM empresas ORDER BY nome_fantasia");
    $empresas = $stmt->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id IN ($placeholders) ORDER BY nome_fantasia");
        $stmt->execute($usuario['empresas_ids']);
        $empresas = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? ORDER BY nome_fantasia");
        $stmt->execute([$usuario['empresa_id'] ?? 0]);
        $empresas = $stmt->fetchAll();
    }
} else {
    $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? ORDER BY nome_fantasia");
    $stmt->execute([$usuario['empresa_id'] ?? 0]);
    $empresas = $stmt->fetchAll();
}

// Monta query para buscar fechamentos extras
$where = ["fp.tipo_fechamento = 'extra'"];
$params = [];

// Filtro de período
if ($filtro_mes_inicio) {
    $where[] = "fp.mes_referencia >= ?";
    $params[] = $filtro_mes_inicio;
}
if ($filtro_mes_fim) {
    $where[] = "fp.mes_referencia <= ?";
    $params[] = $filtro_mes_fim;
}

// Filtro de subtipo
if ($filtro_subtipo) {
    $where[] = "fp.subtipo_fechamento = ?";
    $params[] = $filtro_subtipo;
}

// Filtro de empresa
if ($filtro_empresa_id) {
    $where[] = "fp.empresa_id = ?";
    $params[] = $filtro_empresa_id;
} elseif ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "fp.empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    } else {
        $where[] = "fp.empresa_id = ?";
        $params[] = $usuario['empresa_id'] ?? 0;
    }
} elseif ($usuario['role'] !== 'ADMIN') {
    $where[] = "fp.empresa_id = ?";
    $params[] = $usuario['empresa_id'] ?? 0;
}

$sql = "
    SELECT 
        fp.*,
        e.nome_fantasia as empresa_nome,
        COUNT(DISTINCT fpi.colaborador_id) as total_colaboradores,
        SUM(fpi.valor_total) as total_valor
    FROM fechamentos_pagamento fp
    INNER JOIN empresas e ON fp.empresa_id = e.id
    LEFT JOIN fechamentos_pagamento_itens fpi ON fp.id = fpi.fechamento_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY fp.id
    ORDER BY fp.mes_referencia DESC, fp.data_pagamento DESC, fp.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fechamentos_extras = $stmt->fetchAll();

// Estatísticas gerais
$stats = [
    'total_fechamentos' => count($fechamentos_extras),
    'total_valor' => 0,
    'por_subtipo' => [],
    'por_mes' => []
];

$subtipos_labels = [
    'bonus_especifico' => 'Bônus Específico',
    'individual' => 'Bônus Individual',
    'grupal' => 'Bônus Grupal',
    'adiantamento' => 'Adiantamento'
];

foreach ($fechamentos_extras as $fechamento) {
    $valor = (float)($fechamento['total_valor'] ?? $fechamento['total_pagamento'] ?? 0);
    $stats['total_valor'] += $valor;
    
    $subtipo = $fechamento['subtipo_fechamento'] ?? 'outro';
    if (!isset($stats['por_subtipo'][$subtipo])) {
        $stats['por_subtipo'][$subtipo] = ['count' => 0, 'valor' => 0];
    }
    $stats['por_subtipo'][$subtipo]['count']++;
    $stats['por_subtipo'][$subtipo]['valor'] += $valor;
    
    $mes = $fechamento['mes_referencia'];
    if (!isset($stats['por_mes'][$mes])) {
        $stats['por_mes'][$mes] = ['count' => 0, 'valor' => 0];
    }
    $stats['por_mes'][$mes]['count']++;
    $stats['por_mes'][$mes]['valor'] += $valor;
}

$page_title = 'Relatórios - Fechamentos Extras';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="d-flex text-dark fw-bolder my-1 fs-3">Relatórios de Fechamentos Extras</h1>
        <ul class="breadcrumb breadcrumb-dot fw-bold text-gray-600 fs-7 my-1">
            <li class="breadcrumb-item text-gray-500">
                <a href="dashboard.php" class="text-gray-500 text-hover-primary">Dashboard</a>
            </li>
            <li class="breadcrumb-item">
                <span class="bullet bg-gray-200 w-5px h-2px"></span>
            </li>
            <li class="breadcrumb-item text-gray-900">Relatórios</li>
        </ul>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?= get_session_alert() ?>
        
        <!--begin::Card - Filtros e Estatísticas-->
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">Filtros e Estatísticas</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-7">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Mês Início</label>
                        <input type="month" name="mes_inicio" class="form-control" value="<?= htmlspecialchars($filtro_mes_inicio) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Mês Fim</label>
                        <input type="month" name="mes_fim" class="form-control" value="<?= htmlspecialchars($filtro_mes_fim) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tipo</label>
                        <select name="subtipo" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($subtipos_labels as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $filtro_subtipo === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Empresa</label>
                        <select name="empresa_id" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($empresas as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filtro_empresa_id == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['nome_fantasia']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="relatorios_fechamentos_extras.php" class="btn btn-light">Limpar</a>
                    </div>
                </form>
                
                <!-- Estatísticas -->
                <div class="row g-5">
                    <div class="col-md-3">
                        <div class="card bg-light-primary">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="text-gray-600 fw-semibold fs-6 mb-1">Total de Fechamentos</div>
                                        <div class="text-gray-900 fw-bold fs-2"><?= number_format($stats['total_fechamentos'], 0, ',', '.') ?></div>
                                    </div>
                                    <i class="ki-duotone ki-chart-simple fs-2x text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-success">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="text-gray-600 fw-semibold fs-6 mb-1">Valor Total</div>
                                        <div class="text-gray-900 fw-bold fs-2">R$ <?= number_format($stats['total_valor'], 2, ',', '.') ?></div>
                                    </div>
                                    <i class="ki-duotone ki-dollar fs-2x text-success">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-info">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="text-gray-600 fw-semibold fs-6 mb-1">Média por Fechamento</div>
                                        <div class="text-gray-900 fw-bold fs-2">
                                            R$ <?= $stats['total_fechamentos'] > 0 ? number_format($stats['total_valor'] / $stats['total_fechamentos'], 2, ',', '.') : '0,00' ?>
                                        </div>
                                    </div>
                                    <i class="ki-duotone ki-calculator fs-2x text-info">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-warning">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="text-gray-600 fw-semibold fs-6 mb-1">Por Tipo</div>
                                        <div class="text-gray-900 fw-bold fs-6">
                                            <?php foreach ($stats['por_subtipo'] as $subtipo => $data): ?>
                                            <div><?= htmlspecialchars($subtipos_labels[$subtipo] ?? $subtipo) ?>: <?= $data['count'] ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Card - Listagem-->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Fechamentos Extras</h3>
                <div class="card-toolbar">
                    <a href="fechamento_pagamentos.php" class="btn btn-light">Voltar</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3" id="kt_table_fechamentos_extras">
                        <thead>
                            <tr class="fw-bolder text-muted">
                                <th>Mês</th>
                                <th>Data Pagamento</th>
                                <th>Tipo</th>
                                <th>Empresa</th>
                                <th>Colaboradores</th>
                                <th>Valor Total</th>
                                <th>Referência</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fechamentos_extras)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-10">Nenhum fechamento extra encontrado</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($fechamentos_extras as $fechamento): ?>
                            <tr>
                                <td><?= date('m/Y', strtotime($fechamento['mes_referencia'] . '-01')) ?></td>
                                <td>
                                    <?php if ($fechamento['data_pagamento']): ?>
                                        <?= date('d/m/Y', strtotime($fechamento['data_pagamento'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?= htmlspecialchars($subtipos_labels[$fechamento['subtipo_fechamento']] ?? $fechamento['subtipo_fechamento']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($fechamento['empresa_nome']) ?></td>
                                <td><?= number_format($fechamento['total_colaboradores'] ?? 0, 0, ',', '.') ?></td>
                                <td class="fw-bold text-success">R$ <?= number_format($fechamento['total_valor'] ?? $fechamento['total_pagamento'] ?? 0, 2, ',', '.') ?></td>
                                <td>
                                    <?php if ($fechamento['referencia_externa']): ?>
                                        <span class="text-muted"><?= htmlspecialchars($fechamento['referencia_externa']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status = $fechamento['status'] ?? 'aberto';
                                    $badges = [
                                        'aberto' => '<span class="badge badge-light-warning">Aberto</span>',
                                        'fechado' => '<span class="badge badge-light-info">Fechado</span>',
                                        'pago' => '<span class="badge badge-light-success">Pago</span>'
                                    ];
                                    echo $badges[$status] ?? '<span class="badge badge-light-secondary">-</span>';
                                    ?>
                                </td>
                                <td>
                                    <a href="fechamento_pagamentos.php?view=<?= $fechamento['id'] ?>" class="btn btn-sm btn-light-primary">
                                        Ver Detalhes
                                    </a>
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

<script>
$(document).ready(function() {
    $('#kt_table_fechamentos_extras').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
        },
        order: [[0, 'desc']],
        pageLength: 25
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

