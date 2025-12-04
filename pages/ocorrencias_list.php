<?php
/**
 * Lista de Ocorrências
 */

// Habilita exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$page_title = is_colaborador() ? 'Minhas Ocorrências' : 'Ocorrências';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
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
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
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
                    
                    <div class="col-md-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
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
    
        <!-- Lista Agrupada por Colaborador e Tipo -->
        <?php if (empty($ocorrencias_agrupadas)): ?>
        <div class="card">
            <div class="card-body">
                <div class="text-center py-10">
                    <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <p class="text-muted fs-4">Nenhuma ocorrência encontrada com os filtros aplicados.</p>
                </div>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($ocorrencias_agrupadas as $colab_id => $colab_data): ?>
        <div class="card mb-5 shadow-sm">
            <div class="card-header border-0 pt-6 pb-4">
                <div class="card-title d-flex align-items-center">
                    <!-- Avatar do Colaborador -->
                    <div class="symbol symbol-65px symbol-circle me-4">
                        <?php if ($colab_data['foto']): ?>
                        <img src="../<?= htmlspecialchars($colab_data['foto']) ?>" 
                             alt="<?= htmlspecialchars($colab_data['nome']) ?>" 
                             class="symbol-label"
                             style="object-fit: cover;"
                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <?php endif; ?>
                        <?php if (!$colab_data['foto']): ?>
                        <div class="symbol-label bg-primary text-white fw-bold fs-2 d-flex align-items-center justify-content-center">
                            <?= htmlspecialchars($colab_data['inicial']) ?>
                        </div>
                        <?php else: ?>
                        <div class="symbol-label bg-primary text-white fw-bold fs-2 d-flex align-items-center justify-content-center" style="display: none;">
                            <?= htmlspecialchars($colab_data['inicial']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Informações do Colaborador -->
                    <div class="d-flex flex-column">
                        <h3 class="fw-bold text-gray-800 mb-1">
                            <a href="colaborador_view.php?id=<?= $colab_id ?>" class="text-gray-800 text-hover-primary">
                                <?= htmlspecialchars($colab_data['nome']) ?>
                            </a>
                        </h3>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-light-primary">
                                <i class="ki-duotone ki-notepad fs-2 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <?= array_sum(array_map(function($tipo) { return count($tipo['ocorrencias']); }, $colab_data['tipos'])) ?> ocorrência<?= array_sum(array_map(function($tipo) { return count($tipo['ocorrencias']); }, $colab_data['tipos'])) > 1 ? 's' : '' ?>
                            </span>
                            <span class="text-muted fs-7">
                                <?= count($colab_data['tipos']) ?> tipo<?= count($colab_data['tipos']) > 1 ? 's' : '' ?> diferente<?= count($colab_data['tipos']) > 1 ? 's' : '' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0">
                <?php foreach ($colab_data['tipos'] as $tipo_key => $tipo_data): ?>
                <div class="mb-8">
                    <div class="d-flex align-items-center mb-4">
                        <?php
                        $categoria = $tipo_data['categoria'] ?? 'outros';
                        $categoria_colors = [
                            'pontualidade' => 'badge-light-warning',
                            'comportamento' => 'badge-light-danger',
                            'desempenho' => 'badge-light-primary',
                            'outros' => 'badge-light-secondary'
                        ];
                        ?>
                        <h4 class="fw-bold text-gray-700 me-3">
                            <span class="badge <?= $categoria_colors[$categoria] ?? 'badge-light-secondary' ?> fs-6 me-2">
                                <?= htmlspecialchars($tipo_data['nome']) ?>
                            </span>
                            <span class="text-muted fs-6">
                                (<?= count($tipo_data['ocorrencias']) ?> ocorrência<?= count($tipo_data['ocorrencias']) > 1 ? 's' : '' ?>)
                            </span>
                        </h4>
                    </div>
                    
                <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                        <thead>
                                <tr class="fw-bold text-muted">
                                <th class="min-w-100px">Data</th>
                                <th class="min-w-100px">Severidade</th>
                                <th class="min-w-100px">Status</th>
                                <th class="min-w-200px">Descrição</th>
                                <th class="min-w-100px">Anexos</th>
                                <th class="min-w-100px">Comentários</th>
                                <th class="min-w-100px">Registrado por</th>
                                <th class="text-end min-w-70px">Ações</th>
                            </tr>
                        </thead>
                            <tbody>
                                <?php foreach ($tipo_data['ocorrencias'] as $ocorrencia): ?>
                            <tr>
                                <td><?= formatar_data($ocorrencia['data_ocorrencia']) ?></td>
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
                                <td>
                                    <?php
                                    $status_aprovacao = $ocorrencia['status_aprovacao'] ?? 'aprovada';
                                    $status_colors = [
                                        'pendente' => 'badge-light-warning',
                                        'aprovada' => 'badge-light-success',
                                        'rejeitada' => 'badge-light-danger'
                                    ];
                                    $status_labels = [
                                        'pendente' => 'Pendente',
                                        'aprovada' => 'Aprovada',
                                        'rejeitada' => 'Rejeitada'
                                    ];
                                    ?>
                                    <span class="badge <?= $status_colors[$status_aprovacao] ?? 'badge-light-success' ?>">
                                        <?= $status_labels[$status_aprovacao] ?? 'Aprovada' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $descricao = $ocorrencia['descricao'] ?? '';
                                    $descricao_curta = mb_substr($descricao, 0, 100);
                                    ?>
                                    <span class="text-gray-800" title="<?= htmlspecialchars($descricao) ?>">
                                        <?= nl2br(htmlspecialchars($descricao_curta)) ?>
                                        <?= mb_strlen($descricao) > 100 ? '...' : '' ?>
                                    </span>
                                    <?php
                                    // Mostra badge de "Apenas Informativa" se aplicável
                                    if (!empty($ocorrencia['apenas_informativa']) && $ocorrencia['apenas_informativa'] == 1) {
                                        echo '<div class="mt-2">';
                                        echo '<span class="badge badge-light-success" title="Esta ocorrência é apenas informativa e não gera impacto financeiro">';
                                        echo '<i class="ki-duotone ki-information-5 fs-2 me-1">';
                                        echo '<span class="path1"></span><span class="path2"></span><span class="path3"></span>';
                                        echo '</i>';
                                        echo 'Apenas Informativa';
                                        echo '</span>';
                                        echo '</div>';
                                    }
                                    
                                    // Mostra tags
                                    if (!empty($ocorrencia['tags'])) {
                                        $tags_array = json_decode($ocorrencia['tags'], true);
                                        if ($tags_array) {
                                            echo '<div class="mt-2">';
                                            foreach ($tags_array as $tag_id) {
                                                foreach ($tags_disponiveis as $tag) {
                                                    if ($tag['id'] == $tag_id) {
                                                        echo '<span class="badge badge-light" style="background-color: ' . htmlspecialchars($tag['cor']) . '20; color: ' . htmlspecialchars($tag['cor']) . ';">' . htmlspecialchars($tag['nome']) . '</span> ';
                                                        break;
                                                    }
                                                }
                                            }
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($ocorrencia['total_anexos'] > 0): ?>
                                        <span class="badge badge-light-info">
                                            <i class="ki-duotone ki-file fs-2"></i>
                                            <?= $ocorrencia['total_anexos'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ocorrencia['total_comentarios'] > 0): ?>
                                        <span class="badge badge-light-primary">
                                            <i class="ki-duotone ki-message fs-2"></i>
                                            <?= $ocorrencia['total_comentarios'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($ocorrencia['usuario_nome'] ?? 'N/A') ?></td>
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
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        
    </div>
</div>
<!--end::Post-->

<script>
"use strict";
// Inicializa DataTables para cada tabela de tipo de ocorrência
var KTOcorrenciasList = function() {
    return {
        init: function() {
            // Encontra todas as tabelas dentro dos cards de colaborador (mas não a tabela de filtros)
            const cards = document.querySelectorAll('.card.mb-5');
            
            cards.forEach((card) => {
                const tables = card.querySelectorAll('.table-responsive table');
            
                tables.forEach((table) => {
                    // Verifica se a tabela já não foi inicializada e se tem o cabeçalho correto
                    if (table.querySelector('thead') && !$(table).hasClass('dataTable')) {
                        $(table).DataTable({
                    info: true,
                    order: [[0, 'desc']],
                            pageLength: 10,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    columnDefs: [
                                { orderable: false, targets: 7 }
                            ],
                            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                                 '<"row"<"col-sm-12"tr>>' +
                                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                            paging: true,
                            searching: true,
                            ordering: true
                });
            }
                });
            });
        }
    };
}();

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

// Aguarda jQuery estar disponível
(function waitForDependencies() {
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
        setTimeout(waitForDependencies, 50);
        return;
    }
    
    $(document).ready(function() {
        // Aguarda um pouco para garantir que todas as tabelas foram renderizadas
        setTimeout(function() {
        KTOcorrenciasList.init();
        }, 300);
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

