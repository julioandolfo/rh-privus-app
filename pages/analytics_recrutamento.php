<?php
/**
 * Analytics Completo - Sistema de Recrutamento e Seleção
 */

$page_title = 'Analytics de Recrutamento';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('analytics_recrutamento.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$empresa_id = !empty($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : null;
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$data_fim = $_GET['data_fim'] ?? date('Y-m-t'); // Último dia do mês atual
$vaga_id_filtro = !empty($_GET['vaga_id']) ? (int)$_GET['vaga_id'] : null;

// Monta condições de acesso
$where_acesso = [];
$params_acesso = [];

if (!has_role(['ADMIN'])) {
    if (has_role(['RH'])) {
        // Busca empresas do usuário RH
        $stmt_emp = $pdo->prepare("SELECT empresa_id FROM usuarios_empresas WHERE usuario_id = ?");
        $stmt_emp->execute([$usuario['id']]);
        $empresas_acesso = $stmt_emp->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($empresas_acesso)) {
            $placeholders = implode(',', array_fill(0, count($empresas_acesso), '?'));
            $where_acesso[] = "v.empresa_id IN ($placeholders)";
            $params_acesso = array_merge($params_acesso, $empresas_acesso);
        } else {
            $where_acesso[] = "1 = 0"; // Sem acesso
        }
    } else {
        $where_acesso[] = "1 = 0"; // Sem acesso
    }
}

$where_acesso_sql = !empty($where_acesso) ? " AND " . implode(" AND ", $where_acesso) : "";

// Filtros adicionais
$where_filtros = [];
$params_filtros = [];

if ($empresa_id) {
    $where_filtros[] = "v.empresa_id = ?";
    $params_filtros[] = $empresa_id;
}

if ($vaga_id_filtro) {
    $where_filtros[] = "v.id = ?";
    $params_filtros[] = $vaga_id_filtro;
}

$where_filtros_sql = !empty($where_filtros) ? " AND " . implode(" AND ", $where_filtros) : "";

// ========== ESTATÍSTICAS GERAIS ==========
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT v.id) as total_vagas,
        COUNT(DISTINCT CASE WHEN v.status = 'aberta' THEN v.id END) as vagas_abertas,
        COUNT(DISTINCT CASE WHEN v.status = 'fechada' THEN v.id END) as vagas_fechadas,
        COUNT(DISTINCT c.id) as total_candidaturas,
        COUNT(DISTINCT CASE WHEN c.status = 'aprovada' THEN c.id END) as candidaturas_aprovadas,
        COUNT(DISTINCT CASE WHEN c.status = 'rejeitada' THEN c.id END) as candidaturas_rejeitadas,
        COUNT(DISTINCT e.id) as total_entrevistas,
        COUNT(DISTINCT CASE WHEN e.status = 'realizada' THEN e.id END) as entrevistas_realizadas,
        COUNT(DISTINCT cand.id) as total_candidatos_unicos,
        SUM(v.quantidade_preenchida) as vagas_preenchidas,
        SUM(v.quantidade_vagas) as vagas_total
    FROM vagas v
    LEFT JOIN candidaturas c ON v.id = c.vaga_id AND DATE(c.created_at) BETWEEN ? AND ?
    LEFT JOIN entrevistas e ON e.candidatura_id = c.id AND DATE(e.created_at) BETWEEN ? AND ?
    LEFT JOIN candidatos cand ON c.candidato_id = cand.id
    WHERE DATE(v.created_at) BETWEEN ? AND ?
    $where_acesso_sql
    $where_filtros_sql
");
$params_stats = array_merge(
    [$data_inicio, $data_fim, $data_inicio, $data_fim, $data_inicio, $data_fim],
    $params_acesso,
    $params_filtros
);
$stmt->execute($params_stats);
$stats = $stmt->fetch();

// Taxa de conversão geral
$taxa_conversao = $stats['total_candidaturas'] > 0 
    ? round(($stats['candidaturas_aprovadas'] / $stats['total_candidaturas']) * 100, 2) 
    : 0;

// Taxa de preenchimento
$taxa_preenchimento = $stats['vagas_total'] > 0 
    ? round(($stats['vagas_preenchidas'] / $stats['vagas_total']) * 100, 2) 
    : 0;

