<?php
/**
 * Visualizar Colaborador - Metronic Theme
 */

$page_title = 'Visualizar Colaborador';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';

require_page_permission('colaborador_view.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$id = $_GET['id'] ?? 0;

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
$stmt->execute([$id]);
$colaborador = $stmt->fetch();

if (!$colaborador) {
    redirect('colaboradores.php', 'Colaborador não encontrado!', 'error');
}

// Verifica permissão
if (!can_access_colaborador($id)) {
    redirect('dashboard.php', 'Você não tem permissão para visualizar este colaborador.', 'error');
}

// Busca flags ativas do colaborador
$flags_ativas = get_flags_ativas($id);
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
$stmt->execute([$id]);
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
$stmt->execute([$id]);
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
$stmt->execute([$id]);
$bonus_colaborador = $stmt->fetchAll();

// Busca tipos de bônus disponíveis
$stmt = $pdo->query("SELECT * FROM tipos_bonus WHERE status = 'ativo' ORDER BY nome");
$tipos_bonus = $stmt->fetchAll();

// Busca saldo e histórico do banco de horas
require_once __DIR__ . '/../includes/banco_horas_functions.php';
$saldo_banco_horas = get_saldo_banco_horas($id);
$historico_banco_horas = get_historico_banco_horas($id, [
    'data_inicio' => date('Y-m-01', strtotime('-6 months')), // Últimos 6 meses
    'data_fim' => date('Y-m-t')
]);
$dados_grafico = get_dados_grafico_banco_horas($id, 30); // Últimos 30 dias

// =====================================================
// DADOS DO LMS - ESCOLA PRIVUS
// =====================================================

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
$stmt->execute([$id, $id]);
$lms_stats = $stmt->fetch();

// Busca cursos com progresso detalhado
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.titulo,
        c.descricao,
        c.imagem_capa,
        c.duracao_estimada,
        cat.nome as categoria_nome,
        cat.cor as categoria_cor,
        COUNT(DISTINCT a.id) as total_aulas,
        COUNT(DISTINCT CASE WHEN pc.status = 'concluido' THEN pc.aula_id END) as aulas_concluidas,
        SUM(pc.tempo_assistido) as tempo_assistido_segundos,
        MAX(pc.data_ultimo_acesso) as ultimo_acesso,
        MAX(pc.data_conclusao) as data_conclusao_curso,
        CASE 
            WHEN COUNT(DISTINCT CASE WHEN pc.status = 'concluido' THEN pc.aula_id END) = COUNT(DISTINCT a.id) AND COUNT(DISTINCT a.id) > 0 THEN 'concluido'
            WHEN COUNT(DISTINCT CASE WHEN pc.status IN ('em_andamento', 'concluido') THEN pc.aula_id END) > 0 THEN 'em_andamento'
            ELSE 'nao_iniciado'
        END as status_curso
    FROM cursos c
    LEFT JOIN categorias_cursos cat ON cat.id = c.categoria_id
    LEFT JOIN aulas a ON a.curso_id = c.id AND a.status = 'publicado'
    LEFT JOIN progresso_colaborador pc ON pc.curso_id = c.id AND pc.colaborador_id = ?
    WHERE c.status = 'publicado'
    GROUP BY c.id
    HAVING total_aulas > 0
    ORDER BY ultimo_acesso DESC, c.titulo ASC
");
$stmt->execute([$id]);
$lms_cursos = $stmt->fetchAll();

// Busca certificados
$stmt = $pdo->prepare("
    SELECT 
        cert.*,
        c.titulo as curso_titulo,
        c.imagem_capa
    FROM certificados cert
    INNER JOIN cursos c ON c.id = cert.curso_id
    WHERE cert.colaborador_id = ? AND cert.status = 'ativo'
    ORDER BY cert.data_emissao DESC
");
$stmt->execute([$id]);
$lms_certificados = $stmt->fetchAll();

// Busca badges/conquistas
$stmt = $pdo->prepare("
    SELECT 
        cb.*,
        b.nome as badge_nome,
        b.descricao as badge_descricao,
        b.icone as badge_icone,
        b.cor as badge_cor
    FROM colaborador_badges cb
    INNER JOIN badges_conquistas b ON b.id = cb.badge_id
    WHERE cb.colaborador_id = ?
    ORDER BY cb.data_conquista DESC
");
$stmt->execute([$id]);
$lms_badges = $stmt->fetchAll();

// Busca cursos obrigatórios
$stmt = $pdo->prepare("
    SELECT 
        coc.*,
        c.titulo as curso_titulo,
        c.imagem_capa,
        c.duracao_estimada
    FROM cursos_obrigatorios_colaboradores coc
    INNER JOIN cursos c ON c.id = coc.curso_id
    WHERE coc.colaborador_id = ?
    ORDER BY coc.data_limite ASC, coc.status ASC
");
$stmt->execute([$id]);
$lms_cursos_obrigatorios = $stmt->fetchAll();

// Busca evolução de progresso (últimos 30 dias)
$stmt = $pdo->prepare("
    SELECT 
        DATE(pc.data_conclusao) as data,
        COUNT(DISTINCT pc.aula_id) as aulas_concluidas
    FROM progresso_colaborador pc
    WHERE pc.colaborador_id = ? 
    AND pc.status = 'concluido'
    AND pc.data_conclusao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(pc.data_conclusao)
    ORDER BY data ASC
");
$stmt->execute([$id]);
$lms_evolucao = $stmt->fetchAll();

// Busca manuais individuais do colaborador
$stmt = $pdo->prepare("
    SELECT m.*, u.nome as criado_por_nome
    FROM manuais_individuais m
    INNER JOIN manuais_individuais_colaboradores mc ON m.id = mc.manual_id
    LEFT JOIN usuarios u ON m.created_by = u.id
    WHERE mc.colaborador_id = ? AND m.status = 'ativo'
    ORDER BY m.created_at DESC
");
$stmt->execute([$id]);
$manuais_individuais = $stmt->fetchAll();

// Busca data de desligamento (se houver)
$data_desligamento = null;
$tipo_demissao = null;
$motivo_demissao = null;
if ($colaborador['status'] === 'desligado') {
    $stmt = $pdo->prepare("
        SELECT data_demissao, tipo_demissao, motivo 
        FROM demissoes 
        WHERE colaborador_id = ? 
        ORDER BY data_demissao DESC, created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $demissao = $stmt->fetch();
    if ($demissao) {
        $data_desligamento = $demissao['data_demissao'];
        $tipo_demissao = $demissao['tipo_demissao'];
        $motivo_demissao = $demissao['motivo'];
    }
}

// Calcula percentual geral de conclusão
$lms_percentual_geral = 0;
if ($lms_stats['total_aulas_disponiveis'] > 0) {
    $lms_percentual_geral = round(($lms_stats['total_aulas_concluidas'] / $lms_stats['total_aulas_disponiveis']) * 100, 1);
}

// Formata tempo total assistido
$lms_tempo_total_horas = round($lms_stats['tempo_total_assistido_segundos'] / 3600, 1);

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-3 me-lg-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 fs-md-4 mb-0"><?= htmlspecialchars($colaborador['nome_completo']) ?></h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1 d-none d-md-flex">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="colaboradores.php" class="text-muted text-hover-primary">Colaboradores</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Visualizar</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2 flex-wrap gap-2">
            <?php if ($usuario['role'] !== 'COLABORADOR' && $usuario['role'] !== 'GESTOR'): ?>
                <a href="colaborador_edit.php?id=<?= $id ?>" class="btn btn-sm btn-warning">
                    <i class="ki-duotone ki-pencil fs-2 d-none d-md-inline">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <span class="d-md-none">Editar</span>
                    <span class="d-none d-md-inline">Editar</span>
                </a>
                <?php if (!empty($colaborador['email_pessoal'])): ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="enviarDadosAcesso(<?= $id ?>)">
                    <i class="ki-duotone ki-send fs-2 d-none d-md-inline">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <span class="d-md-none">Enviar Acesso</span>
                    <span class="d-none d-md-inline">Enviar Dados de Acesso</span>
                </button>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (in_array($usuario['role'], ['ADMIN', 'RH', 'GESTOR']) && $colaborador['status'] === 'ativo'): ?>
                <button type="button" class="btn btn-sm btn-info" onclick="logarComoColaborador(<?= $id ?>, '<?= htmlspecialchars($colaborador['nome_completo'], ENT_QUOTES) ?>')">
                    <i class="ki-duotone ki-user fs-2 d-none d-md-inline">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <span class="d-md-none">Logar como</span>
                    <span class="d-none d-md-inline">Logar como Colaborador</span>
                </button>
            <?php endif; ?>
            <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                <a href="colaboradores.php" class="btn btn-sm btn-light">
                    <i class="ki-duotone ki-arrow-left fs-2 d-none d-md-inline">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <span class="d-md-none">Voltar</span>
                    <span class="d-none d-md-inline">Voltar</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?= get_session_alert() ?>
        
        <!--begin::Card-->
        <div class="card">
            <!--begin::Card header-->
            <div class="card-header border-0 pt-6">
                <!--begin::Card title-->
                <div class="card-title w-100">
                    <!--begin::Tabs-->
                    <div style="overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch; width: 100%;">
                        <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold flex-nowrap" style="white-space: nowrap; min-width: max-content;">
                        <li class="nav-item mt-2 flex-shrink-0">
                            <a class="nav-link text-active-primary ms-0 me-5 me-md-10 py-5 active" data-bs-toggle="tab" href="#kt_tab_pane_jornada">
                                <i class="ki-duotone ki-chart-simple fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <span class="d-none d-md-inline">Jornada</span>
                                <span class="d-md-none">Jornada</span>
                            </a>
                        </li>
                        <li class="nav-item mt-2 flex-shrink-0">
                            <a class="nav-link text-active-primary me-5 me-md-10 py-5" data-bs-toggle="tab" href="#kt_tab_pane_dados">
                                <i class="ki-duotone ki-profile-user fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                <span class="d-none d-md-inline">Informações Pessoais</span>
                                <span class="d-md-none">Pessoais</span>
                            </a>
                        </li>
                        <li class="nav-item mt-2 flex-shrink-0">
                            <a class="nav-link text-active-primary me-5 me-md-10 py-5" data-bs-toggle="tab" href="#kt_tab_pane_profissional">
                                <i class="ki-duotone ki-briefcase fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span class="d-none d-md-inline">Informações Profissionais</span>
                                <span class="d-md-none">Profissionais</span>
                            </a>
                        </li>
                        <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                        <li class="nav-item mt-2 flex-shrink-0">
                            <a class="nav-link text-active-primary me-5 me-md-10 py-5" data-bs-toggle="tab" href="#kt_tab_pane_bonus">
                                <i class="ki-duotone ki-wallet fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span class="d-none d-md-inline">Bônus/Pagamentos</span>
                                <span class="d-md-none">Bônus</span>
                                <?php if (count($bonus_colaborador) > 0): ?>
                                <span class="badge badge-circle badge-success ms-2"><?= count($bonus_colaborador) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item mt-2 flex-shrink-0">
                            <a class="nav-link text-active-primary me-5 me-md-10 py-5" data-bs-toggle="tab" href="#kt_tab_pane_horas_extras">
                                <i class="ki-duotone ki-calendar fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span class="d-none d-md-inline">Horas Extras</span>
                                <span class="d-md-none">Horas Extras</span>
                                <?php if (count($horas_extras_colaborador) > 0): ?>
                                <span class="badge badge-circle badge-info ms-2"><?= count($horas_extras_colaborador) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item mt-2 flex-shrink-0">
                            <a class="nav-link text-active-primary me-5 me-md-10 py-5" data-bs-toggle="tab" href="#kt_tab_pane_banco_horas">
                                <i class="ki-duotone ki-time fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span class="d-none d-md-inline">Banco de Horas</span>
                                <span class="d-md-none">Banco Horas</span>
                            </a>
                        </li>
                        <li class="nav-item mt-2 flex-shrink-0">
                            <a class="nav-link text-active-primary py-5" data-bs-toggle="tab" href="#kt_tab_pane_ocorrencias">
                                <i class="ki-duotone ki-clipboard fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span class="d-none d-md-inline">Ocorrências</span>
                                <span class="d-md-none">Ocorrências</span>
                                <?php if (count($ocorrencias) > 0): ?>
                                <span class="badge badge-circle badge-danger ms-2"><?= count($ocorrencias) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item mt-2 flex-shrink-0">
                            <a class="nav-link text-active-primary py-5" data-bs-toggle="tab" href="#kt_tab_pane_manuais">
                                <i class="ki-duotone ki-book fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span class="d-none d-md-inline">Manuais Individuais</span>
                                <span class="d-md-none">Manuais</span>
                                <?php if (count($manuais_individuais) > 0): ?>
                                <span class="badge badge-circle badge-primary ms-2"><?= count($manuais_individuais) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item mt-2 flex-shrink-0">
                            <a class="nav-link text-active-primary py-5" data-bs-toggle="tab" href="#kt_tab_pane_escola_privus">
                                <i class="ki-duotone ki-book fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                <span class="d-none d-md-inline">Escola Privus</span>
                                <span class="d-md-none">LMS</span>
                            </a>
                        </li>
                    </ul>
                    </div>
                    <!--end::Tabs-->
                </div>
                <!--begin::Card title-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Tab Content-->
                <div class="tab-content">
                    <!--begin::Tab Pane - Jornada-->
                    <div class="tab-pane fade show active" id="kt_tab_pane_jornada" role="tabpanel">
                        <!-- Cabeçalho rico com foto e informações -->
                        <div class="card mb-5">
                            <div class="card-body p-6">
                                <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-5">
                                    <!-- Foto do colaborador -->
                                    <div class="flex-shrink-0">
                                        <?php if ($colaborador['foto']): ?>
                                        <img src="../<?= htmlspecialchars($colaborador['foto']) ?>" class="rounded-circle" width="120" height="120" style="object-fit: cover;" alt="<?= htmlspecialchars($colaborador['nome_completo']) ?>">
                                        <?php else: ?>
                                        <div class="symbol symbol-circle symbol-120px">
                                            <div class="symbol-label bg-primary text-white fs-2x fw-bold">
                                                <?= strtoupper(substr($colaborador['nome_completo'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Informações principais -->
                                    <div class="flex-grow-1">
                                        <h2 class="fw-bold text-gray-900 mb-2"><?= htmlspecialchars($colaborador['nome_completo']) ?></h2>
                                        <h5 class="text-gray-600 mb-1"><?= htmlspecialchars($colaborador['nome_cargo']) ?></h5>
                                        <p class="text-gray-500 mb-2"><?= htmlspecialchars($colaborador['nome_setor']) ?></p>
                                        <?php if ($colaborador['empresa_nome']): ?>
                                        <p class="text-gray-500 mb-3"><?= htmlspecialchars($colaborador['empresa_nome']) ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            <span class="badge badge-light-<?= $colaborador['status'] === 'ativo' ? 'success' : ($colaborador['status'] === 'pausado' ? 'warning' : 'secondary') ?>">
                                                <?= ucfirst($colaborador['status']) ?>
                                            </span>
                                            <?php if ($total_flags_ativas > 0): ?>
                                            <a href="flags_view.php?colaborador_id=<?= $id ?>&status=ativa" class="badge badge-<?= $total_flags_ativas >= 3 ? 'danger' : ($total_flags_ativas >= 2 ? 'warning' : 'info') ?>">
                                                🚩 <?= $total_flags_ativas ?> Flag<?= $total_flags_ativas > 1 ? 's' : '' ?> Ativa<?= $total_flags_ativas > 1 ? 's' : '' ?>
                                                <?php if ($total_flags_ativas >= 3): ?>
                                                <span class="ms-1">⚠️</span>
                                                <?php endif; ?>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Liderança -->
                                        <?php if ($colaborador['lider_nome']): ?>
                                        <div class="d-flex align-items-center gap-3 mt-4">
                                            <label class="fw-semibold text-gray-700">Liderança:</label>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if ($colaborador['lider_foto']): ?>
                                                <img src="../<?= htmlspecialchars($colaborador['lider_foto']) ?>" class="rounded-circle" width="30" height="30" style="object-fit: cover;" alt="">
                                                <?php else: ?>
                                                <div class="symbol symbol-circle symbol-30px">
                                                    <div class="symbol-label bg-info text-white fs-7">
                                                        <?= strtoupper(substr($colaborador['lider_nome'], 0, 1)) ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <div>
                                                    <p class="mb-0 fw-semibold text-gray-800"><?= htmlspecialchars($colaborador['lider_nome']) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filtros de data -->
                        <div class="card mb-5">
                            <div class="card-body">
                                <div class="row align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Data de Início</label>
                                        <input type="date" class="form-control form-control-solid" id="filtro-data-inicio" value="">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Data Final</label>
                                        <input type="date" class="form-control form-control-solid" id="filtro-data-fim" value="">
                                    </div>
                                    <div class="col-md-6 d-flex gap-2 justify-content-end">
                                        <button type="button" class="btn btn-light" id="btn-limpar-filtros">Limpar filtros</button>
                                        <button type="button" class="btn btn-primary" id="btn-filtrar">Filtrar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Métricas e gráficos -->
                        <div id="metricas-container">
                            <!-- Será carregado via AJAX -->
                            <div class="text-center py-10">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Tab Pane - Jornada-->
                    
                    <!--begin::Tab Pane - Dados Pessoais-->
                    <div class="tab-pane fade" id="kt_tab_pane_dados" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-6 mb-7">
                                <div class="card card-flush h-xl-100">
                                    <div class="card-header pt-7">
                                        <h3 class="card-title align-items-start flex-column">
                                            <span class="card-label fw-bold text-gray-800">Informações Pessoais</span>
                                        </h3>
                                    </div>
                                    <div class="card-body pt-6">
                                        <div class="d-flex flex-column gap-7 gap-lg-10">
                                            <div class="d-flex flex-wrap gap-5">
                                                <div class="flex-row-fluid">
                                                    <div class="table-responsive">
                                                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                                            <tbody>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold min-w-150px">Nome Completo</th>
                                                                    <td class="text-gray-800 fw-semibold"><?= htmlspecialchars($colaborador['nome_completo']) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">CPF</th>
                                                                    <td class="text-gray-800"><?= formatar_cpf($colaborador['cpf']) ?></td>
                                                                </tr>
                                                                <?php if ($colaborador['tipo_contrato'] === 'PJ' && !empty($colaborador['cnpj'])): ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">CNPJ</th>
                                                                    <td class="text-gray-800"><?= formatar_cnpj($colaborador['cnpj']) ?></td>
                                                                </tr>
                                                                <?php endif; ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">RG</th>
                                                                    <td class="text-gray-800"><?= $colaborador['rg'] ? htmlspecialchars($colaborador['rg']) : '-' ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Data de Nascimento</th>
                                                                    <td class="text-gray-800"><?= formatar_data($colaborador['data_nascimento']) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Telefone</th>
                                                                    <td class="text-gray-800"><?= formatar_telefone($colaborador['telefone']) ?: '-' ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Email Pessoal</th>
                                                                    <td class="text-gray-800"><?= $colaborador['email_pessoal'] ? htmlspecialchars($colaborador['email_pessoal']) : '-' ?></td>
                                                                </tr>
                                                                <?php if ($colaborador['status'] === 'desligado' && $data_desligamento): ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Data de Desligamento</th>
                                                                    <td class="text-gray-800">
                                                                        <span class="badge badge-light-danger me-2"><?= formatar_data($data_desligamento) ?></span>
                                                                        <?php 
                                                                        $tipos_demissao = [
                                                                            'sem_justa_causa' => 'Sem Justa Causa',
                                                                            'justa_causa' => 'Justa Causa',
                                                                            'pedido_demissao' => 'Pedido de Demissão',
                                                                            'aposentadoria' => 'Aposentadoria',
                                                                            'falecimento' => 'Falecimento',
                                                                            'outro' => 'Outro'
                                                                        ];
                                                                        if ($tipo_demissao && isset($tipos_demissao[$tipo_demissao])): 
                                                                        ?>
                                                                        <span class="text-muted fs-7">(<?= $tipos_demissao[$tipo_demissao] ?>)</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                                <?php if ($motivo_demissao): ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Motivo do Desligamento</th>
                                                                    <td class="text-gray-800"><?= htmlspecialchars($motivo_demissao) ?></td>
                                                                </tr>
                                                                <?php endif; ?>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-6 mb-7">
                                <div class="card card-flush h-xl-100">
                                    <div class="card-header pt-7">
                                        <h3 class="card-title align-items-start flex-column">
                                            <span class="card-label fw-bold text-gray-800">Informações Profissionais</span>
                                        </h3>
                                    </div>
                                    <div class="card-body pt-6">
                                        <div class="d-flex flex-column gap-7 gap-lg-10">
                                            <div class="d-flex flex-wrap gap-5">
                                                <div class="flex-row-fluid">
                                                    <div class="table-responsive">
                                                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                                            <tbody>
                                                                <?php if ($usuario['role'] === 'ADMIN'): ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold min-w-150px">Empresa</th>
                                                                    <td class="text-gray-800 fw-semibold"><?= htmlspecialchars($colaborador['empresa_nome']) ?></td>
                                                                </tr>
                                                                <?php endif; ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Setor</th>
                                                                    <td class="text-gray-800"><?= htmlspecialchars($colaborador['nome_setor']) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Cargo</th>
                                                                    <td class="text-gray-800"><?= htmlspecialchars($colaborador['nome_cargo']) ?></td>
                                                                </tr>
                                                                <?php if (!empty($colaborador['nivel_nome'])): ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Nível Hierárquico</th>
                                                                    <td class="text-gray-800"><?= htmlspecialchars($colaborador['nivel_nome']) ?> (<?= htmlspecialchars($colaborador['nivel_codigo']) ?>)</td>
                                                                </tr>
                                                                <?php endif; ?>
                                                                <?php if (!empty($colaborador['lider_nome'])): ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Líder</th>
                                                                    <td class="text-gray-800"><?= htmlspecialchars($colaborador['lider_nome']) ?></td>
                                                                </tr>
                                                                <?php endif; ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Data de Início</th>
                                                                    <td class="text-gray-800"><?= formatar_data($colaborador['data_inicio']) ?></td>
                                                                </tr>
                                                                <?php if ($colaborador['status'] === 'desligado' && $data_desligamento): ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Data de Desligamento</th>
                                                                    <td class="text-gray-800">
                                                                        <span class="badge badge-light-danger me-2"><?= formatar_data($data_desligamento) ?></span>
                                                                        <?php 
                                                                        $tipos_demissao = [
                                                                            'sem_justa_causa' => 'Sem Justa Causa',
                                                                            'justa_causa' => 'Justa Causa',
                                                                            'pedido_demissao' => 'Pedido de Demissão',
                                                                            'aposentadoria' => 'Aposentadoria',
                                                                            'falecimento' => 'Falecimento',
                                                                            'outro' => 'Outro'
                                                                        ];
                                                                        if ($tipo_demissao && isset($tipos_demissao[$tipo_demissao])): 
                                                                        ?>
                                                                        <span class="text-muted fs-7">(<?= $tipos_demissao[$tipo_demissao] ?>)</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                                <?php endif; ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Tipo de Contrato</th>
                                                                    <td class="text-gray-800"><?= htmlspecialchars($colaborador['tipo_contrato']) ?></td>
                                                                </tr>
                                                                <?php if (!empty($colaborador['salario'])): ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Salário</th>
                                                                    <td class="text-gray-800 fw-bold text-success">R$ <?= number_format($colaborador['salario'], 2, ',', '.') ?></td>
                                                                </tr>
                                                                <?php endif; ?>
                                                                <tr>
                                                                    <th class="text-gray-600 fw-semibold">Status</th>
                                                                    <td>
                                                                        <?php if ($colaborador['status'] === 'ativo'): ?>
                                                                            <span class="badge badge-light-success">Ativo</span>
                                                                        <?php elseif ($colaborador['status'] === 'pausado'): ?>
                                                                            <span class="badge badge-light-warning">Pausado</span>
                                                                        <?php else: ?>
                                                                            <span class="badge badge-light-secondary">Desligado</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($colaborador['observacoes'])): ?>
                        <div class="card card-flush mb-7">
                            <div class="card-header pt-7">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label fw-bold text-gray-800">Observações</span>
                                </h3>
                            </div>
                            <div class="card-body pt-6">
                                <p class="text-gray-800"><?= nl2br(htmlspecialchars($colaborador['observacoes'])) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!--end::Tab Pane - Dados Pessoais-->
                    
                    <!--begin::Tab Pane - Informações Profissionais-->
                    <div class="tab-pane fade" id="kt_tab_pane_profissional" role="tabpanel">
                        <div class="row">
                            <?php if (!empty($colaborador['salario']) || !empty($colaborador['pix']) || !empty($colaborador['banco'])): ?>
                            <div class="col-lg-12 mb-7">
                                <div class="card card-flush">
                                    <div class="card-header pt-7">
                                        <h3 class="card-title align-items-start flex-column">
                                            <span class="card-label fw-bold text-gray-800">Dados Bancários e Financeiros</span>
                                        </h3>
                                    </div>
                                    <div class="card-body pt-6">
                                        <div class="table-responsive">
                                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                                <tbody>
                                                <?php if (!empty($colaborador['salario'])): ?>
                                                <tr>
                                                    <th class="text-gray-600 fw-semibold min-w-200px">Salário</th>
                                                    <td class="text-gray-800 fw-bold text-success fs-4">R$ <?= number_format($colaborador['salario'], 2, ',', '.') ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php if (!empty($colaborador['pix'])): ?>
                                                <tr>
                                                    <th class="text-gray-600 fw-semibold">PIX</th>
                                                    <td class="text-gray-800"><?= htmlspecialchars($colaborador['pix']) ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php if (!empty($colaborador['banco'])): ?>
                                                <tr>
                                                    <th class="text-gray-600 fw-semibold">Banco</th>
                                                    <td class="text-gray-800"><?= htmlspecialchars($colaborador['banco']) ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php if (!empty($colaborador['agencia'])): ?>
                                                <tr>
                                                    <th class="text-gray-600 fw-semibold">Agência</th>
                                                    <td class="text-gray-800"><?= htmlspecialchars($colaborador['agencia']) ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php if (!empty($colaborador['conta'])): ?>
                                                <tr>
                                                    <th class="text-gray-600 fw-semibold">Conta</th>
                                                    <td class="text-gray-800"><?= htmlspecialchars($colaborador['conta']) ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php if (!empty($colaborador['tipo_conta'])): ?>
                                                <tr>
                                                    <th class="text-gray-600 fw-semibold">Tipo de Conta</th>
                                                    <td class="text-gray-800"><?= ucfirst($colaborador['tipo_conta']) ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="col-lg-12">
                                <div class="alert alert-info d-flex align-items-center p-5">
                                    <i class="ki-duotone ki-information fs-2hx text-info me-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <div class="d-flex flex-column">
                                        <h4 class="mb-1 text-info">Sem informações financeiras</h4>
                                        <span>Nenhuma informação bancária ou salarial cadastrada para este colaborador.</span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!--end::Tab Pane - Informações Profissionais-->
                    
                    <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                    <!--begin::Tab Pane - Bônus/Pagamentos-->
                    <div class="tab-pane fade" id="kt_tab_pane_bonus" role="tabpanel">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-7 gap-3">
                            <h3 class="fw-bold text-gray-800 mb-0">Bônus e Pagamentos do Colaborador</h3>
                            <button type="button" class="btn btn-primary w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#kt_modal_bonus" onclick="novoBonus()">
                                <i class="ki-duotone ki-plus fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Adicionar Bônus
                            </button>
                        </div>
                        
                        <?php if (empty($bonus_colaborador)): ?>
                            <div class="alert alert-info d-flex align-items-center p-5">
                                <i class="ki-duotone ki-information fs-2hx text-info me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <div class="d-flex flex-column">
                                    <h4 class="mb-1 text-info">Nenhum bônus cadastrado</h4>
                                    <span>Nenhum bônus ou pagamento adicional cadastrado para este colaborador.</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="card-body pt-0">
                                    <div class="table-responsive">
                                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                                            <thead>
                                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                                    <th class="min-w-150px">Tipo de Bônus</th>
                                                    <th class="min-w-100px">Valor</th>
                                                    <th class="min-w-100px">Data Início</th>
                                                    <th class="min-w-100px">Data Fim</th>
                                                    <th class="min-w-200px">Observações</th>
                                                    <th class="text-end min-w-100px">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-gray-600 fw-semibold">
                                                <?php foreach ($bonus_colaborador as $bonus): ?>
                                                <tr>
                                                    <td>
                                                        <span class="fw-bold"><?= htmlspecialchars($bonus['tipo_bonus_nome']) ?></span>
                                                        <?php if (!empty($bonus['tipo_bonus_descricao'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($bonus['tipo_bonus_descricao']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="fw-bold text-success"><?= formatar_moeda($bonus['valor']) ?></td>
                                                    <td><?= $bonus['data_inicio'] ? formatar_data($bonus['data_inicio']) : '-' ?></td>
                                                    <td><?= $bonus['data_fim'] ? formatar_data($bonus['data_fim']) : 'Sem data fim' ?></td>
                                                    <td><?= $bonus['observacoes'] ? htmlspecialchars($bonus['observacoes']) : '-' ?></td>
                                                    <td class="text-end">
                                                        <button type="button" class="btn btn-sm btn-light-warning me-2" onclick="editarBonus(<?= htmlspecialchars(json_encode($bonus)) ?>)">
                                                            <i class="ki-duotone ki-pencil fs-5">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                            </i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-light-danger" onclick="deletarBonus(<?= $bonus['id'] ?>, '<?= htmlspecialchars($bonus['tipo_bonus_nome']) ?>')">
                                                            <i class="ki-duotone ki-trash fs-5">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                                <span class="path3"></span>
                                                            </i>
                                                        </button>
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
                    <!--end::Tab Pane - Bônus/Pagamentos-->
                    <?php endif; ?>
                    
                    <!--begin::Tab Pane - Horas Extras-->
                    <div class="tab-pane fade" id="kt_tab_pane_horas_extras" role="tabpanel">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-7 gap-3">
                            <h3 class="fw-bold text-gray-800 mb-0">Horas Extras do Colaborador</h3>
                            <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                                <a href="horas_extras.php" class="btn btn-primary w-100 w-md-auto">
                                    <i class="ki-duotone ki-plus fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Nova Hora Extra
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (empty($horas_extras_colaborador)): ?>
                            <div class="alert alert-info d-flex align-items-center p-5">
                                <i class="ki-duotone ki-information fs-2hx text-info me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <div class="d-flex flex-column">
                                    <h4 class="mb-1 text-info">Nenhuma hora extra</h4>
                                    <span>Nenhuma hora extra registrada para este colaborador.</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="card-body pt-0">
                                    <div class="table-responsive">
                                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                                            <thead>
                                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                                    <th class="min-w-100px">Data</th>
                                                    <th class="min-w-100px">Quantidade</th>
                                                    <th class="min-w-100px">Tipo</th>
                                                    <th class="min-w-120px">Valor Hora</th>
                                                    <th class="min-w-100px">% Adicional</th>
                                                    <th class="min-w-120px">Valor Total</th>
                                                    <th class="min-w-200px">Observações</th>
                                                    <th class="min-w-150px">Registrado por</th>
                                                    <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                                                    <th class="text-end min-w-100px">Ações</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody class="fw-semibold text-gray-600">
                                                <?php foreach ($horas_extras_colaborador as $he): 
                                                    $is_remocao = ($he['quantidade_horas'] < 0);
                                                    $tipo_pagamento = $he['tipo_pagamento'] ?? 'dinheiro';
                                                ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($he['data_trabalho'])) ?></td>
                                                    <td>
                                                        <?php if ($is_remocao): ?>
                                                            <span class="text-danger">-<?= number_format(abs($he['quantidade_horas']), 2, ',', '.') ?>h</span>
                                                        <?php else: ?>
                                                            <?= number_format($he['quantidade_horas'], 2, ',', '.') ?>h
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($is_remocao): ?>
                                                            <span class="badge badge-light-warning">Remoção Banco</span>
                                                        <?php elseif ($tipo_pagamento === 'banco_horas'): ?>
                                                            <span class="badge badge-info">Banco de Horas</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-success">R$</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($tipo_pagamento === 'banco_horas'): ?>
                                                            <span class="text-muted">-</span>
                                                        <?php else: ?>
                                                            R$ <?= number_format($he['valor_hora'], 2, ',', '.') ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($tipo_pagamento === 'banco_horas'): ?>
                                                            <span class="text-muted">-</span>
                                                        <?php else: ?>
                                                            <?= number_format($he['percentual_adicional'], 2, ',', '.') ?>%
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($is_remocao): ?>
                                                            <span class="text-gray-600">-</span>
                                                        <?php elseif ($tipo_pagamento === 'banco_horas'): ?>
                                                            <span class="text-muted">-</span>
                                                        <?php else: ?>
                                                            <span class="text-success fw-bold">R$ <?= number_format($he['valor_total'], 2, ',', '.') ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($he['observacoes'])): ?>
                                                            <div class="text-gray-800" title="<?= htmlspecialchars($he['observacoes']) ?>">
                                                                <?= htmlspecialchars(mb_substr($he['observacoes'], 0, 50)) ?><?= mb_strlen($he['observacoes']) > 50 ? '...' : '' ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($he['usuario_nome'] ?? 'Sistema') ?></td>
                                                    <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                                                    <td class="text-end">
                                                        <button type="button" class="btn btn-sm btn-light-danger" onclick="deletarHoraExtra(<?= $he['id'] ?>)">
                                                            <i class="ki-duotone ki-trash fs-5">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                                <span class="path3"></span>
                                                            </i>
                                                        </button>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!--end::Tab Pane - Horas Extras-->
                    
                    <!--begin::Tab Pane - Banco de Horas-->
                    <div class="tab-pane fade" id="kt_tab_pane_banco_horas" role="tabpanel">
                        <div class="row mb-7">
                            <div class="col-md-12">
                                <!-- Card de Saldo Atual -->
                                <div class="card card-flush mb-5">
                                    <div class="card-header border-0 pt-6">
                                        <h3 class="card-title align-items-start flex-column">
                                            <span class="card-label fw-bold fs-3 mb-1">Saldo Atual</span>
                                            <span class="text-muted fw-semibold fs-7">Saldo de horas disponível no banco</span>
                                        </h3>
                                    </div>
                                    <div class="card-body pt-0">
                                        <div class="d-flex align-items-center justify-content-center py-10">
                                            <div class="text-center">
                                                <div class="mb-5">
                                                    <?php 
                                                    $saldo_total = $saldo_banco_horas['saldo_total_horas'];
                                                    $cor_saldo = $saldo_total >= 0 ? 'success' : 'danger';
                                                    $icone_saldo = $saldo_total >= 0 ? 'arrow-up' : 'arrow-down';
                                                    ?>
                                                    <div class="symbol symbol-circle symbol-100px mb-5">
                                                        <div class="symbol-label bg-light-<?= $cor_saldo ?>">
                                                            <i class="ki-duotone ki-<?= $icone_saldo ?> fs-2x text-<?= $cor_saldo ?>">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                            </i>
                                                        </div>
                                                    </div>
                                                    <h1 class="fw-bold text-gray-900 mb-2">
                                                        <span class="text-<?= $cor_saldo ?>">
                                                            <?= number_format($saldo_total, 2, ',', '.') ?>
                                                        </span>
                                                        <span class="fs-3 text-gray-600"> horas</span>
                                                    </h1>
                                                    <p class="text-gray-500 mb-0">
                                                        Última atualização: <?= date('d/m/Y H:i', strtotime($saldo_banco_horas['ultima_atualizacao'])) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Gráfico de Evolução -->
                                <div class="card mb-5">
                                    <div class="card-header border-0 pt-6">
                                        <h3 class="card-title align-items-start flex-column">
                                            <span class="card-label fw-bold fs-3 mb-1">Evolução do Saldo</span>
                                            <span class="text-muted fw-semibold fs-7">Últimos 30 dias</span>
                                        </h3>
                                    </div>
                                    <div class="card-body pt-0">
                                        <canvas id="grafico_banco_horas" style="height: 300px;"></canvas>
                                    </div>
                                </div>
                                
                                <!-- Histórico de Movimentações -->
                                <div class="card">
                                    <div class="card-header border-0 pt-6">
                                        <h3 class="card-title align-items-start flex-column">
                                            <span class="card-label fw-bold fs-3 mb-1">Histórico de Movimentações</span>
                                            <span class="text-muted fw-semibold fs-7">Todas as movimentações do banco de horas</span>
                                        </h3>
                                        <div class="card-toolbar">
                                            <div class="d-flex gap-2">
                                                <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        onclick="recalcularSaldoBancoHoras(<?= $id ?>)"
                                                        title="Recalcular saldo baseado no histórico">
                                                    <i class="ki-duotone ki-calculator fs-3">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    Recalcular Saldo
                                                </button>
                                                <?php endif; ?>
                                                <select id="filtro_tipo_historico" class="form-select form-select-solid w-150px">
                                                    <option value="">Todos os tipos</option>
                                                    <option value="credito">Créditos</option>
                                                    <option value="debito">Débitos</option>
                                                </select>
                                                <select id="filtro_origem_historico" class="form-select form-select-solid w-150px">
                                                    <option value="">Todas as origens</option>
                                                    <option value="hora_extra">Horas Extras</option>
                                                    <option value="ocorrencia">Ocorrências</option>
                                                    <option value="ajuste_manual">Ajustes Manuais</option>
                                                    <option value="remocao_manual">Remoções Manuais</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body pt-0">
                                        <?php if (empty($historico_banco_horas)): ?>
                                            <div class="alert alert-info d-flex align-items-center p-5">
                                                <i class="ki-duotone ki-information fs-2hx text-info me-4">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                                <div class="d-flex flex-column">
                                                    <h4 class="mb-1 text-info">Nenhuma movimentação</h4>
                                                    <span>Nenhuma movimentação registrada no banco de horas.</span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_table_historico_banco_horas">
                                                    <thead>
                                                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                                            <th class="min-w-100px">Data</th>
                                                            <th class="min-w-100px">Tipo</th>
                                                            <th class="min-w-150px">Origem</th>
                                                            <th class="min-w-100px">Quantidade</th>
                                                            <th class="min-w-100px">Saldo Anterior</th>
                                                            <th class="min-w-100px">Saldo Posterior</th>
                                                            <th class="min-w-200px">Motivo</th>
                                                            <th class="min-w-150px">Usuário</th>
                                                            <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                                                            <th class="text-end min-w-70px">Ações</th>
                                                            <?php endif; ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="fw-semibold text-gray-600">
                                                        <?php foreach ($historico_banco_horas as $mov): ?>
                                                        <tr>
                                                            <td><?= date('d/m/Y', strtotime($mov['data_movimentacao'])) ?></td>
                                                            <td>
                                                                <?php if ($mov['tipo'] === 'credito'): ?>
                                                                    <span class="badge badge-success">Crédito</span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-danger">Débito</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $origem_labels = [
                                                                    'hora_extra' => 'Hora Extra',
                                                                    'ocorrencia' => 'Ocorrência',
                                                                    'ajuste_manual' => 'Ajuste Manual',
                                                                    'remocao_manual' => 'Remoção Manual'
                                                                ];
                                                                echo htmlspecialchars($origem_labels[$mov['origem']] ?? ucfirst($mov['origem']));
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($mov['tipo'] === 'credito'): ?>
                                                                    <span class="text-success fw-bold">+<?= number_format($mov['quantidade_horas'], 2, ',', '.') ?>h</span>
                                                                <?php else: ?>
                                                                    <span class="text-danger fw-bold">-<?= number_format($mov['quantidade_horas'], 2, ',', '.') ?>h</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= number_format($mov['saldo_anterior'], 2, ',', '.') ?>h</td>
                                                            <td>
                                                                <?php 
                                                                $saldo_posterior = $mov['saldo_posterior'];
                                                                $cor_posterior = $saldo_posterior >= 0 ? 'success' : 'danger';
                                                                ?>
                                                                <span class="text-<?= $cor_posterior ?> fw-bold">
                                                                    <?= number_format($saldo_posterior, 2, ',', '.') ?>h
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="text-gray-800" title="<?= htmlspecialchars($mov['motivo']) ?>">
                                                                    <?= htmlspecialchars(mb_substr($mov['motivo'], 0, 50)) ?><?= mb_strlen($mov['motivo']) > 50 ? '...' : '' ?>
                                                                </div>
                                                                <?php if (!empty($mov['observacoes'])): ?>
                                                                    <small class="text-muted"><?= htmlspecialchars(mb_substr($mov['observacoes'], 0, 30)) ?><?= mb_strlen($mov['observacoes']) > 30 ? '...' : '' ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($mov['usuario_nome'] ?? 'Sistema') ?></td>
                                                            <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                                                            <td class="text-end">
                                                                <button type="button" class="btn btn-sm btn-light-danger" 
                                                                        onclick="deletarMovimentacaoBancoHoras(<?= $mov['id'] ?>, <?= $id ?>)"
                                                                        title="Deletar movimentação">
                                                                    <i class="ki-duotone ki-trash fs-5">
                                                                        <span class="path1"></span>
                                                                        <span class="path2"></span>
                                                                        <span class="path3"></span>
                                                                    </i>
                                                                </button>
                                                            </td>
                                                            <?php endif; ?>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Tab Pane - Banco de Horas-->
                    
                    <!--begin::Tab Pane - Ocorrências-->
                    <div class="tab-pane fade" id="kt_tab_pane_ocorrencias" role="tabpanel">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-7 gap-3">
                            <h3 class="fw-bold text-gray-800 mb-0">Ocorrências do Colaborador</h3>
                            <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                                <a href="ocorrencias_add.php?colaborador_id=<?= $id ?>" class="btn btn-primary w-100 w-md-auto">
                                    <i class="ki-duotone ki-plus fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Nova Ocorrência
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (empty($ocorrencias)): ?>
                            <div class="alert alert-info d-flex align-items-center p-5">
                                <i class="ki-duotone ki-information fs-2hx text-info me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <div class="d-flex flex-column">
                                    <h4 class="mb-1 text-info">Nenhuma ocorrência</h4>
                                    <span>Nenhuma ocorrência registrada para este colaborador.</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="card-body pt-0">
                                    <div class="table-responsive">
                                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                                            <thead>
                                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                                    <th class="min-w-100px">Data</th>
                                                    <th class="min-w-150px">Tipo</th>
                                                    <th class="min-w-200px">Descrição</th>
                                                    <th class="min-w-150px">Registrado por</th>
                                                    <th class="min-w-150px">Data Registro</th>
                                                    <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                                                    <th class="text-end min-w-150px">Ações</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody class="fw-semibold text-gray-600">
                                                <?php foreach ($ocorrencias as $ocorrencia): ?>
                                                <tr>
                                                    <td><?= formatar_data($ocorrencia['data_ocorrencia']) ?></td>
                                                    <td>
                                                        <span class="badge badge-light-<?= in_array($ocorrencia['tipo'], ['elogio']) ? 'success' : ($ocorrencia['tipo'] === 'advertência' ? 'danger' : 'warning') ?>">
                                                            <?= htmlspecialchars($ocorrencia['tipo_nome'] ?? $ocorrencia['tipo']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= nl2br(htmlspecialchars($ocorrencia['descricao'])) ?></td>
                                                    <td><?= htmlspecialchars($ocorrencia['usuario_nome']) ?></td>
                                                    <td><?= formatar_data($ocorrencia['created_at'], 'd/m/Y H:i') ?></td>
                                                    <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                                                    <td class="text-end">
                                                        <div class="d-flex gap-2 justify-content-end">
                                                            <a href="ocorrencia_view.php?id=<?= $ocorrencia['id'] ?>" class="btn btn-sm btn-light btn-active-light-primary" title="Ver Detalhes">
                                                                <i class="ki-duotone ki-eye fs-2">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                    <span class="path3"></span>
                                                                </i>
                                                            </a>
                                                            <?php if (has_role(['ADMIN', 'RH'])): ?>
                                                            <a href="ocorrencias_edit.php?id=<?= $ocorrencia['id'] ?>" class="btn btn-sm btn-light btn-active-light-warning" title="Editar">
                                                                <i class="ki-duotone ki-pencil fs-2">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                </i>
                                                            </a>
                                                            <a href="#" onclick="deletarOcorrencia(<?= $ocorrencia['id'] ?>); return false;" class="btn btn-sm btn-light btn-active-light-danger" title="Deletar">
                                                                <i class="ki-duotone ki-trash fs-2">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                    <span class="path3"></span>
                                                                    <span class="path4"></span>
                                                                    <span class="path5"></span>
                                                                </i>
                                                            </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!--end::Tab Pane - Ocorrências-->
                    
                    <!--begin::Tab Pane - Manuais Individuais-->
                    <div class="tab-pane fade" id="kt_tab_pane_manuais" role="tabpanel">
                        <div class="card-body pt-0">
                            <?php if (empty($manuais_individuais)): ?>
                            <div class="text-center py-10">
                                <i class="ki-duotone ki-book fs-3x text-gray-400 mb-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <p class="text-gray-600 fs-5">Nenhum manual individual disponível no momento.</p>
                            </div>
                            <?php else: ?>
                            <div class="row g-5">
                                <?php foreach ($manuais_individuais as $manual): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100">
                                        <div class="card-header border-0 pt-6">
                                            <div class="card-title">
                                                <h3 class="fw-bold m-0"><?= htmlspecialchars($manual['titulo']) ?></h3>
                                            </div>
                                        </div>
                                        <div class="card-body pt-0">
                                            <?php if ($manual['descricao']): ?>
                                            <p class="text-gray-600 mb-4"><?= nl2br(htmlspecialchars(mb_substr($manual['descricao'], 0, 150))) ?><?= mb_strlen($manual['descricao']) > 150 ? '...' : '' ?></p>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="text-muted fs-7">
                                                    Criado em <?= formatar_data($manual['created_at']) ?>
                                                </span>
                                                <a href="manual_individuais_view.php?id=<?= $manual['id'] ?>" class="btn btn-sm btn-light-primary">
                                                    Ver Manual
                                                    <i class="ki-duotone ki-arrow-right fs-3 ms-1">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!--end::Tab Pane - Manuais Individuais-->
                    
                    <!--begin::Tab Pane - Escola Privus-->
                    <div class="tab-pane fade" id="kt_tab_pane_escola_privus" role="tabpanel">
                        <!-- Estatísticas Gerais -->
                        <div class="row g-4 mb-7">
                            <div class="col-md-3">
                                <div class="card card-flush h-100">
                                    <div class="card-body text-center p-5">
                                        <div class="text-gray-400 fw-bold fs-6 mb-2">Total de Cursos</div>
                                        <div class="text-gray-900 fw-bold fs-2x"><?= $lms_stats['total_cursos'] ?? 0 ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card card-flush h-100">
                                    <div class="card-body text-center p-5">
                                        <div class="text-gray-400 fw-bold fs-6 mb-2">Concluídos</div>
                                        <div class="text-success fw-bold fs-2x"><?= $lms_stats['cursos_concluidos'] ?? 0 ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card card-flush h-100">
                                    <div class="card-body text-center p-5">
                                        <div class="text-gray-400 fw-bold fs-6 mb-2">Certificados</div>
                                        <div class="text-primary fw-bold fs-2x"><?= $lms_stats['total_certificados'] ?? 0 ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card card-flush h-100">
                                    <div class="card-body text-center p-5">
                                        <div class="text-gray-400 fw-bold fs-6 mb-2">Progresso Geral</div>
                                        <div class="text-gray-900 fw-bold fs-2x"><?= $lms_percentual_geral ?>%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tempo de Estudo e Conquistas -->
                        <div class="row g-4 mb-7">
                            <div class="col-md-6">
                                <div class="card card-flush h-100">
                                    <div class="card-header pt-7">
                                        <h3 class="card-title align-items-start flex-column">
                                            <span class="card-label fw-bold text-gray-800">Tempo de Estudo</span>
                                            <span class="text-muted mt-1 fw-semibold fs-7">Total de horas assistidas</span>
                                        </h3>
                                    </div>
                                    <div class="card-body pt-6">
                                        <div class="text-center py-5">
                                            <div class="symbol symbol-circle symbol-100px mb-5">
                                                <div class="symbol-label bg-light-primary">
                                                    <i class="ki-duotone ki-time fs-2x text-primary">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </div>
                                            </div>
                                            <h1 class="fw-bold text-gray-900 mb-2">
                                                <span class="text-primary"><?= $lms_tempo_total_horas ?></span>
                                                <span class="fs-3 text-gray-600"> horas</span>
                                            </h1>
                                            <p class="text-gray-500 mb-0">
                                                <?= $lms_stats['total_aulas_concluidas'] ?? 0 ?> aulas concluídas
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card card-flush h-100">
                                    <div class="card-header pt-7">
                                        <h3 class="card-title align-items-start flex-column">
                                            <span class="card-label fw-bold text-gray-800">Conquistas</span>
                                            <span class="text-muted mt-1 fw-semibold fs-7"><?= count($lms_badges) ?> badge(s) conquistado(s)</span>
                                        </h3>
                                    </div>
                                    <div class="card-body pt-6">
                                        <?php if (empty($lms_badges)): ?>
                                            <div class="text-center py-10">
                                                <i class="ki-duotone ki-medal fs-3x text-gray-300 mb-4">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <p class="text-gray-500">Nenhuma conquista ainda</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="row g-3">
                                                <?php foreach ($lms_badges as $badge): ?>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                                        <?php if ($badge['badge_icone']): ?>
                                                        <i class="<?= htmlspecialchars($badge['badge_icone']) ?> fs-2x me-3" style="color: <?= htmlspecialchars($badge['badge_cor'] ?? '#ffc700') ?>"></i>
                                                        <?php else: ?>
                                                        <div class="symbol symbol-circle symbol-40px me-3" style="background-color: <?= htmlspecialchars($badge['badge_cor'] ?? '#ffc700') ?>20">
                                                            <div class="symbol-label" style="color: <?= htmlspecialchars($badge['badge_cor'] ?? '#ffc700') ?>">
                                                                <i class="ki-duotone ki-medal fs-2">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                </i>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                        <div class="flex-grow-1">
                                                            <div class="fw-bold text-gray-800"><?= htmlspecialchars($badge['badge_nome']) ?></div>
                                                            <small class="text-gray-500"><?= formatar_data($badge['data_conquista']) ?></small>
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
                        
                        <!-- Gráfico de Evolução -->
                        <?php if (!empty($lms_evolucao)): ?>
                        <div class="card mb-7">
                            <div class="card-header border-0 pt-6">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label fw-bold fs-3 mb-1">Evolução de Conclusões</span>
                                    <span class="text-muted fw-semibold fs-7">Últimos 30 dias</span>
                                </h3>
                            </div>
                            <div class="card-body pt-0">
                                <canvas id="lms_evolucao_chart" style="height: 300px;"></canvas>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Cursos Obrigatórios -->
                        <?php if (!empty($lms_cursos_obrigatorios)): ?>
                        <div class="card mb-7">
                            <div class="card-header border-0 pt-6">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label fw-bold fs-3 mb-1">Cursos Obrigatórios</span>
                                    <span class="text-muted fw-semibold fs-7"><?= count($lms_cursos_obrigatorios) ?> curso(s) obrigatório(s)</span>
                                </h3>
                            </div>
                            <div class="card-body pt-0">
                                <div class="table-responsive">
                                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                                        <thead>
                                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                                <th class="min-w-200px">Curso</th>
                                                <th class="min-w-100px">Status</th>
                                                <th class="min-w-100px">Data Limite</th>
                                                <th class="min-w-100px">Progresso</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fw-semibold text-gray-600">
                                            <?php foreach ($lms_cursos_obrigatorios as $curso_obr): ?>
                                            <?php
                                            // Busca progresso do curso obrigatório
                                            $stmt_prog = $pdo->prepare("
                                                SELECT 
                                                    COUNT(DISTINCT a.id) as total_aulas,
                                                    COUNT(DISTINCT CASE WHEN pc.status = 'concluido' THEN pc.aula_id END) as aulas_concluidas
                                                FROM cursos c
                                                LEFT JOIN aulas a ON a.curso_id = c.id AND a.status = 'publicado'
                                                LEFT JOIN progresso_colaborador pc ON pc.curso_id = c.id AND pc.colaborador_id = ?
                                                WHERE c.id = ?
                                            ");
                                            $stmt_prog->execute([$id, $curso_obr['curso_id']]);
                                            $prog_obr = $stmt_prog->fetch();
                                            $percentual_obr = $prog_obr['total_aulas'] > 0 
                                                ? round(($prog_obr['aulas_concluidas'] / $prog_obr['total_aulas']) * 100, 0)
                                                : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($curso_obr['imagem_capa']): ?>
                                                        <img src="../<?= htmlspecialchars($curso_obr['imagem_capa']) ?>" class="rounded me-3" width="50" height="50" style="object-fit: cover;">
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="fw-bold text-gray-800"><?= htmlspecialchars($curso_obr['curso_titulo']) ?></div>
                                                            <?php if ($curso_obr['duracao_estimada']): ?>
                                                            <small class="text-gray-500"><?= $curso_obr['duracao_estimada'] ?> min</small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = 'secondary';
                                                    $status_text = ucfirst($curso_obr['status']);
                                                    if ($curso_obr['status'] === 'concluido') {
                                                        $status_class = 'success';
                                                    } elseif ($curso_obr['status'] === 'vencido') {
                                                        $status_class = 'danger';
                                                    } elseif (in_array($curso_obr['status'], ['pendente', 'em_andamento'])) {
                                                        $status_class = 'warning';
                                                    }
                                                    ?>
                                                    <span class="badge badge-light-<?= $status_class ?>"><?= $status_text ?></span>
                                                </td>
                                                <td>
                                                    <?= $curso_obr['data_limite'] ? formatar_data($curso_obr['data_limite']) : '-' ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress w-100 me-2" style="height: 20px;">
                                                            <div class="progress-bar bg-<?= $percentual_obr == 100 ? 'success' : ($percentual_obr > 0 ? 'primary' : 'secondary') ?>" 
                                                                 role="progressbar" 
                                                                 style="width: <?= $percentual_obr ?>%">
                                                            </div>
                                                        </div>
                                                        <span class="fw-bold text-gray-800 min-w-50px text-end"><?= $percentual_obr ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Lista de Cursos -->
                        <div class="card mb-7">
                            <div class="card-header border-0 pt-6">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label fw-bold fs-3 mb-1">Todos os Cursos</span>
                                    <span class="text-muted fw-semibold fs-7"><?= count($lms_cursos) ?> curso(s) disponível(is)</span>
                                </h3>
                            </div>
                            <div class="card-body pt-0">
                                <?php if (empty($lms_cursos)): ?>
                                    <div class="alert alert-info d-flex align-items-center p-5">
                                        <i class="ki-duotone ki-information fs-2hx text-info me-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <div class="d-flex flex-column">
                                            <h4 class="mb-1 text-info">Nenhum curso disponível</h4>
                                            <span>Nenhum curso foi encontrado para este colaborador.</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                                            <thead>
                                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                                    <th class="min-w-200px">Curso</th>
                                                    <th class="min-w-100px">Categoria</th>
                                                    <th class="min-w-100px">Status</th>
                                                    <th class="min-w-150px">Progresso</th>
                                                    <th class="min-w-100px">Último Acesso</th>
                                                    <th class="text-end min-w-100px">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody class="fw-semibold text-gray-600">
                                                <?php foreach ($lms_cursos as $curso): ?>
                                                <?php
                                                $percentual_curso = $curso['total_aulas'] > 0 
                                                    ? round(($curso['aulas_concluidas'] / $curso['total_aulas']) * 100, 0)
                                                    : 0;
                                                $tempo_horas = round($curso['tempo_assistido_segundos'] / 3600, 1);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if ($curso['imagem_capa']): ?>
                                                            <img src="../<?= htmlspecialchars($curso['imagem_capa']) ?>" class="rounded me-3" width="50" height="50" style="object-fit: cover;">
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-bold text-gray-800"><?= htmlspecialchars($curso['titulo']) ?></div>
                                                                <?php if ($curso['duracao_estimada']): ?>
                                                                <small class="text-gray-500"><?= $curso['duracao_estimada'] ?> min</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($curso['categoria_nome']): ?>
                                                        <span class="badge badge-light" style="background-color: <?= htmlspecialchars($curso['categoria_cor'] ?? '#009ef7') ?>20; color: <?= htmlspecialchars($curso['categoria_cor'] ?? '#009ef7') ?>">
                                                            <?= htmlspecialchars($curso['categoria_nome']) ?>
                                                        </span>
                                                        <?php else: ?>
                                                        <span class="text-gray-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_class = 'secondary';
                                                        if ($curso['status_curso'] === 'concluido') {
                                                            $status_class = 'success';
                                                        } elseif ($curso['status_curso'] === 'em_andamento') {
                                                            $status_class = 'primary';
                                                        }
                                                        ?>
                                                        <span class="badge badge-light-<?= $status_class ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $curso['status_curso'])) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress w-100 me-2" style="height: 20px;">
                                                                <div class="progress-bar bg-<?= $percentual_curso == 100 ? 'success' : ($percentual_curso > 0 ? 'primary' : 'secondary') ?>" 
                                                                     role="progressbar" 
                                                                     style="width: <?= $percentual_curso ?>%">
                                                                </div>
                                                            </div>
                                                            <span class="fw-bold text-gray-800 min-w-50px text-end"><?= $percentual_curso ?>%</span>
                                                        </div>
                                                        <small class="text-gray-500"><?= $curso['aulas_concluidas'] ?>/<?= $curso['total_aulas'] ?> aulas</small>
                                                    </td>
                                                    <td>
                                                        <?= $curso['ultimo_acesso'] ? formatar_data($curso['ultimo_acesso'], 'd/m/Y H:i') : '-' ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="lms_curso_detalhes.php?id=<?= $curso['id'] ?>" class="btn btn-sm btn-light" target="_blank">
                                                            Ver Detalhes
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Certificados -->
                        <?php if (!empty($lms_certificados)): ?>
                        <div class="card">
                            <div class="card-header border-0 pt-6">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label fw-bold fs-3 mb-1">Certificados</span>
                                    <span class="text-muted fw-semibold fs-7"><?= count($lms_certificados) ?> certificado(s) emitido(s)</span>
                                </h3>
                            </div>
                            <div class="card-body pt-0">
                                <div class="row g-4">
                                    <?php foreach ($lms_certificados as $cert): ?>
                                    <div class="col-md-6">
                                        <div class="card card-flush border border-gray-300">
                                            <div class="card-body p-5">
                                                <div class="d-flex align-items-center mb-4">
                                                    <?php if ($cert['imagem_capa']): ?>
                                                    <img src="../<?= htmlspecialchars($cert['imagem_capa']) ?>" class="rounded me-3" width="60" height="60" style="object-fit: cover;">
                                                    <?php endif; ?>
                                                    <div class="flex-grow-1">
                                                        <h4 class="fw-bold text-gray-800 mb-1"><?= htmlspecialchars($cert['curso_titulo']) ?></h4>
                                                        <small class="text-gray-500">Emitido em <?= formatar_data($cert['data_emissao']) ?></small>
                                                    </div>
                                                    <i class="ki-duotone ki-verified fs-2x text-success">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </div>
                                                <?php if ($cert['codigo_unico']): ?>
                                                <div class="d-flex align-items-center justify-content-between pt-4 border-top">
                                                    <span class="text-gray-600 fw-semibold">Código:</span>
                                                    <span class="text-gray-800 fw-bold"><?= htmlspecialchars($cert['codigo_unico']) ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!--end::Tab Pane - Escola Privus-->
                </div>
                <!--end::Tab Content-->
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<?php if ($usuario['role'] !== 'COLABORADOR'): ?>
<!-- Modal Bônus -->
<div class="modal fade" id="kt_modal_bonus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_bonus_header">
                <h2 class="fw-bold">Adicionar Bônus</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_bonus_form" method="POST" action="../api/salvar_bonus_colaborador.php">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <input type="hidden" name="action" id="bonus_action" value="add">
                    <input type="hidden" name="id" id="bonus_id">
                    <input type="hidden" name="colaborador_id" value="<?= $id ?>">
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Tipo de Bônus *</label>
                        <select name="tipo_bonus_id" id="tipo_bonus_id" class="form-select form-select-solid" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($tipos_bonus as $tipo): ?>
                            <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Valor (R$) *</label>
                        <input type="text" name="valor" id="valor_bonus" class="form-control form-control-solid mb-3 mb-lg-0" placeholder="0,00" required />
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Data Início</label>
                            <input type="date" name="data_inicio" id="data_inicio_bonus" class="form-control form-control-solid" />
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Data Fim</label>
                            <input type="date" name="data_fim" id="data_fim_bonus" class="form-control form-control-solid" />
                        </div>
                    </div>
                    
                    <div class="alert alert-info d-flex align-items-center p-4 mb-7">
                        <i class="ki-duotone ki-information fs-2hx text-info me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <span class="fw-semibold text-gray-800 mb-1">Como funcionam as datas:</span>
                            <span class="text-gray-700 fs-7">
                                • <strong>Data Início:</strong> Define quando o bônus começa a valer. Se deixar em branco, será considerado a partir de hoje.<br>
                                • <strong>Data Fim:</strong> Define quando o bônus deixa de valer. Se deixar em branco, o bônus será permanente.<br>
                                • O bônus será incluído automaticamente no fechamento de pagamentos quando estiver ativo no período.
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Observações</label>
                        <textarea name="observacoes" id="observacoes_bonus" class="form-control form-control-solid" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
const colaboradorId = <?= $id ?>;
let humorChart = null;
let feedbackRadarChart = null;

// Carrega métricas ao abrir a aba Jornada
document.addEventListener('DOMContentLoaded', function() {
    // Carrega métricas quando a aba Jornada é aberta
    const tabJornada = document.getElementById('kt_tab_pane_jornada');
    if (tabJornada && tabJornada.classList.contains('active')) {
        carregarMetricas();
    }
    
    // Listener para quando a aba Jornada é clicada
    const linkJornada = document.querySelector('[href="#kt_tab_pane_jornada"]');
    if (linkJornada) {
        linkJornada.addEventListener('shown.bs.tab', function() {
            carregarMetricas();
        });
    }
    
    // Botões de filtro
    document.getElementById('btn-filtrar')?.addEventListener('click', function() {
        carregarMetricas();
    });
    
    document.getElementById('btn-limpar-filtros')?.addEventListener('click', function() {
        document.getElementById('filtro-data-inicio').value = '';
        document.getElementById('filtro-data-fim').value = '';
        carregarMetricas();
    });
});

function carregarMetricas() {
    const dataInicio = document.getElementById('filtro-data-inicio').value;
    const dataFim = document.getElementById('filtro-data-fim').value;
    
    const params = new URLSearchParams({
        colaborador_id: colaboradorId
    });
    if (dataInicio) params.append('data_inicio', dataInicio);
    if (dataFim) params.append('data_fim', dataFim);
    
    fetch(`<?= get_base_url() ?>/api/colaborador/metricas.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarMetricas(data);
            } else {
                console.error('Erro ao carregar métricas:', data.message);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar métricas:', error);
        });
}

function renderizarMetricas(data) {
    const container = document.getElementById('metricas-container');
    const metricas = data.metricas;
    
    // Mapeia nível de humor para emoji
    const humorEmojis = {
        1: '😢',
        2: '😔',
        3: '😐',
        4: '🙂',
        5: '😄'
    };
    
    const humorEmoji = metricas.media_humor ? humorEmojis[Math.round(metricas.media_humor)] || '😐' : '😐';
    
    container.innerHTML = `
        <!-- Métricas principais -->
        <div class="row mb-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center p-6">
                        <h4 class="mb-4">Termômetro de Humor</h4>
                        <div class="mb-3">
                            <span style="font-size: 80px;">${humorEmoji}</span>
                        </div>
                        <h3 class="text-gray-800">Média ${metricas.media_humor || 'N/A'}</h3>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body p-6">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="text-center">
                                    <div class="fs-2x fw-bold text-gray-800">${metricas.feedbacks_enviados}</div>
                                    <div class="text-gray-600">Feedbacks Enviados</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="text-center">
                                    <div class="fs-2x fw-bold text-gray-800">${metricas.feedbacks_recebidos}</div>
                                    <div class="text-gray-600">Feedbacks Recebidos</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="text-center">
                                    <div class="fs-2x fw-bold text-gray-800">${metricas.humores_respondidos}</div>
                                    <div class="text-gray-600">Humores Respondidos</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="text-center">
                                    <div class="fs-2x fw-bold text-gray-800">${metricas.reunioes_1on1_colaborador}</div>
                                    <div class="text-gray-600">1:1 como colaborador</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="text-center">
                                    <div class="fs-2x fw-bold text-gray-800">${metricas.celebracoes_enviadas}</div>
                                    <div class="text-gray-600">Celebrações Enviadas</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="text-center">
                                    <div class="fs-2x fw-bold text-gray-800">${metricas.reunioes_1on1_gestor}</div>
                                    <div class="text-gray-600">1:1 como gestor</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráficos -->
        <div class="row mb-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h4 class="mb-4">Histórico de Humor</h4>
                        <canvas id="humor-chart" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h4 class="mb-4">Radar de Feedbacks</h4>
                        <canvas id="feedback-radar-chart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Histórico de Humores -->
        <div class="card mb-5" id="historico-humores-section">
            <div class="card-header">
                <h3 class="card-title">Histórico de Humor</h3>
            </div>
            <div class="card-body">
                <div id="historico-humores-container">
                    ${renderizarHistoricoHumores(data.historico_humores)}
                </div>
            </div>
        </div>
        
        <!-- Feedbacks Recebidos -->
        <div class="card mb-5" id="feedbacks-recebidos-section">
            <div class="card-header">
                <h3 class="card-title">Histórico de Feedbacks recebidos</h3>
            </div>
            <div class="card-body">
                <div id="feedbacks-recebidos-container">
                    ${renderizarFeedbacksRecebidos(data.feedbacks_recebidos)}
                </div>
            </div>
        </div>
        
        <!-- Reuniões 1:1 -->
        <div class="card mb-5" id="reunioes-1on1-section">
            <div class="card-header">
                <h3 class="card-title">Reuniões de 1:1</h3>
            </div>
            <div class="card-body">
                <div id="reunioes-1on1-container">
                    ${renderizarReunioes1on1(data.reunioes_1on1)}
                </div>
            </div>
        </div>
        
        <!-- PDIs -->
        <div class="card mb-5" id="pdis-section">
            <div class="card-header">
                <h3 class="card-title">Planos de Desenvolvimento</h3>
            </div>
            <div class="card-body">
                <div id="pdis-container">
                    ${renderizarPDIs(data.pdis)}
                </div>
            </div>
        </div>
    `;
    
    // Renderiza gráficos
    setTimeout(() => {
        renderizarGraficoHumor(data.historico_humores);
        renderizarGraficoRadar(data.feedbacks_recebidos);
    }, 100);
}

function renderizarHistoricoHumores(humores) {
    if (!humores || humores.length === 0) {
        return '<div class="alert alert-info">Nenhum humor registrado no período selecionado.</div>';
    }
    
    const humorEmojis = {1: '😢', 2: '😔', 3: '😐', 4: '🙂', 5: '😄'};
    
    return `
        <div class="row">
            ${humores.map(h => `
                <div class="col-md-3 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="mb-2">
                                <span style="font-size: 40px;">${humorEmojis[h.nivel_emocao] || '😐'}</span>
                            </div>
                            <div class="text-gray-600 small">${formatarData(h.data_registro)} ${h.created_at ? formatarHora(h.created_at) : ''}</div>
                            <div class="text-gray-800 mt-2">${h.descricao || 'sem comentário'}</div>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderizarFeedbacksRecebidos(feedbacks) {
    if (!feedbacks || feedbacks.length === 0) {
        return '<div class="alert alert-info">Nenhum feedback recebido no período selecionado.</div>';
    }
    
    return `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="30%">Enviado por</th>
                        <th width="20%">Conteúdo</th>
                        <th width="30%">Avaliação</th>
                        <th width="20%">Criado em</th>
                    </tr>
                </thead>
                <tbody>
                    ${feedbacks.map((fb, idx) => `
                        <tr>
                            <td>
                                ${fb.remetente_foto ? `<img src="../${fb.remetente_foto}" class="rounded-circle me-2" width="32" height="32" style="object-fit: cover;">` : ''}
                                ${fb.remetente_nome || 'Anônimo'}
                            </td>
                            <td>
                                <button class="btn btn-sm btn-light" onclick="abrirModalFeedback(${fb.id}, ${idx})">
                                    Ler Feedback
                                </button>
                            </td>
                            <td>
                                ${fb.avaliacoes && fb.avaliacoes.length > 0 ? fb.avaliacoes.map(av => `
                                    <span class="badge badge-light-${av.nota >= 4 ? 'success' : (av.nota >= 3 ? 'warning' : 'danger')} me-1">
                                        ${av.item_nome || 'Avaliação'}: ${av.nota}
                                    </span>
                                `).join('') : '-'}
                            </td>
                            <td>${formatarData(fb.created_at)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function renderizarReunioes1on1(reunioes) {
    if (!reunioes || reunioes.length === 0) {
        return '<div class="alert alert-info">Nenhuma reunião 1:1 encontrada no período selecionado.</div>';
    }
    
    return `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="25%">Líder</th>
                        <th width="25%">Liderado</th>
                        <th width="20%">Data</th>
                        <th width="15%">Status</th>
                        <th width="15%">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${reunioes.map(r => `
                        <tr>
                            <td>
                                ${r.lider_foto ? `<img src="${r.lider_foto}" class="rounded-circle me-2" width="35" height="35" style="object-fit: cover;">` : ''}
                                ${r.lider_nome}
                            </td>
                            <td>
                                ${r.liderado_foto ? `<img src="${r.liderado_foto}" class="rounded-circle me-2" width="35" height="35" style="object-fit: cover;">` : ''}
                                ${r.liderado_nome}
                            </td>
                            <td>${formatarData(r.data_reuniao)}</td>
                            <td>
                                <span class="badge badge-light-${r.status === 'realizada' ? 'success' : (r.status === 'cancelada' ? 'danger' : 'warning')}">
                                    ${r.status === 'realizada' ? 'Realizada' : (r.status === 'cancelada' ? 'Cancelada' : (r.status === 'reagendada' ? 'Reagendada' : 'Agendada'))}
                                </span>
                            </td>
                            <td>
                                <a href="reuniao_1on1_view.php?id=${r.id}" class="btn btn-sm btn-light" target="_blank">
                                    Visualizar
                                </a>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function renderizarPDIs(pdis) {
    if (!pdis || pdis.length === 0) {
        return '<div class="alert alert-info">Nenhum PDI encontrado.</div>';
    }
    
    return `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Status</th>
                        <th>Data Início</th>
                        <th>Data Fim Prevista</th>
                        <th>Objetivos</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${pdis.map(p => `
                        <tr>
                            <td>${p.titulo || '-'}</td>
                            <td>
                                <span class="badge badge-light-${p.status === 'ativo' ? 'success' : (p.status === 'concluido' ? 'primary' : 'secondary')}">
                                    ${p.status === 'ativo' ? 'Ativo' : (p.status === 'concluido' ? 'Concluído' : 'Rascunho')}
                                </span>
                            </td>
                            <td>${formatarData(p.data_inicio)}</td>
                            <td>${p.data_fim_prevista ? formatarData(p.data_fim_prevista) : '-'}</td>
                            <td>${p.total_objetivos || 0}</td>
                            <td>${p.total_acoes || 0}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function renderizarGraficoHumor(humores) {
    if (!humores || humores.length === 0) return;
    
    const ctx = document.getElementById('humor-chart');
    if (!ctx) return;
    
    if (humorChart) humorChart.destroy();
    
    const labels = humores.map(h => formatarData(h.data_registro)).reverse();
    const data = humores.map(h => h.nivel_emocao).reverse();
    
    humorChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Nível de Humor',
                data: data,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

function renderizarGraficoRadar(feedbacks) {
    if (!feedbacks || feedbacks.length === 0) return;
    
    const ctx = document.getElementById('feedback-radar-chart');
    if (!ctx) return;
    
    if (feedbackRadarChart) feedbackRadarChart.destroy();
    
    // Agrupa avaliações por tipo
    const avaliacoesPorTipo = {};
    feedbacks.forEach(fb => {
        if (fb.avaliacoes) {
            fb.avaliacoes.forEach(av => {
                const tipo = av.item_nome || 'Geral';
                if (!avaliacoesPorTipo[tipo]) {
                    avaliacoesPorTipo[tipo] = [];
                }
                avaliacoesPorTipo[tipo].push(av.nota);
            });
        }
    });
    
    const tipos = Object.keys(avaliacoesPorTipo);
    const medias = tipos.map(tipo => {
        const notas = avaliacoesPorTipo[tipo];
        return notas.reduce((a, b) => a + b, 0) / notas.length;
    });
    
    if (tipos.length === 0) return;
    
    feedbackRadarChart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: tipos,
            datasets: [{
                label: 'Média de Avaliações',
                data: medias,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 5
                }
            }
        }
    });
}

// Gráfico de evolução do banco de horas
let graficoBancoHoras = null;

function inicializarGraficoBancoHoras() {
    const ctx = document.getElementById('grafico_banco_horas');
    if (!ctx) return;
    
    const dadosGrafico = <?= json_encode($dados_grafico) ?>;
    
    // Prepara dados para o gráfico
    const labels = [];
    const saldos = [];
    let saldoAcumulado = <?= $saldo_banco_horas['saldo_total_horas'] ?>;
    
    // Calcula saldo acumulado dia a dia (de trás para frente)
    const saldosPorData = {};
    dadosGrafico.forEach(item => {
        saldosPorData[item.data] = parseFloat(item.saldo_final_dia || 0);
    });
    
    // Cria array de últimos 30 dias
    const hoje = new Date();
    for (let i = 29; i >= 0; i--) {
        const data = new Date(hoje);
        data.setDate(data.getDate() - i);
        const dataStr = data.toISOString().split('T')[0];
        labels.push(new Date(dataStr).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }));
        
        if (saldosPorData[dataStr] !== undefined) {
            saldos.push(saldosPorData[dataStr]);
        } else {
            // Se não tem dado para este dia, usa o último saldo conhecido
            saldos.push(saldos.length > 0 ? saldos[saldos.length - 1] : saldoAcumulado);
        }
    }
    
    // Destrói gráfico anterior se existir
    if (graficoBancoHoras) {
        graficoBancoHoras.destroy();
    }
    
    graficoBancoHoras = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Saldo (horas)',
                data: saldos,
                borderColor: 'rgb(0, 158, 247)',
                backgroundColor: 'rgba(0, 158, 247, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Saldo: ' + parseFloat(context.parsed.y).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' horas';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('pt-BR', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + 'h';
                        }
                    }
                }
            }
        }
    });
}

// Gráfico de evolução do LMS
let lmsEvolucaoChart = null;

function inicializarGraficoLMS() {
    const ctx = document.getElementById('lms_evolucao_chart');
    if (!ctx) return;
    
    const dadosEvolucao = <?= json_encode($lms_evolucao) ?>;
    
    if (!dadosEvolucao || dadosEvolucao.length === 0) return;
    
    // Prepara dados para o gráfico
    const labels = [];
    const dados = [];
    
    // Cria array dos últimos 30 dias
    const hoje = new Date();
    const dadosPorData = {};
    dadosEvolucao.forEach(item => {
        dadosPorData[item.data] = parseInt(item.aulas_concluidas || 0);
    });
    
    for (let i = 29; i >= 0; i--) {
        const data = new Date(hoje);
        data.setDate(data.getDate() - i);
        const dataStr = data.toISOString().split('T')[0];
        labels.push(new Date(dataStr).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }));
        dados.push(dadosPorData[dataStr] || 0);
    }
    
    // Destrói gráfico anterior se existir
    if (lmsEvolucaoChart) {
        lmsEvolucaoChart.destroy();
    }
    
    lmsEvolucaoChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Aulas Concluídas',
                data: dados,
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

// Inicializa gráfico quando a aba for ativada
document.addEventListener('DOMContentLoaded', function() {
    const tabBancoHoras = document.querySelector('[href="#kt_tab_pane_banco_horas"]');
    if (tabBancoHoras) {
        tabBancoHoras.addEventListener('shown.bs.tab', function() {
            setTimeout(inicializarGraficoBancoHoras, 100);
        });
    }
    
    const tabEscolaPrivus = document.querySelector('[href="#kt_tab_pane_escola_privus"]');
    if (tabEscolaPrivus) {
        tabEscolaPrivus.addEventListener('shown.bs.tab', function() {
            setTimeout(inicializarGraficoLMS, 100);
        });
    }
    
    // Filtros do histórico
    const filtroTipo = document.getElementById('filtro_tipo_historico');
    const filtroOrigem = document.getElementById('filtro_origem_historico');
    const tabelaHistorico = document.getElementById('kt_table_historico_banco_horas');
    
    if (filtroTipo && filtroOrigem && tabelaHistorico) {
        function aplicarFiltros() {
            const tipoFiltro = filtroTipo.value.toLowerCase();
            const origemFiltro = filtroOrigem.value.toLowerCase();
            const linhas = tabelaHistorico.querySelectorAll('tbody tr');
            
            linhas.forEach(linha => {
                const tipo = linha.querySelector('td:nth-child(2)')?.textContent.trim().toLowerCase() || '';
                const origem = linha.querySelector('td:nth-child(3)')?.textContent.trim().toLowerCase() || '';
                
                const mostraTipo = !tipoFiltro || tipo.includes(tipoFiltro);
                const mostraOrigem = !origemFiltro || origem.includes(origemFiltro);
                
                linha.style.display = (mostraTipo && mostraOrigem) ? '' : 'none';
            });
        }
        
        filtroTipo.addEventListener('change', aplicarFiltros);
        filtroOrigem.addEventListener('change', aplicarFiltros);
    }
});

function formatarData(data) {
    if (!data) return '-';
    const d = new Date(data);
    return d.toLocaleDateString('pt-BR');
}

function formatarHora(data) {
    if (!data) return '';
    const d = new Date(data);
    return d.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
}

function abrirModalFeedback(feedbackId, index) {
    // Implementar modal de feedback
    alert('Modal de feedback será implementado');
}

<?php if ($usuario['role'] !== 'COLABORADOR'): ?>
// Função para deletar ocorrência
function deletarOcorrencia(ocorrenciaId) {
    Swal.fire({
        title: 'Tem certeza?',
        text: 'Esta ação não pode ser desfeita! A ocorrência será permanentemente deletada.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, deletar!',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn fw-bold btn-danger',
            cancelButton: 'btn fw-bold btn-light'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Envia requisição para deletar
            fetch('../api/ocorrencias/delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ocorrencia_id: ocorrenciaId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        text: data.message,
                        icon: 'success',
                        buttonsStyling: false,
                        confirmButtonText: 'OK',
                        customClass: {
                            confirmButton: 'btn fw-bold btn-primary'
                        }
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        text: data.message || 'Erro ao deletar ocorrência',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'OK',
                        customClass: {
                            confirmButton: 'btn fw-bold btn-primary'
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    text: 'Erro ao conectar com o servidor',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'OK',
                    customClass: {
                        confirmButton: 'btn fw-bold btn-primary'
                    }
                });
            });
        }
    });
}

// Função para deletar hora extra
function deletarHoraExtra(id) {
    Swal.fire({
        text: "Tem certeza que deseja excluir esta hora extra?",
        icon: "warning",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "Sim, excluir!",
        cancelButtonText: "Cancelar",
        customClass: {
            confirmButton: "btn fw-bold btn-danger",
            cancelButton: "btn fw-bold btn-active-light-primary"
        }
    }).then(function(result) {
        if (result.value) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'horas_extras.php';
            form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Função para recalcular saldo do banco de horas
function recalcularSaldoBancoHoras(colaboradorId) {
    Swal.fire({
        title: "Recalcular Saldo?",
        html: "Esta ação irá:<br><br>" +
              "✓ Recalcular o saldo baseado em todas as movimentações<br>" +
              "✓ Corrigir os saldos anterior/posterior de cada movimentação<br>" +
              "✓ Atualizar o saldo atual do colaborador<br><br>" +
              "<strong>Use após deletar movimentações incorretas!</strong>",
        icon: "question",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "Sim, recalcular!",
        cancelButtonText: "Cancelar",
        customClass: {
            confirmButton: "btn fw-bold btn-warning",
            cancelButton: "btn fw-bold btn-active-light-primary"
        }
    }).then(function(result) {
        if (result.value) {
            // Mostra loading
            Swal.fire({
                text: "Recalculando saldo...",
                icon: "info",
                buttonsStyling: false,
                showConfirmButton: false,
                allowOutsideClick: false
            });
            
            fetch('../api/banco_horas/recalcular_saldo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    colaborador_id: colaboradorId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: "Sucesso!",
                        html: data.message + "<br><br>" +
                              "<strong>Movimentações atualizadas:</strong> " + data.dados.movimentacoes_atualizadas + "<br>" +
                              "<strong>Saldo final:</strong> " + data.dados.saldo_final,
                        icon: "success",
                        buttonsStyling: false,
                        confirmButtonText: "OK",
                        customClass: {
                            confirmButton: "btn fw-bold btn-success"
                        }
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        text: data.error || 'Erro ao recalcular saldo',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'OK',
                        customClass: {
                            confirmButton: 'btn fw-bold btn-primary'
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    text: 'Erro ao conectar com o servidor',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'OK',
                    customClass: {
                        confirmButton: 'btn fw-bold btn-primary'
                    }
                });
            });
        }
    });
}

// Função para deletar movimentação do banco de horas
function deletarMovimentacaoBancoHoras(movimentacaoId, colaboradorId) {
    Swal.fire({
        title: "Atenção!",
        html: "Tem certeza que deseja excluir esta movimentação?<br><br>" +
              "<strong class='text-danger'>⚠️ IMPORTANTE:</strong><br>" +
              "Após deletar, você precisará <strong>recalcular o saldo</strong> do banco de horas!<br>" +
              "Use o botão 'Recalcular Saldo' que aparecerá após a exclusão.",
        icon: "warning",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "Sim, excluir!",
        cancelButtonText: "Cancelar",
        customClass: {
            confirmButton: "btn fw-bold btn-danger",
            cancelButton: "btn fw-bold btn-active-light-primary"
        }
    }).then(function(result) {
        if (result.value) {
            // Mostra loading
            Swal.fire({
                text: "Excluindo movimentação...",
                icon: "info",
                buttonsStyling: false,
                showConfirmButton: false,
                allowOutsideClick: false
            });
            
            fetch('../api/banco_horas/deletar_movimentacao.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    movimentacao_id: movimentacaoId,
                    colaborador_id: colaboradorId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: "Excluído!",
                        html: data.message + "<br><br>" +
                              "<strong>⚠️ Não esqueça de recalcular o saldo!</strong>",
                        icon: "success",
                        buttonsStyling: false,
                        confirmButtonText: "OK, vou recalcular",
                        customClass: {
                            confirmButton: "btn fw-bold btn-primary"
                        }
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        text: data.error || 'Erro ao excluir movimentação',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'OK',
                        customClass: {
                            confirmButton: 'btn fw-bold btn-primary'
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    text: 'Erro ao conectar com o servidor',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'OK',
                    customClass: {
                        confirmButton: 'btn fw-bold btn-primary'
                    }
                });
            });
        }
    });
}
<?php endif; ?>
</script>

<?php if ($usuario['role'] !== 'COLABORADOR'): ?>
<script>
// colaboradorId já está declarado acima

function novoBonus() {
    document.getElementById('kt_modal_bonus_header').querySelector('h2').textContent = 'Adicionar Bônus';
    document.getElementById('bonus_action').value = 'add';
    document.getElementById('bonus_id').value = '';
    document.getElementById('kt_modal_bonus_form').reset();
}

function editarBonus(bonus) {
    document.getElementById('kt_modal_bonus_header').querySelector('h2').textContent = 'Editar Bônus';
    document.getElementById('bonus_action').value = 'edit';
    document.getElementById('bonus_id').value = bonus.id;
    document.getElementById('tipo_bonus_id').value = bonus.tipo_bonus_id;
    document.getElementById('valor_bonus').value = parseFloat(bonus.valor).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('data_inicio_bonus').value = bonus.data_inicio || '';
    document.getElementById('data_fim_bonus').value = bonus.data_fim || '';
    document.getElementById('observacoes_bonus').value = bonus.observacoes || '';
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_bonus'));
    modal.show();
}

function deletarBonus(id, nome) {
    Swal.fire({
        text: `Tem certeza que deseja remover o bônus "${nome}"?`,
        icon: "warning",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "Sim, remover!",
        cancelButtonText: "Cancelar",
        customClass: {
            confirmButton: "btn fw-bold btn-danger",
            cancelButton: "btn fw-bold btn-active-light-primary"
        }
    }).then(function(result) {
        if (result.value) {
            const formData = new FormData();
            formData.append('id', id);
            
            fetch('../api/deletar_bonus_colaborador.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        text: data.message,
                        icon: "success",
                        buttonsStyling: false,
                        confirmButtonText: "OK",
                        customClass: {
                            confirmButton: "btn fw-bold btn-primary"
                        }
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        text: data.error || 'Erro ao remover bônus',
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "OK",
                        customClass: {
                            confirmButton: "btn fw-bold btn-primary"
                        }
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    text: 'Erro ao conectar com o servidor',
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "OK",
                    customClass: {
                        confirmButton: "btn fw-bold btn-primary"
                    }
                });
            });
        }
    });
}

// Máscara para valor
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
        jQuery('#valor_bonus').mask('#.##0,00', {reverse: true});
    }
});

// Submit do formulário
document.getElementById('kt_modal_bonus_form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../api/salvar_bonus_colaborador.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                text: data.message,
                icon: "success",
                buttonsStyling: false,
                confirmButtonText: "OK",
                customClass: {
                    confirmButton: "btn fw-bold btn-primary"
                }
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                text: data.error || 'Erro ao salvar bônus',
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "OK",
                customClass: {
                    confirmButton: "btn fw-bold btn-primary"
                }
            });
        }
    })
    .catch(error => {
        Swal.fire({
            text: 'Erro ao conectar com o servidor',
            icon: "error",
            buttonsStyling: false,
            confirmButtonText: "OK",
            customClass: {
                confirmButton: "btn fw-bold btn-primary"
            }
        });
    });
});
</script>
<?php endif; ?>

<?php if ($usuario['role'] !== 'COLABORADOR' && $usuario['role'] !== 'GESTOR'): ?>
<script>
// Função para logar como colaborador
function logarComoColaborador(colaboradorId, nomeColaborador) {
    Swal.fire({
        title: 'Logar como Colaborador',
        html: `
            <p>Você está prestes a fazer login como <strong>${nomeColaborador}</strong>.</p>
            <p class="text-muted">Você poderá voltar ao seu usuário original a qualquer momento.</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirmar Login',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-light'
        },
        preConfirm: () => {
            const formData = new FormData();
            formData.append('colaborador_id', colaboradorId);
            
            return fetch('../api/logar_como_colaborador.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Erro ao fazer login');
                }
                return data;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                text: 'Login realizado com sucesso!',
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            }).then(() => {
                window.location.href = result.value.redirect || 'dashboard.php';
            });
        }
    });
}

function enviarDadosAcesso(colaboradorId) {
    Swal.fire({
        title: 'Enviar Dados de Acesso?',
        text: 'Os dados de acesso (login e senha) serão enviados por email para o colaborador.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, enviar!',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn fw-bold btn-primary',
            cancelButton: 'btn fw-bold btn-light'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostra loading
            Swal.fire({
                title: 'Enviando...',
                text: 'Por favor, aguarde.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Envia requisição
            const formData = new FormData();
            formData.append('colaborador_id', colaboradorId);
            
            fetch('../api/enviar_dados_acesso.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        text: data.message,
                        icon: 'success',
                        buttonsStyling: false,
                        confirmButtonText: 'OK',
                        customClass: {
                            confirmButton: 'btn fw-bold btn-primary'
                        }
                    });
                } else {
                    Swal.fire({
                        text: data.message || 'Erro ao enviar dados de acesso',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'OK',
                        customClass: {
                            confirmButton: 'btn fw-bold btn-primary'
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    text: 'Erro ao conectar com o servidor',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'OK',
                    customClass: {
                        confirmButton: 'btn fw-bold btn-primary'
                    }
                });
            });
        }
    });
}
</script>
<?php endif; ?>
