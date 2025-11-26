<?php
/**
 * Relatórios do LMS
 */

$page_title = 'Relatórios LMS';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_relatorios.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$filtro_curso = $_GET['curso'] ?? '';
$filtro_periodo = $_GET['periodo'] ?? 'mes_atual';
$filtro_status = $_GET['status'] ?? '';

// Calcula período
$data_inicio = null;
$data_fim = null;

switch ($filtro_periodo) {
    case 'hoje':
        $data_inicio = date('Y-m-d');
        $data_fim = date('Y-m-d');
        break;
    case 'semana':
        $data_inicio = date('Y-m-d', strtotime('-7 days'));
        $data_fim = date('Y-m-d');
        break;
    case 'mes_atual':
        $data_inicio = date('Y-m-01');
        $data_fim = date('Y-m-d');
        break;
    case 'mes_anterior':
        $data_inicio = date('Y-m-01', strtotime('-1 month'));
        $data_fim = date('Y-m-t', strtotime('-1 month'));
        break;
    case 'ano':
        $data_inicio = date('Y-01-01');
        $data_fim = date('Y-m-d');
        break;
}

// Estatísticas gerais
$where_stats = [];
$params_stats = [];

if ($filtro_curso) {
    $where_stats[] = "c.id = ?";
    $params_stats[] = $filtro_curso;
}

if ($data_inicio && $data_fim) {
    $where_stats[] = "DATE(pc.data_conclusao) BETWEEN ? AND ?";
    $params_stats[] = $data_inicio;
    $params_stats[] = $data_fim;
}

$where_sql_stats = !empty($where_stats) ? 'WHERE ' . implode(' AND ', $where_stats) : '';

// Total de cursos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cursos WHERE status = 'publicado'");
$total_cursos = $stmt->fetch()['total'] ?? 0;

// Total de aulas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM aulas WHERE status = 'publicado'");
$total_aulas = $stmt->fetch()['total'] ?? 0;

// Total de colaboradores com progresso
$sql_colaboradores = "
    SELECT COUNT(DISTINCT pc.colaborador_id) as total
    FROM progresso_colaborador pc
    INNER JOIN cursos c ON c.id = pc.curso_id
    $where_sql_stats
";
$stmt = $pdo->prepare($sql_colaboradores);
$stmt->execute($params_stats);
$total_colaboradores = $stmt->fetch()['total'] ?? 0;

// Total de conclusões
$where_conclusoes = ["pc.status = ?"];
$params_conclusoes = ['concluido'];

if ($filtro_curso) {
    $where_conclusoes[] = "c.id = ?";
    $params_conclusoes[] = $filtro_curso;
}

if ($data_inicio && $data_fim) {
    $where_conclusoes[] = "DATE(pc.data_conclusao) BETWEEN ? AND ?";
    $params_conclusoes[] = $data_inicio;
    $params_conclusoes[] = $data_fim;
}

$where_sql_conclusoes = 'WHERE ' . implode(' AND ', $where_conclusoes);

$sql_conclusoes = "
    SELECT COUNT(*) as total
    FROM progresso_colaborador pc
    INNER JOIN cursos c ON c.id = pc.curso_id
    $where_sql_conclusoes
";
$stmt = $pdo->prepare($sql_conclusoes);
$stmt->execute($params_conclusoes);
$total_conclusoes = $stmt->fetch()['total'] ?? 0;

// Cursos mais acessados
$sql_populares = "
    SELECT c.id, c.titulo, COUNT(DISTINCT pc.colaborador_id) as total_acessos
    FROM cursos c
    LEFT JOIN progresso_colaborador pc ON pc.curso_id = c.id
    WHERE c.status = 'publicado'
    GROUP BY c.id, c.titulo
    ORDER BY total_acessos DESC
    LIMIT 10
";
$stmt = $pdo->query($sql_populares);
$cursos_populares = $stmt->fetchAll();

// Cursos obrigatórios - status
$sql_obrigatorios = "
    SELECT 
        status,
        COUNT(*) as total
    FROM cursos_obrigatorios_colaboradores
    GROUP BY status
