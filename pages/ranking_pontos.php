<?php
/**
 * Ranking de Pontos - Página dedicada
 */

$page_title = 'Ranking de Pontos';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/pontuacao.php';

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Período do ranking
$periodo_ranking = $_GET['periodo'] ?? 'mes';
if (!in_array($periodo_ranking, ['dia', 'semana', 'mes', 'total'])) {
    $periodo_ranking = 'mes';
}

// Obtém pontos do usuário
$usuario_id = $usuario['id'] ?? null;
$colaborador_id = $usuario['colaborador_id'] ?? null;
$meus_pontos = obter_pontos($usuario_id, $colaborador_id);

// Obtém ranking
$ranking = obter_ranking_pontos($periodo_ranking, 20);

// Busca histórico de pontos do usuário (últimos 30 registros)
$historico = [];
try {
    $where = [];
    $params = [];
    
    if ($usuario_id) {
        $where[] = "ph.usuario_id = ?";
        $params[] = $usuario_id;
    } else if ($colaborador_id) {
        $where[] = "ph.colaborador_id = ?";
        $params[] = $colaborador_id;
    }
    
    if (!empty($where)) {
        $where_sql = implode(' OR ', $where);
        $stmt = $pdo->prepare("
            SELECT ph.*, pc.descricao as acao_descricao
            FROM pontos_historico ph
            LEFT JOIN pontos_config pc ON ph.acao = pc.acao
            WHERE $where_sql
            ORDER BY ph.created_at DESC
            LIMIT 30
        ");
        $stmt->execute($params);
        $historico = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $historico = [];
}

// Busca configuração de pontos (para mostrar como ganhar)
$acoes_pontos = [];
try {
    $stmt = $pdo->query("SELECT acao, descricao, pontos FROM pontos_config WHERE ativo = 1 ORDER BY pontos DESC");
    $acoes_pontos = $stmt->fetchAll();
} catch (PDOException $e) {
    $acoes_pontos = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Ranking de Pontos</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Dashboard</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-500 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Ranking de Pontos</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Row - Stats-->
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col - Total-->
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush bg-light-warning border-warning border border-dashed">
                    <div class="card-body d-flex align-items-center justify-content-between py-5">
                        <div>
                            <div class="fs-2hx fw-bold text-warning"><?= number_format($meus_pontos['pontos_totais'], 0, ',', '.') ?></div>
                            <div class="text-gray-600 fw-semibold fs-6">Pontos Totais</div>
                        </div>
                        <i class="ki-duotone ki-medal-star fs-3x text-warning opacity-50">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                        </i>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Mês-->
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush bg-light-primary border-primary border border-dashed">
                    <div class="card-body d-flex align-items-center justify-content-between py-5">
                        <div>
                            <div class="fs-2hx fw-bold text-primary"><?= number_format($meus_pontos['pontos_mes'], 0, ',', '.') ?></div>
                            <div class="text-gray-600 fw-semibold fs-6">Pontos no Mês</div>
                        </div>
                        <i class="ki-duotone ki-calendar fs-3x text-primary opacity-50">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Semana-->
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush bg-light-success border-success border border-dashed">
                    <div class="card-body d-flex align-items-center justify-content-between py-5">
                        <div>
                            <div class="fs-2hx fw-bold text-success"><?= number_format($meus_pontos['pontos_semana'], 0, ',', '.') ?></div>
                            <div class="text-gray-600 fw-semibold fs-6">Pontos na Semana</div>
                        </div>
                        <i class="ki-duotone ki-time fs-3x text-success opacity-50">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Dia-->
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush bg-light-info border-info border border-dashed">
                    <div class="card-body d-flex align-items-center justify-content-between py-5">
                        <div>
                            <div class="fs-2hx fw-bold text-info"><?= number_format($meus_pontos['pontos_dia'], 0, ',', '.') ?></div>
                            <div class="text-gray-600 fw-semibold fs-6">Pontos Hoje</div>
                        </div>
                        <i class="ki-duotone ki-sun fs-3x text-info opacity-50">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                            <span class="path5"></span>
                            <span class="path6"></span>
                            <span class="path7"></span>
                            <span class="path8"></span>
                        </i>
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        
        <div class="row g-5 g-xl-8">
            <!--begin::Col - Ranking-->
            <div class="col-xl-8">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">
                                <i class="ki-duotone ki-ranking fs-2 text-warning me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                Top 20 - Ranking
                            </span>
                        </h3>
                        <div class="card-toolbar">
                            <div class="btn-group" role="group">
                                <a href="?periodo=dia" class="btn btn-sm btn-light <?= $periodo_ranking === 'dia' ? 'active btn-primary' : '' ?>">Hoje</a>
                                <a href="?periodo=semana" class="btn btn-sm btn-light <?= $periodo_ranking === 'semana' ? 'active btn-primary' : '' ?>">Semana</a>
                                <a href="?periodo=mes" class="btn btn-sm btn-light <?= $periodo_ranking === 'mes' ? 'active btn-primary' : '' ?>">Mês</a>
                                <a href="?periodo=total" class="btn btn-sm btn-light <?= $periodo_ranking === 'total' ? 'active btn-primary' : '' ?>">Total</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-6">
                        <?php if (empty($ranking)): ?>
                            <div class="text-center text-muted py-10">
                                <i class="ki-duotone ki-medal-star fs-5x text-gray-300 mb-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                <p class="fs-6">Nenhum ranking disponível ainda.</p>
                                <p class="fs-7 text-gray-500">Complete ações para começar a ganhar pontos!</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase">
                                            <th class="min-w-50px">Posição</th>
                                            <th class="min-w-200px">Participante</th>
                                            <th class="min-w-100px text-end">Pontos</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-600">
                                        <?php 
                                        $posicao = 1;
                                        foreach ($ranking as $item): 
                                            $pontos_exibir = 0;
                                            if ($periodo_ranking === 'dia') $pontos_exibir = $item['pontos_dia'];
                                            elseif ($periodo_ranking === 'semana') $pontos_exibir = $item['pontos_semana'];
                                            elseif ($periodo_ranking === 'mes') $pontos_exibir = $item['pontos_mes'];
                                            else $pontos_exibir = $item['pontos_totais'];
                                            
                                            $is_me = false;
                                            if ($usuario_id && $item['usuario_id'] == $usuario_id) $is_me = true;
                                            if ($colaborador_id && $item['colaborador_id'] == $colaborador_id) $is_me = true;
                                        ?>
                                        <tr class="<?= $is_me ? 'bg-light-primary' : '' ?>">
                                            <td>
                                                <?php if ($posicao == 1): ?>
                                                    <span class="badge badge-light-warning fs-5 px-4 py-2">🥇 1º</span>
                                                <?php elseif ($posicao == 2): ?>
                                                    <span class="badge badge-light-info fs-5 px-4 py-2">🥈 2º</span>
                                                <?php elseif ($posicao == 3): ?>
                                                    <span class="badge badge-light-success fs-5 px-4 py-2">🥉 3º</span>
                                                <?php else: ?>
                                                    <span class="text-gray-600 fs-6 ms-3"><?= $posicao ?>º</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="symbol symbol-circle symbol-45px me-3">
                                                        <?php if (!empty($item['foto'])): ?>
                                                            <img src="../<?= htmlspecialchars($item['foto']) ?>" alt="<?= htmlspecialchars($item['nome']) ?>">
                                                        <?php else: ?>
                                                            <div class="symbol-label fs-4 fw-bold bg-<?= $posicao <= 3 ? 'warning' : 'primary' ?> text-white">
                                                                <?= strtoupper(substr($item['nome'], 0, 1)) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <span class="text-gray-800 fw-bold fs-6">
                                                            <?= htmlspecialchars($item['nome']) ?>
                                                            <?php if ($is_me): ?>
                                                                <span class="badge badge-primary ms-2">Você</span>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span class="text-muted fs-7"><?= htmlspecialchars($item['email'] ?? '') ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-gray-800 fw-bold fs-4"><?= number_format($pontos_exibir, 0, ',', '.') ?></span>
                                                <span class="text-muted fs-7 ms-1">pts</span>
                                            </td>
                                        </tr>
                                        <?php 
                                        $posicao++;
                                        endforeach; 
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Como Ganhar Pontos-->
            <div class="col-xl-4">
                <!--begin::Card - Como Ganhar-->
                <div class="card card-flush mb-5">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">
                                <i class="ki-duotone ki-gift fs-2 text-success me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                Como Ganhar Pontos
                            </span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <?php if (empty($acoes_pontos)): ?>
                            <div class="text-center text-muted py-5">
                                <p>Nenhuma ação configurada.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($acoes_pontos as $acao): ?>
                                <div class="d-flex align-items-center mb-4 p-3 bg-light-success rounded">
                                    <div class="flex-grow-1">
                                        <span class="fw-bold text-gray-800 fs-6"><?= htmlspecialchars($acao['descricao']) ?></span>
                                    </div>
                                    <div class="badge badge-success fs-6 fw-bold">
                                        +<?= number_format($acao['pontos'], 0, ',', '.') ?> pts
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Card-->
                
                <!--begin::Card - Histórico-->
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">
                                <i class="ki-duotone ki-document fs-2 text-info me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Seu Histórico
                            </span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <?php if (empty($historico)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="ki-duotone ki-document fs-3x text-gray-300 mb-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <p class="fs-7">Nenhum histórico ainda.</p>
                            </div>
                        <?php else: ?>
                            <div class="scroll-y me-n5 pe-5" style="max-height: 400px;">
                                <?php foreach ($historico as $h): ?>
                                    <div class="d-flex align-items-center mb-4">
                                        <span class="bullet bullet-vertical h-40px bg-success me-3"></span>
                                        <div class="flex-grow-1">
                                            <span class="text-gray-800 fw-semibold fs-6 d-block">
                                                <?= htmlspecialchars($h['acao_descricao'] ?? $h['acao']) ?>
                                            </span>
                                            <span class="text-muted fw-semibold fs-7">
                                                <?= date('d/m/Y', strtotime($h['data_registro'])) ?>
                                            </span>
                                        </div>
                                        <span class="badge badge-light-success fs-7 fw-bold">
                                            +<?= $h['pontos'] ?> pts
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Col-->
        </div>
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
