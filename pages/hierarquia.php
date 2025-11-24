<?php
/**
 * Visualização de Hierarquia (Organograma) - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('hierarquia.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$filtro_empresa = $_GET['empresa'] ?? '';
$filtro_setor = $_GET['setor'] ?? '';

// Busca colaboradores com hierarquia
$where = [];
$params = [];

if ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "c.empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    } else {
        // Fallback para compatibilidade
        $where[] = "c.empresa_id = ?";
        $params[] = $usuario['empresa_id'] ?? 0;
    }
}

if ($filtro_empresa && $usuario['role'] === 'ADMIN') {
    $where[] = "c.empresa_id = ?";
    $params[] = $filtro_empresa;
}

if ($filtro_setor) {
    $where[] = "c.setor_id = ?";
    $params[] = $filtro_setor;
}

$where[] = "c.status = 'ativo'";
$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : 'WHERE c.status = \'ativo\'';

$sql = "
    SELECT c.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           car.nome_cargo,
           nh.nome as nivel_nome,
           nh.nivel as nivel_numero,
           nh.codigo as nivel_codigo,
           l.nome_completo as lider_nome
    FROM colaboradores c
    LEFT JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN setores s ON c.setor_id = s.id
    LEFT JOIN cargos car ON c.cargo_id = car.id
    LEFT JOIN niveis_hierarquicos nh ON c.nivel_hierarquico_id = nh.id
    LEFT JOIN colaboradores l ON c.lider_id = l.id
    $where_sql
    ORDER BY nh.nivel ASC, c.nome_completo ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$colaboradores = $stmt->fetchAll();

// Organiza colaboradores por nível hierárquico
$hierarquia = [];
foreach ($colaboradores as $colab) {
    $nivel_num = $colab['nivel_numero'] ?? 999;
    if (!isset($hierarquia[$nivel_num])) {
        $hierarquia[$nivel_num] = [
            'nivel_nome' => $colab['nivel_nome'] ?? 'Sem Nível',
            'nivel_codigo' => $colab['nivel_codigo'] ?? '',
            'colaboradores' => []
        ];
    }
    $hierarquia[$nivel_num]['colaboradores'][] = $colab;
}
ksort($hierarquia);

// Busca empresas para filtro
if ($usuario['role'] === 'ADMIN') {
    $stmt_empresas = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
    $empresas = $stmt_empresas->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt_empresas = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id IN ($placeholders) AND status = 'ativo' ORDER BY nome_fantasia");
        $stmt_empresas->execute($usuario['empresas_ids']);
        $empresas = $stmt_empresas->fetchAll();
    } else {
        // Fallback para compatibilidade
        $stmt_empresas = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? AND status = 'ativo' ORDER BY nome_fantasia");
        $stmt_empresas->execute([$usuario['empresa_id'] ?? 0]);
        $empresas = $stmt_empresas->fetchAll();
    }
} else {
    $empresas = [];
}

// Busca setores para filtro
if ($usuario['role'] === 'ADMIN') {
    $stmt_setores = $pdo->query("SELECT id, nome_setor FROM setores WHERE status = 'ativo' ORDER BY nome_setor");
    $setores = $stmt_setores->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt_setores = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id IN ($placeholders) AND status = 'ativo' ORDER BY nome_setor");
        $stmt_setores->execute($usuario['empresas_ids']);
        $setores = $stmt_setores->fetchAll();
    } else {
        // Fallback para compatibilidade
        $stmt_setores = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_setor");
        $stmt_setores->execute([$usuario['empresa_id'] ?? 0]);
        $setores = $stmt_setores->fetchAll();
    }
} else {
    $setores = [];
}

$page_title = 'Hierarquia';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Hierarquia Organizacional</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Hierarquia</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <a href="niveis_hierarquicos.php" class="btn btn-sm btn-light me-2">
                <i class="ki-duotone ki-setting-2 fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Gerenciar Níveis
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Filtros-->
        <div class="card mb-5 mb-xl-8">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Filtros</span>
                </h3>
            </div>
            <div class="card-body pt-3">
                <form method="GET" class="row g-3">
                    <?php if ($usuario['role'] === 'ADMIN'): ?>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Empresa</label>
                        <select name="empresa" class="form-select form-select-solid">
                            <option value="">Todas</option>
                            <?php foreach ($empresas as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filtro_empresa == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['nome_fantasia']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Setor</label>
                        <select name="setor" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <?php foreach ($setores as $setor): ?>
                            <option value="<?= $setor['id'] ?>" <?= $filtro_setor == $setor['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($setor['nome_setor']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="ki-duotone ki-magnifier fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Filtrar
                        </button>
                        <a href="hierarquia.php" class="btn btn-light">
                            <i class="ki-duotone ki-cross fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Limpar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Filtros-->
        
        <!--begin::Organograma-->
        <?php if (!empty($hierarquia)): ?>
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Organograma</span>
                    <span class="text-muted fw-semibold fs-7">Visualização hierárquica dos colaboradores</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <div class="organograma-container" style="overflow-x: auto;">
                    <?php foreach ($hierarquia as $nivel_num => $nivel_data): ?>
                    <div class="nivel-hierarquico mb-10" data-nivel="<?= $nivel_num ?>">
                        <div class="d-flex align-items-center mb-5">
                            <h4 class="text-gray-800 fw-bold fs-4 me-3">
                                <?= htmlspecialchars($nivel_data['nivel_nome']) ?>
                            </h4>
                            <span class="badge badge-light-primary">Nível <?= $nivel_num ?></span>
                        </div>
                        
                        <div class="row g-4">
                            <?php foreach ($nivel_data['colaboradores'] as $colab): ?>
                            <div class="col-md-3 col-lg-2">
                                <div class="card card-flush h-100 hoverable">
                                    <div class="card-body d-flex flex-column align-items-center text-center p-5">
                                        <div class="symbol symbol-circle symbol-60px mb-3">
                                            <div class="symbol-label fs-2 fw-semibold bg-primary text-white">
                                                <?= strtoupper(substr($colab['nome_completo'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <a href="colaborador_view.php?id=<?= $colab['id'] ?>" class="text-gray-800 text-hover-primary fw-bold fs-6">
                                                <?= htmlspecialchars($colab['nome_completo']) ?>
                                            </a>
                                        </div>
                                        <div class="text-muted fw-semibold fs-7 mb-1">
                                            <?= htmlspecialchars($colab['nome_cargo']) ?>
                                        </div>
                                        <div class="text-muted fw-semibold fs-7">
                                            <?= htmlspecialchars($colab['nome_setor']) ?>
                                        </div>
                                        <?php if ($colab['lider_nome']): ?>
                                        <div class="mt-2 pt-2 border-top border-gray-200">
                                            <span class="text-muted fs-8">Líder:</span>
                                            <div class="text-gray-600 fw-semibold fs-7">
                                                <?= htmlspecialchars($colab['lider_nome']) ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-10">
                <div class="text-muted fw-semibold fs-5">
                    Nenhum colaborador encontrado com hierarquia definida.
                </div>
                <div class="mt-5">
                    <a href="colaboradores.php" class="btn btn-primary">
                        <i class="ki-duotone ki-plus fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Cadastrar Colaboradores
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!--end::Organograma-->
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

