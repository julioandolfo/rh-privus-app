<?php
/**
 * Lista de Ocorrências
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

$page_title = is_colaborador() ? 'Minhas Ocorrências' : 'Ocorrências';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';
require_once __DIR__ . '/../includes/header.php';

require_page_permission('ocorrencias_list.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$filtro_colaborador = $_GET['colaborador'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_tipo_ocorrencia_id = $_GET['tipo_ocorrencia_id'] ?? '';
$filtro_severidade = $_GET['severidade'] ?? '';
$filtro_status_aprovacao = $_GET['status_aprovacao'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';
$filtro_tag = $_GET['tag'] ?? '';
$filtro_apenas_informativa = $_GET['apenas_informativa'] ?? '';

// Monta query com filtros
$where = [];
$params = [];

if ($usuario['role'] === 'COLABORADOR') {
    // Colaborador só vê suas próprias ocorrências
    if (!empty($usuario['colaborador_id'])) {
        $where[] = "o.colaborador_id = ?";
        $params[] = $usuario['colaborador_id'];
    } else {
        // Se não tem colaborador_id, não mostra nada
        $where[] = "1 = 0";
    }
} elseif ($usuario['role'] === 'RH') {
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
} elseif ($usuario['role'] === 'GESTOR') {
    $stmt = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario['id']]);
    $user_data = $stmt->fetch();
    $setor_id = $user_data['setor_id'] ?? 0;
    
    $where[] = "c.setor_id = ?";
    $params[] = $setor_id;
}

if ($filtro_colaborador) {
    $where[] = "o.colaborador_id = ?";
    $params[] = $filtro_colaborador;
}

if ($filtro_tipo) {
    $where[] = "o.tipo = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_tipo_ocorrencia_id) {
    $where[] = "o.tipo_ocorrencia_id = ?";
    $params[] = $filtro_tipo_ocorrencia_id;
}

if ($filtro_severidade) {
    $where[] = "o.severidade = ?";
    $params[] = $filtro_severidade;
}

if ($filtro_status_aprovacao) {
    $where[] = "o.status_aprovacao = ?";
    $params[] = $filtro_status_aprovacao;
}

if ($filtro_data_inicio) {
    $where[] = "o.data_ocorrencia >= ?";
    $params[] = $filtro_data_inicio;
}

if ($filtro_data_fim) {
    $where[] = "o.data_ocorrencia <= ?";
    $params[] = $filtro_data_fim;
}

if ($filtro_tag) {
    $where[] = "JSON_CONTAINS(o.tags, ?)";
    $params[] = json_encode([(int)$filtro_tag]);
}

if ($filtro_apenas_informativa !== '') {
    if ($filtro_apenas_informativa == '1') {
        $where[] = "o.apenas_informativa = 1";
    } else {
        $where[] = "(o.apenas_informativa = 0 OR o.apenas_informativa IS NULL)";
    }
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT o.*, 
           c.nome_completo as colaborador_nome,
           c.foto as colaborador_foto,
           u.nome as usuario_nome,
           t.nome as tipo_ocorrencia_nome,
           t.categoria as tipo_categoria,
           COUNT(DISTINCT a.id) as total_anexos,
           COUNT(DISTINCT com.id) as total_comentarios,
           o.apenas_informativa
    FROM ocorrencias o
    INNER JOIN colaboradores c ON o.colaborador_id = c.id
    LEFT JOIN usuarios u ON o.usuario_id = u.id
    LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
    LEFT JOIN ocorrencias_anexos a ON o.id = a.ocorrencia_id
    LEFT JOIN ocorrencias_comentarios com ON o.id = com.ocorrencia_id
    $where_sql
    GROUP BY o.id
    ORDER BY o.data_ocorrencia DESC, o.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ocorrencias = $stmt->fetchAll();

// Agrupa ocorrências por colaborador e depois por tipo
$ocorrencias_agrupadas = [];
foreach ($ocorrencias as $ocorrencia) {
    $colab_id = $ocorrencia['colaborador_id'];
    $colab_nome = $ocorrencia['colaborador_nome'];
    $colab_foto = $ocorrencia['colaborador_foto'] ?? null;
    
    // Determina o tipo de ocorrência
    $tipo_id = $ocorrencia['tipo_ocorrencia_id'] ?? null;
    $tipo_nome = $ocorrencia['tipo_ocorrencia_nome'] ?? ($tipos_ocorrencias[$ocorrencia['tipo']] ?? $ocorrencia['tipo'] ?? 'Outros');
    $tipo_key = $tipo_id ? 'tipo_' . $tipo_id : 'tipo_' . ($ocorrencia['tipo'] ?? 'outros');
    
    if (!isset($ocorrencias_agrupadas[$colab_id])) {
        // Pega a primeira inicial do nome para avatar padrão
        $inicial = mb_substr($colab_nome, 0, 1, 'UTF-8');
        $inicial = mb_strtoupper($inicial, 'UTF-8');
        
        $ocorrencias_agrupadas[$colab_id] = [
            'nome' => $colab_nome,
            'id' => $colab_id,
            'foto' => $colab_foto,
            'inicial' => $inicial,
            'tipos' => []
        ];
    }
    
    if (!isset($ocorrencias_agrupadas[$colab_id]['tipos'][$tipo_key])) {
        $ocorrencias_agrupadas[$colab_id]['tipos'][$tipo_key] = [
            'nome' => $tipo_nome,
            'categoria' => $ocorrencia['tipo_categoria'] ?? 'outros',
            'ocorrencias' => []
        ];
    }
    
    $ocorrencias_agrupadas[$colab_id]['tipos'][$tipo_key]['ocorrencias'][] = $ocorrencia;
}

// Busca colaboradores para filtro
if ($usuario['role'] === 'ADMIN') {
    $stmt_colab = $pdo->query("SELECT id, nome_completo FROM colaboradores WHERE status = 'ativo' ORDER BY nome_completo");
    $colaboradores = $stmt_colab->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt_colab = $pdo->prepare("SELECT id, nome_completo FROM colaboradores WHERE empresa_id IN ($placeholders) AND status = 'ativo' ORDER BY nome_completo");
        $stmt_colab->execute($usuario['empresas_ids']);
        $colaboradores = $stmt_colab->fetchAll();
    } else {
        // Fallback para compatibilidade
        $stmt_colab = $pdo->prepare("SELECT id, nome_completo FROM colaboradores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_completo");
        $stmt_colab->execute([$usuario['empresa_id'] ?? 0]);
        $colaboradores = $stmt_colab->fetchAll();
    }
} elseif ($usuario['role'] === 'GESTOR') {
    if (!isset($setor_id)) {
        $stmt_setor = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
        $stmt_setor->execute([$usuario['id']]);
        $user_data = $stmt_setor->fetch();
        $setor_id = $user_data['setor_id'] ?? 0;
    }
    $stmt_colab = $pdo->prepare("SELECT id, nome_completo FROM colaboradores WHERE setor_id = ? AND status = 'ativo' ORDER BY nome_completo");
    $stmt_colab->execute([$setor_id]);
    $colaboradores = $stmt_colab->fetchAll();
} else {
    $colaboradores = [];
}

// Busca tipos de ocorrências do banco
try {
    $stmt_tipos = $pdo->query("SELECT id, nome, categoria FROM tipos_ocorrencias WHERE status = 'ativo' ORDER BY categoria, nome");
    $tipos_ocorrencias_db = $stmt_tipos->fetchAll();
} catch (PDOException $e) {
    $tipos_ocorrencias_db = [];
}

// Busca tags para filtro
$tags_disponiveis = get_tags_ocorrencias();

$tipos_ocorrencias = [
    'atraso' => 'Atraso',
    'falta' => 'Falta',
    'ausência injustificada' => 'Ausência Injustificada',
    'falha operacional' => 'Falha Operacional',
    'desempenho baixo' => 'Desempenho Baixo',
    'comportamento inadequado' => 'Comportamento Inadequado',
    'advertência' => 'Advertência',
    'elogio' => 'Elogio'
];
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0"><?= is_colaborador() ? 'Minhas Ocorrências' : 'Ocorrências' ?></h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900"><?= is_colaborador() ? 'Minhas Ocorrências' : 'Ocorrências' ?></li>
            </ul>
        </div>
        <?php if ($usuario['role'] !== 'COLABORADOR' && can_access_page('ocorrencias_add.php')): ?>
        <div class="d-flex align-items-center py-2">
            <a href="ocorrencias_add.php" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Ocorrência
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!-- Cards de Estatísticas -->
        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-3">
                <div class="card card-flush h-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center mb-2">
                            <span class="fs-2hx fw-bold text-gray-800 me-2 lh-1">
                                <?= count($ocorrencias) ?>
                            </span>
                        </div>
                        <span class="fs-6 fw-semibold text-gray-500">Total de Ocorrências</span>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3">
                <div class="card card-flush h-100 bg-warning">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center mb-2">
                            <span class="fs-2hx fw-bold text-white me-2 lh-1">
                                <?= count(array_filter($ocorrencias, fn($o) => ($o['status_aprovacao'] ?? 'aprovada') === 'pendente')) ?>
                            </span>
                        </div>
                        <span class="fs-6 fw-semibold text-white">Pendentes de Aprovação</span>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3">
                <div class="card card-flush h-100 bg-danger">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center mb-2">
                            <span class="fs-2hx fw-bold text-white me-2 lh-1">
                                <?= count(array_filter($ocorrencias, fn($o) => in_array($o['severidade'] ?? '', ['grave', 'critica']))) ?>
                            </span>
                        </div>
                        <span class="fs-6 fw-semibold text-white">Graves/Críticas</span>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3">
                <div class="card card-flush h-100 bg-success">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center mb-2">
                            <span class="fs-2hx fw-bold text-white me-2 lh-1">
                                <?= count(array_unique(array_column($ocorrencias, 'colaborador_id'))) ?>
                            </span>
                        </div>
                        <span class="fs-6 fw-semibold text-white">Colaboradores</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Filtros</span>
                </h3>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-sm btn-icon btn-active-light-primary" data-bs-toggle="collapse" data-bs-target="#kt_filtros_collapse">
                        <i class="ki-duotone ki-down fs-2"></i>
                    </button>
                </div>
            </div>
            <div class="collapse show" id="kt_filtros_collapse">
                <div class="card-body">
                    <form method="GET" class="row g-3" id="form_filtros">
                    <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Colaborador</label>
                        <select name="colaborador" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= $colab['id'] ?>" <?= $filtro_colaborador == $colab['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($colab['nome_completo']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Ocorrência</label>
                        <select name="tipo_ocorrencia_id" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <?php 
                            $categoria_atual = '';
                            foreach ($tipos_ocorrencias_db as $tipo_db): 
                                if ($categoria_atual !== $tipo_db['categoria']):
                                    if ($categoria_atual !== '') echo '</optgroup>';
                                    $categoria_atual = $tipo_db['categoria'];
                                    $categoria_labels = [
                                        'pontualidade' => 'Pontualidade',
                                        'comportamento' => 'Comportamento',
                                        'desempenho' => 'Desempenho',
                                        'outros' => 'Outros'
                                    ];
                                    echo '<optgroup label="' . htmlspecialchars($categoria_labels[$categoria_atual] ?? ucfirst($categoria_atual)) . '">';
                                endif;
                            ?>
                                <option value="<?= $tipo_db['id'] ?>" <?= $filtro_tipo_ocorrencia_id == $tipo_db['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo_db['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($categoria_atual !== '') echo '</optgroup>'; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Severidade</label>
                        <select name="severidade" class="form-select form-select-solid">
                            <option value="">Todas</option>
                            <option value="leve" <?= $filtro_severidade === 'leve' ? 'selected' : '' ?>>Leve</option>
                            <option value="moderada" <?= $filtro_severidade === 'moderada' ? 'selected' : '' ?>>Moderada</option>
                            <option value="grave" <?= $filtro_severidade === 'grave' ? 'selected' : '' ?>>Grave</option>
                            <option value="critica" <?= $filtro_severidade === 'critica' ? 'selected' : '' ?>>Crítica</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Status Aprovação</label>
                        <select name="status_aprovacao" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <option value="pendente" <?= $filtro_status_aprovacao === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="aprovada" <?= $filtro_status_aprovacao === 'aprovada' ? 'selected' : '' ?>>Aprovada</option>
                            <option value="rejeitada" <?= $filtro_status_aprovacao === 'rejeitada' ? 'selected' : '' ?>>Rejeitada</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Tag</label>
                        <select name="tag" class="form-select form-select-solid">
                            <option value="">Todas</option>
                            <?php foreach ($tags_disponiveis as $tag): ?>
                            <option value="<?= $tag['id'] ?>" <?= $filtro_tag == $tag['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tag['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Tipo</label>
                        <select name="apenas_informativa" class="form-select form-select-solid">
                            <option value="">Todas</option>
                            <option value="1" <?= $filtro_apenas_informativa === '1' ? 'selected' : '' ?>>Apenas Informativas</option>
                            <option value="0" <?= $filtro_apenas_informativa === '0' ? 'selected' : '' ?>>Com Impacto</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Data Início</label>
                        <input type="date" name="data_inicio" class="form-control form-control-solid" value="<?= $filtro_data_inicio ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Data Fim</label>
                        <input type="date" name="data_fim" class="form-control form-control-solid" value="<?= $filtro_data_fim ?>">
                    </div>
                    
                    <div class="col-md-12 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ki-duotone ki-magnifier fs-2"></i>
                            Filtrar
                        </button>
                        <a href="ocorrencias_list.php" class="btn btn-light">
                            <i class="ki-duotone ki-cross fs-2"></i>
                            Limpar
                        </a>
                    </div>
                </form>
                </div>
            </div>
        </div>
    
        <!-- Cards de Colaboradores com Ocorrências -->
        <div class="row g-5 mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Ocorrências por Colaborador</span>
                            <span class="text-muted mt-1 fw-semibold fs-7"><?= count($ocorrencias) ?> ocorrência(s) em <?= count($ocorrencias_agrupadas) ?> colaborador(es)</span>
                        </h3>
                    </div>
                    <div class="card-body py-4">
                        <?php if (empty($ocorrencias_agrupadas)): ?>
                        <div class="text-center py-10">
                            <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <p class="text-muted fs-4">Nenhuma ocorrência encontrada com os filtros aplicados.</p>
                        </div>
                        <?php else: ?>
                        
                        <!-- Cards dos Colaboradores -->
                        <div class="row g-5">
                            <?php foreach ($ocorrencias_agrupadas as $colab_id => $colaborador): ?>
                            <div class="col-12">
                                <div class="card card-colaborador shadow-sm hover-elevate-up">
                                    <!-- Header do Card do Colaborador -->
                                    <div class="card-header border-0 bg-light-primary py-5">
                                        <div class="d-flex align-items-center">
                                            <!-- Avatar do Colaborador -->
                                            <div class="symbol symbol-circle symbol-60px me-4">
                                                <?php if ($colaborador['foto']): ?>
                                                <img src="../<?= htmlspecialchars($colaborador['foto']) ?>" 
                                                     alt="<?= htmlspecialchars($colaborador['nome']) ?>" 
                                                     style="object-fit: cover;"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="symbol-label bg-primary text-white fs-2 fw-bold" style="display:none;">
                                                    <?= htmlspecialchars($colaborador['inicial']) ?>
                                                </div>
                                                <?php else: ?>
                                                <div class="symbol-label bg-primary text-white fs-2 fw-bold">
                                                    <?= htmlspecialchars($colaborador['inicial']) ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Informações do Colaborador -->
                                            <div class="flex-grow-1">
                                                <a href="colaborador_view.php?id=<?= $colab_id ?>" class="text-gray-900 text-hover-primary fs-3 fw-bold mb-1">
                                                    <?= htmlspecialchars($colaborador['nome']) ?>
                                                </a>
                                                <div class="text-muted fw-semibold fs-6">
                                                    <?php
                                                    $total_ocorrencias_colab = 0;
                                                    foreach ($colaborador['tipos'] as $tipo) {
                                                        $total_ocorrencias_colab += count($tipo['ocorrencias']);
                                                    }
                                                    ?>
                                                    <span class="badge badge-primary badge-lg">
                                                        <?= $total_ocorrencias_colab ?> ocorrência<?= $total_ocorrencias_colab > 1 ? 's' : '' ?>
                                                    </span>
                                                    <span class="ms-3">
                                                        <?= count($colaborador['tipos']) ?> tipo<?= count($colaborador['tipos']) > 1 ? 's' : '' ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Body do Card com Tabs -->
                                    <div class="card-body">
                                        <!-- Nav Tabs -->
                                        <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x mb-5 fs-6" role="tablist">
                                            <?php 
                                            $tab_index = 0;
                                            foreach ($colaborador['tipos'] as $tipo_key => $tipo_info): 
                                                $tab_index++;
                                                $tab_id = 'tab_' . $colab_id . '_' . $tipo_key;
                                                $active_class = $tab_index === 1 ? 'active' : '';
                                                
                                                // Define cor do badge baseado na categoria
                                                $categoria_colors = [
                                                    'pontualidade' => 'warning',
                                                    'comportamento' => 'danger',
                                                    'desempenho' => 'primary',
                                                    'outros' => 'secondary'
                                                ];
                                                $badge_color = $categoria_colors[$tipo_info['categoria']] ?? 'secondary';
                                            ?>
                                            <li class="nav-item">
                                                <a class="nav-link <?= $active_class ?>" data-bs-toggle="tab" href="#<?= $tab_id ?>">
                                                    <span class="badge badge-<?= $badge_color ?> me-2"><?= count($tipo_info['ocorrencias']) ?></span>
                                                    <?= htmlspecialchars($tipo_info['nome']) ?>
                                                </a>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        
                                        <!-- Tab Content -->
                                        <div class="tab-content" id="tabs_<?= $colab_id ?>">
                                            <?php 
                                            $tab_index = 0;
                                            foreach ($colaborador['tipos'] as $tipo_key => $tipo_info): 
                                                $tab_index++;
                                                $tab_id = 'tab_' . $colab_id . '_' . $tipo_key;
                                                $active_class = $tab_index === 1 ? 'show active' : '';
                                            ?>
                                            <div class="tab-pane fade <?= $active_class ?>" id="<?= $tab_id ?>" role="tabpanel">
                                                <div class="table-responsive">
                                                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-3 gy-3">
                                                        <thead>
                                                            <tr class="fw-bold text-muted">
                                                                <th class="min-w-100px">Data</th>
                                                                <th class="min-w-80px">Severidade</th>
                                                                <th class="min-w-80px">Status</th>
                                                                <th class="min-w-300px">Descrição</th>
                                                                <th class="min-w-80px text-center">Anexos</th>
                                                                <th class="min-w-80px text-center">Comentários</th>
                                                                <th class="min-w-120px">Registrado por</th>
                                                                <th class="text-end min-w-100px">Ações</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($tipo_info['ocorrencias'] as $ocorrencia): ?>
                                                            <tr>
                                                                <!-- Data -->
                                                                <td>
                                                                    <span class="fw-bold text-gray-800"><?= formatar_data($ocorrencia['data_ocorrencia']) ?></span>
                                                                </td>
                                                                
                                                                <!-- Severidade -->
                                                                <td>
                                                                    <?php
                                                                    $severidade = $ocorrencia['severidade'] ?? 'moderada';
                                                                    $severidade_labels = [
                                                                        'leve' => 'Leve',
                                                                        'moderada' => 'Moderada',
                                                                        'grave' => 'Grave',
                                                                        'critica' => 'Crítica'
                                                                    ];
                                                                    $severidade_colors = [
                                                                        'leve' => 'badge-light-success',
                                                                        'moderada' => 'badge-light-info',
                                                                        'grave' => 'badge-light-warning',
                                                                        'critica' => 'badge-light-danger'
                                                                    ];
                                                                    ?>
                                                                    <span class="badge <?= $severidade_colors[$severidade] ?? 'badge-light-info' ?>">
                                                                        <?= $severidade_labels[$severidade] ?? 'Moderada' ?>
                                                                    </span>
                                                                </td>
                                                                
                                                                <!-- Status -->
                                                                <td>
                                                                    <?php
                                                                    $status_aprovacao = $ocorrencia['status_aprovacao'] ?? 'aprovada';
                                                                    $status_colors = [
                                                                        'pendente' => 'badge-warning',
                                                                        'aprovada' => 'badge-success',
                                                                        'rejeitada' => 'badge-danger'
                                                                    ];
                                                                    $status_labels = [
                                                                        'pendente' => 'Pendente',
                                                                        'aprovada' => 'Aprovada',
                                                                        'rejeitada' => 'Rejeitada'
                                                                    ];
                                                                    ?>
                                                                    <span class="badge <?= $status_colors[$status_aprovacao] ?? 'badge-success' ?>">
                                                                        <?= $status_labels[$status_aprovacao] ?? 'Aprovada' ?>
                                                                    </span>
                                                                </td>
                                                                
                                                                <!-- Descrição -->
                                                                <td>
                                                                    <?php
                                                                    $descricao = $ocorrencia['descricao'] ?? '';
                                                                    $descricao_curta = mb_substr($descricao, 0, 100);
                                                                    ?>
                                                                    <div class="text-gray-700">
                                                                        <?= htmlspecialchars($descricao_curta) ?>
                                                                        <?= mb_strlen($descricao) > 100 ? '...' : '' ?>
                                                                    </div>
                                                                    <?php if (!empty($ocorrencia['apenas_informativa']) && $ocorrencia['apenas_informativa'] == 1): ?>
                                                                    <span class="badge badge-light-success mt-1" title="Apenas Informativa">
                                                                        <i class="ki-duotone ki-information-5 fs-6"></i> Informativa
                                                                    </span>
                                                                    <?php endif; ?>
                                                                    <?php
                                                                    // Mostra tags
                                                                    if (!empty($ocorrencia['tags'])) {
                                                                        $tags_array = json_decode($ocorrencia['tags'], true);
                                                                        if ($tags_array) {
                                                                            echo '<div class="mt-1">';
                                                                            foreach ($tags_array as $tag_id) {
                                                                                foreach ($tags_disponiveis as $tag) {
                                                                                    if ($tag['id'] == $tag_id) {
                                                                                        echo '<span class="badge badge-sm me-1" style="background-color: ' . htmlspecialchars($tag['cor']) . '20; color: ' . htmlspecialchars($tag['cor']) . '; font-size: 0.75rem;">' . htmlspecialchars($tag['nome']) . '</span>';
                                                                                        break;
                                                                                    }
                                                                                }
                                                                            }
                                                                            echo '</div>';
                                                                        }
                                                                    }
                                                                    ?>
                                                                </td>
                                                                
                                                                <!-- Anexos -->
                                                                <td class="text-center">
                                                                    <?php if ($ocorrencia['total_anexos'] > 0): ?>
                                                                        <span class="badge badge-light-info">
                                                                            <i class="ki-duotone ki-file fs-5"></i>
                                                                            <?= $ocorrencia['total_anexos'] ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                
                                                                <!-- Comentários -->
                                                                <td class="text-center">
                                                                    <?php if ($ocorrencia['total_comentarios'] > 0): ?>
                                                                        <span class="badge badge-light-primary">
                                                                            <i class="ki-duotone ki-message-text-2 fs-5"></i>
                                                                            <?= $ocorrencia['total_comentarios'] ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                
                                                                <!-- Registrado por -->
                                                                <td>
                                                                    <span class="text-gray-600 fw-semibold">
                                                                        <?= htmlspecialchars($ocorrencia['usuario_nome'] ?? 'N/A') ?>
                                                                    </span>
                                                                </td>
                                                                
                                                                <!-- Ações -->
                                                                <td class="text-end">
                                                                    <a href="#" class="btn btn-light btn-active-light-primary btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                                        Ações
                                                                        <i class="ki-duotone ki-down fs-5 ms-1"></i>
                                                                    </a>
                                                                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-150px py-4" data-kt-menu="true">
                                                                        <div class="menu-item px-3">
                                                                            <a href="ocorrencia_view.php?id=<?= $ocorrencia['id'] ?>" class="menu-link px-3">
                                                                                <i class="ki-duotone ki-eye fs-5 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                                                Ver Detalhes
                                                                            </a>
                                                                        </div>
                                                                        <?php if (has_role(['ADMIN', 'RH'])): ?>
                                                                        <div class="menu-item px-3">
                                                                            <a href="ocorrencias_edit.php?id=<?= $ocorrencia['id'] ?>" class="menu-link px-3">
                                                                                <i class="ki-duotone ki-pencil fs-5 me-2"><span class="path1"></span><span class="path2"></span></i>
                                                                                Editar
                                                                            </a>
                                                                        </div>
                                                                        <div class="menu-item px-3">
                                                                            <a href="#" onclick="deletarOcorrencia(<?= $ocorrencia['id'] ?>); return false;" class="menu-link px-3 text-danger">
                                                                                <i class="ki-duotone ki-trash fs-5 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                                                                                Deletar
                                                                            </a>
                                                                        </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
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
        
    </div>
</div>
<!--end::Post-->

<script>
"use strict";

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
                        title: 'Deletado!',
                        text: 'A ocorrência foi deletada com sucesso.',
                        icon: 'success',
                        buttonsStyling: false,
                        confirmButtonText: 'Ok',
                        customClass: {
                            confirmButton: 'btn fw-bold btn-primary'
                        }
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Erro!',
                        text: data.message || 'Erro ao deletar ocorrência.',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'Ok',
                        customClass: {
                            confirmButton: 'btn fw-bold btn-primary'
                        }
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Erro!',
                    text: 'Erro ao deletar ocorrência: ' + error.message,
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn fw-bold btn-primary'
                    }
                });
            });
        }
    });
}

// Inicializa componentes do Metronic
document.addEventListener('DOMContentLoaded', function() {
    // Reinicializa menus do KTMenu
    KTMenu.createInstances();
    
    // Adiciona animação de hover aos cards
    const cards = document.querySelectorAll('.card-colaborador');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'all 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<style>
/* =====================================================
   ESTILOS ESPECÍFICOS PARA PÁGINA DE OCORRÊNCIAS
   Todos os estilos têm escopo limitado para não afetar 
   o resto do sistema
   ===================================================== */

