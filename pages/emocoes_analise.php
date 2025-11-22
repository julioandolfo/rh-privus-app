<?php
/**
 * Análise Completa de Emoções
 * Permite filtrar por período, colaborador, ver médias gerais, por colaborador, setor e cargo
 */

$page_title = 'Análise de Emoções';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/select_colaborador.php';

require_page_permission('emocoes_analise.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$data_fim = $_GET['data_fim'] ?? date('Y-m-t'); // Último dia do mês atual
$colaborador_id_filtro = $_GET['colaborador_id'] ?? null;
$setor_id_filtro = $_GET['setor_id'] ?? null;
$cargo_id_filtro = $_GET['cargo_id'] ?? null;

// Validação de datas
if (!empty($data_inicio) && !empty($data_fim) && strtotime($data_inicio) > strtotime($data_fim)) {
    $data_fim = $data_inicio;
}

// Busca colaboradores para filtro
$colaboradores = get_colaboradores_disponiveis($pdo, $usuario);

// Busca setores
$setores = [];
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome_setor FROM setores ORDER BY nome_setor");
} else {
    $stmt = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id = ? ORDER BY nome_setor");
    $stmt->execute([$usuario['empresa_id']]);
}
$setores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca cargos
$cargos = [];
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome_cargo FROM cargos ORDER BY nome_cargo");
} else {
    $stmt = $pdo->prepare("SELECT id, nome_cargo FROM cargos WHERE empresa_id = ? ORDER BY nome_cargo");
    $stmt->execute([$usuario['empresa_id']]);
}
$cargos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monta query base para buscar emoções
$where_conditions = ["e.data_registro >= ?", "e.data_registro <= ?"];
$params = [$data_inicio, $data_fim];

// Aplica filtros de permissão primeiro
if ($usuario['role'] === 'RH') {
    $where_conditions[] = "(c.empresa_id = ? OR u.empresa_id = ?)";
    $params[] = $usuario['empresa_id'];
    $params[] = $usuario['empresa_id'];
} elseif ($usuario['role'] === 'GESTOR') {
    $where_conditions[] = "(c.setor_id = ? OR u.setor_id = ? OR c.empresa_id = ? OR u.empresa_id = ?)";
    $params[] = $usuario['setor_id'];
    $params[] = $usuario['setor_id'];
    $params[] = $usuario['empresa_id'];
    $params[] = $usuario['empresa_id'];
}

if (!empty($colaborador_id_filtro)) {
    $where_conditions[] = "(e.colaborador_id = ? OR (e.usuario_id IS NOT NULL AND e.usuario_id IN (SELECT id FROM usuarios WHERE colaborador_id = ?)))";
    $params[] = $colaborador_id_filtro;
    $params[] = $colaborador_id_filtro;
}

if (!empty($setor_id_filtro)) {
    $where_conditions[] = "(c.setor_id = ? OR u.setor_id = ?)";
    $params[] = $setor_id_filtro;
    $params[] = $setor_id_filtro;
}

if (!empty($cargo_id_filtro)) {
    $where_conditions[] = "c.cargo_id = ?";
    $params[] = $cargo_id_filtro;
}

$where_sql = implode(' AND ', $where_conditions);

