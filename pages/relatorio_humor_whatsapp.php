<?php
/**
 * Relatório de Humor - WhatsApp (Evolution API)
 * Lê da tabela `emocoes` com canal='whatsapp', unificado com o sistema web
 */

$page_title = 'Relatório de Humor WhatsApp';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('relatorio_humor_whatsapp.php');

$pdo = getDB();

// ─── Filtros ──────────────────────────────────────────────────────────────────
$data_inicio    = $_GET['data_inicio']    ?? date('Y-m-01');
$data_fim       = $_GET['data_fim']       ?? date('Y-m-d');
$colaborador_id = (int)($_GET['colaborador_id'] ?? 0);

// ─── Cláusula WHERE base (só registros de WhatsApp) ──────────────────────────
$where  = "WHERE e.data_registro BETWEEN ? AND ? AND e.canal = 'whatsapp'";
$params = [$data_inicio, $data_fim];

if ($colaborador_id) {
    $where  .= " AND (e.colaborador_id = ? OR u.colaborador_id = ?)";
    $params[] = $colaborador_id;
    $params[] = $colaborador_id;
}

// ─── Resumo geral ────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_respostas,
        ROUND(AVG(e.nivel_emocao), 2) as media_geral,
        SUM(CASE WHEN e.nivel_emocao = 5 THEN 1 ELSE 0 END) as muito_feliz,
        SUM(CASE WHEN e.nivel_emocao = 4 THEN 1 ELSE 0 END) as feliz,
        SUM(CASE WHEN e.nivel_emocao = 3 THEN 1 ELSE 0 END) as neutro,
        SUM(CASE WHEN e.nivel_emocao = 2 THEN 1 ELSE 0 END) as triste,
        SUM(CASE WHEN e.nivel_emocao = 1 THEN 1 ELSE 0 END) as muito_triste
    FROM emocoes e
    LEFT JOIN usuarios u ON u.id = e.usuario_id
    {$where}
");
$stmt->execute($params);
$resumo = $stmt->fetch(PDO::FETCH_ASSOC);

// ─── Tendência diária ────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        e.data_registro,
        ROUND(AVG(e.nivel_emocao), 2) as media,
        COUNT(*) as respostas
    FROM emocoes e
    LEFT JOIN usuarios u ON u.id = e.usuario_id
    {$where}
    GROUP BY e.data_registro
    ORDER BY e.data_registro ASC
");
$stmt->execute($params);
$tendencia = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── Por colaborador ─────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        COALESCE(e.colaborador_id, u.colaborador_id) as cid,
        c.nome_completo,
        emp.nome_fantasia as empresa_nome,
        ROUND(AVG(e.nivel_emocao), 2) as media,
        COUNT(*) as total_respostas,
        MIN(e.nivel_emocao) as min_nivel,
        MAX(e.nivel_emocao) as max_nivel,
        (
            SELECT e2.nivel_emocao FROM emocoes e2
            LEFT JOIN usuarios u2 ON u2.id = e2.usuario_id
            WHERE (e2.colaborador_id = cid OR u2.colaborador_id = cid) AND e2.canal = 'whatsapp'
            ORDER BY e2.data_registro DESC LIMIT 1
        ) as ultimo_nivel,
        (
            SELECT e2.data_registro FROM emocoes e2
            LEFT JOIN usuarios u2 ON u2.id = e2.usuario_id
            WHERE (e2.colaborador_id = cid OR u2.colaborador_id = cid) AND e2.canal = 'whatsapp'
            ORDER BY e2.data_registro DESC LIMIT 1
        ) as ultima_data
    FROM emocoes e
    LEFT JOIN usuarios u ON u.id = e.usuario_id
    LEFT JOIN colaboradores c ON c.id = COALESCE(e.colaborador_id, u.colaborador_id)
    LEFT JOIN empresas emp ON emp.id = c.empresa_id
    {$where}
    GROUP BY cid, c.nome_completo, emp.nome_fantasia
    ORDER BY media ASC
