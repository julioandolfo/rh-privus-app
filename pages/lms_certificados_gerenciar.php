<?php
/**
 * Gerenciar Certificados
 */

$page_title = 'Gerenciar Certificados';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_certificados_gerenciar.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$filtro_curso = $_GET['curso'] ?? '';
$filtro_colaborador = $_GET['colaborador'] ?? '';
$filtro_status = $_GET['status'] ?? '';

$where = [];
$params = [];

if ($filtro_curso) {
    $where[] = "c.id = ?";
    $params[] = $filtro_curso;
}

if ($filtro_colaborador) {
    $where[] = "(col.nome_completo LIKE ? OR col.cpf LIKE ?)";
    $busca = "%{$filtro_colaborador}%";
    $params[] = $busca;
    $params[] = $busca;
}

if ($filtro_status) {
    $where[] = "cert.status = ?";
    $params[] = $filtro_status;
}

// Restrições por role
if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "col.empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    }
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Busca certificados
$sql = "
    SELECT cert.*,
           c.titulo as curso_titulo,
           col.nome_completo as colaborador_nome,
           col.cpf as colaborador_cpf,
           e.nome_fantasia as empresa_nome
    FROM certificados cert
    INNER JOIN cursos c ON c.id = cert.curso_id
    INNER JOIN colaboradores col ON col.id = cert.colaborador_id
    LEFT JOIN empresas e ON col.empresa_id = e.id
    $where_sql
    ORDER BY cert.data_emissao DESC
    LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$certificados = $stmt->fetchAll();

// Busca cursos para filtro
$stmt = $pdo->query("SELECT id, titulo FROM cursos WHERE status = 'publicado' ORDER BY titulo");
$cursos_filtro = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Gerenciar Certificados</h1>
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
                <li class="breadcrumb-item text-gray-900">Certificados</li>
            </ul>
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
                        <label class="form-label">Buscar Colaborador</label>
                        <input type="text" name="colaborador" class="form-control" placeholder="Nome ou CPF..." value="<?= htmlspecialchars($filtro_colaborador) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Curso</label>
                        <select name="curso" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($cursos_filtro as $curso): ?>
                            <option value="<?= $curso['id'] ?>" <?= $filtro_curso == $curso['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($curso['titulo']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="ativo" <?= $filtro_status == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="expirado" <?= $filtro_status == 'expirado' ? 'selected' : '' ?>>Expirado</option>
                            <option value="revogado" <?= $filtro_status == 'revogado' ? 'selected' : '' ?>>Revogado</option>
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
                    <span class="card-label fw-bold fs-3 mb-1">Certificados Emitidos</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($certificados) ?> certificado(s)</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Colaborador</th>
                                <th class="min-w-200px">Curso</th>
                                <th class="min-w-150px">Código</th>
                                <th class="min-w-100px">Data Emissão</th>
                                <th class="min-w-100px">Validade</th>
                                <th class="min-w-100px">Status</th>
                                <th class="min-w-100px text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php if (empty($certificados)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-10">
                                    <div class="text-muted">Nenhum certificado encontrado</div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($certificados as $cert): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($cert['colaborador_nome']) ?></div>
                                        <div class="text-muted fs-7"><?= htmlspecialchars($cert['colaborador_cpf']) ?></div>
                                        <?php if ($cert['empresa_nome']): ?>
                                        <div class="text-muted fs-7"><?= htmlspecialchars($cert['empresa_nome']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($cert['curso_titulo']) ?></td>
                                    <td>
                                        <code class="text-primary"><?= htmlspecialchars($cert['codigo_unico']) ?></code>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($cert['data_emissao'])) ?></td>
                                    <td>
                                        <?php if ($cert['data_validade']): ?>
                                        <?= date('d/m/Y', strtotime($cert['data_validade'])) ?>
                                        <?php else: ?>
                                        <span class="text-muted">Sem validade</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_classes = [
                                            'ativo' => 'success',
                                            'expirado' => 'warning',
                                            'revogado' => 'danger'
                                        ];
                                        $status_class = $status_classes[$cert['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $status_class ?>"><?= ucfirst($cert['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($cert['arquivo_pdf']): ?>
                                        <a href="../<?= htmlspecialchars($cert['arquivo_pdf']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="ki-duotone ki-file-down fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </a>
                                        <?php endif; ?>
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

