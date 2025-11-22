<?php
/**
 * Página de Aniversariantes
 */

$page_title = 'Aniversariantes';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('dashboard.php'); // Usa mesma permissão do dashboard

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca setor do gestor se necessário
$setor_id = null;
if ($usuario['role'] === 'GESTOR') {
    $stmt = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario['id']]);
    $user_data = $stmt->fetch();
    $setor_id = $user_data['setor_id'] ?? 0;
}

// Busca todos os aniversariantes (próximos 365 dias)
$hoje = date('Y-m-d');
$ano_atual = date('Y');
$mes_atual = date('m');
$dia_atual = date('d');

try {
    if ($usuario['role'] === 'ADMIN') {
        $stmt = $pdo->prepare("
            SELECT c.id, c.nome_completo, c.foto, c.data_nascimento,
                   e.nome_fantasia as empresa_nome,
                   s.nome_setor,
                   car.nome_cargo,
                   DATE_FORMAT(c.data_nascimento, '%m-%d') as mes_dia,
                   DAY(c.data_nascimento) as dia,
                   MONTH(c.data_nascimento) as mes
            FROM colaboradores c
            LEFT JOIN empresas e ON c.empresa_id = e.id
            LEFT JOIN setores s ON c.setor_id = s.id
            LEFT JOIN cargos car ON c.cargo_id = car.id
            WHERE c.status = 'ativo' 
            AND c.data_nascimento IS NOT NULL
            ORDER BY MONTH(c.data_nascimento), DAY(c.data_nascimento)
        ");
        $stmt->execute();
    } elseif ($usuario['role'] === 'RH') {
        $stmt = $pdo->prepare("
            SELECT c.id, c.nome_completo, c.foto, c.data_nascimento,
                   e.nome_fantasia as empresa_nome,
                   s.nome_setor,
                   car.nome_cargo,
                   DATE_FORMAT(c.data_nascimento, '%m-%d') as mes_dia,
                   DAY(c.data_nascimento) as dia,
                   MONTH(c.data_nascimento) as mes
            FROM colaboradores c
            LEFT JOIN empresas e ON c.empresa_id = e.id
            LEFT JOIN setores s ON c.setor_id = s.id
            LEFT JOIN cargos car ON c.cargo_id = car.id
            WHERE c.empresa_id = ? 
            AND c.status = 'ativo'
            AND c.data_nascimento IS NOT NULL
            ORDER BY MONTH(c.data_nascimento), DAY(c.data_nascimento)
        ");
        $stmt->execute([$usuario['empresa_id']]);
    } elseif ($usuario['role'] === 'GESTOR') {
        $stmt = $pdo->prepare("
            SELECT c.id, c.nome_completo, c.foto, c.data_nascimento,
                   e.nome_fantasia as empresa_nome,
                   s.nome_setor,
                   car.nome_cargo,
                   DATE_FORMAT(c.data_nascimento, '%m-%d') as mes_dia,
                   DAY(c.data_nascimento) as dia,
                   MONTH(c.data_nascimento) as mes
            FROM colaboradores c
            LEFT JOIN empresas e ON c.empresa_id = e.id
            LEFT JOIN setores s ON c.setor_id = s.id
            LEFT JOIN cargos car ON c.cargo_id = car.id
            WHERE c.setor_id = ? 
            AND c.status = 'ativo'
            AND c.data_nascimento IS NOT NULL
            ORDER BY MONTH(c.data_nascimento), DAY(c.data_nascimento)
        ");
        $stmt->execute([$setor_id]);
    } else {
        $aniversariantes = [];
    }
    
    $aniversariantes = isset($stmt) ? $stmt->fetchAll() : [];
    
    // Processa aniversários para calcular dias até o aniversário
    foreach ($aniversariantes as &$aniv) {
        $mes_dia = date('m-d', strtotime($aniv['data_nascimento']));
        $data_aniversario_ano = $ano_atual . '-' . $mes_dia;
        
        if (strtotime($data_aniversario_ano) < strtotime($hoje)) {
            $data_aniversario_ano = ($ano_atual + 1) . '-' . $mes_dia;
        }
        
        $dias_ate = (strtotime($data_aniversario_ano) - strtotime($hoje)) / (60 * 60 * 24);
        $aniv['dias_ate'] = $dias_ate;
        $aniv['data_formatada'] = date('d/m', strtotime($data_aniversario_ano));
        $aniv['data_completa'] = date('d/m/Y', strtotime($data_aniversario_ano));
        
        // Calcula idade
        $ano_nascimento = date('Y', strtotime($aniv['data_nascimento']));
        $idade = $ano_atual - $ano_nascimento;
        if (strtotime($data_aniversario_ano) < strtotime($hoje)) {
            $idade++;
        }
        $aniv['idade'] = $idade;
    }
    unset($aniv);
    
    // Agrupa por mês
    $aniversariantes_por_mes = [];
    foreach ($aniversariantes as $aniv) {
        $mes_nome = date('F', strtotime($aniv['data_nascimento']));
        $mes_num = date('m', strtotime($aniv['data_nascimento']));
        if (!isset($aniversariantes_por_mes[$mes_num])) {
            $aniversariantes_por_mes[$mes_num] = [
                'mes_nome' => $mes_nome,
                'mes_num' => $mes_num,
                'aniversariantes' => []
            ];
        }
        $aniversariantes_por_mes[$mes_num]['aniversariantes'][] = $aniv;
    }
    
} catch (PDOException $e) {
    $error = 'Erro ao carregar dados: ' . $e->getMessage();
    $aniversariantes = [];
    $aniversariantes_por_mes = [];
}
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Aniversariantes</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Aniversariantes</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger d-flex align-items-center p-5 mb-10">
                <i class="ki-duotone ki-shield-cross fs-2hx text-danger me-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="d-flex flex-column">
                    <h4 class="mb-1 text-danger">Erro</h4>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <!--begin::Card-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Todos os Aniversariantes</span>
                    <span class="text-muted fw-semibold fs-7">Lista completa de aniversariantes do ano</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <?php if (empty($aniversariantes)): ?>
                    <div class="text-center text-muted py-10">
                        <i class="ki-duotone ki-cake fs-3x text-muted mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <p class="fs-5">Nenhum aniversariante encontrado.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $meses_pt = [
                        'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
                        'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
                        'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
                        'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
                    ];
                    ksort($aniversariantes_por_mes);
                    ?>
                    
                    <?php foreach ($aniversariantes_por_mes as $mes_data): ?>
                        <div class="mb-10">
                            <h4 class="text-gray-800 fw-bold fs-2 mb-5">
                                <?= $meses_pt[$mes_data['mes_nome']] ?? $mes_data['mes_nome'] ?>
                            </h4>
                            
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th class="min-w-200px">Colaborador</th>
                                            <th class="min-w-150px">Empresa</th>
                                            <th class="min-w-150px">Setor</th>
                                            <th class="min-w-100px">Data</th>
                                            <th class="min-w-80px">Idade</th>
                                            <th class="min-w-100px text-end">Dias</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($mes_data['aniversariantes'] as $aniv): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="symbol symbol-circle symbol-40px me-3">
                                                        <?php if (!empty($aniv['foto'])): ?>
                                                            <img alt="<?= htmlspecialchars($aniv['nome_completo']) ?>" src="../<?= htmlspecialchars($aniv['foto']) ?>" />
                                                        <?php else: ?>
                                                            <div class="symbol-label fs-2 fw-semibold bg-success text-white">
                                                                <?= strtoupper(substr($aniv['nome_completo'], 0, 1)) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <a href="colaborador_view.php?id=<?= $aniv['id'] ?>" class="text-gray-900 fw-bold fs-6 text-hover-primary">
                                                        <?= htmlspecialchars($aniv['nome_completo']) ?>
                                                    </a>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-gray-600 fs-7"><?= htmlspecialchars($aniv['empresa_nome'] ?? '-') ?></span>
                                            </td>
                                            <td>
                                                <span class="text-gray-600 fs-7"><?= htmlspecialchars($aniv['nome_setor'] ?? '-') ?></span>
                                            </td>
                                            <td>
                                                <span class="text-gray-800 fw-semibold"><?= $aniv['data_formatada'] ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-light-info fs-7"><?= $aniv['idade'] ?> anos</span>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($aniv['dias_ate'] == 0): ?>
                                                    <span class="badge badge-success fs-7">Hoje! 🎉</span>
                                                <?php elseif ($aniv['dias_ate'] == 1): ?>
                                                    <span class="badge badge-warning fs-7">Amanhã</span>
                                                <?php elseif ($aniv['dias_ate'] <= 7): ?>
                                                    <span class="badge badge-light-primary fs-7"><?= $aniv['dias_ate'] ?> dias</span>
                                                <?php else: ?>
                                                    <span class="text-muted fs-7"><?= $aniv['dias_ate'] ?> dias</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