");
$stmt->execute($params);
$por_colaborador = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── Colaboradores com WA mas sem resposta hoje ───────────────────────────────
$sem_resposta_hoje = $pdo->query("
    SELECT c.nome_completo, c.telefone, c.whatsapp_ativo
    FROM colaboradores c
    WHERE c.status = 'ativo'
      AND c.telefone IS NOT NULL AND c.telefone != ''
      AND c.id NOT IN (
          SELECT COALESCE(e.colaborador_id, u.colaborador_id)
          FROM emocoes e
          LEFT JOIN usuarios u ON u.id = e.usuario_id
          WHERE e.data_registro = CURDATE() AND e.canal = 'whatsapp'
      )
    ORDER BY c.nome_completo
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// ─── Taxa de resposta do dia ──────────────────────────────────────────────────
$taxa = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM colaboradores WHERE status = 'ativo' AND telefone IS NOT NULL AND telefone != '' AND whatsapp_ativo = 1) as com_wa,
        (SELECT COUNT(*) FROM humor_pesquisa_envios WHERE data_envio = CURDATE() AND enviado = 1) as enviados_hoje,
        (SELECT COUNT(*) FROM humor_pesquisa_envios WHERE data_envio = CURDATE() AND respondido = 1) as respondidos_hoje,
        (SELECT COUNT(*) FROM emocoes WHERE data_registro = CURDATE() AND canal = 'whatsapp') as respostas_hoje
")->fetch(PDO::FETCH_ASSOC);

