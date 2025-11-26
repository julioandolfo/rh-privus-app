<?php
/**
 * Portal do Colaborador - Meus Certificados
 */

$page_title = 'Meus Certificados';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_meus_certificados.php');

require_once __DIR__ . '/../includes/lms_functions.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $usuario['colaborador_id'] ?? null;

if (!$colaborador_id) {
    redirect('dashboard.php', 'Colaborador não encontrado', 'error');
}

// Busca certificados do colaborador
$stmt = $pdo->prepare("
    SELECT cert.*,
           c.titulo as curso_titulo,
           c.descricao as curso_descricao,
           c.imagem_capa,
           c.duracao_estimada,
           col.nome as colaborador_nome,
           col.cpf as colaborador_cpf
    FROM certificados cert
    INNER JOIN cursos c ON c.id = cert.curso_id
    INNER JOIN colaboradores col ON col.id = cert.colaborador_id
    WHERE cert.colaborador_id = ?
    ORDER BY cert.data_emissao DESC
");
$stmt->execute([$colaborador_id]);
$certificados = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Meus Certificados</h1>
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
                <li class="breadcrumb-item text-gray-900">Meus Certificados</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?php if (empty($certificados)): ?>
        <!--begin::Empty State-->
        <div class="card">
            <div class="card-body text-center p-10">
                <i class="ki-duotone ki-award fs-3x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-900 mb-2">Nenhum certificado encontrado</h3>
                <p class="text-muted mb-5">Complete os cursos para receber seus certificados.</p>
                <a href="lms_meus_cursos.php" class="btn btn-primary">Ver Meus Cursos</a>
            </div>
        </div>
        <!--end::Empty State-->
        <?php else: ?>
        
        <!--begin::Certificados-->
        <div class="row g-5">
            <?php foreach ($certificados as $certificado): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header border-0 pt-9">
                        <?php if ($certificado['imagem_capa']): ?>
                        <img src="<?= htmlspecialchars($certificado['imagem_capa']) ?>" class="card-img-top" alt="<?= htmlspecialchars($certificado['curso_titulo']) ?>" style="height: 150px; object-fit: cover;">
                        <?php else: ?>
                        <div class="bg-light-success d-flex align-items-center justify-content-center" style="height: 150px;">
                            <i class="ki-duotone ki-award fs-1 text-success">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge badge-success">Certificado</span>
                            <?php if ($certificado['status'] == 'ativo'): ?>
                            <span class="badge badge-success ms-2">Válido</span>
                            <?php elseif ($certificado['status'] == 'expirado'): ?>
                            <span class="badge badge-warning ms-2">Expirado</span>
                            <?php else: ?>
                            <span class="badge badge-danger ms-2">Revogado</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="card-title mb-3"><?= htmlspecialchars($certificado['curso_titulo']) ?></h3>
                        <p class="text-muted mb-4"><?= htmlspecialchars(substr($certificado['curso_descricao'] ?? '', 0, 100)) ?>...</p>
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted fs-7">Código</span>
                                <span class="text-gray-900 fs-7 fw-bold"><?= htmlspecialchars($certificado['codigo_unico']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted fs-7">Data de Emissão</span>
                                <span class="text-gray-900 fs-7"><?= date('d/m/Y', strtotime($certificado['data_emissao'])) ?></span>
                            </div>
                            <?php if ($certificado['data_validade']): ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted fs-7">Válido até</span>
                                <span class="text-gray-900 fs-7"><?= date('d/m/Y', strtotime($certificado['data_validade'])) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($certificado['duracao_estimada']): ?>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted fs-7">Carga Horária</span>
                                <span class="text-gray-900 fs-7"><?= $certificado['duracao_estimada'] ?>h</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <?php if ($certificado['arquivo_pdf']): ?>
                            <a href="<?= htmlspecialchars($certificado['arquivo_pdf']) ?>" target="_blank" class="btn btn-sm btn-primary flex-grow-1">
                                <i class="ki-duotone ki-file-down fs-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Baixar PDF
                            </a>
                            <?php endif; ?>
                            <a href="lms_curso_detalhes.php?id=<?= $certificado['curso_id'] ?>" class="btn btn-sm btn-light">
                                Ver Curso
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <!--end::Certificados-->
        
        <?php endif; ?>
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

