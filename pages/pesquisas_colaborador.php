<?php
/**
 * Página de Pesquisas para Colaboradores
 */

$page_title = 'Minhas Pesquisas';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $usuario['colaborador_id'] ?? null;

if (!$colaborador_id) {
    redirect('dashboard.php', 'Você não está vinculado a um colaborador', 'error');
}

// Filtros
$filtro_status = $_GET['filtro_status'] ?? 'todas'; // 'todas', 'pendentes', 'respondidas'
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// Busca pesquisas de satisfação
$where_satisfacao = ["ps.status = 'ativa'"];
$params_satisfacao = [$colaborador_id];

if ($filtro_status === 'pendentes') {
    $where_satisfacao[] = "pse.respondida = 0";
} elseif ($filtro_status === 'respondidas') {
    $where_satisfacao[] = "pse.respondida = 1";
}

// Filtro de período
if ($data_inicio) {
    $where_satisfacao[] = "DATE(ps.data_inicio) >= ?";
    $params_satisfacao[] = $data_inicio;
}
if ($data_fim) {
    $where_satisfacao[] = "(ps.data_fim IS NULL OR DATE(ps.data_fim) <= ?)";
    $params_satisfacao[] = $data_fim;
}

$sql_satisfacao = "
    SELECT ps.*,
           COALESCE(pse.respondida, 0) as respondida,
           pse.token_resposta
    FROM pesquisas_satisfacao ps
    INNER JOIN pesquisas_satisfacao_envios pse ON pse.pesquisa_id = ps.id AND pse.colaborador_id = ?
    WHERE " . implode(' AND ', $where_satisfacao) . "
    ORDER BY ps.created_at DESC
";

// Verifica se a coluna token_resposta existe, se não, remove do SELECT
try {
    $stmt_check = $pdo->query("SHOW COLUMNS FROM pesquisas_satisfacao_envios LIKE 'token_resposta'");
    $col_exists = $stmt_check->rowCount() > 0;
    if (!$col_exists) {
        // Se não existe, remove do SELECT
        $sql_satisfacao = "
            SELECT ps.*,
                   COALESCE(pse.respondida, 0) as respondida,
                   NULL as token_resposta
            FROM pesquisas_satisfacao ps
            INNER JOIN pesquisas_satisfacao_envios pse ON pse.pesquisa_id = ps.id AND pse.colaborador_id = ?
            WHERE " . implode(' AND ', $where_satisfacao) . "
            ORDER BY ps.created_at DESC
        ";
    }
} catch (Exception $e) {
    // Se der erro ao verificar, assume que não existe e remove do SELECT
    $sql_satisfacao = "
        SELECT ps.*,
               COALESCE(pse.respondida, 0) as respondida,
               NULL as token_resposta
        FROM pesquisas_satisfacao ps
        INNER JOIN pesquisas_satisfacao_envios pse ON pse.pesquisa_id = ps.id AND pse.colaborador_id = ?
        WHERE " . implode(' AND ', $where_satisfacao) . "
        ORDER BY ps.created_at DESC
    ";
}
$params_satisfacao_final = $params_satisfacao;
$stmt_satisfacao = $pdo->prepare($sql_satisfacao);
$stmt_satisfacao->execute($params_satisfacao_final);
$pesquisas_satisfacao = $stmt_satisfacao->fetchAll();

// Busca pesquisas rápidas
$where_rapida = ["pr.status = 'ativa'"];
$params_rapida = [$colaborador_id];

if ($filtro_status === 'pendentes') {
    $where_rapida[] = "pre.respondida = 0";
} elseif ($filtro_status === 'respondidas') {
    $where_rapida[] = "pre.respondida = 1";
}

// Filtro de período
if ($data_inicio) {
    $where_rapida[] = "DATE(pr.data_inicio) >= ?";
    $params_rapida[] = $data_inicio;
}
if ($data_fim) {
    $where_rapida[] = "(pr.data_fim IS NULL OR DATE(pr.data_fim) <= ?)";
    $params_rapida[] = $data_fim;
}

$sql_rapida = "
    SELECT pr.*,
           COALESCE(pre.respondida, 0) as respondida,
           pre.token_resposta
    FROM pesquisas_rapidas pr
    INNER JOIN pesquisas_rapidas_envios pre ON pre.pesquisa_id = pr.id AND pre.colaborador_id = ?
    WHERE " . implode(' AND ', $where_rapida) . "
    ORDER BY pr.created_at DESC
";

