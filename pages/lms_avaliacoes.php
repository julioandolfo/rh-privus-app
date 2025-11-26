<?php
/**
 * Gerenciar Avaliações/Quizzes
 */

$page_title = 'Avaliações';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_avaliacoes.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$curso_id = (int)($_GET['curso_id'] ?? 0);

// Busca avaliações
$where = [];
$params = [];

if ($curso_id) {
    $where[] = "a.curso_id = ?";
    $params[] = $curso_id;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT a.*,
           c.titulo as curso_titulo,
           au.titulo as aula_titulo
    FROM avaliacoes a
    INNER JOIN cursos c ON c.id = a.curso_id
    LEFT JOIN aulas au ON au.id = a.aula_id
    $where_sql
    ORDER BY a.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$avaliacoes = $stmt->fetchAll();

// Busca cursos para filtro
$stmt = $pdo->query("SELECT id, titulo FROM cursos WHERE status = 'publicado' ORDER BY titulo");
$cursos_filtro = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Avaliações</h1>
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
                <li class="breadcrumb-item text-gray-900">Avaliações</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2 gap-2">
            <form method="GET" class="d-flex gap-2">
                <select name="curso_id" class="form-select" style="width: 200px;" onchange="this.form.submit()">
                    <option value="">Todos os cursos</option>
                    <?php foreach ($cursos_filtro as $curso): ?>
                    <option value="<?= $curso['id'] ?>" <?= $curso_id == $curso['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($curso['titulo']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="lms_avaliacao_add.php<?= $curso_id ? '?curso_id=' . $curso_id : '' ?>" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Avaliação
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Avaliações</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($avaliacoes) ?> avaliação(ões)</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <?php if (empty($avaliacoes)): ?>
                <div class="text-center p-10">
                    <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <h3 class="text-gray-900 mb-2">Nenhuma avaliação cadastrada</h3>
                    <p class="text-muted mb-5">Crie avaliações para seus cursos.</p>
                    <a href="lms_avaliacao_add.php<?= $curso_id ? '?curso_id=' . $curso_id : '' ?>" class="btn btn-primary">Criar Primeira Avaliação</a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Avaliação</th>
                                <th class="min-w-150px">Curso</th>
                                <th class="min-w-150px">Aula</th>
                                <th class="min-w-100px">Tipo</th>
                                <th class="min-w-100px">Pontuação Mínima</th>
                                <th class="min-w-100px">Tentativas</th>
                                <th class="min-w-100px text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php foreach ($avaliacoes as $avaliacao): ?>
                            <?php
                            $config = json_decode($avaliacao['configuracao'], true) ?: [];
                            $total_questoes = isset($config['questoes']) ? count($config['questoes']) : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($avaliacao['titulo']) ?></div>
                                    <?php if ($avaliacao['descricao']): ?>
                                    <div class="text-muted fs-7"><?= htmlspecialchars(substr($avaliacao['descricao'], 0, 60)) ?>...</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($avaliacao['curso_titulo']) ?></td>
                                <td>
                                    <?php if ($avaliacao['aula_titulo']): ?>
                                    <?= htmlspecialchars($avaliacao['aula_titulo']) ?>
                                    <?php else: ?>
                                    <span class="text-muted">Curso completo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-light"><?= ucfirst($avaliacao['tipo']) ?></span>
                                </td>
                                <td><?= number_format($avaliacao['pontuacao_minima'], 1) ?>%</td>
                                <td><?= $avaliacao['tentativas_maximas'] ?> tentativa(s)</td>
                                <td class="text-end">
                                    <a href="lms_avaliacao_add.php?id=<?= $avaliacao['id'] ?>&curso_id=<?= $avaliacao['curso_id'] ?>" class="btn btn-sm btn-primary">
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
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