";
$stmt = $pdo->query($sql_obrigatorios);
$stats_obrigatorios = $stmt->fetchAll();

// Busca cursos para filtro
$stmt = $pdo->query("SELECT id, titulo FROM cursos WHERE status = 'publicado' ORDER BY titulo");
$cursos_filtro = $stmt->fetchAll();

// ========== MÉTRICAS AVANÇADAS ==========

// Total de aulas iniciadas
$where_iniciadas = [];
$params_iniciadas = [];
if ($filtro_curso) {
    $where_iniciadas[] = "c.id = ?";
    $params_iniciadas[] = $filtro_curso;
}
if ($data_inicio && $data_fim) {
    $where_iniciadas[] = "DATE(pc.data_inicio) BETWEEN ? AND ?";
    $params_iniciadas[] = $data_inicio;
    $params_iniciadas[] = $data_fim;
}
$where_sql_iniciadas = !empty($where_iniciadas) ? 'WHERE ' . implode(' AND ', $where_iniciadas) : '';
$sql_iniciadas = "SELECT COUNT(DISTINCT CONCAT(pc.colaborador_id, '-', pc.curso_id, '-', pc.aula_id)) as total FROM progresso_colaborador pc INNER JOIN cursos c ON c.id = pc.curso_id $where_sql_iniciadas";
$stmt = $pdo->prepare($sql_iniciadas);
$stmt->execute($params_iniciadas);
$total_iniciadas = $stmt->fetch()['total'] ?? 0;

// Taxa de conclusão (%)
$taxa_conclusao = $total_iniciadas > 0 ? round(($total_conclusoes / $total_iniciadas) * 100, 1) : 0;

// Tempo médio de conclusão (em dias)
$where_tempo = ["pc.status = 'concluido'"];
$params_tempo = [];
if ($filtro_curso) {
    $where_tempo[] = "c.id = ?";
    $params_tempo[] = $filtro_curso;
}
if ($data_inicio && $data_fim) {
    $where_tempo[] = "DATE(pc.data_conclusao) BETWEEN ? AND ?";
    $params_tempo[] = $data_inicio;
    $params_tempo[] = $data_fim;
}
$where_sql_tempo = 'WHERE ' . implode(' AND ', $where_tempo);
$sql_tempo = "SELECT AVG(TIMESTAMPDIFF(DAY, pc.data_inicio, pc.data_conclusao)) as tempo_medio FROM progresso_colaborador pc INNER JOIN cursos c ON c.id = pc.curso_id $where_sql_tempo AND pc.data_inicio IS NOT NULL AND pc.data_conclusao IS NOT NULL";
$stmt = $pdo->prepare($sql_tempo);
$stmt->execute($params_tempo);
$tempo_medio = $stmt->fetch()['tempo_medio'] ?? 0;
$tempo_medio_dias = round($tempo_medio, 1);

// Certificados emitidos no período
$where_cert = [];
$params_cert = [];
if ($filtro_curso) {
    $where_cert[] = "c.id = ?";
    $params_cert[] = $filtro_curso;
}
if ($data_inicio && $data_fim) {
    $where_cert[] = "DATE(cert.data_emissao) BETWEEN ? AND ?";
    $params_cert[] = $data_inicio;
    $params_cert[] = $data_fim;
}
$where_sql_cert = !empty($where_cert) ? 'WHERE ' . implode(' AND ', $where_cert) : '';
$sql_cert = "SELECT COUNT(*) as total FROM certificados cert INNER JOIN cursos c ON c.id = cert.curso_id $where_sql_cert";
$stmt = $pdo->prepare($sql_cert);
$stmt->execute($params_cert);
$total_certificados = $stmt->fetch()['total'] ?? 0;

