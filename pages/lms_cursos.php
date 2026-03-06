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

// Restrições por role (RH e GESTOR)
if ($usuario['role'] === 'RH' || $usuario['role'] === 'GESTOR') {
    // RH e GESTOR veem apenas cursos das empresas que tem acesso
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
        
        <!--begin::Header Resultados-->
        <div class="d-flex align-items-center justify-content-between mb-5">
            <div>
                <h3 class="fw-bold fs-3 text-gray-900 mb-1">Cursos</h3>
                <span class="text-muted fw-semibold fs-7"><?= count($cursos) ?> curso(s) encontrado(s)</span>
            </div>
        </div>
        <!--end::Header Resultados-->

        <?php if (empty($cursos)): ?>
        <!--begin::Estado Vazio-->
        <div class="card">
            <div class="card-body text-center py-20">
                <i class="ki-duotone ki-book fs-5x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <h3 class="text-gray-900 mb-2">Nenhum curso encontrado</h3>
                <p class="text-muted mb-5">Crie o primeiro curso ou ajuste os filtros de busca.</p>
                <a href="lms_curso_add.php" class="btn btn-primary">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Criar Novo Curso
                </a>
            </div>
        </div>
        <!--end::Estado Vazio-->
        <?php else: ?>
        <!--begin::Grid de Cards-->
        <div class="row g-6">
            <?php
            $status_classes = [
                'rascunho' => 'warning',
                'publicado' => 'success',
                'arquivado' => 'secondary'
            ];
            $status_labels = [
                'rascunho' => 'Rascunho',
                'publicado' => 'Publicado',
                'arquivado' => 'Arquivado'
            ];
            foreach ($cursos as $curso):
                $status_class = $status_classes[$curso['status']] ?? 'secondary';
                $status_label = $status_labels[$curso['status']] ?? ucfirst($curso['status']);
            ?>
            <div class="col-xl-4 col-lg-6 col-md-6">
                <div class="card h-100 card-flush hover-elevate-up transition-all" style="transition: transform 0.2s ease, box-shadow 0.2s ease;">
                    <!--begin::Capa do Curso-->
                    <div class="card-header p-0 border-0" style="min-height: 200px; position: relative; overflow: hidden; border-radius: 12px 12px 0 0;">
                        <?php if (!empty($curso['imagem_capa'])): ?>
                        <img src="../<?= htmlspecialchars($curso['imagem_capa']) ?>"
                             alt="<?= htmlspecialchars($curso['titulo']) ?>"
                             style="width: 100%; height: 200px; object-fit: cover; display: block;"
                             onerror="this.parentElement.innerHTML='<div class=\'w-100 h-100 d-flex align-items-center justify-content-center bg-light-primary\' style=\'height:200px\'><i class=\'ki-duotone ki-book fs-3x text-primary\'><span class=\'path1\'></span><span class=\'path2\'></span><span class=\'path3\'></span><span class=\'path4\'></span></i></div>'">
                        <?php else: ?>
                        <div class="w-100 d-flex align-items-center justify-content-center bg-light-primary" style="height: 200px;">
                            <i class="ki-duotone ki-book fs-3x text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </div>
                        <?php endif; ?>

                        <!--begin::Badges sobrepostos-->
                        <div style="position: absolute; top: 12px; left: 12px; display: flex; gap: 6px; flex-wrap: wrap;">
                            <span class="badge badge-<?= $status_class ?> fs-8 fw-bold px-3 py-2"><?= $status_label ?></span>
                            <?php if (!empty($curso['obrigatorio']) && $curso['obrigatorio']): ?>
                            <span class="badge badge-danger fs-8 fw-bold px-3 py-2">Obrigatório</span>
                            <?php endif; ?>
                        </div>
                        <!--end::Badges sobrepostos-->
                    </div>
                    <!--end::Capa-->

                    <!--begin::Corpo do Card-->
                    <div class="card-body p-6 d-flex flex-column">

                        <!--begin::Categoria-->
                        <?php if (!empty($curso['categoria_nome'])): ?>
                        <span class="text-muted fs-8 fw-bold text-uppercase letter-spacing-1 mb-2">
                            <?= htmlspecialchars($curso['categoria_nome']) ?>
                        </span>
                        <?php endif; ?>
                        <!--end::Categoria-->

                        <!--begin::Título-->
                        <h4 class="fw-bold text-gray-900 fs-5 mb-2 lh-sm" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= htmlspecialchars($curso['titulo']) ?>
                        </h4>
                        <!--end::Título-->

                        <!--begin::Descrição-->
                        <?php if (!empty($curso['descricao'])): ?>
                        <p class="text-muted fs-7 mb-4 flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= htmlspecialchars($curso['descricao']) ?>
                        </p>
                        <?php else: ?>
                        <div class="flex-grow-1"></div>
                        <?php endif; ?>
                        <!--end::Descrição-->

                        <!--begin::Metadados-->
                        <div class="d-flex align-items-center gap-4 mb-5 pt-3 border-top border-dashed">
                            <div class="d-flex align-items-center gap-2">
                                <i class="ki-duotone ki-teacher fs-4 text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span class="text-muted fs-7 fw-semibold"><?= $curso['total_aulas'] ?> aula(s)</span>
                            </div>
                            <?php if (!empty($curso['duracao_estimada'])): ?>
                            <div class="d-flex align-items-center gap-2">
                                <i class="ki-duotone ki-time fs-4 text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span class="text-muted fs-7 fw-semibold"><?= $curso['duracao_estimada'] ?> min</span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($curso['obrigatorio']) && $curso['obrigatorio'] && !empty($curso['total_obrigatorios'])): ?>
                            <div class="d-flex align-items-center gap-2">
                                <i class="ki-duotone ki-people fs-4 text-danger">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                    <span class="path5"></span>
                                </i>
                                <span class="text-muted fs-7 fw-semibold"><?= $curso['total_obrigatorios'] ?> atribuições</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!--end::Metadados-->

                        <!--begin::Ações-->
                        <div class="d-flex gap-2">
                            <a href="lms_curso_view.php?id=<?= $curso['id'] ?>" class="btn btn-light btn-sm flex-grow-1">
                                <i class="ki-duotone ki-eye fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Detalhes
                            </a>
                            <a href="lms_curso_edit.php?id=<?= $curso['id'] ?>" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="ki-duotone ki-pencil fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Editar
                            </a>
                        </div>
                        <!--end::Ações-->

                    </div>
                    <!--end::Corpo do Card-->
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <!--end::Grid de Cards-->
        <?php endif; ?>
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

