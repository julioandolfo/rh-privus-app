<?php
/**
 * Visualizar Flags de Colaboradores
 */

// Ativa exibição de erros temporariamente para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';

require_page_permission('flags_view.php');

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    if (empty($usuario)) {
        throw new Exception("Usuário não autenticado");
    }
} catch (Exception $e) {
    die("Erro ao inicializar: " . $e->getMessage() . "<br>Arquivo: " . $e->getFile() . "<br>Linha: " . $e->getLine());
}

// Filtros
$colaborador_id = $_GET['colaborador_id'] ?? null;
$status = $_GET['status'] ?? 'ativa';
$tipo_flag = $_GET['tipo_flag'] ?? null;

// Verifica e expira flags vencidas antes de buscar
// Nota: Para melhor performance, recomenda-se executar o cron diariamente
// A verificação aqui garante dados atualizados mesmo sem cron configurado
verificar_expiracao_flags();

// Se for colaborador, força filtro para ver apenas suas próprias flags
if (is_colaborador() && !empty($usuario['colaborador_id'])) {
    $colaborador_id = $usuario['colaborador_id'];
}

// Monta query com filtros
$where = [];
$params = [];

// Filtro por colaborador
if ($colaborador_id) {
    $where[] = "f.colaborador_id = ?";
    $params[] = $colaborador_id;
} else {
    // Aplica filtros de acesso baseado no role
    if ($usuario['role'] === 'RH') {
        $where[] = "c.empresa_id = ?";
        $params[] = $usuario['empresa_id'];
    } elseif ($usuario['role'] === 'GESTOR') {
        $stmt_setor = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
        $stmt_setor->execute([$usuario['id']]);
        $user_data = $stmt_setor->fetch();
        $setor_id = $user_data['setor_id'] ?? 0;
        
        $where[] = "c.setor_id = ?";
        $params[] = $setor_id;
    } elseif (is_colaborador() && !empty($usuario['colaborador_id'])) {
        // Colaborador só vê suas próprias flags
        $where[] = "f.colaborador_id = ?";
        $params[] = $usuario['colaborador_id'];
    }
}

// Filtro por status
if ($status && $status !== 'todas') {
    $where[] = "f.status = ?";
    $params[] = $status;
}

// Filtro por tipo de flag
if ($tipo_flag) {
    $where[] = "f.tipo_flag = ?";
    $params[] = $tipo_flag;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Busca flags
$sql = "
    SELECT f.*, 
           c.nome_completo as colaborador_nome,
           c.foto as colaborador_foto,
           o.descricao as ocorrencia_descricao,
           o.data_ocorrencia,
           t.nome as tipo_ocorrencia_nome,
           u.nome as created_by_nome,
           (SELECT COUNT(*) FROM ocorrencias_flags f2 
            WHERE f2.colaborador_id = f.colaborador_id 
            AND f2.status = 'ativa' 
            AND f2.data_validade >= CURDATE()) as total_flags_ativas
    FROM ocorrencias_flags f
    INNER JOIN colaboradores c ON f.colaborador_id = c.id
    INNER JOIN ocorrencias o ON f.ocorrencia_id = o.id
    LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
    LEFT JOIN usuarios u ON f.created_by = u.id
    $where_sql
    ORDER BY f.data_flag DESC, f.data_validade ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $flags = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erro ao buscar flags: " . $e->getMessage());
    $flags = [];
}

// Busca colaboradores para filtro
$sql_colabs = "SELECT id, nome_completo FROM colaboradores WHERE status = 'ativo'";
$params_colabs = [];

if ($usuario['role'] === 'RH') {
    $sql_colabs .= " AND empresa_id = ?";
    $params_colabs[] = $usuario['empresa_id'];
} elseif ($usuario['role'] === 'GESTOR') {
    $stmt_setor = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt_setor->execute([$usuario['id']]);
    $user_data = $stmt_setor->fetch();
    $setor_id = $user_data['setor_id'] ?? 0;
    
    $sql_colabs .= " AND setor_id = ?";
    $params_colabs[] = $setor_id;
}

$sql_colabs .= " ORDER BY nome_completo";

try {
    $stmt_colabs = $pdo->prepare($sql_colabs);
    $stmt_colabs->execute($params_colabs);
    $colaboradores = $stmt_colabs->fetchAll();
} catch (Exception $e) {
    error_log("Erro ao buscar colaboradores: " . $e->getMessage());
    $colaboradores = [];
}