// Tempo médio de contratação
$stmt = $pdo->prepare("
    SELECT AVG(TIMESTAMPDIFF(DAY, c.created_at, c.data_aprovacao)) as tempo_medio
    FROM candidaturas c
    INNER JOIN vagas v ON c.vaga_id = v.id
    WHERE c.status = 'aprovada' 
    AND c.data_aprovacao IS NOT NULL
    AND DATE(c.created_at) BETWEEN ? AND ?
    $where_acesso_sql
    $where_filtros_sql
");
$params_tempo = array_merge([$data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_tempo);
$tempo_medio = $stmt->fetch();

// ========== VAGAS POR STATUS ==========
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as total
    FROM vagas v
    WHERE DATE(v.created_at) BETWEEN ? AND ?
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY status
");
$params_vagas_status = array_merge([$data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_vagas_status);
$vagas_por_status = $stmt->fetchAll();

// ========== CANDIDATURAS POR MÊS ==========
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(c.created_at, '%Y-%m') as mes,
        DATE_FORMAT(c.created_at, '%m/%Y') as mes_formatado,
        COUNT(*) as total,
        COUNT(DISTINCT CASE WHEN c.status = 'aprovada' THEN c.id END) as aprovadas
    FROM candidaturas c
    INNER JOIN vagas v ON c.vaga_id = v.id
    WHERE DATE(c.created_at) BETWEEN ? AND ?
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY DATE_FORMAT(c.created_at, '%Y-%m'), DATE_FORMAT(c.created_at, '%m/%Y')
    ORDER BY DATE_FORMAT(c.created_at, '%Y-%m') ASC
");
$params_mes = array_merge([$data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_mes);
$candidaturas_mes = $stmt->fetchAll();

// ========== TOP VAGAS POR CANDIDATURAS ==========
$stmt = $pdo->prepare("
    SELECT 
        v.id,
        v.titulo,
        e.nome_fantasia as empresa_nome,
        COUNT(c.id) as total_candidaturas,
        COUNT(DISTINCT CASE WHEN c.status = 'aprovada' THEN c.id END) as aprovadas
    FROM vagas v
    LEFT JOIN empresas e ON v.empresa_id = e.id
    LEFT JOIN candidaturas c ON v.id = c.vaga_id AND DATE(c.created_at) BETWEEN ? AND ?
    WHERE DATE(v.created_at) BETWEEN ? AND ?
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY v.id, v.titulo, e.nome_fantasia
    ORDER BY total_candidaturas DESC
    LIMIT 10
");
$params_top = array_merge([$data_inicio, $data_fim, $data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_top);
$top_vagas = $stmt->fetchAll();

// ========== CANDIDATURAS POR ETAPA (KANBAN) ==========
$stmt = $pdo->prepare("
    SELECT 
        e.nome as etapa_nome,
        e.codigo,
        e.cor_kanban,
        COUNT(c.id) as total
    FROM processo_seletivo_etapas e
    LEFT JOIN candidaturas c ON c.coluna_kanban = e.codigo 
        AND DATE(c.created_at) BETWEEN ? AND ?
    LEFT JOIN vagas v ON c.vaga_id = v.id
    WHERE e.ativo = 1 AND (e.vaga_id IS NULL OR v.id IS NOT NULL)
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY e.id, e.nome, e.codigo, e.cor_kanban, e.ordem
    ORDER BY e.ordem ASC
");
$params_etapas = array_merge([$data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_etapas);
$candidaturas_etapas = $stmt->fetchAll();

// ========== ENTREVISTAS POR TIPO ==========
$stmt = $pdo->prepare("
    SELECT 
        e.tipo as tipo_entrevista,
        COUNT(*) as total,
        AVG(CASE WHEN e.nota_entrevistador IS NOT NULL THEN e.nota_entrevistador END) as media_avaliacao
    FROM entrevistas e
    INNER JOIN candidaturas c ON e.candidatura_id = c.id
    INNER JOIN vagas v ON c.vaga_id = v.id
    WHERE DATE(e.created_at) BETWEEN ? AND ?
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY e.tipo
");
$params_entrevistas = array_merge([$data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_entrevistas);
$entrevistas_tipo = $stmt->fetchAll();

// ========== VAGAS POR EMPRESA ==========
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.nome_fantasia,
        COUNT(DISTINCT v.id) as total_vagas,
        COUNT(DISTINCT c.id) as total_candidaturas,
        COUNT(DISTINCT CASE WHEN c.status = 'aprovada' THEN c.id END) as aprovadas
    FROM empresas e
    INNER JOIN vagas v ON e.id = v.empresa_id
    LEFT JOIN candidaturas c ON v.id = c.vaga_id AND DATE(c.created_at) BETWEEN ? AND ?
    WHERE DATE(v.created_at) BETWEEN ? AND ?
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY e.id, e.nome_fantasia
    ORDER BY total_candidaturas DESC
");
$params_empresas = array_merge([$data_inicio, $data_fim, $data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_empresas);
$vagas_empresas = $stmt->fetchAll();

// ========== TAXA DE CONVERSÃO POR ETAPA ==========
$stmt = $pdo->prepare("
    SELECT 
        e.nome as etapa_nome,
        COUNT(DISTINCT ce.candidatura_id) as total_passaram,
        COUNT(DISTINCT CASE WHEN ce.status = 'aprovada' THEN ce.candidatura_id END) as aprovadas,
        COUNT(DISTINCT CASE WHEN ce.status = 'rejeitada' THEN ce.candidatura_id END) as rejeitadas
    FROM processo_seletivo_etapas e
    LEFT JOIN candidaturas_etapas ce ON ce.etapa_id = e.id
    LEFT JOIN candidaturas c ON c.id = ce.candidatura_id
    LEFT JOIN vagas v ON c.vaga_id = v.id
    WHERE e.ativo = 1 
    AND (ce.id IS NULL OR DATE(ce.created_at) BETWEEN ? AND ?)
    AND (c.id IS NULL OR DATE(c.created_at) BETWEEN ? AND ?)
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY e.id, e.nome, e.ordem
    HAVING total_passaram > 0
    ORDER BY e.ordem ASC
");
$params_conversao = array_merge([$data_inicio, $data_fim, $data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_conversao);
$conversao_etapas = $stmt->fetchAll();

// Busca empresas para filtro
$stmt = $pdo->query("SELECT id, nome_fantasia FROM empresas ORDER BY nome_fantasia");
$empresas = $stmt->fetchAll();

// Busca vagas para filtro
$stmt_vagas = $pdo->prepare("
    SELECT v.id, v.titulo, e.nome_fantasia
    FROM vagas v
    LEFT JOIN empresas e ON v.empresa_id = e.id
    WHERE 1=1 $where_acesso_sql
    ORDER BY v.created_at DESC
    LIMIT 100
");
$stmt_vagas->execute($params_acesso);
$vagas_filtro = $stmt_vagas->fetchAll();
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <!-- Header -->
                <div class="card mb-5">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2 class="mb-0">Analytics de Recrutamento e Seleção</h2>
                            <span class="text-muted fs-6 ms-2">Análise completa do processo de recrutamento</span>
                        </div>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary" onclick="window.print()">
                                <i class="ki-duotone ki-printer fs-2"></i>
                                Imprimir
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filtros -->
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Empresa</label>
                                <select name="empresa_id" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($empresas as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $empresa_id == $emp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['nome_fantasia']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Vaga Específica</label>
                                <select name="vaga_id" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($vagas_filtro as $v): ?>
                                    <option value="<?= $v['id'] ?>" <?= $vaga_id_filtro == $v['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['titulo']) ?> - <?= htmlspecialchars($v['nome_fantasia']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Data Início</label>
                                <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Data Fim</label>
                                <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ki-duotone ki-magnifier fs-2"></i>
                                    Filtrar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Cards de Métricas Principais -->
                <div class="row g-5 g-xl-8 mb-5">
                    <div class="col-xl-3">
                        <div class="card bg-primary bg-opacity-10 border border-primary border-opacity-25">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block fs-7">Total de Vagas</span>
                                        <span class="text-primary fw-bold fs-2x"><?= $stats['total_vagas'] ?></span>
                                        <span class="text-muted fs-7 d-block mt-1">
                                            <?= $stats['vagas_abertas'] ?> abertas
                                        </span>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="ki-duotone ki-briefcase fs-2x text-primary">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3">
                        <div class="card bg-success bg-opacity-10 border border-success border-opacity-25">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block fs-7">Total de Candidaturas</span>
                                        <span class="text-success fw-bold fs-2x"><?= $stats['total_candidaturas'] ?></span>
                                        <span class="text-muted fs-7 d-block mt-1">
                                            <?= $stats['candidaturas_aprovadas'] ?> aprovadas
                                        </span>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="ki-duotone ki-profile-user fs-2x text-success">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3">
                        <div class="card bg-info bg-opacity-10 border border-info border-opacity-25">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block fs-7">Taxa de Conversão</span>
                                        <span class="text-info fw-bold fs-2x"><?= $taxa_conversao ?>%</span>
                                        <span class="text-muted fs-7 d-block mt-1">
                                            <?= $stats['candidaturas_aprovadas'] ?> de <?= $stats['total_candidaturas'] ?>
                                        </span>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="ki-duotone ki-chart-simple fs-2x text-info">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3">
                        <div class="card bg-warning bg-opacity-10 border border-warning border-opacity-25">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block fs-7">Tempo Médio</span>
                                        <span class="text-warning fw-bold fs-2x">
                                            <?= $tempo_medio && $tempo_medio['tempo_medio'] ? round($tempo_medio['tempo_medio']) : 'N/A' ?>
                                        </span>
                                        <span class="text-muted fs-7 d-block mt-1">dias</span>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="ki-duotone ki-calendar fs-2x text-warning">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gráficos -->
                <div class="row g-5 g-xl-8 mb-5">
                    <!-- Gráfico: Vagas por Status -->
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Vagas por Status</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartVagasStatus" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gráfico: Evolução de Candidaturas -->
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Evolução de Candidaturas</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartEvolucao" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-5 g-xl-8 mb-5">
                    <!-- Gráfico: Candidaturas por Etapa -->
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Distribuição por Etapa (Kanban)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartEtapas" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gráfico: Taxa de Conversão por Etapa -->
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Taxa de Conversão por Etapa</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartConversao" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-5 g-xl-8 mb-5">
                    <!-- Gráfico: Top Vagas -->
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Top 10 Vagas por Candidaturas</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartTopVagas" style="height: 400px;"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gráfico: Vagas por Empresa -->
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Candidaturas por Empresa</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartEmpresas" style="height: 400px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabelas Detalhadas -->
                <div class="row">
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Top Vagas</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                        <thead>
                                            <tr class="fw-bold text-muted">
                                                <th>Vaga</th>
                                                <th>Empresa</th>
                                                <th>Candidaturas</th>
                                                <th>Aprovadas</th>
                                                <th>Taxa</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_vagas as $vaga): 
                                                $taxa_vaga = $vaga['total_candidaturas'] > 0 
                                                    ? round(($vaga['aprovadas'] / $vaga['total_candidaturas']) * 100, 1) 
                                                    : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <a href="vaga_view.php?id=<?= $vaga['id'] ?>" class="text-gray-800 fw-bold">
                                                        <?= htmlspecialchars($vaga['titulo']) ?>
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars($vaga['empresa_nome']) ?></td>
                                                <td><span class="badge badge-light-primary"><?= $vaga['total_candidaturas'] ?></span></td>
                                                <td><span class="badge badge-light-success"><?= $vaga['aprovadas'] ?></span></td>
                                                <td><span class="badge badge-light-info"><?= $taxa_vaga ?>%</span></td>
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
                                <h3 class="card-title">Estatísticas por Empresa</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                        <thead>
                                            <tr class="fw-bold text-muted">
                                                <th>Empresa</th>
                                                <th>Vagas</th>
                                                <th>Candidaturas</th>
                                                <th>Aprovadas</th>
                                                <th>Taxa</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vagas_empresas as $emp): 
                                                $taxa_emp = $emp['total_candidaturas'] > 0 
                                                    ? round(($emp['aprovadas'] / $emp['total_candidaturas']) * 100, 1) 
                                                    : 0;
                                            ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($emp['nome_fantasia']) ?></td>
                                                <td><span class="badge badge-light-primary"><?= $emp['total_vagas'] ?></span></td>
                                                <td><span class="badge badge-light-info"><?= $emp['total_candidaturas'] ?></span></td>
                                                <td><span class="badge badge-light-success"><?= $emp['aprovadas'] ?></span></td>
                                                <td><span class="badge badge-light-warning"><?= $taxa_emp ?>%</span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Seção: Formulários de Cultura -->
                <?php
                // Busca formulários de cultura com respostas
                $stmt = $pdo->prepare("
                    SELECT 
                        fc.id,
                        fc.nome,
                        COUNT(DISTINCT fr.candidatura_id) as total_respostas,
                        COUNT(DISTINCT fr.campo_id) as total_campos_respondidos
                    FROM formularios_cultura fc
                    INNER JOIN formularios_cultura_respostas fr ON fc.id = fr.formulario_id
                    INNER JOIN candidaturas c ON fr.candidatura_id = c.id
                    INNER JOIN vagas v ON c.vaga_id = v.id
                    WHERE fc.ativo = 1
                    AND DATE(fr.created_at) BETWEEN ? AND ?
                    $where_acesso_sql
                    $where_filtros_sql
                    GROUP BY fc.id, fc.nome
                    ORDER BY total_respostas DESC
                ");
                $params_formularios = array_merge([$data_inicio, $data_fim], $params_acesso, $params_filtros);
                $stmt->execute($params_formularios);
                $formularios_cultura = $stmt->fetchAll();
                
                // Busca respostas mais frequentes de formulários de cultura (para campos radio/checkbox/select)
                $stmt = $pdo->prepare("
                    SELECT 
                        fc.id as formulario_id,
                        fc.nome as formulario_nome,
                        fcc.label as campo_label,
                        fcc.tipo_campo,
                        fr.resposta,
                        COUNT(*) as total_respostas
                    FROM formularios_cultura_respostas fr
                    INNER JOIN formularios_cultura fc ON fr.formulario_id = fc.id
                    INNER JOIN formularios_cultura_campos fcc ON fr.campo_id = fcc.id
                    INNER JOIN candidaturas c ON fr.candidatura_id = c.id
                    INNER JOIN vagas v ON c.vaga_id = v.id
                    WHERE DATE(fr.created_at) BETWEEN ? AND ?
                    AND fcc.tipo_campo IN ('radio', 'checkbox', 'select')
                    AND fc.ativo = 1
                    $where_acesso_sql
                    $where_filtros_sql
                    GROUP BY fc.id, fc.nome, fcc.label, fcc.tipo_campo, fr.resposta
                    ORDER BY total_respostas DESC
                    LIMIT 20
                ");
                $stmt->execute($params_formularios);
                $respostas_frequentes = $stmt->fetchAll();
                
                // Agrupa respostas por formulário
                $respostas_por_formulario = [];
                foreach ($respostas_frequentes as $resp) {
                    $form_id = $resp['formulario_id'];
                    if (!isset($respostas_por_formulario[$form_id])) {
                        $respostas_por_formulario[$form_id] = [
                            'nome' => $resp['formulario_nome'],
                            'respostas' => []
                        ];
                    }
                    $respostas_por_formulario[$form_id]['respostas'][] = $resp;
                }
                ?>
                
                <?php if (!empty($formularios_cultura)): ?>
                <div class="row g-5 g-xl-8 mb-5">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header border-0 pt-6">
                                <div class="card-title">
                                    <h2 class="mb-0">Formulários de Cultura</h2>
                                    <span class="text-muted fs-6 ms-2">Análise de respostas dos formulários de alinhamento cultural</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Cards de Métricas -->
                                <div class="row g-3 mb-5">
                                    <?php foreach ($formularios_cultura as $form): ?>
                                    <div class="col-md-3">
                                        <div class="card bg-light-primary border border-primary border-opacity-25">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1">
                                                        <span class="text-muted fw-semibold d-block fs-7"><?= htmlspecialchars($form['nome']) ?></span>
                                                        <span class="text-primary fw-bold fs-3"><?= $form['total_respostas'] ?></span>
                                                        <span class="text-muted fs-8">respostas</span>
                                                    </div>
                                                    <div class="flex-shrink-0">
                                                        <a href="formulario_cultura_analytics.php?id=<?= $form['id'] ?>" class="btn btn-sm btn-primary">
                                                            Ver Analytics
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Respostas Mais Frequentes -->
                                <?php if (!empty($respostas_por_formulario)): ?>
                                <div class="row">
                                    <?php foreach ($respostas_por_formulario as $form_id => $dados_form): ?>
                                    <div class="col-xl-6 mb-5">
                                        <div class="card">
                                            <div class="card-header">
                                                <h3 class="card-title"><?= htmlspecialchars($dados_form['nome']) ?></h3>
                                                <div class="card-toolbar">
                                                    <a href="formulario_cultura_analytics.php?id=<?= $form_id ?>" class="btn btn-sm btn-light-primary">
                                                        Ver Completo
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Campo</th>
                                                                <th>Resposta</th>
                                                                <th class="text-end">Quantidade</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($dados_form['respostas'] as $resp): ?>
                                                            <tr>
                                                                <td>
                                                                    <span class="badge badge-light-info"><?= htmlspecialchars($resp['campo_label']) ?></span>
                                                                    <small class="text-muted d-block"><?= htmlspecialchars($resp['tipo_campo']) ?></small>
                                                                </td>
                                                                <td><?= htmlspecialchars($resp['resposta']) ?></td>
                                                                <td class="text-end">
                                                                    <span class="badge badge-light-primary"><?= $resp['total_respostas'] ?></span>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const vagasStatusData = <?= json_encode($vagas_por_status) ?>;
const candidaturasMesData = <?= json_encode($candidaturas_mes) ?>;
const candidaturasEtapasData = <?= json_encode($candidaturas_etapas) ?>;
const conversaoEtapasData = <?= json_encode($conversao_etapas) ?>;
const topVagasData = <?= json_encode($top_vagas) ?>;
const vagasEmpresasData = <?= json_encode($vagas_empresas) ?>;

// Gráfico: Vagas por Status
const ctxVagasStatus = document.getElementById('chartVagasStatus');
if (ctxVagasStatus && vagasStatusData.length > 0) {
    new Chart(ctxVagasStatus, {
        type: 'doughnut',
        data: {
            labels: vagasStatusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
            datasets: [{
                data: vagasStatusData.map(item => item.total),
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

// Gráfico: Evolução de Candidaturas
const ctxEvolucao = document.getElementById('chartEvolucao');
if (ctxEvolucao && candidaturasMesData.length > 0) {
    new Chart(ctxEvolucao, {
        type: 'line',
        data: {
            labels: candidaturasMesData.map(item => item.mes_formatado),
            datasets: [{
                label: 'Total',
                data: candidaturasMesData.map(item => item.total),
                borderColor: 'rgb(0, 158, 247)',
                backgroundColor: 'rgba(0, 158, 247, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Aprovadas',
                data: candidaturasMesData.map(item => item.aprovadas || 0),
                borderColor: 'rgb(40, 167, 69)',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Gráfico: Candidaturas por Etapa
const ctxEtapas = document.getElementById('chartEtapas');
if (ctxEtapas && candidaturasEtapasData.length > 0) {
    new Chart(ctxEtapas, {
        type: 'bar',
        data: {
            labels: candidaturasEtapasData.map(item => item.etapa_nome),
            datasets: [{
                label: 'Candidaturas',
                data: candidaturasEtapasData.map(item => item.total),
                backgroundColor: candidaturasEtapasData.map(item => item.cor_kanban || 'rgb(0, 123, 255)'),
                borderColor: candidaturasEtapasData.map(item => item.cor_kanban || 'rgb(0, 123, 255)'),
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
                    beginAtZero: true
                }
            }
        }
    });
}

// Gráfico: Taxa de Conversão por Etapa
const ctxConversao = document.getElementById('chartConversao');
if (ctxConversao && conversaoEtapasData.length > 0) {
    new Chart(ctxConversao, {
        type: 'bar',
        data: {
            labels: conversaoEtapasData.map(item => item.etapa_nome),
            datasets: [{
                label: 'Taxa de Conversão (%)',
                data: conversaoEtapasData.map(item => {
                    const total = item.total_passaram;
                    const aprovadas = item.aprovadas || 0;
                    return total > 0 ? Math.round((aprovadas / total) * 100) : 0;
                }),
                backgroundColor: 'rgba(40, 167, 69, 0.5)',
                borderColor: 'rgb(40, 167, 69)',
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
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

// Gráfico: Top Vagas
const ctxTopVagas = document.getElementById('chartTopVagas');
if (ctxTopVagas && topVagasData.length > 0) {
    new Chart(ctxTopVagas, {
        type: 'bar',
        data: {
            labels: topVagasData.map(item => item.titulo.substring(0, 30) + (item.titulo.length > 30 ? '...' : '')),
            datasets: [{
                label: 'Total',
                data: topVagasData.map(item => item.total_candidaturas),
                backgroundColor: 'rgba(0, 123, 255, 0.5)',
                borderColor: 'rgb(0, 123, 255)',
                borderWidth: 1
            }, {
                label: 'Aprovadas',
                data: topVagasData.map(item => item.aprovadas),
                backgroundColor: 'rgba(40, 167, 69, 0.5)',
                borderColor: 'rgb(40, 167, 69)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Gráfico: Vagas por Empresa
const ctxEmpresas = document.getElementById('chartEmpresas');
if (ctxEmpresas && vagasEmpresasData.length > 0) {
    new Chart(ctxEmpresas, {
        type: 'bar',
        data: {
            labels: vagasEmpresasData.map(item => item.nome_fantasia),
            datasets: [{
                label: 'Candidaturas',
                data: vagasEmpresasData.map(item => item.total_candidaturas),
                backgroundColor: 'rgba(255, 193, 7, 0.5)',
                borderColor: 'rgb(255, 193, 7)',
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
                    beginAtZero: true
                }
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

