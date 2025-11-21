<?php
/**
 * Dashboard - Página Inicial (Metronic Theme com Gráficos)
 */

// IMPORTANTE: Garante que nenhum output seja enviado antes dos headers
ob_start();

// Headers para evitar cache e garantir resposta correta
header('Cache-Control: no-cache, no-store, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Verifica login ANTES de incluir o header
require_login();

// Limpa buffer antes de incluir header (que vai gerar HTML)
ob_end_clean();

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Contadores gerais
try {
    // Total de colaboradores
    if ($usuario['role'] === 'ADMIN') {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM colaboradores");
    } elseif ($usuario['role'] === 'RH') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE empresa_id = ?");
        $stmt->execute([$usuario['empresa_id']]);
    } elseif ($usuario['role'] === 'GESTOR') {
        // Busca setor do gestor
        $stmt = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario['id']]);
        $user_data = $stmt->fetch();
        $setor_id = $user_data['setor_id'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE setor_id = ?");
        $stmt->execute([$setor_id]);
    } else {
        $total = 1; // Colaborador vê apenas ele mesmo
    }
    
    $total_colaboradores = isset($stmt) ? $stmt->fetch()['total'] : 1;
    
    // Colaboradores ativos
    if ($usuario['role'] === 'ADMIN') {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM colaboradores WHERE status = 'ativo'");
    } elseif ($usuario['role'] === 'RH') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE empresa_id = ? AND status = 'ativo'");
        $stmt->execute([$usuario['empresa_id']]);
    } elseif ($usuario['role'] === 'GESTOR') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE setor_id = ? AND status = 'ativo'");
        $stmt->execute([$setor_id]);
    } else {
        $total_ativos = 1;
    }
    
    $total_ativos = isset($stmt) ? $stmt->fetch()['total'] : 1;
    
    // Ocorrências no mês
    $mes_atual = date('Y-m');
    if ($usuario['role'] === 'ADMIN') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ocorrencias WHERE DATE_FORMAT(data_ocorrencia, '%Y-%m') = ?");
        $stmt->execute([$mes_atual]);
    } elseif ($usuario['role'] === 'RH') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM ocorrencias o
            INNER JOIN colaboradores c ON o.colaborador_id = c.id
            WHERE c.empresa_id = ? AND DATE_FORMAT(o.data_ocorrencia, '%Y-%m') = ?
        ");
        $stmt->execute([$usuario['empresa_id'], $mes_atual]);
    } elseif ($usuario['role'] === 'GESTOR') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM ocorrencias o
            INNER JOIN colaboradores c ON o.colaborador_id = c.id
            WHERE c.setor_id = ? AND DATE_FORMAT(o.data_ocorrencia, '%Y-%m') = ?
        ");
        $stmt->execute([$setor_id, $mes_atual]);
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM ocorrencias 
            WHERE colaborador_id = ? AND DATE_FORMAT(data_ocorrencia, '%Y-%m') = ?
        ");
        $stmt->execute([$usuario['colaborador_id'], $mes_atual]);
    }
    
    $ocorrencias_mes = $stmt->fetch()['total'];
    
    // Dados para gráfico de ocorrências por mês (últimos 6 meses)
    $meses_grafico = [];
    $ocorrencias_grafico = [];
    for ($i = 5; $i >= 0; $i--) {
        $mes = date('Y-m', strtotime("-$i months"));
        $meses_grafico[] = date('M/Y', strtotime("-$i months"));
        
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ocorrencias WHERE DATE_FORMAT(data_ocorrencia, '%Y-%m') = ?");
            $stmt->execute([$mes]);
        } elseif ($usuario['role'] === 'RH') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM ocorrencias o
                INNER JOIN colaboradores c ON o.colaborador_id = c.id
                WHERE c.empresa_id = ? AND DATE_FORMAT(o.data_ocorrencia, '%Y-%m') = ?
            ");
            $stmt->execute([$usuario['empresa_id'], $mes]);
        } elseif ($usuario['role'] === 'GESTOR') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM ocorrencias o
                INNER JOIN colaboradores c ON o.colaborador_id = c.id
                WHERE c.setor_id = ? AND DATE_FORMAT(o.data_ocorrencia, '%Y-%m') = ?
            ");
            $stmt->execute([$setor_id, $mes]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM ocorrencias 
                WHERE colaborador_id = ? AND DATE_FORMAT(data_ocorrencia, '%Y-%m') = ?
            ");
            $stmt->execute([$usuario['colaborador_id'], $mes]);
        }
        $ocorrencias_grafico[] = $stmt->fetch()['total'];
    }
    
    // Dados para gráfico de ocorrências por tipo (últimos 30 dias)
    $data_inicio = date('Y-m-d', strtotime('-30 days'));
    if ($usuario['role'] === 'ADMIN') {
        $stmt = $pdo->prepare("
            SELECT tipo, COUNT(*) as total
            FROM ocorrencias
            WHERE data_ocorrencia >= ?
            GROUP BY tipo
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute([$data_inicio]);
    } elseif ($usuario['role'] === 'RH') {
        $stmt = $pdo->prepare("
            SELECT o.tipo, COUNT(*) as total
            FROM ocorrencias o
            INNER JOIN colaboradores c ON o.colaborador_id = c.id
            WHERE c.empresa_id = ? AND o.data_ocorrencia >= ?
            GROUP BY o.tipo
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute([$usuario['empresa_id'], $data_inicio]);
    } elseif ($usuario['role'] === 'GESTOR') {
        $stmt = $pdo->prepare("
            SELECT o.tipo, COUNT(*) as total
            FROM ocorrencias o
            INNER JOIN colaboradores c ON o.colaborador_id = c.id
            WHERE c.setor_id = ? AND o.data_ocorrencia >= ?
            GROUP BY o.tipo
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute([$setor_id, $data_inicio]);
    } else {
        $stmt = $pdo->prepare("
            SELECT tipo, COUNT(*) as total
            FROM ocorrencias
            WHERE colaborador_id = ? AND data_ocorrencia >= ?
            GROUP BY tipo
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute([$usuario['colaborador_id'], $data_inicio]);
    }
    $ocorrencias_por_tipo = $stmt->fetchAll();
    
    // Dados para gráfico de colaboradores por status
    if ($usuario['role'] === 'ADMIN') {
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as total
            FROM colaboradores
            GROUP BY status
        ");
    } elseif ($usuario['role'] === 'RH') {
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) as total
            FROM colaboradores
            WHERE empresa_id = ?
            GROUP BY status
        ");
        $stmt->execute([$usuario['empresa_id']]);
    } elseif ($usuario['role'] === 'GESTOR') {
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) as total
            FROM colaboradores
            WHERE setor_id = ?
            GROUP BY status
        ");
        $stmt->execute([$setor_id]);
    } else {
        $colaboradores_status = [];
    }
    $colaboradores_status = isset($stmt) ? $stmt->fetchAll() : [];
    
    // Ranking de ocorrências (últimos 30 dias)
    $data_inicio = date('Y-m-d', strtotime('-30 days'));
    if ($usuario['role'] === 'ADMIN') {
        $stmt = $pdo->prepare("
            SELECT c.nome_completo, COUNT(o.id) as total_ocorrencias
            FROM colaboradores c
            LEFT JOIN ocorrencias o ON c.id = o.colaborador_id AND o.data_ocorrencia >= ?
            GROUP BY c.id, c.nome_completo
            ORDER BY total_ocorrencias DESC
            LIMIT 10
        ");
        $stmt->execute([$data_inicio]);
    } elseif ($usuario['role'] === 'RH') {
        $stmt = $pdo->prepare("
            SELECT c.nome_completo, COUNT(o.id) as total_ocorrencias
            FROM colaboradores c
            LEFT JOIN ocorrencias o ON c.id = o.colaborador_id AND o.data_ocorrencia >= ?
            WHERE c.empresa_id = ?
            GROUP BY c.id, c.nome_completo
            ORDER BY total_ocorrencias DESC
            LIMIT 10
        ");
        $stmt->execute([$data_inicio, $usuario['empresa_id']]);
    } elseif ($usuario['role'] === 'GESTOR') {
        $stmt = $pdo->prepare("
            SELECT c.nome_completo, COUNT(o.id) as total_ocorrencias
            FROM colaboradores c
            LEFT JOIN ocorrencias o ON c.id = o.colaborador_id AND o.data_ocorrencia >= ?
            WHERE c.setor_id = ?
            GROUP BY c.id, c.nome_completo
            ORDER BY total_ocorrencias DESC
            LIMIT 10
        ");
        $stmt->execute([$data_inicio, $setor_id]);
    } else {
        $ranking = [];
    }
    
    $ranking = isset($stmt) ? $stmt->fetchAll() : [];
    
} catch (PDOException $e) {
    $error = 'Erro ao carregar dados: ' . $e->getMessage();
    $total_colaboradores = 0;
    $total_ativos = 0;
    $ocorrencias_mes = 0;
    $ocorrencias_grafico = [];
    $meses_grafico = [];
    $ocorrencias_por_tipo = [];
    $colaboradores_status = [];
    $ranking = [];
}
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Dashboard</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Dashboard</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger d-flex align-items-center p-5 mb-10">
                <i class="ki-duotone ki-shield-cross fs-2hx text-danger me-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="d-flex flex-column">
                    <h4 class="mb-1 text-danger">Erro</h4>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <!--begin::Row-->
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col-->
            <div class="col-xl-3">
                <!--begin::Statistics Widget 5-->
                <a href="colaboradores.php" class="card bg-primary hoverable card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <i class="ki-duotone ki-profile-circle text-white fs-2tx ms-n1 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-white fw-bold fs-2 mb-2 mt-5"><?= $total_colaboradores ?></div>
                        <div class="fw-semibold text-white opacity-75">Total de Colaboradores</div>
                    </div>
                </a>
                <!--end::Statistics Widget 5-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-3">
                <!--begin::Statistics Widget 5-->
                <a href="colaboradores.php?status=ativo" class="card bg-success hoverable card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <i class="ki-duotone ki-check-circle text-white fs-2tx ms-n1 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-white fw-bold fs-2 mb-2 mt-5"><?= $total_ativos ?></div>
                        <div class="fw-semibold text-white opacity-75">Colaboradores Ativos</div>
                    </div>
                </a>
                <!--end::Statistics Widget 5-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-3">
                <!--begin::Statistics Widget 5-->
                <a href="ocorrencias_list.php" class="card bg-warning hoverable card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <i class="ki-duotone ki-notepad text-white fs-2tx ms-n1 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-white fw-bold fs-2 mb-2 mt-5"><?= $ocorrencias_mes ?></div>
                        <div class="fw-semibold text-white opacity-75">Ocorrências no Mês</div>
                    </div>
                </a>
                <!--end::Statistics Widget 5-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-3">
                <!--begin::Statistics Widget 5-->
                <a href="colaboradores.php?status=inativo" class="card bg-danger hoverable card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <i class="ki-duotone ki-cross-circle text-white fs-2tx ms-n1 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-white fw-bold fs-2 mb-2 mt-5"><?= $total_colaboradores - $total_ativos ?></div>
                        <div class="fw-semibold text-white opacity-75">Inativos</div>
                    </div>
                </a>
                <!--end::Statistics Widget 5-->
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        
        <!--begin::Row-->
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col-->
            <div class="col-xl-8">
                <!--begin::Charts Widget 1-->
                <div class="card card-xl-stretch mb-xl-8">
                    <!--begin::Header-->
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Ocorrências por Mês</span>
                            <span class="text-muted fw-semibold fs-7">Últimos 6 meses</span>
                        </h3>
                    </div>
                    <!--end::Header-->
                    <!--begin::Body-->
                    <div class="card-body pt-5">
                        <canvas id="kt_chart_ocorrencias_mes" style="height: 350px;"></canvas>
                    </div>
                    <!--end::Body-->
                </div>
                <!--end::Charts Widget 1-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-4">
                <!--begin::Charts Widget 2-->
                <div class="card card-xl-stretch mb-xl-8">
                    <!--begin::Header-->
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Colaboradores por Status</span>
                        </h3>
                    </div>
                    <!--end::Header-->
                    <!--begin::Body-->
                    <div class="card-body pt-5">
                        <canvas id="kt_chart_colaboradores_status" style="height: 350px;"></canvas>
                    </div>
                    <!--end::Body-->
                </div>
                <!--end::Charts Widget 2-->
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        
        <!--begin::Row-->
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col-->
            <div class="col-xl-12">
                <!--begin::Charts Widget 3-->
                <div class="card card-xl-stretch mb-xl-8">
                    <!--begin::Header-->
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Ocorrências por Tipo</span>
                            <span class="text-muted fw-semibold fs-7">Últimos 30 dias</span>
                        </h3>
                    </div>
                    <!--end::Header-->
                    <!--begin::Body-->
                    <div class="card-body pt-5">
                        <canvas id="kt_chart_ocorrencias_tipo" style="height: 300px;"></canvas>
                    </div>
                    <!--end::Body-->
                </div>
                <!--end::Charts Widget 3-->
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        
        <!--begin::Row-->
        <?php if (!empty($ranking)): ?>
        <div class="row g-5 g-xl-8">
            <div class="col-xl-12">
                <!--begin::Tables Widget 9-->
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <!--begin::Header-->
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Ranking de Ocorrências</span>
                            <span class="text-muted fw-semibold fs-7">Últimos 30 dias</span>
                        </h3>
                    </div>
                    <!--end::Header-->
                    <!--begin::Body-->
                    <div class="card-body py-3">
                        <!--begin::Table container-->
                        <div class="table-responsive">
                            <!--begin::Table-->
                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                <!--begin::Table head-->
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th class="min-w-50px">Posição</th>
                                        <th class="min-w-200px">Colaborador</th>
                                        <th class="min-w-150px text-end">Total de Ocorrências</th>
                                    </tr>
                                </thead>
                                <!--end::Table head-->
                                <!--begin::Table body-->
                                <tbody>
                                    <?php foreach ($ranking as $index => $item): ?>
                                    <tr>
                                        <td>
                                            <?php if ($index === 0): ?>
                                                <span class="badge badge-warning fs-7">🥇 1º</span>
                                            <?php elseif ($index === 1): ?>
                                                <span class="badge badge-secondary fs-7">🥈 2º</span>
                                            <?php elseif ($index === 2): ?>
                                                <span class="badge badge-info fs-7">🥉 3º</span>
                                            <?php else: ?>
                                                <span class="text-muted fw-semibold"><?= $index + 1 ?>º</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-gray-900 fw-bold d-block fs-6"><?= htmlspecialchars($item['nome_completo']) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?php 
                                            $badge_class = $item['total_ocorrencias'] > 5 ? 'badge-danger' : ($item['total_ocorrencias'] > 2 ? 'badge-warning' : 'badge-primary');
                                            ?>
                                            <span class="badge <?= $badge_class ?> fs-7"><?= $item['total_ocorrencias'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <!--end::Table body-->
                            </table>
                            <!--end::Table-->
                        </div>
                        <!--end::Table container-->
                    </div>
                    <!--end::Body-->
                </div>
                <!--end::Tables Widget 9-->
            </div>
        </div>
        <!--end::Row-->
        <?php endif; ?>
        
    </div>
</div>
<!--end::Post-->

<!--begin::Chart Scripts-->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico de Ocorrências por Mês
const ctxOcorrenciasMes = document.getElementById('kt_chart_ocorrencias_mes');
if (ctxOcorrenciasMes) {
    new Chart(ctxOcorrenciasMes, {
        type: 'line',
        data: {
            labels: <?= json_encode($meses_grafico) ?>,
            datasets: [{
                label: 'Ocorrências',
                data: <?= json_encode($ocorrencias_grafico) ?>,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Gráfico de Colaboradores por Status
const ctxColaboradoresStatus = document.getElementById('kt_chart_colaboradores_status');
if (ctxColaboradoresStatus && <?= json_encode(!empty($colaboradores_status)) ?>) {
    const statusData = <?= json_encode($colaboradores_status) ?>;
    new Chart(ctxColaboradoresStatus, {
        type: 'doughnut',
        data: {
            labels: statusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
            datasets: [{
                data: statusData.map(item => item.total),
                backgroundColor: [
                    'rgb(40, 167, 69)',
                    'rgb(220, 53, 69)',
                    'rgb(255, 193, 7)',
                    'rgb(108, 117, 125)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Gráfico de Ocorrências por Tipo
const ctxOcorrenciasTipo = document.getElementById('kt_chart_ocorrencias_tipo');
if (ctxOcorrenciasTipo && <?= json_encode(!empty($ocorrencias_por_tipo)) ?>) {
    const tipoData = <?= json_encode($ocorrencias_por_tipo) ?>;
    new Chart(ctxOcorrenciasTipo, {
        type: 'bar',
        data: {
            labels: tipoData.map(item => item.tipo.charAt(0).toUpperCase() + item.tipo.slice(1)),
            datasets: [{
                label: 'Quantidade',
                data: tipoData.map(item => item.total),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}
</script>
<!--end::Chart Scripts-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