// Verifica se a coluna token_resposta existe, se não, remove do SELECT
try {
    $stmt_check = $pdo->query("SHOW COLUMNS FROM pesquisas_rapidas_envios LIKE 'token_resposta'");
    $col_exists = $stmt_check->rowCount() > 0;
    if (!$col_exists) {
        // Se não existe, remove do SELECT
        $sql_rapida = "
            SELECT pr.*,
                   COALESCE(pre.respondida, 0) as respondida,
                   NULL as token_resposta
            FROM pesquisas_rapidas pr
            INNER JOIN pesquisas_rapidas_envios pre ON pre.pesquisa_id = pr.id AND pre.colaborador_id = ?
            WHERE " . implode(' AND ', $where_rapida) . "
            ORDER BY pr.created_at DESC
        ";
    }
} catch (Exception $e) {
    // Se der erro ao verificar, assume que não existe e remove do SELECT
    $sql_rapida = "
        SELECT pr.*,
               COALESCE(pre.respondida, 0) as respondida,
               NULL as token_resposta
        FROM pesquisas_rapidas pr
        INNER JOIN pesquisas_rapidas_envios pre ON pre.pesquisa_id = pr.id AND pre.colaborador_id = ?
        WHERE " . implode(' AND ', $where_rapida) . "
        ORDER BY pr.created_at DESC
    ";
}
$params_rapida_final = $params_rapida;
$stmt_rapida = $pdo->prepare($sql_rapida);
$stmt_rapida->execute($params_rapida_final);
$pesquisas_rapidas = $stmt_rapida->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Minhas Pesquisas</h2>
                        </div>
                        <div class="card-toolbar">
                            <form method="GET" id="form-filtros" class="d-flex align-items-center gap-3">
                                <div>
                                    <label class="form-label small">Status</label>
                                    <select name="filtro_status" class="form-select form-select-solid form-select-sm" id="filtro-status" style="width: 150px;">
                                        <option value="todas" <?= $filtro_status === 'todas' ? 'selected' : '' ?>>Todas</option>
                                        <option value="pendentes" <?= $filtro_status === 'pendentes' ? 'selected' : '' ?>>Pendentes</option>
                                        <option value="respondidas" <?= $filtro_status === 'respondidas' ? 'selected' : '' ?>>Respondidas</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label small">Data Início</label>
                                    <input type="date" name="data_inicio" class="form-control form-control-solid form-control-sm" value="<?= htmlspecialchars($data_inicio) ?>" style="width: 150px;">
                                </div>
                                <div>
                                    <label class="form-label small">Data Fim</label>
                                    <input type="date" name="data_fim" class="form-control form-control-solid form-control-sm" value="<?= htmlspecialchars($data_fim) ?>" style="width: 150px;">
                                </div>
                                <div class="d-flex align-items-end">
                                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                                    <?php if ($data_inicio || $data_fim || $filtro_status !== 'todas'): ?>
                                    <a href="pesquisas_colaborador.php" class="btn btn-sm btn-light ms-2">Limpar</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <!--begin::Tabs-->
                        <ul class="nav nav-custom nav-tabs nav-line-tabs nav-line-tabs-2x border-0 fs-4 fw-semibold mb-8">
                            <!--begin::Tab item-->
                            <li class="nav-item">
                                <a class="nav-link text-active-primary pb-4 active" data-bs-toggle="tab" href="#kt_pesquisas_satisfacao_tab">
                                    <i class="ki-duotone ki-chart-simple fs-2 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                    </i>
                                    Pesquisas de Satisfação
                                    <span class="badge badge-circle badge-light-primary ms-2"><?= count($pesquisas_satisfacao) ?></span>
                                </a>
                            </li>
                            <!--end::Tab item-->
                            <!--begin::Tab item-->
                            <li class="nav-item">
                                <a class="nav-link text-active-primary pb-4" data-bs-toggle="tab" href="#kt_pesquisas_rapidas_tab">
                                    <i class="ki-duotone ki-speedometer fs-2 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Pesquisas Rápidas
                                    <span class="badge badge-circle badge-light-primary ms-2"><?= count($pesquisas_rapidas) ?></span>
                                </a>
                            </li>
                            <!--end::Tab item-->
                        </ul>
                        <!--end::Tabs-->
                        
                        <!--begin::Tab content-->
                        <div class="tab-content">
                            <!--begin::Tab pane - Pesquisas de Satisfação-->
                            <div class="tab-pane fade show active" id="kt_pesquisas_satisfacao_tab" role="tabpanel">
                                <?php if (empty($pesquisas_satisfacao)): ?>
                                <div class="alert alert-info d-flex align-items-center p-5">
                                    <i class="ki-duotone ki-information-5 fs-2hx text-primary me-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <div class="d-flex flex-column">
                                        <h4 class="mb-1">Nenhuma pesquisa encontrada</h4>
                                        <span>Não há pesquisas de satisfação disponíveis no momento.</span>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                        <thead>
                                            <tr class="fw-bold text-muted">
                                                <th class="min-w-150px">Título</th>
                                                <th class="min-w-200px">Descrição</th>
                                                <th class="min-w-150px">Período</th>
                                                <th class="min-w-100px">Status</th>
                                                <th class="min-w-100px text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pesquisas_satisfacao as $pesquisa): ?>
                                            <tr>
                                                <td>
                                                    <strong class="text-gray-800"><?= htmlspecialchars($pesquisa['titulo']) ?></strong>
                                                </td>
                                                <td>
                                                    <span class="text-gray-600">
                                                        <?= htmlspecialchars(substr($pesquisa['descricao'] ?? '', 0, 100)) ?>
                                                        <?= strlen($pesquisa['descricao'] ?? '') > 100 ? '...' : '' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-gray-600">
                                                        <?= date('d/m/Y', strtotime($pesquisa['data_inicio'])) ?>
                                                        <?php if ($pesquisa['data_fim']): ?>
                                                        <br><small class="text-muted">até <?= date('d/m/Y', strtotime($pesquisa['data_fim'])) ?></small>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($pesquisa['respondida']): ?>
                                                    <span class="badge badge-success">Respondida</span>
                                                    <?php else: ?>
                                                    <span class="badge badge-warning">Pendente</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($pesquisa['respondida']): ?>
                                                    <span class="text-muted small">Já respondida</span>
                                                    <?php elseif (!empty($pesquisa['token_resposta'])): ?>
                                                    <a href="responder_pesquisa.php?token=<?= htmlspecialchars($pesquisa['token_resposta']) ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="ki-duotone ki-pencil fs-6">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Responder
                                                    </a>
                                                    <?php else: ?>
                                                    <span class="text-muted small">Token não disponível</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                            <!--end::Tab pane-->
                            
                            <!--begin::Tab pane - Pesquisas Rápidas-->
                            <div class="tab-pane fade" id="kt_pesquisas_rapidas_tab" role="tabpanel">
                                <?php if (empty($pesquisas_rapidas)): ?>
                                <div class="alert alert-info d-flex align-items-center p-5">
                                    <i class="ki-duotone ki-information-5 fs-2hx text-primary me-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <div class="d-flex flex-column">
                                        <h4 class="mb-1">Nenhuma pesquisa encontrada</h4>
                                        <span>Não há pesquisas rápidas disponíveis no momento.</span>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                        <thead>
                                            <tr class="fw-bold text-muted">
                                                <th class="min-w-150px">Título</th>
                                                <th class="min-w-200px">Pergunta</th>
                                                <th class="min-w-150px">Período</th>
                                                <th class="min-w-100px">Status</th>
                                                <th class="min-w-100px text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pesquisas_rapidas as $pesquisa): ?>
                                            <tr>
                                                <td>
                                                    <strong class="text-gray-800"><?= htmlspecialchars($pesquisa['titulo']) ?></strong>
                                                </td>
                                                <td>
                                                    <span class="text-gray-600">
                                                        <?= htmlspecialchars(substr($pesquisa['pergunta'] ?? '', 0, 100)) ?>
                                                        <?= strlen($pesquisa['pergunta'] ?? '') > 100 ? '...' : '' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-gray-600">
                                                        <?= date('d/m/Y H:i', strtotime($pesquisa['data_inicio'])) ?>
                                                        <?php if ($pesquisa['data_fim']): ?>
                                                        <br><small class="text-muted">até <?= date('d/m/Y H:i', strtotime($pesquisa['data_fim'])) ?></small>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($pesquisa['respondida']): ?>
                                                    <span class="badge badge-success">Respondida</span>
                                                    <?php else: ?>
                                                    <span class="badge badge-warning">Pendente</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($pesquisa['respondida']): ?>
                                                    <span class="text-muted small">Já respondida</span>
                                                    <?php elseif (!empty($pesquisa['token_resposta'])): ?>
                                                    <a href="responder_pesquisa.php?token=<?= htmlspecialchars($pesquisa['token_resposta']) ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="ki-duotone ki-pencil fs-6">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Responder
                                                    </a>
                                                    <?php else: ?>
                                                    <span class="text-muted small">Token não disponível</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                            <!--end::Tab pane-->
                        </div>
                        <!--end::Tab content-->
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
// Atualiza contadores nas tabs quando muda filtro
function atualizarContadores() {
    const tabSatisfacao = document.querySelector('[href="#kt_pesquisas_satisfacao_tab"] .badge');
    const tabRapidas = document.querySelector('[href="#kt_pesquisas_rapidas_tab"] .badge');
    
    if (tabSatisfacao) {
        const countSatisfacao = document.querySelector('#kt_pesquisas_satisfacao_tab tbody')?.querySelectorAll('tr').length || 0;
        tabSatisfacao.textContent = countSatisfacao;
    }
    
    if (tabRapidas) {
        const countRapidas = document.querySelector('#kt_pesquisas_rapidas_tab tbody')?.querySelectorAll('tr').length || 0;
        tabRapidas.textContent = countRapidas;
    }
}

// Atualiza contadores ao carregar
document.addEventListener('DOMContentLoaded', function() {
    atualizarContadores();
});

// Submete formulário de filtros
document.getElementById('form-filtros')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const params = new URLSearchParams();
    
    for (const [key, value] of formData.entries()) {
        if (value) {
            params.append(key, value);
        }
    }
    
    window.location.href = 'pesquisas_colaborador.php?' + params.toString();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

