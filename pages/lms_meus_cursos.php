<?php
/**
 * Portal do Colaborador - Meus Cursos
 */

$page_title = 'Meus Cursos';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_meus_cursos.php');

require_once __DIR__ . '/../includes/lms_functions.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $usuario['colaborador_id'] ?? null;

if (!$colaborador_id) {
    redirect('dashboard.php', 'Colaborador não encontrado', 'error');
}

$filtro_obrigatorios = isset($_GET['obrigatorios']) && $_GET['obrigatorios'] == '1';
$filtro_categoria = $_GET['categoria'] ?? null;
$filtro_busca = $_GET['busca'] ?? null;

// Busca cursos obrigatórios se solicitado
$cursos_obrigatorios = [];
if ($filtro_obrigatorios) {
    $stmt = $pdo->prepare("
        SELECT coc.*,
               c.id as curso_id,
               c.titulo, c.descricao, c.imagem_capa, c.duracao_estimada,
               DATEDIFF(coc.data_limite, CURDATE()) as dias_restantes,
               (SELECT COUNT(*) FROM aulas a WHERE a.curso_id = c.id AND a.status = 'publicado') as total_aulas,
               (SELECT COUNT(*) FROM progresso_colaborador pc 
                WHERE pc.colaborador_id = ? AND pc.curso_id = c.id AND pc.status = 'concluido') as aulas_concluidas
        FROM cursos_obrigatorios_colaboradores coc
        INNER JOIN cursos c ON c.id = coc.curso_id
        WHERE coc.colaborador_id = ?
        ORDER BY 
            CASE 
                WHEN coc.status = 'vencido' THEN 1
                WHEN coc.status = 'pendente' THEN 2
                WHEN coc.status = 'em_andamento' THEN 3
                ELSE 4
            END,
            coc.data_limite ASC
    ");
    $stmt->execute([$colaborador_id, $colaborador_id]);
    $cursos_obrigatorios = $stmt->fetchAll();
    
    foreach ($cursos_obrigatorios as &$curso) {
        if ($curso['total_aulas'] > 0) {
            $curso['percentual_conclusao'] = round(($curso['aulas_concluidas'] / $curso['total_aulas']) * 100, 2);
        } else {
            $curso['percentual_conclusao'] = 0;
        }
    }
} else {
    // Busca cursos disponíveis
    $filtros = [
        'categoria_id' => $filtro_categoria,
        'busca' => $filtro_busca
    ];
    
    if (!function_exists('buscar_cursos_disponiveis')) {
        require_once __DIR__ . '/../includes/lms_functions.php';
    }
    
    try {
        $cursos = buscar_cursos_disponiveis($colaborador_id, $filtros);
        if (!is_array($cursos)) {
            $cursos = [];
        }
        
        // Adiciona progresso para cada curso
        if (!function_exists('buscar_progresso_curso') || !function_exists('calcular_percentual_curso')) {
            require_once __DIR__ . '/../includes/lms_functions.php';
        }
        
        foreach ($cursos as &$curso) {
            $progresso = buscar_progresso_curso($colaborador_id, $curso['id']);
            $curso['progresso'] = $progresso;
            $curso['percentual_conclusao'] = calcular_percentual_curso($colaborador_id, $curso['id']);
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar cursos: " . $e->getMessage());
        $cursos = [];
    }
}

// Busca categorias para filtro
$stmt = $pdo->query("SELECT * FROM categorias_cursos WHERE status = 'ativo' ORDER BY ordem, nome");
$categorias = $stmt->fetchAll();

// Conta cursos obrigatórios pendentes
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cursos_obrigatorios_colaboradores 
    WHERE colaborador_id = ? AND status IN ('pendente', 'em_andamento', 'vencido')
");
$stmt->execute([$colaborador_id]);
$total_obrigatorios = $stmt->fetch()['total'] ?? 0;

// Atualiza título se estiver filtrando obrigatórios
if ($filtro_obrigatorios) {
    $page_title = 'Cursos Obrigatórios';
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0"><?= $page_title ?></h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="lms_meus_cursos.php" class="text-muted text-hover-primary">Escola Privus</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900"><?= $page_title ?></li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?php if ($total_obrigatorios > 0 && !$filtro_obrigatorios): ?>
        <!--begin::Alert Cursos Obrigatórios-->
        <div class="alert alert-warning d-flex align-items-center p-5 mb-5">
            <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-dark">Você tem <?= $total_obrigatorios ?> curso(s) obrigatório(s) pendente(s)</h4>
                <span>Complete seus cursos obrigatórios para manter-se em dia com os treinamentos.</span>
            </div>
                        <a href="lms_meus_cursos.php?obrigatorios=1" class="btn btn-warning ms-auto">Ver Cursos Obrigatórios</a>
        </div>
        <!--end::Alert-->
        <?php endif; ?>
        
        <!--begin::Filtros-->
        <div class="card mb-5">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="busca" class="form-control" placeholder="Buscar cursos..." value="<?= htmlspecialchars($filtro_busca ?? '') ?>">
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
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <?php if ($filtro_busca || $filtro_categoria): ?>
                        <a href="lms_meus_cursos.php<?= $filtro_obrigatorios ? '?obrigatorios=1' : '' ?>" class="btn btn-light ms-2">Limpar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Filtros-->
        
        <?php if ($filtro_obrigatorios): ?>
        <!--begin::Cursos Obrigatórios-->
        <div class="row g-5">
            <?php if (empty($cursos_obrigatorios)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center p-10">
                        <i class="ki-duotone ki-check-circle fs-3x text-success mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <h3 class="text-gray-900 mb-2">Nenhum curso obrigatório pendente</h3>
                        <p class="text-muted">Parabéns! Você completou todos os seus cursos obrigatórios.</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($cursos_obrigatorios as $curso): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 <?= $curso['status'] == 'vencido' ? 'border-danger' : ($curso['status'] == 'pendente' ? 'border-warning' : '') ?>">
                        <div class="card-header border-0 pt-9">
                            <?php if ($curso['imagem_capa']): ?>
                            <img src="<?= htmlspecialchars($curso['imagem_capa']) ?>" class="card-img-top" alt="<?= htmlspecialchars($curso['titulo']) ?>" style="height: 150px; object-fit: cover;">
                            <?php else: ?>
                            <div class="bg-light-primary d-flex align-items-center justify-content-center" style="height: 150px;">
                                <i class="ki-duotone ki-book fs-1 text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-<?= $curso['status'] == 'vencido' ? 'danger' : ($curso['status'] == 'pendente' ? 'warning' : 'success') ?>">
                                    <?= ucfirst($curso['status']) ?>
                                </span>
                                <?php if ($curso['dias_restantes'] < 0): ?>
                                <span class="badge badge-danger ms-2">Vencido há <?= abs($curso['dias_restantes']) ?> dia(s)</span>
                                <?php elseif ($curso['dias_restantes'] <= 7): ?>
                                <span class="badge badge-warning ms-2">Vence em <?= $curso['dias_restantes'] ?> dia(s)</span>
                                <?php endif; ?>
                            </div>
                            <h3 class="card-title mb-3"><?= htmlspecialchars($curso['titulo']) ?></h3>
                            <p class="text-muted mb-4"><?= htmlspecialchars(substr($curso['descricao'] ?? '', 0, 100)) ?>...</p>
                            
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted fs-7">Progresso</span>
                                    <span class="text-muted fs-7"><?= $curso['percentual_conclusao'] ?>%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?= $curso['percentual_conclusao'] ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span class="text-muted fs-7">
                                    <i class="ki-duotone ki-time fs-6">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Prazo: <?= date('d/m/Y', strtotime($curso['data_limite'])) ?>
                                </span>
                            </div>
                            
                            <a href="lms_curso_detalhes.php?id=<?= $curso['curso_id'] ?>" class="btn btn-<?= $curso['status'] == 'vencido' ? 'danger' : 'primary' ?> w-100">
                                <?= $curso['status'] == 'concluido' ? 'Ver Curso' : 'Iniciar Curso' ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!--end::Cursos Obrigatórios-->
        
        <?php else: ?>
        <!--begin::Cursos Disponíveis-->
        <div class="row g-5">
            <?php if (empty($cursos)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center p-10">
                        <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <h3 class="text-gray-900 mb-2">Nenhum curso encontrado</h3>
                        <p class="text-muted">Tente ajustar os filtros de busca.</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($cursos as $curso): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-header border-0 pt-9">
                            <?php if ($curso['imagem_capa']): ?>
                            <img src="<?= htmlspecialchars($curso['imagem_capa']) ?>" class="card-img-top" alt="<?= htmlspecialchars($curso['titulo']) ?>" style="height: 150px; object-fit: cover;">
                            <?php else: ?>
                            <div class="bg-light-primary d-flex align-items-center justify-content-center" style="height: 150px;">
                                <i class="ki-duotone ki-book fs-1 text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body pt-0">
                            <?php if ($curso['categoria_nome']): ?>
                            <span class="badge badge-light mb-2"><?= htmlspecialchars($curso['categoria_nome']) ?></span>
                            <?php endif; ?>
                            <h3 class="card-title mb-3"><?= htmlspecialchars($curso['titulo']) ?></h3>
                            <p class="text-muted mb-4"><?= htmlspecialchars(substr($curso['descricao'] ?? '', 0, 100)) ?>...</p>
                            
                            <?php if (isset($curso['total_aulas']) && $curso['total_aulas'] > 0): ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted fs-7">Progresso</span>
                                    <span class="text-muted fs-7"><?= $curso['percentual_conclusao'] ?? 0 ?>%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?= $curso['percentual_conclusao'] ?? 0 ?>%"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span class="text-muted fs-7">
                                    <i class="ki-duotone ki-time fs-6">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <?= $curso['duracao_estimada'] ?? 0 ?> min
                                </span>
                                <span class="text-muted fs-7">
                                    <i class="ki-duotone ki-file fs-6">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <?= $curso['total_aulas'] ?? 0 ?> aula(s)
                                </span>
                            </div>
                            
                            <a href="lms_curso_detalhes.php?id=<?= $curso['id'] ?>" class="btn btn-primary w-100">
                                <?= ($curso['percentual_conclusao'] ?? 0) > 0 ? 'Continuar Curso' : 'Iniciar Curso' ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!--end::Cursos Disponíveis-->
        <?php endif; ?>
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

