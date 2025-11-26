<?php
/**
 * Gestão de Cursos - Administrativo
 */

$page_title = 'Gestão de Cursos';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_cursos.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_busca = $_GET['busca'] ?? '';

$where = [];
$params = [];

// Filtro por status
if ($filtro_status) {
    $where[] = "c.status = ?";
    $params[] = $filtro_status;
}

// Filtro por categoria
if ($filtro_categoria) {
    $where[] = "c.categoria_id = ?";
    $params[] = $filtro_categoria;
}

// Filtro por busca
if ($filtro_busca) {
    $where[] = "(c.titulo LIKE ? OR c.descricao LIKE ?)";
    $busca = "%{$filtro_busca}%";
    $params[] = $busca;
    $params[] = $busca;
}

// Restrições por role
if ($usuario['role'] === 'RH') {
    // RH vê apenas cursos das empresas que tem acesso
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "(c.empresa_id IN ($placeholders) OR c.empresa_id IS NULL)";
        $params = array_merge($params, $usuario['empresas_ids']);
    }
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Busca cursos
$sql = "
    SELECT c.id,
           c.titulo,
           c.descricao,
           c.imagem_capa,
           c.status,
           c.obrigatorio,
           c.duracao_estimada,
           c.created_at,
           cat.nome as categoria_nome,
           COUNT(DISTINCT a.id) as total_aulas,
           COUNT(DISTINCT coc.id) as total_obrigatorios
    FROM cursos c
    LEFT JOIN categorias_cursos cat ON cat.id = c.categoria_id
    LEFT JOIN aulas a ON a.curso_id = c.id AND a.status = 'publicado'
    LEFT JOIN cursos_obrigatorios_colaboradores coc ON coc.curso_id = c.id
    $where_sql
    GROUP BY c.id, c.titulo, c.descricao, c.imagem_capa, c.status, c.obrigatorio, c.duracao_estimada, c.created_at, cat.nome
    ORDER BY c.created_at DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cursos = $stmt->fetchAll();
    
    // Garante que campos obrigatórios existam
    foreach ($cursos as &$curso) {
        $curso['obrigatorio'] = !empty($curso['obrigatorio']) ? (bool)$curso['obrigatorio'] : false;
        $curso['total_aulas'] = (int)($curso['total_aulas'] ?? 0);
        $curso['total_obrigatorios'] = (int)($curso['total_obrigatorios'] ?? 0);
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar cursos: " . $e->getMessage());
    $cursos = [];
}

// Busca categorias para filtro
try {
    $stmt = $pdo->query("SELECT * FROM categorias_cursos WHERE status = 'ativo' ORDER BY nome");
    $categorias = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar categorias: " . $e->getMessage());
    $categorias = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Gestão de Cursos</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Escola Privus</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <a href="lms_curso_add.php" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i>
                Novo Curso
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Filtros-->
        <div class="card mb-5">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="busca" class="form-control" placeholder="Buscar cursos..." value="<?= htmlspecialchars($filtro_busca) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="rascunho" <?= $filtro_status == 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
                            <option value="publicado" <?= $filtro_status == 'publicado' ? 'selected' : '' ?>>Publicado</option>
                            <option value="arquivado" <?= $filtro_status == 'arquivado' ? 'selected' : '' ?>>Arquivado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Categoria</label>
                        <select name="categoria" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filtro_categoria == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Filtros-->
        
        <!--begin::Card-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Cursos</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($cursos) ?> curso(s) encontrado(s)</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-50px">ID</th>
                                <th class="min-w-200px">Curso</th>
                                <th class="min-w-100px">Categoria</th>
                                <th class="min-w-100px">Status</th>
                                <th class="min-w-100px">Aulas</th>
                                <th class="min-w-100px">Obrigatório</th>
                                <th class="min-w-100px text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php if (empty($cursos)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-10">
                                    <div class="text-muted">Nenhum curso encontrado</div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($cursos as $curso): ?>
                                <tr>
                                    <td><?= $curso['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($curso['imagem_capa']): ?>
                                            <img src="<?= htmlspecialchars($curso['imagem_capa']) ?>" class="rounded me-3" style="width: 50px; height: 50px; object-fit: cover;" alt="">
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($curso['titulo']) ?></div>
                                                <div class="text-muted fs-7"><?= htmlspecialchars(substr($curso['descricao'] ?? '', 0, 60)) ?>...</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($curso['categoria_nome']): ?>
                                        <span class="badge badge-light"><?= htmlspecialchars($curso['categoria_nome']) ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_classes = [
                                            'rascunho' => 'warning',
                                            'publicado' => 'success',
                                            'arquivado' => 'secondary'
                                        ];
                                        $status_class = $status_classes[$curso['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $status_class ?>"><?= ucfirst($curso['status']) ?></span>
                                        <?php if (!empty($curso['obrigatorio']) && $curso['obrigatorio']): ?>
                                        <span class="badge badge-danger ms-1">Obrigatório</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $curso['total_aulas'] ?? 0 ?> aula(s)</td>
                                    <td>
                                        <?php if (!empty($curso['obrigatorio']) && $curso['obrigatorio'] && !empty($curso['total_obrigatorios'])): ?>
                                        <span class="badge badge-info"><?= $curso['total_obrigatorios'] ?> atribuições</span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="lms_curso_view.php?id=<?= $curso['id'] ?>" class="btn btn-sm btn-light me-2">
                                            <i class="ki-duotone ki-eye fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </a>
                                        <a href="lms_curso_edit.php?id=<?= $curso['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="ki-duotone ki-pencil fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

