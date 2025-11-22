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

// Busca colaborador
$stmt = $pdo->prepare("
    SELECT c.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           car.nome_cargo,
           nh.nome as nivel_nome,
           nh.codigo as nivel_codigo,
           l.nome_completo as lider_nome
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
                            <a class="nav-link text-active-primary ms-0 me-5 me-md-10 py-5 active" data-bs-toggle="tab" href="#kt_tab_pane_dados">
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

<?php if ($usuario['role'] !== 'COLABORADOR'): ?>
<script>
const colaboradorId = <?= $id ?>;

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
