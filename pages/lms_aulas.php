<?php
/**
 * Gerenciar Aulas de um Curso
 */

$page_title = 'Gerenciar Aulas';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_aulas.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$curso_id = (int)($_GET['curso_id'] ?? 0);

if (!$curso_id) {
    redirect('lms_cursos.php', 'Curso não encontrado', 'error');
}

// Busca curso
$stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
$stmt->execute([$curso_id]);
$curso = $stmt->fetch();

if (!$curso) {
    redirect('lms_cursos.php', 'Curso não encontrado', 'error');
}

// Valida acesso
if ($usuario['role'] === 'RH' && $curso['empresa_id']) {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        if (!in_array($curso['empresa_id'], $usuario['empresas_ids'])) {
            redirect('lms_cursos.php', 'Você não tem permissão para gerenciar aulas deste curso', 'error');
        }
    }
}

// Processa reordenação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reordenar') {
    $ordens = $_POST['ordem'] ?? [];
    try {
        $pdo->beginTransaction();
        foreach ($ordens as $aula_id => $ordem) {
            $stmt = $pdo->prepare("UPDATE aulas SET ordem = ? WHERE id = ? AND curso_id = ?");
            $stmt->execute([(int)$ordem, (int)$aula_id, $curso_id]);
        }
        $pdo->commit();
        redirect('lms_aulas.php?curso_id=' . $curso_id, 'Ordem das aulas atualizada!', 'success');
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao reordenar aulas: " . $e->getMessage());
        redirect('lms_aulas.php?curso_id=' . $curso_id, 'Erro ao reordenar aulas.', 'error');
    }
}

// Busca aulas
$stmt = $pdo->prepare("SELECT * FROM aulas WHERE curso_id = ? ORDER BY ordem ASC, id ASC");
$stmt->execute([$curso_id]);
$aulas = $stmt->fetchAll();

$page_title = 'Aulas: ' . $curso['titulo'];
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Aulas do Curso</h1>
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
                <li class="breadcrumb-item text-muted">
                    <a href="lms_curso_view.php?id=<?= $curso_id ?>" class="text-muted text-hover-primary"><?= htmlspecialchars($curso['titulo']) ?></a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Aulas</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2 gap-2">
            <a href="lms_curso_view.php?id=<?= $curso_id ?>" class="btn btn-light">Voltar</a>
            <a href="lms_aula_add.php?curso_id=<?= $curso_id ?>" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Aula
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
                    <span class="card-label fw-bold fs-3 mb-1">Aulas do Curso: <?= htmlspecialchars($curso['titulo']) ?></span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($aulas) ?> aula(s)</span>
                </h3>
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
                <form method="POST" id="formReordenar">
                    <input type="hidden" name="action" value="reordenar">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th class="min-w-50px">Ordem</th>
                                    <th class="min-w-200px">Aula</th>
                                    <th class="min-w-100px">Tipo</th>
                                    <th class="min-w-100px">Duração</th>
                                    <th class="min-w-100px">Status</th>
                                    <th class="min-w-100px text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold" id="aulasList">
                                <?php foreach ($aulas as $index => $aula): ?>
                                <tr data-aula-id="<?= $aula['id'] ?>">
                                    <td>
                                        <input type="number" name="ordem[<?= $aula['id'] ?>]" class="form-control form-control-sm" 
                                               value="<?= $aula['ordem'] ?: ($index + 1) ?>" min="1" style="width: 70px;">
                                    </td>
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
                                        <a href="lms_aula_edit.php?id=<?= $aula['id'] ?>&curso_id=<?= $curso_id ?>" class="btn btn-sm btn-primary me-2">
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
                    <div class="d-flex justify-content-end mt-5">
                        <button type="submit" class="btn btn-primary">Salvar Ordem</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