// Avaliações realizadas
$where_aval = [];
$params_aval = [];
if ($filtro_curso) {
    $where_aval[] = "a.curso_id = ?";
    $params_aval[] = $filtro_curso;
}
if ($data_inicio && $data_fim) {
    $where_aval[] = "DATE(ra.data_resposta) BETWEEN ? AND ?";
    $params_aval[] = $data_inicio;
    $params_aval[] = $data_fim;
}
$where_sql_aval = !empty($where_aval) ? 'WHERE ' . implode(' AND ', $where_aval) : '';
$sql_aval = "SELECT COUNT(*) as total FROM respostas_avaliacao ra INNER JOIN avaliacoes a ON a.id = ra.avaliacao_id $where_sql_aval";
$stmt = $pdo->prepare($sql_aval);
$stmt->execute($params_aval);
$total_avaliacoes = $stmt->fetch()['total'] ?? 0;

// Taxa de aprovação em avaliações
$sql_aprovacao = "SELECT COUNT(*) as total, COUNT(CASE WHEN ra.status = 'aprovado' THEN 1 END) as aprovados FROM respostas_avaliacao ra INNER JOIN avaliacoes a ON a.id = ra.avaliacao_id $where_sql_aval";
$stmt = $pdo->prepare($sql_aprovacao);
$stmt->execute($params_aval);
$stats_aprovacao = $stmt->fetch();
$taxa_aprovacao = $stats_aprovacao['total'] > 0 ? round(($stats_aprovacao['aprovados'] / $stats_aprovacao['total']) * 100, 1) : 0;

// Cursos obrigatórios vencidos
$sql_vencidos = "SELECT COUNT(*) as total FROM cursos_obrigatorios_colaboradores WHERE status = 'vencido'";
if ($filtro_curso) {
    $sql_vencidos = "SELECT COUNT(*) as total FROM cursos_obrigatorios_colaboradores WHERE curso_id = ? AND status = 'vencido'";
    $stmt = $pdo->prepare($sql_vencidos);
    $stmt->execute([$filtro_curso]);
} else {
    $stmt = $pdo->query($sql_vencidos);
}
$total_vencidos = $stmt->fetch()['total'] ?? 0;

// Top colaboradores (mais cursos concluídos)
$where_top_colab = ["pc.status = 'concluido'"];
$params_top_colab = [];
if ($filtro_curso) {
    $where_top_colab[] = "c.id = ?";
    $params_top_colab[] = $filtro_curso;
}
if ($data_inicio && $data_fim) {
    $where_top_colab[] = "DATE(pc.data_conclusao) BETWEEN ? AND ?";
    $params_top_colab[] = $data_inicio;
    $params_top_colab[] = $data_fim;
}
$where_sql_top_colab = 'WHERE ' . implode(' AND ', $where_top_colab);
$sql_top_colab = "
    SELECT col.nome_completo, col.id, COUNT(DISTINCT pc.curso_id) as cursos_concluidos
    FROM progresso_colaborador pc
    INNER JOIN cursos c ON c.id = pc.curso_id
    INNER JOIN colaboradores col ON col.id = pc.colaborador_id
    $where_sql_top_colab
    GROUP BY col.id, col.nome_completo
    ORDER BY cursos_concluidos DESC
    LIMIT 10
";
$stmt = $pdo->prepare($sql_top_colab);
$stmt->execute($params_top_colab);
$top_colaboradores = $stmt->fetchAll();

// Cursos com melhor taxa de conclusão
$sql_melhor_taxa = "
    SELECT 
        c.id,
        c.titulo,
        COUNT(DISTINCT pc.colaborador_id) as total_iniciados,
        COUNT(DISTINCT CASE WHEN pc.status = 'concluido' THEN pc.colaborador_id END) as total_concluidos,
        CASE 
            WHEN COUNT(DISTINCT pc.colaborador_id) > 0 
            THEN ROUND((COUNT(DISTINCT CASE WHEN pc.status = 'concluido' THEN pc.colaborador_id END) / COUNT(DISTINCT pc.colaborador_id)) * 100, 1)
            ELSE 0 
        END as taxa_conclusao
    FROM cursos c
    LEFT JOIN progresso_colaborador pc ON pc.curso_id = c.id
    WHERE c.status = 'publicado'
    GROUP BY c.id, c.titulo
    HAVING total_iniciados > 0
    ORDER BY taxa_conclusao DESC, total_concluidos DESC
    LIMIT 10
