<?php
/**
 * Meu Perfil - Área do Colaborador
 * Visualização de todas as informações do colaborador logado
 */

$page_title = 'Meu Perfil';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';

// Apenas colaboradores podem acessar
if (!is_colaborador()) {
    redirect('dashboard.php', 'Acesso negado. Esta página é apenas para colaboradores.', 'error');
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Pega o ID do colaborador logado
$colaborador_id = $usuario['colaborador_id'] ?? null;

if (!$colaborador_id) {
    redirect('dashboard.php', 'Colaborador não encontrado!', 'error');
}

// Busca colaborador com informações completas
$stmt = $pdo->prepare("
    SELECT c.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           car.nome_cargo,
           nh.nome as nivel_nome,
           nh.codigo as nivel_codigo,
           l.nome_completo as lider_nome,
           l.foto as lider_foto
    FROM colaboradores c
    LEFT JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN setores s ON c.setor_id = s.id
    LEFT JOIN cargos car ON c.cargo_id = car.id
    LEFT JOIN niveis_hierarquicos nh ON c.nivel_hierarquico_id = nh.id
    LEFT JOIN colaboradores l ON c.lider_id = l.id
    WHERE c.id = ?
");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch();

if (!$colaborador) {
    redirect('dashboard.php', 'Colaborador não encontrado!', 'error');
}

// Busca flags ativas do colaborador
$flags_ativas = get_flags_ativas($colaborador_id);
$total_flags_ativas = count($flags_ativas);

// Busca ocorrências do colaborador
$stmt = $pdo->prepare("
    SELECT o.*, u.nome as usuario_nome, tp.nome as tipo_nome
    FROM ocorrencias o
    LEFT JOIN usuarios u ON o.usuario_id = u.id
    LEFT JOIN tipos_ocorrencias tp ON o.tipo_ocorrencia_id = tp.id
    WHERE o.colaborador_id = ?
    ORDER BY o.data_ocorrencia DESC, o.created_at DESC
");
$stmt->execute([$colaborador_id]);
$ocorrencias = $stmt->fetchAll();

// Busca horas extras do colaborador
$stmt = $pdo->prepare("
    SELECT h.*, 
           COALESCE(h.tipo_pagamento, 'dinheiro') as tipo_pagamento,
           u.nome as usuario_nome
    FROM horas_extras h
    LEFT JOIN usuarios u ON h.usuario_id = u.id
    WHERE h.colaborador_id = ?
    ORDER BY h.data_trabalho DESC, h.created_at DESC
");
$stmt->execute([$colaborador_id]);
$horas_extras_colaborador = $stmt->fetchAll();

// Busca bônus do colaborador (ativos ou permanentes)
$stmt = $pdo->prepare("
    SELECT cb.*, tb.nome as tipo_bonus_nome, tb.descricao as tipo_bonus_descricao
    FROM colaboradores_bonus cb
    INNER JOIN tipos_bonus tb ON cb.tipo_bonus_id = tb.id
    WHERE cb.colaborador_id = ?
    AND (
        cb.data_fim IS NULL 
        OR cb.data_fim >= CURDATE()
        OR (cb.data_inicio IS NULL AND cb.data_fim IS NULL)
    )
    ORDER BY tb.nome
");
$stmt->execute([$colaborador_id]);
$bonus_colaborador = $stmt->fetchAll();

// Busca saldo e histórico do banco de horas
require_once __DIR__ . '/../includes/banco_horas_functions.php';
$saldo_banco_horas = get_saldo_banco_horas($colaborador_id);
$historico_banco_horas = get_historico_banco_horas($colaborador_id, [
    'data_inicio' => date('Y-m-01', strtotime('-6 months')), // Últimos 6 meses
    'data_fim' => date('Y-m-t')
]);
$dados_grafico = get_dados_grafico_banco_horas($colaborador_id, 30); // Últimos 30 dias

// Busca estatísticas gerais do LMS
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as total_cursos,
        COUNT(DISTINCT CASE WHEN pc.status = 'concluido' THEN c.id END) as cursos_concluidos,
        COUNT(DISTINCT CASE WHEN pc.status = 'em_andamento' OR (pc.status != 'concluido' AND pc.data_inicio IS NOT NULL) THEN c.id END) as cursos_em_andamento,
        COUNT(DISTINCT cert.id) as total_certificados,
        SUM(pc.tempo_assistido) as tempo_total_assistido_segundos,
        COUNT(DISTINCT pc.aula_id) as total_aulas_concluidas,
        COUNT(DISTINCT a.id) as total_aulas_disponiveis
    FROM cursos c
    LEFT JOIN aulas a ON a.curso_id = c.id AND a.status = 'publicado'
    LEFT JOIN progresso_colaborador pc ON pc.curso_id = c.id AND pc.colaborador_id = ?
    LEFT JOIN certificados cert ON cert.curso_id = c.id AND cert.colaborador_id = ? AND cert.status = 'ativo'
    WHERE c.status = 'publicado'
");
$stmt->execute([$colaborador_id, $colaborador_id]);
$lms_stats = $stmt->fetch();

// Busca cursos com progresso
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.titulo,
        c.imagem_capa,
        cat.nome as categoria_nome,
        cat.cor as categoria_cor,
        COUNT(DISTINCT a.id) as total_aulas,
        COUNT(DISTINCT CASE WHEN pc.status = 'concluido' THEN pc.aula_id END) as aulas_concluidas,
        MAX(pc.data_ultimo_acesso) as ultimo_acesso,
        CASE 
            WHEN COUNT(DISTINCT CASE WHEN pc.status = 'concluido' THEN pc.aula_id END) = COUNT(DISTINCT a.id) AND COUNT(DISTINCT a.id) > 0 THEN 'concluido'
            WHEN COUNT(DISTINCT CASE WHEN pc.status IN ('em_andamento', 'concluido') THEN pc.aula_id END) > 0 THEN 'em_andamento'
            ELSE 'nao_iniciado'
        END as status_curso
    FROM cursos c
    LEFT JOIN categorias_cursos cat ON cat.id = c.categoria_id
    LEFT JOIN aulas a ON a.curso_id = c.id AND a.status = 'publicado'
    LEFT JOIN progresso_colaborador pc ON pc.aula_id = a.id AND pc.colaborador_id = ?
    WHERE c.status = 'publicado'
    GROUP BY c.id, c.titulo, c.imagem_capa, cat.nome, cat.cor
    HAVING status_curso IN ('em_andamento', 'concluido')
    ORDER BY ultimo_acesso DESC
    LIMIT 10
");
$stmt->execute([$colaborador_id]);
$cursos_progresso = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Estilos personalizados para a página do colaborador */
.profile-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
}

.profile-photo {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 5px solid white;
    object-fit: cover;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.stat-card {
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.timeline-item {
    position: relative;
    padding-left: 30px;
    padding-bottom: 20px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: -20px;
    width: 2px;
    background: #e0e0e0;
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-dot {
    position: absolute;
    left: 0;
    top: 5px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 0 0 2px #667eea;
}
</style>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">
                <i class="ki-duotone ki-user fs-2 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Meu Perfil
            </h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Meu Perfil</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!-- Card de Perfil com Destaque -->
        <div class="profile-card">
            <div class="row align-items-center">
                <div class="col-auto">
                    <?php if (!empty($colaborador['foto'])): ?>
                        <img src="../<?= htmlspecialchars($colaborador['foto']) ?>" alt="Foto" class="profile-photo" />
                    <?php else: ?>
                        <div class="profile-photo d-flex align-items-center justify-content-center bg-white text-primary" style="font-size: 48px; font-weight: bold;">
                            <?= strtoupper(substr($colaborador['nome_completo'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col">
                    <h2 class="fw-bold mb-2"><?= htmlspecialchars($colaborador['nome_completo']) ?></h2>
                    <div class="d-flex flex-wrap gap-3">
                        <div>
                            <i class="ki-duotone ki-suitcase fs-4 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <?= htmlspecialchars($colaborador['nome_cargo'] ?? 'Sem cargo') ?>
                        </div>
                        <div>
                            <i class="ki-duotone ki-office-bag fs-4 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <?= htmlspecialchars($colaborador['nome_setor'] ?? 'Sem setor') ?>
                        </div>
                        <?php if (!empty($colaborador['lider_nome'])): ?>
                        <div>
                            <i class="ki-duotone ki-profile-user fs-4 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            Líder: <?= htmlspecialchars($colaborador['lider_nome']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="row g-5 g-xl-8 mb-5">
            <!-- Banco de Horas -->
            <div class="col-xl-3 col-lg-6">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-50px me-3">
                                <span class="symbol-label bg-light-primary">
                                    <i class="ki-duotone ki-time fs-2x text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <span class="text-gray-700 fw-bold d-block fs-7">Banco de Horas</span>
                                <?php 
                                $saldo_total = ($saldo_banco_horas['saldo_horas'] ?? 0) + (($saldo_banco_horas['saldo_minutos'] ?? 0) / 60);
                                $cor_saldo = $saldo_total >= 0 ? 'success' : 'danger';
                                ?>
                                <span class="fw-bold fs-2 text-<?= $cor_saldo ?>">
                                    <?= number_format($saldo_total, 2, ',', '.') ?>h
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Horas Extras -->
            <div class="col-xl-3 col-lg-6">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-50px me-3">
                                <span class="symbol-label bg-light-success">
                                    <i class="ki-duotone ki-chart-simple fs-2x text-success">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                    </i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <span class="text-gray-700 fw-bold d-block fs-7">Horas Extras</span>
                                <span class="fw-bold fs-2 text-gray-900"><?= count($horas_extras_colaborador) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cursos LMS -->
            <div class="col-xl-3 col-lg-6">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-50px me-3">
                                <span class="symbol-label bg-light-info">
                                    <i class="ki-duotone ki-book fs-2x text-info">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                    </i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <span class="text-gray-700 fw-bold d-block fs-7">Cursos Concluídos</span>
                                <span class="fw-bold fs-2 text-gray-900">
                                    <?= (int)($lms_stats['cursos_concluidos'] ?? 0) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bônus Ativos -->
            <div class="col-xl-3 col-lg-6">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-50px me-3">
                                <span class="symbol-label bg-light-warning">
                                    <i class="ki-duotone ki-star fs-2x text-warning">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <span class="text-gray-700 fw-bold d-block fs-7">Bônus Ativos</span>
                                <span class="fw-bold fs-2 text-gray-900"><?= count($bonus_colaborador) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs com Informações Detalhadas -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tab_dados_pessoais">
                            <i class="ki-duotone ki-profile-circle fs-2 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Dados Pessoais
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab_banco_horas">
                            <i class="ki-duotone ki-time fs-2 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Banco de Horas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab_horas_extras">
                            <i class="ki-duotone ki-chart-simple fs-2 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            Horas Extras
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab_ocorrencias">
                            <i class="ki-duotone ki-clipboard fs-2 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Ocorrências
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab_bonus">
                            <i class="ki-duotone ki-star fs-2 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Bônus
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab_cursos">
                            <i class="ki-duotone ki-book fs-2 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            Meus Cursos
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content">
                    
                    <!-- Tab: Dados Pessoais -->
                    <div class="tab-pane fade show active" id="tab_dados_pessoais">
                        <?php require __DIR__ . '/../includes/meu_perfil_tabs/dados_pessoais.php'; ?>
                    </div>

                    <!-- Tab: Banco de Horas -->
                    <div class="tab-pane fade" id="tab_banco_horas">
                        <?php require __DIR__ . '/../includes/meu_perfil_tabs/banco_horas.php'; ?>
                    </div>

                    <!-- Tab: Horas Extras -->
                    <div class="tab-pane fade" id="tab_horas_extras">
                        <?php require __DIR__ . '/../includes/meu_perfil_tabs/horas_extras.php'; ?>
                    </div>

                    <!-- Tab: Ocorrências -->
                    <div class="tab-pane fade" id="tab_ocorrencias">
                        <?php require __DIR__ . '/../includes/meu_perfil_tabs/ocorrencias.php'; ?>
                    </div>

                    <!-- Tab: Bônus -->
                    <div class="tab-pane fade" id="tab_bonus">
                        <?php require __DIR__ . '/../includes/meu_perfil_tabs/bonus.php'; ?>
                    </div>

                    <!-- Tab: Cursos -->
                    <div class="tab-pane fade" id="tab_cursos">
                        <?php require __DIR__ . '/../includes/meu_perfil_tabs/cursos.php'; ?>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
