<?php
/**
 * Gestão de Vagas
 */

$page_title = 'Gestão de Vagas';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('vagas.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$where = ["1=1"];
$params = [];

if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "v.empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    }
}

$filtro_status = $_GET['status'] ?? '';
if ($filtro_status) {
    $where[] = "v.status = ?";
    $params[] = $filtro_status;
}

$sql = "
    SELECT v.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           car.nome_cargo,
           COUNT(DISTINCT c.id) as total_candidaturas,
           COUNT(DISTINCT CASE WHEN c.status = 'aprovada' THEN c.id END) as candidaturas_aprovadas
    FROM vagas v
    LEFT JOIN empresas e ON v.empresa_id = e.id
    LEFT JOIN setores s ON v.setor_id = s.id
    LEFT JOIN cargos car ON v.cargo_id = car.id
    LEFT JOIN candidaturas c ON v.id = c.vaga_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY v.id
    ORDER BY v.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vagas = $stmt->fetchAll();

// Busca empresas para filtro
require_once __DIR__ . '/../includes/select_colaborador.php';
$empresas = get_empresas_disponiveis($pdo, $usuario);
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Gestão de Vagas</h2>
                        </div>
                        <div class="card-toolbar">
                            <a href="vaga_add.php" class="btn btn-primary">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Nova Vaga
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <!-- Filtros -->
                        <div class="mb-5">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <select name="status" class="form-select">
                                        <option value="">Todos os status</option>
                                        <option value="aberta" <?= $filtro_status === 'aberta' ? 'selected' : '' ?>>Aberta</option>
                                        <option value="pausada" <?= $filtro_status === 'pausada' ? 'selected' : '' ?>>Pausada</option>
                                        <option value="fechada" <?= $filtro_status === 'fechada' ? 'selected' : '' ?>>Fechada</option>
                                        <option value="cancelada" <?= $filtro_status === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-light-primary">Filtrar</button>
                                    <a href="vagas.php" class="btn btn-light">Limpar</a>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Tabela -->
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Vaga</th>
                                        <th>Empresa</th>
                                        <th>Cargo</th>
                                        <th>Status</th>
                                        <th>Candidaturas</th>
                                        <th>Preenchimento</th>
                                        <th>Portal</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vagas as $vaga): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($vaga['titulo']) ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($vaga['modalidade']) ?> - <?= htmlspecialchars($vaga['tipo_contrato']) ?>
                                            </small>
                                        </td>
                                        <td><?= htmlspecialchars($vaga['empresa_nome']) ?></td>
                                        <td><?= htmlspecialchars($vaga['nome_cargo'] ?? '-') ?></td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'aberta' => 'success',
                                                'pausada' => 'warning',
                                                'fechada' => 'info',
                                                'cancelada' => 'danger'
                                            ];
                                            $color = $status_colors[$vaga['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-light-<?= $color ?>"><?= ucfirst($vaga['status']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= $vaga['total_candidaturas'] ?></strong>
                                            <?php if ($vaga['candidaturas_aprovadas'] > 0): ?>
                                            <br><small class="text-success"><?= $vaga['candidaturas_aprovadas'] ?> aprovadas</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $vaga['quantidade_preenchida'] ?>/<?= $vaga['quantidade_vagas'] ?>
                                            <?php if ($vaga['quantidade_vagas'] > 0): ?>
                                            <?php $percentual = ($vaga['quantidade_preenchida'] / $vaga['quantidade_vagas']) * 100; ?>
                                            <div class="progress" style="height: 20px; width: 100px;">
                                                <div class="progress-bar" style="width: <?= $percentual ?>%"></div>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($vaga['publicar_portal']): ?>
                                            <span class="badge badge-light-success">Publicada</span>
                                            <br>
                                            <a href="../vaga_publica.php?id=<?= $vaga['id'] ?>" target="_blank" class="btn btn-sm btn-light-primary mt-1">
                                                Ver Portal
                                            </a>
                                            <?php else: ?>
                                            <span class="badge badge-light-secondary">Não publicada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="vaga_view.php?id=<?= $vaga['id'] ?>" class="btn btn-sm btn-light-info">
                                                    Ver
                                                </a>
                                                <a href="vaga_edit.php?id=<?= $vaga['id'] ?>" class="btn btn-sm btn-light-warning">
                                                    Editar
                                                </a>
                                                <a href="kanban_selecao.php?vaga_id=<?= $vaga['id'] ?>" class="btn btn-sm btn-light-primary">
                                                    Kanban
                                                </a>
                                                <?php if ($vaga['usar_landing_page_customizada']): ?>
                                                <a href="vaga_landing_page.php?id=<?= $vaga['id'] ?>" class="btn btn-sm btn-light-success">
                                                    Landing Page
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