";
$stmt = $pdo->query($sql_melhor_taxa);
$cursos_melhor_taxa = $stmt->fetchAll();

// Evolução temporal (conclusões por dia)
$where_evolucao = ["pc.status = 'concluido'"];
$params_evolucao = [];
if ($filtro_curso) {
    $where_evolucao[] = "c.id = ?";
    $params_evolucao[] = $filtro_curso;
}
if ($data_inicio && $data_fim) {
    $where_evolucao[] = "DATE(pc.data_conclusao) BETWEEN ? AND ?";
    $params_evolucao[] = $data_inicio;
    $params_evolucao[] = $data_fim;
}
$where_sql_evolucao = 'WHERE ' . implode(' AND ', $where_evolucao);
$sql_evolucao = "
    SELECT 
        DATE(pc.data_conclusao) as data,
        COUNT(*) as total
    FROM progresso_colaborador pc
    INNER JOIN cursos c ON c.id = pc.curso_id
    $where_sql_evolucao
    GROUP BY DATE(pc.data_conclusao)
    ORDER BY data ASC
";
$stmt = $pdo->prepare($sql_evolucao);
$stmt->execute($params_evolucao);
$evolucao_temporal = $stmt->fetchAll();

// Distribuição por empresa
$where_empresa = [];
$params_empresa = [];
if ($filtro_curso) {
    $where_empresa[] = "c.id = ?";
    $params_empresa[] = $filtro_curso;
}
if ($data_inicio && $data_fim) {
    $where_empresa[] = "DATE(pc.data_conclusao) BETWEEN ? AND ?";
    $params_empresa[] = $data_inicio;
    $params_empresa[] = $data_fim;
}
$where_sql_empresa = 'WHERE ' . implode(' AND ', $where_empresa);
$sql_empresa = "
    SELECT 
        e.nome_fantasia,
        COUNT(DISTINCT pc.colaborador_id) as total_colaboradores,
        COUNT(CASE WHEN pc.status = 'concluido' THEN 1 END) as total_conclusoes
    FROM progresso_colaborador pc
    INNER JOIN cursos c ON c.id = pc.curso_id
    INNER JOIN colaboradores col ON col.id = pc.colaborador_id
    LEFT JOIN empresas e ON e.id = col.empresa_id
    $where_sql_empresa
    GROUP BY e.id, e.nome_fantasia
    ORDER BY total_conclusoes DESC
    LIMIT 10
";
$stmt = $pdo->prepare($sql_empresa);
$stmt->execute($params_empresa);
$distribuicao_empresa = $stmt->fetchAll();

// Comparação período anterior (para crescimento)
$data_inicio_anterior = null;
$data_fim_anterior = null;
if ($filtro_periodo === 'mes_atual') {
    $data_inicio_anterior = date('Y-m-01', strtotime('-1 month'));
    $data_fim_anterior = date('Y-m-t', strtotime('-1 month'));
} elseif ($filtro_periodo === 'semana') {
    $data_inicio_anterior = date('Y-m-d', strtotime('-14 days'));
    $data_fim_anterior = date('Y-m-d', strtotime('-7 days'));
}

$conclusoes_anterior = 0;
if ($data_inicio_anterior && $data_fim_anterior) {
    $where_anterior = ["pc.status = 'concluido'", "DATE(pc.data_conclusao) BETWEEN ? AND ?"];
    $params_anterior = [$data_inicio_anterior, $data_fim_anterior];
    if ($filtro_curso) {
        $where_anterior[] = "c.id = ?";
        $params_anterior[] = $filtro_curso;
    }
    $where_sql_anterior = 'WHERE ' . implode(' AND ', $where_anterior);
    $sql_anterior = "SELECT COUNT(*) as total FROM progresso_colaborador pc INNER JOIN cursos c ON c.id = pc.curso_id $where_sql_anterior";
    $stmt = $pdo->prepare($sql_anterior);
    $stmt->execute($params_anterior);
    $conclusoes_anterior = $stmt->fetch()['total'] ?? 0;
}

