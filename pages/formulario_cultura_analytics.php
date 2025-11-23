<?php
/**
 * Analytics de Formulário de Cultura
 */

$page_title = 'Analytics do Formulário de Cultura';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('formularios_cultura.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$formulario_id = (int)($_GET['id'] ?? 0);

if (!$formulario_id) {
    redirect('formularios_cultura.php', 'Formulário não encontrado', 'error');
}

// Busca formulário
$stmt = $pdo->prepare("SELECT * FROM formularios_cultura WHERE id = ?");
$stmt->execute([$formulario_id]);
$formulario = $stmt->fetch();

if (!$formulario) {
    redirect('formularios_cultura.php', 'Formulário não encontrado', 'error');
}

// Filtros
$empresa_id = !empty($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : null;
$setor_id = !empty($_GET['setor_id']) ? (int)$_GET['setor_id'] : null;
$cargo_id = !empty($_GET['cargo_id']) ? (int)$_GET['cargo_id'] : null;
$vaga_id = !empty($_GET['vaga_id']) ? (int)$_GET['vaga_id'] : null;
$etapa_id = !empty($_GET['etapa_id']) ? (int)$_GET['etapa_id'] : null;
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// Monta condições de acesso
$where_acesso = [];
$params_acesso = [];

if (!has_role(['ADMIN'])) {
    if (has_role(['RH'])) {
        $stmt_emp = $pdo->prepare("SELECT empresa_id FROM usuarios_empresas WHERE usuario_id = ?");
        $stmt_emp->execute([$usuario['id']]);
        $empresas_acesso = $stmt_emp->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($empresas_acesso)) {
            $placeholders = implode(',', array_fill(0, count($empresas_acesso), '?'));
            $where_acesso[] = "v.empresa_id IN ($placeholders)";
            $params_acesso = array_merge($params_acesso, $empresas_acesso);
        } else {
            $where_acesso[] = "1 = 0";
        }
    } else {
        $where_acesso[] = "1 = 0";
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

if ($setor_id) {
    $where_filtros[] = "v.setor_id = ?";
    $params_filtros[] = $setor_id;
}

if ($cargo_id) {
    $where_filtros[] = "v.cargo_id = ?";
    $params_filtros[] = $cargo_id;
}

if ($vaga_id) {
    $where_filtros[] = "c.vaga_id = ?";
    $params_filtros[] = $vaga_id;
}

if ($etapa_id) {
    $where_filtros[] = "ce.etapa_id = ?";
    $params_filtros[] = $etapa_id;
}

$where_filtros_sql = !empty($where_filtros) ? " AND " . implode(" AND ", $where_filtros) : "";

// ========== ESTATÍSTICAS GERAIS ==========
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT fr.candidatura_id) as total_respostas,
        COUNT(DISTINCT fr.campo_id) as total_campos_respondidos,
        COUNT(DISTINCT c.vaga_id) as total_vagas,
        COUNT(DISTINCT c.candidato_id) as total_candidatos
    FROM formularios_cultura_respostas fr
    INNER JOIN candidaturas c ON fr.candidatura_id = c.id
    INNER JOIN vagas v ON c.vaga_id = v.id
    WHERE fr.formulario_id = ?
    AND DATE(fr.created_at) BETWEEN ? AND ?
    $where_acesso_sql
    $where_filtros_sql
");
$params_stats = array_merge([$formulario_id, $data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_stats);
$stats = $stmt->fetch();

// ========== RESPOSTAS POR CAMPO (para radio, checkbox, select) ==========
$stmt = $pdo->prepare("
    SELECT 
        fc.id as campo_id,
        fc.label as campo_label,
        fc.tipo_campo,
        fr.resposta,
        COUNT(*) as total_respostas,
        COUNT(DISTINCT fr.candidatura_id) as total_candidaturas
    FROM formularios_cultura_campos fc
    LEFT JOIN formularios_cultura_respostas fr ON fc.id = fr.campo_id 
        AND fr.formulario_id = ?
        AND DATE(fr.created_at) BETWEEN ? AND ?
    LEFT JOIN candidaturas c ON fr.candidatura_id = c.id
    LEFT JOIN vagas v ON c.vaga_id = v.id
    WHERE fc.formulario_id = ?
    AND fc.tipo_campo IN ('radio', 'checkbox', 'select')
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY fc.id, fc.label, fc.tipo_campo, fr.resposta
    HAVING total_respostas > 0
    ORDER BY fc.ordem ASC, total_respostas DESC
");
$params_respostas = array_merge([$formulario_id, $data_inicio, $data_fim, $formulario_id], $params_acesso, $params_filtros);
$stmt->execute($params_respostas);
$respostas_campos = $stmt->fetchAll();

// Agrupa respostas por campo
$respostas_agrupadas = [];
foreach ($respostas_campos as $resposta) {
    $campo_id = $resposta['campo_id'];
    if (!isset($respostas_agrupadas[$campo_id])) {
        $respostas_agrupadas[$campo_id] = [
            'campo_label' => $resposta['campo_label'],
            'tipo_campo' => $resposta['tipo_campo'],
            'respostas' => []
        ];
    }
    $respostas_agrupadas[$campo_id]['respostas'][] = $resposta;
}

// ========== RESPOSTAS POR EMPRESA ==========
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.nome_fantasia,
        COUNT(DISTINCT fr.candidatura_id) as total_respostas
    FROM formularios_cultura_respostas fr
    INNER JOIN candidaturas c ON fr.candidatura_id = c.id
    INNER JOIN vagas v ON c.vaga_id = v.id
    INNER JOIN empresas e ON v.empresa_id = e.id
    WHERE fr.formulario_id = ?
    AND DATE(fr.created_at) BETWEEN ? AND ?
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY e.id, e.nome_fantasia
    ORDER BY total_respostas DESC
");
$params_empresas = array_merge([$formulario_id, $data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_empresas);
$respostas_empresas = $stmt->fetchAll();

// ========== RESPOSTAS POR SETOR ==========
$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.nome_setor,
        COUNT(DISTINCT fr.candidatura_id) as total_respostas
    FROM formularios_cultura_respostas fr
    INNER JOIN candidaturas c ON fr.candidatura_id = c.id
    INNER JOIN vagas v ON c.vaga_id = v.id
    LEFT JOIN setores s ON v.setor_id = s.id
    WHERE fr.formulario_id = ?
    AND DATE(fr.created_at) BETWEEN ? AND ?
    AND s.id IS NOT NULL
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY s.id, s.nome_setor
    ORDER BY total_respostas DESC
");
$params_setores = array_merge([$formulario_id, $data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_setores);
$respostas_setores = $stmt->fetchAll();

// ========== RESPOSTAS POR CARGO ==========
$stmt = $pdo->prepare("
    SELECT 
        car.id,
        car.nome_cargo,
        COUNT(DISTINCT fr.candidatura_id) as total_respostas
    FROM formularios_cultura_respostas fr
    INNER JOIN candidaturas c ON fr.candidatura_id = c.id
    INNER JOIN vagas v ON c.vaga_id = v.id
    LEFT JOIN cargos car ON v.cargo_id = car.id
    WHERE fr.formulario_id = ?
    AND DATE(fr.created_at) BETWEEN ? AND ?
    AND car.id IS NOT NULL
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY car.id, car.nome_cargo
    ORDER BY total_respostas DESC
");
$params_cargos = array_merge([$formulario_id, $data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_cargos);
$respostas_cargos = $stmt->fetchAll();

// ========== RESPOSTAS POR VAGA ==========
$stmt = $pdo->prepare("
    SELECT 
        v.id,
        v.titulo,
        e.nome_fantasia as empresa_nome,
        COUNT(DISTINCT fr.candidatura_id) as total_respostas
    FROM formularios_cultura_respostas fr
    INNER JOIN candidaturas c ON fr.candidatura_id = c.id
    INNER JOIN vagas v ON c.vaga_id = v.id
    LEFT JOIN empresas e ON v.empresa_id = e.id
    WHERE fr.formulario_id = ?
    AND DATE(fr.created_at) BETWEEN ? AND ?
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY v.id, v.titulo, e.nome_fantasia
    ORDER BY total_respostas DESC
    LIMIT 20
");
$params_vagas = array_merge([$formulario_id, $data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_vagas);
$respostas_vagas = $stmt->fetchAll();

// ========== RESPOSTAS POR ETAPA ==========
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.nome as etapa_nome,
        COUNT(DISTINCT fr.candidatura_id) as total_respostas
    FROM formularios_cultura_respostas fr
    INNER JOIN candidaturas c ON fr.candidatura_id = c.id
    LEFT JOIN candidaturas_etapas ce ON ce.candidatura_id = c.id
    LEFT JOIN processo_seletivo_etapas e ON ce.etapa_id = e.id
    WHERE fr.formulario_id = ?
    AND DATE(fr.created_at) BETWEEN ? AND ?
    AND e.id IS NOT NULL
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY e.id, e.nome
    ORDER BY total_respostas DESC
");
$params_etapas = array_merge([$formulario_id, $data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_etapas);
$respostas_etapas = $stmt->fetchAll();

// ========== EVOLUÇÃO TEMPORAL ==========
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(fr.created_at, '%Y-%m') as mes,
        DATE_FORMAT(fr.created_at, '%m/%Y') as mes_formatado,
        COUNT(DISTINCT fr.candidatura_id) as total_respostas
    FROM formularios_cultura_respostas fr
    INNER JOIN candidaturas c ON fr.candidatura_id = c.id
    INNER JOIN vagas v ON c.vaga_id = v.id
    WHERE fr.formulario_id = ?
    AND DATE(fr.created_at) BETWEEN ? AND ?
    $where_acesso_sql
    $where_filtros_sql
    GROUP BY DATE_FORMAT(fr.created_at, '%Y-%m'), DATE_FORMAT(fr.created_at, '%m/%Y')
    ORDER BY DATE_FORMAT(fr.created_at, '%Y-%m') ASC
");
$params_temporal = array_merge([$formulario_id, $data_inicio, $data_fim], $params_acesso, $params_filtros);
$stmt->execute($params_temporal);
$evolucao_temporal = $stmt->fetchAll();

// Busca empresas para filtro
$stmt = $pdo->query("SELECT id, nome_fantasia FROM empresas ORDER BY nome_fantasia");
$empresas = $stmt->fetchAll();

// Busca setores para filtro
$stmt_setores = $pdo->prepare("
    SELECT DISTINCT s.id, s.nome_setor
    FROM setores s
    INNER JOIN vagas v ON s.id = v.setor_id
    INNER JOIN candidaturas c ON v.id = c.vaga_id
    INNER JOIN formularios_cultura_respostas fr ON c.id = fr.candidatura_id
    WHERE fr.formulario_id = ?
    $where_acesso_sql
    ORDER BY s.nome_setor
");
$stmt_setores->execute(array_merge([$formulario_id], $params_acesso));
$setores_filtro = $stmt_setores->fetchAll();

// Busca cargos para filtro
$stmt_cargos = $pdo->prepare("
    SELECT DISTINCT car.id, car.nome_cargo
    FROM cargos car
    INNER JOIN vagas v ON car.id = v.cargo_id
    INNER JOIN candidaturas c ON v.id = c.vaga_id
    INNER JOIN formularios_cultura_respostas fr ON c.id = fr.candidatura_id
    WHERE fr.formulario_id = ?
    $where_acesso_sql
    ORDER BY car.nome_cargo
");
$stmt_cargos->execute(array_merge([$formulario_id], $params_acesso));
$cargos_filtro = $stmt_cargos->fetchAll();

// Busca vagas para filtro
$stmt_vagas = $pdo->prepare("
    SELECT DISTINCT v.id, v.titulo, e.nome_fantasia, v.created_at
    FROM vagas v
    LEFT JOIN empresas e ON v.empresa_id = e.id
    INNER JOIN candidaturas c ON v.id = c.vaga_id
    INNER JOIN formularios_cultura_respostas fr ON c.id = fr.candidatura_id
    WHERE fr.formulario_id = ?
    $where_acesso_sql
    ORDER BY v.created_at DESC
    LIMIT 50
");
$stmt_vagas->execute(array_merge([$formulario_id], $params_acesso));
$vagas_filtro = $stmt_vagas->fetchAll();

// Busca etapas para filtro
$stmt_etapas = $pdo->query("SELECT id, nome FROM processo_seletivo_etapas WHERE ativo = 1 ORDER BY ordem ASC");
$etapas_filtro = $stmt_etapas->fetchAll();
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <!-- Header -->
                <div class="card mb-5">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2 class="mb-0">Analytics: <?= htmlspecialchars($formulario['nome'] ?? '') ?></h2>
                            <span class="text-muted fs-6 ms-2">Análise completa das respostas do formulário</span>
                        </div>
                        <div class="card-toolbar">
                            <a href="formulario_cultura_editar.php?id=<?= $formulario_id ?>" class="btn btn-light-primary me-2">
                                <i class="ki-duotone ki-notepad-edit fs-2"></i>
                                Editar Formulário
                            </a>
                            <button type="button" class="btn btn-primary" onclick="window.print()">
                                <i class="ki-duotone ki-printer fs-2"></i>
                                Imprimir
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filtros -->
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="id" value="<?= $formulario_id ?>">
                            <div class="col-md-2">
                                <label class="form-label">Empresa</label>
                                <select name="empresa_id" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($empresas as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $empresa_id == $emp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['nome_fantasia'] ?? '') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Setor</label>
                                <select name="setor_id" class="form-select">
                                    <option value="">Todos</option>
                                    <?php foreach ($setores_filtro as $setor): ?>
                                    <option value="<?= $setor['id'] ?>" <?= $setor_id == $setor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($setor['nome_setor'] ?? '') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Cargo</label>
                                <select name="cargo_id" class="form-select">
                                    <option value="">Todos</option>
                                    <?php foreach ($cargos_filtro as $cargo): ?>
                                    <option value="<?= $cargo['id'] ?>" <?= $cargo_id == $cargo['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cargo['nome_cargo'] ?? '') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Vaga</label>
                                <select name="vaga_id" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($vagas_filtro as $v): ?>
                                    <option value="<?= $v['id'] ?>" <?= $vaga_id == $v['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['titulo'] ?? '') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Etapa</label>
                                <select name="etapa_id" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($etapas_filtro as $etapa): ?>
                                    <option value="<?= $etapa['id'] ?>" <?= $etapa_id == $etapa['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($etapa['nome'] ?? '') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Data Início</label>
                                <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Data Fim</label>
                                <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ki-duotone ki-magnifier fs-2"></i>
                                    Filtrar
                                </button>
                                <a href="formulario_cultura_analytics.php?id=<?= $formulario_id ?>" class="btn btn-light">
                                    Limpar Filtros
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Cards de Métricas -->
                <div class="row g-5 g-xl-8 mb-5">
                    <div class="col-xl-3">
                        <div class="card bg-primary bg-opacity-10 border border-primary border-opacity-25">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block fs-7">Total de Respostas</span>
                                        <span class="text-primary fw-bold fs-2x"><?= $stats['total_respostas'] ?></span>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="ki-duotone ki-message-text-2 fs-2x text-primary">
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
                    
                    <div class="col-xl-3">
                        <div class="card bg-success bg-opacity-10 border border-success border-opacity-25">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block fs-7">Candidaturas</span>
                                        <span class="text-success fw-bold fs-2x"><?= $stats['total_candidatos'] ?></span>
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
                                        <span class="text-muted fw-semibold d-block fs-7">Vagas</span>
                                        <span class="text-info fw-bold fs-2x"><?= $stats['total_vagas'] ?></span>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="ki-duotone ki-briefcase fs-2x text-info">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
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
                                        <span class="text-muted fw-semibold d-block fs-7">Campos Respondidos</span>
                                        <span class="text-warning fw-bold fs-2x"><?= $stats['total_campos_respondidos'] ?></span>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="ki-duotone ki-notepad fs-2x text-warning">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico: Evolução Temporal -->
                <?php if (!empty($evolucao_temporal)): ?>
                <div class="row g-5 g-xl-8 mb-5">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Evolução de Respostas</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartEvolucao" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Respostas por Campo (Radio, Checkbox, Select) -->
                <?php if (!empty($respostas_agrupadas)): ?>
                <div class="row g-5 g-xl-8 mb-5">
                    <?php foreach ($respostas_agrupadas as $campo_id => $dados_campo): ?>
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><?= htmlspecialchars($dados_campo['campo_label'] ?? '') ?></h3>
                                <span class="badge badge-light-info"><?= htmlspecialchars($dados_campo['tipo_campo'] ?? '') ?></span>
                            </div>
                            <div class="card-body">
                                <div style="position: relative; height: <?= $dados_campo['tipo_campo'] === 'checkbox' ? '300px' : '250px' ?>; max-height: <?= $dados_campo['tipo_campo'] === 'checkbox' ? '300px' : '250px' ?>;">
                                    <canvas id="chartCampo<?= $campo_id ?>"></canvas>
                                </div>
                                <div class="table-responsive mt-4">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Resposta</th>
                                                <th class="text-end">Quantidade</th>
                                                <th class="text-end">%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_campo = array_sum(array_column($dados_campo['respostas'], 'total_respostas'));
                                            foreach ($dados_campo['respostas'] as $resp): 
                                                $percentual = $total_campo > 0 ? round(($resp['total_respostas'] / $total_campo) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($resp['resposta'] ?? '') ?></td>
                                                <td class="text-end"><?= $resp['total_respostas'] ?></td>
                                                <td class="text-end"><?= $percentual ?>%</td>
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
                
                <!-- Tabelas de Distribuição -->
                <div class="row g-5 g-xl-8 mb-5">
                    <!-- Por Empresa -->
                    <?php if (!empty($respostas_empresas)): ?>
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Respostas por Empresa</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartEmpresas" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Por Setor -->
                    <?php if (!empty($respostas_setores)): ?>
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Respostas por Setor</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartSetores" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="row g-5 g-xl-8 mb-5">
                    <!-- Por Cargo -->
                    <?php if (!empty($respostas_cargos)): ?>
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Respostas por Cargo</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartCargos" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Por Etapa -->
                    <?php if (!empty($respostas_etapas)): ?>
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Respostas por Etapa</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartEtapas" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tabela: Respostas por Vaga -->
                <?php if (!empty($respostas_vagas)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Respostas por Vaga</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Vaga</th>
                                        <th>Empresa</th>
                                        <th class="text-end">Respostas</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($respostas_vagas as $vaga): ?>
                                    <tr>
                                        <td>
                                            <a href="vaga_view.php?id=<?= $vaga['id'] ?>" class="text-gray-800 fw-bold">
                                                <?= htmlspecialchars($vaga['titulo'] ?? '') ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($vaga['empresa_nome'] ?? 'N/A') ?></td>
                                        <td class="text-end">
                                            <span class="badge badge-light-primary"><?= $vaga['total_respostas'] ?></span>
                                        </td>
                                        <td class="text-end">
                                            <a href="formulario_cultura_analytics.php?id=<?= $formulario_id ?>&vaga_id=<?= $vaga['id'] ?>" class="btn btn-sm btn-light-primary">
                                                Ver Detalhes
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
const evolucaoData = <?= json_encode($evolucao_temporal) ?>;
const respostasEmpresasData = <?= json_encode($respostas_empresas) ?>;
const respostasSetoresData = <?= json_encode($respostas_setores) ?>;
const respostasCargosData = <?= json_encode($respostas_cargos) ?>;
const respostasEtapasData = <?= json_encode($respostas_etapas) ?>;
const respostasAgrupadasData = <?= json_encode($respostas_agrupadas) ?>;

// Gráfico: Evolução Temporal
const ctxEvolucao = document.getElementById('chartEvolucao');
if (ctxEvolucao && evolucaoData.length > 0) {
    new Chart(ctxEvolucao, {
        type: 'line',
        data: {
            labels: evolucaoData.map(item => item.mes_formatado),
            datasets: [{
                label: 'Respostas',
                data: evolucaoData.map(item => item.total_respostas),
                borderColor: 'rgb(0, 158, 247)',
                backgroundColor: 'rgba(0, 158, 247, 0.1)',
                tension: 0.4,
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
                    beginAtZero: true
                }
            }
        }
    });
}

// Gráficos por Campo (Radio/Checkbox/Select)
<?php foreach ($respostas_agrupadas as $campo_id => $dados_campo): 
    $total_campo = array_sum(array_column($dados_campo['respostas'], 'total_respostas'));
?>
const ctxCampo<?= $campo_id ?> = document.getElementById('chartCampo<?= $campo_id ?>');
if (ctxCampo<?= $campo_id ?>) {
    const dadosCampo<?= $campo_id ?> = <?= json_encode($dados_campo['respostas']) ?>;
    const isCheckbox = '<?= $dados_campo['tipo_campo'] ?>' === 'checkbox';
    
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: isCheckbox ? 'top' : 'bottom',
                display: !isCheckbox
            }
        }
    };
    
    if (isCheckbox) {
        // Configurações específicas para gráfico de barra (checkbox)
        chartOptions.indexAxis = 'y'; // Barras horizontais
        chartOptions.scales = {
            x: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            },
            y: {
                ticks: {
                    maxRotation: 0,
                    minRotation: 0
                }
            }
        };
    }
    
    new Chart(ctxCampo<?= $campo_id ?>, {
        type: isCheckbox ? 'bar' : 'doughnut',
        data: {
            labels: dadosCampo<?= $campo_id ?>.map(item => item.resposta),
            datasets: [{
                label: 'Respostas',
                data: dadosCampo<?= $campo_id ?>.map(item => item.total_respostas),
                backgroundColor: isCheckbox ? 'rgba(0, 158, 247, 0.6)' : [
                    'rgb(0, 158, 247)',
                    'rgb(40, 167, 69)',
                    'rgb(255, 193, 7)',
                    'rgb(220, 53, 69)',
                    'rgb(108, 117, 125)',
                    'rgb(114, 57, 234)',
                    'rgb(241, 65, 108)'
                ],
                borderColor: isCheckbox ? 'rgb(0, 158, 247)' : undefined,
                borderWidth: isCheckbox ? 1 : undefined
            }]
        },
        options: chartOptions
    });
}
<?php endforeach; ?>

// Gráfico: Por Empresa
const ctxEmpresas = document.getElementById('chartEmpresas');
if (ctxEmpresas && respostasEmpresasData.length > 0) {
    new Chart(ctxEmpresas, {
        type: 'bar',
        data: {
            labels: respostasEmpresasData.map(item => item.nome_fantasia),
            datasets: [{
                label: 'Respostas',
                data: respostasEmpresasData.map(item => item.total_respostas),
                backgroundColor: 'rgba(0, 158, 247, 0.5)',
                borderColor: 'rgb(0, 158, 247)',
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

// Gráfico: Por Setor
const ctxSetores = document.getElementById('chartSetores');
if (ctxSetores && respostasSetoresData.length > 0) {
    new Chart(ctxSetores, {
        type: 'bar',
        data: {
            labels: respostasSetoresData.map(item => item.nome_setor),
            datasets: [{
                label: 'Respostas',
                data: respostasSetoresData.map(item => item.total_respostas),
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
                    beginAtZero: true
                }
            }
        }
    });
}

// Gráfico: Por Cargo
const ctxCargos = document.getElementById('chartCargos');
if (ctxCargos && respostasCargosData.length > 0) {
    new Chart(ctxCargos, {
        type: 'bar',
        data: {
            labels: respostasCargosData.map(item => item.nome_cargo),
            datasets: [{
                label: 'Respostas',
                data: respostasCargosData.map(item => item.total_respostas),
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

// Gráfico: Por Etapa
const ctxEtapas = document.getElementById('chartEtapas');
if (ctxEtapas && respostasEtapasData.length > 0) {
    new Chart(ctxEtapas, {
        type: 'bar',
        data: {
            labels: respostasEtapasData.map(item => item.etapa_nome),
            datasets: [{
                label: 'Respostas',
                data: respostasEtapasData.map(item => item.total_respostas),
                backgroundColor: 'rgba(114, 57, 234, 0.5)',
                borderColor: 'rgb(114, 57, 234)',
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

