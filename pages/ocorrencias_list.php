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
    
        <!-- Tabela Principal de Ocorrências -->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Lista de Ocorrências</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($ocorrencias) ?> ocorrência(s) encontrada(s)</span>
                </h3>
                <div class="card-toolbar">
                    <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                        <!-- Botão de Exportar -->
                        <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                            <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                            Exportar
                        </button>
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-200px py-4" data-kt-menu="true">
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3" onclick="exportarTabela('excel'); return false;">
                                    <i class="ki-duotone ki-file fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                                    Excel
                                </a>
                            </div>
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3" onclick="exportarTabela('pdf'); return false;">
                                    <i class="ki-duotone ki-file fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                                    PDF
                                </a>
                            </div>
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3" onclick="exportarTabela('csv'); return false;">
                                    <i class="ki-duotone ki-file fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                                    CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body py-4">
                <?php if (empty($ocorrencias)): ?>
                <div class="text-center py-10">
                    <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <p class="text-muted fs-4">Nenhuma ocorrência encontrada com os filtros aplicados.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table id="kt_table_ocorrencias" class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-150px">Colaborador</th>
                                <th class="min-w-100px">Tipo</th>
                                <th class="min-w-90px">Data</th>
                                <th class="min-w-80px">Severidade</th>
                                <th class="min-w-80px">Status</th>
                                <th class="min-w-250px">Descrição</th>
                                <th class="min-w-60px text-center">Anexos</th>
                                <th class="min-w-80px text-center">Comentários</th>
                                <th class="min-w-120px">Registrado por</th>
                                <th class="text-end min-w-100px">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php foreach ($ocorrencias as $ocorrencia): ?>
                            <tr data-colaborador-id="<?= $ocorrencia['colaborador_id'] ?>">
                                <!-- Colaborador com Avatar -->
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-circle symbol-40px me-3">
                                            <?php if ($ocorrencia['colaborador_foto']): ?>
                                            <img src="../<?= htmlspecialchars($ocorrencia['colaborador_foto']) ?>" 
                                                 alt="<?= htmlspecialchars($ocorrencia['colaborador_nome']) ?>" 
                                                 style="object-fit: cover;"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="symbol-label bg-light-primary text-primary fw-bold" style="display:none;">
                                                <?= mb_substr($ocorrencia['colaborador_nome'], 0, 1) ?>
                                            </div>
                                            <?php else: ?>
                                            <div class="symbol-label bg-light-primary text-primary fw-bold">
                                                <?= mb_substr($ocorrencia['colaborador_nome'], 0, 1) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <a href="colaborador_view.php?id=<?= $ocorrencia['colaborador_id'] ?>" class="text-gray-800 text-hover-primary mb-1 fw-bold">
                                                <?= htmlspecialchars($ocorrencia['colaborador_nome']) ?>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Tipo de Ocorrência -->
                                <td>
                                    <?php
                                    $categoria = $ocorrencia['tipo_categoria'] ?? 'outros';
                                    $categoria_colors = [
                                        'pontualidade' => 'badge-light-warning',
                                        'comportamento' => 'badge-light-danger',
                                        'desempenho' => 'badge-light-primary',
                                        'outros' => 'badge-light-secondary'
                                    ];
                                    $tipo_nome = $ocorrencia['tipo_ocorrencia_nome'] ?? 'N/A';
                                    ?>
                                    <span class="badge <?= $categoria_colors[$categoria] ?? 'badge-light-secondary' ?>">
                                        <?= htmlspecialchars($tipo_nome) ?>
                                    </span>
                                    <?php if (!empty($ocorrencia['apenas_informativa']) && $ocorrencia['apenas_informativa'] == 1): ?>
                                    <br><span class="badge badge-light-success mt-1" title="Apenas Informativa">
                                        <i class="ki-duotone ki-information-5 fs-6"></i> Info
                                    </span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Data -->
                                <td>
                                    <span class="fw-bold"><?= formatar_data($ocorrencia['data_ocorrencia']) ?></span>
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
                                    $descricao_curta = mb_substr($descricao, 0, 80);
                                    ?>
                                    <span class="text-gray-700" title="<?= htmlspecialchars($descricao) ?>">
                                        <?= htmlspecialchars($descricao_curta) ?>
                                        <?= mb_strlen($descricao) > 80 ? '...' : '' ?>
                                    </span>
                                    <?php
                                    // Mostra tags
                                    if (!empty($ocorrencia['tags'])) {
                                        $tags_array = json_decode($ocorrencia['tags'], true);
                                        if ($tags_array) {
                                            echo '<div class="mt-1">';
                                            foreach ($tags_array as $tag_id) {
                                                foreach ($tags_disponiveis as $tag) {
                                                    if ($tag['id'] == $tag_id) {
                                                        echo '<span class="badge badge-sm" style="background-color: ' . htmlspecialchars($tag['cor']) . '20; color: ' . htmlspecialchars($tag['cor']) . '; font-size: 0.75rem;">' . htmlspecialchars($tag['nome']) . '</span> ';
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
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<script>
"use strict";

var KTOcorrenciasList = (function() {
    var table;
    var datatable;
    
    return {
        init: function() {
            table = document.querySelector('#kt_table_ocorrencias');
            
            if (!table) {
                return;
            }
            
            // Inicializa DataTable
            datatable = $(table).DataTable({
                info: true,
                order: [[2, 'desc']], // Ordena por data (coluna 2) decrescente
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                },
                columnDefs: [
                    { orderable: false, targets: 9 }, // Coluna de ações não ordenável
                    { width: '150px', targets: 0 }, // Colaborador
                    { width: '100px', targets: 1 }, // Tipo
                    { width: '90px', targets: 2 }, // Data
                    { width: '80px', targets: 3 }, // Severidade
                    { width: '80px', targets: 4 }, // Status
                    { width: '250px', targets: 5 }, // Descrição
                    { width: '60px', targets: 6, className: 'text-center' }, // Anexos
                    { width: '80px', targets: 7, className: 'text-center' }, // Comentários
                    { width: '120px', targets: 8 }, // Registrado por
                    { width: '100px', targets: 9, className: 'text-end' } // Ações
                ],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                buttons: [
                    {
                        extend: 'excel',
                        text: 'Excel',
                        className: 'btn btn-light-success'
                    },
                    {
                        extend: 'pdf',
                        text: 'PDF',
                        className: 'btn btn-light-danger'
                    },
                    {
                        extend: 'csv',
                        text: 'CSV',
                        className: 'btn btn-light-info'
                    }
                ],
                rowGroup: {
                    dataSrc: 0, // Agrupa pela coluna 0 (Colaborador)
                    startRender: function(rows, group) {
                        var totalOcorrencias = rows.count();
                        
                        // Detecta o tema
                        var isDark = document.documentElement.getAttribute('data-theme') === 'dark' || 
                                     document.body.getAttribute('data-theme') === 'dark';
                        
                        var bgColor = isDark ? '#1e1e2d' : '#f3f6f9';
                        var textColor = isDark ? '#ffffff' : '#181c32';
                        var borderColor = isDark ? '#2b2b40' : '#e4e6ef';
                        
                        return $('<tr/>')
                            .addClass('group-row')
                            .append('<td colspan="10" class="fw-bold" style="padding: 15px; background-color: ' + bgColor + ' !important; color: ' + textColor + ' !important; border-top: 2px solid #3699ff; border-bottom: 1px solid ' + borderColor + ';">' +
                                    '<i class="ki-duotone ki-user fs-2 me-2" style="color: ' + textColor + ';"><span class="path1"></span><span class="path2"></span></i>' +
                                    '<span style="color: ' + textColor + ';">' + group + '</span>' +
                                    ' <span class="badge badge-primary ms-3">' + totalOcorrencias + ' ocorrência' + (totalOcorrencias > 1 ? 's' : '') + '</span>' +
                                    '</td>');
                    }
                },
                drawCallback: function() {
                    // Reinicializa menus do Metronic após cada redesenho
                    KTMenu.createInstances();
                    
                    // Força cores corretas nas linhas de agrupamento após cada redesenho
                    var isDark = document.documentElement.getAttribute('data-theme') === 'dark' || 
                                 document.body.getAttribute('data-theme') === 'dark';
                    
                    if (isDark) {
                        $('.group-row td').css({
                            'background-color': '#1e1e2d',
                            'color': '#ffffff'
                        });
                    } else {
                        $('.group-row td').css({
                            'background-color': '#f3f6f9',
                            'color': '#181c32'
                        });
                    }
                }
            });
        },
        
        getDatatable: function() {
            return datatable;
        }
    };
})();

// Função para exportar tabela
function exportarTabela(formato) {
    var dt = KTOcorrenciasList.getDatatable();
    
    if (!dt) {
        Swal.fire({
            title: 'Erro!',
            text: 'Tabela não inicializada',
            icon: 'error',
            confirmButtonText: 'Ok',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn fw-bold btn-primary'
            }
        });
        return;
    }
    
    // Carrega bibliotecas de exportação se necessário
    if (formato === 'excel') {
        // Verifica se JSZip e Excel estão carregados
        if (typeof JSZip === 'undefined') {
            $.getScript('https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js', function() {
                $.getScript('https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js', function() {
                    dt.button('.buttons-excel').trigger();
                });
            });
        } else {
            dt.button('.buttons-excel').trigger();
        }
    } else if (formato === 'pdf') {
        // Verifica se pdfMake está carregado
        if (typeof pdfMake === 'undefined') {
            $.getScript('https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js', function() {
                $.getScript('https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js', function() {
                    $.getScript('https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js', function() {
                        dt.button('.buttons-pdf').trigger();
                    });
                });
            });
        } else {
            dt.button('.buttons-pdf').trigger();
        }
    } else if (formato === 'csv') {
        dt.button('.buttons-csv').trigger();
    }
}

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

// Aguarda jQuery e DataTables estarem disponíveis
(function waitForDependencies() {
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
        setTimeout(waitForDependencies, 50);
        return;
    }
    
    $(document).ready(function() {
        // Aguarda um pouco para garantir que a tabela foi renderizada
        setTimeout(function() {
            KTOcorrenciasList.init();
            
            // Observer para mudanças de tema
            if (typeof MutationObserver !== 'undefined') {
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'data-theme') {
                            var dt = KTOcorrenciasList.getDatatable();
                            if (dt) {
                                dt.draw(false);
                            }
                        }
                    });
                });
                
                // Observa mudanças no atributo data-theme
                observer.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['data-theme']
                });
                
                observer.observe(document.body, {
                    attributes: true,
                    attributeFilter: ['data-theme']
                });
            }
        }, 300);
    });
})();
</script>

