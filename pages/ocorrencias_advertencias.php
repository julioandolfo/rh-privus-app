<?php
/**
 * Sistema de Advertências Progressivas
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';

require_page_permission('ocorrencias_list.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca advertências
$where = [];
$params = [];

if ($usuario['role'] === 'RH') {
    $where[] = "c.empresa_id = ?";
    $params[] = $usuario['empresa_id'];
} elseif ($usuario['role'] === 'GESTOR') {
    $stmt = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario['id']]);
    $user_data = $stmt->fetch();
    $setor_id = $user_data['setor_id'] ?? 0;
    
    $where[] = "c.setor_id = ?";
    $params[] = $setor_id;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT a.*, 
           c.nome_completo as colaborador_nome,
           o.tipo as ocorrencia_tipo,
           t.nome as tipo_ocorrencia_nome,
           u.nome as created_by_nome
    FROM ocorrencias_advertencias a
    INNER JOIN colaboradores c ON a.colaborador_id = c.id
    LEFT JOIN ocorrencias o ON a.ocorrencia_id = o.id
    LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
    LEFT JOIN usuarios u ON a.created_by = u.id
    $where_sql
    ORDER BY a.data_advertencia DESC, a.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$advertencias = $stmt->fetchAll();

// Busca estatísticas por colaborador
$sql_stats = "
    SELECT 
        c.id,
        c.nome_completo,
        COUNT(DISTINCT a.id) as total_advertencias,
        MAX(a.nivel) as nivel_maximo,
        MAX(a.data_advertencia) as ultima_advertencia,
        COUNT(DISTINCT CASE WHEN a.tipo_advertencia = 'verbal' THEN a.id END) as verbais,
        COUNT(DISTINCT CASE WHEN a.tipo_advertencia = 'escrita' THEN a.id END) as escritas,
        COUNT(DISTINCT CASE WHEN a.tipo_advertencia = 'suspensao' THEN a.id END) as suspensoes,
        COUNT(DISTINCT CASE WHEN a.tipo_advertencia = 'demissao' THEN a.id END) as demissoes
    FROM colaboradores c
    LEFT JOIN ocorrencias_advertencias a ON c.id = a.colaborador_id
    $where_sql
    GROUP BY c.id, c.nome_completo
    HAVING total_advertencias > 0
    ORDER BY total_advertencias DESC, nivel_maximo DESC
";

$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute($params);
$estatisticas = $stmt_stats->fetchAll();

$page_title = 'Advertências Progressivas';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Advertências Progressivas</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Advertências</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!-- Estatísticas por Colaborador -->
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">Estatísticas por Colaborador</h3>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_advertencias_stats_table">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Colaborador</th>
                                <th class="min-w-100px">Total</th>
                                <th class="min-w-100px">Nível Máximo</th>
                                <th class="min-w-100px">Verbais</th>
                                <th class="min-w-100px">Escritas</th>
                                <th class="min-w-100px">Suspensões</th>
                                <th class="min-w-100px">Demissões</th>
                                <th class="min-w-150px">Última Advertência</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            <?php foreach ($estatisticas as $stat): ?>
                            <tr>
                                <td>
                                    <a href="colaborador_view.php?id=<?= $stat['id'] ?>" class="text-gray-800 text-hover-primary">
                                        <?= htmlspecialchars($stat['nome_completo']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge-light-info"><?= $stat['total_advertencias'] ?></span>
                                </td>
                                <td>
                                    <?php
                                    $nivel_maximo = $stat['nivel_maximo'] ?? 0;
                                    $nivel_colors = [
                                        1 => 'badge-light-success',
                                        2 => 'badge-light-warning',
                                        3 => 'badge-light-danger',
                                        4 => 'badge-light-danger'
                                    ];
                                    ?>
                                    <span class="badge <?= $nivel_colors[$nivel_maximo] ?? 'badge-light-secondary' ?>">
                                        Nível <?= $nivel_maximo ?>
                                    </span>
                                </td>
                                <td><?= $stat['verbais'] ?? 0 ?></td>
                                <td><?= $stat['escritas'] ?? 0 ?></td>
                                <td><?= $stat['suspensoes'] ?? 0 ?></td>
                                <td><?= $stat['demissoes'] ?? 0 ?></td>
                                <td><?= $stat['ultima_advertencia'] ? formatar_data($stat['ultima_advertencia']) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Histórico de Advertências -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Histórico de Advertências</h3>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_advertencias_table">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-150px">Data</th>
                                <th class="min-w-200px">Colaborador</th>
                                <th class="min-w-150px">Tipo</th>
                                <th class="min-w-100px">Nível</th>
                                <th class="min-w-150px">Validade</th>
                                <th class="min-w-200px">Ocorrência</th>
                                <th class="min-w-150px">Registrado por</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            <?php foreach ($advertencias as $adv): ?>
                            <tr>
                                <td><?= formatar_data($adv['data_advertencia']) ?></td>
                                <td>
                                    <a href="colaborador_view.php?id=<?= $adv['colaborador_id'] ?>" class="text-gray-800 text-hover-primary">
                                        <?= htmlspecialchars($adv['colaborador_nome']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                    $tipo_labels = [
                                        'verbal' => 'Verbal',
                                        'escrita' => 'Escrita',
                                        'suspensao' => 'Suspensão',
                                        'demissao' => 'Demissão'
                                    ];
                                    $tipo_colors = [
                                        'verbal' => 'badge-light-info',
                                        'escrita' => 'badge-light-warning',
                                        'suspensao' => 'badge-light-danger',
                                        'demissao' => 'badge-light-danger'
                                    ];
                                    ?>
                                    <span class="badge <?= $tipo_colors[$adv['tipo_advertencia']] ?? 'badge-light-secondary' ?>">
                                        <?= $tipo_labels[$adv['tipo_advertencia']] ?? ucfirst($adv['tipo_advertencia']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-light-primary">Nível <?= $adv['nivel'] ?></span>
                                </td>
                                <td>
                                    <?php if ($adv['data_validade']): ?>
                                        <?php
                                        $hoje = date('Y-m-d');
                                        $validade = $adv['data_validade'];
                                        $valida = $validade >= $hoje;
                                        ?>
                                        <span class="badge <?= $valida ? 'badge-light-success' : 'badge-light-secondary' ?>">
                                            <?= formatar_data($validade) ?>
                                        </span>
                                        <?php if (!$valida): ?>
                                        <br><small class="text-muted">Expirada</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Permanente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($adv['ocorrencia_id']): ?>
                                    <a href="ocorrencia_view.php?id=<?= $adv['ocorrencia_id'] ?>" class="text-gray-800 text-hover-primary">
                                        <?= htmlspecialchars($adv['tipo_ocorrencia_nome'] ?? $adv['ocorrencia_tipo'] ?? 'Ocorrência') ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($adv['created_by_nome'] ?? 'N/A') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<script>
"use strict";
var KTAdvertenciasList = function() {
    var t1, t2, n1, n2;
    
    return {
        init: function() {
            n1 = document.querySelector("#kt_advertencias_stats_table");
            n2 = document.querySelector("#kt_advertencias_table");
            
            if (n1) {
                t1 = $(n1).DataTable({
                    info: true,
                    order: [[1, 'desc']],
                    pageLength: 25,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    }
                });
            }
            
            if (n2) {
                t2 = $(n2).DataTable({
                    info: true,
                    order: [[0, 'desc']],
                    pageLength: 25,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    }
                });
            }
        }
    };
}();

// Aguarda jQuery estar disponível
(function waitForDependencies() {
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
        setTimeout(waitForDependencies, 50);
        return;
    }
    
    $(document).ready(function() {
        KTAdvertenciasList.init();
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