/* Cards de Colaboradores */
.card-colaborador {
    border: 1px solid #e4e6ef;
    border-radius: 0.625rem;
    overflow: hidden;
    transition: all 0.3s ease;
}

.card-colaborador:hover {
    box-shadow: 0 0 30px rgba(0,0,0,0.1);
}

[data-theme="dark"] .card-colaborador {
    border-color: #2b2b40;
}

[data-theme="dark"] .card-colaborador:hover {
    box-shadow: 0 0 30px rgba(0,0,0,0.4);
}

.card-colaborador .card-header {
    border-bottom: 1px solid #e4e6ef;
}

[data-theme="dark"] .card-colaborador .card-header {
    background-color: #1e1e2d !important;
    border-bottom-color: #2b2b40;
}

/* Badge maior apenas dentro dos cards de colaborador */
.card-colaborador .badge-lg {
    padding: 0.65rem 1rem;
    font-size: 0.95rem;
}

/* Hover nos cards de estatísticas desta página */
.card-flush:hover {
    transform: translateY(-5px);
    transition: all 0.3s ease;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

[data-theme="dark"] .card-flush:hover {
    box-shadow: 0 0 20px rgba(0,0,0,0.4);
}

/* Animação hover apenas para cards de colaborador */
.hover-elevate-up {
    transition: all 0.3s ease;
}

.hover-elevate-up:hover {
    transform: translateY(-5px);
}

/* Tabelas dentro dos cards de colaborador */
.card-colaborador .table tbody tr:hover {
    background-color: #f9f9f9;
}

[data-theme="dark"] .card-colaborador .table tbody tr:hover {
    background-color: #1e1e2d;
}

/* Responsividade */
@media (max-width: 768px) {
    .card-colaborador .symbol-circle {
        width: 40px;
        height: 40px;
    }
    
    .card-colaborador .table {
        font-size: 0.85rem;
    }
}

@media (max-width: 576px) {
    .card-colaborador .nav-line-tabs {
        flex-wrap: wrap;
    }
    
    .card-colaborador .nav-line-tabs .nav-link {
        margin-bottom: 0.5rem;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

