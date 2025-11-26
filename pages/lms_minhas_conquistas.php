<?php
/**
 * Portal do Colaborador - Minhas Conquistas (Badges)
 */

$page_title = 'Minhas Conquistas';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_minhas_conquistas.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $usuario['colaborador_id'] ?? null;

if (!$colaborador_id) {
    redirect('dashboard.php', 'Colaborador não encontrado', 'error');
}

// Busca badges do colaborador
$stmt = $pdo->prepare("
    SELECT cb.*,
           b.nome as badge_nome,
           b.descricao as badge_descricao,
           b.icone as badge_icone,
           b.cor as badge_cor,
           c.titulo as curso_titulo
    FROM colaborador_badges cb
    INNER JOIN badges_conquistas b ON b.id = cb.badge_id
    LEFT JOIN cursos c ON c.id = cb.curso_id
    WHERE cb.colaborador_id = ?
    ORDER BY cb.data_conquista DESC
");
$stmt->execute([$colaborador_id]);
$badges = $stmt->fetchAll();

// Busca badges disponíveis não conquistados
$stmt = $pdo->prepare("
    SELECT b.*
    FROM badges_conquistas b
    WHERE b.ativo = 1
    AND b.id NOT IN (
        SELECT badge_id FROM colaborador_badges WHERE colaborador_id = ?
    )
    ORDER BY b.nome ASC
");
$stmt->execute([$colaborador_id]);
$badges_disponiveis = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Minhas Conquistas</h1>
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
                <li class="breadcrumb-item text-gray-900">Minhas Conquistas</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Estatísticas-->
        <div class="row g-5 mb-5">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Conquistas</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= count($badges) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Disponíveis</div>
                        <div class="text-gray-900 fw-bold fs-2x"><?= count($badges_disponiveis) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center p-5">
                        <div class="text-gray-400 fw-bold fs-6 mb-2">Progresso</div>
                        <div class="text-gray-900 fw-bold fs-2x">
                            <?php
                            $total = count($badges) + count($badges_disponiveis);
                            $percentual = $total > 0 ? round((count($badges) / $total) * 100, 0) : 0;
                            echo $percentual . '%';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Estatísticas-->
        
        <?php if (!empty($badges)): ?>
        <!--begin::Badges Conquistados-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Conquistas Desbloqueadas</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($badges) ?> conquista(s)</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="row g-5">
                    <?php foreach ($badges as $badge): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-success">
                            <div class="card-body text-center p-5">
                                <?php if ($badge['badge_icone']): ?>
                                <i class="ki-duotone ki-<?= htmlspecialchars($badge['badge_icone']) ?> fs-3x mb-4" style="color: <?= htmlspecialchars($badge['badge_cor'] ?? '#ffc700') ?>;">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <?php else: ?>
                                <div class="mb-4" style="width: 80px; height: 80px; margin: 0 auto; background-color: <?= htmlspecialchars($badge['badge_cor'] ?? '#ffc700') ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="ki-duotone ki-award fs-2x text-white">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                                <?php endif; ?>
                                
                                <h4 class="fw-bold mb-2"><?= htmlspecialchars($badge['badge_nome']) ?></h4>
                                <?php if ($badge['badge_descricao']): ?>
                                <p class="text-muted mb-3"><?= htmlspecialchars($badge['badge_descricao']) ?></p>
                                <?php endif; ?>
                                
                                <?php if ($badge['curso_titulo']): ?>
                                <div class="badge badge-light mb-3"><?= htmlspecialchars($badge['curso_titulo']) ?></div>
                                <?php endif; ?>
                                
                                <div class="text-muted fs-7">
                                    Conquistado em <?= date('d/m/Y', strtotime($badge['data_conquista'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <!--end::Badges Conquistados-->
        <?php endif; ?>
        
        <?php if (!empty($badges_disponiveis)): ?>
        <!--begin::Badges Disponíveis-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Conquistas Disponíveis</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($badges_disponiveis) ?> conquista(s) para desbloquear</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="row g-5">
                    <?php foreach ($badges_disponiveis as $badge): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 opacity-50">
                            <div class="card-body text-center p-5">
                                <?php if ($badge['icone']): ?>
                                <i class="ki-duotone ki-<?= htmlspecialchars($badge['icone']) ?> fs-3x mb-4" style="color: #ccc;">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <?php else: ?>
                                <div class="mb-4" style="width: 80px; height: 80px; margin: 0 auto; background-color: #ccc; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="ki-duotone ki-award fs-2x text-white">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                                <?php endif; ?>
                                
                                <h4 class="fw-bold mb-2 text-muted"><?= htmlspecialchars($badge['nome']) ?></h4>
                                <?php if ($badge['descricao']): ?>
                                <p class="text-muted mb-3"><?= htmlspecialchars($badge['descricao']) ?></p>
                                <?php endif; ?>
                                
                                <div class="badge badge-secondary">Bloqueado</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <!--end::Badges Disponíveis-->
        <?php endif; ?>
        
        <?php if (empty($badges) && empty($badges_disponiveis)): ?>
        <div class="card">
            <div class="card-body text-center p-10">
                <i class="ki-duotone ki-award fs-3x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-900 mb-2">Nenhuma conquista disponível</h3>
                <p class="text-muted">Complete cursos para desbloquear conquistas!</p>
                <a href="lms_meus_cursos.php" class="btn btn-primary">Ver Meus Cursos</a>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

