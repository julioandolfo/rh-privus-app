<?php
/**
 * Portal do Colaborador - Detalhes do Curso
 */

$page_title = 'Detalhes do Curso';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_curso_detalhes.php');

require_once __DIR__ . '/../includes/lms_functions.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $usuario['colaborador_id'] ?? null;

if (!$colaborador_id) {
    redirect('dashboard.php', 'Colaborador não encontrado', 'error');
}

$curso_id = (int)($_GET['id'] ?? 0);

if ($curso_id <= 0) {
    redirect('lms_meus_cursos.php', 'Curso não encontrado', 'error');
}

// Verifica se pode acessar o curso
if (!function_exists('pode_acessar_curso')) {
    require_once __DIR__ . '/../includes/lms_functions.php';
}

if (!pode_acessar_curso($colaborador_id, $curso_id)) {
    redirect('lms_meus_cursos.php', 'Você não tem permissão para acessar este curso', 'error');
}

// Busca dados do curso
$stmt = $pdo->prepare("
    SELECT c.*, cat.nome as categoria_nome, cat.cor as categoria_cor
    FROM cursos c
    LEFT JOIN categorias_cursos cat ON cat.id = c.categoria_id
    WHERE c.id = ?
");
$stmt->execute([$curso_id]);
$curso = $stmt->fetch();

if (!$curso) {
    redirect('lms_meus_cursos.php', 'Curso não encontrado', 'error');
}

// Busca aulas do curso
$stmt = $pdo->prepare("
    SELECT a.*,
           pc.id as progresso_id,
           pc.status as status_progresso,
           pc.percentual_conclusao,
           pc.tempo_assistido,
           pc.ultima_posicao,
           pc.data_conclusao,
           pc.bloqueado_por_fraude
    FROM aulas a
    LEFT JOIN progresso_colaborador pc ON pc.aula_id = a.id AND pc.colaborador_id = ?
    WHERE a.curso_id = ? AND a.status = 'publicado'
    ORDER BY a.ordem ASC, a.id ASC
");
$stmt->execute([$colaborador_id, $curso_id]);
$aulas = $stmt->fetchAll();

// Calcula progresso geral
if (!function_exists('buscar_progresso_curso') || !function_exists('calcular_percentual_curso')) {
    require_once __DIR__ . '/../includes/lms_functions.php';
}

$progresso_geral = buscar_progresso_curso($colaborador_id, $curso_id);
$percentual_geral = calcular_percentual_curso($colaborador_id, $curso_id);

// Verifica se é curso obrigatório
$stmt = $pdo->prepare("
    SELECT * FROM cursos_obrigatorios_colaboradores 
    WHERE curso_id = ? AND colaborador_id = ?
");
$stmt->execute([$curso_id, $colaborador_id]);
$curso_obrigatorio = $stmt->fetch();

// Atualiza título com nome do curso
if (!empty($curso['titulo'])) {
    $page_title = $curso['titulo'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0"><?= htmlspecialchars($curso['titulo']) ?></h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="lms_meus_cursos.php" class="text-muted text-hover-primary">Meus Cursos</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900"><?= htmlspecialchars($curso['titulo']) ?></li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?php if ($curso_obrigatorio): ?>
        <!--begin::Alert Curso Obrigatório-->
        <div class="alert alert-<?= $curso_obrigatorio['status'] == 'vencido' ? 'danger' : 'warning' ?> d-flex align-items-center p-5 mb-5">
            <i class="ki-duotone ki-information-5 fs-2hx text-<?= $curso_obrigatorio['status'] == 'vencido' ? 'danger' : 'warning' ?> me-4">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-dark">Curso Obrigatório</h4>
                <span>
                    <?php if ($curso_obrigatorio['status'] == 'vencido'): ?>
                        Este curso está <strong>vencido</strong>. Complete-o o quanto antes.
                    <?php else: ?>
                        Prazo para conclusão: <strong><?= date('d/m/Y', strtotime($curso_obrigatorio['data_limite'])) ?></strong>
                        (<?= $curso_obrigatorio['dias_restantes'] ?? 0 ?> dias restantes)
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <!--end::Alert-->
        <?php endif; ?>
        
        <!--begin::Card Curso-->
        <div class="card mb-5">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <?php if ($curso['imagem_capa']): ?>
                        <img src="<?= htmlspecialchars($curso['imagem_capa']) ?>" class="img-fluid rounded" alt="<?= htmlspecialchars($curso['titulo']) ?>">
                        <?php else: ?>
                        <div class="bg-light-primary d-flex align-items-center justify-content-center rounded" style="height: 200px;">
                            <i class="ki-duotone ki-book fs-1 text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-9">
                        <?php if ($curso['categoria_nome']): ?>
                        <span class="badge badge-light mb-3"><?= htmlspecialchars($curso['categoria_nome']) ?></span>
                        <?php endif; ?>
                        <h2 class="mb-3"><?= htmlspecialchars($curso['titulo']) ?></h2>
                        <p class="text-muted mb-4"><?= nl2br(htmlspecialchars($curso['descricao'] ?? '')) ?></p>
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-time fs-2 text-primary me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div>
                                        <div class="fw-bold">Duração</div>
                                        <div class="text-muted"><?= $curso['duracao_estimada'] ?? 0 ?> minutos</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-file fs-2 text-primary me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div>
                                        <div class="fw-bold">Aulas</div>
                                        <div class="text-muted"><?= count($aulas) ?> aula(s)</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-chart-simple fs-2 text-primary me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div>
                                        <div class="fw-bold">Progresso</div>
                                        <div class="text-muted"><?= $percentual_geral ?>%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-bold">Progresso do Curso</span>
                                <span class="text-muted"><?= $percentual_geral ?>%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar" role="progressbar" style="width: <?= $percentual_geral ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card Curso-->
        
        <!--begin::Card Aulas-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Aulas do Curso</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($aulas) ?> aula(s) disponível(is)</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-50px">#</th>
                                <th class="min-w-200px">Aula</th>
                                <th class="min-w-100px">Tipo</th>
                                <th class="min-w-100px">Duração</th>
                                <th class="min-w-150px">Progresso</th>
                                <th class="min-w-100px text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php foreach ($aulas as $index => $aula): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($aula['status_progresso'] == 'concluido'): ?>
                                        <i class="ki-duotone ki-check-circle fs-2 text-success me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <?php elseif ($aula['status_progresso'] == 'em_andamento'): ?>
                                        <i class="ki-duotone ki-time fs-2 text-warning me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <?php else: ?>
                                        <i class="ki-duotone ki-circle fs-2 text-muted me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($aula['titulo']) ?></div>
                                            <?php if ($aula['descricao']): ?>
                                            <div class="text-muted fs-7"><?= htmlspecialchars(substr($aula['descricao'], 0, 80)) ?>...</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-light">
                                        <?php
                                        $tipos = [
                                            'video_youtube' => 'Vídeo YouTube',
                                            'video_upload' => 'Vídeo',
                                            'pdf' => 'PDF',
                                            'texto' => 'Texto'
                                        ];
                                        echo $tipos[$aula['tipo_conteudo']] ?? $aula['tipo_conteudo'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($aula['duracao_minutos']): ?>
                                    <?= $aula['duracao_minutos'] ?> min
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($aula['progresso_id']): ?>
                                    <div class="d-flex align-items-center">
                                        <div class="progress w-100 me-2" style="height: 6px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?= $aula['percentual_conclusao'] ?? 0 ?>%"></div>
                                        </div>
                                        <span class="text-muted fs-7"><?= round($aula['percentual_conclusao'] ?? 0, 0) ?>%</span>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">Não iniciado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="lms_player_aula.php?curso_id=<?= $curso_id ?>&aula_id=<?= $aula['id'] ?>" class="btn btn-sm btn-primary">
                                        <?= $aula['status_progresso'] == 'concluido' ? 'Revisar' : ($aula['status_progresso'] == 'em_andamento' ? 'Continuar' : 'Iniciar') ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Card Aulas-->
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