<!-- Adiciona bibliotecas necessárias para exportação -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/rowgroup/1.4.1/js/dataTables.rowGroup.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/rowgroup/1.4.1/css/rowGroup.dataTables.min.css">

<style>
/* Estilos para agrupamento de linhas */
.group-row td {
    background-color: #f3f6f9 !important;
    color: #181c32 !important;
    font-weight: 600 !important;
    font-size: 1.05rem !important;
    border-top: 2px solid #3699ff !important;
    border-bottom: 1px solid #e4e6ef !important;
}

/* Tema escuro - força fundo escuro e texto claro */
[data-theme="dark"] .group-row td,
body[data-theme="dark"] .group-row td,
html[data-theme="dark"] .group-row td {
    background-color: #1e1e2d !important;
    color: #ffffff !important;
    border-top: 2px solid #3699ff !important;
    border-bottom: 1px solid #2b2b40 !important;
}

/* Força para linhas pares/ímpares do DataTables */
table.dataTable tbody tr.group-row {
    background-color: #f3f6f9 !important;
    color: #181c32 !important;
}

[data-theme="dark"] table.dataTable tbody tr.group-row,
body[data-theme="dark"] table.dataTable tbody tr.group-row {
    background-color: #1e1e2d !important;
    color: #ffffff !important;
}

