<?php
/**
 * Dashboard e Analytics de Ocorrências
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';

require_page_permission('ocorrencias_list.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros de período
$periodo = $_GET['periodo'] ?? '30'; // dias
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime("-{$periodo} days"));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

// Monta condições de acesso
$where_acesso = [];
$params_acesso = [];

if ($usuario['role'] === 'RH') {
    $where_acesso[] = "c.empresa_id = ?";
    $params_acesso[] = $usuario['empresa_id'];
} elseif ($usuario['role'] === 'GESTOR') {
    $stmt = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario['id']]);
    $user_data = $stmt->fetch();
    $setor_id = $user_data['setor_id'] ?? 0;
    
    $where_acesso[] = "c.setor_id = ?";
    $params_acesso[] = $setor_id;
}

$where_acesso_sql = !empty($where_acesso) ? 'AND ' . implode(' AND ', $where_acesso) : '';

// Estatísticas gerais
$sql_stats = "
    SELECT 
        COUNT(*) as total_ocorrencias,
        SUM(CASE WHEN o.severidade = 'leve' THEN 1 ELSE 0 END) as ocorrencias_leves,
        SUM(CASE WHEN o.severidade = 'moderada' THEN 1 ELSE 0 END) as ocorrencias_moderadas,
        SUM(CASE WHEN o.severidade = 'grave' THEN 1 ELSE 0 END) as ocorrencias_graves,
        SUM(CASE WHEN o.severidade = 'critica' THEN 1 ELSE 0 END) as ocorrencias_criticas,
        SUM(CASE WHEN o.status_aprovacao = 'pendente' THEN 1 ELSE 0 END) as pendentes,
        SUM(CASE WHEN o.status_aprovacao = 'aprovada' THEN 1 ELSE 0 END) as aprovadas,
        SUM(CASE WHEN o.status_aprovacao = 'rejeitada' THEN 1 ELSE 0 END) as rejeitadas,
        COUNT(DISTINCT o.colaborador_id) as colaboradores_envolvidos,
        SUM(o.valor_desconto) as total_descontos
    FROM ocorrencias o
    INNER JOIN colaboradores c ON o.colaborador_id = c.id
    WHERE o.data_ocorrencia BETWEEN ? AND ?
    $where_acesso_sql
";

$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute(array_merge([$data_inicio, $data_fim], $params_acesso));
$stats = $stmt_stats->fetch();

// Ocorrências por tipo
$sql_tipos = "
    SELECT 
        t.nome,
        t.categoria,
        COUNT(*) as total,
        AVG(CASE WHEN o.severidade = 'leve' THEN 1 ELSE 0 END) * 100 as pct_leve,
        AVG(CASE WHEN o.severidade = 'moderada' THEN 1 ELSE 0 END) * 100 as pct_moderada,
        AVG(CASE WHEN o.severidade = 'grave' THEN 1 ELSE 0 END) * 100 as pct_grave,
        AVG(CASE WHEN o.severidade = 'critica' THEN 1 ELSE 0 END) * 100 as pct_critica
    FROM ocorrencias o
    INNER JOIN colaboradores c ON o.colaborador_id = c.id
    LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
    WHERE o.data_ocorrencia BETWEEN ? AND ?
    $where_acesso_sql
    GROUP BY t.id, t.nome, t.categoria
    ORDER BY total DESC
    LIMIT 10
";

$stmt_tipos = $pdo->prepare($sql_tipos);
$stmt_tipos->execute(array_merge([$data_inicio, $data_fim], $params_acesso));
$ocorrencias_por_tipo = $stmt_tipos->fetchAll();

// Ocorrências por colaborador (top 10)
$sql_colab = "
    SELECT 
        c.id,
        c.nome_completo,
        COUNT(*) as total,
        SUM(CASE WHEN o.severidade = 'grave' THEN 1 ELSE 0 END) as graves,
        SUM(CASE WHEN o.severidade = 'critica' THEN 1 ELSE 0 END) as criticas,
        MAX(o.data_ocorrencia) as ultima_ocorrencia
    FROM ocorrencias o
    INNER JOIN colaboradores c ON o.colaborador_id = c.id
    WHERE o.data_ocorrencia BETWEEN ? AND ?
    $where_acesso_sql
    GROUP BY c.id, c.nome_completo
    ORDER BY total DESC
    LIMIT 10
";

$stmt_colab = $pdo->prepare($sql_colab);
$stmt_colab->execute(array_merge([$data_inicio, $data_fim], $params_acesso));
$ocorrencias_por_colab = $stmt_colab->fetchAll();

// Ocorrências por dia (últimos 30 dias)
$sql_dias = "
    SELECT 
        DATE(o.data_ocorrencia) as data,
        COUNT(*) as total
    FROM ocorrencias o
    INNER JOIN colaboradores c ON o.colaborador_id = c.id
    WHERE o.data_ocorrencia BETWEEN ? AND ?
    $where_acesso_sql
    GROUP BY DATE(o.data_ocorrencia)
    ORDER BY data ASC
";

$stmt_dias = $pdo->prepare($sql_dias);
$stmt_dias->execute(array_merge([$data_inicio, $data_fim], $params_acesso));
$ocorrencias_por_dia = $stmt_dias->fetchAll();

// Ocorrências por categoria
$sql_categoria = "
    SELECT 
        COALESCE(t.categoria, 'outros') as categoria,
        COUNT(*) as total
    FROM ocorrencias o
    INNER JOIN colaboradores c ON o.colaborador_id = c.id
    LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
    WHERE o.data_ocorrencia BETWEEN ? AND ?
    $where_acesso_sql
    GROUP BY categoria
    ORDER BY total DESC
";

$stmt_categoria = $pdo->prepare($sql_categoria);
$stmt_categoria->execute(array_merge([$data_inicio, $data_fim], $params_acesso));
$ocorrencias_por_categoria = $stmt_categoria->fetchAll();

$page_title = 'Dashboard de Ocorrências';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Dashboard de Ocorrências</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Dashboard Ocorrências</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!-- Filtros -->
        <div class="card mb-5">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Data Início</label>
                        <input type="date" name="data_inicio" class="form-control form-control-solid" value="<?= $data_inicio ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data Fim</label>
                        <input type="date" name="data_fim" class="form-control form-control-solid" value="<?= $data_fim ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Período Rápido</label>
                        <select name="periodo" class="form-select form-select-solid" onchange="this.form.submit()">
                            <option value="7" <?= $periodo == '7' ? 'selected' : '' ?>>Últimos 7 dias</option>
                            <option value="30" <?= $periodo == '30' ? 'selected' : '' ?>>Últimos 30 dias</option>
                            <option value="90" <?= $periodo == '90' ? 'selected' : '' ?>>Últimos 90 dias</option>
                            <option value="365" <?= $periodo == '365' ? 'selected' : '' ?>>Último ano</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ki-duotone ki-magnifier fs-2"></i>
                            Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Cards de Estatísticas -->
        <div class="row g-5 mb-5">
            <div class="col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <span class="text-gray-500 fw-semibold fs-6 d-block">Total de Ocorrências</span>
                                <span class="text-gray-900 fw-bold fs-2x"><?= $stats['total_ocorrencias'] ?? 0 ?></span>
                            </div>
                            <div class="symbol symbol-50px">
                                <div class="symbol-label bg-light-primary">
                                    <i class="ki-duotone ki-clipboard fs-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <span class="text-gray-500 fw-semibold fs-6 d-block">Pendentes</span>
                                <span class="text-gray-900 fw-bold fs-2x"><?= $stats['pendentes'] ?? 0 ?></span>
                            </div>
                            <div class="symbol symbol-50px">
                                <div class="symbol-label bg-light-warning">
                                    <i class="ki-duotone ki-time fs-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <span class="text-gray-500 fw-semibold fs-6 d-block">Colaboradores Envolvidos</span>
                                <span class="text-gray-900 fw-bold fs-2x"><?= $stats['colaboradores_envolvidos'] ?? 0 ?></span>
                            </div>
                            <div class="symbol symbol-50px">
                                <div class="symbol-label bg-light-info">
                                    <i class="ki-duotone ki-people fs-2x text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <span class="text-gray-500 fw-semibold fs-6 d-block">Total Descontos</span>
                                <span class="text-gray-900 fw-bold fs-2x">R$ <?= number_format($stats['total_descontos'] ?? 0, 2, ',', '.') ?></span>
                            </div>
                            <div class="symbol symbol-50px">
                                <div class="symbol-label bg-light-danger">
                                    <i class="ki-duotone ki-dollar fs-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráficos -->
        <div class="row g-5 mb-5">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Ocorrências por Severidade</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart_severidade" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Ocorrências por Categoria</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart_categoria" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-5 mb-5">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Ocorrências por Dia</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart_dias" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabelas -->
        <div class="row g-5">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Top 10 Tipos de Ocorrências</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5">
                                <thead>
                                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase">
                                        <th>Tipo</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ocorrencias_por_tipo as $tipo): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($tipo['nome'] ?? 'N/A') ?></td>
                                        <td class="text-end">
                                            <span class="badge badge-light-primary"><?= $tipo['total'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Top 10 Colaboradores com Mais Ocorrências</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5">
                                <thead>
                                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase">
                                        <th>Colaborador</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Graves</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ocorrencias_por_colab as $colab): ?>
                                    <tr>
                                        <td>
                                            <a href="colaborador_view.php?id=<?= $colab['id'] ?>" class="text-gray-800 text-hover-primary">
                                                <?= htmlspecialchars($colab['nome_completo']) ?>
                                            </a>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge badge-light-primary"><?= $colab['total'] ?></span>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge badge-light-danger"><?= ($colab['graves'] ?? 0) + ($colab['criticas'] ?? 0) ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Gráfico de Severidade
const ctxSeveridade = document.getElementById('chart_severidade');
if (ctxSeveridade) {
    new Chart(ctxSeveridade, {
        type: 'doughnut',
        data: {
            labels: ['Leve', 'Moderada', 'Grave', 'Crítica'],
            datasets: [{
                data: [
                    <?= $stats['ocorrencias_leves'] ?? 0 ?>,
                    <?= $stats['ocorrencias_moderadas'] ?? 0 ?>,
                    <?= $stats['ocorrencias_graves'] ?? 0 ?>,
                    <?= $stats['ocorrencias_criticas'] ?? 0 ?>
                ],
                backgroundColor: [
                    '#28a745',
                    '#17a2b8',
                    '#ffc107',
                    '#dc3545'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

// Gráfico de Categoria
const ctxCategoria = document.getElementById('chart_categoria');
if (ctxCategoria) {
    new Chart(ctxCategoria, {
        type: 'bar',
        data: {
            labels: [<?php foreach ($ocorrencias_por_categoria as $cat): ?>'<?= ucfirst($cat['categoria']) ?>',<?php endforeach; ?>],
            datasets: [{
                label: 'Ocorrências',
                data: [<?php foreach ($ocorrencias_por_categoria as $cat): ?><?= $cat['total'] ?>,<?php endforeach; ?>],
                backgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Gráfico de Dias
const ctxDias = document.getElementById('chart_dias');
if (ctxDias) {
    new Chart(ctxDias, {
        type: 'line',
        data: {
            labels: [<?php foreach ($ocorrencias_por_dia as $dia): ?>'<?= formatar_data($dia['data'], 'd/m') ?>',<?php endforeach; ?>],
            datasets: [{
                label: 'Ocorrências',
                data: [<?php foreach ($ocorrencias_por_dia as $dia): ?><?= $dia['total'] ?>,<?php endforeach; ?>],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