// Estatísticas
$sql_stats = "
    SELECT 
        COUNT(CASE WHEN f.status = 'ativa' THEN 1 END) as total_ativas,
        COUNT(CASE WHEN f.status = 'expirada' THEN 1 END) as total_expiradas,
        COUNT(CASE WHEN f.tipo_flag = 'falta_nao_justificada' THEN 1 END) as faltas_nao_justificadas,
        COUNT(CASE WHEN f.tipo_flag = 'ma_conduta' THEN 1 END) as ma_conduta,
        COUNT(DISTINCT f.colaborador_id) as colaboradores_com_flags
    FROM ocorrencias_flags f
    INNER JOIN colaboradores c ON f.colaborador_id = c.id
    $where_sql
";

try {
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute($params);
    $stats = $stmt_stats->fetch();
    
    // Garante que todas as chaves existem
    $stats = array_merge([
        'total_ativas' => 0,
        'total_expiradas' => 0,
        'faltas_nao_justificadas' => 0,
        'ma_conduta' => 0,
        'colaboradores_com_flags' => 0
    ], $stats ?: []);
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas de flags: " . $e->getMessage());
    $stats = [
        'total_ativas' => 0,
        'total_expiradas' => 0,
        'faltas_nao_justificadas' => 0,
        'ma_conduta' => 0,
        'colaboradores_com_flags' => 0
    ];
}