/* Corrige linhas normais no tema escuro */
[data-theme="dark"] #kt_table_ocorrencias tbody tr,
body[data-theme="dark"] #kt_table_ocorrencias tbody tr {
    background-color: #151521 !important;
    color: #a1a5b7 !important;
}

[data-theme="dark"] #kt_table_ocorrencias tbody tr:nth-child(even),
body[data-theme="dark"] #kt_table_ocorrencias tbody tr:nth-child(even) {
    background-color: #1a1a27 !important;
}

/* Garante que badges dentro de linhas de grupo sejam visíveis */
[data-theme="dark"] .group-row .badge,
body[data-theme="dark"] .group-row .badge {
    background-color: #3699ff !important;
    color: #ffffff !important;
}

/* Ícones dentro das linhas de grupo */
[data-theme="dark"] .group-row i.ki-duotone,
body[data-theme="dark"] .group-row i.ki-duotone {
    color: #ffffff !important;
}

/* Força todos os textos dentro de células da tabela no tema escuro */
[data-theme="dark"] #kt_table_ocorrencias tbody td,
body[data-theme="dark"] #kt_table_ocorrencias tbody td {
    color: #a1a5b7 !important;
}

/* Links de colaboradores no tema escuro */
[data-theme="dark"] #kt_table_ocorrencias tbody td a,
body[data-theme="dark"] #kt_table_ocorrencias tbody td a {
    color: #3699ff !important;
}

