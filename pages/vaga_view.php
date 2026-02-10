<?php
/**
 * Visualização Detalhada da Vaga - Versão Melhorada com Analytics
 */

$page_title = 'Detalhes da Vaga';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('vaga_view.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$vaga_id = (int)($_GET['id'] ?? 0);

if (!$vaga_id) {
    redirect('vagas.php', 'Vaga não encontrada', 'error');
}

// Busca vaga
$stmt = $pdo->prepare("
    SELECT v.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           car.nome_cargo,
           u.nome as criado_por_nome
    FROM vagas v
    LEFT JOIN empresas e ON v.empresa_id = e.id
    LEFT JOIN setores s ON v.setor_id = s.id
    LEFT JOIN cargos car ON v.cargo_id = car.id
    LEFT JOIN usuarios u ON v.criado_por = u.id
    WHERE v.id = ?
");
$stmt->execute([$vaga_id]);
$vaga = $stmt->fetch();

// Busca estatísticas de candidaturas separadamente
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_candidaturas,
        COUNT(DISTINCT CASE WHEN status = 'aprovada' THEN id END) as candidaturas_aprovadas
    FROM candidaturas
    WHERE vaga_id = ?
");
$stmt->execute([$vaga_id]);
$stats_candidaturas = $stmt->fetch();
$vaga['total_candidaturas'] = $stats_candidaturas['total_candidaturas'];
$vaga['candidaturas_aprovadas'] = $stats_candidaturas['candidaturas_aprovadas'];

if (!$vaga || !can_access_empresa($vaga['empresa_id'])) {
    redirect('vagas.php', 'Sem permissão', 'error');
}

// Processa benefícios
$beneficios = [];
if ($vaga['beneficios']) {
    $beneficios = json_decode($vaga['beneficios'], true) ?: [];
}

// Busca etapas da vaga
$stmt = $pdo->prepare("
    SELECT e.*, ve.ordem as ordem_vaga
    FROM processo_seletivo_etapas e
    INNER JOIN vagas_etapas ve ON e.id = ve.etapa_id
    WHERE ve.vaga_id = ?
    ORDER BY ve.ordem ASC
");
$stmt->execute([$vaga_id]);
$etapas = $stmt->fetchAll();

// ========== ANALYTICS ==========
// Estatísticas gerais
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_candidaturas,
        COUNT(DISTINCT CASE WHEN status = 'aprovada' THEN id END) as aprovadas,
        COUNT(DISTINCT CASE WHEN status = 'rejeitada' THEN id END) as rejeitadas,
        COUNT(DISTINCT CASE WHEN status = 'pendente' THEN id END) as pendentes,
        COUNT(DISTINCT CASE WHEN status = 'em_andamento' THEN id END) as em_andamento,
        COUNT(DISTINCT CASE WHEN status = 'cancelada' THEN id END) as canceladas
    FROM candidaturas
    WHERE vaga_id = ?
");
$stmt->execute([$vaga_id]);
$stats = $stmt->fetch();

// Candidaturas por etapa (Kanban)
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.nome as etapa_nome,
        e.codigo as etapa_codigo,
        e.cor_kanban,
        e.ordem,
        COUNT(c.id) as total
    FROM processo_seletivo_etapas e
    LEFT JOIN candidaturas c ON c.coluna_kanban = e.codigo AND c.vaga_id = ?
    WHERE (e.vaga_id IS NULL OR e.vaga_id = ?) AND e.ativo = 1
    GROUP BY e.id, e.nome, e.codigo, e.cor_kanban, e.ordem
    ORDER BY e.ordem ASC
");
$stmt->execute([$vaga_id, $vaga_id]);
$por_etapa = $stmt->fetchAll();

// Candidaturas por mês
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as mes,
        DATE_FORMAT(created_at, '%m/%Y') as mes_formatado,
        COUNT(*) as total
    FROM candidaturas
    WHERE vaga_id = ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%m/%Y')
    ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
    LIMIT 12
");
$stmt->execute([$vaga_id]);
$por_mes = $stmt->fetchAll();

// Entrevistas realizadas
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_entrevistas,
        COUNT(DISTINCT CASE WHEN e.status = 'realizada' THEN e.id END) as realizadas,
        COUNT(DISTINCT CASE WHEN e.status = 'agendada' THEN e.id END) as agendadas,
        COUNT(DISTINCT CASE WHEN e.status = 'cancelada' THEN e.id END) as canceladas,
        AVG(CASE WHEN e.status = 'realizada' AND e.nota_entrevistador IS NOT NULL THEN e.nota_entrevistador END) as media_avaliacao
    FROM entrevistas e
    INNER JOIN candidaturas c ON e.candidatura_id = c.id
    WHERE c.vaga_id = ?
");
$stmt->execute([$vaga_id]);
$entrevistas = $stmt->fetch();

// Taxa de conversão por etapa
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.nome as etapa_nome,
        e.ordem,
        COUNT(DISTINCT ce.candidatura_id) as total_passaram,
        COUNT(DISTINCT CASE WHEN ce.status = 'aprovada' THEN ce.candidatura_id END) as aprovadas_etapa,
        COUNT(DISTINCT CASE WHEN ce.status = 'rejeitada' THEN ce.candidatura_id END) as rejeitadas_etapa
    FROM processo_seletivo_etapas e
    LEFT JOIN candidaturas_etapas ce ON ce.etapa_id = e.id
    LEFT JOIN candidaturas c ON c.id = ce.candidatura_id AND c.vaga_id = ?
    WHERE (e.vaga_id IS NULL OR e.vaga_id = ?) AND e.ativo = 1
    GROUP BY e.id, e.nome, e.ordem
    ORDER BY e.ordem ASC
");
$stmt->execute([$vaga_id, $vaga_id]);
$conversao_etapas = $stmt->fetchAll();

// Tempo médio por etapa
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.nome as etapa_nome,
        e.ordem,
        AVG(TIMESTAMPDIFF(DAY, ce.data_inicio, ce.data_conclusao)) as tempo_medio_dias
    FROM candidaturas_etapas ce
    INNER JOIN processo_seletivo_etapas e ON ce.etapa_id = e.id
    INNER JOIN candidaturas c ON c.id = ce.candidatura_id
    WHERE c.vaga_id = ? AND ce.data_conclusao IS NOT NULL
    GROUP BY e.id, e.nome, e.ordem
    ORDER BY e.ordem ASC
");
$stmt->execute([$vaga_id]);
$tempo_medio = $stmt->fetchAll();

// Taxa de conversão geral
$taxa_conversao = $stats['total_candidaturas'] > 0 
    ? round(($stats['aprovadas'] / $stats['total_candidaturas']) * 100, 2) 
    : 0;

// Tempo médio de contratação
$stmt = $pdo->prepare("
    SELECT AVG(TIMESTAMPDIFF(DAY, c.created_at, c.data_aprovacao)) as tempo_medio_contratacao
    FROM candidaturas c
    WHERE c.vaga_id = ? AND c.status = 'aprovada' AND c.data_aprovacao IS NOT NULL
");
$stmt->execute([$vaga_id]);
$tempo_contratacao = $stmt->fetch();
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <!-- Header com Informações Principais -->
                <div class="card mb-5">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2 class="mb-0"><?= htmlspecialchars($vaga['titulo']) ?></h2>
                            <span class="text-muted fs-6 ms-2">
                                <?= htmlspecialchars($vaga['empresa_nome']) ?>
                                <?php if ($vaga['nome_setor']): ?>
                                • <?= htmlspecialchars($vaga['nome_setor']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="card-toolbar">
                            <?php if (has_role(['ADMIN', 'RH'])): ?>
                            <a href="vaga_edit.php?id=<?= $vaga_id ?>" class="btn btn-light-warning me-2">
                                <i class="ki-duotone ki-notepad-edit fs-2"></i>
                                Editar
                            </a>
                            <?php endif; ?>
                            <a href="kanban_selecao.php?vaga_id=<?= $vaga_id ?>" class="btn btn-primary me-2">
                                <i class="ki-duotone ki-diagram fs-2"></i>
                                Ver Kanban
                            </a>
                            <a href="vagas.php" class="btn btn-light">
                                <i class="ki-duotone ki-arrow-left fs-2"></i>
                                Voltar
                            </a>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-5 g-xl-8">
                            <!-- Card: Total Candidaturas -->
                            <div class="col-xl-3">
                                <div class="card bg-primary bg-opacity-10 border border-primary border-opacity-25">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <span class="text-muted fw-semibold d-block fs-7">Total de Candidaturas</span>
                                                <span class="text-primary fw-bold fs-2x"><?= $stats['total_candidaturas'] ?></span>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <i class="ki-duotone ki-profile-user fs-2x text-primary">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Card: Aprovadas -->
                            <div class="col-xl-3">
                                <div class="card bg-success bg-opacity-10 border border-success border-opacity-25">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <span class="text-muted fw-semibold d-block fs-7">Aprovadas</span>
                                                <span class="text-success fw-bold fs-2x"><?= $stats['aprovadas'] ?></span>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <i class="ki-duotone ki-check-circle fs-2x text-success">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Card: Taxa de Conversão -->
                            <div class="col-xl-3">
                                <div class="card bg-info bg-opacity-10 border border-info border-opacity-25">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <span class="text-muted fw-semibold d-block fs-7">Taxa de Conversão</span>
                                                <span class="text-info fw-bold fs-2x"><?= $taxa_conversao ?>%</span>
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
                            
                            <!-- Card: Entrevistas -->
                            <div class="col-xl-3">
                                <div class="card bg-warning bg-opacity-10 border border-warning border-opacity-25">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <span class="text-muted fw-semibold d-block fs-7">Entrevistas</span>
                                                <span class="text-warning fw-bold fs-2x"><?= $entrevistas['total_entrevistas'] ?? 0 ?></span>
                                                <span class="text-muted fs-7 d-block"><?= $entrevistas['realizadas'] ?? 0 ?> realizadas</span>
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
                        
                        <!-- Informações Adicionais -->
                        <div class="row mt-5">
                            <div class="col-md-6">
                                <div class="d-flex flex-column">
                                    <div class="mb-3">
                                        <span class="text-muted fw-semibold d-block">Status</span>
                                        <span class="badge badge-light-<?= $vaga['status'] === 'aberta' ? 'success' : ($vaga['status'] === 'pausada' ? 'warning' : 'danger') ?> fs-6">
                                            <?= ucfirst($vaga['status']) ?>
                                        </span>
                                    </div>
                                    <div class="mb-3">
                                        <span class="text-muted fw-semibold d-block">Vagas Preenchidas</span>
                                        <span class="fw-bold fs-4">
                                            <?= $vaga['quantidade_preenchida'] ?>/<?= $vaga['quantidade_vagas'] ? $vaga['quantidade_vagas'] : '<span class="badge badge-light-success">Ilimitado</span>' ?>
                                        </span>
                                    </div>
                                    <?php if ($vaga['salario_min'] || $vaga['salario_max']): ?>
                                    <div class="mb-3">
                                        <span class="text-muted fw-semibold d-block">Salário</span>
                                        <span class="fw-bold fs-5">
                                            R$ <?= number_format($vaga['salario_min'] ?? 0, 2, ',', '.') ?>
                                            <?php if ($vaga['salario_max']): ?>
                                            - R$ <?= number_format($vaga['salario_max'], 2, ',', '.') ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex flex-column">
                                    <div class="mb-3">
                                        <span class="text-muted fw-semibold d-block">Modalidade</span>
                                        <span class="fw-bold"><?= htmlspecialchars($vaga['modalidade']) ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <span class="text-muted fw-semibold d-block">Tipo de Contrato</span>
                                        <span class="fw-bold"><?= htmlspecialchars($vaga['tipo_contrato']) ?></span>
                                    </div>
                                    <?php if ($vaga['horario_trabalho']): ?>
                                    <div class="mb-3">
                                        <span class="text-muted fw-semibold d-block">Horário</span>
                                        <span class="fw-bold"><?= htmlspecialchars($vaga['horario_trabalho']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($vaga['dias_trabalho']): ?>
                                    <div class="mb-3">
                                        <span class="text-muted fw-semibold d-block">Dias de Trabalho</span>
                                        <span class="fw-bold"><?= htmlspecialchars($vaga['dias_trabalho']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($beneficios)): ?>
                        <div class="mt-5">
                            <span class="text-muted fw-semibold d-block mb-2">Benefícios</span>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($beneficios as $beneficio): ?>
                                <span class="badge badge-light-primary fs-6"><?= htmlspecialchars($beneficio) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Gráficos e Analytics -->
                <div class="row g-5 g-xl-8 mb-5">
                    <!-- Gráfico: Candidaturas por Status -->
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Candidaturas por Status</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartStatus" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gráfico: Candidaturas por Mês -->
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
                    <!-- Gráfico: Distribuição por Etapa -->
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
                
                <!-- Informações Detalhadas -->
                <div class="row">
                    <div class="col-xl-6">
                        <!-- Descrição -->
                        <div class="card mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Descrição da Vaga</h3>
                            </div>
                            <div class="card-body">
                                <div class="text-gray-800 vaga-html-content"><?= $vaga['descricao'] ?></div>
                            </div>
                        </div>
                        
                        <!-- Requisitos -->
                        <?php if ($vaga['requisitos_obrigatorios'] || $vaga['requisitos_desejaveis']): ?>
                        <div class="card mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Requisitos</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($vaga['requisitos_obrigatorios']): ?>
                                <h4 class="mb-3">Obrigatórios</h4>
                                <div class="text-gray-800 vaga-html-content"><?= $vaga['requisitos_obrigatorios'] ?></div>
                                <?php endif; ?>
                                
                                <?php if ($vaga['requisitos_desejaveis']): ?>
                                <h4 class="mt-5 mb-3">Desejáveis</h4>
                                <div class="text-gray-800 vaga-html-content"><?= $vaga['requisitos_desejaveis'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-xl-6">
                        <!-- Etapas do Processo -->
                        <?php if (!empty($etapas)): ?>
                        <div class="card mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Etapas do Processo Seletivo</h3>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <?php foreach ($etapas as $index => $etapa): ?>
                                    <div class="timeline-item mb-5">
                                        <div class="timeline-line w-40px"></div>
                                        <div class="timeline-icon">
                                            <span class="badge badge-circle badge-<?= $index % 2 === 0 ? 'primary' : 'success' ?>">
                                                <?= $index + 1 ?>
                                            </span>
                                        </div>
                                        <div class="timeline-content">
                                            <h4 class="mb-1"><?= htmlspecialchars($etapa['nome']) ?></h4>
                                            <?php if ($etapa['descricao']): ?>
                                            <p class="text-muted mb-0"><?= htmlspecialchars($etapa['descricao']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Estatísticas Adicionais -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Estatísticas Adicionais</h3>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-column gap-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Criado por</span>
                                        <span class="fw-bold"><?= htmlspecialchars($vaga['criado_por_nome']) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Data de Criação</span>
                                        <span class="fw-bold"><?= date('d/m/Y H:i', strtotime($vaga['created_at'])) ?></span>
                                    </div>
                                    <?php if ($tempo_contratacao && $tempo_contratacao['tempo_medio_contratacao']): ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Tempo Médio de Contratação</span>
                                        <span class="fw-bold text-primary"><?= round($tempo_contratacao['tempo_medio_contratacao']) ?> dias</span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($entrevistas && $entrevistas['media_avaliacao']): ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Avaliação Média das Entrevistas</span>
                                        <span class="fw-bold text-success"><?= number_format($entrevistas['media_avaliacao'], 1) ?>/10.0</span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Rejeitadas</span>
                                        <span class="fw-bold text-danger"><?= $stats['rejeitadas'] ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Em Andamento</span>
                                        <span class="fw-bold text-info"><?= $stats['em_andamento'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ações Rápidas -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <a href="kanban_selecao.php?vaga_id=<?= $vaga_id ?>" class="btn btn-primary">
                                <i class="ki-duotone ki-diagram fs-2"></i>
                                Ver Kanban de Seleção
                            </a>
                            <a href="candidaturas.php?vaga_id=<?= $vaga_id ?>" class="btn btn-light-primary">
                                <i class="ki-duotone ki-profile-user fs-2"></i>
                                Ver Todas as Candidaturas
                            </a>
                            <?php if ($vaga['usar_landing_page_customizada']): ?>
                            <a href="vaga_landing_page.php?id=<?= $vaga_id ?>" class="btn btn-success">
                                <i class="ki-duotone ki-notepad-edit fs-2"></i>
                                Editar Landing Page
                            </a>
                            <?php endif; ?>
                            <a href="../vaga_publica.php?id=<?= $vaga_id ?>" target="_blank" class="btn btn-info">
                                <i class="ki-duotone ki-eye fs-2"></i>
                                Ver Portal Público
                            </a>
                            <a href="analytics_recrutamento.php?vaga_id=<?= $vaga_id ?>" class="btn btn-warning">
                                <i class="ki-duotone ki-chart-simple fs-2"></i>
                                Analytics Completo
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Estilos para HTML gerado pela IA -->
<style>
.vaga-html-content ul,
.vaga-html-content ol {
    margin: 1rem 0;
    padding-left: 2rem;
}

.vaga-html-content li {
    margin-bottom: 0.75rem;
    line-height: 1.8;
}

.vaga-html-content ul li {
    list-style-type: none;
    position: relative;
    padding-left: 1.5rem;
}

.vaga-html-content ul li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #50cd89;
    font-weight: bold;
    font-size: 1.2rem;
}

.vaga-html-content strong,
.vaga-html-content b {
    color: #2d3748;
    font-weight: 600;
}

.vaga-html-content p {
    margin-bottom: 1rem;
    line-height: 1.8;
}

.vaga-html-content p:last-child {
    margin-bottom: 0;
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Dados para gráficos
const statsData = <?= json_encode($stats) ?>;
const porEtapaData = <?= json_encode($por_etapa) ?>;
const porMesData = <?= json_encode($por_mes) ?>;
const conversaoEtapasData = <?= json_encode($conversao_etapas) ?>;

// Gráfico: Candidaturas por Status
const ctxStatus = document.getElementById('chartStatus');
if (ctxStatus) {
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['Aprovadas', 'Rejeitadas', 'Pendentes', 'Em Andamento', 'Canceladas'],
            datasets: [{
                data: [
                    statsData.aprovadas || 0,
                    statsData.rejeitadas || 0,
                    statsData.pendentes || 0,
                    statsData.em_andamento || 0,
                    statsData.canceladas || 0
                ],
                backgroundColor: [
                    'rgb(40, 167, 69)',
                    'rgb(220, 53, 69)',
                    'rgb(255, 193, 7)',
                    'rgb(0, 123, 255)',
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
if (ctxEvolucao && porMesData.length > 0) {
    new Chart(ctxEvolucao, {
        type: 'line',
        data: {
            labels: porMesData.map(item => item.mes_formatado),
            datasets: [{
                label: 'Candidaturas',
                data: porMesData.map(item => item.total),
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
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Gráfico: Distribuição por Etapa
const ctxEtapas = document.getElementById('chartEtapas');
if (ctxEtapas && porEtapaData.length > 0) {
    new Chart(ctxEtapas, {
        type: 'bar',
        data: {
            labels: porEtapaData.map(item => item.etapa_nome),
            datasets: [{
                label: 'Candidaturas',
                data: porEtapaData.map(item => item.total),
                backgroundColor: porEtapaData.map(item => item.cor_kanban || 'rgb(0, 123, 255)'),
                borderColor: porEtapaData.map(item => item.cor_kanban || 'rgb(0, 123, 255)'),
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

// Gráfico: Taxa de Conversão por Etapa
const ctxConversao = document.getElementById('chartConversao');
if (ctxConversao && conversaoEtapasData.length > 0) {
    const conversaoData = conversaoEtapasData.filter(item => item.total_passaram > 0);
    if (conversaoData.length > 0) {
        new Chart(ctxConversao, {
            type: 'bar',
            data: {
                labels: conversaoData.map(item => item.etapa_nome),
                datasets: [{
                    label: 'Taxa de Conversão (%)',
                    data: conversaoData.map(item => {
                        const total = item.total_passaram;
                        const aprovadas = item.aprovadas_etapa || 0;
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
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
