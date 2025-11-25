<?php
/**
 * Relatório de Adiantamentos Pendentes
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('relatorio_adiantamentos_pendentes.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$filtro_empresa_id = $_GET['empresa_id'] ?? '';
$filtro_colaborador_id = $_GET['colaborador_id'] ?? '';
$filtro_mes_desconto = $_GET['mes_desconto'] ?? '';

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

// Monta query para buscar adiantamentos pendentes
$where = ["fa.descontado = 0"];
$params = [];

// Filtro de empresa
if ($filtro_empresa_id) {
    $where[] = "c.empresa_id = ?";
    $params[] = $filtro_empresa_id;
} elseif ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "c.empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    } else {
        $where[] = "c.empresa_id = ?";
        $params[] = $usuario['empresa_id'] ?? 0;
    }
} elseif ($usuario['role'] !== 'ADMIN') {
    $where[] = "c.empresa_id = ?";
    $params[] = $usuario['empresa_id'] ?? 0;
}

// Filtro de colaborador
if ($filtro_colaborador_id) {
    $where[] = "fa.colaborador_id = ?";
    $params[] = $filtro_colaborador_id;
}

// Filtro de mês de desconto
if ($filtro_mes_desconto) {
    $where[] = "fa.mes_desconto = ?";
    $params[] = $filtro_mes_desconto;
}

$sql = "
    SELECT 
        fa.*,
        c.nome_completo as colaborador_nome,
        c.empresa_id,
        e.nome_fantasia as empresa_nome,
        fp.mes_referencia as mes_adiantamento,
        fp.data_pagamento as data_adiantamento,
        fp.referencia_externa,
        DATEDIFF(CURDATE(), fa.created_at) as dias_pendente,
        CASE 
            WHEN fa.mes_desconto IS NOT NULL AND fa.mes_desconto < DATE_FORMAT(CURDATE(), '%Y-%m') THEN 'atrasado'
            WHEN fa.mes_desconto IS NOT NULL AND fa.mes_desconto = DATE_FORMAT(CURDATE(), '%Y-%m') THEN 'vencendo'
            ELSE 'pendente'
        END as status_desconto
    FROM fechamentos_pagamento_adiantamentos fa
    INNER JOIN colaboradores c ON fa.colaborador_id = c.id
    INNER JOIN empresas e ON c.empresa_id = e.id
    INNER JOIN fechamentos_pagamento fp ON fa.fechamento_pagamento_id = fp.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY 
        CASE status_desconto
            WHEN 'atrasado' THEN 1
            WHEN 'vencendo' THEN 2
            ELSE 3
        END,
        fa.mes_desconto ASC,
        c.nome_completo ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$adiantamentos = $stmt->fetchAll();

// Estatísticas
$stats = [
    'total' => count($adiantamentos),
    'total_valor' => 0,
    'atrasados' => 0,
    'vencendo' => 0,
    'pendentes' => 0,
    'valor_atrasado' => 0,
    'valor_vencendo' => 0,
    'valor_pendente' => 0,
    'por_colaborador' => []
];

foreach ($adiantamentos as $adiantamento) {
    $valor = (float)$adiantamento['valor_descontar'];
    $stats['total_valor'] += $valor;
    
    $status = $adiantamento['status_desconto'];
    if ($status === 'atrasado') {
        $stats['atrasados']++;
        $stats['valor_atrasado'] += $valor;
    } elseif ($status === 'vencendo') {
        $stats['vencendo']++;
        $stats['valor_vencendo'] += $valor;
    } else {
        $stats['pendentes']++;
        $stats['valor_pendente'] += $valor;
    }
    
    $colab_id = $adiantamento['colaborador_id'];
    if (!isset($stats['por_colaborador'][$colab_id])) {
        $stats['por_colaborador'][$colab_id] = [
            'nome' => $adiantamento['colaborador_nome'],
            'count' => 0,
            'valor' => 0
        ];
    }
    $stats['por_colaborador'][$colab_id]['count']++;
    $stats['por_colaborador'][$colab_id]['valor'] += $valor;
}

// Busca colaboradores para filtro
$colaboradores = [];
if ($filtro_empresa_id || $usuario['role'] !== 'ADMIN') {
    $empresa_filtro = $filtro_empresa_id ?: ($usuario['empresa_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, nome_completo FROM colaboradores WHERE empresa_id = ? AND ativo = 1 ORDER BY nome_completo");
    $stmt->execute([$empresa_filtro]);
    $colaboradores = $stmt->fetchAll();
}

$page_title = 'Relatório - Adiantamentos Pendentes';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
    <div class="page-title d-flex flex-column me-3">
        <h1 class="d-flex text-dark fw-bolder my-1 fs-3">Relatório de Adiantamentos Pendentes</h1>
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
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Empresa</label>
                        <select name="empresa_id" class="form-select" id="filtro_empresa">
                            <option value="">Todas</option>
                            <?php foreach ($empresas as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filtro_empresa_id == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['nome_fantasia']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Colaborador</label>
                        <select name="colaborador_id" class="form-select" id="filtro_colaborador">
                            <option value="">Todos</option>
                            <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= $colab['id'] ?>" <?= $filtro_colaborador_id == $colab['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($colab['nome_completo']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Mês de Desconto</label>
                        <input type="month" name="mes_desconto" class="form-control" value="<?= htmlspecialchars($filtro_mes_desconto) ?>">
                    </div>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="relatorio_adiantamentos_pendentes.php" class="btn btn-light">Limpar</a>
                    </div>
                </form>
                
                <!-- Estatísticas -->
                <div class="row g-5">
                    <div class="col-md-3">
                        <div class="card bg-light-danger">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="text-gray-600 fw-semibold fs-6 mb-1">Atrasados</div>
                                        <div class="text-gray-900 fw-bold fs-2"><?= number_format($stats['atrasados'], 0, ',', '.') ?></div>
                                        <div class="text-gray-600 fs-7">R$ <?= number_format($stats['valor_atrasado'], 2, ',', '.') ?></div>
                                    </div>
                                    <i class="ki-duotone ki-warning-2 fs-2x text-danger">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
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
                                        <div class="text-gray-600 fw-semibold fs-6 mb-1">Vencendo Este Mês</div>
                                        <div class="text-gray-900 fw-bold fs-2"><?= number_format($stats['vencendo'], 0, ',', '.') ?></div>
                                        <div class="text-gray-600 fs-7">R$ <?= number_format($stats['valor_vencendo'], 2, ',', '.') ?></div>
                                    </div>
                                    <i class="ki-duotone ki-time fs-2x text-warning">
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
                                        <div class="text-gray-600 fw-semibold fs-6 mb-1">Pendentes</div>
                                        <div class="text-gray-900 fw-bold fs-2"><?= number_format($stats['pendentes'], 0, ',', '.') ?></div>
                                        <div class="text-gray-600 fs-7">R$ <?= number_format($stats['valor_pendente'], 2, ',', '.') ?></div>
                                    </div>
                                    <i class="ki-duotone ki-information-5 fs-2x text-info">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-primary">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="text-gray-600 fw-semibold fs-6 mb-1">Total</div>
                                        <div class="text-gray-900 fw-bold fs-2"><?= number_format($stats['total'], 0, ',', '.') ?></div>
                                        <div class="text-gray-600 fs-7">R$ <?= number_format($stats['total_valor'], 2, ',', '.') ?></div>
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
                </div>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Card - Listagem-->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Adiantamentos Pendentes</h3>
                <div class="card-toolbar">
                    <a href="fechamento_pagamentos.php" class="btn btn-light">Voltar</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3" id="kt_table_adiantamentos">
                        <thead>
                            <tr class="fw-bolder text-muted">
                                <th>Status</th>
                                <th>Colaborador</th>
                                <th>Empresa</th>
                                <th>Valor Adiantamento</th>
                                <th>Valor a Descontar</th>
                                <th>Mês de Desconto</th>
                                <th>Data Adiantamento</th>
                                <th>Dias Pendente</th>
                                <th>Referência</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($adiantamentos)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-10">Nenhum adiantamento pendente encontrado</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($adiantamentos as $adiantamento): ?>
                            <tr>
                                <td>
                                    <?php
                                    $status = $adiantamento['status_desconto'];
                                    $badges = [
                                        'atrasado' => '<span class="badge badge-danger">Atrasado</span>',
                                        'vencendo' => '<span class="badge badge-warning">Vencendo</span>',
                                        'pendente' => '<span class="badge badge-info">Pendente</span>'
                                    ];
                                    echo $badges[$status] ?? '<span class="badge badge-secondary">-</span>';
                                    ?>
                                </td>
                                <td><strong><?= htmlspecialchars($adiantamento['colaborador_nome']) ?></strong></td>
                                <td><?= htmlspecialchars($adiantamento['empresa_nome']) ?></td>
                                <td class="fw-bold">R$ <?= number_format($adiantamento['valor_adiantamento'], 2, ',', '.') ?></td>
                                <td class="fw-bold text-danger">R$ <?= number_format($adiantamento['valor_descontar'], 2, ',', '.') ?></td>
                                <td>
                                    <?php if ($adiantamento['mes_desconto']): ?>
                                        <?= date('m/Y', strtotime($adiantamento['mes_desconto'] . '-01')) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Não definido</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($adiantamento['data_adiantamento']): ?>
                                        <?= date('d/m/Y', strtotime($adiantamento['data_adiantamento'])) ?>
                                    <?php else: ?>
                                        <?= date('d/m/Y', strtotime($adiantamento['created_at'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $dias = (int)$adiantamento['dias_pendente'];
                                    if ($dias > 90) {
                                        echo '<span class="text-danger fw-bold">' . $dias . ' dias</span>';
                                    } elseif ($dias > 60) {
                                        echo '<span class="text-warning fw-bold">' . $dias . ' dias</span>';
                                    } else {
                                        echo '<span class="text-muted">' . $dias . ' dias</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($adiantamento['referencia_externa']): ?>
                                        <span class="text-muted"><?= htmlspecialchars($adiantamento['referencia_externa']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="fechamento_pagamentos.php?view=<?= $adiantamento['fechamento_pagamento_id'] ?>" class="btn btn-sm btn-light-primary">
                                        Ver Fechamento
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
    $('#kt_table_adiantamentos').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
        },
        order: [[0, 'asc'], [5, 'asc']],
        pageLength: 25
    });
    
    // Atualiza lista de colaboradores quando empresa muda
    $('#filtro_empresa').on('change', function() {
        var empresaId = $(this).val();
        if (empresaId) {
            // Recarrega página com empresa selecionada
            window.location.href = 'relatorio_adiantamentos_pendentes.php?empresa_id=' + empresaId;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