[data-theme="dark"] #kt_table_ocorrencias tbody td a:hover,
body[data-theme="dark"] #kt_table_ocorrencias tbody td a:hover {
    color: #0095e8 !important;
}

/* Cabeçalho da tabela no tema escuro */
[data-theme="dark"] #kt_table_ocorrencias thead th,
body[data-theme="dark"] #kt_table_ocorrencias thead th {
    background-color: #1e1e2d !important;
    color: #a1a5b7 !important;
    border-bottom: 1px solid #2b2b40 !important;
}

/* Linhas de filtro/busca do DataTables */
[data-theme="dark"] .dataTables_wrapper,
body[data-theme="dark"] .dataTables_wrapper {
    color: #a1a5b7 !important;
}

[data-theme="dark"] .dataTables_info,
body[data-theme="dark"] .dataTables_info {
    color: #a1a5b7 !important;
}

/* Melhora visual dos badges */
.badge {
    font-weight: 600;
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
}

/* Hover nas linhas da tabela */
#kt_table_ocorrencias tbody tr:hover {
    background-color: #f9f9f9 !important;
}

[data-theme="dark"] #kt_table_ocorrencias tbody tr:hover {
    background-color: #1e1e2d !important;
}

/* Avatar círculo perfeito */
.symbol-circle img,
.symbol-circle .symbol-label {
    border-radius: 50% !important;
}

/* Cards de estatísticas com hover */
.card-flush:hover {
    transform: translateY(-5px);
    transition: all 0.3s ease;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

/* Menu de ações */
.menu-link:hover {
    background-color: #f3f6f9 !important;
}

[data-theme="dark"] .menu-link:hover {
    background-color: #1e1e2d !important;
}

/* Botão de colapsar filtros */
#kt_filtros_collapse {
    transition: all 0.3s ease;
}

/* Responsividade dos cards de estatísticas */
@media (max-width: 768px) {
    .col-xl-3 {
        margin-bottom: 1rem;
    }
    
    .card-flush .fs-2hx {
        font-size: 2rem !important;
    }
}

/* DataTables search input styling */
.dataTables_filter input {
    margin-left: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.475rem;
    border: 1px solid #e4e6ef;
}

[data-theme="dark"] .dataTables_filter input {
    background-color: #1e1e2d;
    border-color: #2b2b40;
    color: #a1a5b7;
}

/* Paginação */
.dataTables_paginate .paginate_button {
    padding: 0.5rem 1rem;
    margin: 0 0.25rem;
    border-radius: 0.475rem;
}

.dataTables_paginate .paginate_button.current {
    background: #3699ff !important;
    color: white !important;
}

/* FORÇA MÁXIMA para linhas de agrupamento no tema escuro */
[data-theme="dark"] tr.group-row > td,
body[data-theme="dark"] tr.group-row > td,
html[data-theme="dark"] tr.group-row > td,
[data-theme="dark"] tr.dtrg-group > td,
body[data-theme="dark"] tr.dtrg-group > td {
    background-color: #1e1e2d !important;
    color: #ffffff !important;
}

/* Força para todos os elementos dentro da linha de grupo */
[data-theme="dark"] .group-row *,
body[data-theme="dark"] .group-row * {
    color: #ffffff !important;
}

[data-theme="dark"] .group-row .badge,
body[data-theme="dark"] .group-row .badge {
    color: #ffffff !important;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

