<?php
/**
 * Visualizar Curso - Detalhes Completos
 */

$page_title = 'Detalhes do Curso';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_curso_view.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$curso_id = (int)($_GET['id'] ?? 0);

if (!$curso_id) {
    redirect('lms_cursos.php', 'Curso não encontrado', 'error');
}

// Busca curso
$stmt = $pdo->prepare("
    SELECT c.*, 
           cat.nome as categoria_nome,
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           car.nome_cargo,
           u.nome as criado_por_nome
    FROM cursos c
    LEFT JOIN categorias_cursos cat ON cat.id = c.categoria_id
    LEFT JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN setores s ON c.setor_id = s.id
    LEFT JOIN cargos car ON c.cargo_id = car.id
    LEFT JOIN usuarios u ON c.created_by = u.id
    WHERE c.id = ?
");
$stmt->execute([$curso_id]);
$curso = $stmt->fetch();

if (!$curso) {
    redirect('lms_cursos.php', 'Curso não encontrado', 'error');
}

// Valida acesso por empresa
if ($usuario['role'] === 'RH' && $curso['empresa_id']) {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        if (!in_array($curso['empresa_id'], $usuario['empresas_ids'])) {
            redirect('lms_cursos.php', 'Você não tem permissão para visualizar este curso', 'error');
        }
    }
}

// Busca aulas do curso
$stmt = $pdo->prepare("
    SELECT * FROM aulas 
    WHERE curso_id = ? 
    ORDER BY ordem ASC, id ASC
");
$stmt->execute([$curso_id]);
$aulas = $stmt->fetchAll();

// Estatísticas
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT pc.colaborador_id) as total_colaboradores,
        COUNT(DISTINCT CASE WHEN pc.status = 'concluido' THEN pc.id END) as aulas_concluidas,
        COUNT(DISTINCT coc.id) as total_obrigatorios
    FROM progresso_colaborador pc
    LEFT JOIN cursos_obrigatorios_colaboradores coc ON coc.curso_id = ?
    WHERE pc.curso_id = ?
");
$stmt->execute([$curso_id, $curso_id]);
$stats = $stmt->fetch();

$page_title = $curso['titulo'];
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
                    <a href="lms_cursos.php" class="text-muted text-hover-primary">Escola Privus</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900"><?= htmlspecialchars($curso['titulo']) ?></li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2 gap-2">
            <a href="lms_aulas.php?curso_id=<?= $curso_id ?>" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i>
                Gerenciar Aulas
            </a>
            <a href="lms_curso_edit.php?id=<?= $curso_id ?>" class="btn btn-light">
                <i class="ki-duotone ki-pencil fs-2"></i>
                Editar
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Estatísticas-->
        <div class="row g-5 mb-5">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Total de Aulas</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= count($aulas) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Colaboradores</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $stats['total_colaboradores'] ?? 0 ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Aulas Concluídas</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $stats['aulas_concluidas'] ?? 0 ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Obrigatórios</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $stats['total_obrigatorios'] ?? 0 ?></div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Estatísticas-->
        
        <!--begin::Card Informações-->
        <div class="card mb-5">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <?php if ($curso['imagem_capa']): ?>
                        <img src="../<?= htmlspecialchars($curso['imagem_capa']) ?>" class="img-fluid rounded" alt="<?= htmlspecialchars($curso['titulo']) ?>">
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
                        <div class="d-flex align-items-center mb-3">
                            <?php if ($curso['categoria_nome']): ?>
                            <span class="badge badge-light me-2"><?= htmlspecialchars($curso['categoria_nome']) ?></span>
                            <?php endif; ?>
                            <?php
                            $status_classes = [
                                'rascunho' => 'warning',
                                'publicado' => 'success',
                                'arquivado' => 'secondary'
                            ];
                            $status_class = $status_classes[$curso['status']] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?= $status_class ?>"><?= ucfirst($curso['status']) ?></span>
                            <?php if ($curso['obrigatorio']): ?>
                            <span class="badge badge-danger ms-2">Obrigatório</span>
                            <?php endif; ?>
                        </div>
                        
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
                                        <div class="fw-bold">Nível</div>
                                        <div class="text-muted"><?= ucfirst($curso['nivel_dificuldade']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($curso['obrigatorio']): ?>
                        <div class="alert alert-info">
                            <strong>Curso Obrigatório:</strong>
                            <?php if ($curso['prazo_tipo'] === 'data_fixa' && $curso['data_limite']): ?>
                                Prazo: <?= date('d/m/Y', strtotime($curso['data_limite'])) ?>
                            <?php elseif ($curso['prazo_dias']): ?>
                                Prazo: <?= $curso['prazo_dias'] ?> dias após atribuição
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card Informações-->
        
        <!--begin::Card Aulas-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Aulas do Curso</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($aulas) ?> aula(s)</span>
                </h3>
                <div class="card-toolbar">
                    <a href="lms_aula_add.php?curso_id=<?= $curso_id ?>" class="btn btn-primary">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        Nova Aula
                    </a>
                </div>
            </div>
            <div class="card-body pt-0">
                <?php if (empty($aulas)): ?>
                <div class="text-center p-10">
                    <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <h3 class="text-gray-900 mb-2">Nenhuma aula cadastrada</h3>
                    <p class="text-muted mb-5">Adicione aulas para este curso.</p>
                    <a href="lms_aula_add.php?curso_id=<?= $curso_id ?>" class="btn btn-primary">Adicionar Primeira Aula</a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-50px">#</th>
                                <th class="min-w-200px">Aula</th>
                                <th class="min-w-100px">Tipo</th>
                                <th class="min-w-100px">Duração</th>
                                <th class="min-w-100px">Status</th>
                                <th class="min-w-100px text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php foreach ($aulas as $index => $aula): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($aula['titulo']) ?></div>
                                    <?php if ($aula['descricao']): ?>
                                    <div class="text-muted fs-7"><?= htmlspecialchars(substr($aula['descricao'], 0, 80)) ?>...</div>
                                    <?php endif; ?>
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
                                    <?php
                                    $status_aula = $aula['status'] === 'publicado' ? 'success' : 'warning';
                                    ?>
                                    <span class="badge badge-<?= $status_aula ?>"><?= ucfirst($aula['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="lms_aula_edit.php?id=<?= $aula['id'] ?>&curso_id=<?= $curso_id ?>" class="btn btn-sm btn-primary">
                                        <i class="ki-duotone ki-pencil fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Card Aulas-->
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

