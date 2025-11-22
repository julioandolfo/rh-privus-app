<?php
/**
 * Visualizar Colaborador - Metronic Theme
 */

$page_title = 'Visualizar Colaborador';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

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
                <div class="card-title">
                    <!--begin::Tabs-->
                    <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold overflow-auto flex-nowrap" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
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
                    </ul>
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
                                        <img src="<?= htmlspecialchars($colaborador['foto']) ?>" class="rounded-circle" width="120" height="120" style="object-fit: cover;" alt="<?= htmlspecialchars($colaborador['nome_completo']) ?>">
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
                                        </div>
                                        
                                        <!-- Liderança -->
                                        <?php if ($colaborador['lider_nome']): ?>
                                        <div class="d-flex align-items-center gap-3 mt-4">
                                            <label class="fw-semibold text-gray-700">Liderança:</label>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if ($colaborador['lider_foto']): ?>
                                                <img src="<?= htmlspecialchars($colaborador['lider_foto']) ?>" class="rounded-circle" width="30" height="30" style="object-fit: cover;" alt="">
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
                    <div class="tab-pane fade show active" id="kt_tab_pane_dados" role="tabpanel">
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
                                ${fb.remetente_foto ? `<img src="${fb.remetente_foto}" class="rounded-circle me-2" width="32" height="32" style="object-fit: cover;">` : ''}
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