$page_title = is_colaborador() ? 'Minhas Flags' : 'Flags de Colaboradores';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0"><?= is_colaborador() ? 'Minhas Flags' : 'Flags de Colaboradores' ?></h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Flags</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!-- Estatísticas -->
        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-3">
                <div class="card card-flush h-xl-100">
                    <div class="card-body pt-5">
                        <div class="d-flex flex-stack">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-45px me-3">
                                    <div class="symbol-label bg-light-danger">
                                        <i class="ki-duotone ki-flag fs-2x text-danger">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </div>
                                </div>
                                <div>
                                    <div class="fs-4 fw-bold text-gray-800"><?= $stats['total_ativas'] ?></div>
                                    <div class="fs-7 text-gray-500">Flags Ativas</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card card-flush h-xl-100">
                    <div class="card-body pt-5">
                        <div class="d-flex flex-stack">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-45px me-3">
                                    <div class="symbol-label bg-light-secondary">
                                        <i class="ki-duotone ki-time fs-2x text-secondary">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </div>
                                </div>
                                <div>
                                    <div class="fs-4 fw-bold text-gray-800"><?= $stats['total_expiradas'] ?></div>
                                    <div class="fs-7 text-gray-500">Flags Expiradas</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card card-flush h-xl-100">
                    <div class="card-body pt-5">
                        <div class="d-flex flex-stack">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-45px me-3">
                                    <div class="symbol-label bg-light-warning">
                                        <i class="ki-duotone ki-user fs-2x text-warning">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </div>
                                </div>
                                <div>
                                    <div class="fs-4 fw-bold text-gray-800"><?= $stats['colaboradores_com_flags'] ?></div>
                                    <div class="fs-7 text-gray-500">Colaboradores</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card card-flush h-xl-100">
                    <div class="card-body pt-5">
                        <div class="d-flex flex-stack">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-45px me-3">
                                    <div class="symbol-label bg-light-info">
                                        <i class="ki-duotone ki-information fs-2x text-info">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </div>
                                </div>
                                <div>
                                    <div class="fs-4 fw-bold text-gray-800"><?= $stats['ma_conduta'] ?></div>
                                    <div class="fs-7 text-gray-500">Má Conduta</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <?php if (!is_colaborador()): ?>
        <div class="card mb-5">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Colaborador</label>
                        <select name="colaborador_id" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= $colab['id'] ?>" <?= $colaborador_id == $colab['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($colab['nome_completo']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select form-select-solid">
                            <option value="todas" <?= $status === 'todas' ? 'selected' : '' ?>>Todas</option>
                            <option value="ativa" <?= $status === 'ativa' ? 'selected' : '' ?>>Ativas</option>
                            <option value="expirada" <?= $status === 'expirada' ? 'selected' : '' ?>>Expiradas</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Flag</label>
                        <select name="tipo_flag" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <option value="falta_nao_justificada" <?= $tipo_flag === 'falta_nao_justificada' ? 'selected' : '' ?>>Falta Não Justificada</option>
                            <option value="falta_compromisso_pessoal" <?= $tipo_flag === 'falta_compromisso_pessoal' ? 'selected' : '' ?>>Falta por Compromisso Pessoal</option>
                            <option value="ma_conduta" <?= $tipo_flag === 'ma_conduta' ? 'selected' : '' ?>>Má Conduta</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filtrar</button>
                        <a href="flags_view.php" class="btn btn-light">Limpar</a>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- Informação para colaborador -->
        <div class="alert alert-info d-flex align-items-center p-5 mb-5">
            <i class="ki-duotone ki-information fs-2hx text-info me-4">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-1">Suas Flags</h4>
                <span>Você está visualizando apenas suas próprias flags. Cada flag tem validade de 30 dias corridos a partir da data da ocorrência.</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lista de Flags -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Flags Registradas</h3>
            </div>
            <div class="card-body">
                <?php if (empty($flags)): ?>
                <div class="text-center py-10">
                    <i class="ki-duotone ki-flag fs-3x text-gray-400 mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <p class="text-gray-600">Nenhuma flag encontrada com os filtros selecionados.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th class="min-w-150px">Colaborador</th>
                                <th class="min-w-120px">Tipo</th>
                                <th class="min-w-100px">Data da Flag</th>
                                <th class="min-w-100px">Validade</th>
                                <th class="min-w-100px">Status</th>
                                <th class="min-w-100px">Flags Ativas</th>
                                <th class="min-w-150px">Ocorrência</th>
                                <th class="min-w-100px">Criado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($flags as $flag): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($flag['colaborador_foto']): ?>
                                        <img src="../<?= htmlspecialchars($flag['colaborador_foto']) ?>" class="rounded-circle me-3" width="40" height="40" style="object-fit: cover;" alt="">
                                        <?php else: ?>
                                        <div class="symbol symbol-circle symbol-40px me-3">
                                            <div class="symbol-label bg-light-primary text-primary fw-bold">
                                                <?= strtoupper(substr($flag['colaborador_nome'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="colaborador_view.php?id=<?= $flag['colaborador_id'] ?>" class="text-gray-800 fw-bold text-hover-primary">
                                                <?= htmlspecialchars($flag['colaborador_nome']) ?>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-light-<?= get_cor_badge_flag($flag['tipo_flag']) ?>">
                                        <?= get_label_tipo_flag($flag['tipo_flag']) ?>
                                    </span>
                                </td>
                                <td class="text-gray-800"><?= formatar_data($flag['data_flag']) ?></td>
                                <td class="text-gray-800">
                                    <?= formatar_data($flag['data_validade']) ?>
                                    <?php 
                                    $dias_restantes = (strtotime($flag['data_validade']) - time()) / (60 * 60 * 24);
                                    if ($flag['status'] === 'ativa' && $dias_restantes <= 7 && $dias_restantes > 0):
                                    ?>
                                    <br><small class="text-warning">Expira em <?= ceil($dias_restantes) ?> dias</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-light-<?= $flag['status'] === 'ativa' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($flag['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($flag['status'] === 'ativa'): ?>
                                    <span class="badge badge-<?= $flag['total_flags_ativas'] >= 3 ? 'danger' : ($flag['total_flags_ativas'] >= 2 ? 'warning' : 'info') ?>">
                                        <?= $flag['total_flags_ativas'] ?> ativa(s)
                                    </span>
                                    <?php if ($flag['total_flags_ativas'] >= 3): ?>
                                    <br><small class="text-danger fw-bold">⚠️ Alerta</small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="ocorrencia_view.php?id=<?= $flag['ocorrencia_id'] ?>" class="text-gray-800 text-hover-primary">
                                        <?= htmlspecialchars($flag['tipo_ocorrencia_nome'] ?? 'Ocorrência') ?>
                                    </a>
                                    <br><small class="text-muted"><?= formatar_data($flag['data_ocorrencia']) ?></small>
                                </td>
                                <td class="text-gray-800"><?= htmlspecialchars($flag['created_by_nome'] ?? '-') ?></td>
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
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