// Busca todas as emoções com filtros
$stmt = $pdo->prepare("
    SELECT e.*,
           u.nome as usuario_nome,
           u.colaborador_id as usuario_colaborador_id,
           c.nome_completo as colaborador_nome,
           c.setor_id,
           c.cargo_id,
           s.nome_setor,
           car.nome_cargo,
           emp.nome_fantasia as empresa_nome
    FROM emocoes e
    LEFT JOIN usuarios u ON e.usuario_id = u.id
    LEFT JOIN colaboradores c ON (e.colaborador_id = c.id OR u.colaborador_id = c.id)
    LEFT JOIN setores s ON (c.setor_id = s.id OR u.setor_id = s.id)
    LEFT JOIN cargos car ON c.cargo_id = car.id
    LEFT JOIN empresas emp ON (c.empresa_id = emp.id OR u.empresa_id = emp.id)
    WHERE $where_sql
    ORDER BY e.data_registro DESC, e.created_at DESC
");
$stmt->execute($params);
$todas_emocoes = $stmt->fetchAll();

// Calcula média geral
$stmt = $pdo->prepare("
    SELECT AVG(e.nivel_emocao) as media_geral, COUNT(*) as total_registros
    FROM emocoes e
    LEFT JOIN usuarios u ON e.usuario_id = u.id
    LEFT JOIN colaboradores c ON (e.colaborador_id = c.id OR u.colaborador_id = c.id)
    WHERE $where_sql
");
$stmt->execute($params);
$media_geral_data = $stmt->fetch(PDO::FETCH_ASSOC);
$media_geral = $media_geral_data['media_geral'] ?? null;
$total_registros = $media_geral_data['total_registros'] ?? 0;

// Média por colaborador
// Usa subquery para evitar problemas com GROUP BY e sql_mode=only_full_group_by
$stmt = $pdo->prepare("
    SELECT 
        pessoa_id,
        nome_pessoa,
        AVG(nivel_emocao) as media_emocao,
        COUNT(*) as total_registros
    FROM (
        SELECT 
            COALESCE(c.id, u.id) as pessoa_id,
            COALESCE(c.nome_completo, u.nome, 'Sem nome') as nome_pessoa,
            e.nivel_emocao
        FROM emocoes e
        LEFT JOIN usuarios u ON e.usuario_id = u.id
        LEFT JOIN colaboradores c ON (e.colaborador_id = c.id OR u.colaborador_id = c.id)
        WHERE $where_sql
    ) as emocoes_com_pessoa
    GROUP BY pessoa_id, nome_pessoa
    ORDER BY media_emocao DESC
    LIMIT 20
");
$stmt->execute($params);
$medias_colaboradores = $stmt->fetchAll();

// Média por setor
$stmt = $pdo->prepare("
    SELECT 
        s.nome_setor,
        s.id as setor_id,
        AVG(e.nivel_emocao) as media_emocao,
        COUNT(*) as total_registros
    FROM emocoes e
    LEFT JOIN usuarios u ON e.usuario_id = u.id
    LEFT JOIN colaboradores c ON (e.colaborador_id = c.id OR u.colaborador_id = c.id)
    LEFT JOIN setores s ON (c.setor_id = s.id OR u.setor_id = s.id)
    WHERE $where_sql AND s.id IS NOT NULL
    GROUP BY s.id, s.nome_setor
    ORDER BY media_emocao DESC
");
$stmt->execute($params);
$medias_setores = $stmt->fetchAll();

// Média por cargo
$stmt = $pdo->prepare("
    SELECT 
        car.nome_cargo,
        car.id as cargo_id,
        AVG(e.nivel_emocao) as media_emocao,
        COUNT(*) as total_registros
    FROM emocoes e
    LEFT JOIN usuarios u ON e.usuario_id = u.id
    LEFT JOIN colaboradores c ON (e.colaborador_id = c.id OR u.colaborador_id = c.id)
    LEFT JOIN cargos car ON c.cargo_id = car.id
    WHERE $where_sql AND car.id IS NOT NULL
    GROUP BY car.id, car.nome_cargo
    ORDER BY media_emocao DESC
");
$stmt->execute($params);
$medias_cargos = $stmt->fetchAll();

// Define emojis e cores
$niveis_emoji = [1 => '😢', 2 => '😔', 3 => '😐', 4 => '🙂', 5 => '😄'];
$nomes_nivel = [1 => 'Muito triste', 2 => 'Triste', 3 => 'Neutro', 4 => 'Feliz', 5 => 'Muito feliz'];

function getCorBadge($nivel) {
    if ($nivel >= 4) return 'success';
    if ($nivel >= 3) return 'info';
    if ($nivel >= 2) return 'warning';
    return 'danger';
}

function getEmojiMedia($media) {
    global $niveis_emoji;
    if ($media === null) return '😐';
    $nivel_arredondado = round($media);
    return $niveis_emoji[$nivel_arredondado] ?? '😐';
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Análise de Emoções</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Análise de Emoções</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <a href="dashboard.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-arrow-left fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Voltar
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Card - Filtros -->
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">Filtros</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="form">
                    <div class="row g-5">
                        <div class="col-md-3">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Colaborador</label>
                            <?= render_select_colaborador('colaborador_id', 'colaborador_id', $colaborador_id_filtro, $colaboradores, false) ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Setor</label>
                            <select name="setor_id" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($setores as $setor): ?>
                                    <option value="<?= $setor['id'] ?>" <?= $setor_id_filtro == $setor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($setor['nome_setor']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cargo</label>
                            <select name="cargo_id" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($cargos as $cargo): ?>
                                    <option value="<?= $cargo['id'] ?>" <?= $cargo_id_filtro == $cargo['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cargo['nome_cargo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="ki-duotone ki-magnifier fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Filtrar
                            </button>
                            <a href="emocoes_analise.php" class="btn btn-light">
                                Limpar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Row - Estatísticas Gerais -->
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col - Média Geral -->
            <div class="col-xl-4">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Média Geral</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Período selecionado</span>
                        </h3>
                    </div>
                    <div class="card-body pt-6">
                        <?php if ($total_registros > 0): ?>
                            <div class="d-flex flex-column align-items-center p-5">
                                <div class="text-center mb-5">
                                    <div class="fs-1 mb-3"><?= getEmojiMedia($media_geral) ?></div>
                                    <div class="fs-2 fw-bold text-gray-800 mb-2">
                                        <?= number_format($media_geral, 2) ?> / 5.0
                                    </div>
                                    <div class="badge badge-<?= getCorBadge($media_geral) ?> fs-6 mb-3">
                                        <?= $total_registros ?> registro(s)
                                    </div>
                                    <div class="progress h-10px w-100" style="max-width: 200px;">
                                        <div class="progress-bar bg-<?= getCorBadge($media_geral) ?>" 
                                             role="progressbar" 
                                             style="width: <?= ($media_geral / 5) * 100 ?>%"
                                             aria-valuenow="<?= $media_geral ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="5">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-10">
                                <p>Nenhuma emoção encontrada no período selecionado.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Média por Setor -->
            <div class="col-xl-4">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Média por Setor</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Top setores</span>
                        </h3>
                    </div>
                    <div class="card-body pt-6">
                        <?php if (!empty($medias_setores)): ?>
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th>Setor</th>
                                            <th class="text-end">Média</th>
                                            <th class="text-end">Registros</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($medias_setores as $setor): ?>
                                        <tr>
                                            <td>
                                                <span class="text-gray-800 fw-bold"><?= htmlspecialchars($setor['nome_setor']) ?></span>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge badge-<?= getCorBadge($setor['media_emocao']) ?> fs-7">
                                                    <?= number_format($setor['media_emocao'], 2) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-gray-600"><?= $setor['total_registros'] ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-10">
                                <p>Nenhum dado disponível.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Média por Cargo -->
            <div class="col-xl-4">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Média por Cargo</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Top cargos</span>
                        </h3>
                    </div>
                    <div class="card-body pt-6">
                        <?php if (!empty($medias_cargos)): ?>
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th>Cargo</th>
                                            <th class="text-end">Média</th>
                                            <th class="text-end">Registros</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($medias_cargos as $cargo): ?>
                                        <tr>
                                            <td>
                                                <span class="text-gray-800 fw-bold"><?= htmlspecialchars($cargo['nome_cargo']) ?></span>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge badge-<?= getCorBadge($cargo['media_emocao']) ?> fs-7">
                                                    <?= number_format($cargo['media_emocao'], 2) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-gray-600"><?= $cargo['total_registros'] ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-10">
                                <p>Nenhum dado disponível.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        
        <!--begin::Card - Média por Colaborador -->
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">Média por Colaborador</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($medias_colaboradores)): ?>
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Colaborador</th>
                                    <th class="text-center">Média</th>
                                    <th class="text-end">Registros</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medias_colaboradores as $colab): ?>
                                <tr>
                                    <td>
                                        <span class="text-gray-800 fw-bold"><?= htmlspecialchars($colab['nome_pessoa']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-<?= getCorBadge($colab['media_emocao']) ?> fs-6">
                                            <?= getEmojiMedia($colab['media_emocao']) ?> <?= number_format($colab['media_emocao'], 2) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-gray-600"><?= $colab['total_registros'] ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-10">
                        <p>Nenhum dado disponível.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Card - Todas as Emoções -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Todas as Emoções Registradas</h3>
                <div class="card-toolbar">
                    <span class="badge badge-light-primary"><?= count($todas_emocoes) ?> registro(s)</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($todas_emocoes)): ?>
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4" id="kt_table_emocoes">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th class="min-w-100px">Data</th>
                                    <th class="min-w-150px">Colaborador/Usuário</th>
                                    <th class="min-w-100px">Setor</th>
                                    <th class="min-w-100px">Cargo</th>
                                    <th class="min-w-80px text-center">Emoção</th>
                                    <th class="min-w-100px text-center">Nível</th>
                                    <th>Descrição</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todas_emocoes as $emocao): 
                                    $data_formatada = date('d/m/Y', strtotime($emocao['data_registro']));
                                    $nivel = $emocao['nivel_emocao'];
                                    $emoji = $niveis_emoji[$nivel] ?? '😐';
                                    $nome_nivel = $nomes_nivel[$nivel] ?? 'Neutro';
                                    $nome_pessoa = $emocao['colaborador_nome'] ?? $emocao['usuario_nome'] ?? 'N/A';
                                ?>
                                <tr>
                                    <td>
                                        <span class="text-gray-800 fw-bold"><?= $data_formatada ?></span>
                                    </td>
                                    <td>
                                        <span class="text-gray-800 fw-bold"><?= htmlspecialchars($nome_pessoa) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-gray-600"><?= htmlspecialchars($emocao['nome_setor'] ?? '-') ?></span>
                                    </td>
                                    <td>
                                        <span class="text-gray-600"><?= htmlspecialchars($emocao['nome_cargo'] ?? '-') ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fs-2"><?= $emoji ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-<?= getCorBadge($nivel) ?> fs-7">
                                            <?= $nome_nivel ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($emocao['descricao'])): ?>
                                            <span class="text-gray-600"><?= htmlspecialchars($emocao['descricao']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Sem descrição</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-10">
                        <p>Nenhuma emoção encontrada com os filtros selecionados.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<script>
// DataTables para a tabela de emoções
var KTEmocoesList = function() {
    var initDatatable = function() {
        const table = document.getElementById('kt_table_emocoes');
        if (!table) return;
        
        const datatable = $(table).DataTable({
            "info": true,
            "order": [[0, "desc"]], // Ordena por data (mais recente primeiro)
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            }
        });
        
        // Filtro de busca
        const filterSearch = document.querySelector('[data-kt-emocoes-table-filter="search"]');
        if (filterSearch) {
            filterSearch.addEventListener('keyup', function(e) {
                datatable.search(e.target.value).draw();
            });
        }
    };
    
    return {
        init: function() {
            initDatatable();
        }
    };
}();

// Inicializa quando jQuery e DataTables estiverem prontos
function waitForDependencies() {
    if (typeof jQuery !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
        KTEmocoesList.init();
    } else {
        setTimeout(waitForDependencies, 100);
    }
}
waitForDependencies();
</script>

<!--begin::Select2 CSS-->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    /* Ajusta a altura do Select2 */
    .select2-container .select2-selection--single {
        height: 44px !important;
        padding: 0.75rem 1rem !important;
        display: flex !important;
        align-items: center !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 44px !important;
        padding-left: 0 !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px !important;
    }
    
    .select2-container .select2-selection--single .select2-selection__rendered {
        display: flex !important;
        align-items: center !important;
    }
</style>
<!--end::Select2 CSS-->

<!--begin::Select Colaborador Script-->
<script src="../assets/js/select-colaborador.js"></script>
<!--end::Select Colaborador Script-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