// ─── Lista colaboradores para filtro ─────────────────────────────────────────
$colaboradores_lista = $pdo->query("
    SELECT id, nome_completo FROM colaboradores WHERE status = 'ativo' ORDER BY nome_completo
")->fetchAll(PDO::FETCH_ASSOC);

// Mapeamento (compatível com emocoes: 1=Muito triste ... 5=Muito feliz)
$nivel_labels  = [1 => 'Muito triste', 2 => 'Triste', 3 => 'Neutro', 4 => 'Feliz', 5 => 'Muito feliz'];
$nivel_emojis  = [1 => '😢', 2 => '😔', 3 => '😐', 4 => '🙂', 5 => '😄'];
$nivel_colors  = [1 => 'danger', 2 => 'warning', 3 => 'secondary', 4 => 'primary', 5 => 'success'];

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                    Humor via WhatsApp
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted"><a href="dashboard.php" class="text-muted text-hover-primary">Início</a></li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">Humor WhatsApp</li>
                </ul>
            </div>
            <div class="d-flex gap-3">
                <a href="emocoes_analise.php" class="btn btn-sm btn-light-primary">
                    <i class="ki-duotone ki-chart-simple fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                    Ver Análise Completa
                </a>
                <a href="configuracoes_evolution.php?aba=pesquisa_humor" class="btn btn-sm btn-light-success">
                    <i class="ki-duotone ki-setting-2 fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Configurações
                </a>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-xxl">

            <div class="alert alert-light-success border border-success border-dashed mb-6">
                <i class="ki-duotone ki-information-5 fs-2 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                Este relatório exibe apenas os registros coletados via <strong>WhatsApp (Evolution API)</strong>.
                Para ver todos os registros (web + WhatsApp), acesse a
                <a href="emocoes_analise.php" class="fw-bold">Análise Completa de Emoções</a>.
            </div>

            <!-- Filtros -->
            <div class="card mb-6">
                <div class="card-body py-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Colaborador</label>
                            <select name="colaborador_id" class="form-select">
                                <option value="0">Todos</option>
                                <?php foreach ($colaboradores_lista as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $colaborador_id == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome_completo']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="ki-duotone ki-filter fs-2 me-1"><span class="path1"></span><span class="path2"></span></i>
                                Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cards de hoje -->
            <div class="row g-5 mb-6">
                <div class="col-md-3">
                    <div class="card card-flush h-100">
                        <div class="card-body text-center py-6">
                            <?php $pct = ($taxa['com_wa'] ?? 0) > 0 ? round((($taxa['respostas_hoje'] ?? 0) / $taxa['com_wa']) * 100) : 0; ?>
                            <div class="fs-1 fw-bold text-success"><?= $taxa['respostas_hoje'] ?? 0 ?></div>
                            <div class="text-muted fw-semibold fs-7">Responderam hoje</div>
                            <div class="progress mt-3" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                            </div>
                            <div class="text-muted fs-8 mt-1"><?= $pct ?>% de <?= $taxa['com_wa'] ?? 0 ?> com WA</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-flush h-100">
                        <div class="card-body text-center py-6">
                            <div class="fs-1 fw-bold text-primary"><?= $taxa['enviados_hoje'] ?? 0 ?></div>
                            <div class="text-muted fw-semibold fs-7">Pesquisas enviadas hoje</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-flush h-100">
                        <div class="card-body text-center py-6">
                            <?php
                            $media = floatval($resumo['media_geral'] ?? 0);
                            $cor_m = $media >= 4 ? 'success' : ($media >= 3 ? 'primary' : ($media >= 2 ? 'warning' : 'danger'));
                            ?>
                            <div class="fs-1 fw-bold text-<?= $cor_m ?>"><?= $media > 0 ? number_format($media, 1) : '—' ?></div>
                            <div class="text-muted fw-semibold fs-7">Média do período</div>
                            <div class="fs-2 mt-1"><?= $nivel_emojis[(int)round($media)] ?? '' ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-flush h-100">
                        <div class="card-body text-center py-6">
                            <div class="fs-1 fw-bold"><?= $resumo['total_respostas'] ?? 0 ?></div>
                            <div class="text-muted fw-semibold fs-7">Total no período</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-5 mb-6">
                <!-- Gráfico de tendência -->
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title">Tendência Diária</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="chart_tendencia" height="110"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Distribuição -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title">Distribuição</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="chart_dist" height="180"></canvas>
                            <div class="mt-4">
                                <?php
                                $total_r = max($resumo['total_respostas'] ?? 0, 1);
                                $dist = [
                                    5 => ['label' => 'Muito feliz', 'emoji' => '😄', 'count' => $resumo['muito_feliz']  ?? 0, 'cor' => '#50cd89'],
                                    4 => ['label' => 'Feliz',       'emoji' => '🙂', 'count' => $resumo['feliz']        ?? 0, 'cor' => '#009ef7'],
                                    3 => ['label' => 'Neutro',      'emoji' => '😐', 'count' => $resumo['neutro']       ?? 0, 'cor' => '#a1a5b7'],
                                    2 => ['label' => 'Triste',      'emoji' => '😔', 'count' => $resumo['triste']       ?? 0, 'cor' => '#ffc700'],
                                    1 => ['label' => 'Muito triste','emoji' => '😢', 'count' => $resumo['muito_triste'] ?? 0, 'cor' => '#f1416c'],
                                ];
                                foreach ($dist as $d):
                                    $pct_d = round(($d['count'] / $total_r) * 100);
                                ?>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fs-7"><?= $d['emoji'] ?> <?= $d['label'] ?></span>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress w-70px" style="height:5px;">
                                            <div class="progress-bar" style="width:<?= $pct_d ?>%;background-color:<?= $d['cor'] ?>"></div>
                                        </div>
                                        <span class="fw-bold fs-7 w-20px text-end"><?= $d['count'] ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela por colaborador -->
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="card-title">Por Colaborador</h3>
                    <div class="card-toolbar">
                        <span class="badge badge-light-warning fs-7">Ordenado do menor para o maior humor</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-4 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Colaborador</th>
                                    <th class="text-center">Média</th>
                                    <th class="text-center">Respostas</th>
                                    <th class="text-center">Último Humor</th>
                                    <th class="text-center">Última Data</th>
                                    <th class="text-center">Variação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($por_colaborador)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-muted">
                                        Nenhum dado no período. Certifique-se de que a pesquisa está ativa e configurada.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($por_colaborador as $col): ?>
                                <?php
                                $media_col = floatval($col['media']);
                                $cor_col   = $media_col >= 4 ? 'success' : ($media_col >= 3 ? 'primary' : ($media_col >= 2 ? 'warning' : 'danger'));
                                $ult       = (int)($col['ultimo_nivel'] ?? 0);
                                $variacao  = (int)$col['max_nivel'] - (int)$col['min_nivel'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($col['nome_completo'] ?? '—') ?></div>
                                        <div class="text-muted fs-7"><?= htmlspecialchars($col['empresa_nome'] ?? '') ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light-<?= $cor_col ?> fs-6 px-3">
                                            <?= $nivel_emojis[(int)round($media_col)] ?? '' ?>
                                            <?= number_format($media_col, 1) ?>
                                        </span>
                                    </td>
                                    <td class="text-center fw-semibold"><?= $col['total_respostas'] ?></td>
                                    <td class="text-center">
                                        <?php if ($ult): ?>
                                        <span class="badge badge-light-<?= $nivel_colors[$ult] ?? 'secondary' ?>">
                                            <?= $nivel_emojis[$ult] ?? '' ?> <?= $nivel_labels[$ult] ?? '' ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-muted">
                                        <?= $col['ultima_data'] ? formatar_data($col['ultima_data']) : '—' ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($variacao >= 3): ?>
                                        <span class="badge badge-light-danger">⚡ Alta (<?= $variacao ?>)</span>
                                        <?php elseif ($variacao >= 2): ?>
                                        <span class="badge badge-light-warning">Média (<?= $variacao ?>)</span>
                                        <?php else: ?>
                                        <span class="badge badge-light-success">Estável (<?= $variacao ?>)</span>
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

            <!-- Sem resposta hoje -->
            <?php if (!empty($sem_resposta_hoje)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Com WhatsApp mas sem resposta hoje</h3>
                    <div class="card-toolbar">
                        <span class="badge badge-light-warning"><?= count($sem_resposta_hoje) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach ($sem_resposta_hoje as $sr): ?>
                        <div class="d-flex align-items-center gap-2 border rounded px-3 py-2">
                            <i class="ki-duotone ki-user fs-4 text-muted"><span class="path1"></span><span class="path2"></span></i>
                            <div>
                                <div class="fw-semibold fs-7"><?= htmlspecialchars($sr['nome_completo']) ?></div>
                                <?php if (!$sr['whatsapp_ativo']): ?>
                                <div class="text-warning fs-8">WA desativado</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const tendenciaData = <?= json_encode($tendencia) ?>;

    // Gráfico de tendência
    new Chart(document.getElementById('chart_tendencia').getContext('2d'), {
        type: 'line',
        data: {
            labels: tendenciaData.map(d => {
                const p = d.data_registro.split('-');
                return p[2] + '/' + p[1];
            }),
            datasets: [{
                label: 'Média de Humor',
                data: tendenciaData.map(d => d.media),
                borderColor: '#009ef7',
                backgroundColor: 'rgba(0,158,247,0.1)',
                fill: true, tension: 0.4, borderWidth: 2,
                pointBackgroundColor: tendenciaData.map(d => {
                    const v = parseFloat(d.media);
                    if (v >= 4.5) return '#50cd89';
                    if (v >= 3.5) return '#009ef7';
                    if (v >= 2.5) return '#a1a5b7';
                    if (v >= 1.5) return '#ffc700';
                    return '#f1416c';
                }),
                pointRadius: 5,
            }, {
                label: 'Respostas',
                data: tendenciaData.map(d => d.respostas),
                borderColor: '#a1a5b7', borderWidth: 1, borderDash: [5,5],
                fill: false, tension: 0, yAxisID: 'y1', pointRadius: 3,
            }]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    min: 1, max: 5,
                    ticks: { stepSize: 1, callback: v => (['','😢','😔','😐','🙂','😄'])[v] || v }
                },
                y1: { position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Respostas' } }
            }
        }
    });

    // Gráfico de distribuição
    new Chart(document.getElementById('chart_dist').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['😄 Muito feliz', '🙂 Feliz', '😐 Neutro', '😔 Triste', '😢 Muito triste'],
            datasets: [{
                data: [
                    <?= (int)($resumo['muito_feliz']  ?? 0) ?>,
                    <?= (int)($resumo['feliz']        ?? 0) ?>,
                    <?= (int)($resumo['neutro']       ?? 0) ?>,
                    <?= (int)($resumo['triste']       ?? 0) ?>,
                    <?= (int)($resumo['muito_triste'] ?? 0) ?>
                ],
                backgroundColor: ['#50cd89','#009ef7','#a1a5b7','#ffc700','#f1416c'],
                borderWidth: 2, borderColor: '#fff',
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
