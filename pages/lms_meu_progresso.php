<?php
/**
 * Portal do Colaborador - Meu Progresso
 */

$page_title = 'Meu Progresso';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_meu_progresso.php');

require_once __DIR__ . '/../includes/lms_functions.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $usuario['colaborador_id'] ?? null;

if (!$colaborador_id) {
    redirect('dashboard.php', 'Colaborador não encontrado', 'error');
}

// Busca todos os cursos com progresso
$stmt = $pdo->prepare("
    SELECT DISTINCT
        c.id,
        c.titulo,
        c.descricao,
        c.imagem_capa,
        c.duracao_estimada,
        cat.nome as categoria_nome,
        COUNT(DISTINCT a.id) as total_aulas,
        SUM(CASE WHEN pc.status = 'concluido' THEN 1 ELSE 0 END) as aulas_concluidas,
        SUM(CASE WHEN pc.status = 'em_andamento' THEN 1 ELSE 0 END) as aulas_em_andamento,
        SUM(pc.tempo_assistido) as tempo_total_assistido,
        MAX(pc.data_ultimo_acesso) as ultimo_acesso
    FROM cursos c
    LEFT JOIN categorias_cursos cat ON cat.id = c.categoria_id
    LEFT JOIN aulas a ON a.curso_id = c.id AND a.status = 'publicado'
    LEFT JOIN progresso_colaborador pc ON pc.curso_id = c.id AND pc.colaborador_id = ?
    WHERE c.status = 'publicado'
    GROUP BY c.id
    HAVING total_aulas > 0
    ORDER BY ultimo_acesso DESC, c.titulo ASC
");
$stmt->execute([$colaborador_id]);
$cursos_com_progresso = $stmt->fetchAll();

// Calcula estatísticas gerais
$total_cursos = count($cursos_com_progresso);
$cursos_concluidos = 0;
$cursos_em_andamento = 0;
$total_tempo_assistido = 0;
$total_aulas_concluidas = 0;
$total_aulas = 0;

foreach ($cursos_com_progresso as $curso) {
    $total_aulas += $curso['total_aulas'];
    $total_aulas_concluidas += $curso['aulas_concluidas'];
    $total_tempo_assistido += $curso['tempo_total_assistido'] ?? 0;
    
    $percentual = $curso['total_aulas'] > 0 
        ? round(($curso['aulas_concluidas'] / $curso['total_aulas']) * 100, 0) 
        : 0;
    
    if ($percentual == 100) {
        $cursos_concluidos++;
    } elseif ($curso['aulas_concluidas'] > 0 || $curso['aulas_em_andamento'] > 0) {
        $cursos_em_andamento++;
    }
}

$percentual_geral = $total_aulas > 0 
    ? round(($total_aulas_concluidas / $total_aulas) * 100, 0) 
    : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Meu Progresso</h1>
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
                <li class="breadcrumb-item text-gray-900">Meu Progresso</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Estatísticas Gerais-->
        <div class="row g-5 mb-5">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Total de Cursos</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $total_cursos ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Concluídos</div>
                        <div class="text-success fw-bold fs-2x"><?= $cursos_concluidos ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Em Andamento</div>
                        <div class="text-primary fw-bold fs-2x"><?= $cursos_em_andamento ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Progresso Geral</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= $percentual_geral ?>%</div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Estatísticas Gerais-->
        
        <!--begin::Card Progresso por Curso-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="fw-bold m-0">Progresso por Curso</h3>
                </div>
            </div>
            <div class="card-body pt-0">
                <?php if (empty($cursos_com_progresso)): ?>
                <div class="text-center p-10">
                    <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <h3 class="text-gray-900 mb-2">Nenhum progresso registrado</h3>
                    <p class="text-muted">Comece a assistir aos cursos para ver seu progresso aqui.</p>
                    <a href="lms_meus_cursos.php" class="btn btn-primary">Ver Cursos Disponíveis</a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Curso</th>
                                <th class="min-w-100px">Categoria</th>
                                <th class="min-w-150px">Progresso</th>
                                <th class="min-w-100px">Aulas</th>
                                <th class="min-w-100px">Tempo</th>
                                <th class="min-w-100px">Último Acesso</th>
                                <th class="text-end min-w-100px">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php foreach ($cursos_com_progresso as $curso): ?>
                            <?php
                            $percentual = $curso['total_aulas'] > 0 
                                ? round(($curso['aulas_concluidas'] / $curso['total_aulas']) * 100, 0) 
                                : 0;
                            $horas = floor(($curso['tempo_total_assistido'] ?? 0) / 3600);
                            $minutos = floor((($curso['tempo_total_assistido'] ?? 0) % 3600) / 60);
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($curso['imagem_capa']): ?>
                                        <img src="<?= htmlspecialchars($curso['imagem_capa']) ?>" class="w-50px h-50px rounded me-3" alt="<?= htmlspecialchars($curso['titulo']) ?>">
                                        <?php else: ?>
                                        <div class="w-50px h-50px rounded bg-light-primary d-flex align-items-center justify-content-center me-3">
                                            <i class="ki-duotone ki-book fs-2 text-primary">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                            </i>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($curso['titulo']) ?></div>
                                            <?php if ($curso['descricao']): ?>
                                            <div class="text-muted fs-7"><?= htmlspecialchars(substr($curso['descricao'], 0, 50)) ?>...</div>
                                            <?php endif; ?>
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
                                    <div class="d-flex align-items-center">
                                        <div class="progress w-100 me-2" style="height: 20px;">
                                            <div class="progress-bar <?= $percentual == 100 ? 'bg-success' : 'bg-primary' ?>" role="progressbar" style="width: <?= $percentual ?>%">
                                                <?= $percentual ?>%
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-gray-900 fw-bold"><?= $curso['aulas_concluidas'] ?>/<?= $curso['total_aulas'] ?></span>
                                </td>
                                <td>
                                    <?php if ($horas > 0 || $minutos > 0): ?>
                                    <span class="text-gray-900">
                                        <?= $horas > 0 ? $horas . 'h ' : '' ?><?= $minutos ?>min
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($curso['ultimo_acesso']): ?>
                                    <span class="text-gray-900"><?= date('d/m/Y H:i', strtotime($curso['ultimo_acesso'])) ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">Nunca</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="lms_curso_detalhes.php?id=<?= $curso['id'] ?>" class="btn btn-sm btn-light">
                                        Ver Detalhes
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
        <!--end::Card Progresso por Curso-->
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

