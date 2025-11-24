<?php
/**
 * Lista de Ocorrências
 */

$page_title = 'Ocorrências';
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

// Monta query com filtros
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

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT o.*, 
           c.nome_completo as colaborador_nome,
           u.nome as usuario_nome,
           t.nome as tipo_ocorrencia_nome,
           t.categoria as tipo_categoria,
           COUNT(DISTINCT a.id) as total_anexos,
           COUNT(DISTINCT com.id) as total_comentarios
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
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Ocorrências</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Ocorrências</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <a href="ocorrencias_add.php" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Ocorrência
            </a>
        </div>
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
    
        <div class="card">
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_ocorrencias_table">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-100px">Data</th>
                                <th class="min-w-150px">Colaborador</th>
                                <th class="min-w-150px">Tipo</th>
                                <th class="min-w-100px">Severidade</th>
                                <th class="min-w-100px">Status</th>
                                <th class="min-w-200px">Descrição</th>
                                <th class="min-w-100px">Anexos</th>
                                <th class="min-w-100px">Comentários</th>
                                <th class="min-w-100px">Registrado por</th>
                                <th class="text-end min-w-70px">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            <?php foreach ($ocorrencias as $ocorrencia): ?>
                            <tr>
                                <td><?= formatar_data($ocorrencia['data_ocorrencia']) ?></td>
                                <td>
                                    <a href="colaborador_view.php?id=<?= $ocorrencia['colaborador_id'] ?>" class="text-gray-800 text-hover-primary">
                                        <?= htmlspecialchars($ocorrencia['colaborador_nome']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                    $tipo_nome = $ocorrencia['tipo_ocorrencia_nome'] ?? ($tipos_ocorrencias[$ocorrencia['tipo']] ?? $ocorrencia['tipo']);
                                    $categoria = $ocorrencia['tipo_categoria'] ?? 'outros';
                                    $categoria_colors = [
                                        'pontualidade' => 'badge-light-warning',
                                        'comportamento' => 'badge-light-danger',
                                        'desempenho' => 'badge-light-primary',
                                        'outros' => 'badge-light-secondary'
                                    ];
                                    ?>
                                    <span class="badge <?= $categoria_colors[$categoria] ?? 'badge-light-secondary' ?>">
                                        <?= htmlspecialchars($tipo_nome) ?>
                                    </span>
                                </td>
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
                                    <a href="ocorrencia_view.php?id=<?= $ocorrencia['id'] ?>" class="btn btn-sm btn-light btn-active-light-primary">
                                        Ver Detalhes
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<script>
"use strict";
var KTOcorrenciasList = function() {
    var t, n;
    
    return {
        init: function() {
            n = document.querySelector("#kt_ocorrencias_table");
            
            if (n) {
                t = $(n).DataTable({
                    info: true,
                    order: [[0, 'desc']],
                    pageLength: 25,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    columnDefs: [
                        { orderable: false, targets: 9 }
                    ]
                });
            }
        }
    };
}();

// Aguarda jQuery estar disponível
(function waitForDependencies() {
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
        setTimeout(waitForDependencies, 50);
        return;
    }
    
    $(document).ready(function() {
        KTOcorrenciasList.init();
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