$crescimento = 0;
if ($conclusoes_anterior > 0) {
    $crescimento = round((($total_conclusoes - $conclusoes_anterior) / $conclusoes_anterior) * 100, 1);
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Relatórios LMS</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="lms_cursos.php" class="text-muted text-hover-primary">Escola Privus</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Relatórios</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Filtros-->
        <div class="card mb-5">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Curso</label>
                        <select name="curso" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($cursos_filtro as $curso): ?>
                            <option value="<?= $curso['id'] ?>" <?= $filtro_curso == $curso['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($curso['titulo']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Período</label>
                        <select name="periodo" class="form-select">
                            <option value="hoje" <?= $filtro_periodo == 'hoje' ? 'selected' : '' ?>>Hoje</option>
                            <option value="semana" <?= $filtro_periodo == 'semana' ? 'selected' : '' ?>>Últimos 7 dias</option>
                            <option value="mes_atual" <?= $filtro_periodo == 'mes_atual' ? 'selected' : '' ?>>Mês Atual</option>
                            <option value="mes_anterior" <?= $filtro_periodo == 'mes_anterior' ? 'selected' : '' ?>>Mês Anterior</option>
                            <option value="ano" <?= $filtro_periodo == 'ano' ? 'selected' : '' ?>>Ano Atual</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Filtros-->
        
        <!--begin::Estatísticas Gerais-->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Total de Cursos</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $total_cursos ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Total de Aulas</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $total_aulas ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Colaboradores Ativos</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $total_colaboradores ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Conclusões</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $total_conclusoes ?></div>
                        <?php if ($crescimento != 0): ?>
                        <div class="text-<?= $crescimento > 0 ? 'success' : 'danger' ?> fs-7 mt-1">
                            <i class="ki-duotone ki-arrow-<?= $crescimento > 0 ? 'up' : 'down' ?> fs-6">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <?= abs($crescimento) ?>%
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!--begin::Métricas Avançadas-->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Taxa de Conclusão</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $taxa_conclusao ?>%</div>
                        <div class="text-muted fs-7 mt-1"><?= $total_conclusoes ?> de <?= $total_iniciadas ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Tempo Médio</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $tempo_medio_dias ?></div>
                        <div class="text-muted fs-7 mt-1">dias para conclusão</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Certificados</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $total_certificados ?></div>
                        <div class="text-muted fs-7 mt-1">emitidos no período</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Taxa Aprovação</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $taxa_aprovacao ?>%</div>
                        <div class="text-muted fs-7 mt-1"><?= $total_avaliacoes ?> avaliações</div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($total_vencidos > 0): ?>
        <div class="alert alert-danger d-flex align-items-center p-5 mb-4">
            <i class="ki-duotone ki-information-5 fs-2hx text-danger me-4">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-danger">Atenção!</h4>
                <span><?= $total_vencidos ?> curso(s) obrigatório(s) vencido(s) precisam de atenção.</span>
            </div>
        </div>
        <?php endif; ?>
        <!--end::Métricas Avançadas-->
        
        <div class="row g-4 mb-4">
            <!--begin::Cursos Mais Acessados-->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Cursos Mais Acessados</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <?php if (empty($cursos_populares)): ?>
                        <div class="text-center p-5">
                            <div class="text-muted">Nenhum dado disponível</div>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5">
                                <thead>
                                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-200px">Curso</th>
                                        <th class="min-w-100px text-end">Acessos</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600 fw-semibold">
                                    <?php foreach ($cursos_populares as $curso): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($curso['titulo']) ?></td>
                                        <td class="text-end">
                                            <span class="badge badge-primary"><?= $curso['total_acessos'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Cursos Mais Acessados-->
            
            <!--begin::Status Cursos Obrigatórios-->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Status Cursos Obrigatórios</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <?php if (empty($stats_obrigatorios)): ?>
                        <div class="text-center p-5">
                            <div class="text-muted">Nenhum curso obrigatório atribuído</div>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5">
                                <thead>
                                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-150px">Status</th>
                                        <th class="min-w-100px text-end">Quantidade</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600 fw-semibold">
                                    <?php foreach ($stats_obrigatorios as $stat): ?>
                                    <?php
                                    $status_classes = [
                                        'pendente' => 'warning',
                                        'em_andamento' => 'primary',
                                        'concluido' => 'success',
                                        'vencido' => 'danger',
                                        'cancelado' => 'secondary'
                                    ];
                                    $status_class = $status_classes[$stat['status']] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-<?= $status_class ?>"><?= ucfirst($stat['status']) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <span class="fw-bold"><?= $stat['total'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Status Cursos Obrigatórios-->
        </div>
        
        <!--begin::Análises Detalhadas-->
        <div class="row g-4 mb-4">
            <!--begin::Top Colaboradores-->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Top Colaboradores</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Mais cursos concluídos</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <?php if (empty($top_colaboradores)): ?>
                        <div class="text-center p-5">
                            <div class="text-muted">Nenhum dado disponível</div>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5">
                                <thead>
                                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-50px">#</th>
                                        <th class="min-w-200px">Colaborador</th>
                                        <th class="min-w-100px text-end">Cursos</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600 fw-semibold">
                                    <?php foreach ($top_colaboradores as $index => $colab): ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-<?= $index < 3 ? 'primary' : 'light' ?>"><?= $index + 1 ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($colab['nome_completo']) ?></td>
                                        <td class="text-end">
                                            <span class="badge badge-success"><?= $colab['cursos_concluidos'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Top Colaboradores-->
            
            <!--begin::Cursos Melhor Taxa-->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Cursos com Melhor Taxa</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Maior % de conclusão</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <?php if (empty($cursos_melhor_taxa)): ?>
                        <div class="text-center p-5">
                            <div class="text-muted">Nenhum dado disponível</div>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5">
                                <thead>
                                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-200px">Curso</th>
                                        <th class="min-w-100px text-end">Taxa</th>
                                        <th class="min-w-100px text-end">Concluídos</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600 fw-semibold">
                                    <?php foreach ($cursos_melhor_taxa as $curso): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($curso['titulo']) ?></td>
                                        <td class="text-end">
                                            <span class="badge badge-success"><?= $curso['taxa_conclusao'] ?>%</span>
                                        </td>
                                        <td class="text-end">
                                            <span class="text-muted"><?= $curso['total_concluidos'] ?>/<?= $curso['total_iniciados'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Cursos Melhor Taxa-->
        </div>
        
        <!--begin::Distribuição e Evolução-->
        <div class="row g-4">
            <!--begin::Distribuição por Empresa-->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Distribuição por Empresa</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <?php if (empty($distribuicao_empresa)): ?>
                        <div class="text-center p-5">
                            <div class="text-muted">Nenhum dado disponível</div>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5">
                                <thead>
                                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-200px">Empresa</th>
                                        <th class="min-w-100px text-end">Colaboradores</th>
                                        <th class="min-w-100px text-end">Conclusões</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600 fw-semibold">
                                    <?php foreach ($distribuicao_empresa as $emp): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($emp['nome_fantasia'] ?: 'Sem empresa') ?></td>
                                        <td class="text-end"><?= $emp['total_colaboradores'] ?></td>
                                        <td class="text-end">
                                            <span class="badge badge-primary"><?= $emp['total_conclusoes'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Distribuição por Empresa-->
            
            <!--begin::Evolução Temporal-->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Evolução Temporal</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Conclusões ao longo do tempo</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <?php if (empty($evolucao_temporal)): ?>
                        <div class="text-center p-5">
                            <div class="text-muted">Nenhum dado disponível</div>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5">
                                <thead>
                                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-150px">Data</th>
                                        <th class="min-w-100px text-end">Conclusões</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600 fw-semibold">
                                    <?php foreach (array_slice($evolucao_temporal, -10) as $evol): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($evol['data'])) ?></td>
                                        <td class="text-end">
                                            <span class="badge badge-primary"><?= $evol['total'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Evolução Temporal-->
        </div>
        <!--end::Distribuição e Evolução-->
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

